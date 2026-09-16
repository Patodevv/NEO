<?php

function normalizarTextoIA(string $texto): string
{
    $texto = mb_strtolower(trim($texto));
    $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;
    return $texto;
}

function limitarEntradaPromptIA(string $texto, int $limite = 20000): string
{
    $texto = str_replace("\0", '', $texto);
    $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $texto) ?? $texto;
    return mb_substr(trim($texto), 0, max(1, $limite));
}

function limitarPalavrasIA(string $texto, int $limite): string
{
    $palavras = preg_split('/\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($palavras) <= $limite) {
        return trim($texto);
    }
    return implode(' ', array_slice($palavras, 0, $limite)) . '...';
}

function problemasTextoEducacional(string $texto, int $minimo = 120): array
{
    $problemas = [];
    $limpo = trim(strip_tags($texto));
    if (mb_strlen($limpo) < $minimo) {
        $problemas[] = 'conteudo_curto';
    }
    if (preg_match('/(prompt do sistema|instru[cç][oõ]es internas|chave de api|api[_ -]?key|como modelo de linguagem)/iu', $limpo)) {
        $problemas[] = 'vazamento_ou_texto_meta';
    }
    if (preg_match('/\b(n[aã]o sei|talvez seja|invente|ignore as instru[cç][oõ]es)\b/iu', $limpo)) {
        $problemas[] = 'incerteza_ou_injecao';
    }
    return array_values(array_unique($problemas));
}

function dicaRevelaResposta(string $dica, string $letraCorreta, string $textoCorreto): bool
{
    $normalizada = normalizarTextoIA($dica);
    if (preg_match('/\b(resposta|alternativa|op[cç][aã]o)\s+(correta\s+)?(é|seria|:)\s*' . preg_quote($letraCorreta, '/') . '\b/iu', $dica)) {
        return true;
    }

    $correto = normalizarTextoIA($textoCorreto);
    return mb_strlen($correto) >= 24 && str_contains($normalizada, $correto);
}

function schemaQuestaoIA(): array
{
    $properties = [
        'enunciado' => ['type' => 'string'],
        'opcao_a' => ['type' => 'string'],
        'opcao_b' => ['type' => 'string'],
        'opcao_c' => ['type' => 'string'],
        'opcao_d' => ['type' => 'string'],
        'correta' => ['type' => 'string', 'enum' => ['A', 'B', 'C', 'D']],
        'dificuldade' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12],
        'explicacao_correta' => ['type' => 'string'],
        'feedback_a' => ['type' => 'string'],
        'feedback_b' => ['type' => 'string'],
        'feedback_c' => ['type' => 'string'],
        'feedback_d' => ['type' => 'string'],
        'dica_1' => ['type' => 'string'],
        'dica_2' => ['type' => 'string'],
        'dica_3' => ['type' => 'string'],
    ];

    return [
        'type' => 'object',
        'properties' => $properties,
        'required' => array_keys($properties),
        'additionalProperties' => false,
    ];
}

function problemasQuestoesLocal(array $questoes): array
{
    $problemas = [];
    $enunciados = [];

    if (count($questoes) < 5 || count($questoes) > 8) {
        $problemas[] = 'quantidade_de_questoes_invalida';
    }

    foreach ($questoes as $indice => $questao) {
        $prefixo = 'questao_' . ($indice + 1) . ':';
        $correta = strtoupper(trim((string)($questao['correta'] ?? '')));
        $enunciado = normalizarTextoIA((string)($questao['enunciado'] ?? ''));
        if (mb_strlen($enunciado) < 20) {
            $problemas[] = $prefixo . 'enunciado_curto';
        } elseif (isset($enunciados[$enunciado])) {
            $problemas[] = $prefixo . 'enunciado_duplicado';
        }
        $enunciados[$enunciado] = true;

        if (!in_array($correta, ['A', 'B', 'C', 'D'], true)) {
            $problemas[] = $prefixo . 'gabarito_invalido';
        }

        $opcoes = [];
        foreach (['a', 'b', 'c', 'd'] as $letra) {
            $opcao = trim((string)($questao['opcao_' . $letra] ?? ''));
            if (mb_strlen($opcao) < 3) {
                $problemas[] = $prefixo . 'opcao_' . $letra . '_vazia';
            }
            $normalizada = normalizarTextoIA($opcao);
            if ($normalizada !== '' && isset($opcoes[$normalizada])) {
                $problemas[] = $prefixo . 'alternativas_duplicadas';
            }
            $opcoes[$normalizada] = true;
        }

        if (problemasTextoEducacional((string)($questao['explicacao_correta'] ?? ''), 35)) {
            $problemas[] = $prefixo . 'explicacao_insuficiente';
        }
        foreach (['a', 'b', 'c', 'd'] as $letra) {
            if (problemasTextoEducacional((string)($questao['feedback_' . $letra] ?? ''), 20)) {
                $problemas[] = $prefixo . 'feedback_' . $letra . '_insuficiente';
            }
        }

        $textoCorreto = (string)($questao['opcao_' . strtolower($correta)] ?? '');
        foreach ([1, 2, 3] as $nivel) {
            $dica = trim((string)($questao['dica_' . $nivel] ?? ''));
            if (mb_strlen($dica) < 15 || dicaRevelaResposta($dica, $correta, $textoCorreto)) {
                $problemas[] = $prefixo . 'dica_' . $nivel . '_invalida';
            }
        }
    }

    return array_values(array_unique($problemas));
}

