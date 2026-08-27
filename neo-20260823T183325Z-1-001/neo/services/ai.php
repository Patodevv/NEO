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
    $tentativas = [
        ['temperature' => 0.3, 'format' => 'json_schema'],
        ['temperature' => 0.1, 'format' => 'json_schema'],
        ['temperature' => 0.2, 'format' => 'json_object'],
    ];
    $ultimoErro = '';

    foreach ($tentativas as $tentativa) {
        $dados = [
            'model' => 'openai/gpt-oss-20b',
            'messages' => $messages,
            'temperature' => $tentativa['temperature'],
            'response_format' => $tentativa['format'] === 'json_schema' ? [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $schemaName,
                    'strict' => true,
                    'schema' => $schema
                ]
            ] : [
                'type' => 'json_object'
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
            $ultimoErro = "Erro da Groq ({$httpCode}): {$mensagem}";

            if ($httpCode === 401) {
                throw new Exception('A chave da API da Groq esta invalida ou expirou. Atualize a chave em config/groq.php ou na variavel GROQ_API_KEY.');
            }

            if (
                $httpCode === 400 &&
                (
                    stripos($mensagem, 'Failed to generate JSON') !== false ||
                    stripos($mensagem, 'Failed to validate JSON') !== false ||
                    stripos($mensagem, 'json') !== false
                )
            ) {
                continue;
            }

            throw new Exception($ultimoErro);
        }

        $json = $dadosResposta['choices'][0]['message']['content'] ?? '';
        $resultado = decodificarJsonIa($json);
        if (is_array($resultado)) {
            return $resultado;
        }

        $ultimoErro = 'A resposta da IA possui formato invalido.';
    }

    throw new Exception($ultimoErro ?: 'A IA nao conseguiu gerar JSON valido.');
}

function decodificarJsonIa(string $conteudo): ?array
{
    $resultado = json_decode($conteudo, true);
    if (is_array($resultado)) {
        return $resultado;
    }

    $inicio = strpos($conteudo, '{');
    $fim = strrpos($conteudo, '}');
    if ($inicio === false || $fim === false || $fim <= $inicio) {
        return null;
    }

    $resultado = json_decode(substr($conteudo, $inicio, $fim - $inicio + 1), true);
    return is_array($resultado) ? $resultado : null;
}

function textoErroIa(Exception $e): string
{
    $mensagem = $e->getMessage();
    if (
        stripos($mensagem, 'Groq') !== false ||
        stripos($mensagem, 'API') !== false ||
        stripos($mensagem, 'JSON') !== false ||
        stripos($mensagem, 'curl') !== false ||
        stripos($mensagem, 'conectar') !== false
    ) {
        return 'A IA nao conseguiu responder agora. Geramos uma versao provisoria para voce continuar estudando.';
    }

    return $mensagem;
}

function conteudosFallback(string $materia, int $nivel = 1, array $titulosExistentes = [], string $gostos = ''): array
{
    $bases = [
        'Fundamentos de ' . $materia,
        'Conceitos essenciais de ' . $materia,
        'Aplicacoes de ' . $materia,
        'Analise de problemas em ' . $materia,
        'Revisao orientada de ' . $materia,
        'Exercicios comentados de ' . $materia,
        'Topicos avancados de ' . $materia,
        'Sintese geral de ' . $materia,
    ];

    $existentes = [];
    foreach ($titulosExistentes as $titulo) {
        $existentes[mb_strtolower(trim((string)$titulo))] = true;
    }

    $conteudos = [];
    foreach ($bases as $base) {
        $titulo = $base . ' - Nivel ' . $nivel;
        if (isset($existentes[mb_strtolower($titulo)])) {
            continue;
        }

        $conteudos[] = [
            'titulo' => $titulo,
            'corpo' => livroFallback($materia, $titulo, $gostos)['corpo'],
        ];

        if (count($conteudos) === 6) {
            break;
        }
    }

    return $conteudos;
}

