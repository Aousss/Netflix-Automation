<?php

namespace App\Services;

use App\Models\AuditLog;
use Carbon\Carbon;
use DOMDocument;

class NetflixImapService
{
    /**
     * Fetch verification emails from the last 15 minutes via IMAP,
     * falling back to demo data when IMAP is not configured locally.
     */
    public function fetchRecentCodes(): array
    {
        $host = config('services.netflix_imap.host');
        $port = config('services.netflix_imap.port', 993);
        $encryption = config('services.netflix_imap.encryption', 'ssl');
        $user = config('services.netflix_imap.user');
        $pass = config('services.netflix_imap.pass');

        $isConfigured = ! empty($host)
            && $host !== 'mail.yourdomain.com'
            && ! empty($user)
            && ! empty($pass);

        if (! $isConfigured) {
            AuditLog::record('imap.code_fetched', ['mode' => 'demo_fallback']);

            return [
                'mode' => 'demo',
                'items' => $this->demoVerificationRequests(),
            ];
        }

        if (! function_exists('imap_open')) {
            return $this->fetchViaSocket($host, (int) $port, (string) $encryption, $user, $pass);
        }

        $flags = '/imap/' . ($encryption ?: 'ssl') . '/novalidate-cert';
        $mailbox = sprintf('{%s:%d%s}INBOX', $host, (int) $port, $flags);

        $stream = @imap_open($mailbox, $user, $pass, OP_READONLY, 1);
        if (! $stream) {
            AuditLog::record('imap.connection_failed', ['error' => imap_last_error()]);

            return [
                'mode' => 'error',
                'error' => 'Unable to connect to IMAP mailbox: ' . (imap_last_error() ?: 'Connection refused'),
                'items' => [],
            ];
        }

        $sinceDate = now()->subDay()->format('d-M-Y');
        $messageNums = imap_search($stream, 'SINCE "' . $sinceDate . '" FROM "info@account.netflix.com"') ?: [];
        $cutoff = now()->subMinutes(15)->timestamp;
        $items = [];

        rsort($messageNums);

        foreach (array_slice($messageNums, 0, 10) as $msgNo) {
            $overviewList = imap_fetch_overview($stream, (string) $msgNo, 0);
            if (empty($overviewList)) {
                continue;
            }

            $overview = $overviewList[0];
            $timestamp = isset($overview->date) ? strtotime($overview->date) : time();
            if ($timestamp < $cutoff) {
                continue;
            }

            $subject = isset($overview->subject) ? imap_utf8($overview->subject) : 'Netflix Household Verification';
            $rawBody = $this->extractHtmlPart($stream, $msgNo);
            $parsed = $this->parseHtmlBody($rawBody, $subject, $timestamp);

            if ($parsed['approval_url'] || $parsed['code']) {
                $items[] = $parsed;
            }
        }

        imap_close($stream);
        AuditLog::record('imap.code_fetched', ['mode' => 'live', 'count' => count($items)]);

        return [
            'mode' => 'live',
            'items' => $items,
        ];
    }

