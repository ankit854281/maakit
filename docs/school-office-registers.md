# School Office registers (`/school/registers.php`)

Owner request, 22 Oct 2026: build the old ERP's blueprint (schoolerpindia.net/school/snmishra, 45 menu groups) into the new ERP,
and move the old data across with **the same receipt numbers**.

## What maps where

| Old ERP menu | New ERP |
|---|---|
| Dashboard(s), Setting, Class/Section/Subject master, Session | Existing app (Dashboard, Admin Dashboard, Settings) |
| Fee Master, Fee, Fee Report (31 reports) | Existing Fee Structure, Fee Desk, Fee Department, Dues, Calls, Receipts |
| Student Attendance, Timetable, Examination I/II/III, Marks, Progress cards | Existing Attendance, Timetable, Exams & Date Sheets, Marks Entry, Report Cards |
| HR Management, Salary, Leave, Lesson Plan, Homework/Mobile app, Circular, Calendar | Existing Staff, Salary, Leave, Lesson Plans, Diary/Homework, Notices, Calendar |
| Everything else (below) | **School Office registers** (this page) |

School Office groups: **Admission** (Enquiry with follow-ups, Admission form), **Students** (Student Full Record with every
old field + status, TC, Certificates & Awards, ID cards, Student leave, Tags, Alumni), **Exams** (Exam rooms, Admit cards,
Previous question papers), **Transport** (Vehicles, Drivers & helpers, Routes & stops, Log book, Diesel), **Store** (Suppliers,
Items + stock report, Purchases, Issue, Sale, Repair), **Accounts** (Heads, Parties & banks, Cash book + running balance,
Cash verification), **Front Office** (Gate pass, Visitors, Reminders, Events & PTM, Feedback, Documents, Online class links,
SMS/WhatsApp templates — never auto-sent, Website content), **Masters**, and the **Old ERP Archive** (read-only: Old ERP
Receipts with original numbers, Old ERP Fee Statement, Old ERP Marks).

## Code

- `school/registers_lib.php` — every register is ONE array entry in `reg_modules()`: fields (text, area, num, money in paise,
  date, time, sel, yn, phone, student, teacher, cls, json), list columns, totals, who reads (`r`) and writes (`w`) by role letter
  (P principal, C chairman, A accountant, R reception, T teacher), optional auto number (`TC/2026-27/0001`), due-date highlight,
  print layout. Add an old-ERP menu by adding an entry — no new screen.
- `school/registers_api.php` — `meta`, `rows`, `report` (stock, cashbook), `save`, `del` (reason required, logged), `import`
  (Principal; preview then apply; rows that exist with other values block the whole import; nothing is ever overwritten),
  `carry` (Principal; old-ERP net due → Fee Desk `acctStu.opening`, only where empty, preview first).
- `school/registers.php|js|css` — the screen, embedded in the app as view `office` (Admin group; teacher Planning; Office for
  accountant/reception).
- Storage: `sch_records` kinds `regRow` (r = `module|id`) and `regSeq`. The `reg` prefix is excluded from legacy load/sync in
  `inc.php`, so office registers never reach teachers' phones.
- Test: `.github/jaanch/registers-flow.php`.

## Import file formats (made from the old ERP by Claude)

`{"module": "<id>", "rows": [ … ]}` — import order: students first through Students › "Import verified ERP students"
(`snmishra-students-v1`), then:

| module | key (never duplicated) | main fields |
|---|---|---|
| `studinfo` | student | student (`erp-snmishra-<old id>`), status, gender, category, caste, religion, aadhar, mother mobile, PEN, … `extra` = any other old field |
| `oldreceipt` | session + receiptNo | session `2024-25`, receiptNo exactly as old (`1-708`), date, student, name, months, total, discount, lateFee, received, mode, user |
| `oldfee` | session + student | billed, transport, discount, otherDiscount, adjust, received, due, status |
| `oldrcptdet` | session + receiptNo | the whole printed receipt: title, date, name, class, adm, mother, father, scheme, `lines` = [[sno, particular, month, amount]…], balance, payable, lateFee, adjustment, received, mode, bank, cheque, remark, user — text exactly as the old ERP printed it |
| `oldfeecard` | session + oldId | the old Fee › Fee Receipt card of every student in every session (also students not in the new ERP): profile rows, `months` (Fee Details: month rows + head rows with amount / paid / balance / status), `receipts`, `tables` (Fee Refund, Enquiry Receipt, Cancel Receipt/Cheque, Fees Transfer, Other Fee Receipt, Fee Discount, Fee Adjustment, siblings), summary (old fee … fee due), next receipt number, copied-on date. Text exactly as the old ERP showed it. |
| `oldmarks` | session + exam + student | exam, marks `{subject: {component (max): mark}}`, total, percent, grade, rank, attendance |

Money is written in rupees in the file and stored in paise. Bank account numbers are reduced to the last 4 digits.

## Session menu and old-style receipt (Oct 2026)

- App header (Principal, Chairman, Accountant, Reception): a session menu like the old ERP's. It lists 2023-24 to next year,
  stores the choice in `localStorage` `sch_view_session` (removed when the current session is picked), and passes
  `&session=` to the Fee Department and School Office frames. It never changes `settings.session` — the working session
  is still changed only in Settings.
- School Office: registers with a `session` field are filtered to that session; a session select sits next to the filters.
- Old ERP Receipts → Print uses the old ERP's `print.php` layout (logo `img/snmsis-logo.png`, red school name, address,
  barcode strip `img/receipt-barcode.gif`, particulars table, totals grid, "Parent Copy … Auth. Sign"), filled from the
  matching `oldrcptdet` row. If that row is not imported yet it says so instead of printing a half receipt.

## Why old receipts are an archive, not ledger entries

The Fee Department numbers its own receipts (`RC/2026-27/000001`) and its ledger must never contain invented history
(AGENTS.md, Detailed Fee Department). So old receipts keep their **original numbers** in the read-only Old ERP Receipts
register (printable as a copy), and only the **net due** moves into the live fee system through "Carry old dues to Fee Desk".

## Verified (22 Oct 2026, Class 1st, session 2024-25)

44 students, 44 full records (35 Active, 9 Deactive), 81 receipts (₹2,79,415, all original numbers), 44 fee statements
(billed ₹6,76,922, due ₹2,36,189), 105 mark rows (Term-1, Term-2, Final) — 0 differences against the old ERP.

## Old-ERP look (22 Oct 2026)

`school/classic.js` / `classic.css`: office roles see the old ERP's frame (same 47 menu groups, item names and order, colours
#2D353C / #EDEDED / #F64015 / #F7F7F7, tiles #FC8675 #6BAFBD #F3CE85 #65CEA7). Every old item opens the new screen that does
the job; items with no new screen yet stay in the menu, dimmed, and open a short note. ADMIN ▸ New look switches back.

## Fee Receipt in the old layout (22 Oct 2026)

Owner: "fee receipt same to same jaise old software me hai". Fee › Fee Receipt (and Fee Statement, Fee Card) in the classic menu
open the `oldfeecard` register, which School Office draws like the old studentfee.php: Session / School / Class / Section / Name /
Search For, the student details on the left, Fee Details month by month on the right (green #4AC598 heads), the yellow
#F0C164 totals box, then Fee Receipt (each with Print in the old receipt layout), Refund, Transfer, Other Fee, Discount and
Adjustment tables. It is the old ERP's record as on the copy date; new fees are taken in the Fee Department ("New fee").
