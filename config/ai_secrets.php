<?php
/* ============================================================
   config/ai_secrets.php
   ============================================================ */

require_once __DIR__ . '/db.php'; // .env yuklanishini ta'minlash uchun

define('OLLAMA_URL', getenv('OLLAMA_URL') ?: 'http://localhost:11434/api/chat');
define('OLLAMA_MODEL', getenv('OLLAMA_MODEL') ?: 'llama3.1:8b');
define('OLLAMA_TIMEOUT', (int)(getenv('OLLAMA_TIMEOUT') ?: 60));