function livroFallback(string $materia, string $titulo, string $gostos = ''): array
{
    $exemplo = trim($gostos) !== ''
        ? "Use os interesses informados pelo estudante ({$gostos}) como ponto de partida para criar exemplos, mas sem fugir do conteudo."
        : 'Use exemplos simples e proximos da rotina de um estudante.';

    return [
        'titulo' => $titulo,
        'corpo' => "Este material apresenta uma explicacao organizada sobre {$titulo}, dentro da materia {$materia}. Primeiro, entenda o conceito central, as palavras-chave e o tipo de problema em que ele costuma aparecer.\n\n{$exemplo} A ideia e transformar o tema em uma situacao concreta, depois voltar para a definicao formal e comparar as duas coisas.\n\nPara fixar, escreva um resumo com suas palavras, crie um exemplo proprio e resolva questoes em ordem crescente de dificuldade. Se errar, volte ao ponto especifico do erro antes de tentar novamente.",
    ];
}

function questoesFallback(string $materia, string $titulo, string $gostos = ''): array
{
    $contexto = trim($gostos) !== '' ? " considerando exemplos ligados a {$gostos}" : '';
    return [
        [
            'enunciado' => "Qual e a melhor primeira atitude ao estudar {$titulo} em {$materia}{$contexto}?",
            'opcao_a' => 'Memorizar frases soltas sem entender o contexto.',
            'opcao_b' => 'Identificar conceitos principais e exemplos de aplicacao.',
            'opcao_c' => 'Ignorar definicoes e ir direto para assuntos avancados.',
            'opcao_d' => 'Responder questoes sem ler o material.',
            'correta' => 'B',
        ],
        [
            'enunciado' => 'Por que exemplos ajudam na aprendizagem?',
            'opcao_a' => 'Porque substituem completamente a teoria.',
            'opcao_b' => 'Porque tornam desnecessaria a revisao.',
            'opcao_c' => 'Porque ligam conceitos abstratos a situacoes concretas.',
            'opcao_d' => 'Porque evitam qualquer necessidade de exercicio.',
            'correta' => 'C',
        ],
        [
            'enunciado' => 'Depois de errar uma questao, o mais indicado e:',
            'opcao_a' => 'Voltar ao conteudo e entender a causa do erro.',
            'opcao_b' => 'Apagar o resultado do historico.',
            'opcao_c' => 'Trocar imediatamente de materia.',
            'opcao_d' => 'Responder aleatoriamente ate acertar.',
            'correta' => 'A',
        ],
        [
            'enunciado' => 'Um bom resumo de estudo deve:',
            'opcao_a' => 'Copiar todo o texto original.',
            'opcao_b' => 'Usar apenas palavras dificeis.',
            'opcao_c' => 'Organizar ideias centrais com clareza.',
            'opcao_d' => 'Excluir exemplos importantes.',
            'correta' => 'C',
        ],
        [
            'enunciado' => 'Qual pratica melhora a fixacao do conteudo?',
            'opcao_a' => 'Revisar, explicar com suas palavras e resolver exercicios.',
            'opcao_b' => 'Ler uma unica vez rapidamente.',
            'opcao_c' => 'Estudar apenas quando houver prova.',
            'opcao_d' => 'Evitar comparar respostas.',
            'correta' => 'A',
        ],
    ];
}

