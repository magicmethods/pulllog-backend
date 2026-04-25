# Agent Scope Governance

## Purpose

- backend エージェントによる無断の越境編集を防止する
- frontend / contract / docs 変更が必要になった場合のエスカレーションと申し送りを標準化する
- dirty worktree や差分混在状態でも、インシデント時の説明責任を明確に保つ

## Default Scope

- backend エージェントは `backend/` 配下のみを作業対象とする
- `frontend/`、`contract/`、`pulllog-docs/` は、ユーザーが当該リクエスト内で明示的に許可した場合を除き参照専用とする

## Mandatory Gate

`backend/` 外を変更する編集やコマンドを実行する前に、以下をすべて満たす必要がある

1. 越境変更が必要であることが明示されていること
2. ユーザーが同一リクエストスレッド内で明示的に許可していること
3. どのファイルとどのコマンドが対象かをエージェントが確認していること

いずれかが欠ける場合、実装は停止しなければならない

## Required Handoff Packet (when not authorized)

frontend / contract / docs 変更が必要でも許可されていない場合は、以下を含む handoff packet を返す

- 必要な cross-team 変更内容
- 影響を受ける endpoint、runtime 挙動、関連 case やドキュメント
- 必要な検証内容（テスト、smoke check、E2E case、contract validation など）
- rollback 観点とリスクメモ

## Required Record (when authorized)

越境編集が明示許可の上で実行された場合は、同一ターン内で必ず以下を記録する

- ファイル単位の変更要約
- 変更意図と挙動差分
- 実施した検証と未検証箇所
- 既知の残留リスク

## Dirty Worktree Handling

- 変更済みファイルがすべて今回タスク由来だと仮定してはならない
- 今回タスクで高確度に触った変更と、既存差分を区別する
- 帰属が不確かな場合は、その不確実性を報告書内で明記する

## Incident Response Minimum

職掌違反、またはその疑いがある場合は最低限以下を実施する

1. それ以上の越境編集を停止する
2. ファイル単位のインシデント申し送り報告書を作成する
3. 以下のガバナンス規則を追加または更新する
   - `AGENTS.md`
   - `.github/copilot-instructions.md`
   - 関連する `.github/agents/*.agent.md`
   - `docs/architecture/feature-development-workflow.md`
4. ユーザーの指示があるまで、実装を再開しない