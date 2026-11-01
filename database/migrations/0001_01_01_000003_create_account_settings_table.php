<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_settings', function (Blueprint $table) {
            $table->id();
            $table->string('netflix_email');
            $table->text('netflix_password');
            $table->timestamp('password_updated_at')->nullable();
            $table->string('plan_name')->default('Netflix Premium 4K');
            $table->decimal('total_monthly_cost', 8, 2);
            $table->tinyInteger('billing_day_of_month')->default(1);
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_holder')->nullable();
            $table->string('duitnow_qr_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_settings');
    }
};

