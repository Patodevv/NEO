<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/content_lock.php';
require __DIR__ . '/includes/materia_icon.php';
require __DIR__ . '/services/ai.php';
exigirLogin();
$usuario = usuarioAtual($pdo);
$erroIA = '';
$conteudoId = (int)($_GET['conteudo_id'] ?? ($_POST['conteudo_id'] ?? 0));
$stmt = $pdo->prepare("
    SELECT c.*, m.nome AS materia_nome, m.id AS materia_id
    FROM conteudos c
    JOIN materias m ON m.id = c.materia_id
    WHERE c.id = ? AND c.user_id = ? AND c.removido_em IS NULL
");
$stmt->execute([$conteudoId, $usuario['id']]);
$conteudo = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$conteudo) {
    header('Location: materias.php');
    exit;
}
$acaoLivro = (string)($_POST['acao'] ?? '');
$acoesGeracaoLivro = ['preparar_primeiro_acesso', 'gerar_livro'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($acaoLivro, $acoesGeracaoLivro, true)) {
        http_response_code(400);
        exit('Ação inválida.');
    }
    validarCsrf();
}
$pdo->prepare("UPDATE conteudos SET status = 'Em andamento' WHERE id = ? AND user_id = ? AND removido_em IS NULL AND status <> 'Concluído'")
    ->execute([$conteudoId, $usuario['id']]);
