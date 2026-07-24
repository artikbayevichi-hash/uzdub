<?php
require_once __DIR__ . '/env.php';

define('OLLAMA_URL', env('OLLAMA_URL', 'http://localhost:11434/api/chat'));
define('OLLAMA_MODEL', env('OLLAMA_MODEL', 'llama3.2:3b'));
define('OLLAMA_TIMEOUT', 45);
define('OLLAMA_MAX_CONCURRENT', (int)env('OLLAMA_MAX_CONCURRENT', '1'));
define('OLLAMA_QUEUE_WAIT_SECONDS', 15);
define('OLLAMA_NUM_CTX', 2048);
define('OLLAMA_NUM_PREDICT', 250);
define('OLLAMA_NUM_THREAD', 4);
define('AI_HISTORY_MESSAGES', 6);
define('AI_MAX_RECOMMENDATIONS', 5);

// Groq API
define('GROQ_API_KEY', env('GROQ_API_KEY', ''));
define('GROQ_MODEL', env('GROQ_MODEL', 'llama-3.3-70b-versatile'));
define('GROQ_URL', 'https://api.groq.com/openai/v1/chat/completions');
define('GROQ_TIMEOUT', 30);

// Cerebras API
define('CEREBRAS_API_KEY', env('CEREBRAS_API_KEY', ''));
define('CEREBRAS_MODEL', env('CEREBRAS_MODEL', 'llama-3.3-70b'));
define('CEREBRAS_URL', 'https://api.cerebras.ai/v1/chat/completions');
define('CEREBRAS_TIMEOUT', 30);

// SambaNova API
define('SAMBANOVA_API_KEY', env('SAMBANOVA_API_KEY', ''));
define('SAMBANOVA_MODEL', env('SAMBANOVA_MODEL', 'Meta-Llama-3.3-70B-Instruct'));
define('SAMBANOVA_URL', 'https://api.sambanova.ai/v1/chat/completions');
define('SAMBANOVA_TIMEOUT', 30);

// Together AI API
define('TOGETHER_API_KEY', env('TOGETHER_API_KEY', ''));
define('TOGETHER_MODEL', env('TOGETHER_MODEL', 'meta-llama/Llama-3.3-70B-Instruct-Turbo'));
define('TOGETHER_URL', 'https://api.together.xyz/v1/chat/completions');
define('TOGETHER_TIMEOUT', 30);

// OpenRouter AI API
define('OPENROUTER_API_KEY', env('OPENROUTER_API_KEY', ''));
define('OPENROUTER_MODEL', env('OPENROUTER_MODEL', 'meta-llama/llama-3.3-70b-instruct:free'));
define('OPENROUTER_URL', 'https://openrouter.ai/api/v1/chat/completions');
define('OPENROUTER_TIMEOUT', 30);

// AI Provider ketma-ketligi (birinchisi birinchi ishlatiladi, limit tursa — keyingisiga o'tadi)
define('AI_PROVIDERS', [
    [
        'name' => 'groq',
        'url'  => GROQ_URL,
        'key'  => GROQ_API_KEY,
        'model'=> GROQ_MODEL,
        'timeout' => GROQ_TIMEOUT,
    ],
    [
        'name' => 'cerebras',
        'url'  => CEREBRAS_URL,
        'key'  => CEREBRAS_API_KEY,
        'model'=> CEREBRAS_MODEL,
        'timeout' => CEREBRAS_TIMEOUT,
    ],
    [
        'name' => 'sambanova',
        'url'  => SAMBANOVA_URL,
        'key'  => SAMBANOVA_API_KEY,
        'model'=> SAMBANOVA_MODEL,
        'timeout' => SAMBANOVA_TIMEOUT,
    ],
    [
        'name' => 'together',
        'url'  => TOGETHER_URL,
        'key'  => TOGETHER_API_KEY,
        'model'=> TOGETHER_MODEL,
        'timeout' => TOGETHER_TIMEOUT,
    ],
    [
        'name' => 'openrouter',
        'url'  => OPENROUTER_URL,
        'key'  => OPENROUTER_API_KEY,
        'model'=> OPENROUTER_MODEL,
        'timeout' => OPENROUTER_TIMEOUT,
    ],
]);

define('SITE_URL', rtrim(env('SITE_URL', 'http://localhost/uzdub'), '/'));
