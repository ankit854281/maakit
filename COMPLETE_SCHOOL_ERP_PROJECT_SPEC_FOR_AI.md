# SN Mishra SIS school ERP — implementation specification

Canonical application: `https://maakit.in/school/`. This specification describes implemented code, not a simulated notebook demo. The running platform is PHP 8.2+ and MySQL/MariaDB. Keep the school isolated from Maakit marketplace tables. Preserve existing student IDs, teachers, sessions and records. Never put student exports or credentials in Git.

## PART 7 — Teacher Smart Studio and private classroom network

Entry: School sidebar / phone More → Smart Studio. Roles: Principal, assigned teacher, linked parent. `school/studio.php`, `studio.js`, `studio.css`, `studio_api.php`, `studio_lib.php`. The existing Learning Lab remains the lesson-plan and answer-key drafting tool.

### Data model

The four requested logical resources use the established `sch_records` schema rather than introducing an incompatible PostgreSQL database:

| Logical resource | Record kinds | Purpose |
| --- | --- | --- |
| class_homework_assignments | studioTask, studioKey | Class, subject, homework/classwork, due date, maximum marks; separately protected answer key/rubric |
| student_homework_submissions | studioCopy, studioMedia | Original/prepared/checked images, OCR draft, proposed annotations, teacher-approved score and feedback |
| classroom_social_posts | studioPost, studioReact | Class-scoped caption/photo/video; one changeable applause/love reaction per parent account |
| student_behavior_badges | studioBadge, studioHouse | Four fixed badges, server-owned points, house at award time |

Supporting kinds: studioMsg for private chat, studioPref for parent WhatsApp permission. All `studio*` kinds are excluded from the legacy load/since sync response and cannot be written through legacy sync. New APIs share authentication but perform their own permission checks. No schema migration is needed; no old records are rewritten.

### Notebook workflow

1. Teacher selects an assigned class/subject, publishes instructions, due date, maximum marks, verified answer key and step-wise rubric.
2. Parent uploads only for their linked child and assigned class. Teacher may upload for a child they teach. Up to five images per attempt.
3. Browser preserves originals, rotates/straightens and optionally trims blank margins on a separate prepared image. Auto-trim is whitespace detection, not AI page detection. User checks every crop. Images up to 8 MiB / 25 megapixels, downscaled to 1800 px for prepared review.
4. AI is optional. The teacher explicitly confirms transfer of prepared images and key to OpenAI. Names should be covered before submission. Server calls Responses with image inputs, `store:false`, strict JSON output and configured model. No API secrets enter the browser. At most ten draft requests per teacher per hour. Unreadable handwriting/insufficient keys must be marked uncertain.
5. AI output is a draft: Hindi/English/maths transcript, proposed marks, constructive feedback, page-indexed normalised annotations. Maths uses the teacher's marking rubric for partial credit. Neatness is descriptive, not an intelligence or ability assessment.
6. Teacher compares against originals, edits marks and feedback, adds/removes green ticks, red circles and notes. Every annotation and score is validated on the server.
7. Explicit approval publishes a locked notebook review to the family and saves checked JPEGs when PHP GD is available. Client also renders full textual annotations and downloads the checked page. Server JPEGs contain numeric marks and visual marks; complete multilingual written feedback remains in the parent panel.
8. Notebook marks are in the checking register, separate from formal exam marks. New attempts create new records; approved reviews are not overwritten.
9. WhatsApp is optional and off until connected. Parent opts in for each child. Teacher selects sending on approval. Only then may a configured school WhatsApp utility template send an approved summary and optional first checked-page image. Other pages remain in the parent panel. Provider acceptance is not delivery. Ambiguous timeouts are not automatically retried.

No fixed five-second checking, three-second delivery, perfect OCR or automatic NCERT correctness claims are implemented. The app never produces a canned 23/25 result.

### Private classroom network

- Teacher posts captions and up to five images/videos only to a class they teach/manage. School permission for identifiable children must be confirmed before posting. JPEG/PNG/WebP ≤8 MiB; MP4/WebM ≤25 MiB.
- Only parents of children in that class can read/react. Reactions disclose aggregate counts, not other parent identities. Authors/Principal can remove posts; tombstones preserve normal recovery/audit semantics.
- Fixed badges: Star of the Day +5; Neat Handwriting +2; Helping a Peer +3; Math Wizard +4. One of each badge per child per day. Award requires an observed action. Repeat request IDs do not duplicate points.
- Principal assigns Tagore/Raman/Ashoka/Shivaji houses. Points use the house at award time, so later house changes do not rewrite history. Parent sees only their own child’s badges and aggregate house totals.
- Badge bell is browser audio enabled by user interaction, while the page is open. This is not background push.
- Teacher–parent chat is child- and teacher-scoped. Parent cannot read another family’s conversation; teachers cannot read another teacher's messages. Principal can supervise. Teacher personal numbers are not exposed in this interface.
- Quiet hours: 16:30 through 07:59:59 Asia/Kolkata. Parent messages are stored immediately but withheld from teacher reads until the next 08:00. Parent sees queued state; no misleading read receipt. The current implementation does not send external chat notifications.

### Private media and deployment

Media files live outside the website document root, default `dirname(project-root)/school-private-media`. Set `SCHOOL_MEDIA_DIR` to a separate private directory if needed. The API rejects a path inside the project/web root. Server must have write permission there. Files use random IDs, MIME inspection, size/pixel limits and private authenticated streaming; no public upload URLs or tokens in query strings. Include this private directory alongside database backups. Removing a post does not purge media bytes; retention/purge requires an explicit school policy.

New provider secrets are deployment-only environment variables or constants in private config; never commit values:

- `SCHOOL_AI_API_KEY`
- `SCHOOL_AI_VISION_MODEL` (falls back to `SCHOOL_AI_MODEL`, must support vision + structured outputs)
- `SCHOOL_MEDIA_DIR` (absolute path outside web root)
- `SCHOOL_WA_TOKEN`, `SCHOOL_WA_PHONE_ID`, `SCHOOL_WA_API_VERSION`
- `SCHOOL_WA_REVIEW_TEMPLATE` (Meta-approved utility template)
- `SCHOOL_WA_LANGUAGE` (default en)
- `SCHOOL_WA_IMAGE_HEADER=1` only for an approved IMAGE-header template; otherwise text-only.

WhatsApp body parameters in order: student name, assignment title, approved score such as 23/25, https://maakit.in/school/. Optional image header uploads the first checked JPEG directly to Meta, without making it public. Confirm template approval and school messaging consent before enabling. No credentials have been provisioned by this change.

### Verification

`node --check school/studio.js`; `php .github/jaanch/studio-unit.php`; the standard `bash .github/jaanch/sab.sh` includes `studio-flow.php` on the isolated `maakit_jaanch` database and HTTP server. GitHub CI also runs the new checks. Test unauthenticated access, another parent/teacher, answer-key leakage through legacy sync, image ACLs, reviewed marks, immutable approval, reaction replacement, badge idempotency, quiet-hour boundaries and parent-owned messaging consent. Never use real child data in test fixtures.

API references: https://developers.openai.com/api/docs/guides/images-vision ; https://developers.openai.com/api/docs/guides/structured-outputs ; Meta's official collection https://www.postman.com/meta/whatsapp-business-platform/overview .
