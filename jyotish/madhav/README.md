# MADHAV — safe development slice

**Status:** This directory is a standalone MADHAV prototype inside the existing Maakit repository, on the `madhav-redesign-v1` development branch. It does not replace Maakit Jyotish, does not deploy to cPanel, and is **not** a released Android/iOS application.

## What is implemented
- Mobile-friendly multi-screen UI in `index.html`, with animated tap interactions, verse samples, stories placeholders, and locally saved japa counter.
- `api.php` + `madhav_core.php`: server-selected Bhagavad Gita verse references, Hindi/English knowledge topics, context-aware suggestion chips, limited-question fallback, and simple crisis routing.
- `ai_provider.php`: **optional** OpenAI Responses API call, only when `MADHAV_AI_API_KEY` and `MADHAV_AI_MODEL` are both configured on the server. Defaults to *no paid API call*. Uses `store: false`. No API key belongs in browser code or GitHub.
- No personal chat data is saved by the MADHAV backend. Browser japa count is device-local.

## Dev and checks
PHP 8.2+ with JSON, cURL for optional AI, and ideally mbstring. Run:
```bash
php -l jyotish/madhav/madhav_core.php
php -l jyotish/madhav/ai_provider.php
php -l jyotish/madhav/api.php
php jyotish/madhav/tests.php
```
From the directory, test a PHP server:
```bash
php -S 127.0.0.1:8080
```
Then open `http://127.0.0.1:8080/index.html`. Without AI key: curated responses. With AI key: optional inference. Make sure PHP can write to its system temporary directory for per-IP limits.

## IMPORTANT LIMITS
The verse collection contains **four examples**, with original short paraphrases awaiting expert review. It is not a verified complete corpus or licensed scholarly edition. Keyword selection and crisis detection have significant false-positive/false-negative risk; production needs safer moderation, a dedicated verified retrieval corpus and evaluation. Current rate-limiting is **per-server-instance and per-IP**, not production-scale. Hosting abuse protection, authenticated sessions, secure telemetry, consent management, content licensing, full Hindi/English localization, accessibility testing, real device testing, signing, and store release are incomplete. AI answers may still make mistakes; citations are server-selected but interpretations are not yet scholar-approved. This is not a divine persona.

**Privacy:** Avoid sending sensitive questions in this experimental preview. If AI is enabled, users' questions are sent to the configured model provider. Do not mix Jyotish user/birth data with Madhav.

**Deployment:** Do not merge this branch into the live cPanel deployment path until reviewed, secured and tested against a staging environment.
