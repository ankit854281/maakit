# Partner documents — owner request, 29 October 2026

Joining is a ONE-QUESTION-PER-SCREEN wizard (sr_wizard_steps): vehicle → name → Aadhaar →
PAN → "भेज दीजिए". No WhatsApp fallback (owner: nobody to handle it). Optional documents
(selfie, licence, RC) appear only after the wizard, as "ज़रूरी नहीं".

apply.php (inc/identity-apply.php) is a Rapido-Captain style flow: 1) choose vehicle + name,
2) documents one by one — selfie, Aadhaar (front+back, ONLY last 4 digits stored, masked Aadhaar
recommended), PAN (number + photo), driving licence (number + front, back optional), RC + vehicle
number — each with a 🔊 Hindi voice hint, 3) office approval. OWNER RULE: only Aadhaar and PAN
are REQUIRED; selfie, licence and RC are optional (office does not check them — vehicles may be
rented). Care Saathi is not offered licence/RC. Columns are added by sr_docs_upgrade() (call it OUTSIDE transactions). Adding a
document never changes approval status. Photos are shrunk on the phone before upload.
admin/profiles.php shows every document. Never store a full Aadhaar number.

# Two apps — owner decision, 29 October 2026

Customers and partners now have SEPARATE installable apps (like Rapido / Rapido Captain):
- Smart Sarathi (customer): ghar.php, manifest.json, mint icon, scope "/".
- Sarathi Partner: sarathi.php entry, partner.json, yellow icon (assets/partner-*.png),
  scope "/smartsarathi/", start_url sarathi.php?src=app → logged-in partner goes straight to
  kaam.php / apply.php. sr_app_kind() in inc/ui.php picks which manifest a page links.
Same OTP login. The app a person logs in from decides the role (intent=customer|partner →
sr_home_path sets it). No intent and no role → partner if they have a rider profile or were
pre-added by admin, else customer; role.php is no longer the default step.
Customer app links to partners go to sarathi.php, never straight to login.

# App v3 (Rapido-style onboarding) — owner decision, 29 October 2026

ghar.php first run: language screen (cookie sr_lang, see sr_lang_chosen() in
inc/lang.php) → welcome screen ("सारथी बनिए" → /?intent=partner, or "ग्राहक?").
intent is kept in $_SESSION['sr_intent'] and used by sr_home_path() when no role
is set. Home shows the current place (mini map, only after location permission),
a "बोलें" mic (Web Speech hi-IN) and recent/favourite places (localStorage
sr_recent: {addr, geo, fav}). Partner apply page shows a documents checklist with
🔊 Hindi voice guide (speechSynthesis). Login remembers the last verified number
(localStorage sr_last_phone) and offers one-tap "इसी नंबर से जारी रखें" — it still
sends a fresh OTP; never skip OTP.

# App v2 — owner master prompt, 29 October 2026

Customer app = smartsarathi/ghar.php (single page: home, map bottom-sheet booking,
tracking, my bookings) + assets/customer.js/css. Bookings go through book-api.php →
inc/booking.php → job_create() on the Sarathi DB (client "सीधे ग्राहक (वेबसाइट)").
Fare and km are computed on the server only; fares can be tuned in settings
(fare_<service>_base/_km/_min). Instant services auto-assign; scheduled bookings,
Saath Safar, Hospital Saathi and Door se Seva stay 'new' for office assignment
(no_auto). After OTP: role.php (Customer / Partner), stored in sr_accounts.role,
routed by sr_home_path(); switch in Account. Partner types include loader and care.
UPI QR on rider job when SR_UPI_ID is defined. tests/sarathi-booking.php runs in CI.

# One-app experience — owner instruction, 29 October 2026

Owner wants ONE professional app, not a website with many links. Maakit booking
pages (ride.php, trip.php, sewa.php, bazaar.php, track.php…) render in "Sarathi app
mode" when cookie sr_app=1 (set by smartsarathi/ghar.php, or ?app=1; ?app=0 clears):
inc/sr-app-head.php replaces the Maakit header/ticker, inc/sr-app-foot.php replaces
footer/tabbar with the app bottom nav. Manifest scope is "/" so these pages stay
inside the installed app. Login is number + OTP only (no consent checkbox; a line
of text links privacy). Keep every new customer screen in the navy/mint app style.

# Launch tracking — owner instruction, 29 October 2026

For launch, the old hosting GPS sharing (kaam.php do=location, 20 s minimum gap)
and the 60 s idle refresh are re-enabled in Firebase mode. Move tracking to
Firebase Realtime Database (module 4) before rider count grows past ~30 online.

# Customer home on Smart Sarathi — owner decision, 29 October 2026

Customer app lives on Smart Sarathi: smartsarathi/ghar.php is the 8-tile home
(बाइक · ऑटो · ई-रिक्शा first row; सामान · पार्सल · गाड़ी बुकिंग · माल गाड़ी · साथ सफ़र).
No login needed to browse; login after OTP lands on ghar.php. Tiles reuse the
working Maakit booking backend (ride.php?v=..., ride.php?t=parcel, sewa.php?s=gaadi|maal|saath,
bazaar.php) via sr_maakit_url — do not duplicate booking logic in Sarathi.
Saath Safar is currently a request form (sewa 'saath'); office matches manually.

# Simple rider entry — owner instruction, 29 October 2026

"Smart work, not hard work." Customers: plain mobile OTP login. Service people:
entry first, profile later. If the admin adds a mobile in the Sarathi admin panel
(riders list), that person's OTP login auto-links and is approved immediately
(sr_identity_autolink). Self-applicants give only name + vehicle type (vehicle
number optional); photo and licence are OPTIONAL and can be added later from the
account page; admin may approve without them. Do not re-add mandatory documents
without asking the owner.

# Go-live decision — owner instruction, 28 October 2026 (afternoon)

Ankit asked to skip the staging subdomain and take Smart Sarathi modules 1+2
live directly on smartsarathi.in. module-1-2 is merged into main. Firebase
project: smartsarathi-8fd8f. App Check and the beforeSmsSent Cloud Function are
OPTIONAL extras, not required for login; SMS region policy is India-only. The
module still stays OFF until the private flag SR_FIREBASE_ENABLED=true is added
to hosting config after the SQL migration. This supersedes the branch-only and
staging-first restriction below. Module 3 still waits for owner confirmation.

# SMS protection follow-up — 28 October 2026

Module 1+2 now requires SR_FIREBASE_APP_CHECK_SITE_KEY in test config. See
`docs/smartsarathi-sms-protection.md`. The Firebase beforeSmsSent hook uses private
Firestore transactions: phone 5/hour, IP 20/hour, staging project 20/24h. Browser
5/hour is best effort, NOT a physical-device guarantee. Never claim a GitHub push
enables App Check enforcement or registers cloud triggers. Both old broad CI
failures were verified in baseline main logs; do not claim the full suite passed.

# Branch and testing restriction — latest owner instruction, 28 October 2026

For Smart Sarathi modules 1+2, push ONLY to branch `module-1-2`. Do not push or
merge into `main`, deploy live, or migrate either live database. The owner must
first confirm staging tests pass and approve merging. Do not start module 3
until explicitly requested. This overrides all older direct-to-main permissions
below. See docs/smartsarathi-staging-test.md for backup-first isolated testing.

# Smart Sarathi owner update — 28 October 2026

The owner now requests a customer + rider app at smartsarathi.in, one mobile/Firebase
Phone Auth login and Customer/Rider modes. This supersedes the riders-only and
no-paid-service rules for Smart Sarathi. Implement in owner-confirmed modules:
1+2 identity/OTP, then 3 Maps, 4 Firebase realtime, 5 Razorpay, 6 shared trips.
Do not implement the next module until owner confirmation. Module 1+2 is enabled
only by private SR_FIREBASE_ENABLED=true after SQL/config preparation; it disables
legacy rider codes/admin-key login, hosting GPS posts and automatic rider refresh.
Existing Maakit customer flows are separate and unchanged. New profiles must be
reviewed; legacy rider IDs/history attach only during explicit admin approval.
See docs/smartsarathi-module-1-2.md. Never claim a push activates cloud services.

