<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
exigirLogin();
$usuario = usuarioAtual($pdo);
$materias = $pdo->query("
    SELECT m.id, m.nome, COUNT(c.id) AS total
    FROM materias m
    LEFT JOIN conteudos c ON c.materia_id = m.id
    GROUP BY m.id
    ORDER BY m.nome
")->fetchAll(PDO::FETCH_ASSOC);
$tituloPagina = 'Matérias';
$paginaAtual  = 'materias';
require __DIR__ . '/includes/head.php';
?>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <header class="topbar">
        <div>
            <span class="eyebrow">NEOMIND • PLATAFORMA DE ESTUDOS</span>
            <h1>Matérias</h1>
        </div>
        <a href="config.php" class="profile"><?= htmlspecialchars(strtoupper(substr($usuario['nome'], 0, 1))) ?></a>
    </header>
    <div class="section-title">
        <span>Matérias</span>
        <small>Escolha uma área para estudar</small>
    </div>
    <div class="subject-grid">
        <?php foreach ($materias as $m): ?>
            <a class="subject" href="conteudos.php?materia_id=<?= (int)$m['id'] ?>">
                <b><?= htmlspecialchars($m['nome']) ?></b>
                <span><?= (int)$m['total'] ?> conteúdo(s)</span>
            </a>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>
