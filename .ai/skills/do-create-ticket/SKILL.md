---
name: do-create-ticket
description: Turn a requirement into a docs/plans/ design doc — sourced either from the current conversation or from an existing GitHub or GitLab ticket, whichever the user points at. Asks follow-up questions if the requirement has gaps. Orders the to-do list in vertical, user-visible slices rather than horizontal layers, and maps acceptance criteria to the slice where each test is written. Interviews the user on the drafted plan to close remaining gaps. When the source is chat, creates the ticket last, from the final plan, including its acceptance criteria. Stops there — does not implement anything.
license: MIT
compatibility: Requires the gh CLI (GitHub) or glab CLI (GitLab), authenticated against the project's repo.
metadata:
    author: project
    version: '2.3'
---

Turn a requirement into a `docs/plans/` design doc. The requirement can come from either of two places:

- **The current conversation** — an idea, bug report, or feature request discussed in this session (whether or not it was already fully worked out into an agreed plan).
- **An existing ticket** — when the user points at one (an issue number like `#45`/`GH-45`/`GL-45`, or an issue URL).

The skill's primary job is to **understand the requirement**, asking follow-up questions where the source material is thin, then produce the plan document and interview the user to close any gaps in it. When the source is chat, the ticket is created last, so it carries the final plan in one shot. The skill does not implement anything — once the plan doc is final and any new ticket exists, the skill's job is done and it waits for the user to decide what happens next.

---

**Steps**

1. **Determine the ticket platform and the source**

    Work out the platform from the host in `git remote get-url origin`:

    | Remote host       | Platform | CLI    | Ticket reference |
    | ----------------- | -------- | ------ | ---------------- |
    | `github.com`      | GitHub   | `gh`   | `GH-<n>`         |
    | contains `gitlab` | GitLab   | `glab` | `GL-<n>`         |

    If the host matches neither (e.g. a self-hosted instance on a custom domain), ask the user which platform it is. Then check authentication with `gh auth status` or `glab auth status`, so a problem surfaces now rather than after the interview.

    Check what the user said when invoking this skill:
    - If they reference an existing ticket (`#45`, `GH-45`, `GL-45`, an issue URL, or similar), the source is that ticket. Fetch it:
        ```bash
        # GitHub
        gh issue view <number> --json title,body,url,comments,labels

        # GitLab
        glab issue view <number> --output json
        glab issue view <number> --comments
        ```
    - Otherwise, the source is the current conversation — use whatever's already been discussed.
    - If it's genuinely unclear which is meant, ask before proceeding.

