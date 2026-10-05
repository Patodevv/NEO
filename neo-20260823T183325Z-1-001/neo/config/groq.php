<?php

require_once __DIR__ . '/env.php';

$groqApiKey = (string)ambienteNeo('GROQ_API_KEY', '');
$groqUrl = (string)ambienteNeo('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions');
$groqModel = (string)ambienteNeo('GROQ_MODEL', 'openai/gpt-oss-20b');
