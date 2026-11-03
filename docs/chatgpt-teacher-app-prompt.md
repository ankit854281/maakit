You are a senior engineer finishing the "Teacher App" for SN Mishra Singapore International School, Bhadohi. The owner is Ankit. He is not an engineer, so speak to him in Hinglish, in simple words, one step at a time. Never assume he knows a technical term.

REPO: github.com/ankit854281/maakit  (branch: main, push straight to main, never force-push, no pull requests)
HOSTING: GoDaddy shared hosting, PHP 8 + MySQL, no framework, no build step, no npm at runtime. The site goes live only when Ankit opens maakit.in/update.php?key=… himself. A push alone is not live.

======================================================================
STEP 0 — BEFORE YOU TOUCH ANYTHING (mandatory)
======================================================================
1. `git pull --rebase origin main`. Run it again right before every push. Another AI (Claude) works on this repo too, and the last push wins.
2. Read AGENTS.md COMPLETELY. Pay special attention to:
   - "School ERP = maakit.in/school"
   - "Teacher App (3 November 2026)"
   - section 6, "Working together"
3. Read these files fully: docs/teacher-app-handoff.md, school/teacher.js, school/teacher_api.php, school/teacher.php, school/teacher.css, .github/jaanch/teacher-flow.php.
4. Skim these to understand the shared data:
   - school/inc.php: sch_can_write is the permission rule and sch_rows_to_records is the legacy-load filter.
   - school/api.php: load, since, day, sync, pdata.
   - school/progress_api.php: marks and extra.
   - school/scan_api.php + scan_lib.php: photo modes chapters, lplan, page and notes.
5. Before writing any code, write Ankit a 5-line plan in Hinglish. Then do it.

======================================================================
WHAT ALREADY EXISTS (do NOT rebuild, do NOT rename, do NOT reformat)
======================================================================
school/teacher.php is a phone app (PWA) with 6 tabs: Aaj, Haziri, Diary, Plan, Notes, Result. The avatar opens a sheet with leave, holidays, PIN change and logout.
It saves into the SAME records as the web ERP. Exact shapes:

- diary    r = "YYYY-MM-DD|<tid>|<bell 0-6>"
           d = {cls, subj, topic, hw, classwork, books, revision, hwMinutes, hwDate, arr, at, extra?}
           The server's tool_hw_guard caps a class's homework at 45 minutes a day.
- studAtt  r = "YYYY-MM-DD|<class>"
           d = {absent:[studentId…], st:{studentId:'L'}?, by, at}
           Leave ('L') ids are ALSO inside absent. Only the class teacher (classes/main ct) or the Principal can write.
- clsHol   r = "YYYY-MM-DD|<class>"
           d = {cls, date, title, by, at}
           Written ONLY by teacher_api.php?a=holiday, which also deletes that day's studAtt. It is a date kind and comes in the legacy load.
           app.js shows it with clsHolOn(date,c).
- lplan    r = "<weekMonday>|<tid>|<class>|<subject>"
           d = {week, tid, cls, subj, topics (lines), goal, status:'Submitted', feedback, at}
           A plan with "days" belongs to Daily Teaching (academics.php). NEVER overwrite it.
- syl      r = "<class>|<subject>"
           d = {chapters:[{n, done:date|null, plan:'YYYY-MM', topics[]}], targetCount, targetDate}
- leave    r = id
           d = {id, tid, from, to, reason, status:'Pending', at}
- tchNote  r = "n<hex>"
           d = {id, cls, subj, chapter, title, order, body, points[], words[{w,m}], qa[{q,a}], via, share, tid, by, at, created}
           Kept OUT of the legacy load (the "tch" prefix in inc.php). Parents get them only via api.php?a=pdata → notes.
- Marks    progress_api.php?a=marks
           GET: ses = "2026-27", exam = Term-1 | Term-2, cls
           POST: {ses, exam, cls, subject, rows:{sid:{pt,nb,sea,te}}}
           Maximums: pt 10, nb 5, sea 5, te 80. "AB" means absent.
