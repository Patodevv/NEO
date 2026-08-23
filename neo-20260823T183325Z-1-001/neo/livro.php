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
    WHERE c.id = ?
");
$stmt->execute([$conteudoId]);
$conteudo = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$conteudo) {
    header('Location: materias.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'gerar_livro') {
    try {
        $novoLivro = gerarLivro(
            $conteudo['materia_nome'],
            $conteudo['titulo'],
            trim($usuario['gostos'] ?? '')
        );
        $novoTitulo = trim($novoLivro['titulo'] ?? '');
        $novoCorpo = trim($novoLivro['corpo'] ?? '');
        if ($novoCorpo === '') {
            throw new Exception('A IA nao retornou um livro valido.');
        }
        $stmtUpdate = $pdo->prepare("UPDATE conteudos SET titulo = ?, corpo = ?, status = ? WHERE id = ?");
        $stmtUpdate->execute([
            $novoTitulo !== '' ? $novoTitulo : $conteudo['titulo'],
            $novoCorpo,
            'Livro gerado pela IA',
            $conteudoId
        ]);
        $stmtDelete = $pdo->prepare("DELETE FROM questoes WHERE conteudo_id = ?");
        $stmtDelete->execute([$conteudoId]);
        $stmt->execute([$conteudoId]);
        $conteudo = $stmt->fetch(PDO::FETCH_ASSOC);
        $livroAtualizado = true;
    } catch (Exception $e) {
        $erroIA = $e->getMessage();
    }
}
$tituloPagina = $conteudo['titulo'];
$paginaAtual  = 'materias';
require __DIR__ . '/includes/head.php';
?>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <header class="topbar">
        <div>
            <span class="eyebrow">NEOMIND • <?= htmlspecialchars(strtoupper($conteudo['materia_nome'])) ?></span>
            <h1>Livro do conteúdo</h1>
        </div>
        <a href="config.php" class="profile"><?= htmlspecialchars(strtoupper(substr($usuario['nome'], 0, 1))) ?></a>
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
                <input type="hidden" name="conteudo_id" value="<?= (int)$conteudo['id'] ?>">
                <input type="hidden" name="acao" value="gerar_livro">
                <button type="submit" class="ghost">Gerar novo livro</button>
            </form>
        </div>
    </div>
</main>
</body>
</html>
