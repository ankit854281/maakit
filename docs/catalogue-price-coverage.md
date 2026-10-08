# Catalogue price coverage — 8 October 2026

This is a structural review of all 1,794 catalogue entries (1,446 goods and 348 services), not a claim that all products have been searched on the web or all shops have verified prices.

The CSV tracks every catalogue ID once. It contains 30 specific product variants with 87 unique sourced packs, displayed across 51 catalogue entries. Twenty-one generic entries show explicitly labelled brand examples; shared examples are not counted as new pack prices. The 1,395 remaining goods entries are pending exact variants and verified prices. Services require scope-specific provider quotes. Live shop-owned prices remain separate and authoritative; the CSV does not audit the production shop database.

Each retail reference gives its actual named brand/variant, pack, retailer link and observation date. Generic families may contain a clearly labelled specific example; they do not acquire that example's selling price. Prices are online observations and may vary by location, offer and date. Existing expiry rules hide references after 30 days. No shop stock or checkout prices are seeded.

The Pampers medium listing was investigated but rejected because the displayed 20-piece pack and per-piece price do not agree. Conflicting 1 kg Amul cheese alternatives and unclear Surf Excel liquid mass/volume variants were also excluded. Unverified listings and medicine/service rates remain unpriced.

Pending work is explicit in the CSV's required_details column. For example, mobile accessories require an exact model/connector; vehicle parts need make/model/year and part number; garments need size/material; paint needs product range/finish/shade and quantity. Finding one arbitrary listing cannot establish a price for all of those variants.

This snapshot belongs to the Git repository and should be regenerated when catalogue IDs or sourced references change. CI verifies complete coverage, goods/service counts, source-to-pack consistency and exclusion of unverified prices.
