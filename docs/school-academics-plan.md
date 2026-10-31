# School academics — daily teaching, homework and per-child tracking

**Owner request, 27 Oct 2026 (Ankit, Hinglish, summarised):** the whole
day-to-day teaching of SN Mishra SIS should run through the app. Syllabus and
lesson plan by stage (PG–UKG, 1st–5th, 6th–10th); "what to teach tomorrow"
made from the book page the class has reached (teacher photographs the page);
homework made automatically from it; track which child did not do homework,
which book to bring, which rhyme was taught and needs revision; teachers must
track this easily; homework copy checked; oral checks child by child (A for
Apple … A to Z); a child who was absent must not be left behind; substitute
and sports periods also have a plan; the duty in-charge's round; the Principal
gets a daily report from every teacher and a bot that leaves nothing pending.

**Who builds this:** the owner split the work. The other AI (ChatGPT/Codex)
builds this document. Claude keeps doing the old-ERP migration (old-look
screens, imports, Fee Receipt cut-over). Read AGENTS.md first. Do not edit
`school/classic.js`, `oldlook.js`, `stumaster*`, `feereports*`, `feecollect*`,
`registers*`, `progress*`, `oldreports*` — those are Claude's; ask the owner if
you must.

## What already exists — build on it, do not rebuild

| Need | Already in the code |
|---|---|
| Syllabus / lesson plan from a photo | `scan_api.php` + `scan_lib.php` (`?a=read {mode: chapters|lplan}`), AI vision when `SCHOOL_AI_API_KEY` is set, Tesseract fallback |
| Weekly lesson plan, checked against the diary | kind `lplan` (`weekMonday|tid|class|subject`), app.js `vLessonPlan` / `vLessonPlans` |
| What was taught + homework per bell | kind `diary` (`date|tid|bell` → `{cls, subj, topic, hw, …}`), `vDiary`, "Prepare today's draft" |
| Homework time limit | `homework_lib.php` — 45 min per class per day, shared by Diary, Learning, Studio |
| Syllabus progress / deadlines | kind `syl`, `settings.sylDeadlines`, `sylStatus` |
| Substitute teacher | `staffAtt[date].subs`, `freeAt`, arrangement v2 in app.js |
| Attendance | `studAtt 'date|class' {absent:[ids]}` (old-look screen may add `st:{id:'L'}`) |
| Notebook checking from photos | `studio_api.php` (teacher approval before scores reach families) |
| Practice / missions / skill checks | Learning Lab (`learning_api.php`, kinds `learnMission`, `skillCheck`) |
| Speaking, behaviour, Game Zone | kinds `speak`, `behav`, `game*` |

Data rules (AGENTS.md): every new kind gets a prefix that is kept out of legacy
`load/sync` unless teachers' phones truly need it; the server checks who may
write (`sch_can_write` or the module's own API); never invent records; AI output
is always a draft the teacher checks before it is saved or sent to parents.

## What to build

### 1. Stage plans (curriculum by stage)
- Three stages, each with its own unit type:
  - **PG–UKG (foundation):** rhymes, phonics letters A–Z (A for Apple …),
    numbers 1–100, colours/shapes, stories, oral activities.
  - **1st–5th (primary):** book chapters per subject, reading passages,
    tables, spellings.
  - **6th–10th (secondary):** chapters, exercises, unit tests, board
    preparation (9th/10th already have `sylDeadlines`).
- Store per class+subject an ordered list of units (reuse `syl` chapters for
  books; add `acUnit` for rhymes/letters/oral items of PG–UKG).
- Start of session: teacher photographs the book's contents page (existing
  `scan_api` chapters mode) → units list → teacher checks → saved.

### 2. "Tomorrow's lesson" from the book page
- Teacher photographs the page the class has reached (scan_api, new mode
  `page`) → AI drafts the next N days: topic per bell, classwork, homework
  (within the 45-minute budget), books/copies to bring, revision items.
- Teacher edits/approves → becomes the weekly `lplan` and next day's diary
  draft (existing "Prepare today's draft" uses approved plans).
- Without AI: the teacher picks the next unit from the list and the same draft
  is made from a template per stage.

### 3. Per-child tracking (the core)
New kind `acTrack` (`date|class|subject`) → per student:
`hw: done | not done | incomplete`, `copy: checked`, `oral: done/needs repeat`,
`book: brought / not brought`. One tap per child on the teacher phone, default
"done" so only exceptions are tapped (same idea as attendance).
- **PG–UKG oral board:** grid of children × letters A–Z / rhymes; teacher
  ticks when the child says it; untouched items become tomorrow's revision.
- **Not done homework** carries forward until marked done; parents see it in
  their panel (`pdata`) the same evening.

### 4. Absent child catch-up
- From `studAtt`: for each absent child, list what the class did that day
  (diary topic + homework). Shown to the class teacher the next day and to the
  parent. Teacher marks "caught up".

### 5. Substitute and sports periods
- When a substitute is assigned (`staffAtt.subs`), the substitute's phone shows
  that class's planned topic/revision for that bell from the approved plan.
- Sports / activity bells: a short activity plan per class from a built-in
  list (no AI needed), marked done by the teacher.

### 6. Duty in-charge round
- Kind `acRound` (`date|tid`): checklist per round (gate, corridors, toilets,
  classes without teacher, discipline), time, remarks, optional photo through
  the existing private-photo pattern (never public).

### 7. Principal daily report and "nothing pending" bot
- Evening summary per teacher: diary filled per bell, plan vs taught, homework
  given, `acTrack` filled, absent catch-up done, round done.
- Pending list with reminders; reuse the existing helper-bot pattern (AGENTS.md
  §7) and WhatsApp share links — no paid SMS gateway is connected.

## Order of work (each step usable on its own)
1. `acTrack` per-child homework/copy/oral tracking + parent view.
2. PG–UKG oral board (A–Z, rhymes).
3. Absent child catch-up.
4. Book-page photo → next days' plan + homework draft (scan_api `page` mode).
5. Substitute/sports bell plan.
6. Duty round.
7. Principal daily report + pending bot.

## Checks the owner will expect
- Teacher writes only their own classes/subjects; parents see only their own
  child; nothing from another child reaches a parent.
- AI never publishes by itself; works without AI (template fallback).
- Homework budget (45 min) still enforced.
- A CI test per step in `.github/jaanch/` with synthetic children only.
- Teacher screens stay simple: one tap per exception, works on a phone.
