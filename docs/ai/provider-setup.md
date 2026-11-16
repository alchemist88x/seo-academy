# AI Provider Setup Guide — SEO Academy

> **Status:** **Phase 1.1 Foundation Only.** The configuration keys, `AiProviderInterface` binding, `OpenAiCompatibleProvider`, and `AcademyAiCredential` model are implemented. End-user AI UI and automated workflows are **planned for Phase 5 and not yet implemented**.

---

## 1. Default Behavior (Disabled)

By default in `.env.example` and `config/lms.php`, AI is **disabled**:

```env
LMS_AI_ENABLED=false
LMS_AI_DRIVER=null
LMS_MAX_MESSAGES_PER_MINUTE=10
LMS_MAX_INPUT_CHARACTERS=4000
LMS_AI_MODEL=gpt-4.1-mini
LMS_AI_ENDPOINT=
OPENAI_API_KEY=
```

When `LMS_AI_ENABLED=false` (or `LMS_AI_DRIVER=null`), `AppServiceProvider` binds `NullAiProvider`, and `AiAssistantService::ask()` returns:

> `AI assistant is currently disabled. Please contact your academy administrator to enable it.`

No outbound HTTP requests are made.

---

## 2. Option A: Local Self-Hosted AI with Ollama (Free / No API Key)

Recommended for privacy-sensitive deployments or VPS setups with sufficient RAM/CPU/GPU.

1. Install and run [Ollama](https://ollama.com/) on your VPS or local network, and pull a model (e.g., `llama3.2` or `qwen2.5`):
   ```bash
   ollama pull llama3.2
   ```
2. Update `.env`:
   ```env
   LMS_AI_ENABLED=true
   LMS_AI_DRIVER=ollama
   LMS_AI_ENDPOINT=http://127.0.0.1:11434
   LMS_AI_MODEL=llama3.2
   OPENAI_API_KEY=
   ```
   `OpenAiCompatibleProvider` automatically targets `/api/chat` (or respects an explicit `/api/chat`, `/api/generate`, or `/v1/chat/completions` suffix) with `stream: false`.

---

## 3. Option B: OpenAI or Any OpenAI-Compatible API

> **Important:** Consumer AI subscriptions (such as ChatGPT Plus, Claude Pro, or Gemini Advanced) **do not** include API usage. You must use a developer API account with pay-as-you-go API billing or a compatible inference provider.

Update `.env`:

```env
LMS_AI_ENABLED=true
LMS_AI_DRIVER=openai_compatible
LMS_AI_ENDPOINT=https://api.openai.com
LMS_AI_MODEL=gpt-4.1-mini
OPENAI_API_KEY=sk-...
```

Any OpenAI-compatible gateway (OpenRouter, Groq, Together, vLLM, LiteLLM, LM Studio) works by setting `LMS_AI_ENDPOINT` to its base URL (or full `/v1/chat/completions` URL) and supplying the corresponding bearer token in `OPENAI_API_KEY`.

---

## 4. Per-Academy Credentials (`AcademyAiCredential`)

Phase 1.1 includes `App\Domain\AI\Models\AcademyAiCredential` (`academy_ai_credentials` table) to support per-academy provider credentials:

- Scoped to an academy via `BelongsToAcademy` (`academy_id`, UUID primary key via `HasUuids`).
- Fields: `driver`, `endpoint`, `model`, `api_key`, `is_active`.
- `api_key` is encrypted at rest using Laravel's `'encrypted'` cast and excluded from JSON/array serialization via `$hidden = ['api_key']`.
- **Note:** The admin UI for managing `AcademyAiCredential` records is **planned for Phase 5**.

---

## 5. Rate Limits & Input Constraints

| Setting | `.env` Key | Default | Enforcement |
|---|---|---|---|
| **Max Requests / Min** | `LMS_MAX_MESSAGES_PER_MINUTE` | `10` | Per-user `RateLimiter` key `ai-assistant:{userId}` (`60`s window); throws HTTP `429` (`TooManyRequestsHttpException`) when exceeded. |
| **Max Prompt Length** | `LMS_MAX_INPUT_CHARACTERS` | `4000` | Checked via `mb_strlen()` in `AiAssistantService::ask()`; throws `InvalidArgumentException` when exceeded. |
| **HTTP Timeout** | *(hardcoded default)* | `30`s | `OpenAiCompatibleProvider` constructor `$timeout = 30`. |