    /**
     * Parse a Netflix verification HTML email into a structured DTO array.
     */
    public function parseHtmlBody(string $html, string $subject = '', ?int $timestamp = null): array
    {
        $timestamp = $timestamp ?? time();
        $decoded = (str_contains($html, '=3D') || preg_match('/=\r?\n/', $html))
            ? quoted_printable_decode($html)
            : $html;
        $cleanHtml = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $approvalUrl = null;
        if (preg_match('/https:\/\/www\.netflix\.com\/account\/(travel\/verify|update-primary-location)[^\s"\'<>]+/i', $cleanHtml, $matches)) {
            $approvalUrl = str_replace('&amp;', '&', trim($matches[0]));
        }

        $code = null;
        if (preg_match('/(?:code|pin|enter)\s*(?:is|:)?\s*<[^>]+>\s*(\d{4})\s*<\//i', $cleanHtml, $codeMatch)) {
            $code = $codeMatch[1];
        } elseif (preg_match('/\b(\d{4})\b/', strip_tags($cleanHtml), $fallbackMatch)) {
            // Avoid matching current year like 2026 if no explicit label
            if ((int) $fallbackMatch[1] < 2020 || (int) $fallbackMatch[1] > 2035) {
                $code = $fallbackMatch[1];
            }
        }

        $profileName = 'Household Member';
        $deviceType = 'Smart TV / Streaming Device';

        if (class_exists(DOMDocument::class) && trim($cleanHtml) !== '') {
            $dom = new DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $cleanHtml);
            $text = preg_replace('/\s+/', ' ', $dom->textContent ?? '');

            if (preg_match('/Requested by\s+([A-Za-z0-9 _\-]{2,25}?)(?:\s+on|\s+from|\s+at|\s+Device|$)/i', $text, $profMatch)) {
                $profileName = trim($profMatch[1]);
            }
            if (preg_match('/(?:Device|on a|from a)\s*:?\s*([A-Za-z0-9 ()\-_\/]{3,40}?)(?:\s+Location|\s+Time|\s+If this|\.)/i', $text, $devMatch)) {
                $deviceType = trim($devMatch[1]);
            }
        }

        $expiresAt = $timestamp + 900;
        $remainingSeconds = max(0, $expiresAt - time());

