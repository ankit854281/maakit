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

A pick-up and drop delivery service for villages around **Kapsethi, Chauri
and Kachhwa** in Bhadohi, UP. Site: **maakit.in**. Phone **+91 84293 93903**.
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
| `shop.php`, `inc/dukan*.php` | Shop panel — items, orders, ledger, UPI |
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
3. **Work on a branch and open a pull request** when the change is more than a
   few lines. Name the branch for the work (`codex/…`, `claude/…`).
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

---

## 7. The helper bots already running

| Name | What it does |
|---|---|
| **चौकीदार** | Every 15 min checks the live site's pulse; twice a day a full check (tiles, photos, forms, order list, shop login). Opens a GitHub issue if something breaks. |
| **मुनीम** | A weekly ledger — what sold, what didn't, which items have no photo or price, which shops have gone quiet, **what people searched for and did not find**. |
| **नक़ल** | Gzipped database backup. Weekly automatically, and before every migration run. |
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
maroon       #7A1F1F   brand — header, primary buttons
deep maroon  #5A1414
gold         #E0A526   highlight, secondary button, logo accent
cream        #FBF4E6   page background
soft         #F4E6CB   boxes and fills
line         #EAD7B5   borders
ink          #2B1A12   text
muted        #6B4A2F   small text
green        #1F7A4D   success
red          #A3360A   error
```

Font **Poppins + Noto Sans Devanagari**, 17px base, line-height 1.6.
Devanagari headlines need line-height ≥ 1.2 or the matras collide.
Rounded corners 11–16px, buttons at least 50px tall, soft shadows.
**No gradients, no glassmorphism.**

Use the CSS variables (`var(--brand)`, `var(--gold)`, `var(--soft)`,
`var(--line)`, `var(--muted)`), never raw hex in new code.

The feeling: **warm, Indian, trustworthy — a well-run local shop, not a
Silicon Valley startup.**

---

## 10. What Ankit wants this to become

The one app a village family opens for anything they need — the way people in
cities open Swiggy or Blinkit, but built for a place those companies will
never serve.

As fast and as polished as a big app, but clearly from here: Indian, warm, in
Hindi, run by someone from the same district who will pick up the phone.

**Not a marketplace that squeezes shops.** A layer that makes the local shops
that already exist reachable from a phone.
