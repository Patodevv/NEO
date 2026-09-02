<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/services/ai.php';
exigirLogin();
$usuario = usuarioAtual($pdo);
$erroIA = '';
$livroAtualizado = false;
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
        $novoTitulo = trim($novoLivro['titulo'] ?? '');
        $novoCorpo = trim($novoLivro['corpo'] ?? '');
        if ($novoCorpo === '') {
            throw new Exception('A IA nao retornou um livro valido.');
        }
        registrarAuditoriaIA($pdo, (int)$usuario['id'], 'livro', $conteudo['materia_nome'] . ':' . $conteudo['titulo']);
        $pdo->beginTransaction();
        $stmtUpdate = $pdo->prepare("UPDATE conteudos SET titulo = ?, corpo = ?, status = ? WHERE id = ? AND user_id = ?");
        $stmtUpdate->execute([
            $novoTitulo !== '' ? $novoTitulo : $conteudo['titulo'], $novoCorpo,
            'Livro gerado pela IA', $conteudoId, $usuario['id']
        ]);
        $pdo->prepare("DELETE FROM questoes WHERE conteudo_id = ? AND user_id = ?")
            ->execute([$conteudoId, $usuario['id']]);
        $pdo->commit();
        $stmt->execute([$conteudoId, $usuario['id']]);
        $conteudo = $stmt->fetch(PDO::FETCH_ASSOC);
        $livroAtualizado = true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $erroIA = $e->getMessage();
    }
}
$tituloPagina = $conteudo['titulo'];
$paginaAtual  = 'materias';
$usaSidebar = true;
$cssPaginas = ['livro'];
require __DIR__ . '/includes/head.php';
?>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <header class="topbar">
        <div class="user-heading">
            <span class="eyebrow">NEOMIND • <?= htmlspecialchars(strtoupper($conteudo['materia_nome'])) ?></span>
            <strong><?= htmlspecialchars($usuario['nome']) ?></strong>
            <span class="page-title">Livro do conteúdo</span>
        </div>
        <a href="perfil.php" class="profile">
            <?php if (!empty($usuario['foto'])): ?>
                <img src="<?= htmlspecialchars($usuario['foto']) ?>" alt="">
            <?php else: ?>
                <?= htmlspecialchars(strtoupper(substr($usuario['nome'], 0, 1))) ?>
            <?php endif; ?>
        </a>
    </header>
    <div class="back-row">
        <a href="conteudos.php?materia_id=<?= (int)$conteudo['materia_id'] ?>" class="back">← Voltar para conteúdos</a>
    </div>
    <div class="reader">
        <?php if ($livroAtualizado): ?>
            <div class="msg-ok">✓ Novo livro gerado e salvo. As questoes antigas foram limpas para ficarem alinhadas ao novo conteudo.</div>
        <?php endif; ?>
        <?php if ($erroIA): ?>
            <div class="error">Nao foi possivel gerar um novo livro agora: <?= htmlspecialchars($erroIA) ?></div>
        <?php endif; ?>
        <div class="reader-title"><?= htmlspecialchars($conteudo['titulo']) ?></div>
        <article>
            <?php foreach (explode("\n\n", trim($conteudo['corpo'] ?? '')) as $paragrafo): ?>
                <p><?= nl2br(htmlspecialchars($paragrafo)) ?></p>
            <?php endforeach; ?>
        </article>
        <div class="action-row">
            <a href="questoes.php?conteudo_id=<?= (int)$conteudo['id'] ?>" class="primary question-btn">Responder questões →</a>

            <form method="post">
                <?= campoCsrf() ?>
                <input type="hidden" name="conteudo_id" value="<?= (int)$conteudo['id'] ?>">
                <input type="hidden" name="acao" value="gerar_livro">
                <button type="submit" class="ghost">Gerar novo livro</button>
            </form>
        </div>
    </div>
</main>
</body>
</html>
