# Backend Feature Docs Templates

このディレクトリは、backend/stable の 5 役ワークフローで利用する feature ドキュメントの共通ひな形です。

## 使い方

- feature ごとに `docs/features/<feature-slug>/` を作成する
- このディレクトリのファイルを複製し、feature 用の名前へ変更して使う
- 長期参照する成果物のみ `docs/features/` に保存し、一時メモは `.codex/` に置く

## 推奨ファイル対応

| 用途 | テンプレート | 保存例 |
|---|---|---|
| Orchestrator の進行メモ | `workflow-notes.md` | `docs/features/<feature-slug>/workflow-notes.md` |
| Architect の設計 | `feature-plan.md` | `docs/features/<feature-slug>/<feature-slug>-plan.md` |
| Contract 整理 | `contract-impact.md` | `docs/features/<feature-slug>/contract-impact.md` |
| Implementer の検証メモ | `implementation-notes.md` | `docs/features/<feature-slug>/implementation-notes.md` |
| Reviewer のレビュー結果 | `review-notes.md` | `docs/features/<feature-slug>/review-notes.md` |

## 関連

- `../../architecture/feature-development-workflow.md`
- `../../architecture/overview.md`
- `../../../AGENTS.md`
- `../../../stable/AGENTS.md`