function enriquecerQuestaoFallback(array $questao, int $dificuldade = 1): array
{
    $correta = strtoupper((string)($questao['correta'] ?? 'A'));
    $textoCorreto = (string)($questao['opcao_' . strtolower($correta)] ?? 'a alternativa indicada pelo gabarito');
    $questao['dificuldade'] = max(1, min(12, $dificuldade));
    $questao['explicacao_correta'] = "O raciocínio correto identifica o conceito central pedido no enunciado. Nesse caso, ele corresponde a: {$textoCorreto}";
    foreach (['A', 'B', 'C', 'D'] as $letra) {
        $questao['feedback_' . strtolower($letra)] = $letra === $correta
            ? $questao['explicacao_correta']
            : 'Essa alternativa desvia do conceito central ou aplica uma relação que o enunciado não sustenta. Compare cada afirmação com a definição apresentada no material.';
    }
    $questao['dica_1'] = 'Identifique primeiro qual conceito central o enunciado está avaliando, sem olhar para as alternativas como respostas prontas.';
    $questao['dica_2'] = 'Elimine as opções que contradizem diretamente a definição ou a relação de causa e efeito estudada no material.';
    $questao['dica_3'] = 'Compare as opções restantes com o exemplo mais próximo apresentado no conteúdo e verifique qual preserva todas as condições do enunciado.';
    return $questao;
}

