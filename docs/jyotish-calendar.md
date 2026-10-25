# Pandit Ji calendar (25 October 2026)

Added to the existing Jyotish page as an expandable, bilingual calendar (Hinglish/Hindi/English). Existing chat and counter stay in place. Tomorrow's notice is updated in India time on visibility changes and while the page remains open. Voice playback is user-triggered and depends on installed browser voices.

## Data and coverage

`jyotish/pandit.js` contains 24 **Smarta Ekadashi** dates and 30 selected observances for **Varanasi in 2026**. Sources: Drik Panchang's Varanasi Ekadashi and Hindu calendar pages, linked per record, checked 25 October 2026. Only date/name facts are used, not their artwork or explanatory articles. These are not Maakit's shopping campaign ranges from inc/festival.php. There is no automatic copying from that file or inference from the instantaneous tithi.

This is an initial curated calendar, NOT every fast or a national calendar. Pradosh, all Purnima/Amavasya dates, other cities, Vaishnava differences, 2027, and local parana/muhurat times require verified data. Uncovered dates say there is no entry, not that no observance exists. Years are never repeated automatically. The source may change: review differences before publishing revised dates. Do not silently claim a different city is supported.

## Reminders

Single-event and upcoming-current-month downloads generate RFC 5545 ICS files locally. Each event has a stable UID, exclusive next-day DTEND, provenance, and an absolute DISPLAY alarm for **18:00 Asia/Kolkata on the previous day** (12:30 UTC). Users must import and check alarms in their calendar app; importers vary and may ignore alarms or create duplicates. This is not subscription sync. Changes to source dates do not update previously imported files.

No push subscriptions are collected, no permission prompt is shown, and no WhatsApp or SMS is sent. Reliable app-closed notifications still require Web Push/VAPID, subscription storage and deletion, an opt-in flow, timezone/tradition preferences, and a scheduled sender with deduplication. Never substitute a browser timer for reliable background scheduling.

## Verification

Run `node .github/jaanch/jyotish-calendar.cjs` and the existing Jyotish engine test. Calendar check covers IST rollover, year/month/leap-day boundaries, multiple observances, unsupported years, provenance, UTF-8 ICS folding and injection protection, and previous-evening alarms. PHP/database suite requires the normal CI environment. This change is local until explicitly deployed and verified on the live host.
