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