# Owner architecture update — 8 October 2026

The latest owner instruction supersedes the earlier Node.js/PostgreSQL exception: PHP 8.2+ with PDO and MySQL 8.0.16+/MariaDB 10.6+ is the active backend. No Node runtime or build step is required. Historical node-api/ and docs/postgresql/ files are inactive references only; do not deploy or extend them. Direct commits to main without PRs remain explicitly authorized by Ankit. Preserve private config.php, uploads/ and existing live data. New UUID API tables use the additive mk_ namespace. The PHP API is opt-in until its identity/catalog migration and keys are configured; do not claim that a code push automatically switches legacy checkout.

# Maakit — rules for anyone working on this code

**Read this file completely before changing anything.**

Both ChatGPT/Codex and Claude work on this repository. This file is the
single source of truth for both. If something here conflicts with what you
were told in a chat, **ask the owner before acting** — do not guess.

The owner is **Ankit**, Bhadohi district, Uttar Pradesh. He is not a software
engineer. Explain things to him in Hinglish, in simple words, one step at a
time. Never assume he knows a technical term.

---

## 1. What Maakit is

Owner update, 7 October 2026: Maakit targets **all of India**, including cities,
towns and villages. Delivery and bookings are enabled only in verified active
service areas; national ambition must never be shown as universal live coverage.
The original Kapsethi, Chauri and Kachhwa operation remains supported. Site: **maakit.in**. Phone **+91 84293 93903**.
Tagline: *"Anything you need. Maa hai na."*

The customer asks for **anything, from any shop** — by phone, WhatsApp, or on
the website. Maakit buys it from the shop and delivers it.

**This is the core of the business model, and nothing may break it:**

> Maakit does not sell goods and keeps no stock. The price of the goods
> belongs to the shop. Maakit charges only a delivery fee.

Phase 1 (now): pick-up and drop only. Phase 2 (later): own stock for
fast-moving items.

Alongside delivery the site also does: bookings (vehicle, goods vehicle,
lawn/marriage hall, tent & sound, halwai, pandit ji, mistri, photographer),
a directory of local shops and tradespeople, second-hand books, live order
tracking, salon seat booking with a live queue, and a **shop panel** where
shopkeepers run their own items, prices, orders and daily accounts.

---

## 2. The money rule — NEVER break this

Maakit **does not hold the customer's money**. There are exactly two ways a
customer pays for goods:

1. **Cash** on delivery
2. **UPI straight to the shop's own UPI ID** — the customer sees the
   **shop's** QR, never Maakit's

Maakit's own earning is the **delivery fee**, plus optionally a small
commission from the shop, which appears as a **separate visible line** in the
shopkeeper's own ledger — never hidden.

**Why:** in India, a platform that collects customer money and then pays it
out to sellers is doing payment-aggregator work, which needs an RBI licence
or an escrow/nodal account. By never touching the money we avoid that
entirely. **Do not propose or build anything that routes customer money
through Maakit.**

### The terms page is the written form of this rule

`niyam.php` (linked in the footer, and from the shop and rider join forms)
states the money rule in plain Hindi for all three audiences. Sections:
`?k=graahak` · `?k=dukan` · `?k=sarathi` · `?k=sab`.

**If the way the business works ever changes, change `niyam.php` first and
the code second — never the other way round.** Terms that do not match
practice are worse than no terms: on the day of a dispute, they are read back
against Ankit. `.github/jaanch/panne.php` asserts the money-rule sentences
are present in each section; if you remove one, the जाँच goes red.

### Two things still need a real lawyer — tell Ankit, do not quietly "fix" them

No lawyer has read `niyam.php`. It describes Ankit's actual practice honestly,
which is the right starting point, but two areas carry real legal weight:

1. **Is a sarathi a worker or a partner?** The page says he sets his own
   hours, has no fixed shift and may work elsewhere — all true today. But in
   India this is decided by *how the relationship actually works*, not by what
   a document calls it. Fixed shifts, exclusivity or penalties would make it
   look like employment (PF/ESI, minimum wage, and the gig-worker provisions
   of the Social Security Code). **Do not add features that impose shifts,
   exclusivity or fines on riders without telling Ankit this is the trade-off.**
2. **Grievance officer.** India's e-commerce rules expect a named grievance
   officer with contact details and a response timeline. Right now the page
   gives only Ankit's phone. A name and a stated timeline should be added.

Also still missing by choice, and written as missing rather than hidden:
**no accident insurance for riders.** It is stated plainly on the rider page.
If Maakit ever provides cover, say so there.

---

## 3. Who uses this, and the one rule about them

| Who | What they are like |
|---|---|
| Customer | Village people, all ages. Cheap Android phone, slow 4G, often one shared family phone. |
| Shopkeeper | Kirana, medical, sweet shop, mobile shop. Logs in with **mobile + a code**, never a password. |
| Delivery boy | Staff, on a bike. |
| BPO staff | Staff, take phone and WhatsApp orders. |
| Designer | Can only change offers, photos and items. |
| Admin | Ankit. |

### NEVER design as if village people are uneducated.

Bhadohi has students, teachers, shopkeepers and young people. They use
WhatsApp, YouTube and UPI every day. They are not slow — **their internet is
slow.**

So the yardstick for every decision is **not** "is this simple enough for a
villager?" It is:

> **Is this fast, and does it look trustworthy?**

Which means:

- **No cartoon icons, no toy-like illustrations, no childish graphics.**
  Clean single-colour line icons for categories. Real photographs for goods.
- **Category names follow the convention real Indian apps use**: two or three
  things joined with `&` (`Atta, Rice & Dal`), and for tradespeople **list the
  trades, don't name the function** (`Electrician, Plumber & Carpenter`, never
  "Repairs"). Category tile names are in **Roman script** (`Kirana`,
  `Masala`) with a small Hindi line underneath — this is what real Indian
  apps do.
- **Interface language is Hindi (Devanagari) with an English toggle.** Hindi
  is the default. Use `t('English text', 'हिन्दी')` for every visible string.
- **Mobile first**, 360–400px wide, on slow 4G. Page weight matters more than
  animation.

---

## 4. Hard rules — do not break these

1. **Never use photographs taken from Google or any website.** Only photos
   Maakit has taken itself.
2. **Never fabricate content** — no invented offers, no fake reviews, no fake
   "3 people are viewing this", no fake urgency. If there is no offer today,
   show no offer.
3. **Never write a claim that will become false.** Example: do not write "no
   commission" — commission is 0 today but is a per-shop setting. Write what
   stays true: "whatever share Maakit takes is shown to the shopkeeper in his
   own ledger."
4. **Never put every feature in the admin login.** Each role sees only its own
   work. A designer sees offers and photos, nothing else. A shopkeeper sees
   only his own shop.
5. **Never route customer money through Maakit** (section 2).
6. **Never touch `config.php` or `uploads/`** in any automated process. They
   are not in this repo and the updater skips them.
7. **Vehicle documents are verified by a human eye.** Do not automate that.
8. **No dependency Ankit cannot fix from cPanel.** No framework, no build
   step, no Docker, no paid service, no npm at runtime.

---

## 5. How the code is built

- **PHP 8, MySQL/MariaDB, no framework.** No React, no build step.
- **PDO with prepared statements everywhere.** Never concatenate user input
  into SQL. Escape every output with `h()`.
- GoDaddy shared hosting, cPanel. Code lives on GitHub.
- **Deployment:** Ankit opens one link — `maakit.in/update.php?key=…`. That
  file pulls the latest `main` from GitHub, backs up the old files, dumps the
  database, then runs any new SQL. It never touches `config.php` or
  `uploads/`.
- **Database changes are files in `sql/`**, numbered (`009-…sql`), and they
  **must be safe to run twice**. Guard every `ALTER` with an
  `information_schema` check; use `CREATE TABLE IF NOT EXISTS` and
  `INSERT IGNORE`. Start every migration with `SET NAMES utf8mb4;` or
  Devanagari comparisons silently match nothing.
