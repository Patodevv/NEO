<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/materia_icon.php';
require __DIR__ . '/services/ai.php';
exigirLogin();
$usuario = usuarioAtual($pdo);
$erroIA = '';
$erroAcao = '';
$materiaId = (int)($_GET['materia_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM materias WHERE id = ?");
$stmt->execute([$materiaId]);
$materia = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$materia) {
    header('Location: materias.php');
    exit;
}
$stmt = $pdo->prepare("SELECT * FROM conteudos WHERE materia_id = ? AND user_id = ? ORDER BY dificuldade, ordem, id");
$stmt->execute([$materiaId, $usuario['id']]);
$conteudos = $stmt->fetchAll(PDO::FETCH_ASSOC);
function salvarConteudosGerados(PDO $pdo, int $userId, int $materiaId, array $gerados, int $dificuldade, int $ordemInicial, array $titulosExistentes): int
{
    $stmtInsert = $pdo->prepare("INSERT INTO conteudos (user_id, materia_id, titulo, status, corpo, ai_provider, ai_model, dificuldade, ordem) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $salvos = 0;
    $titulosNormalizados = [];
    $iniciouTransacao = !$pdo->inTransaction();

    if ($iniciouTransacao) {
        $pdo->beginTransaction();
    }

    try {

        foreach ($titulosExistentes as $tituloExistente) {
        $titulosNormalizados[mb_strtolower(trim($tituloExistente))] = true;
        }

        foreach ($gerados as $conteudoGerado) {
        $titulo = limparMarcacaoIA((string)($conteudoGerado['titulo'] ?? ''));
        $corpo = limparMarcacaoIA((string)($conteudoGerado['corpo'] ?? ''));
        $normalizado = mb_strtolower($titulo);

        if ($titulo !== '' && $corpo !== '' && empty($titulosNormalizados[$normalizado])) {
            $stmtInsert->execute([
                $userId,
                $materiaId,
                $titulo,
                'Gerado pela IA',
                $corpo,
                trim((string)($conteudoGerado['_ai_provider'] ?? 'Local')),
                trim((string)($conteudoGerado['_ai_model'] ?? 'fallback')),
                $dificuldade,
                $ordemInicial + $salvos,
            ]);
            $titulosNormalizados[$normalizado] = true;
            $salvos++;
        }
        }

        if ($salvos !== count($gerados)) {
            throw new RuntimeException('Nem todos os conteúdos passaram pela validação de unicidade.');
        }
        if ($iniciouTransacao) {
            $pdo->commit();
        }
        return $salvos;
    } catch (Throwable $e) {
        if ($iniciouTransacao && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function gerarSeisConteudos(string $materia, string $gostos, array $titulosExistentes, int $nivel): array
{
    $conteudos = [];
    $titulosUsados = $titulosExistentes;

    for ($tentativa = 0; $tentativa < 3 && count($conteudos) < 6; $tentativa++) {
        $gerados = gerarConteudos($materia, $gostos, $titulosUsados, $nivel);

        foreach ($gerados as $conteudoGerado) {
            $titulo = limparMarcacaoIA((string)($conteudoGerado['titulo'] ?? ''));
            $corpo = limparMarcacaoIA((string)($conteudoGerado['corpo'] ?? ''));

            if ($titulo === '' || $corpo === '') {
                continue;
            }

            $jaExiste = false;
            foreach ($titulosUsados as $tituloUsado) {
                if (mb_strtolower(trim($tituloUsado)) === mb_strtolower($titulo)) {
                    $jaExiste = true;
                    break;
                }
            }

            if (!$jaExiste) {
                $conteudos[] = [
                    'titulo' => $titulo,
                    'corpo' => $corpo,
                    '_ai_provider' => $conteudoGerado['_ai_provider'] ?? 'Local',
                    '_ai_model' => $conteudoGerado['_ai_model'] ?? 'fallback',
                ];
                $titulosUsados[] = $titulo;
            }

            if (count($conteudos) >= 6) {
                break 2;
            }
        }
    }

    if (count($conteudos) < 6) {
        throw new Exception('A IA gerou menos de 6 conteudos novos. Tente novamente.');
    }

    return array_slice($conteudos, 0, 6);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'gerar_mais') {
    validarCsrf();
    try {
        $titulosExistentes = array_column($conteudos, 'titulo');
        $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(dificuldade), 0), COALESCE(MAX(ordem), 0) FROM conteudos WHERE materia_id = ? AND user_id = ?");
        $stmtMax->execute([$materiaId, $usuario['id']]);
        [$maiorDificuldade, $maiorOrdem] = array_map('intval', $stmtMax->fetch(PDO::FETCH_NUM));
        $proximoNivel = $maiorDificuldade + 1;

        $gerados = gerarSeisConteudos($materia['nome'], trim($usuario['gostos'] ?? ''), $titulosExistentes, $proximoNivel);
        registrarAuditoriaIA($pdo, (int)$usuario['id'], 'conteudos', $materia['nome'] . ':' . $proximoNivel);
        $salvos = salvarConteudosGerados($pdo, (int)$usuario['id'], $materiaId, $gerados, $proximoNivel, $maiorOrdem + 1, $titulosExistentes);

        if ($salvos === 0) {
            throw new Exception('A IA nao retornou conteudos novos o suficiente. Tente novamente.');
        }

        $stmt->execute([$materiaId, $usuario['id']]);
        $conteudos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $erroIA = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'apagar_livro') {
    validarCsrf();
    $conteudoApagarId = (int)($_POST['conteudo_id'] ?? 0);

    if ($conteudoApagarId > 0) {
        try {
            $stmtConferirLivro = $pdo->prepare("SELECT id FROM conteudos WHERE id = ? AND materia_id = ? AND user_id = ?");
            $stmtConferirLivro->execute([$conteudoApagarId, $materiaId, $usuario['id']]);

            if ($stmtConferirLivro->fetchColumn()) {
                $pdo->beginTransaction();

                if (tabelaExiste($pdo, 'respostas_historico')) {
                    $stmtLimparRespostas = $pdo->prepare("
                        DELETE rh FROM respostas_historico rh
                        JOIN historico h ON h.id = rh.historico_id
                        WHERE h.conteudo_id = ? AND h.user_id = ?
                    ");
                    $stmtLimparRespostas->execute([$conteudoApagarId, $usuario['id']]);
                }

                if (tabelaExiste($pdo, 'ajudas_questoes')) {
                    $stmtLimparAjudas = $pdo->prepare("
                        DELETE aq FROM ajudas_questoes aq
                        JOIN questoes q ON q.id = aq.questao_id
                        WHERE q.conteudo_id = ? AND q.user_id = ?
                    ");
                    $stmtLimparAjudas->execute([$conteudoApagarId, $usuario['id']]);
                }

                if (tabelaExiste($pdo, 'recompensas_atividades')) {
                    $stmtLimparRecompensas = $pdo->prepare("DELETE FROM recompensas_atividades WHERE conteudo_id = ? AND user_id = ?");
                    $stmtLimparRecompensas->execute([$conteudoApagarId, $usuario['id']]);
                }

                if (tabelaExiste($pdo, 'ultimos_acessos')) {
                    $stmtLimparAcessos = $pdo->prepare("DELETE FROM ultimos_acessos WHERE conteudo_id = ? AND user_id = ?");
                    $stmtLimparAcessos->execute([$conteudoApagarId, $usuario['id']]);
                }

                $stmtLimparHistorico = $pdo->prepare("DELETE FROM historico WHERE conteudo_id = ? AND user_id = ?");
                $stmtLimparHistorico->execute([$conteudoApagarId, $usuario['id']]);

                $stmtLimparQuestoes = $pdo->prepare("DELETE FROM questoes WHERE conteudo_id = ? AND user_id = ?");
                $stmtLimparQuestoes->execute([$conteudoApagarId, $usuario['id']]);

                $stmtApagar = $pdo->prepare("DELETE FROM conteudos WHERE id = ? AND materia_id = ? AND user_id = ?");
                $stmtApagar->execute([$conteudoApagarId, $materiaId, $usuario['id']]);

                $pdo->commit();
            }

            $stmt->execute([$materiaId, $usuario['id']]);
            $conteudos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erroAcao = 'Não foi possível apagar o livro agora.';
        }
    }
}

if (!$conteudos && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    try {
        $gerados = gerarSeisConteudos($materia['nome'], trim($usuario['gostos'] ?? ''), [], 1);
        registrarAuditoriaIA($pdo, (int)$usuario['id'], 'conteudos', $materia['nome'] . ':1');
        salvarConteudosGerados($pdo, (int)$usuario['id'], $materiaId, $gerados, 1, 1, []);
        $stmt->execute([$materiaId, $usuario['id']]);
        $conteudos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $erroIA = $e->getMessage();
    }
}
$totalConteudos = count($conteudos);
$rotuloConteudos = $totalConteudos === 1 ? 'livro' : 'livros';
$tituloPagina = 'Matéria';
$tituloTopbar = 'Matéria';
$paginaAtual  = 'materias';
$usaSidebar = true;
$cssPaginas = ['conteudos'];
require __DIR__ . '/includes/head.php';
?>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <?php require __DIR__ . '/includes/topbar.php'; ?>
    <section class="content-page">
        <section class="content-header" aria-label="Resumo da matéria">
            <div class="content-summary neo-panel">
                <div class="content-summary-main">
                    <h1><?= htmlspecialchars($materia['nome']) ?></h1>
                    <span class="content-count"><?= $totalConteudos ?> <?= $rotuloConteudos ?></span>
                </div>
            </div>

            <a href="materias.php" class="content-icon-btn" aria-label="Voltar para matérias" title="Voltar para matérias">
                <?= estrelaHoverNeo() ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="M15 18l-6-6 6-6"></path>
                    <path d="M9 12h10"></path>
                </svg>
            </a>
        </section>

        <section class="content-list-wrap">


            <?php if ($erroIA): ?>
                <div class="error">Não foi possível gerar os livros agora: <?= htmlspecialchars($erroIA) ?></div>
            <?php endif; ?>

            <?php if ($erroAcao): ?>
                <div class="error"><?= htmlspecialchars($erroAcao) ?></div>
            <?php endif; ?>

            <?php if (!$conteudos): ?>
                <p class="empty">Nenhum livro disponível nessa matéria ainda.</p>
            <?php endif; ?>

            <div class="content-list">
                <?php foreach ($conteudos as $i => $c): ?>
                    <article class="content-row">
                        <a class="content-row-main" href="livro.php?conteudo_id=<?= (int)$c['id'] ?>">
                            <b><?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?></b>
                            <span><?= htmlspecialchars($c['titulo']) ?></span>
                        </a>
                        <form method="post" class="content-delete-form" onsubmit="return confirm('Apagar este livro?');">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="acao" value="apagar_livro">
                            <input type="hidden" name="conteudo_id" value="<?= (int)$c['id'] ?>">
                            <button type="submit" class="content-delete-btn" aria-label="Apagar livro">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                    <path d="M4 7h16"></path>
                                    <path d="M10 11v6"></path>
                                    <path d="M14 11v6"></path>
                                    <path d="M6 7l1 13h10l1-13"></path>
                                    <path d="M9 7V4h6v3"></path>
                                </svg>
                            </button>
                        </form>
                    </article>
                <?php endforeach; ?>
                <form method="post" class="content-generate-row" data-ai-loading data-ai-message="Gerando novos livros">
                    <?= campoCsrf() ?>
                    <input type="hidden" name="acao" value="gerar_mais">
                    <button type="submit" class="content-generate-list-btn">
                        <b>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                <path d="M5 5h7a4 4 0 0 1 4 4v10H9a4 4 0 0 0-4-4V5Z"></path>
                                <path d="M16 9a4 4 0 0 1 4-4v10a4 4 0 0 0-4 4"></path>
                                <path d="M12 8v6"></path>
                                <path d="M9 11h6"></path>
                            </svg>
                        </b>
                        <span>Gerar mais livros</span>
                    </button>
                </form>
            </div>
        </section>
    </section>
</main>
</body>
</html>
