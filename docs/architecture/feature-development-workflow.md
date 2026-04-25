# バックエンド stable 機能開発ワークフロー

## 1. 目的

このドキュメントは、PullLog backend/stable における機能開発の標準ワークフローを定義するためのひな形です。
Issue、要件書、要求仕様から着手し、設計、契約整合、実装、レビューまでを 5 役の専用エージェントで段階的に進める前提で運用します。

本ワークフローの目的は以下です。

- 各段階の責務を明確にする
- handoff 条件と差し戻し条件を固定する
- API 契約、DB 影響、認証認可、実装、レビューの抜け漏れを防ぐ
- 最小差分かつ現行 Laravel 構成に整合した feature delivery を行う

---

## 2. 適用範囲

このワークフローは、backend/stable における以下の変更に適用します。

- 新規 API エンドポイント追加
- 既存 API の仕様変更、バリデーション変更、認可変更
- controller、request、service、resource、model、migration を伴う機能追加や改善
- frontend や contract に影響する backend 仕様変更

以下は原則として本ワークフローの対象外です。

- beta の hook や mock 実装の保守
- typo のみの修正
- ドキュメントだけの軽微修正
- release script だけの更新

対象外であっても、stable API の挙動や契約に影響する場合は本ワークフローを適用してよいです。

---

## 3. 前提方針

- スコープは backend/stable に固定し、beta は考慮しない
- API 契約の正本は ../contract/api-schema.yaml とする
- stable のエンドポイントは routes/api.php と app/Http/Controllers 配下を起点に扱う
- 既存の controller、form request、service、resource、model、Feature test を優先し、不必要な新規抽象化は避ける
- 実装は最小差分を原則とし、無関係なリファクタリングを混ぜない
- 変更に応じて auth、authorization、status code、error shape、DB 影響を明示する

### 3.1 職掌境界

- backend エージェントの既定作業範囲は `backend/` 配下のみとする
- `frontend/`、`contract/`、`pulllog-docs/` は参照のみ許可し、編集・生成・削除はユーザーの明示許可以前は禁止とする
- frontend / contract / docs の修正が必要でも未許可であれば、実装を止めて handoff packet を返す
- handoff packet には最低限、必要変更、影響範囲、検証期待値、rollback 観点を含める
- 越境変更が明示許可された場合でも、同ターンでファイル単位の変更要約、意図した挙動差分、実施検証、残留リスクを記録する
- edit / terminal 権限を持つエージェントは、この職掌境界を convenience より優先して強制する

詳細運用は `docs/operations/agent-scope-governance.md` を参照する。

---

## 4. 役割一覧

| 役割 | 担当 | 主な責務 |
|---|---|---|
| 司令塔 | backend-orch-feature | 要求整理、段階管理、handoff、差し戻し管理、最終取りまとめ |
| 設計 | backend-arch-api | Laravel stable の最小構成設計、影響範囲整理、DB と認証認可の整理 |
| 契約整合 | backend-design-contract | API 契約変更要否、status や response shape、frontend 影響の整理 |
| 実装 | backend-impl-feature | 最小差分実装、必要なテスト追加、検証実施 |
| レビュー | backend-review-feature | 要求適合、契約整合、認証認可、DB 影響、テスト妥当性、回帰リスク評価 |

---

## 5. 標準フロー

```text
Request / Issue / Spec
  -> backend-orch-feature
    -> backend-arch-api
      -> backend-design-contract
        -> backend-impl-feature
          -> backend-review-feature
            -> backend-orch-feature summary
```

API 契約に変更がない場合、backend-design-contract は省略してもよいです。
ただし、endpoint shape、status code、validation、documented behavior のいずれかが変わる可能性がある場合は省略しません。

---

## 6. ステージ定義

### 6.1 backend-orch-feature

#### 入力

- Issue
- 要件書
- 要求仕様
- ユーザーの自然言語依頼

#### やること

- 要求の要約
- 非対象の明確化
- backend/stable に閉じるかの確認
- contract 整合確認の要否判定
- 実行ステージの決定
- handoff 順序の管理

#### 出力

- Request summary
- Scope and non-goals
- Required stages
- Current stage status
- Blockers or open questions
- Recommended next action

