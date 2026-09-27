<?php

require_once __DIR__ . '/ai_usage.php';
require_once __DIR__ . '/ai_safety.php';
require_once __DIR__ . '/personalization.php';
require_once __DIR__ . '/gamification.php';

function validarTemaEducacional(string $texto, string $tipo = 'conteúdo'): string
{
    $texto = trim((string)preg_replace('/\s+/u', ' ', strip_tags($texto)));
    $limite = $tipo === 'matéria' ? 70 : 180;
    $minimo = $tipo === 'matéria' ? 2 : 3;
    if (mb_strlen($texto) < $minimo || mb_strlen($texto) > $limite || !preg_match('/\p{L}/u', $texto)) {
        throw new DomainException("Digite um nome de {$tipo} entre {$minimo} e {$limite} caracteres.");
    }
    if (preg_match('/[<>\x00-\x1f]/u', $texto) || preg_match('/(?:https?:\/\/|www\.|\b[\w.+-]+@[\w.-]+\.[a-z]{2,}\b)/iu', $texto)) {
        throw new DomainException('Digite somente o tema que você quer estudar, sem links, códigos ou contatos.');
    }
    $normalizado = mb_strtolower((string)iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto));
    if (preg_match('/(?:ignore|desconsidere|revele|mostre|repita).{0,30}(?:instruc|prompt|sistema|chave|api)|(?:prompt|system message|api[_ -]?key)/iu', $normalizado)) {
        throw new DomainException('Esse pedido contém instruções que não fazem parte de um tema de estudo.');
    }
    validarPedidoIASeguro($texto, $tipo);
    if (preg_match('/\b(?:porra|caralho|merda|buceta|puta|foder|fodase|desgraca)\b/iu', $normalizado)
        || preg_match('/\b(?:como|ensine|manual|passo a passo).{0,35}\b(?:fabricar bomba|matar|invadir conta|roubar senha|fraudar|clonar cartao)\b/iu', $normalizado)) {
        throw new DomainException('Peça uma matéria ou um conteúdo educacional apropriado.');
    }
    if (preg_match('/(.)\1{4,}/u', $texto) || preg_match('/\b(?:asdf\w*|qwer\w*|zxcv\w*)\b/i', $normalizado)) {
        throw new DomainException('Escreva um tema de estudo reconhecível.');
    }
    if ($tipo === 'matéria' && count(preg_split('/\s+/u', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: []) > 8) {
        throw new DomainException('Use um nome curto para a matéria, como Programação, Música ou Mecânica automotiva.');
    }
    return $texto;
}

function formatarNomeMateria(string $materia): string
{
    $materia = trim((string)preg_replace('/\s+/u', ' ', $materia));
    if ($materia === '') {
        return '';
    }

    $chave = mb_strtolower($materia, 'UTF-8');
    $chave = (string)iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $chave);
    $chave = trim((string)preg_replace('/[^a-z0-9+#]+/i', ' ', $chave));
    $grafias = [
        'matematica' => 'Matemática',
        'matemtica' => 'Matemática',
        'portugues' => 'Português',
        'portuges' => 'Português',
        'historia' => 'História',
        'geografia' => 'Geografia',
        'biologia' => 'Biologia',
        'fisica' => 'Física',
        'quimica' => 'Química',
        'ingles' => 'Inglês',
        'redacao' => 'Redação',
        'filosofia' => 'Filosofia',
        'sociologia' => 'Sociologia',
        'educacao fisica' => 'Educação física',
        'programacao' => 'Programação',
        'progamacao' => 'Programação',
        'progarmacao' => 'Programação',
        'administracao' => 'Administração',
        'adiministracao' => 'Administração',
        'computacao' => 'Computação',
        'ciencia da computacao' => 'Ciência da computação',
        'ciencias' => 'Ciências',
        'inteligencia artificial' => 'Inteligência artificial',
        'mecanica' => 'Mecânica',
        'mecanica automotiva' => 'Mecânica automotiva',
        'eletronica' => 'Eletrônica',
        'musica' => 'Música',
        'guitara' => 'Guitarra',
        'guitarra' => 'Guitarra',
        'violao' => 'Violão',
        'animacao' => 'Animação',
        'animacao 2d' => 'Animação 2D',
        'animacao 3d' => 'Animação 3D',
        'psicologia' => 'Psicologia',
        'astronomia' => 'Astronomia',
    ];
    if (isset($grafias[$chave])) {
        return $grafias[$chave];
    }

    return mb_strtoupper(mb_substr($materia, 0, 1, 'UTF-8'), 'UTF-8')
        . mb_substr($materia, 1, null, 'UTF-8');
}

