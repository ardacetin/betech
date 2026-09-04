# Agent Instructions

## Git Workflow

- Work directly on `main`; do not create branches, worktrees, or pull requests.
- After implementing and verifying a requested change, commit it and push to `origin/main`.
- If `origin/main` has advanced, fetch and rebase before pushing; never force-push.
- Preserve unrelated user changes and commit only files belonging to the current task.

## Package Manager

- PHP dependencies: Composer (`composer install`).
- Frontend dependencies: npm (`npm ci`, `npm run build`).

## File-Scoped Commands

| Task | Command |
|------|---------|
| PHP syntax | `php -l path/to/file.php` |
| Patch whitespace | `git diff --check -- path/to/file` |
| Tailwind build | `npm run build` |

## Commit Attribution

AI commits must include:

```text
Co-Authored-By: OpenAI Codex <noreply@openai.com>
```

## Key Conventions

- Keep generated `public/css/app.css` synchronized with Tailwind source and template classes.
- Use `apply_patch` for source-file edits.
