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

## Repository-wide instructions for Playwright E2E operations

### Purpose
This repository uses GitHub Copilot custom agents to design, implement, run, debug, and review Playwright E2E tests.
Always follow the repository E2E architecture and case manifest definitions before making changes.

### Required references
Before working on any E2E task, read these files when they exist in the **frontend** workspace:

- `${workspaceFolder:frontend}/docs/architecture/e2e-test.md`
- `${workspaceFolder:frontend}/e2e/cases/case.schema.json`
- related case manifest files under `${workspaceFolder:frontend}/e2e/cases/`
- `${workspaceFolder:frontend}/e2e/templates/report-template.md`
- `${workspaceFolder:frontend}/e2e/templates/index-template.md`
- `${workspaceFolder:frontend}/e2e/templates/evidence-template.html` when PDF archival or evidence layout is involved
- `${workspaceFolder:frontend}/e2e/templates/pulllog/report-template.md`, `index-template.md`, and `evidence-template.html` when using the Pulllog-specific default set
- `${workspaceFolder:frontend}/.env.e2e` when template overrides, account resolution, or environment-specific execution behavior are relevant
- existing Playwright config, fixtures, helpers, reporters, and test utilities in the frontend workspace

### General rules
- Use the existing project structure and naming conventions.
- Prefer minimal diffs.
- Do not rewrite unrelated tests.
- Do not hardcode secrets, passwords, or tokens in source files or manifests.
- Keep environment-specific values outside of test code whenever possible.
- Use stable locators first: `getByRole`, `getByLabel`, `getByPlaceholder`, `getByTestId`.
- Do not rely on brittle CSS selectors unless there is no practical alternative.
- Do not use `waitForTimeout` unless there is no reliable state-based wait and the reason is documented.
- Prefer explicit state-based waits and assertions.
- Keep one test focused on one behavior whenever possible.
- Prefer the frontend repository E2E scripts (`pnpm run test:e2e`, `test:e2e:case`, `test:e2e:tag`) over ad-hoc raw commands.
- When narrowing project scope explicitly, prefer a comma-separated selector such as `--project=chromium,ipad-pro-11,iphone-14`.
- Use reusable fixtures, helpers, and page objects when it reduces duplication without hiding test intent.

### E2E execution policy
- E2E tests are manifest-driven.
- The standard default project matrix is PC `chromium`, tablet `ipad-pro-11`, and smartphone `iphone-14`.
- Use the frontend repository runner scripts and this standard matrix unless the task or manifest intentionally overrides it.
- BaseURL, account key, target page, excluded navigation coverage, and report behavior must come from the case manifest when possible.
- When a new case is needed, create or update a manifest file instead of burying case-specific assumptions inside the spec.
- If the requested behavior should not be covered by E2E, say so clearly and explain whether it belongs in unit or integration tests.
- Multi-project runs for the same case should aggregate into one case report that shows all executed project results, not only the first project.

### Reporting and evidence policy
- Every E2E execution must produce a Markdown report.
- Markdown case reports and the daily index should be rendered from the shared templates under `${workspaceFolder:frontend}/e2e/templates/` whenever available.
- The current repository default template set may be activated globally via `${workspaceFolder:frontend}/.env.e2e` using `PLAYWRIGHT_E2E_TEMPLATE_DIR=pulllog`.
- Global template overrides may use `PLAYWRIGHT_E2E_TEMPLATE_DIR`, `PLAYWRIGHT_E2E_REPORT_TEMPLATE`, `PLAYWRIGHT_E2E_INDEX_TEMPLATE`, and `PLAYWRIGHT_E2E_EVIDENCE_TEMPLATE`.
- Case-specific overrides may use `report.templates.markdown` and `report.templates.evidence`.
- The Markdown report must include execution metadata, key assertions, snapshot references, failure summary when applicable, and a per-project result summary when more than one project is executed.
- When a case succeeds and the manifest allows it, convert the Markdown report to PDF and store it as evidence.
- PDF evidence should use the active evidence template and, when multiple standard-matrix projects run for the same case, include the fixed PC / Tablet / SP comparison table while preserving the per-project detail sections.
- When a case fails, keep Markdown, screenshots, traces, and logs, but do not generate final PDF evidence unless explicitly requested.
- Evidence files must use deterministic naming and folder structure.

### Manifest policy
- Treat `${workspaceFolder:frontend}/e2e/cases/case.schema.json` as the source of truth for case structure.
- Prefer JSON for case manifests.
- Store only account keys or references in manifests, not raw credentials.
- Manifest filenames should normally match the case id (for example, `auth-apps-smoke.json`).
- Use `execution.project` only for case-specific overrides; otherwise the standard default matrix applies.
- Support per-case controls such as:
  - environment selection
  - target page
  - navigation start point
  - excluded flows
  - prerequisite state
  - report behavior
  - template overrides
  - execution overrides
  - tags

### Expected workflow
1. Read the frontend architecture, manifest, `.env.e2e`, and relevant template references.
2. Confirm whether a case already exists.
3. Design or update the scenario.
4. Implement or update Playwright code with minimal changes.
5. Run the relevant tests from the frontend workspace.
6. Generate a Markdown report from the shared template layout, ensuring all executed project results are represented.
7. If successful and allowed, archive the evidence as PDF using the active HTML evidence template.
8. Review for stability, maintainability, coverage, and template consistency.

### Output expectations
- Be explicit about assumptions.
- Distinguish facts from guesses.
- When blocked, report the exact blocker.
- When backend changes may affect E2E, explain which frontend case manifests or reports should be re-verified and how the result was verified

## General Guidelines

See `AGENTS.md` in this workspace root for full coding conventions, build commands, and PR guidelines.

## Workspace Root Policy Summary

- This workspace follows the shared root policy in `pulllog/AGENTS.md`.
- On Windows, prefer PowerShell-first workflows.
- Do not assume Python is installed. Avoid Python-based helpers unless availability is confirmed.
- For command selection, prioritize existing `composer.json` / `package.json` scripts and committed repo scripts.
- Keep edits scoped and validate with the smallest relevant command first.
