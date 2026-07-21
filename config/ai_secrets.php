<?php
require_once __DIR__ . '/env.php';

define('OLLAMA_URL', env('OLLAMA_URL', 'http://localhost:11434/api/chat'));
define('OLLAMA_MODEL', env('OLLAMA_MODEL', 'llama3.1:8b'));
define('OLLAMA_TIMEOUT', 60);
define('OLLAMA_MAX_CONCURRENT', (int)env('OLLAMA_MAX_CONCURRENT', '1'));
define('OLLAMA_QUEUE_WAIT_SECONDS', 25);
define('OLLAMA_NUM_CTX', 2048);
define('OLLAMA_NUM_PREDICT', 200);
define('OLLAMA_NUM_THREAD', 4);
define('AI_HISTORY_MESSAGES', 8);
define('AI_MAX_RECOMMENDATIONS', 3);

define('SITE_URL', rtrim(env('SITE_URL', 'http://localhost/uzdub'), '/'));
