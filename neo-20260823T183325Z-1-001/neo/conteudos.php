<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/services/ai.php';
exigirLogin();
$usuario = usuarioAtual($pdo);
$erroIA = '';
$conteudosGerados = false;
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
    $stmtInsert = $pdo->prepare("INSERT INTO conteudos (user_id, materia_id, titulo, status, corpo, dificuldade, ordem) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $salvos = 0;
    $titulosNormalizados = [];

    foreach ($titulosExistentes as $tituloExistente) {
        $titulosNormalizados[mb_strtolower(trim($tituloExistente))] = true;
    }

    foreach ($gerados as $conteudoGerado) {
        $titulo = trim($conteudoGerado['titulo'] ?? '');
        $corpo = trim($conteudoGerado['corpo'] ?? '');
        $normalizado = mb_strtolower($titulo);

        if ($titulo !== '' && $corpo !== '' && empty($titulosNormalizados[$normalizado])) {
            $stmtInsert->execute([$userId, $materiaId, $titulo, 'Gerado pela IA', $corpo, $dificuldade, $ordemInicial + $salvos]);
            $titulosNormalizados[$normalizado] = true;
            $salvos++;
        }
    }

    return $salvos;
}

function gerarSeisConteudos(string $materia, string $gostos, array $titulosExistentes, int $nivel): array
{
    $conteudos = [];
    $titulosUsados = $titulosExistentes;

    for ($tentativa = 0; $tentativa < 3 && count($conteudos) < 6; $tentativa++) {
        $gerados = gerarConteudos($materia, $gostos, $titulosUsados, $nivel);

        foreach ($gerados as $conteudoGerado) {
            $titulo = trim($conteudoGerado['titulo'] ?? '');
            $corpo = trim($conteudoGerado['corpo'] ?? '');

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
                $conteudos[] = ['titulo' => $titulo, 'corpo' => $corpo];
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
    try {
        $titulosExistentes = array_column($conteudos, 'titulo');
        $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(dificuldade), 0), COALESCE(MAX(ordem), 0) FROM conteudos WHERE materia_id = ? AND user_id = ?");
        $stmtMax->execute([$materiaId, $usuario['id']]);
        [$maiorDificuldade, $maiorOrdem] = array_map('intval', $stmtMax->fetch(PDO::FETCH_NUM));
        $proximoNivel = $maiorDificuldade + 1;

        $gerados = gerarSeisConteudos($materia['nome'], trim($usuario['gostos'] ?? ''), $titulosExistentes, $proximoNivel);
        $salvos = salvarConteudosGerados($pdo, (int)$usuario['id'], $materiaId, $gerados, $proximoNivel, $maiorOrdem + 1, $titulosExistentes);

        if ($salvos === 0) {
            throw new Exception('A IA nao retornou conteudos novos o suficiente. Tente novamente.');
        }

        $stmt->execute([$materiaId, $usuario['id']]);
        $conteudos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $conteudosGerados = true;
    } catch (Exception $e) {
        $erroIA = $e->getMessage();
    }
}
if (!$conteudos) {
    try {
        $gerados = gerarSeisConteudos($materia['nome'], trim($usuario['gostos'] ?? ''), [], 1);
        salvarConteudosGerados($pdo, (int)$usuario['id'], $materiaId, $gerados, 1, 1, []);
        $stmt->execute([$materiaId, $usuario['id']]);
        $conteudos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $erroIA = $e->getMessage();
    }
}
$tituloPagina = $materia['nome'];
$paginaAtual  = 'materias';
$usaSidebar = true;
$cssPaginas = ['conteudos'];
require __DIR__ . '/includes/head.php';
?>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <header class="topbar">
        <div class="user-heading">
            <span class="eyebrow">NEOMIND • PLATAFORMA DE ESTUDOS</span>
            <strong><?= htmlspecialchars($usuario['nome']) ?></strong>
            <span class="page-title"><?= htmlspecialchars($materia['nome']) ?></span>
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
        <a href="materias.php" class="back">← Voltar para matérias</a>
    </div>
    <div class="section-title">
        <span><?= htmlspecialchars($materia['nome']) ?></span>
        <small>Conteúdos disponíveis</small>
    </div>
    <div class="action-row content-actions">
        <form method="post">
            <input type="hidden" name="acao" value="gerar_mais">
            <button type="submit" class="primary">Gerar mais conteúdos</button>
        </form>
    </div>
    <div class="content-list">
        <?php if ($conteudosGerados): ?>
            <div class="msg-ok">✓ Mais 6 conteudos foram gerados e salvos na progressao da materia.</div>
        <?php endif; ?>

        <?php if ($erroIA): ?>
            <div class="error">Nao foi possivel gerar os conteudos agora: <?= htmlspecialchars($erroIA) ?></div>
        <?php endif; ?>

        <?php if (!$conteudos): ?>
            <p class="empty">Nenhum conteudo disponivel nessa materia ainda.</p>
        <?php endif; ?>

        <?php foreach ($conteudos as $i => $c): ?>
            <a class="content-row" href="livro.php?conteudo_id=<?= (int)$c['id'] ?>">
                <b><?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?></b>
                <span><?= htmlspecialchars($c['titulo']) ?></span>
                <em>Nível <?= (int)($c['dificuldade'] ?? 1) ?></em>
            </a>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>
