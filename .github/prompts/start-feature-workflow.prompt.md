---
description: Start the stable backend feature development workflow from an issue, requirement, or specification
name: Start Backend Feature Workflow
argument-hint: Issue, requirement, or backend feature specification to orchestrate
agent: backend-orch-feature
---

Start the stable backend feature development workflow for the provided request.

Use the repository's 5-role workflow and coordinate the work through backend-orch-feature first.

Required references:
- [Feature workflow](../../docs/architecture/feature-development-workflow.md)
- [Architecture overview](../../docs/architecture/overview.md)
- [Backend rules](../../AGENTS.md)
- [Stable rules](../../stable/AGENTS.md)
- [backend-orch-feature](../agents/backend-orch-feature.agent.md)
- [backend-arch-api](../agents/backend-arch-api.agent.md)
- [backend-design-contract](../agents/backend-design-contract.agent.md)
- [backend-impl-feature](../agents/backend-impl-feature.agent.md)
- [backend-review-feature](../agents/backend-review-feature.agent.md)

Instructions:
- Treat the user input as the primary request source unless it explicitly references a stricter source of truth
- Restate the request and identify explicit non-goals first
- Keep the execution scope fixed to backend/stable
- Decide whether API contract alignment is required before implementation
- Use the standard stage order from the workflow document
- Do not skip architecture for non-trivial work
- Only include the contract stage when endpoint shape, status code, validation, or documented behavior may change
- If frontend re-verification is required, say so clearly and explain why
- Keep the plan minimal and grounded in the existing codebase

Return the result using this structure:

```text
Request summary
-

Scope and non-goals
-

Required stages
-

Current stage status
-

Blockers or open questions
-

Recommended next action
-
```

User input:

{{input}}