- The site installs on a phone like an app (PWA) and works offline for basic
  pages.

### Where things live

| Path | What it is |
|---|---|
| `index.php` | Home page |
| `order.php` | The goods catalogue and cart |
| `search.php`, `inc/search.php` | One search across goods, bookings and shops |
| `sewa.php` | **Bookings.** (`book.php` is the **old-books** page, not booking) |
| `shop.php`, `inc/dukan*.php` | Shop panel — items, orders, ledger, UPI. Shell (Back, breadcrumb, KPIs, tabs) is `inc/dukan-shell.php` + `inc/dukan-shell-end.php` (toast, auto-save, new-order poll `shop.php?poll=1`); salon tabs in `inc/dukan-salon.php`. Styles are `.sp-*` in `assets/mk.css`. `item_daam`, `item_stock`, `khuli` answer JSON when `ajax=1`, and redirect as before without it. |
| `dukan-se.php` | Customer ordering from one shop's own items |
| `login.php` | **The only login door** — shopkeeper (mobile+code) and team (user+password) |
| `business.php`, `directory.php` | Local shop and tradesperson directory |
| `track.php` | Live order tracking |
| `admin/`, `bpo/`, `delivery/` | Staff panels |
| `inc/fn.php` | Core helpers — `t()`, `h()`, `csrf()`, `calc_charge()`, categories |
| `inc/icons.php` | All SVG icons. Check a name exists before using it. |
| `muneem.php`, `nakal.php`, `.github/chowkidar/` | The helper bots (section 7) |
| `sql/` | Numbered, idempotent migrations |

---

## 6. Working together without redoing each other's work

Two different AIs work on this repo. **The last push wins**, so without
discipline one of us silently erases the other's work. These rules exist to
stop that. They are not optional.

1. **Always pull before you start, and again before you push.**
   `git pull --rebase origin main`. If you keep a working copy outside the
   repo, sync *from* GitHub into it first — never copy your whole folder over
   the repo without pulling.
2. **Never force-push. Never revert someone else's commit** without the owner
   saying so.
3. **Owner update, 9 October 2026: commit and push directly to `main`; do not
   create pull requests.** Read the latest remote head before every push, run
   relevant checks, and preserve other contributors' changes. Never force-push.
   The owner explicitly requested Node.js/PostgreSQL API modules for Tasks 1–3;
   these require a separately provisioned Node/PostgreSQL runtime. Adding them
   to GitHub does not convert the existing PHP/MySQL hosting or activate platform
   payment collection.
4. **Before you change a file, read it.** If a section carries a comment
   explaining *why* it is that way, that comment is a decision the owner
   already made. Respect it or ask.
5. **One feature at a time.** Do not reformat, rename or "tidy" files you were
   not asked to change — that is what creates invisible conflicts.
6. **Write down what you did**, in the commit message, in enough detail that
   the other AI can understand it without reading the diff.
7. **If you remove or replace something, say so loudly** in the commit message
   and tell the owner.

### Finish what you start

If you add a page, wire it up. If you change where a form posts, check what
the old target was doing that the new one is not. The real example: the
search page was added and the home page's search box was pointed at it, but
the new page did not write to `search_log` — so मुनीम's "what are people
searching for that we don't have" report would have gone blind. Nothing
looked broken. **Check what you displaced, not only what you added.**

### Test with real data before saying it works

There is a जाँच workflow (`.github/workflows/jaanch.yml`) that runs on every
push and every pull request. It lints every PHP file, runs all migrations
twice on a fresh database, boots the site and opens the main pages. **If it
goes red, fix it before telling the owner it is done.**

**Run them all with `bash .github/jaanch/sab.sh`, not with your own loop.**
The जाँच workflow runs that same script (21 Oct 2026), so a new check file is
picked up automatically. Before that, CI had a hand-written list of test files
and four finished checks — एक काम, पैकेट, ढिंढोरची, घंटी — were missing from
it, so on GitHub they never ran at all. **A check that does not run is not a
check.** When it fails, `sab.sh` also prints a GitHub `::error::` line per
broken check, because the run log lives on another host and is awkward to
read; the reason now shows on the run page itself.

To reproduce CI locally, build the database the way CI does — drop
`maakit_jaanch`, run every `sql/*.sql` twice, and use a `config.php` copied
from `config.sample.php` with only DB_* and a generated `MUNEEM_KEY` filled
in. A local database carrying seed rows from earlier work hides exactly the
failures CI finds.

Most checks run as `php <file>`, but `backup-flow.php` does not. It needs
three steps — `export`, then restoring the dump into a *fresh empty*
database, then `verify`. Run bare, it correctly refuses with "Choose export
or verify". A hand-rolled `for f in .github/jaanch/*.php` loop reads that
refusal as a failure, and the owner gets told something is broken when it is
not. That has already happened once. `sab.sh` knows the three-step dance and
prints an honest count.

**A wrong count is more expensive than no count** — it either makes a broken
thing look fine, or sends Ankit chasing a bug that was never there. Never
report a number you have not produced with `sab.sh`.

### A check must only go red when something is actually broken

Three checks went red on 21 Oct 2026 while nothing was broken, and all three
were the test's fault, not the code's:

- `kisne-kiya.php` wanted the literal string `2/7`. Two seed orders arrived
  in the test database and the page correctly showed `4/9`. **Never assert an
  absolute count that includes rows you did not create.** Count first, then
  assert the difference.
- `jyotish-flow.php` wanted the words `Sankalp poora`; the code now says
  `sankalp poora`. **Assert on ids, classes, `data-i18n` keys and function
  names — never on visible prose.** Visible language changes, and should.
- `partner-flow.php` tripped the live 15-minute flood guard on a second local
  run. A check must clear its own scope rows first (see `earning-flow.php`)
  instead of going red because a safety feature worked.

A red mark that means nothing is worse than no mark, because after two of
them nobody looks at red again.

### The lesson from `faculty-flow.php` (21 Oct 2026)

For days this check was green here and **red on GitHub every single time**:

```
Faculty: Credential save   (.github/jaanch/faculty-flow.php)
```

The cause was not the environment. The check assumed a `settings.main` row
with a `session` already existed — and in this workspace one did, left behind
by an older run of a different check. CI builds the database from migrations
every time, so there it never existed.

Behind it sat a real product bug: with no session saved,
`school/faculty_api.php` built the CPD window from `substr('', 0, 4)`, i.e.
from `-04-01` to `1-03-31`, so **every** CPD date fell "outside the session"
and the teacher was told the date was wrong. The honest answer is that the
school session was never filled in, and the endpoint now says exactly that.

Two rules come out of it:

- **A check sets up everything it needs and puts back what it found.** One
  that leans on another check's leftovers passes here and fails there, and a
  check that is red every time is the same as having no check at all.
- **A defaulted value that cannot be right is worse than an error.** Guessing
  a session window from an empty string produced a confident, wrong message
  that pointed at the wrong thing.

---

## 7. The helper bots already running

