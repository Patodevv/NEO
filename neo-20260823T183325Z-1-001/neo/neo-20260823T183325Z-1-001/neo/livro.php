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
    <section class="lesson-page">
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

        <section class="reader neo-panel <?= $livroBloqueado ? 'reader-locked-state' : '' ?>">
            <?php if ($rotuloModeloLivro !== ''): ?>
                <span class="ai-provider-badge" title="Livro gerado por <?= htmlspecialchars($nomeProvedorLivro) ?>" aria-label="Livro gerado por <?= htmlspecialchars($nomeProvedorLivro) ?>">
                    <?= $rotuloModeloLivro ?>
                </span>
            <?php endif; ?>
            <?php if ($erroIA): ?>
                <div class="error"><?= htmlspecialchars($erroIA) ?></div>
            <?php endif; ?>

            <?php if ($livroBloqueado): ?>
                <div class="reader-lock">
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
                <article>
                    <?php if ($corpoLivroExibicao === ''): ?>
                        <h2>Este é um dos próximos passos do seu plano.</h2>
                        <p>O Manel vai preparar a explicação de <?= htmlspecialchars($conteudo['titulo']) ?> considerando sua fase de estudos, seu nível e o jeito de aprender que você escolheu.</p>
                    <?php endif; ?>
                    <?php foreach (preg_split('/\n{2,}/', $corpoLivroExibicao) ?: [] as $paragrafo): ?>
                        <?php $tituloSecao = blocoEhTituloLivroIA($paragrafo); ?>
                        <?php if ($tituloSecao): ?>
                            <h2><?= htmlspecialchars($paragrafo) ?></h2>
                        <?php else: ?>
                            <p><?= nl2br(htmlspecialchars($paragrafo)) ?></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </article>
                <div class="reader-actions">
                    <form method="post" data-ai-loading data-ai-message="<?= $corpoLivroExibicao === '' ? 'Preparando seu livro' : 'Preparando uma nova versão do livro' ?>"<?= $prepararNoPrimeiroAcesso ? ' data-auto-first-book' : '' ?>>
                        <?= campoCsrf() ?>
                        <input type="hidden" name="conteudo_id" value="<?= (int)$conteudo['id'] ?>">
                        <input type="hidden" name="acao" value="<?= $corpoLivroExibicao === '' ? 'preparar_primeiro_acesso' : 'gerar_livro' ?>">
                        <button type="submit" class="ghost"><?= $corpoLivroExibicao === '' ? 'Preparar meu conteúdo' : 'Gerar novo livro' ?></button>
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
</body>
</html>
