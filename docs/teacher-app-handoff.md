# Teacher App — handoff (3 Nov 2026)

Built by Claude and pushed: see AGENTS.md › "Teacher App". All school checks pass locally (`teacher-flow.php` new).

## Delivery status — 3 Nov 2026

- [x] **390px phone UI:** Playwright checks Aaj, Haziri Day/Register/Not marked, class-only holidays, Diary, Plan/Syllabus, Notes, Result, avatar sheet, offline queue and console. Real CI screenshots examined. Attendance register now preserves horizontal as well as vertical scroll.
- [x] **Teacher Android APK:** build passed and `teacher-android-v1/snmsis-teacher.apk` was published. `WebChromeClient.onShowFileChooser` supports gallery and camera with `FileProvider`. Camera on a physical Android phone still needs manual confirmation.
- [x] **Principal/Chairman page:** `tnotes` displays class holidays and subject-grouped chapter notes. `teacher_api.php?a=overview` is read-only for these roles, with note content withheld until clicked. Teacher role is forbidden.
- [x] **School tests:** `teacher-flow.php`, `classic-menu.php`, `school-flow.php`, `progress-flow.php`, `scan-flow.php`, fee/finance/salary and other school checks passed in the GitHub CI runner.
- [ ] **Full sab.sh green:** CI reports 43 passing and 23 failing checks, with failures in non-school routes caused largely by missing `config/config.php` in the test environment. Do not claim all checks pass.
- [ ] **2027 BSA Bhadohi holidays:** wait for the official 2027 list; add new stable IDs only. Never invent dates or change 2026 IDs.

## Files and deployment

- Web: `https://maakit.in/school/teacher.php`
- Android: `https://github.com/ankit854281/maakit/releases/download/teacher-android-v1/snmsis-teacher.apk`
- Website deployments are not automatic: Ankit must use his private `update.php?key=…` URL. Do not place the key in code or docs.

## Same-domain Teacher APK download
- The `teacher-apk-domain.yml` action mirrors the already-built release APK, checks its SHA-256 and commits `school/downloads/snmsis-teacher.apk` into `main`. No APK is rebuilt or re-signed by the mirror.
- After owner runs the private updater, download at `https://maakit.in/school/downloads/snmsis-teacher.apk` (not GitHub). The web ERP's Teacher App button links to that file. Verify the mirror workflow succeeds before publishing the new website link.

## Welcome, motivation, and profile enhancement
- Added a brief personalized welcome opening with skip and reduced-motion support, shown only on initial app load (not while marking attendance).
- Added a rotating teacher encouragement card to the Aaj tab.
- Expanded the account sheet into a real read-only school profile with class/subject/period summary and an optional photo stored on that phone only. Existing leave, holiday, PIN, logout and offline attendance queue stay unchanged.
- A school-wide synced profile-photo backend is NOT included; official staff details remain managed by the school office.

## Teacher App v2 — 5 Nov 2026 (Claude)
- [x] Professional school-colour design; English / Hinglish / हिंदी switch (`teacher-lang.js`).
- [x] Roll-call attendance (tap name, quick absent roll numbers), marks cards with progress, diary minute chips.
- [x] Fixed: typed marks/diary lost on 45-s refresh or another save; incomplete results graded and sendable on WhatsApp; app could not open offline; homework date defaulted to same day; back-date absent WhatsApp said "today".
- [x] Teacher UI checks 01–17 pass locally at 390px; school PHP checks pass.
- [ ] Owner must run the private updater for it to go live. Teachers just reopen the app — nothing to reinstall (the APK opens the live site).

## Teacher App — 5 Nov 2026 (Claude), owner list (4)
- [x] Diary card: class attendance line; question-number chips; homework ideas from class work.
- [x] Messages: Attendance (from the register) instead of Absent.
- [x] Syllabus: books, chapter rows with pages, exam ticks (UT-3/UT-4/Annual…), WhatsApp exam syllabus; parents see it.
- [x] Chapter text (paste / phone OCR) → homework ideas, lesson-plan topics, question paper (Planner → Paper).
- [x] My work check for teachers; Principal cross-check covers homework checks + weekly/one-time work.
- [x] Students report for subject teachers (read-only) + profile card.
- [x] teacher-flow.php and teacher-ui.cjs 01–26 pass locally at 390px.
- [ ] Owner must run the private updater for it to go live (teachers just reopen the app).
