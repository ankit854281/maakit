# MADHAV V6 — isolated development branch

**Status:** responsive bilingual web preview + standalone PHP API. **Not production-ready, not deployed, not an Android/iOS build.** Code lives in `jyotish/madhav/` and does not replace Maakit Jyotish.

## V6.1 mobile-app feedback update
- **Full-screen app shape:** removes the 520px phone mockup constraint in browser; 100dvh native-style layout, sticky toolbar, adaptive cards and full-width bottom navigation.
- **Opening animation:** Krishna-inspired flute, lotus, soft particles and mandala animation plays for approximately 4.4 seconds (not minutes). It is skippable, honors reduced motion, and leads automatically to **बोलो पार्थ** chat without forcing the mobile keyboard.
- **Installable web-app metadata:** standalone display manifest and SVG icon. This alone does **not** make a native Android/iOS binary; offline service worker and store packaging are not yet present.
- **Regression coverage:** `node jyotish/madhav/startup.test.cjs` checks the intro, skip control, viewport, chat transition and manifest basics. Browser/mobile real-device motion tests remain outstanding.

## What's new in V6
- Semantic mobile-first UI in `index.html`; premium plum/gold styles and reduced-motion support in `app.css`.
- Working Hindi/English interface toggle, saved locally; `app.js` handles the UI (no inline scripts).
- API-linked **बोलो पार्थ** chat, no fake AI replies when API unavailable, a bounded timeout, dynamic server-suggested follow-up chips and a visible answer mode.
- Verse references only come from the PHP API. Client marks them as **awaiting scholar review**.
- Local-only japa count, 108-cycle progress, reset confirmation; source-checked stories still placeholders.
- CSP restricts script/style/network to same-origin; no API keys are embedded in client code.
- Separate V6 CI quality gate validates PHP syntax, PHP unit suite, JavaScript syntax, sample JSON and basic token-pattern scan.

## What already exists on main (not new to V6)
- `api.php` and `madhav_core.php`: curated 4-verse matching and server-selected citations.
- `ai_provider.php`: **optional** Responses API integration; server env vars `MADHAV_AI_API_KEY`, `MADHAV_AI_MODEL`, with `store: false`. When not configured, curated fallback replies are returned. **Real inference has not been verified**. Do not put API keys in GitHub.
- Basic per-IP file rate limit **only for paid model calls**, not a complete production abuse defense.

## Local test
PHP 8.2+ with JSON and mbstring recommended; curl needed for optional provider.
```bash
php -l jyotish/madhav/api.php
php -l jyotish/madhav/madhav_core.php
php -l jyotish/madhav/ai_provider.php
php jyotish/madhav/tests.php
node --check jyotish/madhav/app.js
node jyotish/madhav/startup.test.cjs
cd jyotish/madhav && php -S 127.0.0.1:8080
```
Open `http://127.0.0.1:8080/index.html`.

## API request
POST `/jyotish/madhav/api.php` with same-origin JSON:
```json
{"question":"मन कैसे शांत करूँ?","language":"hi","history":[]}
```
Response contains `answer`, `mode` (`curated`, `ai`, `limited`, `safety`), optional `citation`, and three `suggestions` for ordinary matched topics. Safety responses return none.

## Risks / before launch
- **Scriptural scholarship:** four Sanskrit excerpts and original short paraphrases are samples, **not** a complete verified 700-verse licensed corpus. Review spellings, chapter/verse alignment and translations with a qualified editor.
- **AI safety:** prompt instructions and keyword crisis checks are not sufficient. Build thorough evaluation, moderation / response validation and emergency escalation UX.
- **Privacy:** API does not persist message text itself; if AI is enabled user inputs go to model provider. Create privacy notice, consent and retention controls before real users.
- **Security:** no auth, no robust distributed throttling or server-side privacy telemetry; current file-based hourly limits are insufficient. Test cross-origin/CSRF protections, load, abuse and accessibility.
- **Mobile:** this is a web preview, not a signed Android/iOS application. Validate on real devices, add packaging only after a tested staging build.
- **Delivery:** no merge or deployment without explicit founder approval. The connected Canva design is a concept, not an implemented native app.

## Design
Canva editable concept: https://canva.link/sdbcqogsgzk0ju7

