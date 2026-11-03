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
