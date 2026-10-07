# Maakit

Gaav ki apni delivery aur booking service — Kapsethi, Chauri, Kachhwa
aur aas-paas ke gaav.

Website: https://maakit.in

## Isme kya hai

- Kisi bhi dukaan se saaman ki delivery (pick-up & drop)
- Gaadi, lawn, tent, halwai, pandit ji, mistri ki booking
- Transport registration — sawari aur maal gaadi
- Purani kitaabein — bechiye, muft dijiye, badliye
- Admin / BPO / Delivery panel
- Hindi aur English, dono

## Taknik

PHP 8 + MySQL. Koi framework nahi. cPanel shared hosting par chalti hai.

## Website par kaise chadhti hai

`maakit-update.php` site par ek baar rakhi jaati hai. Use kholne par
wo yahi se naya code uthakar apne aap laga leti hai —
`config.php` aur `uploads/` ko chhue bina.

## Jo is repo me kabhi nahi aata

- `config.php` — database ka password
- `uploads/` — grahak aur dukaan ki photo

## Customer shop catalogue

`bazaar.php` exposes the existing `catalog_types` / `catalog_items` master list:
main group → shop type → product subcategory → local shop offers. The supplied
Excel contains 1,510 entries across 196 product-catalogue shop types (its shop
directory lists 188). `inc/catalog-meta.json` assigns every type to a main group
and supplies Hindi shop names. The existing `sql/008-catalog.sql` remains the
source of product IDs; this feature introduces no database migration.

Prices come only from active `shop_items` linked by `cat_id` to approved shops
with `items_on=1`. Each offer retains its actual unit, stock and open/closed
status. There are no invented master prices. A missing price offers an enquiry;
a sold-out or closed shop does not get an order button. Shopkeepers choose their
shop type and fill prices / pack sizes in `shop.php?tab=saaman`, and can upload
product photos there. Catalogue entries are examples, not a promise that every
shop carries every item. Delivery and service booking retain their existing flows.

Check catalogue filtering and price visibility with `php tests/catalog.php`
(PHP 8 with mbstring and PDO SQLite). Before deployment, check catalogue browsing,
Hindi/English search, pagination, and actual shop offers on PHP/MySQL.

Category pages now list approved shops by their chosen shop type or active
catalogue-linked inventory. They show village, opening status and the actual
count of available priced items. No name guessing or distance claim is used.
Shop pages show all published products in pages of 30, including sold-out stock;
disabled catalogues remain hidden and cannot be ordered through a direct URL.
The CI-only category-flow check submits a seeded shop order to verify server
prices, stock exclusions, shop routing and the disabled-catalogue guard.

### Launch revenue and checkout checks

`admin/summary.php` records actual daily fuel, staff and other operating costs. It compares these with completed delivery fees and the explicit shop commission already recorded in `shop_ledger`. Goods value, pending/cancelled orders and booking revenue are excluded. Missing costs or an unknown completed delivery fee leave the balance incomplete rather than reporting false profit. Monthly costs must be allocated once per day by the admin; this report is not payment collection, tax or full accounting. Existing customer/village ranking remains available.

Shop checkout now shows the normal delivery estimate and supports market, weight and fragile/large-item charges. Known packed weight cannot be understated in a forged request, and UPI is rejected if the shop has no payment details. Delivery staff distinguish both direct-shop UPI labels from cash and see any weight surcharges on the first order. Booking navigation now opens `sewa.php`. Private tracking, orders and uploaded bills are excluded from offline caches; the cache version is bumped to remove previously stored private pages.

Validation includes SQLite revenue/cost edge cases, MariaDB migrations twice, real shop-order HTTP checks, and admin login/cost entry/correction/CSRF checks in the guarded CI database. No live test orders or expenses are created.

Shop category imports preserve existing prices. Pending price fields can be prefilled from an approved shop’s public price updated within seven days, only for the same catalogue ID, name and pack. The source shop/date are displayed and the owner must confirm or edit and save before publication. No invented default prices or silent updates are used.