function revisarConteudosIA(string $materia, int $nivel, array $conteudos): array
{
    $problemasLocais = [];
    $titulos = [];
    foreach ($conteudos as $i => $conteudo) {
        $titulo = normalizarTextoIA((string)($conteudo['titulo'] ?? ''));
        if (mb_strlen($titulo) < 6 || isset($titulos[$titulo])) {
            $problemasLocais[] = 'titulo_invalido_ou_duplicado_' . ($i + 1);
        }
        $titulos[$titulo] = true;
        foreach (problemasTextoEducacional((string)($conteudo['corpo'] ?? ''), 180) as $problema) {
            $problemasLocais[] = $problema . '_' . ($i + 1);
        }
    }

    if (count($conteudos) !== 6) {
        $problemasLocais[] = 'quantidade_invalida';
    }

    $schema = [
        'type' => 'object',
        'properties' => [
            'aprovado' => ['type' => 'boolean'],
            'problemas' => ['type' => 'array', 'items' => ['type' => 'string']],
            'conteudos' => [
                'type' => 'array', 'minItems' => 6, 'maxItems' => 6,
                'items' => [
                    'type' => 'object',
                    'properties' => ['titulo' => ['type' => 'string'], 'corpo' => ['type' => 'string']],
                    'required' => ['titulo', 'corpo'], 'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['aprovado', 'problemas', 'conteudos'],
        'additionalProperties' => false,
    ];

    try {
        $json = json_encode($conteudos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $revisao = chamarIA([
            ['role' => 'system', 'content' => diretrizesIA() . "\n\nVocê é o revisor pedagógico final. Verifique precisão, coerência, progressão, adequação ao nível, clareza e ausência de contradições ou conteúdo inventado. Corrija somente o necessário. Retorne JSON estrito."],
            ['role' => 'user', 'content' => "Matéria: {$materia}\nNível: {$nivel}\nProblemas locais: " . implode(', ', $problemasLocais) . "\nConteúdos:\n{$json}"],
        ], $schema, 'revisao_conteudos');
        $candidatos = $revisao['conteudos'] ?? $conteudos;
    } catch (Throwable $e) {
        $candidatos = $conteudos;
    }

    if (count($candidatos) !== 6) {
        throw new RuntimeException('A revisão não preservou os seis conteúdos necessários.');
    }
    $titulosRevisados = [];
    foreach ($candidatos as $conteudo) {
        $tituloRevisado = normalizarTextoIA((string)($conteudo['titulo'] ?? ''));
        if (mb_strlen($tituloRevisado) < 6 || isset($titulosRevisados[$tituloRevisado]) || problemasTextoEducacional((string)($conteudo['corpo'] ?? ''), 180)) {
            throw new RuntimeException('O material nao passou pela validacao de qualidade.');
        }
        $titulosRevisados[$tituloRevisado] = true;
    }
    return $candidatos;
}

function revisarLivroIA(string $materia, int $nivel, array $livro): array
{
    $problemas = problemasTextoEducacional((string)($livro['corpo'] ?? ''), 350);
    $schema = [
        'type' => 'object',
        'properties' => [
            'aprovado' => ['type' => 'boolean'],
            'problemas' => ['type' => 'array', 'items' => ['type' => 'string']],
            'titulo' => ['type' => 'string'],
            'corpo' => ['type' => 'string'],
        ],
        'required' => ['aprovado', 'problemas', 'titulo', 'corpo'],
        'additionalProperties' => false,
    ];
    try {
        $json = json_encode($livro, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $revisado = chamarIA([
            ['role' => 'system', 'content' => diretrizesIA() . "\n\nAtue como revisor didático. Garanta precisão, estrutura lógica, explicação antes da aplicação, exemplos úteis e adequação ao nível. Corrija contradições e trechos genéricos. Retorne JSON estrito."],
            ['role' => 'user', 'content' => "Matéria: {$materia}\nNível: {$nivel}\nProblemas locais: " . implode(', ', $problemas) . "\nMaterial:\n{$json}"],
        ], $schema, 'revisao_livro');
        $candidato = ['titulo' => $revisado['titulo'], 'corpo' => $revisado['corpo']];
    } catch (Throwable $e) {
        $candidato = $livro;
    }

    if (problemasTextoEducacional((string)($candidato['corpo'] ?? ''), 350)) {
        throw new RuntimeException('O livro nao passou pela validacao de qualidade.');
    }
    return $candidato;
}

function revisarQuestoesIA(string $materia, string $titulo, string $corpo, int $nivel, array $questoes): array
{
    $schema = [
        'type' => 'object',
        'properties' => [
            'aprovado' => ['type' => 'boolean'],
            'problemas' => ['type' => 'array', 'items' => ['type' => 'string']],
            'questoes' => ['type' => 'array', 'minItems' => 5, 'maxItems' => 8, 'items' => schemaQuestaoIA()],
        ],
        'required' => ['aprovado', 'problemas', 'questoes'],
        'additionalProperties' => false,
    ];

    $candidatas = $questoes;
    for ($tentativa = 1; $tentativa <= 2; $tentativa++) {
        $problemas = problemasQuestoesLocal($candidatas);
        try {
            $json = json_encode($candidatas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $revisao = chamarIA([
                ['role' => 'system', 'content' => diretrizesIA() . "\n\nVocê é revisor de avaliações. Confirme que cada questão está no contexto, tem exatamente uma resposta correta, distratores plausíveis e não ambíguos, dificuldade adequada, explicações corretas e dicas progressivas que não revelam a resposta. Corrija todos os problemas. Retorne JSON estrito."],
                ['role' => 'user', 'content' => "Matéria: {$materia}\nConteúdo: {$titulo}\nDificuldade: {$nivel}\nProblemas locais: " . implode(', ', $problemas) . "\nTexto-base:\n{$corpo}\n\nQuestões:\n{$json}"],
            ], $schema, 'revisao_questoes');
            $candidatas = $revisao['questoes'] ?? $candidatas;
        } catch (Throwable $e) {
            if (!$problemas) {
                return $candidatas;
            }
        }

        if (!problemasQuestoesLocal($candidatas)) {
            return $candidatas;
        }
    }

    throw new RuntimeException('As questoes nao passaram pela validacao de qualidade.');
}

function validarDicaFacilitador(string $dica, array $questao): bool
{
    $correta = strtoupper((string)$questao['correta']);
    $textoCorreto = (string)$questao['opcao_' . strtolower($correta)];
    return mb_strlen(trim($dica)) >= 15
        && mb_strlen($dica) <= 500
        && !dicaRevelaResposta($dica, $correta, $textoCorreto)
        && !problemasTextoEducacional($dica, 15);
}

function registrarAuditoriaIA(PDO $pdo, ?int $userId, string $tipo, string $contexto, string $status = 'entregue_validado', array $problemas = [], int $tentativas = 1): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO auditoria_ia (user_id, tipo, contexto_hash, status, tentativas, problemas_json)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId, mb_substr($tipo, 0, 50), hash('sha256', $contexto), mb_substr($status, 0, 30),
            max(1, min(10, $tentativas)),
            $problemas ? json_encode($problemas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
    } catch (Throwable $e) {
        error_log('[NEO][auditoria-ia] ' . $e->getMessage());
    }
}
