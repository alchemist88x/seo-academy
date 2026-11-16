# AI Integration Architecture — SEO Academy

> **Implementation Status:** **Phase 1.1 Foundation Implemented.** Core provider contracts, HTTP driver, rate-limited service, and encrypted per-academy credential storage are in place. Full user-facing AI features (chat UI, FAQ/material retrieval, assignment feedback, keyword research) are **planned for Phase 5 and are not yet implemented**.

---

## 1. Design Principles

1. **Optional & Disabled by Default:** `LMS_AI_ENABLED=false` and `LMS_AI_DRIVER=null` out of the box. The LMS installs, boots, and operates without any AI keys or external AI services.
2. **Provider-Independent (No Proprietary SDK Lock-In):** All provider communication uses Laravel's standard HTTP client (`Illuminate\Support\Facades\Http`) against OpenAI-compatible REST endpoints or local Ollama instances.
3. **Tenant-Scoped Encrypted Credentials:** `AcademyAiCredential` stores per-academy provider settings with Laravel's `encrypted` cast on `api_key` and hides `api_key` from serialization.
4. **Defensive Guardrails:** Input length limits and per-user rate limiting are enforced in `AiAssistantService` before any outbound network request.

---

## 2. Implemented Components (Phase 1.1)

| Component | Path | Responsibility |
|---|---|---|
| **Contract** | `app/Domain/AI/Contracts/AiProviderInterface.php` | Defines `generate(string $prompt, array $context = []): string`, `isAvailable(): bool`, and `driverName(): string`. |
| **Null Driver** | `app/Domain/AI/Services/Providers/NullAiProvider.php` | Default safe fallback when AI is disabled (`driverName() === 'null'`, `isAvailable() === false`). Returns a static disabled notice without making network calls. |
| **HTTP Driver** | `app/Domain/AI/Services/Providers/OpenAiCompatibleProvider.php` | Implements `AiProviderInterface` for `openai_compatible` and `ollama` drivers using `Http::timeout($this->timeout)->acceptJson()`. |
| **Application Service** | `app/Domain/AI/Services/AiAssistantService.php` | Validates prompts, enforces `4000`-char max length and `10`/min per-user `RateLimiter`, checks `lms.ai_enabled`, and delegates to `AiProviderInterface`. |
| **Credential Model** | `app/Domain/AI/Models/AcademyAiCredential.php` | Eloquent model (`HasUuids`, `BelongsToAcademy`) storing `driver`, `endpoint`, `model`, `api_key` (`encrypted` cast, `$hidden`), and `is_active`. |
| **Domain Models (Schema)** | `app/Domain/AI/Models/AiFaq.php`, `AiMaterial.php`, `AiMessage.php` | Phase 1 schema/models for future Phase 5 FAQ, material, and conversation history features. |
| **DI Binding** | `app/Providers/AppServiceProvider.php` | Binds `AiProviderInterface::class` to `NullAiProvider` when `lms.ai_enabled` is `false` or `lms.ai_driver` is `null`/empty, otherwise `OpenAiCompatibleProvider`. |
| **Config** | `config/lms.php` | Reads `LMS_AI_ENABLED`, `LMS_AI_DRIVER`, `LMS_MAX_MESSAGES_PER_MINUTE`, `LMS_MAX_INPUT_CHARACTERS`, `LMS_AI_MODEL`, `LMS_AI_ENDPOINT`, and `OPENAI_API_KEY`. |

---

## 3. Request Flow (`AiAssistantService::ask`)

1. **Trim & Non-Empty Check:** Throws `InvalidArgumentException` if the trimmed prompt is empty.
2. **Input Length Check:** Enforces `config('lms.max_input_characters', 4000)` (`LMS_MAX_INPUT_CHARACTERS`). Throws `InvalidArgumentException` if exceeded.
3. **Feature Flag Check:** If `config('lms.ai_enabled', false)` is `false`, immediately returns `(new NullAiProvider)->generate(...)`.
4. **Per-User Rate Limit:** When `$userId` (or `$context['user_id']`) is provided, checks `RateLimiter::tooManyAttempts('ai-assistant:' . $resolvedUserId, config('lms.max_messages_per_minute', 10))`. Exceeding `10` requests per `60` seconds throws `Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException`.
5. **Provider Dispatch:** Calls `$this->provider->generate($cleanPrompt, $context)`.

---

## 4. Supported Endpoint Formats (`OpenAiCompatibleProvider`)

- **OpenAI-Compatible (`driver = 'openai_compatible'`):**
  - Appends `/v1/chat/completions` unless the configured endpoint already ends with `/chat/completions` or `/responses`.
  - Parses `choices[0].message.content`, `output_text`, or `output[0].content[0].text`.
- **Ollama (`driver = 'ollama'`):**
  - Appends `/api/chat` unless the configured endpoint already ends with `/api/chat`, `/api/generate`, or `/v1/chat/completions`.
  - Sets `stream: false` and parses `message.content` or `response`.

---

## 5. Planned for Phase 5 (Not Yet Implemented)

- Student & trainer AI chat UI (Livewire components and routes)
- Per-academy AI credential management UI in Academy Settings
- Retrieval-augmented generation (RAG) over `ai_faqs` and `ai_materials`
- AI-assisted assignment feedback and SEO keyword research workflows
- Token usage accounting and per-academy quota dashboards