function formatarTituloLivro(string $titulo): string
{
    $titulo = trim((string)preg_replace('/\s+/u', ' ', $titulo));
    if ($titulo === '') {
        return '';
    }

    return mb_strtoupper(mb_substr($titulo, 0, 1, 'UTF-8'), 'UTF-8')
        . mb_substr($titulo, 1, null, 'UTF-8');
}

function materiaDisponivelParaUsuario(array $usuario, string $materia, bool $possuiConteudo = false): bool
{
    $perfil = adaptiveProfile($usuario);
    $selecionadas = $perfil['materias']['selected'] ?? [];
    $outra = trim((string)($perfil['materias']['other'] ?? ''));
    $adicionadas = is_array($perfil['materias_adicionadas'] ?? null)
        ? $perfil['materias_adicionadas']
        : [];
    $slug = adaptiveSlug($materia);

    foreach ($selecionadas as $selecionada) {
        if (adaptiveSlug((string)$selecionada) === $slug) {
            return true;
        }
    }
    if ($outra !== '' && adaptiveSlug($outra) === $slug) {
        return true;
    }

    foreach ($adicionadas as $adicionada) {
        if (is_string($adicionada) && adaptiveSlug($adicionada) === $slug) {
            return true;
        }
    }

    $perfilDefineMaterias = !empty($selecionadas) || $outra !== '' || !empty($adicionadas);
    return !$perfilDefineMaterias && $possuiConteudo;
}

