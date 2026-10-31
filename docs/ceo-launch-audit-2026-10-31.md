# MAAKIT CEO Launch Readiness — 31 Oct 2026
## Verified from repository
- PHP 8 / MySQL / cPanel architecture; public repo `ankit854281/maakit`.
- Existing `muneem.php` already provides read-only weekly order/booking and expense signals; `admin/summary.php` handles daily delivery costs. Do not double-count in new finance register.
- Customer catalogue, shop management, delivery panels, Smart Sarathi integration, area coverage and booking modules exist in source. Source presence is not proof of live function.
- `docs/launch-fixes-2026-10-21.md` explicitly says owner-approved deployment and external launch gates remain.
- Existing `sql/010-service-areas.sql` means finance migration uses `sql/045-finance-vault.sql`.
## Blocking evidence required before launch
1. Backup DB + private uploads and perform restore drill (owner-controlled storage).
2. Check PHP lint, SQL migration twice on staging, admin CSRF/access control, founder ledger and receipt reconciliation. Review existing CI status; never assume passing.
3. Verify shop owners' prices, stock, availability, UPI and service-area links.
4. Test controlled order: checkout, rider acceptance, pickup/drop OTP, GPS, cancellation/refund, payment reconciliation; no live orders without owner approval.
5. Verify SMS/OTP, email sender, grievance contact, privacy policies and applicable registration/tax with actual CA.
6. Record pilot location, 5 willing shops, 2 riders and a support contact as targets ONLY; none verified/committed.
## Finance
Six receipt leads total INR 10886.30: directly attributable MAAKIT domain+hosting 3524.66; Smart Sarathi 1413.64 pending allocation; AI subscriptions 5948 pending allocation. Do not represent as fully business-expensed, GST-eligible or bank reconciled. Founder time is separate managerial valuation, not automatically tax deduction or capital.
## Recommended commercial strategy (hypotheses, not measured market facts)
Start in one verified service area. Revenue: transparent delivery fee plus explicitly agreed shop commission; goods money stays with shop. No nationwide service claims. Track successful deliveries, net fee margin, on-time rate, cancellations, shop retention, repeat customers and support resolution. Test prices with actual shop/rider economics before rollout.
## Competitor research
FURTILO: evidence not yet collected from reliable primary sources; do not claim pricing, market share or features. Compare verified service geography, delivery SLAs, shop fees, rider model, onboarding and refunds when sourced.
## Owner approvals needed
Production deployment, real transactions, spending, public claims and legal filings. No external team member was independently assigned or contacted.
## Immediate engineering backlog
P0: ensure finance migration 045 and avoid conflict; add audit log, safe private receipt storage, per-role permission matrix, exports; validate with CI/staging.
P0: assess existing `muneem.php` and finance module integration before duplication.
P1: verify launch gates and live operational evidence; prepare CA packet and founder-hours evidence.
P2: competitor report, pilot metrics dashboard and marketing test plan.
## Work completed in this review
Repository architecture and existing launch gates inspected; SQL filename conflict identified and corrected on draft finance PR. This is not a production deployment or an operational launch.