#### 次へ進める条件

- 要求と非対象が読み取れる
- 設計に必要な入力が不足していない
- contract 影響の有無が暫定でも整理されている

#### 差し戻し条件

- 要件が曖昧で acceptance criteria を定義できない
- API 前提が不明
- stable 範囲で閉じるか判定不能

---

### 6.2 backend-arch-api

#### 入力

- orchestrator の要求整理結果
- 既存 stable 実装
- 必要に応じて docs/features や docs/integrations の既存資料
- ../contract/api-schema.yaml

#### やること

- 最小構成の実装設計
- 影響ファイルの特定
- route、controller、request、service、resource、model の役割整理
- auth、authorization、DB 影響の整理
- テスト方針、実装順序、acceptance criteria の定義

#### 出力

- Requirement summary
- Non-goals
- Proposed architecture
- Impacted files
- API and contract alignment
- Verification strategy
- Implementation order
- Acceptance criteria
- Risks and open questions

#### 次へ進める条件

- 実装担当が迷わず着手できる粒度まで構造が整理されている
- API と DB の前提が説明できる
- contract 影響がある場合、その事実が明記されている

#### 差し戻し条件

- 要件に対して設計の粒度が粗すぎる
- API レスポンス前提が契約と整合しない
- 新規 abstraction の必要性が説明できない

---

### 6.3 backend-design-contract

#### 入力

- 要件
- backend-arch-api の設計結果
- 既存 stable 実装
- ../contract/api-schema.yaml と関連 path schema

#### やること

- contract 変更要否の判定
- path、schema、status code、error shape の整理
- additive 変更か breaking change かの整理
- frontend 再確認範囲の明示

#### 出力

- Contract impact summary
- Affected paths and schemas
- Response and error expectations
- Compatibility and drift risk
- Frontend re-verification scope
- Recommended next action

#### 次へ進める条件

- 実装担当が undocumented change を持ち込まずに作業できる
- contract 更新要否が判断できる
- frontend 影響の有無が説明できる

#### 差し戻し条件

- path や schema の影響が曖昧
- response shape や status code の整理が不足している
- drift の存在を説明できていない

---

### 6.4 backend-impl-feature

#### 入力

- backend-arch-api の設計結果
- backend-design-contract の整理結果
- 既存 stable コードベース

#### やること

- 最小差分での機能実装
- 必要に応じた PHPUnit Feature または Unit テスト追加や更新
- migration、config、resource、request など必要な層への反映
- 変更に見合う最小限の検証実施

#### 出力

- changed files
- tests or checks added and run
- manual verification performed when applicable
- unresolved risks or follow-up items

#### 次へ進める条件

- 要求に対応する実装が完了している
- 必要な検証結果を提示できる
- 設計や contract と差分がある場合、その理由が説明されている

#### 差し戻し条件

- 設計未確定のまま実装判断が必要になった
- contract 前提の矛盾でレスポンスを決めきれない
- stable 単独では成立しない依存がある

---

### 6.5 backend-review-feature

#### 入力

- 設計結果
- contract 整理結果
- 実装コード
- テストと検証結果

#### やること

- 要求適合性の確認
- repository rule 順守確認
- API 契約整合、認証認可、DB 影響、回帰リスクの確認
- Must Fix / Should Fix / Nice to Have / Final Verdict の提示

#### 出力

- Must Fix
- Should Fix
- Nice to Have
- Final Verdict

#### 完了条件

- blocking issue の有無が明確
- ship recommendation が明確

---

## 7. 成果物テンプレート

各ステージの成果物は、最低でも以下を含むことを推奨します。

| ステージ | 最低限の成果物 |
|---|---|
| Orchestrator | 要求要約、非対象、必要ステージ、阻害要因 |
| Architect | 影響範囲、route と controller、auth と DB 影響、acceptance criteria |
| Contract alignment | path と schema、status code、error shape、frontend 影響 |
| Implementer | 変更ファイル、検証結果、残リスク |
| Reviewer | Must Fix / Should Fix / Nice to Have / Final Verdict |

実際の feature 文書を作る場合は、`docs/features/_templates/` 配下のひな形を複製して使います。