| Name | What it does |
|---|---|
| **चौकीदार** | Every 15 min checks the live site's pulse; twice a day a full check (tiles, photos, forms, order list, shop login). Opens a GitHub issue if something breaks. |
| **मुनीम** | A weekly ledger — what sold, what didn't, which items have no photo or price, which shops have gone quiet, **what people searched for and did not find**. Since 21 Oct 2026 it also shows profit and loss (`$B['paisa']`: in, out, left, per order, petrol, staff) and says plainly which days have no costs recorded, because a day with no costs recorded looks more profitable than it was. **Goods money is deliberately excluded** — it never belongs to Maakit. |
| **नक़ल** | Gzipped database backup. Weekly automatically, and before every migration run. Stored on the same hosting, so it survives a bad migration but not a lost account. |
| **संदूक़** | `.github/workflows/sanduk.yml` — every night it has नक़ल make a backup, **downloads it** and keeps it on GitHub for 90 days, off the hosting. It checks the file is really gzip and really contains tables; a 403 page saved as `.sql.gz` was caught exactly this way. Needs `MUNEEM_KEY` in GitHub Secrets. |
| **पैकेट** | `inc/packet.php`, `admin/packet.php`, `sql/043` — ready-made sets ("₹100 की सब्ज़ी", "पूजा का सामान"), because in a village nobody picks from a list of 40 items; they say "सौ रुपये की सब्ज़ी भेज दो". The bot builds budget sets from the shop's own items and **cannot make them live** — only the owner can. Two rules are built into the schema, not into a page: **a pack's price is never stored** (it is added up from `shop_items` every time, so the shop always owns the price) and **one pack = one shop** (`pack_banao()` refuses another shop's item, because goods money goes straight to one shop). A pack with anything out of stock or unpriced hides itself completely — showing a set and then dropping items from it is a lie. On the customer's page a pack only fills the existing quantity form; it is not a second order path. Test: `.github/jaanch/packet-flow.php`. |
| **घंटी** | `inc/ghanti.php`, `.github/workflows/ghanti.yml` — every 15 minutes, **only what is going wrong right now**: a new order untouched for 20 minutes, a confirmed order with no rider for 30, goods picked up but not delivered in 2 hours, open orders with nobody on duty, a complaint unanswered for an hour. Everything that matters but does not rot within half an hour belongs to एक काम instead. It is **silent outside 6:45–22:15 IST** (cron window + `ghanti_waqt()`), because an alert nobody can act on teaches the owner to ignore alerts — and then he ignores the real one too. One issue, and it says nothing while the situation is unchanged. Test: `.github/jaanch/ghanti-flow.php`. |
| **सारथी का साथी** | `saathi_baaki()` and `saathi_rate_nahi()` in `inc/ghanti.php` — who is owed money and for how many days, **counted from delivered orders** (`sarathi_khata()`) and payments recorded in `sarathi_khata`, never from anyone's word. The owner decided riders are paid per order, not a salary: a per-order earner is a daily earner, so held-up money means he quietly takes other work and there is nobody on the day an order comes. `saathi_rate_nahi()` exists because with no rate set the balance silently computes to **zero** — the owner would think everyone is paid up while the rider thinks he was cheated, so एक काम asks for the rate before it asks for the money. |
| **ढिंढोरची + पंडित जी** | `inc/dhindhora.php`, `admin/dhindhora.php` — today's WhatsApp message and poster, and the advance warning for what is coming. **Every line is built from a record** (approved shops, live packs at the shop's own price, live areas, the panchang in `inc/festival.php`); when there is nothing true to say it returns `null`, because in a village a false claim works once and that reader never comes back. `pandit_aage()` warns BEFORE a festival (goods are ordered 4–5 days ahead) and an ongoing festival always wins over an upcoming one. `pandit_mausam()` uses the month only — Maakit has no weather data, so "सर्दी आ रही है" is true and "आज 8 degree है" would be a lie. **It never sends anything**: one mistake would reach a hundred people, and WhatsApp blocks numbers that auto-send. "ले लिया" records the date so एक काम stops asking. Test: `.github/jaanch/dhindhora-flow.php`. |
| **एक काम** | `.github/workflows/ek-kaam.yml` + `inc/ek-kaam.php` — every morning it names **one** task, the one whose being stuck is holding up everything else, and keeps one GitHub issue open until it is done. Never two: given a list, a person picks the easiest item, not the most important one. The same single task is shown at the top of `admin/super.php` and inside मुनीम — one truth in three places, which `.github/jaanch/ek-kaam.php` checks. If you add a stage to the ladder in `inc/ek-kaam.php`, **order matters**: the OTP key stays first, because without it there is no first order however many shops have joined. |
| **डाकिया** | One-tap WhatsApp messages for each order stage. |
| **जाँच** | Checks every push and pull request (section 6). |

If you add a page, **add it to `.github/chowkidar/pages.mjs`** so चौकीदार
watches it too.

---

## 8. Language and tone of the interface

- Hindi first, English toggle. Every visible string goes through `t()`.
- Write like a person, not like a form: `दाम भरिए`, not
  `मूल्य प्रविष्ट करें`.
- Error messages say **what went wrong and what to do next**. Never show a
  technical error to a user.
- Buttons say what will happen. "दे दिया", not "सबमिट".

---

## 9. Colours and type

```
blue         #2563EB   brand — header, primary buttons
deep blue    #1D4ED8
light blue   #93C5FD   highlight, secondary button, logo accent (var --gold, name kept)
page         #F5F9FF   page background
soft         #E6F0FD   boxes and fills
line         #D3E2F5   borders
ink          #0F1E33   text
muted        #4B5D75   small text
green        #1F7A4D   success
red          #A3360A   error
```

Font **Poppins + Noto Sans Devanagari**, 17px base, line-height 1.6.
Devanagari headlines need line-height ≥ 1.2 or the matras collide.
Rounded corners 11–16px, buttons at least 50px tall, soft shadows.
**No gradients, no glassmorphism.**

Use the CSS variables (`var(--brand)`, `var(--gold)`, `var(--soft)`,
`var(--line)`, `var(--muted)`), never raw hex in new code.

