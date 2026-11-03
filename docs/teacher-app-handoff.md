# Teacher App — handoff (3 Nov 2026)

Built by Claude and pushed: see AGENTS.md › "Teacher App". All school checks pass locally (`teacher-flow.php` new).

## Still to do (owner asked for these; not finished)
1. **Phone check of the UI** — last scroll/animation fix (render keeps scroll position, animation only on tab change) was not re-checked in a browser. Open `school/teacher.php` at 390px width, log in as a class teacher, and check every tab: tapping a student in Haziri must NOT jump to the top.
2. **Android APK** like `snmsis-chairman.apk`: copy `school/android` + `.github/workflows/chairman-apk.yml` to a teacher version (HOME = `https://maakit.in/school/teacher.php`, teal colour #0F766E, icon `img/teacher-512.png`). It MUST support `<input type=file capture>` (WebChromeClient.onShowFileChooser + camera) because Notes/Syllabus/Plan take photos.
3. **2027 holiday list**: when BSA Bhadohi issues it (end of Dec), add rows to `TCH_LOCAL_HOLIDAYS` in `school/teacher_api.php` (new ids, never change old ones).
4. Optional: Principal screen in the web ERP to see all class holidays (`clsHol`) and chapter notes (`tchNote`) of every teacher.
