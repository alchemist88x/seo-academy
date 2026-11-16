# AI Privacy, Security & Cost Controls — SEO Academy

> **Status:** **Phase 1.1 Foundation Only.** Guardrails and encrypted credential storage are implemented. Full AI features (chat UI, assignment feedback, keyword research) are **planned for Phase 5 and not yet implemented**.

---

## 1. Cost & Billing Clarification

- **Consumer Subscriptions Do Not Include API Access:** Paid web subscriptions (e.g., ChatGPT Plus, Claude Pro, Gemini Advanced) only cover their respective web/mobile apps. Calling external APIs via `OpenAiCompatibleProvider` requires a separate developer API key with API billing enabled.
- **Zero Cost by Default:** `LMS_AI_ENABLED=false` and `LMS_AI_DRIVER=null` by default. No external API calls are possible until an administrator explicitly enables AI and configures a provider.
- **Self-Hosted Zero-Token Option:** Using `LMS_AI_DRIVER=ollama` against a local Ollama server avoids per-token API charges entirely.

---

## 2. Implemented Abuse & Spend Controls

1. **Per-User Rate Limiting (`AiAssistantService::ask`):**
   - Uses Laravel's `Illuminate\Support\Facades\RateLimiter` keyed by `ai-assistant:{userId}`.
   - Default limit: **`10` messages per minute** (`LMS_MAX_MESSAGES_PER_MINUTE=10`).
   - Exceeding the limit raises `TooManyRequestsHttpException` (HTTP `429`) before any provider request is sent.
2. **Input Character Cap (`AiAssistantService::ask`):**
   - Default limit: **`4000` characters** (`LMS_MAX_INPUT_CHARACTERS=4000`).
   - Empty prompts and oversized prompts throw `InvalidArgumentException` before hitting the provider.
3. **Kill Switch:**
   - Setting `LMS_AI_ENABLED=false` immediately routes all calls through `NullAiProvider` without network I/O.

---

## 3. Credential & Data Privacy

- **Encryption at Rest:** `AcademyAiCredential` casts `api_key` to `'encrypted'` (using `APP_KEY` via Laravel's `Crypt`) and marks `'api_key'` in `$hidden` so keys are never leaked in model serialization.
- **Tenant Isolation:** `AcademyAiCredential` uses `BelongsToAcademy`, scoping credentials to a specific `academy_id`.
- **Data Minimization:** `OpenAiCompatibleProvider::buildMessages()` sends only the prompt, optional `context['system']`, and optional `context['history']`—never database dumps, passwords, or `.env` secrets.
- **Third-Party Data Handling:** When using hosted APIs (e.g., OpenAI), prompts are transmitted to that provider under your API agreement. For strict data residency or zero third-party transmission, use a local `ollama` instance.

---

## 4. Planned for Phase 5 (Not Yet Implemented)

- Per-academy token/cost tracking and monthly budget caps
- PII redaction / prompt-auditing middleware before external API dispatch
- Role-based AI feature toggles (student assistant vs. trainer grading assistant)