以下のテンプレートを、そのままベースとして利用してよいです。

### 7.1 Orchestrator テンプレート

```text
Request summary
- 対象:
- 背景:
- 期待される結果:

Scope and non-goals
- 対象範囲:
- 非対象:

Required stages
- Architect: 必須 / 任意
- Contract alignment: 必須 / 任意
- Implementer: 必須
- Reviewer: 必須

Current stage status
- 現在ステージ:
- 進行可否:

Blockers or open questions
-

Recommended next action
-
```

### 7.2 Architect テンプレート

```text
Requirement summary
-

Non-goals
-

Proposed architecture
- route / controller entry point:
- request validation:
- service or domain flow:
- response formatting:
- reuse strategy:

Impacted files
-

API and contract alignment
- 対象 endpoint:
- contract 整合:
- contract 更新要否:
- frontend 影響:
- drift リスク:

Auth and data impact
- auth / authorization:
- middleware:
- DB / migration:
- backward compatibility:

Verification strategy
-

Implementation order
1.
2.
3.

Acceptance criteria
-

Risks and open questions
-
```

### 7.3 Contract alignment テンプレート

```text
Contract impact summary
- contract 変更要否:
- 変更理由:

Affected paths and schemas
- path:
- operation:
- request:
- response:
- schema:

Response and error expectations
- success status:
- validation error:
- auth error:
- domain error:

Compatibility and drift risk
- additive / breaking:
- 既存 drift:

Frontend re-verification scope
- 影響画面:
- 再確認項目:

Recommended next action
-
```

### 7.4 Implementer テンプレート

```text
Changed files
-

Implementation notes
-

Tests or checks added and run
-

Manual verification performed when applicable
-

Unresolved risks or follow-up items
-
```

### 7.5 Reviewer テンプレート

```text
Must Fix
-

Should Fix
-

Nice to Have
-

Final Verdict
- Ship recommendation:
- Residual risk:
```

---

## 8. 成果物の保存先ポリシー

このワークフローでは、成果物を「長期参照するもの」と「一時的な作業メモ」に分けて扱います。

### 8.1 基本方針

- 長期参照する成果物は `docs/` 配下に保存する
- 一時的な作業メモ、壁打ち、未整理の検討内容は `.codex/` に置く
- 差し戻しメッセージ本文は通常はチャット上の運用メッセージとして扱い、逐一永続化しない
- 差し戻しによって確定した判断、blocker、仕様変更は成果物へ反映して永続化する

### 8.2 推奨保存先

feature ごとの長期成果物は、原則として `docs/features/<feature-slug>/` に保存します。

| 成果物 | 推奨保存先 | 永続化の原則 |
|---|---|---|
| Orchestrator の初期整理 | `docs/features/<feature-slug>/workflow-notes.md` | 複数日にまたがる作業、contract 依存、承認判断がある場合は保存 |
| Architect の設計結果 | `docs/features/<feature-slug>/*-plan.md` または `*-spec.md` | 原則として保存 |
| Contract の整理結果 | `docs/features/<feature-slug>/contract-impact.md` | contract 変更要否や drift 判断がある場合は保存 |
| Implementer の検証サマリ | `docs/features/<feature-slug>/implementation-notes.md` | テスト結果、既知の制約、残リスクがある場合に保存 |
| Reviewer のレビュー結果 | `docs/features/<feature-slug>/review-notes.md` | Must Fix がある場合、または ship recommendation の根拠を残す必要がある場合は保存 |
| 一時メモ、未確定案 | `.codex/` | 必要時のみ保存 |

`<feature-slug>` は feature 名や issue 名に対応する短い識別子を使います。既存の feature 文書がある場合は、その配下へ追記または関連ファイルを追加します。

利用開始時は、以下の共通ひな形を基に feature ディレクトリを構成します。

- `docs/features/_templates/workflow-notes.md`
- `docs/features/_templates/feature-plan.md`
- `docs/features/_templates/contract-impact.md`
- `docs/features/_templates/implementation-notes.md`
- `docs/features/_templates/review-notes.md`

### 8.3 保存必須のもの

以下はチャットだけで済ませず、原則として永続化します。

