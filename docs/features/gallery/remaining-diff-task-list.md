# Remaining Diff Task List (Post Commit-2)

## Purpose

- `feat(gallery): add bootstrap endpoint and feature tests`
- `fix(gallery): stabilize direct upload auth and cors preflight`

上記 2 コミット後に残っている差分を、別タスクとして切り分けるための整理メモ。

## Remaining Diff Groups

### Group A: Agent / Governance Docs

- `.github/agents/backend-impl-feature.agent.md`
- `.github/agents/backend-orch-feature.agent.md`
- `.github/copilot-instructions.md`
- `AGENTS.md`
- `docs/architecture/feature-development-workflow.md`
- `docs/operations/agent-scope-governance.md` (new)

Intent:

- backend エージェントの職掌境界と越境時 handoff ルールの明文化。

Suggested task title:

- `docs(agents): enforce backend authority boundary`

Suggested validation:

- 主要指示ファイル間で矛盾がないかの目視確認。

### Group B: Runtime / E2E Operation Docs

- `README.md`
- `docs/operations/local-development-runtime.md`

Intent:

- local-dev/e2e レーンの説明と migration 注意点の更新。

Suggested task title:

- `docs(ops): align local-dev and e2e runtime guidance`

Suggested validation:

- 記載コマンドの存在確認（`composer` スクリプト、`php artisan` コマンド）。

### Group C: Gallery Runtime Hardening (Non-bootstrap / Non-direct-upload-split)

- `stable/app/Http/Controllers/Gallery/GalleryAssetController.php`
- `stable/app/Http/Controllers/Gallery/GalleryUsageController.php`
- `stable/database/migrations/2025_07_21_100620_create_logs_with_money_view.php`
- `stable/database/migrations/2026_04_19_120000_ensure_gallery_storage_disk_enum_values.php` (new)
- `stable/database/migrations/2026_04_25_140000_add_gallery_assets_active_listing_index.php` (new)
- `stable/app/Console/Commands/GalleryEnsureDisposableAsset.php` (new)
- `stable/tests/Feature/Gallery/GalleryEnsureDisposableAssetCommandTest.php` (new)

Intent:

- gallery 運用安定化（slow log、soft-delete 重複再利用、SQLite 互換、enum drift 補修、一覧向け index、E2E 補助コマンド）。

Suggested task title:

- `feat(gallery): harden runtime and maintenance paths`

Suggested validation:

- `php artisan test tests/Feature/Gallery/GalleryEnsureDisposableAssetCommandTest.php`
- `php artisan test tests/Feature/Gallery/ --filter=GalleryAssetTest`
- `php artisan test tests/Feature/Gallery/ --filter=GalleryUploadTicketTest`

### Group D: Gallery Planning / Notes Docs

- `docs/features/gallery/design-resync-minimum-proposal.md` (new)
- `docs/features/gallery/implementation-notes.md` (new)
- `docs/features/gallery/overreach-impact-notes.md` (new)
- `docs/features/gallery/workflow-notes.md` (new)

Intent:

- 設計再同期案、実装メモ、影響整理、進行メモの文書化。

Suggested task title:

- `docs(gallery): add resync proposal and workflow notes`

Suggested validation:

- 文書間リンクと参照ファイルの存在確認。

## Recommended Order

1. Group A
2. Group B
3. Group C
4. Group D

## Notes

- Group C は実装差分を含むため、docs-only の Group A/B/D と分離してレビュー負荷を下げる。
- Group D は計画/メモが中心のため、実装コミットと混在させない。