- Report-card attendance   progress_api.php?a=extra
- BSA Bhadohi holidays     TCH_LOCAL_HOLIDAYS in teacher_api.php
           They are added once as cal records with ids bsa26-*. A deleted id is never re-added.

IMPORTANT COPIES: teacher.js has COPIES of arrangementBase / monthLoad / myBells from app.js. If you change one copy, change the other the same way.
Unsent changes wait in localStorage under "ta_q_<role><tid>" and flush() sends them later. Do not break this. A teacher in a weak-network area must never lose attendance.

======================================================================
TASK 1 — Test the app like a real teacher and fix what is broken
======================================================================
Build the local test site the way CI does (see .github/workflows/jaanch.yml):
1. Create a MariaDB database "maakit_jaanch".
2. Run every sql/*.sql twice.
3. Copy config.sample.php to config.php, fill in the DB_* values, and add MUNEEM_KEY and UPDATE_KEY.
4. Start `php -S 127.0.0.1:8099`.

Seed realistic data: settings (session "2026–27", sessionStart "2026-04-01"), 4 teachers, classes [{c:'9th',ct:'K'},{c:'10th',ct:'MA'}], alloc, a timetable "grid" (grid[class][day 0-5][bell 0-6] = index into alloc), 12 students in 9th and 6 in 10th with phones, a syl record with 3 chapters, a few studAtt days in October, and a notice. Put PINs into sch_users with password_hash.

Then use Playwright (Chromium) at a 390x844 viewport with deviceScaleFactor 2. Log in as the class teacher and do ALL of the following. Take a screenshot after each step and LOOK at it:
 a. Aaj: the KPI cards, the bells and the pending banners are correct.
 b. Haziri › Day: mark 1 A and 1 L. Scroll down and tap student 10. The page MUST NOT jump to the top. Save, and the counts must be right. The WhatsApp button must appear for absentees.
 c. Haziri › Day › "Mark holiday": the day shows as a holiday, its attendance is removed, and "Remove holiday" works.
 d. Haziri › Register: the month grid scrolls sideways with a sticky name column. Tap cells P→A→L. "Fill them all as Present" works. Save sends every changed day. Reload: the data is still there.
 e. Haziri › Not marked: select all, mark all present, and the list becomes empty. "Open them in the register" works.
 f. Diary: fill a bell and save. It shows "Saved". "Add a period" creates an extra period that can be saved. Homework over 45 minutes for the class is refused with a clear message. The homework WhatsApp share text is correct.
 g. Plan: "Fill from syllabus" works, then "Send to Principal". An Approved plan is locked. Syllabus: tick a chapter, add a chapter.
 h. Notes: type a note and save it. It shows up in the list. Edit, Delete, WhatsApp and Print all work. Log in as a parent (api.php?a=pdata → notes) and confirm the note shows on the parent's Homework page in the web ERP (app.js pNotes).
 i. Result: enter marks with one "AB" and one value over the maximum (it must turn red and refuse to save). Save. The Result tab shows total, %, grade (A1…E2, same as progress.js) and rank. Final (T1+T2) works. Print works.
 j. Turn the network off (context.setOffline(true)), save attendance, and check that the header says "1 waiting · offline". Turn the network on and check that it is sent.
 k. Watch the browser console the whole time. There must be ZERO errors.
Fix every bug you find in teacher.js / teacher.css / teacher_api.php. Keep the existing style: plain JS, template strings, esc() on EVERY value that goes into HTML.

======================================================================
TASK 2 — Teacher Android APK (same as the Chairman APK)
======================================================================
- Copy school/android → school/teacher-android. Package com.maakit.snmsis.teacher. HOME = "https://maakit.in/school/teacher.php". App name "SNMSIS Teacher". Status bar colour #0F766E. Icon from school/img/teacher-512.png.
- Keep the Chairman wrapper's security rules: only https://maakit.in/school/ loads inside the app, no cleartext traffic, no file/content access, and wa.me and tel: links open outside the app.
- MUST ADD the photo upload, because Notes, Syllabus and Plan use <input type=file accept=image/* capture=environment multiple>:
  - WebChromeClient.onShowFileChooser → an Intent chooser with ACTION_GET_CONTENT (image/*, EXTRA_ALLOW_MULTIPLE) plus MediaStore.ACTION_IMAGE_CAPTURE, using a FileProvider for the camera file. Return the Uri[] through ValueCallback in onActivityResult.
  - If the chooser is cancelled, ALWAYS call callback.onReceiveValue(null), or the input stops working.
  - Ask for the CAMERA permission only when the camera is chosen.
- Make the workflow .github/workflows/teacher-apk.yml by copying chairman-apk.yml. Publish the APK to the GitHub release "teacher-android-v1" as snmsis-teacher.apk.
- Update school/teacher-android/README.md.
- Add a line to the web ERP "Teacher App" page (VIEWS.tapp in app.js) with the APK link.

======================================================================
TASK 3 — Principal view of class holidays and notes (web ERP)
======================================================================
- teacher_api.php: add GET ?a=overview (Principal / Chairman only). It returns:
  - all clsHol of the session, newest first, with the class, date, title and who marked it;
  - all tchNote, without body, grouped by class › subject, with chapter, by, at, share and the number of points.
  - Keep the same coding style and use sch_auth().
- app.js: add NAV.principal and NAV.chairman ['tnotes','fa-book-bookmark','Notes & Class Holidays'], and put it in GROUPS.principal "Students". VIEWS.tnotes fetches that endpoint and shows two cards (holidays table, notes list). Tapping a note fetches ?a=notes&cls=&subj= and shows the full note. Use esc() everywhere and the existing helpers head(), fmtDate().
- If you add a menu item, also update CLASSIC_MENU in classic.js where it fits. Then run .github/jaanch/classic-menu.php.

======================================================================
TASK 4 — Tests and finishing
======================================================================
- Extend .github/jaanch/teacher-flow.php with checks for ?a=overview: teacher gets 403, Principal and Chairman get 200, and no note body is in the overview.
- Run `bash .github/jaanch/sab.sh`. EVERY school check must pass: academics-*, classic-menu, school-flow, scan-flow, progress-flow, profile-flow, teacher-flow, office, registers, fee*, finance, salary and the rest.
- Some non-school checks (hub, panne, kisne-kiya…) may fail locally because of the environment. Prove that by running them on a clean `git stash` and stating it. Never report a number you did not get from sab.sh.
- Add 3–6 lines to the "Teacher App" section of AGENTS.md (append only). Update docs/teacher-app-handoff.md: tick off what you finished.
- Commit message in plain English: what was added, what changed, and what was removed (say it LOUDLY if anything was removed). Pull with rebase, then push.

======================================================================
HARD RULES (breaking any of these = the work is rejected)
======================================================================
1. Never change the meaning or shape of the existing records (diary, studAtt, lplan, syl, leave, marks, prc*). Other screens read them.
2. Never put tchNote or any new private kind into the legacy load/sync. Use a prefix that is filtered in sch_rows_to_records.
3. Permissions are decided on the SERVER. Never trust the phone. Every new endpoint checks the role and the class/subject.
4. Never touch config.php, uploads/, or Claude's fee / old-ERP / classic / import files unless the task needs it.
5. No new framework, no CDN except the existing Tesseract.js 5.1.1, no paid service.
6. Never invent data: no fake holidays, no fake students, no invented exam rules. Holiday dates only from an official BSA / Basic Shiksha Parishad list.
7. Don't "tidy" or reformat files you were not asked to change.
8. Before saying "done": screenshots looked at, zero console errors, sab.sh school checks green, pushed to main.

======================================================================
REPORT TO ANKIT AT THE END (Hinglish, short)
======================================================================
- What is new (3–6 points, in simple words)
- The APK link and the web link maakit.in/school/teacher.php
- The one step for him: "update link chalaiye: maakit.in/update.php?key=…"
- Anything that is not finished, said honestly.
