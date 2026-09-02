<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
exigirLogin();
$usuario = usuarioAtual($pdo);
$stmtMaterias = $pdo->prepare("
    SELECT m.id, m.nome, COUNT(c.id) AS total,
           COALESCE(pm.nivel, 1) AS nivel_materia,
           COALESCE(pm.xp_total, 0) AS xp_materia
    FROM materias m
    LEFT JOIN conteudos c ON c.materia_id = m.id AND c.user_id = ?
    LEFT JOIN progresso_materias pm ON pm.materia_id = m.id AND pm.user_id = ?
    GROUP BY m.id, m.nome, pm.nivel, pm.xp_total
    ORDER BY m.nome
");
$stmtMaterias->execute([$usuario['id'], $usuario['id']]);
$materias = $stmtMaterias->fetchAll(PDO::FETCH_ASSOC);
$tituloPagina = 'Matérias';
$paginaAtual  = 'materias';
$usaSidebar = true;
$cssPaginas = ['materias'];
require __DIR__ . '/includes/head.php';
?>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <header class="topbar">
        <div class="user-heading">
            <span class="eyebrow">NEOMIND • PLATAFORMA DE ESTUDOS</span>
            <strong><?= htmlspecialchars($usuario['nome']) ?></strong>
            <span class="page-title">Matérias</span>
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
        <span>Matérias</span>
        <small>Escolha uma área para estudar</small>
    </div>
    <div class="subject-grid">
        <?php foreach ($materias as $m): ?>
            <a class="subject" href="conteudos.php?materia_id=<?= (int)$m['id'] ?>">
                <b><?= htmlspecialchars($m['nome']) ?></b>
                <span><?= (int)$m['total'] ?> conteúdo(s) • Nível <?= (int)$m['nivel_materia'] ?> • <?= (int)$m['xp_materia'] ?> EXP</span>
            </a>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>
