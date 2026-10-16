# School Learning Lab

Teacher and Parent navigation now includes Learning Lab / Learning Adventure. It uses the existing school login and the same PHP/PDO school database, with subject/child filtering enforced on its own API. New records live only in existing `sch_records`: `learnMission`, `learnKey`, `learnTry`, `learnReview`, `skillCheck`. No schema migration or changes to formal marks are needed. These kinds are excluded from legacy load/sync outputs and cannot be written through generic sync.

Teacher flow: select an assigned class/subject → build a structured outline or request an optional AI draft → edit lesson and questions → review every answer → publish → examine practice submissions → observe a skill independently → record evidence, next practice and a three-day recheck. Source-based AI plans contain suggested questions and keys in the editable teacher draft; teachers select/edit the questions for the published mission. Math warm-ups create exact addition/multiplication keys and are clearly not topic-specific. Open written answers are never auto-marked.

Parents see only their own children, class missions, submitted-practice progress and teacher observations. Teacher notes containing plans/keys and other children's attempts are not sent to them. Numeric practice is checked server-side; written responses stay pending teacher review. Badges count distinct submitted missions, not repeated attempts, marks or mastery. Unobserved skills have no level rather than a zero. Observations suggest practice; they do not diagnose a condition or determine educational access. No parent messages are sent automatically.

## Optional AI connection

Until connected, the screen explicitly says AI is not connected. The structured outline, practice and observations work without a provider. To enable, the hosting owner supplies `SCHOOL_AI_API_KEY` and `SCHOOL_AI_MODEL` as private server environment values or constants in the existing private config. No key-setting UI or secret is committed. No code modifies config.php automatically. Use an OpenAI model supporting Responses API structured output. The key must be authorised for the chosen project/model. AI drafts use the official `https://api.openai.com/v1/responses`, `store:false`, a JSON schema and a 10-request/hour per-login-label limit; no student records are added to the request. Only a teacher's explicit button sends the selected class, subject, topic and lesson text. Review that lesson text contains no personal student information. The API is never called when unconfigured. Provider failure does not publish anything. No external-provider call was made during implementation tests.

Reference: https://developers.openai.com/api/docs/guides/structured-outputs?api-mode=responses

## Validation

`school-learning.php` unit checks plus Learning Lab integration coverage in `school-flow.php`: assigned-subject permissions, reviewed publication, retries, parent child ownership, server scoring, written-answer review, evidence-only teacher writes, and absence of answer keys/teacher plans from parent or legacy-sync payloads. Existing role/login/import tests remain unchanged. CI also lints all PHP and runs all migrations twice.

Deployment requires the existing Maakit admin updater. Code on GitHub is not proof of live activation. The earlier student import is still pending live activation; this feature does not invent student records.
