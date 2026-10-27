# Daily Teaching — implementation and hand-off (27 October 2026)

Built in the seven steps in `school-academics-plan.md`. The school remains PHP/MySQL with no production build or new paid service. The old-ERP migration files assigned to Claude are unchanged.

## Opening it

Principal/Chairman: the dashboard has **Daily Teaching · child checks & pending report**, including in the classic frame. Teachers: **My Classes → Daily Teaching**. The page is also `school/academics.php`, using the existing school token. Parent homework screens receive a child-only projection through `pdata`.

The class/subject and date selectors stay above seven small sections. A teacher confirms present children's checks before saving; attendance must already be recorded. Absent children are excluded. Defaults exist only in the form until confirmed. Unknown checks stay unknown. Another save requires the revision the form loaded, protecting concurrent edits.

## Seven usable steps

1. `acTrack`: date/class/subject checks for homework, notebook, oral and books. Original unfinished homework stays pending until the teacher explicitly checks that original item as done (`acResolve`), even when later days are marked done. Parents receive only their own children's entries.
2. `acUnit` and `acOral`: ordered foundation curriculum and child × item oral board. Phonics/English starts with A–Z; numbers starts with 1–100. Rhymes, stories, colours/shapes and other oral units are entered/checked by teachers, never fabricated as taught. One tap cycles unobserved → done → repeat. Untouched/repeat items remain revision; template drafts include the pending units. Book chapters continue using existing `syl` and contents-page scanning.
3. `acCatch`: missed work derives from saved absence/leave and diary entries, scoped to each child. A catch-up confirmation identifies the specific source topic/homework; correcting that source requires a new check.
4. `scan_api` adds `page` mode. Photos are not stored and require explicit transfer consent in the new screen. AI output is bounded and editable; no AI configuration is needed for stage templates. Teacher-reviewed daily drafts become existing `lplan` records with status **Submitted**. The Principal reviews all daily details in Lesson Plans and approves. Only approved plans fill diary drafts. Classwork, books/copies and revision accompany topic/homework. Existing shared homework guards still run when actual diary work is saved. Planning checks currently recorded homework, but does not reserve time or assign homework; other teachers may fill the budget before diary saving.
5. Effective bell plans use the same grid, half-day absence, approved leave, explicit substitutes, automatic arrangement scoring, holidays and exam exclusions as the app. A substitute sees the absent teacher's approved topic for that bell. Activity suggestions are stage templates; completed activity evidence (`acBell`) is separate from the actual diary.
6. Principal-assigned `acDuty` and observed `acRound` checklists. Unchecked points never silently become OK. Round submission IDs make retries safe. Photos are optional, metadata-stripped and private; only the duty teacher, Principal and Chairman may read them. The Principal records an explicit action to close reported issues (`acRoundFix`); the original observation remains unchanged.
7. Per-teacher report: due bells vs saved diaries, literal topic comparison (not an AI judgment), homework, present-child checks, catch-up and round completion. The pending helper refreshes while that section is open. WhatsApp is a manual share link with staff summary counts; nothing is auto-sent and no SMS gateway is added. A missing timetable is shown explicitly. No pending items means only that the checked records contain no outstanding entries, not proof that every educational need is met.

## Permissions and records

All `ac*` kinds are outside `SCH_KINDS` and legacy load/sync. All writes authenticate, recheck the session and take the Principal row lock. Subject writes require the existing allocation; substitutes get only their effective bell's plan/outcome, not general access to another subject's child checks. Chairman is read-only. Parent/student APIs project child records, never a whole class map. Updated records retain `acHistory`. Existing school tables suffice; there is no schema migration or production data import.

Private duty photos use `SCHOOL_MEDIA_DIR/academics`, or the existing sibling `school-private-media/academics` when that setting is absent. Include this subdirectory with the existing private-media backup/restore; a database-only backup cannot restore photo bytes. The directory is required to be outside the website root. `config.php`, `uploads/` and live records are untouched by this implementation.

## Validation and deployment

- `.github/jaanch/academics-01-tracking.php` through `academics-07-report.php`: synthetic checks for each step.
- `academics-flow.php`: CI-only HTTP/database checks for authentication, subject/child isolation, attendance, stale revisions, legacy-sync exclusion, pending resolution, oral/catch-up, teacher review, Principal duty assignment, retry safety, private photos, issue resolution and reports. It snapshots and restores its school fixtures.
- All PHP tests are automatically included by `bash .github/jaanch/sab.sh`. Use that runner for the full suite and counts; do not substitute an ad-hoc loop.
- Local PHP lint and seven focused checks passed. A 390px Chromium test with synthetic API fixtures passed teacher tracking, absent exclusion, one-tap oral checks, reviewed plan submission, round submission, report sharing and page-width/error checks. This is UI verification, not a claim about the live database.

A Git commit/push does not deploy. After the remote full checks pass, the existing Maakit administrator update process must apply the code; then validate on the hosting using school logins. Photo AI additionally needs the existing school AI configuration. No real child or financial data was used as a test fixture.

## Legacy plan protection and timetable pilot (27 October 2026)

The weekly form now locks any plan containing `days`, including an empty day list, and links to Daily Teaching. `lpSubmit` also checks before reading the old form. Under the existing transaction lock, legacy sync rejects edits to daily content; Principal status/feedback review remains possible with the daily content intact. The HTTP regression checks stale Teacher and Principal submissions and approval without lost days.

Phone and PHP arrangement now use the same chronological bell order, deterministic teacher-ID tie break, empty availability-list semantics and holiday/exam end fallback. Equal-score automatic assignments can therefore change from old insertion-order choices; manual choices remain first. `academics-08-regression.php` compares the actual phone functions with PHP over full/half absences, manual overrides, approved leave, prior monthly load, holidays, exams and the saved Ritu UKG week observed in the live My Week screen. Absence scenarios are synthetic; this is not a live substitute assignment or a completed teacher trial.

Period plans and the daily report require a complete six-day, seven-bell grid with valid class allocations, existing teachers, no teacher clashes and allowed bell windows. Explicit `-1` Self Study slots are accepted. An incomplete grid suppresses those reports and sharing rather than showing an apparent all-clear. Child checks and duty records remain independently usable.

Pilot: owner selected Ritu first. Her live My Week screen showed 42 UKG periods across Monday–Saturday on 27 October. Whole-school timetable verification, current code deployment and the other one or two teacher names remain pending. After these are ready, run three actual school days: confirm attendance; save/review one daily plan; verify old weekly form protection; compare any real substitution with Staff & Arrangement; record actual diary and child checks; inspect the Principal daily report. Do not create absences, child results or duty observations to simulate a live trial. Record mismatches and unresolved work each day before expanding the rollout.
