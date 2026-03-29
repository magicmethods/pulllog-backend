# Copilot Instructions — Pulllog Backend

## API Schema (Canonical Source)

The authoritative OpenAPI schema for the Pulllog API is maintained in the **contract** workspace:

- Canonical file: `contract/api-schema.yaml`
- Relative path from this workspace root: `../contract/api-schema.yaml`
- When working in the multi-root workspace (`backend.code-workspace` or root `pulllog.code-workspace`), the file is accessible as `${workspaceFolder:contract}/api-schema.yaml`.

> **Important:** Always treat `contract/api-schema.yaml` as the source of truth.  
> When adding or modifying API endpoints in `stable/` or `beta/`, consult the contract schema first.

## Endpoint Implementation Notes

- Beta endpoints live in `beta/hooks/<verb>_<resource>.php` (e.g., `get_apps.php`, `post_auth_login.php`).
- Stable endpoints are routed via `stable/routes/` and handled in `stable/app/Http/Controllers/`.
- Response structure must conform to the schema defined in `contract/api-schema.yaml`.

## General Guidelines

See `AGENTS.md` in this workspace root for full coding conventions, build commands, and PR guidelines.

## Workspace Root Policy Summary

- This workspace follows the shared root policy in `pulllog/AGENTS.md`.
- On Windows, prefer PowerShell-first workflows.
- Do not assume Python is installed. Avoid Python-based helpers unless availability is confirmed.
- For command selection, prioritize existing `composer.json` / `package.json` scripts and committed repo scripts.
- Keep edits scoped and validate with the smallest relevant command first.
