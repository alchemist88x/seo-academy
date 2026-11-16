<?php

return [
    'ai_enabled' => (bool) env('LMS_AI_ENABLED', false),
    'ai_driver' => env('LMS_AI_DRIVER', 'null'),
    'max_messages_per_minute' => (int) env('LMS_MAX_MESSAGES_PER_MINUTE', 10),
    'max_input_characters' => (int) env('LMS_MAX_INPUT_CHARACTERS', 4000),
    'support_email' => env('LMS_SUPPORT_EMAIL', 'support@seoacademy.local'),
    'ai_model' => env('LMS_AI_MODEL', 'gpt-4.1-mini'),
    'ai_endpoint' => env('LMS_AI_ENDPOINT', ''),
    'ai_api_key' => env('OPENAI_API_KEY', ''),
];
