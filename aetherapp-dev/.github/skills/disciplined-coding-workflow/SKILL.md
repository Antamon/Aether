---
name: disciplined-coding-workflow
description: "Use for focused code changes, bug fixes, and maintenance in an existing repository. Guides local code routing, falsifiable hypotheses, minimal edits, targeted validation, and end-to-end completion without broad exploratory drift."
argument-hint: "Describe the requested change, known file or symbol, and any failing behavior or command."
user-invocable: true
---

# Disciplined Coding Workflow

Use this workflow when changing an existing codebase and the request is concrete enough to act on.

## Outcome

Produce a small, verifiable change that addresses the root cause, respects the repository's existing patterns, and leaves the user with a clear account of what changed and what was validated.

## Procedure

### 1. Establish the local anchor

Start with the most concrete available anchor:

- named file, symbol, failing behavior, failing command, test, or call site
- if none is named, perform one targeted search to find the nearest relevant implementation

Read only enough nearby code to identify:

- the code path that directly decides, computes, mutates, or controls the behavior
- one falsifiable hypothesis about the cause or intended behavior
- one cheap check that could disconfirm that hypothesis

If the initial file only forwards, wires, registers, or renders the behavior, follow one nearby hop to the owning implementation. Avoid mapping the whole repository.

### 2. Choose the smallest testable action

State the working hypothesis internally or briefly to the user, then choose the smallest edit or reversible probe that can test it.

Prefer existing helpers, APIs, conventions, and test patterns. Preserve public interfaces unless the request requires a contract change. Do not refactor unrelated code.

If several paths look plausible, choose the one with the clearest discriminating check. Take at most one additional nearby read when an abstraction boundary or dependency is genuinely unresolved; then act.

### 3. Edit narrowly

Before editing, tell the user which focused change is being made. Use the repository's normal editing mechanism and keep the patch scoped to the behavioral surface.

Respect existing user changes in a dirty worktree. Inspect and work with overlapping changes; never revert them without explicit permission. Do not commit or create branches unless requested.

### 4. Validate immediately

After the first substantive edit, run one focused executable check before further reading or patching:

1. the cheapest behavior-scoped or failing check
2. a narrow test for the touched slice
3. a narrow compile, lint, or typecheck command
4. a diff inspection only when no executable check is available

Keep the first validation within the touched scope. If it fails and supports the hypothesis, repair that same slice and rerun the same check. If it falsifies the hypothesis, follow one nearby hop to the code that actually controls the behavior, then reassess. If it is ambiguous, perform one nearby disambiguating read or call-site check.

### 5. Complete adjacent work

Once focused validation succeeds, make only the adjacent follow-up edits needed to finish the request. Rerun focused validation after each meaningful follow-up. Add or update tests when the behavioral risk warrants them.

Finish with at least one post-edit executable validation whenever the environment provides one. Report unavailable commands, unrelated failures, and remaining risk instead of hiding them.

## Decision Rules

- **No clear workflow:** ask for the desired outcome, scope, and whether the user wants a checklist or full workflow before creating a skill or implementation.
- **Named failing test or command:** use it as the first validation target.
- **No tests but a build/lint/typecheck exists:** run the narrowest available command for the touched slice.
- **No executable validation exists:** inspect the diff and state the limitation clearly.
- **Multiple independent files:** parallelize read-only context gathering where useful, then report the findings before editing.
- **Blocked by missing requirements or an interactive secret:** ask the user for non-sensitive clarification; never request secrets through chat or tools.
- **Unrelated worktree changes:** leave them untouched and mention only if they affect validation or the requested files.

## Progress Communication

Keep updates short and useful:

- acknowledge the request and name the first anchor
- after context gathering, state what was learned and what will happen next
- before edits, state the focused patch
- after validation, state the result and any next action

For reviews, lead with findings ordered by severity and grounded in file links, then list assumptions, test gaps, and a brief summary. For implementation tasks, close with changed files, validation performed, and any unresolved issue.

## Completion Checklist

- [ ] The controlling local code path was identified.
- [ ] A falsifiable hypothesis and discriminating check guided the change.
- [ ] The patch is minimal and consistent with local patterns.
- [ ] User changes and unrelated work were preserved.
- [ ] Focused executable validation passed, or its limitation is reported.
- [ ] The final response names the change and validation without unnecessary detail.