function adicionarMateriaAoPerfil(PDO $pdo, array &$usuario, string $materia): void
{
    $perfil = adaptiveProfile($usuario);
    $adicionadas = is_array($perfil['materias_adicionadas'] ?? null)
        ? $perfil['materias_adicionadas']
        : [];
    foreach ($adicionadas as $adicionada) {
        if (is_string($adicionada) && adaptiveSlug($adicionada) === adaptiveSlug($materia)) {
            return;
        }
    }

    $adicionadas[] = $materia;
    $perfil['materias_adicionadas'] = array_values($adicionadas);
    $json = json_encode($perfil, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $pdo->prepare('UPDATE users SET personalizacao_json = ? WHERE id = ?')
        ->execute([$json, (int)$usuario['id']]);
    $usuario['personalizacao_json'] = $json;
    $GLOBALS['neo_personalization_user'] = $usuario;
}

function perfilSemMateria(array $perfil, string $materia, array $conteudoIds = []): array
{
    $slug = adaptiveSlug($materia);
    $ids = array_fill_keys(array_map('intval', $conteudoIds), true);
    $outraEraMateria = adaptiveSlug((string)($perfil['materias']['other'] ?? '')) === $slug;

    $selecionadas = is_array($perfil['materias']['selected'] ?? null)
        ? $perfil['materias']['selected']
        : [];
    $perfil['materias']['selected'] = array_values(array_filter(
        $selecionadas,
        static fn($selecionada): bool => adaptiveSlug((string)$selecionada) !== $slug
    ));

    if ($outraEraMateria) {
        $perfil['materias']['other'] = '';
    }

    $adicionadas = is_array($perfil['materias_adicionadas'] ?? null)
        ? $perfil['materias_adicionadas']
        : [];
    $perfil['materias_adicionadas'] = array_values(array_filter(
        $adicionadas,
        static fn($adicionada): bool => adaptiveSlug((string)$adicionada) !== $slug
    ));

    if (isset($perfil['niveis']) && is_array($perfil['niveis'])) {
        foreach (array_keys($perfil['niveis']) as $chave) {
            if (adaptiveSlug((string)$chave) === $slug || ($outraEraMateria && $chave === 'outra')) {
                unset($perfil['niveis'][$chave]);
            }
        }
    }
    if (isset($perfil['diagnostic_result']['subjects']) && is_array($perfil['diagnostic_result']['subjects'])) {
        foreach (array_keys($perfil['diagnostic_result']['subjects']) as $chave) {
            if (adaptiveSlug((string)$chave) === $slug || ($outraEraMateria && $chave === 'outra')) {
                unset($perfil['diagnostic_result']['subjects'][$chave]);
            }
        }
    }
    if (isset($perfil['content_map']) && is_array($perfil['content_map'])) {
        $chavesRemovidas = [];
        foreach ($perfil['content_map'] as $chave => $conteudoId) {
            $chaveNormalizada = adaptiveSlug((string)$chave);
            if (isset($ids[(int)$conteudoId]) || $chaveNormalizada === $slug || str_starts_with($chaveNormalizada, $slug . '_')) {
                $chavesRemovidas[(string)$chave] = true;
                unset($perfil['content_map'][$chave]);
            }
        }
        if (isset($perfil['prioridades']) && is_array($perfil['prioridades'])) {
            foreach (array_keys($chavesRemovidas) as $chave) {
                unset($perfil['prioridades'][$chave]);
            }
        }
    }

    return $perfil;
}

function perfilSemConteudos(array $perfil, array $conteudoIds): array
{
    $ids = array_fill_keys(array_map('intval', $conteudoIds), true);
    if (!$ids || !isset($perfil['content_map']) || !is_array($perfil['content_map'])) {
        return $perfil;
    }

    $chavesRemovidas = [];
    foreach ($perfil['content_map'] as $chave => $conteudoId) {
        if (isset($ids[(int)$conteudoId])) {
            $chavesRemovidas[(string)$chave] = true;
            unset($perfil['content_map'][$chave]);
        }
    }
    if (isset($perfil['prioridades']) && is_array($perfil['prioridades'])) {
        foreach (array_keys($chavesRemovidas) as $chave) {
            unset($perfil['prioridades'][$chave]);
        }
    }
    return $perfil;
}

function simuladoReferenciaMateria(array $simulado, int $materiaId, array $conteudoIds): bool
{
    $ids = array_fill_keys(array_map('intval', $conteudoIds), true);
    $filtros = json_decode((string)($simulado['filtros_json'] ?? ''), true);
    if (is_array($filtros)) {
        if ((int)($filtros['materia_id'] ?? 0) === $materiaId) {
            return true;
        }
        if (isset($ids[(int)($filtros['conteudo_id'] ?? 0)])) {
            return true;
        }
    }

    $questoes = json_decode((string)($simulado['questoes_json'] ?? ''), true);
    if (!is_array($questoes)) {
        return false;
    }
    foreach ($questoes as $questao) {
        if (!is_array($questao)) {
            continue;
        }
        if ((int)($questao['materia_id'] ?? 0) === $materiaId
            || isset($ids[(int)($questao['conteudo_id'] ?? 0)])) {
            return true;
        }
    }
    return false;
}

function simuladoReferenciaConteudos(array $simulado, array $conteudoIds): bool
{
    $ids = array_fill_keys(array_map('intval', $conteudoIds), true);
    if (!$ids) {
        return false;
    }
    $filtros = json_decode((string)($simulado['filtros_json'] ?? ''), true);
    if (is_array($filtros) && isset($ids[(int)($filtros['conteudo_id'] ?? 0)])) {
        return true;
    }
    $questoes = json_decode((string)($simulado['questoes_json'] ?? ''), true);
    if (!is_array($questoes)) {
        return false;
    }
    foreach ($questoes as $questao) {
        if (is_array($questao) && isset($ids[(int)($questao['conteudo_id'] ?? 0)])) {
            return true;
        }
    }
    return false;
}

function apagarSimuladosReferentes(PDO $pdo, int $userId, callable $referencia): void
{
    $stmt = $pdo->prepare('SELECT id, filtros_json, questoes_json FROM neo_simulados WHERE user_id = ? FOR UPDATE');
    $stmt->execute([$userId]);
    $ids = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $simulado) {
        if ($referencia($simulado)) {
            $ids[] = (int)$simulado['id'];
        }
    }
    if (!$ids) {
        return;
    }
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("DELETE FROM neo_simulados WHERE user_id = ? AND id IN ({$marcadores})")
        ->execute(array_merge([$userId], $ids));
}

