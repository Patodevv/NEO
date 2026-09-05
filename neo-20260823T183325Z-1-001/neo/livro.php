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
    WHERE c.id = ? AND c.user_id = ?
");
$stmt->execute([$conteudoId, $usuario['id']]);
$conteudo = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$conteudo) {
    header('Location: materias.php');
    exit;
}
$pdo->prepare("UPDATE conteudos SET status = 'Em andamento' WHERE id = ? AND user_id = ? AND status <> 'Concluído'")
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'gerar_livro') {
    validarCsrf();
    try {
        $nivelAdaptativo = dificuldadeAdaptativa($pdo, (int)$usuario['id'], (int)$conteudo['materia_id']);
        $novoLivro = gerarLivro(
            $conteudo['materia_nome'],
            $conteudo['titulo'],
            trim($usuario['gostos'] ?? ''),
            $nivelAdaptativo
        );
        $novoTitulo = limparMarcacaoIA((string)($novoLivro['titulo'] ?? ''));
        $novoCorpo = limparMarcacaoIA((string)($novoLivro['corpo'] ?? ''));
        if ($novoCorpo === '') {
            throw new Exception('A IA nao retornou um livro valido.');
        }
        registrarAuditoriaIA($pdo, (int)$usuario['id'], 'livro', $conteudo['materia_nome'] . ':' . $conteudo['titulo']);
        $pdo->beginTransaction();
        $stmtUpdate = $pdo->prepare("UPDATE conteudos SET titulo = ?, corpo = ?, ai_provider = ?, ai_model = ?, status = ? WHERE id = ? AND user_id = ?");
        $stmtUpdate->execute([
            $novoTitulo !== '' ? $novoTitulo : $conteudo['titulo'],
            $novoCorpo,
            trim((string)($novoLivro['_ai_provider'] ?? 'Local')),
            trim((string)($novoLivro['_ai_model'] ?? 'fallback')),
            'Livro gerado pela IA',
            $conteudoId,
            $usuario['id']
        ]);
        $stmtDelete = $pdo->prepare("DELETE FROM questoes WHERE conteudo_id = ? AND user_id = ?");
        $stmtDelete->execute([$conteudoId, $usuario['id']]);
        $pdo->commit();
        $stmt->execute([$conteudoId, $usuario['id']]);
        $conteudo = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $erroIA = $e->getMessage();
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
$corpoLivroExibicao = limparMarcacaoIA((string)($conteudo['corpo'] ?? ''));
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
                <div class="error">Não foi possível gerar um novo livro agora: <?= htmlspecialchars($erroIA) ?></div>
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
                    <?php foreach (preg_split('/\n{2,}/', $corpoLivroExibicao) ?: [] as $paragrafo): ?>
                        <p><?= nl2br(htmlspecialchars($paragrafo)) ?></p>
                    <?php endforeach; ?>
                </article>
                <div class="reader-actions">
                    <form method="post" data-ai-loading data-ai-message="Preparando uma nova versão do livro">
                        <?= campoCsrf() ?>
                        <input type="hidden" name="conteudo_id" value="<?= (int)$conteudo['id'] ?>">
                        <input type="hidden" name="acao" value="gerar_livro">
                        <button type="submit" class="ghost">Gerar novo livro</button>
                    </form>
                </div>
            <?php endif; ?>
        </section>
    </section>
</main>
</body>
</html>