2. **Understand the requirement**

    Read the source closely: what needs to be built, why, and any constraints or decisions already surfaced (in chat, or in the ticket's body/comments). Treat the source as a requirement to be understood, not necessarily a finished design — a ticket or a conversation may be thin, informal, or leave gaps (unclear scope, data model, UI, edge cases).

    If the project has rules in `.ai/rules`, read `.ai/rules/index.md`, read every rule file whose globs cover the paths the plan is likely to touch, and grep `.ai/rules` for the requirement's keywords. Carry what applies into the plan's Decisions and acceptance criteria. Not every project has these — if `.ai/rules` doesn't exist, continue without it and don't create it.

3. **Ask follow-up questions if needed**

    If there are open questions that block drafting or materially affect the plan's direction, ask them now (AskUserQuestion or plain chat questions) before drafting anything — don't guess at decisions the user should make. If the source is already detailed and unambiguous (e.g. a plan already fully agreed in this conversation, or a well-specified ticket), skip this — don't manufacture questions for the sake of it. The in-depth interview comes later, in step 8, once the plan is drafted.

4. **Determine the plan document's number and filename**

    The `docs/plans/` numbering is independent of the ticket number — the platform assigns ticket numbers itself; you cannot and should not try to control those. The `docs/plans/` prefix is a separate, sequential counter you maintain yourself:

    - If `docs/plans/` does not exist yet: create it (`mkdir -p docs/plans`) and use `001` as the number.
    - Otherwise, list the files already in `docs/plans/` **and `docs/plans/archive/`** (closed-out tickets move there — see `do-close-ticket`), extract the leading numeric prefix from each (files look like `003-sm2-quiz-generator.md`), take the highest one, and add 1. **Always zero-pad the result to at least 3 digits** — after `009` comes `010`, not `10`; after `015` comes `016`. A quick way to get the next number, covering both locations:
        ```bash
        find docs/plans -maxdepth 2 -type f -name '[0-9]*.md' | xargs -n1 basename | grep -E '^[0-9]+-' | sed -E 's/^([0-9]+)-.*/\1/' | sort -n | tail -1
        ```
        then increment and format with `printf '%03d\n' <n>`.
    - Derive a logical kebab-case slug from the ticket title, or from the requirement if the source is chat (3-6 words, lowercase, hyphens, drop stop words if needed).
    - Filename: `docs/plans/<NNN>-<slug>.md`. Go ahead with it — no need to confirm the filename with the user.

5. **Order the to-do list into vertical slices, not horizontal layers**

    Structure the plan's to-do list so each slice cuts through every layer (migration → model → repository/service → controller/route → frontend) for one complete, user-visible piece of functionality, rather than grouping steps by layer across the whole feature.

    For a CRUD-shaped feature, that means: slice 1 delivers the listing/index page end-to-end (migration, model, read-path repository method, index controller/route, listing frontend page) before slice 2 touches create; slice 2 delivers create end-to-end before slice 3 touches edit; then edit; then delete. Do **not** plan "all repository methods, then all controllers, then the whole frontend" as separate phases — that defers anything demoable until the very end.

    Apply the same slicing logic to any multi-part feature, not just CRUD: order steps so the earliest slices produce something the user can look at and react to, and later slices build on that feedback instead of all landing in one final review. Group each slice's steps together and note in the to-do table (or a short preceding note) where one slice ends and the next begins, so it's clear the user can review and confirm after each slice rather than only at the end.

6. **Define the acceptance criteria and map them to slices**

    Write a numbered set of acceptance criteria covering the requirement's behavior and its important failure modes, so the plan shows at a glance which test cases are planned and where. Use this table:

    ```markdown
    ## Acceptance criteria

    | ID   | Acceptance criterion                             | Test slice     | Planned test                                     |
    | ---- | ------------------------------------------------ | -------------- | ------------------------------------------------ |
    | AC-1 | A guest visiting the list is redirected to login | 1 — List sales | Feature: `SaleControllerTest` "redirects guests" |
    ```

    - Each criterion is one observable behavior that clearly passes or fails, not an implementation step.
    - Each criterion names the slice that delivers the behavior, since that's where its test is written, and the planned test's type, file, and case.
    - A criterion that can't reasonably be automated says `Manual` under Planned test and is covered in the plan's Verification section instead.
    - Each slice with automated criteria has a test row in the To-do table that cites their IDs (e.g. `Tests: AC-1, AC-2`). Every automated criterion is cited by exactly one row.
    - Don't add a Done column — the To-do table is the only progress tracker, because `do-work` and `do-close-ticket` work from its checkboxes.
    - Follow the project's testing guidance when it has any (e.g. a `testing-best-practices` skill): cover the changed behavior and its important failure modes, and don't pad the list beyond them.

7. **Write the full plan document**

    Read one existing file in `docs/plans/` first (if any exist) to match its structure exactly — typically a "To-do" checklist table at the top (one row per implementation step, all unchecked for a new plan, ordered per step 5), then **Acceptance criteria** (per step 6), **Context**, **Decisions** (numbered, with rationale), concrete code/file snippets, a **Files to create/modify** table, a **Testing plan** (grouped by slice, referencing the AC IDs), and a **Verification** section. If the existing file predates the Acceptance criteria section, add it anyway, right after the To-do table. Write the _full_ plan detail here, based on your understanding from steps 2-3 — not a condensed summary.

    Title the doc with the ticket reference, matching existing convention: `# Feature Name (GH-45)` or `# Feature Name (GL-45)` when sourced from an existing ticket. When sourced from chat no ticket exists yet, so use `GH-TBD` or `GL-TBD` until step 9.

    Save it to `docs/plans/<NNN>-<slug>.md` from step 4.

8. **Interview the user on the drafted plan**

    This step is mandatory, even when the source looked complete. Now that the plan exists, re-read it critically and interview the user hard with AskUserQuestion to find what's still wrong or missing:
    - Unstated assumptions behind each Decision, and whether the alternatives were really ruled out.
    - Edge cases and failure modes: invalid input, duplicates, concurrent actions, partial failures.
    - Data: model shape, existing records, migrations, what happens on delete.
    - Access and validation: who can do what, and which rules apply where.
    - UI states: empty, loading, error, and the keyboard or mobile flow where relevant.
    - Scope and slicing: what's deliberately excluded, and whether each slice is really demoable on its own.
    - Acceptance criteria: behaviors with no criterion, and criteria too vague to test.

    How to run it:
    - Ask specific questions tied to this plan, citing the Decision or AC involved. Skip generic checklists and anything already answered.
    - Look up what the code or docs can answer rather than asking the user.
    - Put your recommended option first. Ask up to 4 questions per call, and run as many rounds as it takes, following up on any answer that raises something new.
    - After each round, update the plan doc before asking more, keeping the To-do, Acceptance criteria, Decisions, Files, and Testing plan sections consistent with each other.
    - Stop when a full round produces no change to the plan, or when the user says to stop. If the user defers a question, list it under **Open questions** in the doc.

9. **If sourced from chat: create the ticket from the final plan**

    Skip this entire step if the source is an existing ticket (step 1) — leave that ticket as it is, and do not create a duplicate.

    Otherwise:
    - **Title**: imperative, concise, under 72 chars (e.g. "Add route + page to generate and display a user's quiz").
    - **Body**: a short Context paragraph, key **Decisions** as bullets grouped by area, a **Scope** list, an **Out of scope** list if relevant, the final **Acceptance criteria** table including each criterion's test slice, and any prior ticket numbers the requirement builds on (e.g. "GH-7"). End with a line pointing at the full doc:
        ```
        Full design doc: `docs/plans/<NNN>-<slug>.md`
        ```
    - Check for a fitting existing label (`gh label list` or `glab label list`) and include it if one clearly applies; don't invent new labels.
    - Show the full title and body to the user, then use AskUserQuestion to offer **Create**, **Edit**, or **Cancel**. This is a hard stop: never create the ticket without their answer. On **Edit**, apply their changes and ask again.
    - On **Create**:
        ```bash
        # GitHub
        gh issue create --title "<title>" --body "<body>" --label "<label>"

        # GitLab
        glab issue create --title "<title>" --description "<body>" --label "<label>" --yes
        ```
        Omit the label flag if none applies. Parse the printed URL to get the ticket number, then replace `GH-TBD` or `GL-TBD` in the plan doc's title with the real reference.
    - On **Cancel**, or if the run ends before this step, the doc keeps its `TBD` title. Tell the user plainly that no ticket exists yet.

10. **Report back and stop**

    Give the user the plan doc path, plus the ticket URL/number (the one created in step 9, or the existing one if sourced from a ticket). If no ticket was created, say so. Nothing else happens automatically — do not start implementing, do not write code, do not open a PR or merge request. The task for this skill ends here; wait for the user to confirm what they want to happen next.

---

**Guardrails**

- Never create a ticket without showing the full title and body and getting the user's explicit answer (step 9), and only create one at all when the source is chat, not an existing ticket.
- Create the ticket only after the interview, so it carries the final acceptance criteria. Never create it early and fill it in later.
- Never let a run end with a `TBD` title without telling the user that no ticket exists yet.
- Don't skip follow-up questions in step 3 when the requirement has real gaps — but don't manufacture questions there when the source is already clear. The step 8 interview always runs.
- This skill's job ends once the plan doc is final and any new ticket exists. Never proceed to implementation, tests, or further code changes in the same run, even if the original request implied building the feature — only produce the plan and wait for explicit confirmation.
- The `docs/plans/` number and the ticket number are two unrelated counters. Never substitute one for the other, and never try to make the plan doc number match the ticket number.
- Always zero-pad the plan doc prefix to at least 3 digits, even past `009`.
- If `docs/plans/` doesn't exist, create it and start at `001` — don't ask the user where to put it.
- Match the structure of an existing `docs/plans/*.md` file rather than inventing a new layout each time; if none exist yet, use the structure described in step 7.
- If the platform's CLI isn't installed or authenticated, or the repo has no remote, stop and surface the error rather than working around it — this applies whether fetching an existing ticket or creating a new one.
- When sourced from an existing ticket, never create a duplicate — only reference the existing one, and never edit it.
- Order the to-do list in vertical, user-visible slices (e.g. listing → create → edit → delete), never in horizontal layers (all repository work, then all backend, then all frontend) — the whole point is enabling review and course-correction after each slice instead of one big-bang delivery at the end.
- Every acceptance criterion names the slice where its test is written, and every automated criterion is cited by a test row in that slice's To-do steps.
