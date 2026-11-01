# Git Commit Message & Branch Naming Rules

All Git commit messages in this repository **MUST** follow the scoped convention:

```text
<type>(<scope>): <short imperative description>
```

## Example
```text
fix(authentication): fix the error when the user cannot login
```

## Allowed Prefix Types & Branch Prefixes

| Category | Commit Prefix (`<type>(<scope>): ...`) | Branch Prefix |
| :--- | :--- | :--- |
| **New features** | `feat(<scope>): ...` | `feat/` |
| **Bugs fix** | `fix(<scope>): ...` | `fix/` |
| **Hotfix (urgent production fix)** | `hotfix(<scope>): ...` | `hotfix/` |
| **Enhancement/Improvement** | `imp(<scope>): ...` | `imp/` |
| **UI enhancement** | `ui(<scope>): ...` | `ui/` |
| **Refactoring (code cleaner)** | `refactor(<scope>): ...` | `refactor/` |
| **Documentation** | `docs(<scope>): ...` | `docs/` |
| **Testing** | `test(<scope>): ...` | `test/` |

