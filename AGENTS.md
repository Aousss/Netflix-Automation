# Project Agent Rules & Git Commit Conventions

## Git Commit Message & Branch Naming Rules

All Git commit messages in this repository **MUST** follow the scoped convention:

```text
<type>(<scope>): <short imperative description>
```

### Example
```text
fix(authentication): fix the error when the user cannot login
feat(imap): add live 15-minute Netflix verification code parser
ui(dashboard): improve admin setup modal responsiveness
docs(readme): update Laravel Cloud and TiDB Cloud deployment steps
```

### Allowed Prefix Types & Branch Prefixes

| Category | Commit Prefix (`<type>(<scope>): ...`) | Branch Prefix | Description |
| :--- | :--- | :--- | :--- |
| **New features** | `feat(<scope>): ...` | `feat/` | Introducing a new feature or capability |
| **Bugs fix** | `fix(<scope>): ...` | `fix/` | Fixing a bug or regression |
| **Hotfix (urgent production fix)** | `hotfix(<scope>): ...` | `hotfix/` | Urgent production fix (e.g., 500 error, critical auth/db issue) |
| **Enhancement/Improvement** | `imp(<scope>): ...` | `imp/` | Improving an existing feature, performance, or configuration |
| **UI enhancement** | `ui(<scope>): ...` | `ui/` | Visual, layout, styling, or interaction improvements |
| **Refactoring (code cleaner)** | `refactor(<scope>): ...` | `refactor/` | Code restructuring without changing external behavior |
| **Documentation** | `docs(<scope>): ...` | `docs/` | Creating or updating documentation (`README.md`, `docs/*`, rules) |
| **Testing** | `test(<scope>): ...` | `test/` | Adding or updating unit/feature tests |

### Guidelines
1. Always include a meaningful `<scope>` in parentheses (e.g., `authentication`, `vault`, `billing`, `imap`, `admin`, `dashboard`, `database`, `readme`, `rules`).
2. Keep the `<description>` concise, lowercase, and written in the imperative mood.
3. When creating feature or fix branches, prefix the branch name with the matching prefix from the table above (`feat/`, `fix/`, `hotfix/`, `imp/`, `ui/`, `refactor/`, `docs/`, `test/`).
