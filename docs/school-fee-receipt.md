# Fee Receipt and Progress Report in the old ERP's way (24 Oct 2026)

Owner: "Fee Receipt aur Progress Report bilkul purane ERP jaisa — same design, same fields, same hisaab; data galat na ho."

## Fee Receipt (`/school/feecollect.php`)

The old ERP's Fee › Fee Receipt (studentfee.php), rebuilt field by field:

| Old field | Here |
|---|---|
| Session / School / Class / Section / Name / Search By / Search For | same selects; Search By = Student Name, Mobile No, Father's Name, Scholar No |
| Upto Month (No, All, April … March) | `fcalcu()` = old `getfcalcu()`: every unpaid, active, not-ticked-D/A head of each month up to the chosen month |
| Total Fee (typed) | `calcfee()` = old `getcalcfee()`: spreads the amount over heads in month order, part of a head allowed; more than the balance → "not received negative amount" |
| Head ticks, month ticks, D/A | `calcuamt()`, `tickMonth()` = old `calcuamt()`, `calcuamtmnth()`; D/A keeps a head out |
| Late Fee, Discount, Currently Received | `calcudis()`: Currently Received = Total Fee + Late Fee − Discount |
| Payment Mode, Bank Name, Cheque No/Date, Deposit Bank, Deposit Date | `setMode()` = old `getpmode()`; not Cash needs the deposit bank and date; not Cheque → bank/cheque "NA" |
| Followup Date / Remark, Remark | kept on the receipt |
| Upload Receipt | not here (no file storage for receipts yet) |
| Checks before saving | old `validateForm()`: Total ≤ balance, deposit bank/date, "Firstly Paid Old Due Amount", "Select Month"; all checked again on the server |
| Receipt No | continues the old ERP's series per session (`1-382`, `1-383` …) |
| Print | the old print.php layout (logo, red name, barcode, particulars grouped by head with months joined, Balance, Payable, Late Fee, Adjustment = Discount) |

**One place to take fees.** The Principal starts a session (Check before starting → first date → type START). That copies every child's
heads and what is already paid from the Old ERP Fee Card, and from then on the Fee Desk (`office_api.php pay`) and the Fee Department
(`finance_api.php receive`) refuse that session. Before starting, copy the fee cards from the old ERP again so nothing taken there after
the last copy is missed. Receipts are never deleted; the Principal cancels with a reason and the heads become Not Paid again. A child with
no old card (new admission) gets heads only when the Principal copies a classmate's card, with nothing paid.

## Progress Report (`/school/progress.php`)

* Old ERP cards: Examination-I Term-1, Term-2 and Final (Progress Card I-VIII) for PG–9th, 2023-24 to 2026-27, kept as the old ERP's own
  HTML and shown with its header picture, Principal's signature, the child's photo and the marks bar chart.
* New marks: Marks Entry(Term) with Pre. Test 10, Notebook 5, SEA 5, Term Exam 80 → Total, 9-point grade (A1 91+ … E2 below 21);
  Class Teacher Attendance/Remark with Art, Work, Health & Physical, Discipline (A–C), working days, presents, remark, result, promoted to.
  Cards are printed in the same HTML: Overall Marks, Percentage (2 decimals), Overall Grade, Final = Term-1 + Term-2, attendance %.
  Checked against Aahan Ali, 1st A, 2025-26: 462/600 77% B1, 479/600 79.83% B1, 941/1200 78.42% B1 — same as the old ERP.

## Photos

All 323 pictures the old ERP used (student photos, report card header, Principal's signature) are kept in the database, shrunk to
about 300 px. They are linked to students by the old ERP student id, shown on the Fee Receipt and on progress cards, and only to staff.

## Import files (made from the old ERP, imported by the Principal)

`7-Old-ERP-Progress-Reports-<session>.json` (one per session) and `8-Old-ERP-Photos.json`: Progress Report › Import from old ERP.
Preview first; nothing already here is changed.
