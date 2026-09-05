<?php

require_once __DIR__ . '/env.php';

$openaiApiKey = (string)ambienteNeo('OPENAI_API_KEY', '');
$openaiUrl = (string)ambienteNeo('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');
$openaiModel = (string)ambienteNeo('OPENAI_MODEL', 'gpt-5-mini');