        return [
            'id' => substr( sha1($subject . $timestamp . $approvalUrl . $code), 0, 10),
            'subject' => $subject ?: 'Netflix Temporary Access Code',
            'profile_name' => $profileName,
            'device_type' => $deviceType,
            'approval_url' => $approvalUrl,
            'code' => $code,
            'received_at' => Carbon::createFromTimestamp($timestamp)->toIso8601String(),
            'received_human' => Carbon::createFromTimestamp($timestamp)->diffForHumans(),
            'expires_at' => Carbon::createFromTimestamp($expiresAt)->toIso8601String(),
            'remaining_seconds' => $remainingSeconds,
        ];
    }

    protected function extractHtmlPart($stream, int $msgNo): string
    {
        $body = imap_fetchbody($stream, $msgNo, '1.2');
        if (empty(trim($body))) {
            $body = imap_fetchbody($stream, $msgNo, '1');
        }
        if (empty(trim($body))) {
            $body = imap_body($stream, $msgNo);
        }

        return (string) $body;
    }

    /**
     * Pure-PHP SSL Socket IMAP fallback for cloud runtimes (e.g. Laravel Cloud PHP 8.3/8.4)
     * where the legacy c-client ext-imap extension is not installed.
     */
    protected function fetchViaSocket(string $host, int $port, string $encryption, string $user, string $pass): array
    {
        $transport = strtolower($encryption) === 'ssl' ? 'ssl://' : 'tcp://';
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $fp = @stream_socket_client("{$transport}{$host}:{$port}", $errno, $errstr, 8, STREAM_CLIENT_CONNECT, $context);
        if (! $fp) {
            AuditLog::record('imap.connection_failed', ['transport' => 'socket', 'error' => $errstr ?: "Errno {$errno}"]);

            return [
                'mode' => 'error',
                'error' => "Unable to connect to IMAP mailbox ({$host}:{$port}): " . ($errstr ?: 'Connection refused'),
                'items' => [],
            ];
        }

        stream_set_timeout($fp, 8);
        fgets($fp); // Server greeting

        $sendCommand = function (string $tag, string $cmd) use ($fp): string {
            fwrite($fp, "{$tag} {$cmd}\r\n");
            $response = '';
            while (! feof($fp)) {
                $line = fgets($fp, 8192);
                if ($line === false) {
                    break;
                }
                $response .= $line;
                if (str_starts_with($line, "{$tag} OK") || str_starts_with($line, "{$tag} NO") || str_starts_with($line, "{$tag} BAD")) {
                    break;
                }
            }

            return $response;
        };

        $escapedUser = '"' . addcslashes($user, '\\"') . '"';
        $escapedPass = '"' . addcslashes($pass, '\\"') . '"';

        $loginResp = $sendCommand('A01', "LOGIN {$escapedUser} {$escapedPass}");
        if (! str_contains($loginResp, 'A01 OK')) {
            fclose($fp);
            AuditLog::record('imap.connection_failed', ['transport' => 'socket', 'error' => 'Authentication failed']);

            return [
                'mode' => 'error',
                'error' => 'IMAP authentication failed. Verify NETFLIX_IMAP_USER and NETFLIX_IMAP_PASS.',
                'items' => [],
            ];
        }

        $sendCommand('A02', 'EXAMINE INBOX');
        $sinceDate = now()->subDay()->format('d-M-Y');
        $searchResp = $sendCommand('A03', 'SEARCH SINCE "' . $sinceDate . '" FROM "info@account.netflix.com"');

        $msgNums = [];
        if (preg_match('/^\* SEARCH(.*)$/m', $searchResp, $m)) {
            $msgNums = array_values(array_filter( preg_split('/\s+/', trim($m[1]))));
        }

        rsort($msgNums);
        $cutoff = now()->subMinutes(15)->timestamp;
        $items = [];

        foreach (array_slice($msgNums, 0, 8) as $idx => $msgNo) {
            $raw = $sendCommand('F' . $idx, "FETCH {$msgNo} (INTERNALDATE BODY.PEEK[HEADER.FIELDS (SUBJECT DATE)] BODY.PEEK[TEXT])");
            $timestamp = time();
            if (preg_match('/INTERNALDATE "([^"]+)"/i', $raw, $dm)) {
                $timestamp = strtotime($dm[1]) ?: time();
            }
            if ($timestamp < $cutoff) {
                continue;
            }

            $subject = 'Netflix Household Verification';
            if (preg_match('/^Subject:\s*(.+)$/mi', $raw, $sm)) {
                $subject = trim(mb_decode_mimeheader(trim($sm[1])));
            }

            $parsed = $this->parseHtmlBody($raw, $subject, $timestamp);
            if ($parsed['approval_url'] || $parsed['code']) {
                $items[] = $parsed;
            }
        }

        $sendCommand('A99', 'LOGOUT');
        fclose($fp);

        AuditLog::record('imap.code_fetched', ['mode' => 'live_socket', 'count' => count($items)]);

        return [
            'mode' => 'live',
            'items' => $items,
        ];
    }

    protected function demoVerificationRequests(): array
    {
        $sampleHtml1 = '
            <html><body>
                <table>
                    <tr><td>Requested by Alex on Device: Samsung QLED 4K Smart TV. Location: Kuala Lumpur.</td></tr>
                    <tr><td>Enter this code on your TV: <strong>8492</strong></td></tr>
                    <tr><td><a href="https://www.netflix.com/account/travel/verify?nftoken=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.demo_travel_token_8492&amp;messageGuid=d91a-482b">Get Code / Approve Temporary Access</a></td></tr>
                </table>
            </body></html>
        ';

        $sampleHtml2 = '
            <html><body>
                <table>
                    <tr><td>Requested by Sarah on Device: LG OLED WebOS TV. Location: Petaling Jaya.</td></tr>
                    <tr><td><a href="https://www.netflix.com/account/update-primary-location?nftoken=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.demo_household_token_3109&amp;messageGuid=f44c-991e">Yes, This Was Me — Update Netflix Household</a></td></tr>
                </table>
            </body></html>
        ';

        return [
            $this->parseHtmlBody(
                $sampleHtml1,
                'Your Netflix temporary access code',
                now()->subMinutes(2)->subSeconds(18)->timestamp
            ),
            $this->parseHtmlBody(
                $sampleHtml2,
                'How to update your Netflix Household',
                now()->subMinutes(6)->subSeconds(42)->timestamp
            ),
        ];
    }
}