Since 11 October 2026 the site uses ONE colour, light yellow header like Blinkit (`--head:#FBE38E`) with green buttons (`inc/theme.php`: brand #0C831F, yellow #F8CB46), on a white page. On the home page the festival/hero section is full-width and fades from the header yellow into the page (no card), and search floats at the bottom as frosted glass that shrinks on scroll. New design rules live in `assets/mk.css` (header `.mk-head`, home `.mk-*`): compact product cards (image ~104px phone / 140px desktop), 10–12px corners, no capsule overload, and a desktop layout from 900px with a 1200px container. The festival strip and banner come from `inc/festival.php` (dates per year; Admin > समय/छुट्टी/त्योहार turns it off). Product photo slides (up to 7 per catalog item) are in `catalog_photos` (sql/031), uploaded at `/admin/product-photos.php`. `/admin/auto-photos.php` finds photos ONLY in free libraries (Wikimedia Commons, Open Food Facts) from the admin's browser; the server downloads only from `upload.wikimedia.org` / `images.openfoodfacts.org` (`inc/photo-fetch.php`), refuses NC/ND licences, and stores credit + licence + source page in `catalog_photos` (sql/032). Credits must stay visible (`/photo-credits.php`, credit line on the product page). Never add Google or brand-site images. Products with no photo show `catalog_tile_html()` (icon tile), and shop items without their own photo fall back to the catalogue photo (`dukan_cat_photos()`).
Always use the CSS variables, never hex, so every palette keeps working.

Language: an `EN | हिंदी` switch sits at the far right of the header on every page (`lang_url()` in `inc/lang.php`).

---

## 10. How Maakit grows — the order of work

The name is national. The operation is not, and must never pretend to be.
This section exists so neither AI builds breadth while depth is still empty.

**Where Maakit can win, and where it cannot.**
"The big apps don't come here" is true in a village. It is false in Varanasi,
Lucknow or Delhi, where Blinkit, Instamart and Zepto deliver in ten minutes
with far more money behind them. In a metro, "anything from any shop" is a
slower offer, not a better one. What does scale nationally is the opposite
end: the lakhs of villages and qasbas those companies will never serve.
**So national growth means more small towns, not big cities.**

**Software travels in one click. A delivery boy does not.** Every new area
needs real riders, someone local to manage them, shops onboarded and cash
handled. That is why expansion is area by area, and why the natural shape is
a **local partner per area** — someone who runs their own riders and shops the
way the owner does in Bhadohi, with Maakit providing the platform, the name
and the accounts. When the first partner is real, build a partner login and a
per-area ledger. Do not build either before then.

**Depth before breadth. The current bottleneck is not features.**
At the time of writing the catalogue holds 196 shop types and 1,510 entries,
while the number of real shops with real prices, real pack sizes and their own
photos is close to zero. One village with ten shops genuinely running proves
the model. A hundred empty shop types prove nothing, and an empty shop page
costs trust that does not come back.

**Before a second area opens, the first one should show:**

- about ten shops with real prices, filled in and kept current by the
  shopkeepers themselves, not by the owner
- orders repeating from the same customers week after week — repeat orders are
  the signal, total orders are not
- one delivery boy busy enough to be worth his pay

Until then, work that adds depth (getting real shops, prices and photos in,
making the shop panel easier, making ordering faster) comes before work that
adds reach.

**What this means for code right now:** the area gating that already exists is
correct and sufficient. Do not add partner accounts, franchise billing,
multi-city dispatch or courier integrations until there is a real partner or a
real second area asking for them.

---

## 11. What Ankit wants this to become

One platform for customers across India to find shops, request local delivery
and book vehicles/services. Expansion is area by area after operational readiness.
Do not invent national availability, delivery times, GPS dispatch or courier integration.

As fast and as polished as a big app, but clearly from here: Indian, warm, in
Hindi, run by someone from the same district who will pick up the phone.

**Not a marketplace that squeezes shops.** A layer that makes the local shops
that already exist reachable from a phone.

## Delivery = Smart Sarathi (12 Oct 2026, owner decision)

- Riders have ONE login: `maakit.in/smartsarathi/` (own DB, `smartsarathi/inc/config.php`). `/sarathi/` redirects there; the old Maakit-internal rider app still answers at `/sarathi/?purana=1` only for transition and CI.
- Rider applications live on Smart Sarathi (`smartsarathi/apply.php` → admin "नई अर्ज़ियाँ" → approve creates the rider + one-time login code). `public/partner-apply.php?kind=rider` GET redirects there.
- Smart Sarathi is RIDERS ONLY (owner, 12 Oct 2026). Its public tabs are just "Join as rider" and "Rider login". `smartsarathi/book.php` 301-redirects to `maakit.in/ride.php?t=parcel`; `smartsarathi/track.php` redirects to `maakit.in/trip.php` unless an old `SR…` number is given (`sr_maakit_url()`, override with `SR_MAAKIT_URL`).
- Customers book on Maakit: `ride.php` (ride: Bike/Auto/E-rickshaw, or `?t=parcel`) → table `rides` (sql/034, functions in `inc/trips.php`) → sent through the same bridge (`sb_call create`, `kind=ride|delivery`, `pick_geo/drop_geo`, own 4-digit `pick_code/drop_code`). Tracking/cancel: `trip.php` (status synced every 15 s). Fare = base + km × rate (settings `fare_{bike,auto,erick,parcel}_{base,km,on}`, `ride_max_km`; Admin → Sarathi), km = straight GPS distance × 1.3, always shown as "approx." and recomputed on the server. Bookings that could not reach Sarathi show red in Admin → Sarathi with a "भेजिए" button. Map = Leaflet + OpenStreetMap tiles; place search = Nominatim only on button/Enter (their policy forbids type-ahead). Test: `.github/jaanch/ride-flow.php`.
- Rider app (`smartsarathi/kaam.php`): go-online, picked-up and delivered are swipe sliders (`[data-slide]`, JS in `inc/ui.php`); the real submit button stays inside for no-JS. Jobs with `kind='ride'` show passenger wording and ask for the start/end code.
- Maakit orders reach riders through the API bridge `inc/sarathi-bridge.php` (settings `sarathi_api_url`, `sarathi_api_key`, `sarathi_auto`, set in Admin → Sarathi). The order's shop OTP (`orders.pick_otp`) and customer code (`orders.code`) are sent as the job's `pick_otp`/`drop_code`, so nobody learns new codes. Status comes back on admin orders and `track.php` (`sb_sync`, once per 60 s). Columns: `orders.sarathi_job/sarathi_status/sarathi_checked` (sql/033).
- Never ship a login that accepts a fixed/master OTP or trusts a plain cookie; real OTP needs an SMS provider.
- OTP channels (`smartsarathi/inc/fast2sms.php`, shared by Maakit and Sarathi): WhatsApp Cloud API first (`WA_*` config, no DLT), then Fast2SMS SMS (`FAST2SMS_ROUTE='otp'` = no-DLT OTP route, or Smart OTP IDs). Codes are always generated and verified locally. Pickup/delivery codes go only through WhatsApp utility templates or DLT SMS templates — never as a bare "Your OTP". Setup: `docs/fast2sms-setup.md`.
- One rider = one Smart Sarathi login for deliveries AND rides. `riders.do_delivery/do_ride` (rider's own choice in Profile), `riders.ride_ok` + `riders.vtype` (office sets after checking DL/RC). `job_auto_give` matches: delivery → do_delivery; ride → do_ride AND ride_ok AND vehicle fits (`sr_ride_fits`). Going online (`duty_on`) also hands out waiting jobs.

## School ERP = maakit.in/school (16 Oct 2026, owner decision)

- A separate product hosted under Maakit for SN Mishra Singapore International School, Bhadohi (Ankit was its principal). It shares only the hosting, `config.php` (database constants and `UPDATE_KEY`) and the "Powered by Maakit" credit. It never reads or writes any Maakit table.
- Files: `school/index.php` (page), `school/app.js` (whole app, plain JS), `school/app.css` (pre-built static CSS — no build on the server; if you add new Tailwind classes, rebuild it locally with Tailwind 3 and commit the file), `school/api.php` + `school/inc.php` (JSON API). Tables: `sql/036-school-erp.sql` — `sch_users`, `sch_sessions`, `sch_records`, `sch_log`, `sch_login_fail`.
- Data model: each piece of school data is one row in `sch_records` (`k` kind, `r` key) holding JSON, so two teachers saving at once never overwrite each other. Deletes are tombstones (`deleted=1`) so other phones learn about them. Phones fetch changes every 45 s (`?a=since`).
- Login: first visit asks for the website `UPDATE_KEY` and creates the Principal PIN ("Forgot PIN" on the login screen resets it with the same key). The Principal sets every other PIN (4–8 digits by owner choice, `password_hash`). 5 wrong PINs lock that login for 15 min; 15 wrong in total (`sch_users.strikes`, sql/037) block it until a new PIN is set; 20 wrong tries from one IP block that IP for 15 min. Token is sent in the `X-School-Token` header, never a cookie.
- Permissions are enforced on the server (`sch_can_write`): Principal writes everything; Chairman reads only; a teacher writes only their own diary, attendance of the class they are class teacher of, syllabus and marks of subjects they teach, and their own leave request while it is Pending.
- Every action goes to `sch_log` (Activity Log, exportable as CSV by Principal/Chairman).
- Added 16 Oct 2026: Word of the Day (366 built-in words, English + Hindi meaning + example) and ~120 conversation starters, both chosen by date in `school/app.js`; Behaviour points (`behav`, date-keyed), Speaking activities with badges at 5/10/20 (`speak`), weekly Lesson Plans checked automatically against the teacher's diary words (`lplan`, key `weekMonday|tid|class|subject`, teacher can only set "Submitted", Approved plans are locked), School Calendar (`cal`, Principal only; a Holiday removes that day's bells, arrangement and alerts everywhere), and an automatic monthly Hall of Fame.
- Interface is English: the school is English-medium CBSE and staff asked for English. Parent WhatsApp messages are bilingual.
- First PIN 1234 (owner, 16 Oct 2026, sql/038): everyone except the Principal can log in the first time with `1234` (`SCH_DEFAULT_PIN`) — teacher, chairman, parent (mobile on a student record) and student (admission number; refused if the number is shared). That login sets `sch_users.must_change=1`; `sch_auth()` then refuses every API with HTTP 428 until the person chooses their own PIN through `setpin` (1234 is not accepted as a chosen PIN). The Principal "resets" anyone by setting their PIN to 1234. sql/038 also cleared existing teacher/chairman/parent PINs once — never edit that file or the updater will run it again. New role `student` (uid `s:<student id>`) uses the parent screens and `pdata` with only that one child; it is outside Learning Lab / Studio / Toolkit, which allow only principal/teacher/parent/chairman. Teacher login with tid `P` is refused (the Principal uses the Principal login).
- Exams & board plan (16 Oct 2026, owner): exam records (in `exams/main`) may carry `classes`, `to`, `noClasses` (normal bells, diary-due and arrangement stop for those classes during the exam), `sheet` (date sheet; a paper can combine timetable subjects, e.g. Science = Physics/Chemistry + Biology, each teacher enters their own part), `time`, `reportDate` and `sent` (server strips `sent` from `pdata`). `settings.sylDeadlines` = class → date by which 100% of chapters must be done; pacing (`sylStatus`) and dashboard alerts use it. The Class 10 plan 2026–27 is written once by `applyBoardPlan()` on Principal login (flag `settings.plans['board-2026-27']`): Pre-Board 1 21–28 Dec with date sheet (Computer/IT 50 marks, others 80), deadline 19 Dec, Christmas 25 Dec, evaluation 29 Dec, PTM 30 Dec, winter vacation 31 Dec–14 Jan, doubt workshops 16–24 Jan, Pre-Board 2 1–8 Feb (date sheet to be filled), Annual Exam PG–9th 23 Feb–11 Mar. Screen: Principal/Chairman › Exams & Date Sheets (results + one-tap WhatsApp per parent, only when every paper is marked).
- Board plan v2 (16 Oct 2026, owner, replaces v1 the same night): `applyBoardPlan()` flag `board-2026-27-v2`. Classes 9 AND 10: full syllabus by 5 Dec (`sylDeadlines`); Pre-Board 1 7–12 Dec (results 15 Dec); Pre-Board 2 21–28 Dec (the owner's original date sheet; PTM 30 Dec); winter vacation 31 Dec–14 Jan; Class 10 board study leave from 16 Jan (no February pre-board); Class 9 revision 16 Jan–20 Feb, then Annual Exam PG–9th 23 Feb–11 Mar. v2 removes the v1 calendar entries `bp-syl`, `bp-eval`, `bp-ptm`, `bp-doubt`.
- Fee structure (16 Oct 2026, owner): record `fees/main` (Principal writes; teachers cannot), seeded once by `applyFeeSheet()` from the school's printed 2024–25 sheet: composite fee per class (Class 10 blank on the sheet), yearly charges (exam fee not for PG–UKG), admission fees (waived), bus slabs by km (above 30 km blank), third child 50% off composite (the lowest-fee children beyond the first two in a family = same 10-digit mobile), printed notes. Bank details are deliberately not stored. Student records may carry `km` (bus distance, blank = no bus). Screens: Principal/Chairman › Fee Structure (edit + each student's monthly fee), Parent › Fees (`pdata.fees`). This is the structure only — no bills, dues or receipts yet.
- Game Zone (17 Oct 2026, owner: auto-generated, fun, game-like; no teacher writing): `school/game_api.php` + `game_lib.php` + `game_words.json` (server copy of the 366 Word-of-the-Day words; keep in sync with WORD_BANK in app.js). Kinds `gameR` (round, key `sid|date|n`), `gameMiss` (spaced review per student), `gamePack` (AI topic pack per class/subject/topic), `gameLock`; excluded from legacy load/sync by the `game` prefix. Questions: maths generator per class band (deterministic from a seed, CI re-checks 24,000), English word questions (Class 3+), AI topic packs for taught diary topics/finished chapters (only when `SCHOOL_AI_API_KEY` + `SCHOOL_AI_MODEL` are set; a second AI call must agree on every answer, `gm_pack_keep`; 2 student reports hide a question until the subject teacher restores/removes it), and approved Quiz Duel questions. Every question carries `fun` (memory hook), `rule` and an English `word` with Hindi. 5 questions a round, 20 s each, the clock starts on `next` (not while reading feedback); max 4 rounds a day; XP 10/right (15 in revenge), +5 per answer from a 3-combo, +20 per day played; levels, streak, a chest every 10 right, wrong answers return after 1, 3, 7 days, class weekly mission (25 right answers × class size, min 100), weekly XP league. Packs are made in the background after replying to `state` (`fastcgi_finish_request`, one per class per 10 min) or by a teacher button. Student/parent main tab: Game Zone. Test: `.github/jaanch/game-flow.php`.
- Timetable fixed bells (17 Oct 2026, owner: "Monday to Saturday same bell"): `ttTry` in app.js adds a cost for every class/subject that moves bell across the week (weight `W=1.0`, 450,000 swaps). About 81–84% of class-bells keep the same subject all week with 0 clashes; the rest (shaded in "One Class" view) are where it cannot fit. `ttFixedInfo()` gives the % shown on the Timetable page. W=1.5 made generation fail on some seeds — test 10 seeds before changing it.
- Arrangement v2 (17 Oct 2026): `staffAtt[date] = {absent:[tid], from:{tid: bell}, subs:{'absentTid|bell': tid or '-'}}`. `from` = half day (absent from that bell; earlier bells stay theirs, see `absentFrom/absentAt`). `subs` = the Principal's own choice for a slip ('-' = Principal supervises); auto fills the rest, scoring same subject +5, knows class +2, and sharing duty fairly (−3 per extra today, −0.8 per arrangement bell this month via `monthLoad`, −0.3 per teaching bell today). `freeAt(date, bell)` = who is free (not absent, not teaching, not already arranged). Staff page: half-day picker, slip dropdowns, "Who is free" table, monthly duty counts, "Send all to staff group" WhatsApp. Timetable has a "Free Bells" tab (weekly pattern).
- School office (17 Oct 2026, owner: accountant Akash Tiwari, reception Shiva Rai calls parents about dues): roles `accountant` and `reception` (uid = role, PIN 1234 first then own; Principal resets in Settings; names in `settings.accountantName/receptionName`). Login: Teacher / Student / Parent / Staff → Staff has Principal, Chairman, Accountant, Reception. Office logins are in `SCH_NO_LOAD` (inc.php): no `load/since/day/pdata`, sync rejects them — they only use `school/office_api.php` (+ `office_lib.php` for the maths). Kinds `acctPay` (receipt, r = 000001), `acctSeq` (counter, row lock), `acctNonce` (same button twice = one receipt), `acctStu` (old due, own monthly fee, free scheme, concession), `acctCall` (call result, per family = same mobile); `acct` prefix kept out of legacy load. Dues = old due + yearly fees (from the session's first month) + monthly × months from `sessionStart` up to this month (max 12) − concession − live receipts dated in this session. `of_fee_for` in PHP mirrors `feeFor` in app.js — change both together. Accountant: Fee Desk (search, family dues, take fee + 2-copy receipt, fee settings), Dues, Calls, Receipts. Reception: Calls (follow-ups due today, not called this week, all, call log; tel: and Hindi WhatsApp reminder), Dues, Receipts (view). Only the Principal cancels a receipt, with a reason (logged). Chairman sees Fee Dues. Parent Fees page shows "Fee account" (dues + receipts) from `?a=mine`, own children only. Test: `.github/jaanch/office-flow.php`.
- Syllabus + lesson plan from photos (17 Oct 2026, owner: teachers photograph the book's contents page / their plan instead of typing): `school/scan_api.php` + `scan_lib.php`. `?a=read {mode: chapters|lplan, cls, subj, images ≤4}` — teacher only for own class-subject, or Principal. AI (OpenAI Responses, `SCHOOL_AI_API_KEY` + `SCHOOL_AI_VISION_MODEL` or `SCHOOL_AI_MODEL`) reads the photo; when AI is off or fails the reply is `noAi` and the phone reads it with Tesseract.js 5.1.1 from jsdelivr (eng+hin). Photos are never stored; the teacher checks/edits the list and only then it is saved through normal sync (`syl`, `lplan`). 40 reads per user per day (`scanUse`, `scan` prefix out of legacy load). `SCHOOL_AI_TEST_URL` env exists only so CI can use the fake AI in `.github/jaanch/scan-fake/`. Syllabus chapter = `{n, done, plan:'YYYY-MM', topics[]}`; "Spread month-wise" (`sylSpread`) fills `plan` up to the class deadline (`settings.sylDeadlines`) or the subject's finish date or February; `sylStatus` counts chapters planned for past months as "should be done". Lesson plan: "Fill from syllabus" (`lpFromSyllabus`, one line per weekly period, skipping topics already planned) or a photo; drafts live in `LPDRAFT` until submitted. Principal's Syllabus page lists teachers still to update, with a WhatsApp reminder. Test: `.github/jaanch/scan-flow.php`.
- ERP "Pro" redesign (18 Oct 2026, owner, 3rd pass — latest wins): owner shared a soft-grey dashboard with pill menu buttons and asked for "these left-side panels on top, this kind of colour, redesign the whole ERP". Desktop first. One top bar on the grey page (no white strip): black "SN" logo with lime letters · school name · PILL menu groups with dropdowns (Dashboard · Staff ▾ · Students ▾ · Admin ▾; teacher Today · My Classes · Planning · Me; parent Home · Learning · Progress & Fees) · "Saved" pill · user pill (Settings, Log out); small breadcrumb. Active pill = lime #EAFB8C; idle pill #E2E2E7. Page #EDEDF0 with a soft lavender glow; white cards radius 24px, no borders; near-black text #1C1C1E; primary buttons near-black pills, "go/save" buttons lime; pastel stat cards `kpi kpi-peach|kpi-lav|kpi-mint|kpi-lime|kpi-warn` with an icon square (`kpi-ic`) then value, label, sub-line; attendance tiles mint/lavender/peach; Inter font. No sidebar, no bottom tab bar, no floating dock, no dark header — do not bring them back. Groups per role are in `GROUPS` (app.js); any NAV page not listed joins the role's last group, but put new pages in the right group. Dropdowns: `ddToggle/ddClose`. All of it lives in `school/erp-pro.css` (plain CSS, loaded LAST in index.php so it wins over app.css and design-2026.css; neither of those is edited for the look). Embedded pages (Studio, Learning Lab, Toolkit, Profile, Saathi, Fee Department) keep their own inside styling; they no longer get a second title above the iframe.
- Staff salary (18 Oct 2026): `school/salary_api.php` + `salary_lib.php`, page `salary` (Principal: Staff → Salary; Accountant: Staff Salary; teacher: Me → My Salary = own PAID slips only). Records `salStaff` (r = `t:<tid>` for teachers, `x…` for non-teaching staff added by the Principal: driver, peon…) and `salMonth` (r = `YYYY-MM|id`); `sal` prefix is kept out of legacy load/sync — salaries must never reach teachers' phones. Working days = days − Sundays − calendar Holidays; absences = Staff Attendance (half day 0.5) + approved leave on working days (non-teaching: days typed by the office); cut = salary ÷ working days × (absences − paid leave/month, default 1); net = salary − cut − advance + bonus. Only the Principal sets salaries and can reopen a paid month (reason logged); Principal or Accountant adjust and mark paid; paid numbers are frozen in `salMonth.calc`. Slip and monthly register print. Test: `.github/jaanch/salary-flow.php`.
- Search, profiles, Admin dashboard (18 Oct 2026, owner): header right side = expanding search (Principal/Chairman), ⚙ Settings (Principal; Settings is no longer in a menu group — `NAV_HIDDEN`), and only the initials avatar (dropdown: name, Settings, Log out). Search covers teachers and students (name, adm no., class, father, mobile); Enter or "See all results" opens the profile window (`PROF`, `vProfileWindow`): list on the left, full profile on the right. Teacher: classes/subjects, weekly periods, timetable, attendance per month for 5 months (`teacherMonth`), arrangement duty, lesson plans, syllabus, salary history (Principal only), complaints/appreciation notes. Student: joined date/class and time in school (`joinDate`, `joinClass` on the student record, Principal edits), monthly attendance for 6 months (`studentMonth`), marksheet for every exam with a trend line, fee account + receipts (office_api data), behaviour, notes; report card print. `school/profile_api.php`: `att` (staff + student attendance and behaviour for up to 400 days, merged into S and SYNCED so nothing is re-uploaded), `teacher` (salary history), `notes`/`note`/`delnote` (kind `profNote`, Principal writes, Chairman reads; `prof` prefix kept out of legacy load — complaints must never reach teachers). Admin menu: Admin Dashboard (`vAdmin`: fee collected by month, fee due by class, salary bill vs paid from `salary_api.php?a=summary`, student and staff attendance by month, students per class, quick actions), Fee Desk, Fee Department, Dues, Calls, Receipts, Fee Structure, Marks Entry, Report Cards & Results, Notices, Calendar, Rules & Policies (`settings.rules`, Principal edits, teachers read under Me), Activity Log. Charts are small inline SVG helpers (`svgBars`, `svgGroupBars`, `svgLine`) — one axis, values written on marks, `<title>` tooltips, legend for 2 series. Dashboard: class tiles show %, present/roll and absent count; on Sunday/holiday it shows the last school day; 12-day attendance trend; fee this month/still due (fee data refreshes every 2 minutes, `ofFresh`). Test: `.github/jaanch/profile-flow.php`.
- Quiz Duel (16 Oct 2026, owner): `school/quiz_api.php` + `quiz_lib.php`, kinds `quizQ` (question), `quizC` (challenge), `quizD` (daily quiz, key `sid|date`), excluded from legacy load/sync by the `quiz` prefix in `sch_rows_to_records`. Students (student login, or a parent for their own child) write 4-choice questions only on topics in their class's teacher diaries from the last 14 days; a teacher who teaches that class+subject (or the Principal) approves, may correct the key, or rejects. Approved questions: send to up to 3 classmates (3 challenges/day, 1/day per classmate, 45 s from opening, server-timed, key never sent before answering) and feed a 5-question daily quiz (30 s each). Points are never stored — `qz_points()` recomputes them: daily quiz +1/right (+2 for 5/5), right answer to a challenge +2, asker +1 for a wrong answer only once another student has answered that question right (`qz_fair_map`), same pair capped at 2 point-events a week per direction. Teacher screen: approvals, add questions, hardest questions (% right), printable worksheet with answer key, monthly leaderboards. Test: `.github/jaanch/quiz-flow.php`.
- Parent / Student panel (16 Oct 2026): parents log in with the mobile number saved on the student record + a 4-digit code the Principal makes in Students › Parent logins (`?a=parentcode`, shown once, sent on WhatsApp). Parent uid is `p:<10-digit mobile>`. Parents never get `load`/`since`/`day`; they get `?a=pdata`, which the server builds with ONLY their own children (siblings with the same mobile appear together): attendance, homework from diaries (last 10 days), their marks, behaviour notes, speaking count, notices not meant only for teachers, calendar.
- Look (16 Oct 2026, owner said the old one was boring): ink blue `#17324D`, marigold `#F2A93B`, page `#EEF2F5`, font Lexend; a carpet-pattern border (Bhadohi) is the one decorative element. Phone: bottom tab bar + "More" sheet; laptop: left sidebar. To change styles, edit `school/build/app.input.css` / `tailwind.config.js` and rebuild `school/app.css` as `school/build/README.txt` says.
- Test: `.github/jaanch/school-flow.php`.

- School Office registers (22 Oct 2026, owner: "build the old ERP blueprint the same in the new software"): `school/registers.php` + `registers_api.php` + `registers_lib.php` + `registers.js/.css`, view `office`. Every old-ERP menu the app did not already have (enquiry, TC, certificates, ID cards, transport, store, accounts/cash book, gate pass, visitors, reminders, events/PTM, feedback, documents, alumni, exam rooms, admit cards, question papers, masters, website content, message templates) is ONE entry in `reg_modules()` — add menus there, not new screens. Kinds `regRow` (r = `module|id`) / `regSeq`, `reg` prefix kept out of legacy load/sync. Old ERP Archive (Old ERP Receipts with ORIGINAL receipt numbers, Fee Statement, Marks) is read-only and filled only by the Principal's import (preview → apply, never overwrites); only the net due moves into the live Fee Desk via `carry` (`acctStu.opening`, only where empty). Message templates are never auto-sent. See `docs/school-office-registers.md`. Test: `.github/jaanch/registers-flow.php`.

### Smart Studio (16 October 2026)

`school/studio.php` is linked for Principal/Teacher/Parent in the existing sidebar/More sheet. It uses `studio_api.php`/`studio_lib.php`, the existing token, and separate `studio*` JSON kinds. Never add these kinds to legacy sync: answer keys, notebook media and private chat must remain class/subject/child scoped. Originals and prepared/checked photos live outside the website root, served only after authentication. AI Vision is optional, explicit transfer confirmation is required in the teacher workflow, and teacher approval is mandatory before scores reach families. Approved notebook scores do not overwrite formal exam marks. WhatsApp requires separate SCHOOL_WA_* configuration, parent opt-in and teacher sending selection. See `COMPLETE_SCHOOL_ERP_PROJECT_SPEC_FOR_AI.md` PART 7 for actual behavior, privacy boundaries, private-media backups and provider setup. Tests: `.github/jaanch/studio-unit.php`, `studio-flow.php` (CI database only).

### Teacher Smart Toolkit (16 October 2026)

`school/toolkit.php` / `toolkit_api.php` / `toolkit_lib.php` provide teacher-reviewed papers, factual bilingual report remarks, accurate board aids, diary preparation and private appreciation. `toolkit_english.js` supplies private classroom phrase practice. Keep all `tool*` records out of legacy sync. `homework_lib.php` enforces the shared class/day 45-minute budget under the Principal row lock in Diary, Learning and Studio writes; unknown legacy times must be estimated, never assumed zero. Tests: `.github/jaanch/toolkit-unit.php`, `toolkit-flow.php`, `toolkit-english.cjs`. See master specification PART 8.

### Teacher Profile Studio and school design (16 October 2026)

Owner explicitly requested bento surfaces, gradients and glass mobile dock for the school; this overrides older no-glass/no-gradient school guidance only. `school/design-2026.css` is a school-only progressive layer; preserve solid fallbacks and reduced-motion support. `faculty.php` / `faculty_api.php` / `faculty_lib.php` implement authenticated self-onboarding for existing teacher accounts, versioned Principal approval, consented public/linked-parent professional bios, credential verification, reconciled CL ledger, private appreciation/reflection and expiring/revocable QR IDs. Hide every `faculty*` kind from legacy sync. Private portraits/signatures are outside web root, served through scoped endpoints. Do not fabricate affiliation, signatory signatures, biometric feeds or attendance streaks. Profile approval checks the existing grid but never rewrites timetable allocations. Next.js component, same-origin adapter and PostgreSQL Prisma schema in `school/next-profile/` are explicitly requested portable source deliverables; live ERP remains PHP/MySQL. Tests: faculty-unit.php, faculty-flow.php, faculty-ui.cjs plus React type and Prisma validation in CI.

### Detailed Fee Department (17 October 2026)

`school/finance.php` + `finance_api.php` / `finance_lib.php` / `finance.js` implement session-scoped immutable bills/events, partial collection, pending/cleared/bounced cheques, concessions, advance/refunds/transfers, private proof, reports and closing. All `fin*` kinds stay OUT of legacy sync / `SCH_KINDS`. Financial writes and legacy migration share the Principal row lock. Legacy `office_api.php` pay/settings/cancel refuse activated detailed accounts; original `acct*` receipts remain. Explicit reviewed migration carries only reconciled remaining balance plus covered months, not fabricated historic receipts. See `docs/school-fee-department.md`; tests finance-unit.php / finance-flow.php / finance-ui.cjs. Preserve private `school-private-finance` outside web root and include it in private backup. A code commit is not live deployment.

## Maakit Jyotish = maakit.in/jyotish (18 Oct 2026, owner decision)

- A separate product "powered by Maakit", like `/school`: free Janam Kundli (North Indian chart, planets, Manglik, Vimshottari dasha), 36-gun Kundli Milan, personalised daily Rashifal (Chandra gochar, Tarabala, Sade Sati) and an AI Jyotishi chat in Hinglish / Hindi / English. It never reads or writes any Maakit table except its own `jy_ai_use`.
- All astrology maths runs on the phone: `jyotish/engine.js` on top of `jyotish/astronomy.min.js` (astronomy-engine 2.1.19, MIT, vendored — no CDN). Sidereal Lahiri ayanamsa (formula, about arc-minute accuracy), whole-sign houses. Profile stays only in the phone's localStorage.
- AI: `jyotish/ai.php` + `ai_lib.php`. Key from config.php `JYOTISH_AI_API_KEY` + `JYOTISH_AI_MODEL`, falling back to `SCHOOL_AI_API_KEY` + `SCHOOL_AI_MODEL` (OpenAI Responses). No key → page shows "AI Jyotishi is starting soon" and everything else works. The reading rules live on the server (`jy_rules`), never sent from the phone.
- Owner's honesty rule for this product: no "guaranteed / 100% real prediction" claims anywhere, timing only as windows, no fear, no selling gemstones or costly remedies. Keep it that way.
- Limit: `JY_DAILY` (20) AI questions per IP per day, counted by a hashed IP+day in `jy_ai_use` (sql/040). No payments yet; if paid reports are added, follow section 2 (money rule) and ask the owner first.
- Interface is English with Hindi labels (owner chose English replies). Tests: `.github/jaanch/jyotish-flow.php`, `jyotish-engine.cjs`.
- V2 (18 Oct 2026, owner: no login yet; "jitne log aaye unka data store ho taaki pata chale log kya search kar rahe hain"):
  - "Today" tab = the tri-layer daily card (`jyotish/daily.js`, plan doc "Maakit Jyotish — Product Design Plan"): 3-tap check-in → state (stays on the phone, never sent) → Chandra-gochar theme → ONE free upaya (27 practices × Hinglish/Hindi/English, ≤10 min, ₹0) + a graha mantra (Shani during Sade Sati/Dhaiya, else alternating weekday lord and antardasha lord). Feeling outranks the chart. No repeat within 3 days. Diya streak: 4 of 7 days = full week, no guilt messages. WhatsApp share has no personal data.
  - Help screen: crisis words (`JY_CRISIS` in ai_lib.php = `CRISIS` in daily.js — keep identical, CI compares them) stop all astrology and show Tele-MANAS 14416 and 112. Server never stores the words, only a count (`crisis`, `help`).
  - Usage log `jy_events` (sql/041) via `jyotish/track.php` (whitelisted kinds) and `ai.php` (every question + auto topic, also when the AI is off). Never stored: name, exact birth date/time, check-in mood. Stored: city, rashi, lagna, nakshatra, birth year, gender, milan score, upaya kind, question text. A notice + "Don't send" opt-out (and `nolog`) and "Delete my data" (`forget` = this phone's rows deleted). Question text 90 days, rows 180 days (`jy_cleanup`). This is notice + opt-out; a lawyer should confirm it for the DPDP Act before heavy promotion.
  - Ankit reads it at `/admin/jyotish.php` (admin only, menu Overview → Jyotish usage): totals, topics, latest questions with topic filter, day by day, places, ages, rashi, upayas done, language.
  - Navagrah Council mode (18 Oct 2026): optional AI style for relationship/situation clarity. The user must tick a consent box before each send. The situation text, optional situation-area/help-goal selections, and only Moon sign/Nakshatra/current dasha summary go to the AI provider; no name, exact birth details, daily check-in or device ID are sent. Council text is never written to `jy_events`, even if general usage sharing is on. Four planetary voices are storytelling lenses, not evidence; no verdict about guilt, motive, diagnosis, remorse, incompatibility or fate may be inferred from a chart. Three reality-check questions must use only events the user reported and must not be leading; if detail is missing, say so. Follow-ups: Rahu (facts), Chandrama (grounding), Shani (boundary). No retaliation, fear, costly remedies, or guaranteed outcomes. CI checks the server rules, no-log behavior, and UI wiring.
