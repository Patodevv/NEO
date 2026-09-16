<?php

require_once __DIR__ . '/ai_usage.php';
require_once __DIR__ . '/personalization.php';

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

    if (in_array($slug, $selecionadas, true)) {
        return true;
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

    $selecionadas = is_array($perfil['materias']['selected'] ?? null)
        ? $perfil['materias']['selected']
        : [];
    $perfil['materias']['selected'] = array_values(array_filter(
        $selecionadas,
        static fn($selecionada): bool => adaptiveSlug((string)$selecionada) !== $slug
    ));

    if (adaptiveSlug((string)($perfil['materias']['other'] ?? '')) === $slug) {
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
        unset($perfil['niveis'][$slug]);
    }
    if (isset($perfil['diagnostic_result']['subjects']) && is_array($perfil['diagnostic_result']['subjects'])) {
        unset($perfil['diagnostic_result']['subjects'][$slug]);
    }
    if (isset($perfil['content_map']) && is_array($perfil['content_map'])) {
        $perfil['content_map'] = array_filter(
            $perfil['content_map'],
            static fn($conteudoId): bool => !isset($ids[(int)$conteudoId])
        );
    }

    return $perfil;
}

function arquivarMateriaUsuario(PDO $pdo, array &$usuario, int $materiaId): int
{
    $userId = (int)($usuario['id'] ?? 0);
    if ($userId <= 0 || $materiaId <= 0) {
        throw new DomainException('Matéria inválida.');
    }

    $lockName = nomeBloqueioOperacaoIA($userId, 'materia:' . $materiaId);
    $stmtLock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
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

        $stmt = $pdo->prepare('SELECT id FROM conteudos WHERE user_id = ? AND materia_id = ? AND removido_em IS NULL FOR UPDATE');
        $stmt->execute([$userId, $materiaId]);
        $conteudoIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if (!$conteudoIds && !materiaDisponivelParaUsuario($usuario, $materia)) {
            throw new DomainException('Essa matéria já foi apagada.');
        }

        if ($conteudoIds) {
            $marcadores = implode(',', array_fill(0, count($conteudoIds), '?'));
            $parametros = array_merge($conteudoIds, [$userId]);
            $pdo->prepare("DELETE FROM ultimos_acessos WHERE conteudo_id IN ({$marcadores}) AND user_id = ?")
                ->execute($parametros);
            $pdo->prepare("DELETE FROM questoes WHERE conteudo_id IN ({$marcadores}) AND user_id = ?")
                ->execute($parametros);
            $stmtArquivar = $pdo->prepare('UPDATE conteudos SET removido_em = NOW() WHERE user_id = ? AND materia_id = ? AND removido_em IS NULL');
            $stmtArquivar->execute([$userId, $materiaId]);
        }

        $perfil = perfilSemMateria(adaptiveProfile($usuario), $materia, $conteudoIds);
        $json = json_encode($perfil, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $pdo->prepare('UPDATE users SET personalizacao_json = ? WHERE id = ?')->execute([$json, $userId]);
        $usuario['personalizacao_json'] = $json;
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
                $stmtInsert->execute([$userId,$materiaId,$titulo,$status,$corpo,trim((string)($conteudoGerado['_ai_provider'] ?? 'Local')),trim((string)($conteudoGerado['_ai_model'] ?? 'fallback')),$dificuldade,$ordemInicial + $salvos]);
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

function gerarSeisConteudos(string $materia, string $gostos, array $titulosExistentes, int $nivel): array
{
    $conteudos = gerarConteudos($materia, $gostos, $titulosExistentes, $nivel);
    if (count($conteudos) !== 6) {
        throw new RuntimeException('A IA gerou uma trilha incompleta. Tente novamente.');
    }
    return $conteudos;
}

function arquivarConteudo(PDO $pdo, int $userId, int $materiaId, int $conteudoId): bool
{
    $lockName = nomeBloqueioOperacaoIA($userId, 'conteudo:' . $conteudoId);
    $stmtLock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $stmtLock->execute([$lockName]);
    if ((int)$stmtLock->fetchColumn() !== 1) {
        throw new DomainException('Este livro está sendo atualizado. Aguarde a conclusão antes de apagá-lo.');
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            SELECT id FROM conteudos
            WHERE id = ? AND materia_id = ? AND user_id = ? AND removido_em IS NULL
            FOR UPDATE
        ");
        $stmt->execute([$conteudoId, $materiaId, $userId]);

        if ($stmt->fetchColumn() === false) {
            $pdo->commit();
            return false;
        }

        $pdo->prepare('DELETE FROM ultimos_acessos WHERE conteudo_id = ? AND user_id = ?')
            ->execute([$conteudoId, $userId]);
        $pdo->prepare('DELETE FROM questoes WHERE conteudo_id = ? AND user_id = ?')
            ->execute([$conteudoId, $userId]);
        $pdo->prepare('UPDATE conteudos SET removido_em = NOW() WHERE id = ? AND user_id = ?')
            ->execute([$conteudoId, $userId]);
        $pdo->commit();
        return true;
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
