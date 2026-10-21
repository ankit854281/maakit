# Launch fixes — 21 October 2026

These changes need owner-approved deployment. A repository commit is not a live deployment.

## Included

- Correct the obsolete Bolero offer link, including existing saved links and a narrow repeatable migration.
- Resolve social metadata language from the actual selected language.
- Translate checkout market, weight, size and payment options. Preserve stored payment values and reject unsupported payment methods.
- Send rider login through Smart Sarathi instead of attempting to authenticate against the Maakit staff database.
- Add explicit pickup search; bound place searches to the configured local range, reject mismatching locality results and ignore stale responses. Editing a selected address clears the old coordinate/fare.
- Let the same-origin voice control request microphone permission while keeping other origins blocked. Users still approve microphone access in their browser.
- Save new order list/bill/proof images outside the website root, require order/job access for image reads, and route legacy private image URLs through access checks. Product/shop photos stay public.
- Copy fresh Smart Sarathi rider GPS into goods tracking, forward valid customer drop pins and prevent out-of-order responses from moving order status backwards.
- Show OTP/provider/bridge prerequisites and installation-password warnings in Admin → Launch readiness. Allow the owner to enter reviewed public complaints details without inventing a mailbox or response promise.
- Reject non-scalar form fields safely and recheck active staff roles at protected panel entry.

## Deployment review

1. Review the commit and successful `जाँच` / PHP API workflows before approving deployment. SQL 044 is additive and repeatable.
2. Back up the database and existing uploads. Preserve private configuration and all uploaded files. The code does not bulk move/delete legacy media.
3. The PHP hosting account must be able to create `maakit-order-media/orders` and `maakit-order-media/sarathi` beside the web root. New sensitive media fails closed if this directory cannot be created. Include both directories in private off-host file backups and restore exercises; the database-only backup does not contain their bytes.
4. Apache 2.4 `mod_rewrite` must be enabled. On Apache, check an old `/uploads/list-…` or `/uploads/bill-…` and Smart Sarathi `/uploads/pod-…` URL returns 404 when signed out and works only for its authorized viewer. The PHP built-in CI server does not apply Apache rewrite rules. Pre-existing copies cached by browsers cannot be recalled; new responses are private/no-store.
5. Verify Smart Sarathi both at `/smartsarathi/` and its configured alias. API clients fetching proof images must send their existing Bearer key to `/proof.php?file=…`; never put keys in image URLs.
6. Admin → Launch readiness: add the actual complaints contact, existing mailbox and an agreed acknowledgement target. A legal review still needs to confirm published policies and operating practice.

## External launch gates still require real evidence

- `maakit.in` is a domain, not an email sender mailbox. Select an existing full email address and configure SPF/DKIM/transport privately. Leave email OTP disabled until inbox verification succeeds; do not expose passwords or API keys in chat/GitHub.
- Confirm shop stock, pack sizes, operating hours, correct shop UPI and area-specific delivery fees with their owners. Do not fabricate data to turn readiness indicators green.
- Complete one controlled order per launch area: quote → approval → shop → rider → pickup/drop codes → tracking → COD/direct-shop UPI reconciliation. Also test unavailable stock, cancellation, return and payment failure handling.
- Test OTP wrong/expired/reused codes and real inbox/SMS/WhatsApp delivery to controlled accounts. Automated mail tests use a fake transport.
- Verify low-cost Android/iPhone, denied GPS, slow 4G, backup restore and alert delivery. Phone GPS can pause when the browser is backgrounded; no native background-tracking claim is added.

## Checks

`launch-flow.php` is automatically included by `bash .github/jaanch/sab.sh`. It checks private-media authorization across guest/customer/shop/rider, disabled identities, redirect, language and Smart Sarathi tracking with synthetic CI data, then cleans up its own fixtures.

`launch-ui.cjs` uses mocked geocoding with real PHP pages to check both search fields, wrong-town filtering, stale coordinates/fare and translated checkout. Existing mail, payment, dispatch, security and restore tests remain in their established workflows. Their success does not prove real provider delivery or a physical delivery.