- `backend-arch-api` の最終設計結果
- `backend-design-contract` の最終整理結果
- contract 変更要否や drift 判断
- レビュー段階での Must Fix
- 次回セッションへ持ち越す blocker

### 8.4 保存不要または任意のもの

以下は通常、永続化必須ではありません。

- 軽微な差し戻しメッセージ本文
- 単発セッション内で完結する Orchestrator の進行メッセージ
- git diff から十分に追跡できる単純な実装メモ

ただし、以下のいずれかに当てはまる場合は要点だけ保存します。

- 要件や非対象が変わった
- 実装前提が変わった
- API / contract 依存が新たに判明した
- 次の担当者がその判断を参照しないと再開できない

### 8.5 差し戻し時の永続化ルール

差し戻しメッセージそのものは、通常はチャット上で伝えるだけで構いません。
ただし差し戻しにより、以下が確定した場合は成果物へ反映します。

- blocker の内容
- 誰に戻すか
- 次に必要な判断または入力
- 仕様、非対象、API 前提の変更点

保存先は、差し戻し対象に最も近い成果物を優先します。

- 要件整理の差し戻し: `workflow-notes.md` または設計書
- 設計差し戻し: `*-plan.md` または `*-spec.md`
- contract 整理差し戻し: `contract-impact.md`
- 実装差し戻し: 実装ノートまたは設計書追記
- レビュー差し戻し: `review-notes.md`

---

## 9. 差し戻しメッセージ例

差し戻し時は、何が不足しているかを短く具体的に返すことを推奨します。

### 9.1 Orchestrator から差し戻す場合

```text
現時点では次ステージへ進めません。
理由: 要件の完了条件が不明で、設計対象範囲を固定できません。
不足情報:
-
必要な次アクション:
-
```

### 9.2 Architect から差し戻す場合

```text
現時点では実装設計を確定できません。
理由: API 前提が `../contract/api-schema.yaml` と一致していません。
確認が必要な点:
-
提案:
- contract 整理を先行する
- route / response 前提を見直す
```

### 9.3 Contract alignment から差し戻す場合

```text
現時点では contract 整理を確定できません。
理由: success response または error shape の前提が不足しています。
不足している情報:
-
必要な次アクション:
- architect で response / status の前提を再整理する
```

### 9.4 Implementer から差し戻す場合

```text
現時点では安全に実装を進められません。
理由: 設計または contract guidance に矛盾があり、response behavior を一意に決められません。
衝突している内容:
-
必要な次アクション:
- architect または contract alignment で前提を再確定する
```

### 9.5 Reviewer から差し戻す場合

```text
現時点では ship recommendation を出せません。
理由: blocking issue が残っています。
Must Fix:
-
再確認が必要な項目:
-
```

---

## 10. 実運用時の開始テンプレート

通常は prompt の `Start Backend Feature Workflow` か、agent の `backend-orch-feature` を入口にします。起動する際は、以下のような入力形式を推奨します。

```text
対象: <issue or feature name>
入力資料:
- <issue link or summary>
- <spec or requirement note>

依頼内容:
- 5役ワークフローで stable backend 開発を開始
- contract 影響があれば明示
- 最終的に設計、contract 整理、実装、レビューの順で進める
```

---

## 11. 今後の拡張候補

- feature 種別ごとの派生テンプレートを `docs/features/` に追加する
- migration-heavy な作業向けの DB 変更テンプレートを追加する

---

## 12. 関連ドキュメント

- `docs/README.md`
- `docs/architecture/overview.md`
- `docs/features/_templates/README.md`
- `docs/features/_templates/workflow-notes.md`
- `docs/features/_templates/feature-plan.md`
- `docs/features/_templates/contract-impact.md`
- `docs/features/_templates/implementation-notes.md`
- `docs/features/_templates/review-notes.md`
- `controller-map.md`
- `../contract/api-schema.yaml`
- `.github/agents/backend-orch-feature.agent.md`
- `.github/agents/backend-arch-api.agent.md`
- `.github/agents/backend-design-contract.agent.md`
- `.github/agents/backend-impl-feature.agent.md`
- `.github/agents/backend-review-feature.agent.md`