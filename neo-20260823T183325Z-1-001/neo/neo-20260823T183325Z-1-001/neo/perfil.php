<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/materia_icon.php';
require_once __DIR__ . '/services/store.php';
exigirLogin();
$usuario = usuarioAtual($pdo);
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'foto') {
    validarCsrf();
    $arquivo = $_FILES['foto'] ?? null;
    if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
        $erro = 'Escolha uma imagem para usar no perfil.';
    } else {
        $fotoAnterior = (string)($usuario['foto'] ?? '');
        $caminhoPublico = null;
        try {
            $caminhoPublico = salvarImagemUploadSeguro($arquivo, 'perfis', 'user_' . (int)$usuario['id'] . '_');
            try {
                $stmt = $pdo->prepare("UPDATE users SET foto = ? WHERE id = ?");
                $stmt->execute([$caminhoPublico, $usuario['id']]);
            } catch (Throwable $e) {
                removerImagemUpload($caminhoPublico, 'perfis');
                throw $e;
            }
            removerImagemUpload($fotoAnterior, 'perfis');
            $usuario = usuarioAtual($pdo);
        } catch (DomainException $e) {
            $erro = $e->getMessage();
        } catch (Throwable $e) {
            error_log('[NEO][perfil-foto] ' . $e->getMessage());
            $erro = 'Não foi possível salvar a foto agora.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'aplicar_item') {
    validarCsrf();
    try {
        aplicarItemCosmetico($pdo, (int)$usuario['id'], (int)($_POST['produto_id'] ?? 0));
        $usuario = usuarioAtual($pdo);
    } catch (DomainException $e) {
        $erro = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'remover_item') {
    validarCsrf();
    try {
        removerItemCosmetico($pdo, (int)$usuario['id'], (string)($_POST['categoria'] ?? ''));
        $usuario = usuarioAtual($pdo);
    } catch (DomainException $e) {
        $erro = $e->getMessage();
    }
}

$inventario = inventarioUsuario($pdo, (int)$usuario['id']);
$stmt = $pdo->prepare("
    SELECT m.nome, pm.nivel, pm.xp_total, pm.desempenho_recente
    FROM progresso_materias pm
    JOIN materias m ON m.id = pm.materia_id
    WHERE pm.user_id = ?
    ORDER BY pm.nivel DESC, pm.xp_total DESC, m.nome
");
$stmt->execute([$usuario['id']]);
$progressosMaterias = $stmt->fetchAll(PDO::FETCH_ASSOC);
$perfilAdaptativo = adaptiveProfile($usuario);
$progressosMaterias = array_values(array_filter(
    $progressosMaterias,
    static fn(array $progresso): bool => adaptiveSubjectAllowed($perfilAdaptativo, (string)$progresso['nome'])
));
$stmtResumoPerfil = $pdo->prepare("
    SELECT
        (SELECT COUNT(*) FROM ultimos_acessos WHERE user_id = ?) AS livros_acessados,
        (SELECT COALESCE(SUM(total), 0) FROM historico WHERE user_id = ?) AS questoes_respondidas
");
$stmtResumoPerfil->execute([$usuario['id'], $usuario['id']]);
$resumoPerfil = $stmtResumoPerfil->fetch(PDO::FETCH_ASSOC) ?: [];
$livrosAcessados = (int)($resumoPerfil['livros_acessados'] ?? 0);
$questoesRespondidas = (int)($resumoPerfil['questoes_respondidas'] ?? 0);

$xpAtual = (int)($usuario['xp'] ?? 0);
$nivel = max(1, (int)($usuario['nivel'] ?? 1));
$xpProximo = xpParaProximoNivel($nivel);
$progresso = $xpProximo > 0 ? min(100, round(($xpAtual / $xpProximo) * 100)) : 0;
$decoracaoAtual = $usuario['decoracao_perfil'] ?? '';
$nomeCompleto = trim((string)$usuario['nome'] . ' ' . (string)($usuario['sobrenome'] ?? ''));
$cosmeticosPerfil = cosmeticosAtivosUsuario($pdo, $usuario);
$decoracaoProdutoAtual = $cosmeticosPerfil['decoracao_perfil'] ?? null;
$decoracaoImagemAtual = is_array($decoracaoProdutoAtual) ? trim((string)($decoracaoProdutoAtual['imagem'] ?? '')) : '';
$decoracaoInline = '';
$rotulosGenero = [
    'feminino' => 'Feminino',
    'masculino' => 'Masculino',
    'nao_binario' => 'Não binário',
    'prefiro_nao_informar' => 'Prefiro não informar',
];
$generoPerfil = $rotulosGenero[$usuario['genero'] ?? ''] ?? 'Não informado';
$idadePerfil = !empty($usuario['idade']) ? (int)$usuario['idade'] . ' anos' : 'Não informada';
$membroDesde = !empty($usuario['criado_em']) ? date('m/Y', strtotime($usuario['criado_em'])) : 'Não informado';

$tituloPagina = 'Perfil';
$paginaAtual = 'perfil';
$usaSidebar = true;
$cssPaginas = ['perfil'];
require __DIR__ . '/includes/head.php';
?>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <?php require __DIR__ . '/includes/topbar.php'; ?>

    <div class="neo-page-shell profile-page">
        <?php if ($erro): ?>
            <div class="error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <section class="profile-summary neo-panel <?= htmlspecialchars($decoracaoAtual) ?>"<?= $decoracaoInline !== '' ? ' style="' . htmlspecialchars($decoracaoInline, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
            <div class="profile-avatar-ring<?= $decoracaoImagemAtual !== '' ? ' has-frame-art' : '' ?>">
                <div class="profile-avatar">
                    <?php if (!empty($usuario['foto'])): ?>
                        <img src="<?= htmlspecialchars($usuario['foto']) ?>" alt="" loading="lazy" decoding="async">
                    <?php else: ?>
                        <span><?= htmlspecialchars(strtoupper(substr($usuario['nome'], 0, 1))) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($decoracaoImagemAtual !== ''): ?>
                    <img class="profile-frame-art" src="<?= htmlspecialchars($decoracaoImagemAtual) ?>" alt="" loading="lazy" decoding="async">
                <?php endif; ?>
            </div>

            <div class="profile-summary-copy">
                <h1><?= htmlspecialchars($nomeCompleto) ?></h1>
                <div class="profile-level-card">
                    <div class="profile-level-top">
                        <span>Nível <?= $nivel ?> · <?= $xpAtual ?> / <?= $xpProximo ?> EXP</span>
                    </div>
                    <div class="progress neo-progress-track"><i class="neo-progress-fill" style="width: <?= $progresso ?>%;"><?= cometaProgressoNeo() ?></i></div>
                </div>
            </div>
        </section>

        <section class="profile-stats" aria-label="Estatísticas do perfil">
            <div><b><?= $livrosAcessados ?></b><span>Livros acessados</span></div>
            <div><b><?= $questoesRespondidas ?></b><span>Questões respondidas</span></div>
            <div><b><?= $nivel ?></b><span>Nível atual</span></div>
            <div><b><?= $xpAtual ?></b><span>EXP no nível</span></div>
        </section>

        <section class="profile-account neo-panel">
            <div class="neo-section-heading">
                <div>
                    <span class="neo-page-kicker">Com a sua cara</span>
                    <h2>O que o NEO aprendeu sobre você</h2>
                    <p>Confira seu mapa de conhecimento, revisões, rotina e preferências.</p>
                </div>
            </div>
            <a class="primary neo-star-hover" href="aprendizado.php" data-manel-tip="Abre seu mapa de aprendizado, rotina e progresso."><?= estrelaHoverNeo() ?>Meu aprendizado</a>
            <a class="ghost neo-star-hover" href="register.php?editar=1" data-manel-tip="Atualiza suas preferências para o NEO adaptar melhor os estudos."><?= estrelaHoverNeo() ?>Editar personalização</a>
        </section>

        <section class="profile-account neo-panel">
            <div class="neo-section-heading">
                <div>
                    <span class="neo-page-kicker">Conta</span>
                    <h2>Informações principais</h2>
                </div>
                <a href="config.php" class="neo-icon-button neo-star-hover" aria-label="Abrir configurações" title="Configurações">
                    <?= estrelaHoverNeo() ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 13a7.6 7.6 0 0 0 0-2l2-1.5-2-3.4-2.3 1a7.4 7.4 0 0 0-1.7-1L15 3.6h-4l-.4 2.5a7.4 7.4 0 0 0-1.7 1l-2.3-1-2 3.4L6.6 11a7.6 7.6 0 0 0 0 2l-2 1.5 2 3.4 2.3-1c.5.4 1.1.75 1.7 1l.4 2.6h4l.4-2.5c.6-.25 1.2-.6 1.7-1l2.3 1 2-3.4-2-1.6Z"></path></svg>
                </a>
            </div>

            <div class="profile-info-grid">
                <div><span>Email</span><b><?= htmlspecialchars($usuario['email']) ?></b></div>
                <div><span>Idade</span><b><?= htmlspecialchars($idadePerfil) ?></b></div>
                <div><span>Gênero</span><b><?= htmlspecialchars($generoPerfil) ?></b></div>
                <div><span>Membro desde</span><b><?= htmlspecialchars($membroDesde) ?></b></div>
            </div>

            <form method="post" enctype="multipart/form-data" class="photo-form">
                <?= campoCsrf() ?>
                <input type="hidden" name="acao" value="foto">
                <label for="foto">Trocar foto de perfil</label>
                <div class="photo-input-row">
                    <input type="file" id="foto" name="foto" accept="image/png,image/jpeg,image/webp,image/gif" required>
                    <button type="submit" class="primary neo-star-hover">
                        <?= estrelaHoverNeo() ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4"></path><path d="m7 9 5-5 5 5"></path><path d="M5 20h14"></path></svg>
                        Salvar foto
                    </button>
                </div>
            </form>
        </section>

        <section class="profile-inventory neo-panel">
            <div class="neo-section-heading">
                <div>
                    <span class="neo-page-kicker">Aparência</span>
                    <h2>Inventário</h2>
                </div>
                <a href="loja.php" class="ghost neo-star-hover"><?= estrelaHoverNeo() ?>Abrir loja</a>
            </div>

            <?php if (!$inventario): ?>
                <div class="profile-inline-empty">
                    <span>Você ainda não possui itens cosméticos.</span>
                    <a href="loja.php" class="ghost neo-star-hover"><?= estrelaHoverNeo() ?>Ver itens</a>
                </div>
            <?php else: ?>
                <div class="inventory-grid">
                    <?php foreach ($inventario as $item): ?>
                        <?php
                        $itemDados = metadadosProduto($item);
                        $itemEmUso = produtoEquipadoPorUsuario($usuario, $item);
                        $itemEquipavel = produtoEquipavel((string)$item['categoria']);
                        $accentItem = $item['categoria'] === 'decoracao_perfil' ? '' : valorCssSeguro($itemDados['accent'] ?? ($itemDados['manel_color'] ?? ($itemDados['name_color'] ?? '')));
                        $accent2Item = $item['categoria'] === 'decoracao_perfil' ? '' : valorCssSeguro($itemDados['accent2'] ?? '');
                        $classeVarianteManelItem = $item['categoria'] === 'skin_manel' ? varianteManelClasse($itemDados['manel_variant'] ?? '') : '';
                        $styleItem = trim(($accentItem !== '' ? '--store-accent: ' . $accentItem . '; --profile-accent: ' . $accentItem . '; ' : '') . ($accent2Item !== '' ? '--profile-accent-2: ' . $accent2Item . '; ' : ''));
                        ?>
                        <article class="inventory-item neo-star-hover <?= htmlspecialchars($item['classe_visual'] ?? '') ?>"<?= $styleItem !== '' ? ' style="' . htmlspecialchars($styleItem, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                            <?= estrelaHoverNeo() ?>
                            <div class="inventory-preview<?= $item['categoria'] === 'decoracao_perfil' ? ' inventory-frame-stage' : '' ?> <?= htmlspecialchars($item['codigo'] ?? '') ?>">
                                <?php if ($item['categoria'] === 'decoracao_perfil' && !empty($item['imagem'])): ?>
                                    <img class="inventory-frame-preview" src="<?= htmlspecialchars($item['imagem']) ?>" alt="<?= htmlspecialchars($item['nome']) ?>" loading="lazy" decoding="async">
                                <?php elseif (!empty($item['imagem'])): ?>
                                    <img src="<?= htmlspecialchars($item['imagem']) ?>" alt="" loading="lazy" decoding="async">
                                <?php elseif ($item['categoria'] === 'tema_site'): ?>
                                    <div class="store-theme-demo">
                                        <span></span><i></i><b></b>
                                    </div>
                                <?php elseif ($item['categoria'] === 'skin_manel'): ?>
                                    <div class="store-manel-demo <?= htmlspecialchars($classeVarianteManelItem) ?>" aria-hidden="true">
                                        <svg viewBox="0 0 300 220" focusable="false">
                                            <?= decoracoesRostoManelSvg() ?>
                                            <rect x="95" y="70" width="30" height="60" rx="15" ry="15"></rect>
                                            <rect x="175" y="70" width="30" height="60" rx="15" ry="15"></rect>
                                            <path d="M 128 147 Q 150 166 172 147"></path>
                                        </svg>
                                    </div>
                                <?php elseif ($item['categoria'] === 'cor_nome'): ?>
                                    <div class="store-name-demo">NEO</div>
                                <?php else: ?>
                                    <div class="inventory-avatar-demo">
                                        <?php if (!empty($usuario['foto'])): ?>
                                            <img src="<?= htmlspecialchars($usuario['foto']) ?>" alt="" loading="lazy" decoding="async">
                                        <?php else: ?>
                                            <span><?= htmlspecialchars(strtoupper(substr($usuario['nome'], 0, 1))) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span><?= htmlspecialchars(rotuloCategoriaProduto($item['categoria'])) ?></span>
                            <h3><?= htmlspecialchars($item['nome']) ?></h3>
                            <?php if ($itemEquipavel): ?>
                                <form method="post">
                                    <?= campoCsrf() ?>
                                    <input type="hidden" name="acao" value="<?= $itemEmUso ? 'remover_item' : 'aplicar_item' ?>">
                                    <input type="hidden" name="produto_id" value="<?= (int)$item['id'] ?>">
                                    <?php if ($itemEmUso): ?>
                                        <input type="hidden" name="categoria" value="<?= htmlspecialchars((string)$item['categoria']) ?>">
                                    <?php endif; ?>
                                    <button type="submit" class="<?= $itemEmUso ? 'ghost' : 'primary' ?> neo-star-hover">
                                        <?= estrelaHoverNeo() ?>
                                        <?= $itemEmUso ? 'Remover' : 'Equipar' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="profile-mastery neo-panel">
            <div class="neo-section-heading">
                <div>
                    <span class="neo-page-kicker">Progresso</span>
                    <h2>Domínio por matéria</h2>
                </div>
            </div>
            <?php if (!$progressosMaterias): ?>
                <div class="profile-inline-empty">
                    <span>Responda atividades para iniciar sua progressão.</span>
                    <a href="materias.php" class="ghost neo-star-hover" data-manel-tip="Escolha uma matéria para começar seu progresso."><?= estrelaHoverNeo() ?>Começar a estudar</a>
                </div>
            <?php else: ?>
                <div class="mastery-grid">
                    <?php foreach ($progressosMaterias as $progressoMateria): ?>
                        <?php $desempenhoMateria = max(0, min(100, round((float)$progressoMateria['desempenho_recente']))); ?>
                        <article class="mastery-item <?= classeTemaMateria((string)$progressoMateria['nome']) ?>">
                            <span class="mastery-subject-icon" aria-hidden="true"><?= iconeMateriaDashboard((string)$progressoMateria['nome']) ?></span>
                            <div class="mastery-copy"><span><?= htmlspecialchars($progressoMateria['nome']) ?></span><b>Nível <?= (int)$progressoMateria['nivel'] ?></b></div>
                            <strong><?= (int)$progressoMateria['xp_total'] ?> EXP</strong>
                            <div class="progress neo-progress-track"><i class="neo-progress-fill" style="width: <?= $desempenhoMateria ?>%; background: var(--materia-cor, var(--neo-blue));"><?= cometaProgressoNeo() ?></i></div>
                            <small><?= $desempenhoMateria ?>% de desempenho recente</small>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>