function arquivarMateriaUsuario(PDO $pdo, array &$usuario, int $materiaId, bool $aceitarAusente = false): int
{
    $userId = (int)($usuario['id'] ?? 0);
    if ($userId <= 0 || $materiaId <= 0) {
        throw new DomainException('Matéria inválida.');
    }

    $lockName = nomeBloqueioOperacaoIA($userId, 'materia:' . $materiaId);
    $stmtLock = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $stmtLock->execute([$lockName]);
    if ((int)$stmtLock->fetchColumn() !== 1) {
        throw new DomainException('Esta matéria está sendo atualizada. Aguarde a conclusão.');
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT nome FROM materias WHERE id = ? LIMIT 1');
        $stmt->execute([$materiaId]);
        $materia = $stmt->fetchColumn();
        if (!is_string($materia) || $materia === '') {
            throw new DomainException('Matéria não encontrada.');
        }

        $stmt = $pdo->prepare('SELECT id FROM conteudos WHERE user_id = ? AND materia_id = ? FOR UPDATE');
        $stmt->execute([$userId, $materiaId]);
        $conteudoIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if (!$conteudoIds && !materiaDisponivelParaUsuario($usuario, $materia) && !$aceitarAusente) {
            throw new DomainException('Essa matéria já foi apagada.');
        }

        apagarSimuladosReferentes(
            $pdo,
            $userId,
            static fn(array $simulado): bool => simuladoReferenciaMateria($simulado, $materiaId, $conteudoIds)
        );

        $stmtSemanas = $pdo->prepare("SELECT DISTINCT DATE_SUB(data_atividade, INTERVAL WEEKDAY(data_atividade) DAY) FROM atividades_estudo_diarias WHERE user_id = ? AND materia_id = ?");
        $stmtSemanas->execute([$userId, $materiaId]);
        $semanasAfetadas = $stmtSemanas->fetchAll(PDO::FETCH_COLUMN);

        // Apaga explicitamente as referências visíveis antes dos livros. As chaves
        // estrangeiras continuam como uma segunda garantia para dados antigos.
        $pdo->prepare('DELETE FROM ultimos_acessos WHERE user_id = ? AND materia_id = ?')
            ->execute([$userId, $materiaId]);
        $pdo->prepare('DELETE FROM neo_learning_events WHERE user_id = ? AND materia_id = ?')
            ->execute([$userId, $materiaId]);
        $pdo->prepare('DELETE FROM atividades_estudo_diarias WHERE user_id = ? AND materia_id = ?')
            ->execute([$userId, $materiaId]);
        recalcularOfensivasSemanaisUsuario($pdo, $userId, $semanasAfetadas);
        $pdo->prepare('DELETE FROM transacoes_exp WHERE user_id = ? AND materia_id = ?')
            ->execute([$userId, $materiaId]);
        $pdo->prepare('DELETE FROM progresso_materias WHERE user_id = ? AND materia_id = ?')
            ->execute([$userId, $materiaId]);
        $pdo->prepare('DELETE FROM conteudos WHERE user_id = ? AND materia_id = ?')
            ->execute([$userId, $materiaId]);

        $perfil = perfilSemMateria(adaptiveProfile($usuario), $materia, $conteudoIds);
        $json = json_encode($perfil, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $preferencias = json_decode((string)($usuario['preferencias_json'] ?? ''), true);
        $preferencias = is_array($preferencias) ? $preferencias : [];
        if (isset($preferencias['onboarding']) && is_array($preferencias['onboarding'])) {
            $preferencias['onboarding'] = perfilSemMateria($preferencias['onboarding'], $materia, $conteudoIds);
        }
        $preferenciasJson = json_encode($preferencias, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $pdo->prepare('UPDATE users SET personalizacao_json = ?, preferencias_json = ? WHERE id = ?')
            ->execute([$json, $preferenciasJson, $userId]);

        $stmtOnboarding = $pdo->prepare('SELECT answers_json, diagnostic_json FROM onboarding_progress WHERE user_id = ? FOR UPDATE');
        $stmtOnboarding->execute([$userId]);
        if ($rascunho = $stmtOnboarding->fetch(PDO::FETCH_ASSOC)) {
            $respostas = json_decode((string)$rascunho['answers_json'], true);
            $diagnostico = json_decode((string)($rascunho['diagnostic_json'] ?? ''), true);
            $respostas = perfilSemMateria(is_array($respostas) ? $respostas : [], $materia, $conteudoIds);
            $diagnostico = perfilSemMateria(is_array($diagnostico) ? $diagnostico : [], $materia, $conteudoIds);
            $pdo->prepare('UPDATE onboarding_progress SET answers_json = ?, diagnostic_json = ? WHERE user_id = ?')
                ->execute([
                    json_encode($respostas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    json_encode($diagnostico, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    $userId,
                ]);
        }

        $usuario['personalizacao_json'] = $json;
        $usuario['preferencias_json'] = $preferenciasJson;
        $GLOBALS['neo_personalization_user'] = $usuario;
        $pdo->commit();
        return count($conteudoIds);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } finally {
        $stmtRelease = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $stmtRelease->execute([$lockName]);
    }
}

function salvarConteudosGerados(PDO $pdo, int $userId, int $materiaId, array $gerados, int $dificuldade, int $ordemInicial, array $titulosExistentes): int
{
    $stmtInsert = $pdo->prepare("INSERT INTO conteudos (user_id, materia_id, titulo, status, corpo, ai_provider, ai_model, dificuldade, ordem) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtArquivado = $pdo->prepare('SELECT id FROM conteudos WHERE user_id = ? AND materia_id = ? AND titulo = ? AND removido_em IS NOT NULL ORDER BY id DESC LIMIT 1 FOR UPDATE');
    $stmtReativar = $pdo->prepare('UPDATE conteudos SET status = ?, corpo = ?, ai_provider = ?, ai_model = ?, dificuldade = ?, ordem = ?, removido_em = NULL WHERE id = ? AND user_id = ?');
    $salvos = 0;
    $titulosNormalizados = [];
    $iniciouTransacao = !$pdo->inTransaction();
    if ($iniciouTransacao) $pdo->beginTransaction();
    try {
        foreach ($titulosExistentes as $tituloExistente) $titulosNormalizados[mb_strtolower(trim($tituloExistente))] = true;
        foreach ($gerados as $conteudoGerado) {
            $titulo = formatarTituloLivro(limparMarcacaoIA((string)($conteudoGerado['titulo'] ?? '')));
            $corpo = normalizarCorpoLivroIA((string)($conteudoGerado['corpo'] ?? ''), $titulo);
            $normalizado = mb_strtolower($titulo);
            if ($corpo !== '' && problemasTextoEducacional($corpo, 180)) {
                throw new RuntimeException('Erro no sistema, tente novamente mais tarde.');
            }
            if ($titulo !== '' && empty($titulosNormalizados[$normalizado])) {
                $status = $corpo !== '' ? 'Gerado pela IA' : 'Não iniciado';
                $provider = trim((string)($conteudoGerado['_ai_provider'] ?? 'Local'));
                $model = trim((string)($conteudoGerado['_ai_model'] ?? 'fallback'));
                $stmtArquivado->execute([$userId, $materiaId, $titulo]);
                $arquivadoId = (int)($stmtArquivado->fetchColumn() ?: 0);
                if ($arquivadoId > 0) {
                    $stmtReativar->execute([$status, $corpo, $provider, $model, $dificuldade, $ordemInicial + $salvos, $arquivadoId, $userId]);
                } else {
                    $stmtInsert->execute([$userId,$materiaId,$titulo,$status,$corpo,$provider,$model,$dificuldade,$ordemInicial + $salvos]);
                }
                $titulosNormalizados[$normalizado] = true;
                $salvos++;
            }
        }
        if ($salvos !== count($gerados)) throw new RuntimeException('Nem todos os conteúdos passaram pela validação de unicidade.');
        if ($iniciouTransacao) $pdo->commit();
        return $salvos;
    } catch (Throwable $e) {
        if ($iniciouTransacao && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function tituloIntroducaoMateria(string $materia): string
{
    $materia = formatarNomeMateria($materia);
    $normalizada = (string)iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($materia, 'UTF-8'));
    $primeiraPalavra = strtok($normalizada, ' ') ?: $normalizada;
    $masculinas = ['portugues', 'ingles', 'direito', 'desenho', 'violao', 'piano', 'teatro', 'marketing', 'xadrez'];
    $femininas = ['matematica', 'historia', 'geografia', 'biologia', 'fisica', 'quimica', 'redacao', 'filosofia', 'sociologia', 'programacao', 'administracao', 'mecanica', 'eletronica', 'musica', 'guitarra', 'astronomia', 'psicologia'];

    if (in_array($primeiraPalavra, $masculinas, true)) {
        return 'Introdução ao ' . $materia;
    }
    if (in_array($primeiraPalavra, $femininas, true) || preg_match('/(?:a|cao|dade|gem)$/u', $normalizada)) {
        return 'Introdução à ' . $materia;
    }
    return 'Introdução a ' . $materia;
}

function criarLivroIntroducaoMateria(PDO $pdo, int $userId, int $materiaId, string $materia, int $dificuldade = 1): int
{
    $stmtExiste = $pdo->prepare('SELECT id FROM conteudos WHERE user_id = ? AND materia_id = ? AND removido_em IS NULL ORDER BY ordem, id LIMIT 1');
    $stmtExiste->execute([$userId, $materiaId]);
    $existente = (int)($stmtExiste->fetchColumn() ?: 0);
    if ($existente > 0) {
        return $existente;
    }

    $titulo = tituloIntroducaoMateria($materia);
    $stmtArquivado = $pdo->prepare('SELECT id FROM conteudos WHERE user_id = ? AND materia_id = ? AND titulo = ? AND removido_em IS NOT NULL ORDER BY id DESC LIMIT 1');
    $stmtArquivado->execute([$userId, $materiaId, $titulo]);
    $arquivadoId = (int)($stmtArquivado->fetchColumn() ?: 0);
    if ($arquivadoId > 0) {
        $pdo->prepare("UPDATE conteudos SET status = 'Não iniciado', corpo = '', ai_provider = 'Local', ai_model = 'intro', dificuldade = ?, ordem = 1, removido_em = NULL WHERE id = ? AND user_id = ?")
            ->execute([max(1, $dificuldade), $arquivadoId, $userId]);
        return $arquivadoId;
    }

    $stmtInsert = $pdo->prepare("
        INSERT INTO conteudos (user_id, materia_id, titulo, status, corpo, ai_provider, ai_model, dificuldade, ordem)
        VALUES (?, ?, ?, 'Não iniciado', '', 'Local', 'intro', ?, 1)
    ");
    $stmtInsert->execute([$userId, $materiaId, $titulo, max(1, $dificuldade)]);
    return (int)$pdo->lastInsertId();
}

function primeiroLivroMateria(PDO $pdo, int $userId, int $materiaId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM conteudos WHERE user_id = ? AND materia_id = ? AND removido_em IS NULL ORDER BY ordem, id LIMIT 1');
    $stmt->execute([$userId, $materiaId]);
    $conteudo = $stmt->fetch(PDO::FETCH_ASSOC);
    return $conteudo ?: null;
}

function introducaoMateriaConcluida(PDO $pdo, int $userId, int $materiaId): bool
{
    $primeiro = primeiroLivroMateria($pdo, $userId, $materiaId);
    return $primeiro !== null && (string)($primeiro['status'] ?? '') === 'Concluído';
}

function exigirIntroducaoConcluida(PDO $pdo, int $userId, int $materiaId): void
{
    if (!introducaoMateriaConcluida($pdo, $userId, $materiaId)) {
        throw new DomainException('Conclua o livro de introdução antes de pedir novos livros nessa matéria.');
    }
}

function gerarSeisConteudos(string $materia, string $gostos, array $titulosExistentes, int $nivel): array
{
    $conteudos = gerarConteudos($materia, $gostos, $titulosExistentes, $nivel);
    if (count($conteudos) !== 6) {
        throw new RuntimeException('A IA gerou uma trilha incompleta. Tente novamente.');
    }
    return $conteudos;
}

function arquivarConteudos(PDO $pdo, int $userId, int $materiaId, array $conteudoIds): int
{
    $conteudoIds = array_values(array_unique(array_filter(array_map('intval', $conteudoIds), static fn(int $id): bool => $id > 0)));
    if ($userId <= 0 || $materiaId <= 0 || !$conteudoIds) {
        return 0;
    }

    $lockName = nomeBloqueioOperacaoIA($userId, 'materia:' . $materiaId);
    $stmtLock = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $stmtLock->execute([$lockName]);
    if ((int)$stmtLock->fetchColumn() !== 1) {
        throw new DomainException('Esta matéria está sendo atualizada. Aguarde a conclusão antes de apagar livros.');
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id FROM conteudos WHERE materia_id = ? AND user_id = ? AND removido_em IS NULL ORDER BY ordem, id FOR UPDATE');
        $stmt->execute([$materiaId, $userId]);
        $ativos = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $alvos = array_values(array_intersect($ativos, $conteudoIds));
        if (!$alvos) {
            $pdo->commit();
            return 0;
        }

        $primeiroId = $ativos[0] ?? 0;
        $restantes = array_values(array_diff($ativos, $alvos));
        if ($primeiroId > 0 && in_array($primeiroId, $alvos, true) && $restantes) {
            throw new DomainException('A introdução só pode ser apagada junto com todos os outros livros da matéria.');
        }

        apagarSimuladosReferentes(
            $pdo,
            $userId,
            static fn(array $simulado): bool => simuladoReferenciaConteudos($simulado, $alvos)
        );

        $marcadores = implode(',', array_fill(0, count($alvos), '?'));
        $parametros = array_merge([$userId], $alvos);
        $pdo->prepare("DELETE FROM ultimos_acessos WHERE user_id = ? AND conteudo_id IN ({$marcadores})")->execute($parametros);
        $pdo->prepare("DELETE FROM neo_learning_events WHERE user_id = ? AND conteudo_id IN ({$marcadores})")->execute($parametros);
        $pdo->prepare("DELETE FROM questoes WHERE user_id = ? AND conteudo_id IN ({$marcadores})")->execute($parametros);
        $pdo->prepare("UPDATE conteudos SET removido_em = NOW() WHERE user_id = ? AND id IN ({$marcadores})")->execute($parametros);

        $stmtUsuario = $pdo->prepare('SELECT personalizacao_json, preferencias_json FROM users WHERE id = ? FOR UPDATE');
        $stmtUsuario->execute([$userId]);
        $dadosUsuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC) ?: [];
        $perfil = perfilSemConteudos(adaptiveProfile(['personalizacao_json' => $dadosUsuario['personalizacao_json'] ?? '']), $alvos);
        $preferencias = json_decode((string)($dadosUsuario['preferencias_json'] ?? ''), true);
        $preferencias = is_array($preferencias) ? $preferencias : [];
        if (isset($preferencias['onboarding']) && is_array($preferencias['onboarding'])) {
            $preferencias['onboarding'] = perfilSemConteudos($preferencias['onboarding'], $alvos);
        }
        $perfilJson = json_encode($perfil, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $preferenciasJson = json_encode($preferencias, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $pdo->prepare('UPDATE users SET personalizacao_json = ?, preferencias_json = ? WHERE id = ?')
            ->execute([$perfilJson, $preferenciasJson, $userId]);

        $stmtOnboarding = $pdo->prepare('SELECT answers_json, diagnostic_json FROM onboarding_progress WHERE user_id = ? FOR UPDATE');
        $stmtOnboarding->execute([$userId]);
        if ($rascunho = $stmtOnboarding->fetch(PDO::FETCH_ASSOC)) {
            $respostas = perfilSemConteudos((array)(json_decode((string)$rascunho['answers_json'], true) ?: []), $alvos);
            $diagnostico = perfilSemConteudos((array)(json_decode((string)($rascunho['diagnostic_json'] ?? ''), true) ?: []), $alvos);
            $pdo->prepare('UPDATE onboarding_progress SET answers_json = ?, diagnostic_json = ? WHERE user_id = ?')->execute([
                json_encode($respostas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                json_encode($diagnostico, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                $userId,
            ]);
        }

        $pdo->commit();
        if (isset($GLOBALS['neo_personalization_user']) && (int)($GLOBALS['neo_personalization_user']['id'] ?? 0) === $userId) {
            $GLOBALS['neo_personalization_user']['personalizacao_json'] = $perfilJson;
            $GLOBALS['neo_personalization_user']['preferencias_json'] = $preferenciasJson;
        }
        return count($alvos);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } finally {
        $stmtRelease = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $stmtRelease->execute([$lockName]);
    }
}

function arquivarConteudo(PDO $pdo, int $userId, int $materiaId, int $conteudoId): bool
{
    return arquivarConteudos($pdo, $userId, $materiaId, [$conteudoId]) > 0;
}