$conteudo['status'] = $conteudo['status'] === 'Concluído' ? 'Concluído' : 'Em andamento';
$stmtAcesso = $pdo->prepare("
    INSERT INTO ultimos_acessos (user_id, conteudo_id, materia_id)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE acessado_em = CURRENT_TIMESTAMP, materia_id = VALUES(materia_id)
");
$stmtAcesso->execute([$usuario['id'], $conteudo['id'], $conteudo['materia_id']]);
$stmtLimparAcessos = $pdo->prepare("
    DELETE FROM ultimos_acessos
    WHERE user_id = ?
    AND id NOT IN (
        SELECT id FROM (
            SELECT id
            FROM ultimos_acessos
            WHERE user_id = ?
            ORDER BY acessado_em DESC
            LIMIT 9
        ) acessos_recentes
    )
");
$stmtLimparAcessos->execute([$usuario['id'], $usuario['id']]);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($acaoLivro, $acoesGeracaoLivro, true)) {
    $forcarNovaVersao = $acaoLivro === 'gerar_livro';
    try {
        $corpoAtual = normalizarCorpoLivroIA((string)($conteudo['corpo'] ?? ''), (string)$conteudo['titulo']);
        $livroJaPreparado = $corpoAtual !== '' && !textoParecePromptOuMoldeIA($corpoAtual);

        if (!$forcarNovaVersao && $livroJaPreparado) {
            header('Location: livro.php?conteudo_id=' . $conteudoId);
            exit;
        }

        $nivelAdaptativo = adaptiveDifficulty($pdo, (int)$usuario['id'], $conteudoId);
        executarOperacaoControladaIA(
            $pdo,
            (int)$usuario['id'],
            'livro',
            $conteudo['materia_nome'] . ':' . $conteudo['titulo'],
            static function () use ($pdo, $usuario, $conteudo, $conteudoId, $nivelAdaptativo, $forcarNovaVersao): void {
                $stmtAtual = $pdo->prepare("SELECT titulo, corpo FROM conteudos WHERE id = ? AND user_id = ? AND removido_em IS NULL LIMIT 1");
                $stmtAtual->execute([$conteudoId, $usuario['id']]);
                $conteudoAtual = $stmtAtual->fetch(PDO::FETCH_ASSOC);
                if (!$conteudoAtual) {
                    throw new DomainException('Este livro não está mais disponível.');
                }

                $corpoMaisRecente = normalizarCorpoLivroIA((string)($conteudoAtual['corpo'] ?? ''), (string)$conteudoAtual['titulo']);
                if (!$forcarNovaVersao && $corpoMaisRecente !== '' && !textoParecePromptOuMoldeIA($corpoMaisRecente)) {
                    return;
                }

                $novoLivro = gerarLivro(
                    $conteudo['materia_nome'],
                    (string)$conteudoAtual['titulo'],
                    trim($usuario['gostos'] ?? ''),
                    $nivelAdaptativo
                );
                $novoTitulo = $forcarNovaVersao
                    ? limparMarcacaoIA((string)($novoLivro['titulo'] ?? ''))
                    : limparMarcacaoIA((string)$conteudoAtual['titulo']);
                $novoCorpo = normalizarCorpoLivroIA((string)($novoLivro['corpo'] ?? ''), $novoTitulo);
                if ($novoCorpo === '' || problemasTextoEducacional($novoCorpo, 350)) {
                    throw new RuntimeException('Erro no sistema, tente novamente mais tarde.');
                }
                registrarAuditoriaIA($pdo, (int)$usuario['id'], 'livro', $conteudo['materia_nome'] . ':' . $conteudo['titulo']);

                $pdo->beginTransaction();
                try {
                    $stmtUpdate = $pdo->prepare("UPDATE conteudos SET titulo = ?, corpo = ?, ai_provider = ?, ai_model = ?, status = ? WHERE id = ? AND user_id = ? AND removido_em IS NULL");
                    $stmtUpdate->execute([
                        $novoTitulo !== '' ? $novoTitulo : $conteudo['titulo'],
                        $novoCorpo,
                        trim((string)($novoLivro['_ai_provider'] ?? 'Local')),
                        trim((string)($novoLivro['_ai_model'] ?? 'fallback')),
                        'Livro gerado pela IA',
                        $conteudoId,
                        $usuario['id']
                    ]);
                    if ($stmtUpdate->rowCount() !== 1) {
                        throw new DomainException('Este livro não está mais disponível.');
                    }
                    $pdo->prepare("DELETE FROM questoes WHERE conteudo_id = ? AND user_id = ?")
                        ->execute([$conteudoId, $usuario['id']]);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
            },
            'conteudo:' . $conteudoId
        );
        header('Location: livro.php?conteudo_id=' . $conteudoId, true, 303);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[NEO][livro][' . $conteudoId . '] ' . $e->getMessage());
        $erroIA = 'Erro no sistema, tente novamente mais tarde.';
    }
}
$stmtQuestoesLivro = $pdo->prepare("SELECT id, correta FROM questoes WHERE conteudo_id = ? AND user_id = ? ORDER BY id");
$stmtQuestoesLivro->execute([$conteudoId, $usuario['id']]);
$questoesLivro = $stmtQuestoesLivro->fetchAll(PDO::FETCH_ASSOC);
$questoesGeradasLivro = count($questoesLivro) > 0;
$atividadeLivroConcluida = atividadeAtualConcluida($pdo, (int)$usuario['id'], $conteudoId, $questoesLivro);
$livroBloqueado = $questoesGeradasLivro && !$atividadeLivroConcluida;
$provedorLivro = (string)($conteudo['ai_provider'] ?? '');
$rotuloModeloLivro = siglaProvedorIA($provedorLivro);
$nomeProvedorLivro = nomeProvedorIA($provedorLivro);
$corpoLivroExibicao = normalizarCorpoLivroIA((string)($conteudo['corpo'] ?? ''), (string)$conteudo['titulo']);
if ($corpoLivroExibicao !== '' && textoParecePromptOuMoldeIA($corpoLivroExibicao)) {
    $corpoLivroExibicao = '';
    $erroIA = 'Erro no sistema, tente novamente mais tarde.';
    error_log('[NEO][livro] Conteúdo com linguagem interna bloqueado na exibição: ' . $conteudoId);
}

/**
 * Converte as seções do texto didático em páginas independentes do leitor.
 * Livros antigos sem títulos também recebem uma paginação previsível.
 */
function montarPaginasLivroNeo(string $corpo): array
{
    $blocos = array_values(array_filter(
        array_map('trim', preg_split('/\n{2,}/u', $corpo) ?: []),
        static fn(string $bloco): bool => $bloco !== ''
    ));
    if (!$blocos) {
        return [];
    }

    $paginas = [];
    $paginaAtual = ['titulo' => 'Introdução', 'blocos' => []];
    $encontrouTitulo = false;

    foreach ($blocos as $bloco) {
        if (blocoEhTituloLivroIA($bloco)) {
            if ($paginaAtual['blocos']) {
                $paginas[] = $paginaAtual;
            }
            $paginaAtual = ['titulo' => $bloco, 'blocos' => []];
            $encontrouTitulo = true;
            continue;
        }
        $paginaAtual['blocos'][] = $bloco;
    }
    if ($paginaAtual['blocos']) {
        $paginas[] = $paginaAtual;
    }

    if (!$encontrouTitulo && count($blocos) > 2) {
        $paginas = [];
        foreach (array_chunk($blocos, 2) as $indice => $grupo) {
            $paginas[] = [
                'titulo' => $indice === 0 ? 'Introdução' : 'Parte ' . ($indice + 1),
                'blocos' => $grupo,
            ];
        }
    }

    return array_values(array_filter(
        $paginas,
        static fn(array $pagina): bool => !empty($pagina['blocos'])
    ));
}

$paginasLivro = montarPaginasLivroNeo($corpoLivroExibicao);
$prepararNoPrimeiroAcesso = $_SERVER['REQUEST_METHOD'] === 'GET'
    && $corpoLivroExibicao === ''
    && $erroIA === ''
    && !$livroBloqueado;
$tituloPagina = 'Matéria';
$tituloTopbar = 'Matéria';
$paginaAtual  = 'materias';
$usaSidebar = true;
$cssPaginas = ['livro'];
require __DIR__ . '/includes/head.php';
?>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <?php require __DIR__ . '/includes/topbar.php'; ?>
    <section class="lesson-page <?= classeTemaMateria((string)$conteudo['materia_nome']) ?>">
        <section class="lesson-header">
            <div class="lesson-title-panel neo-panel">
                <h1><?= htmlspecialchars($conteudo['titulo']) ?></h1>
            </div>
            <a href="conteudos.php?materia_id=<?= (int)$conteudo['materia_id'] ?>" class="lesson-icon-btn" title="Voltar para conteúdos" aria-label="Voltar para conteúdos">
                <?= estrelaHoverNeo() ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="M15 18l-6-6 6-6"></path>
                    <path d="M9 12h10"></path>
                </svg>
            </a>
        </section>

        <?php $contentTabActive = 'livro'; $questoesGeradasTabs = $questoesGeradasLivro; $conteudoBloqueadoTabs = $livroBloqueado; require __DIR__ . '/includes/content_tabs.php'; ?>

        <section class="reader <?= $livroBloqueado ? 'reader-locked-state' : '' ?>">
            <?php if ($rotuloModeloLivro !== ''): ?>
                <span class="ai-provider-badge" title="Livro gerado por <?= htmlspecialchars($nomeProvedorLivro) ?>" aria-label="Livro gerado por <?= htmlspecialchars($nomeProvedorLivro) ?>">
                    <?= $rotuloModeloLivro ?>
                </span>
            <?php endif; ?>
            <?php if ($erroIA): ?>
                <div class="error"><?= htmlspecialchars($erroIA) ?></div>
            <?php endif; ?>

            <?php if ($livroBloqueado): ?>
                <div class="reader-lock neo-panel">
                    <span class="reader-lock-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                            <rect x="6" y="10" width="12" height="10" rx="2"></rect>
                            <path d="M8.5 10V7.5a3.5 3.5 0 0 1 7 0V10"></path>
                            <path d="M12 14v2"></path>
                        </svg>
                    </span>
                    <strong>Conteúdo bloqueado</strong>
                    <p>As questões deste livro já foram geradas. Continue pelo módulo de questões.</p>
                </div>
            <?php else: ?>
                <div class="book-reader" data-book-reader data-page-count="<?= max(1, count($paginasLivro)) ?>">
                    <div class="book-shell">
                        <span class="book-spine" aria-hidden="true"></span>
                        <div class="book-stage" data-book-stage aria-live="polite">
                            <div class="book-spread" data-book-spread>
                                <section class="book-sheet book-sheet-left" data-book-left role="button" tabindex="0" aria-label="Voltar páginas" data-manel-tip="Volta para as páginas anteriores deste livro."></section>
                                <section class="book-sheet book-sheet-right" data-book-right role="button" tabindex="0" aria-label="Avançar páginas" data-manel-tip="Avança para as próximas páginas deste livro."></section>
                                <canvas class="book-page-canvas" data-book-canvas aria-hidden="true" hidden></canvas>
                                <span class="book-gutter" aria-hidden="true"></span>
                            </div>

                            <div class="book-page-sources" data-book-sources hidden aria-hidden="true">
                            <?php if (!$paginasLivro): ?>
                                <article class="book-source-page" data-book-page data-page-index="0">
                                    <div class="book-page-inner">
                                        <header class="book-page-heading">
                                            <span>Preparando livro</span>
                                            <h2>Este é um dos próximos passos do seu plano.</h2>
                                        </header>
                                        <div class="book-page-content">
                                            <p>O Manel vai preparar a explicação de <?= htmlspecialchars($conteudo['titulo']) ?> considerando sua fase de estudos, seu nível e o jeito de aprender que você escolheu.</p>
                                        </div>
                                    </div>
                                </article>
                            <?php else: ?>
                                <?php foreach ($paginasLivro as $indicePagina => $pagina): ?>
                                    <article class="book-source-page" data-book-page data-page-index="<?= $indicePagina ?>">
                                        <div class="book-page-inner">
                                            <header class="book-page-heading">
                                                <span>Tópico <?= $indicePagina + 1 ?></span>
                                                <h2><?= htmlspecialchars((string)$pagina['titulo']) ?></h2>
                                            </header>
                                            <div class="book-page-content">
                                                <?php foreach ($pagina['blocos'] as $bloco): ?>
                                                    <?php
                                                        $linhasBloco = preg_split('/\n/u', trim((string)$bloco)) ?: [];
                                                        $somenteTopicos = !empty($linhasBloco);
                                                        foreach ($linhasBloco as $linhaBloco) {
                                                            if (!preg_match('/^\s*•\s*(.+)$/u', $linhaBloco)) {
                                                                $somenteTopicos = false;
                                                                break;
                                                            }
                                                        }
                                                    ?>
                                                    <?php if ($somenteTopicos): ?>
                                                        <ul>
                                                            <?php foreach ($linhasBloco as $linhaBloco): ?>
                                                                <li><?= htmlspecialchars(preg_replace('/^\s*•\s*/u', '', $linhaBloco) ?? $linhaBloco) ?></li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php else: ?>
                                                        <p><?= nl2br(htmlspecialchars((string)$bloco)) ?></p>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                            <span class="book-page-number" aria-hidden="true"><?= $indicePagina + 1 ?></span>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </div>
                        </div>

                        <nav class="book-navigation" aria-label="Paginação do livro">
                            <button type="button" class="book-nav-button neo-star-hover" data-book-prev disabled aria-label="Página anterior" data-manel-tip="Volta para as páginas anteriores.">
                                <?= estrelaHoverNeo() ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"></path></svg>
                                <span>Anterior</span>
                            </button>
                            <div class="book-progress" aria-label="Progresso da leitura">
                                <span><strong data-book-current>1</strong> / <span data-book-total><?= max(1, count($paginasLivro)) ?></span></span>
                                <span class="book-progress-track" aria-hidden="true"><i data-book-progress></i></span>
                            </div>
                            <button type="button" class="book-nav-button neo-star-hover" data-book-next<?= count($paginasLivro) <= 2 ? ' disabled' : '' ?> aria-label="Próximas páginas" data-manel-tip="Avança para as próximas páginas.">
                                <?= estrelaHoverNeo() ?>
                                <span>Próxima</span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"></path></svg>
                            </button>
                        </nav>
                    </div>
                </div>
                <div class="reader-actions">
                    <form method="post" data-ai-loading data-ai-message="<?= $corpoLivroExibicao === '' ? 'Preparando seu livro' : 'Preparando uma nova versão do livro' ?>"<?= $prepararNoPrimeiroAcesso ? ' data-auto-first-book' : '' ?>>
                        <?= campoCsrf() ?>
                        <input type="hidden" name="conteudo_id" value="<?= (int)$conteudo['id'] ?>">
                        <input type="hidden" name="acao" value="<?= $corpoLivroExibicao === '' ? 'preparar_primeiro_acesso' : 'gerar_livro' ?>">
                        <button type="submit" class="ghost neo-star-hover"><?= estrelaHoverNeo() ?><?= $corpoLivroExibicao === '' ? 'Preparar meu conteúdo' : 'Gerar novo livro' ?></button>
                    </form>
                </div>
            <?php endif; ?>
        </section>
    </section>
</main>
<?php if ($prepararNoPrimeiroAcesso): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('form[data-auto-first-book]');
        if (!form || form.dataset.autoFirstBookSubmitted === '1') return;
        form.dataset.autoFirstBookSubmitted = '1';
        form.requestSubmit();
    }, { once: true });
</script>
<?php endif; ?>
<script src="static/pages/livro.js?v=<?= filemtime(__DIR__ . '/static/pages/livro.js') ?>" defer></script>
</body>
</html>
