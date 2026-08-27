<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
exigirLogin();
$usuario = usuarioAtual($pdo);
$stmt = $pdo->prepare("
    SELECT h.*, c.titulo AS conteudo_titulo, m.nome AS materia_nome
    FROM historico h
    JOIN conteudos c ON c.id = h.conteudo_id
    JOIN materias m ON m.id = c.materia_id
    WHERE h.user_id = ? AND c.user_id = ?
    ORDER BY h.data DESC
");
$stmt->execute([$usuario['id'], $usuario['id']]);
$historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
$tituloPagina = 'Histórico';
$paginaAtual  = 'historico';
$usaSidebar = true;
$cssPaginas = ['historico'];
require __DIR__ . '/includes/head.php';
?>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <header class="topbar">
        <div class="user-heading">
            <span class="eyebrow">NEOMIND • PLATAFORMA DE ESTUDOS</span>
            <strong><?= htmlspecialchars($usuario['nome']) ?></strong>
            <span class="page-title">Histórico</span>
        </div>
        <a href="perfil.php" class="profile">
            <?php if (!empty($usuario['foto'])): ?>
                <img src="<?= htmlspecialchars($usuario['foto']) ?>" alt="">
            <?php else: ?>
                <?= htmlspecialchars(strtoupper(substr($usuario['nome'], 0, 1))) ?>
            <?php endif; ?>
        </a>
    </header>
    <div class="section-title">
        <span>Histórico</span>
        <small>Seu desempenho recente</small>
    </div>
    <div class="history-card">
        <?php if (!$historico): ?>
            <p class="empty">Você ainda não respondeu nenhuma questão.</p>
        <?php endif; ?>
        <?php foreach ($historico as $h): ?>
            <?php $pct = $h['total'] > 0 ? round(($h['acertos'] / $h['total']) * 100) : 0; ?>
            <div class="history-item">
                <span class="date"><?= date('d/m', strtotime($h['data'])) ?></span>
                <div>
                    <b><?= htmlspecialchars($h['conteudo_titulo']) ?></b>
                    <small><?= htmlspecialchars($h['materia_nome']) ?> • <?= (int)$h['acertos'] ?>/<?= (int)$h['total'] ?> questões</small>
                </div>
                <strong><?= $pct ?>%</strong>
            </div>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>
