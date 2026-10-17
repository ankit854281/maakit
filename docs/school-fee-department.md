# School Fee Department — implementation and rollout

`school/finance.php` is linked in the school sidebar / More menu. Accountant starts on this module; Legacy Fee Desk remains accessible for existing accounts and original receipts. No legacy records are deleted. Live hosting must be updated separately from a GitHub commit.

## Observed legacy feature parity

Read-only review of School ERP India on 17 October 2026 found fee-head/scheme/facility masters; month/head-wise receipts; transport, discounts, advance, other charges (single/bulk), refund, adjustments, session copy/transfer; and receipt, demand, defaulter, head/class/month collection, bank, cancellation, follow-up and statement reports. No old-ERP payment was posted or modified during research.

The new module combines these into:

- Fee setup: custom heads; explicit session rate snapshot copied from existing Fee Structure; student-specific final tuition/transport overrides; annual class exclusions; sibling concession; collector delegation; date closure / reasoned reopening.
- Bills: month-wise tuition + transport; annual heads; preview/hash confirmation; deterministic IDs skip existing bills; single/bulk other fees. Existing bills never reprice when rates change. Void only wholly unpaid, unallocated bills. Custom schemes use reviewed final per-child rates; no public scheme-name tags.
- Collection: student/admission/father/mobile search, selected-head FIFO partial payments, rupee/paise precision, same-request replay, duplicate non-cash reference rejection, private proof, explicit excess advance. Cash, cheque, card, wallet, NEFT, UPI, net banking, online and demand draft are recorded modes, not payment integrations.
- Ledger: paid, concession, balance, overdue, pending cheques, unused advance; standalone discount/credit/debit; credit application and reversal; completed refund from unused advance or original paid allocations; paired same-child unused-credit transfer across sessions.
- Cheques: pending allocation reserves head capacity but never reduces dues; only Principal can clear/bounce/cancel. Realized collection date is clearance date. Cancellation/reversals append dated events; receipt numbers are never reused.
- Documents: printable original receipt/status/refund note, current fee card / statement, demand through today, school-authorized payment statement, bulk demand bills (up to 100). Browser Print saves PDF. No invented signature/affiliation, tax-exemption assertion, automatic WhatsApp message or bank transfer.
- Reports: collection register, class/month groups, head allocation movement, due/zero billed balance, pending cheque, refund, cancellation, concession/credit, bank/non-cash, filtered CSV/print; follow-ups with next date and completion. Zero balance never certifies ungenerated future fees as paid.

## Existing Fee Desk migration

Legacy `acct*` and new `fin*` records are separate financial histories. Before collections move for a child, Principal uses **Review / migrate legacy Fee Desk balance**, reviews the computed cut-off balance against the old fee card, then confirms an unchanged hash. The new opening balance contains the remaining due or unused credit, not invented historical receipts. Already-covered months and annual fees are suppressed in subsequent bill generation. Custom/free monthly treatment carries forward. Original receipts remain printable in the legacy archive. Bank settlement records and fee history from external School ERP India have **not** been automatically imported.

A child with legacy records cannot be billed/collected here without reconciliation. After activation, old pay/settings/cancellation endpoints refuse further writes for that child under the same Principal row lock as migration; stale tabs cannot silently alter the reconciled balance. Legacy calling/parent balance displays use the active detailed ledger. Original collection reports remain legacy reports, not combined historical/new totals. Prefer the new Fee Department for new-period reports.

A manual opening balance is also available once before any billing/collection, with source report/reason and explicit reconciliation. Principal must ensure covered months are not generated again when entering a manual migrated balance; the built-in Legacy Fee Desk migration handles coverage automatically. External legacy CSV/report import needs a separate reviewed mapping; never treat missing receipts as zero paid.

## Permissions / storage / invariants

Principal manages financial corrections/setup. Accountant receives payments and follows up. Explicitly granted teacher accounts receive/follow up only; ungranted teachers get 403. Reception reads and records follow-ups. Chairman reads. Parents/students receive only linked-child records and attached proofs, without bank/reference/internal actor data. Default PIN must be changed before every API works. `fin*` kinds are excluded from generic sync and are never accepted by `SCH_KINDS`.

Every write serializes under the Principal user row, validates the selected session, uses integer paise and a request UUID bound to actor/action/session/payload, then commits financial records, audit log and replay result atomically. Financial entries are append-only. Posting dates cannot predate the student's later entries; historical corrections use current dated reversals. Date closure blocks backdated entries. Pending allocations cannot be paid/discounted twice. Refunds cannot exceed the source's unused credit or original paid amounts. Transfers move credit only and never count as cash collection.

No new SQL tables are required: `sch_records` stores `finAccount`, `finBill`, `finEntry`, `finOpening`, `finStructure`, `finHead`, `finSeq`, `finRequest`, `finAccess`, `finClose`, `finFollow`, `finMedia`. Backup all `sch_` tables and the private directory **outside** web root: `dirname(project_root)/school-private-finance`. Proofs are size/type constrained, images decoded and re-encoded without metadata; downloads require authentication. Do not move private proofs into public uploads. API errors never claim a payment succeeded after a failed transaction. Receipt state and balance are always reloaded after saves.

## Validation / rollout

CI runs `finance-unit.php`, `finance-flow.php`, `finance-ui.cjs`, PHP lint, existing migrations and regression checks. Finance HTTP tests refuse non-CI databases. Tests use synthetic children/payments only. First confirm current-session rates; reconcile one child and compare legacy and new opening balance; then generate only uncovered months. Existing Fee Structure labelled 2024–25 is never silently assumed to be the current-session rate.
