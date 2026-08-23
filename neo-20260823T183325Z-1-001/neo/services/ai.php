<?php

function groqApiKey(): string
{
    $envKey = getenv('GROQ_API_KEY');
    if (is_string($envKey) && trim($envKey) !== '') {
        return trim($envKey);
    }

    $config = __DIR__ . '/../config/groq.php';
    if (is_file($config)) {
        clearstatcache(true, $config);
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($config, true);
        }

        require $config;
        if (!empty($groqApiKey) && trim((string)$groqApiKey) !== '') {
            return trim((string)$groqApiKey);
        }
    }

    throw new Exception('Chave da API da Groq nao configurada.');
}

function chamarGroq(array $messages, array $schema, string $schemaName): array
{
    $dados = [
        'model' => 'openai/gpt-oss-20b',
        'messages' => $messages,
        'temperature' => 0.7,
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => $schemaName,
                'strict' => true,
                'schema' => $schema
            ]
        ]
    ];

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . groqApiKey()
        ],
        CURLOPT_POSTFIELDS => json_encode($dados, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 120
    ]);

    $resposta = curl_exec($ch);
    if ($resposta === false) {
        $erro = curl_error($ch);
        curl_close($ch);
        throw new Exception('Erro ao conectar com a Groq: ' . $erro);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $dadosResposta = json_decode($resposta, true);
    if ($httpCode < 200 || $httpCode >= 300) {
        $mensagem = $dadosResposta['error']['message'] ?? 'Erro desconhecido na API da Groq.';
        if ($httpCode === 401) {
            throw new Exception('A chave da API da Groq esta invalida ou expirou. Atualize a chave em config/groq.php ou na variavel GROQ_API_KEY.');
        }

        throw new Exception("Erro da Groq ({$httpCode}): {$mensagem}");
    }

    $json = $dadosResposta['choices'][0]['message']['content'] ?? '';
    $resultado = json_decode($json, true);
    if (!is_array($resultado)) {
        throw new Exception('A resposta da IA possui formato invalido.');
    }

    return $resultado;
}

function gerarConteudos(string $materia, string $gostos = '', array $titulosExistentes = [], int $proximoNivel = 1): array
{
    $schema = [
        'type' => 'object',
        'properties' => [
            'conteudos' => [
                'type' => 'array',
                'minItems' => 6,
                'maxItems' => 6,
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'titulo' => ['type' => 'string'],
                        'corpo' => ['type' => 'string']
                    ],
                    'required' => ['titulo', 'corpo'],
                    'additionalProperties' => false
                ]
            ]
        ],
        'required' => ['conteudos'],
        'additionalProperties' => false
    ];

    $preferencias = $gostos !== '' ? "Preferencias do estudante: {$gostos}" : 'Sem preferencias informadas.';
    $existentes = $titulosExistentes ? implode('; ', array_map('trim', $titulosExistentes)) : 'Nenhum conteudo existente.';

    $resultado = chamarGroq([
        [
            'role' => 'system',
            'content' => 'Voce cria conteudos educacionais serios, especificos e didaticos para estudantes do ensino medio brasileiro. Responda somente no JSON solicitado.'
        ],
        [
            'role' => 'user',
            'content' => "Materia: {$materia}\n{$preferencias}\nConteudos ja existentes: {$existentes}\nNivel desta nova leva: {$proximoNivel}\nCrie exatamente 6 novos conteudos em progressao, continuando depois dos conteudos existentes. Se ja houver conteudos basicos, avance para temas intermediarios; se ja houver intermediarios, avance para temas mais dificeis; se ja houver avancados, aprofunde com topicos mais especificos. Nao repita nem reescreva nenhum titulo ja existente. Os titulos devem ser nomes serios, especificos e academicos de topicos reais da materia, como Energia potencial gravitacional, Cinematica escalar, Estequiometria, Funcoes quadraticas ou Concordancia verbal. Evite titulos genericos, infantis ou em forma de pergunta. Cada conteudo deve ter titulo e corpo completo, com explicacao em formato de mini-livro, exemplos e linguagem adaptada as preferencias do estudante."
        ]
    ], $schema, 'geracao_conteudos');

    return $resultado['conteudos'] ?? [];
}

function gerarQuestoes(string $materia, string $titulo, string $corpo, string $gostos = ''): array
{
    $schema = [
        'type' => 'object',
        'properties' => [
            'questoes' => [
                'type' => 'array',
                'minItems' => 5,
                'maxItems' => 8,
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'enunciado' => ['type' => 'string'],
                        'opcao_a' => ['type' => 'string'],
                        'opcao_b' => ['type' => 'string'],
                        'opcao_c' => ['type' => 'string'],
                        'opcao_d' => ['type' => 'string'],
                        'correta' => ['type' => 'string', 'enum' => ['A', 'B', 'C', 'D']]
                    ],
                    'required' => ['enunciado', 'opcao_a', 'opcao_b', 'opcao_c', 'opcao_d', 'correta'],
                    'additionalProperties' => false
                ]
            ]
        ],
        'required' => ['questoes'],
        'additionalProperties' => false
    ];

    $preferencias = $gostos !== '' ? "Preferencias do estudante: {$gostos}" : 'Sem preferencias informadas.';

    $resultado = chamarGroq([
        [
            'role' => 'system',
            'content' => 'Voce cria questoes objetivas para estudo. Cada questao deve ter uma unica alternativa correta. Responda somente no JSON solicitado.'
        ],
        [
            'role' => 'user',
            'content' => "Materia: {$materia}\nConteudo: {$titulo}\n{$preferencias}\nTexto base:\n{$corpo}\n\nCrie de 5 a 8 questoes sobre esse conteudo, adaptando exemplos ao gosto do estudante quando fizer sentido."
        ]
    ], $schema, 'geracao_questoes');

    return $resultado['questoes'] ?? [];
}

function gerarLivro(string $materia, string $titulo, string $gostos = ''): array
{
    $schema = [
        'type' => 'object',
        'properties' => [
            'titulo' => ['type' => 'string'],
            'corpo' => ['type' => 'string']
        ],
        'required' => ['titulo', 'corpo'],
        'additionalProperties' => false
    ];

    $preferencias = $gostos !== '' ? "Preferencias do estudante: {$gostos}" : 'Sem preferencias informadas.';

    return chamarGroq([
        [
            'role' => 'system',
            'content' => 'Voce escreve livros curtos, claros e didaticos para estudantes do ensino medio brasileiro. Responda somente no JSON solicitado.'
        ],
        [
            'role' => 'user',
            'content' => "Materia: {$materia}\nConteudo desejado: {$titulo}\n{$preferencias}\nGere uma nova versao completa do livro desse conteudo. Mantenha o foco no tema, explique passo a passo, use exemplos e adapte a linguagem aos gostos do estudante."
        ]
    ], $schema, 'geracao_livro');
}

function gerar(string $materia): array
{
    return gerarConteudos($materia);
}