function gerarConteudos(string $materia, string $gostos = '', array $titulosExistentes = [], int $proximoNivel = 1): array
{
    $schema = [
        'type' => 'object',
        'properties' => [
            'conteudos' => [
                'type' => 'array',
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
    $titulosLimitados = array_slice(array_values(array_filter(array_map('trim', $titulosExistentes))), -30);
    $existentes = $titulosLimitados ? implode('; ', $titulosLimitados) : 'Nenhum conteudo existente.';

    try {
        $resultado = chamarGroq([
            [
                'role' => 'system',
                'content' => 'Voce cria conteudos educacionais serios, especificos e didaticos para um unico estudante. Os gostos informados pertencem somente ao usuario atual. Nunca use preferencias que nao estejam nesta conversa. Responda somente um objeto JSON valido, sem markdown, sem comentarios e sem texto fora do JSON.'
            ],
            [
                'role' => 'user',
                'content' => "Materia: {$materia}\n{$preferencias}\nConteudos ja existentes deste mesmo usuario: {$existentes}\nNivel desta nova leva: {$proximoNivel}\nCrie exatamente 6 novos conteudos em progressao, continuando depois dos conteudos existentes. Nao repita nem reescreva nenhum titulo ja existente. Os titulos devem ser serios, especificos e academicos. Cada corpo deve ter 4 a 6 paragrafos objetivos, com explicacao clara, exemplo adaptado aos gostos do usuario quando houver gostos e mini orientacao de estudo. O objeto deve ter exatamente a chave conteudos, contendo uma lista de objetos com titulo e corpo."
            ]
        ], $schema, 'geracao_conteudos');
    } catch (Exception $e) {
        return conteudosFallback($materia, $proximoNivel, $titulosExistentes, $gostos);
    }

    if (empty($resultado['conteudos']) || !is_array($resultado['conteudos'])) {
        return conteudosFallback($materia, $proximoNivel, $titulosExistentes, $gostos);
    }

    return $resultado['conteudos'];
}

function gerarQuestoes(string $materia, string $titulo, string $corpo, string $gostos = '', int $nivel = 1): array
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

    try {
        $resultado = chamarGroq([
            [
                'role' => 'system',
                'content' => 'Voce cria questoes objetivas para um unico estudante. Use somente os gostos informados para este usuario. Cada questao deve ter uma unica alternativa correta. Responda somente um objeto JSON valido, sem markdown, sem comentarios e sem texto fora do JSON.'
            ],
            [
                'role' => 'user',
                'content' => "Materia: {$materia}\nConteudo: {$titulo}\n{$preferencias}\nNivel do estudante: {$nivel}\nTexto base:\n{$corpo}\n\nCrie de 5 a 8 questoes sobre esse conteudo. Pelo menos metade das questoes deve usar situacoes, exemplos ou analogias ligadas aos gostos do estudante quando eles existirem. Quanto maior o nivel, mais interpretativas e exigentes devem ser as questoes. O objeto deve ter exatamente a chave questoes."
            ]
        ], $schema, 'geracao_questoes');
    } catch (Exception $e) {
        return questoesFallback($materia, $titulo, $gostos);
    }

    if (empty($resultado['questoes']) || !is_array($resultado['questoes'])) {
        return questoesFallback($materia, $titulo, $gostos);
    }

    return $resultado['questoes'];
}

function gerarLivro(string $materia, string $titulo, string $gostos = '', int $nivel = 1): array
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

    try {
        $resultado = chamarGroq([
            [
                'role' => 'system',
                'content' => 'Voce escreve livros didaticos para um unico estudante do ensino medio brasileiro. Use somente os gostos informados para o usuario atual. O livro precisa parecer personalizado, nao generico. Responda somente um objeto JSON valido, sem markdown, sem comentarios e sem texto fora do JSON.'
            ],
            [
                'role' => 'user',
                'content' => "Materia: {$materia}\nConteudo desejado: {$titulo}\n{$preferencias}\nNivel do estudante: {$nivel}\nGere uma versao completa e personalizada do livro desse conteudo. Escreva de 7 a 10 paragrafos. Explique passo a passo, inclua exemplos concretos ligados aos gostos do usuario quando houver gostos, inclua um erro comum e uma forma de revisar. Nao faca texto generico. O objeto deve ter exatamente as chaves titulo e corpo."
            ]
        ], $schema, 'geracao_livro');

        if (empty($resultado['corpo']) || !is_string($resultado['corpo'])) {
            return livroFallback($materia, $titulo, $gostos);
        }

        return $resultado;
    } catch (Exception $e) {
        return livroFallback($materia, $titulo, $gostos);
    }
}

function gerar(string $materia): array
{
    return gerarConteudos($materia);
}
