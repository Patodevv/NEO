<?php
$tituloVisual = $tituloTopbar ?? $tituloPagina ?? '';
$normalizarTitulo = function (string $valor): string {
    $valor = trim($valor);
    $valor = function_exists('mb_strtolower') ? mb_strtolower($valor, 'UTF-8') : strtolower($valor);
    $trocas = [
        'á' => 'a',
        'à' => 'a',
        'ã' => 'a',
        'â' => 'a',
        'é' => 'e',
        'ê' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ú' => 'u',
        'ç' => 'c'
    ];
    $valor = strtr($valor, $trocas);
    $valor = preg_replace('/[^a-z0-9]+/', '-', $valor);
    return trim($valor, '-');
};
$titulosImagem = [
    'inicio' => 'tittles/ready/inicio-removebg-preview.png',
    'materia' => 'tittles/ready/materias-removebg-preview.png',
    'materias' => 'tittles/ready/materias-removebg-preview.png',
    'historico' => 'tittles/ready/histórico-removebg-preview.png',
    'loja' => 'tittles/ready/loja-removebg-preview.png',
    'perfil' => 'tittles/ready/perfil-removebg-preview.png',
    'config' => 'tittles/ready/config-removebg-preview.png',
    'configuracoes' => 'tittles/ready/config-removebg-preview.png'
];
$chavesTitulo = array_filter([
    $normalizarTitulo((string)$tituloVisual),
    $normalizarTitulo((string)($tituloPagina ?? ''))
]);
$imagemTitulo = null;
$versaoImagemTitulo = null;
$chaveImagemTituloAtiva = null;
foreach ($chavesTitulo as $chaveTitulo) {
    if (isset($titulosImagem[$chaveTitulo])) {
        $caminhoImagemTitulo = $titulosImagem[$chaveTitulo];
        if (is_file(__DIR__ . '/../' . $caminhoImagemTitulo)) {
            $imagemTitulo = $caminhoImagemTitulo;
            $versaoImagemTitulo = filemtime(__DIR__ . '/../' . $caminhoImagemTitulo);
            $chaveImagemTituloAtiva = $chaveTitulo;
            break;
        }
    }
}

$diasOfensivaTopbar = 0;
$metaOfensivaTopbar = isset($pdo, $usuario['id']) && function_exists('metaOfensivaSemanalUsuario')
    ? metaOfensivaSemanalUsuario($pdo, (int)$usuario['id'])
    : 3;
if (isset($pdo, $usuario['id']) && function_exists('inicioSemanaNeo') && function_exists('tabelaExiste') && tabelaExiste($pdo, 'ofensivas_semanais')) {
    $stmtOfensivaTopbar = $pdo->prepare("SELECT dias_ativos FROM ofensivas_semanais WHERE user_id = ? AND semana_inicio = ?");
    $stmtOfensivaTopbar->execute([(int)$usuario['id'], inicioSemanaNeo()->format('Y-m-d')]);
    $diasOfensivaTopbar = min($metaOfensivaTopbar, (int)($stmtOfensivaTopbar->fetchColumn() ?: 0));
}
?>
<header class="topbar">
    <div class="topbar-burst" aria-hidden="true">
        <span class="burst-trail"></span>
        <span class="burst-trail burst-trail-2"></span>
        <span class="burst-particle burst-particle-2"></span>
        <span class="burst-particle burst-particle-3"></span>
        <span class="burst-particle burst-particle-4"></span>
        <span class="burst-particle burst-particle-6"></span>
        <svg class="burst-star" viewBox="0 0 120 120" focusable="false">
            <path class="burst-star-shadow" d="M60 6 C66 34 86 54 114 60 C86 66 66 86 60 114 C54 86 34 66 6 60 C34 54 54 34 60 6 Z"></path>
            <path class="burst-star-core" d="M60 18 C65 40 80 55 102 60 C80 65 65 80 60 102 C55 80 40 65 18 60 C40 55 55 40 60 18 Z"></path>
            <path class="burst-star-center" d="M60 34 C64 48 72 56 86 60 C72 64 64 72 60 86 C56 72 48 64 34 60 C48 56 56 48 60 34 Z"></path>
        </svg>
    </div>
    <div class="topbar-side-stats" aria-label="Indicadores do usuário">
        <span class="side-stat side-stat-streak" title="Ofensiva semanal">
            <svg class="blue-flame" viewBox="0 0 64 64" aria-hidden="true" focusable="false">
                <path class="flame-outline" d="M33.5 4.5C43.5 13.2 39 25.3 51.4 31.1C60.3 35.3 62.2 47.9 54.9 56.1C48.6 63.1 37.8 63.3 27.8 59.3C16.1 54.5 8 45.1 10.5 34.4C12.4 26.5 19.3 20.4 20 10.6C28.3 15.6 31.6 23.2 30 31C38.1 25.3 38.7 14.7 33.5 4.5Z"></path>
                <path class="flame-body" d="M34.1 7.8C42.2 16.3 36.9 28.4 50.1 33.6C56.5 36.1 58.2 45 52.8 51.9C47.1 59.1 36.4 58.9 27.5 55.2C18.3 51.4 12.8 43.2 15.2 35C17.3 27.5 24.5 22.3 23.3 15.3C29.2 20.1 32.2 26.3 30.6 34.5C39.2 30.3 41.9 18.8 34.1 7.8Z"></path>
                <path class="flame-core" d="M29.2 51.5C21.8 47.7 21 39.3 27.6 34.9C32.8 31.4 38.2 32.3 41.7 28.5C42.4 35.4 37.6 39.1 33.9 41.5C28.7 44.9 30.2 49.2 35.2 51.2C33.3 52.2 31.1 52.4 29.2 51.5Z"></path>
                <path class="flame-shine" d="M23.8 44.6C19.5 37.4 23.6 29.9 29.7 24.8"></path>
                <path class="flame-ember-trail flame-ember-1" d="M17 41L19.5 43.6"></path>
                <path class="flame-ember-trail flame-ember-2" d="M25 34L22.4 37.2"></path>
                <path class="flame-ember-trail flame-ember-3" d="M46 36L42.8 39.4"></path>
                <path class="flame-ember-trail flame-ember-4" d="M37 24L39.2 27.5"></path>
                <path class="flame-ember-trail flame-ember-5" d="M52 45L48.7 47.8"></path>
                <circle class="flame-ember flame-ember-1" cx="17" cy="41" r="2.4"></circle>
                <circle class="flame-ember flame-ember-2" cx="25" cy="34" r="2.2"></circle>
                <circle class="flame-ember flame-ember-3" cx="46" cy="36" r="2.4"></circle>
                <circle class="flame-ember flame-ember-4" cx="37" cy="24" r="2"></circle>
                <circle class="flame-ember flame-ember-5" cx="52" cy="45" r="2.1"></circle>
            </svg>
            <b><?= $diasOfensivaTopbar ?>/<?= $metaOfensivaTopbar ?></b>
        </span>
    </div>
    <div class="user-heading">
        <span class="eyebrow">NEOMIND</span>
        <strong><?= htmlspecialchars($usuario['nome']) ?></strong>
        <?php if ($imagemTitulo): ?>
            <img class="page-title-image page-title-<?= htmlspecialchars($chaveImagemTituloAtiva) ?>" src="<?= htmlspecialchars(str_replace('%2F', '/', rawurlencode($imagemTitulo)) . '?v=' . $versaoImagemTitulo) ?>" alt="<?= htmlspecialchars($tituloVisual) ?>" decoding="async">
        <?php else: ?>
            <span class="page-title"><?= htmlspecialchars($tituloVisual) ?></span>
        <?php endif; ?>
    </div>
    <div class="topbar-actions">
        <button type="button" class="topbar-mobile-test neo-star-hover" data-mobile-preview-toggle aria-pressed="false" title="Simular mobile" aria-label="Simular mobile">
            <?= estrelaHoverNeo() ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <rect x="7" y="3" width="10" height="18" rx="2.2"></rect>
                <path d="M10.5 18h3"></path>
            </svg>
        </button>
        <a href="loja.php" class="cossas-pill"><span class="coin"></span><span class="cossas-pill-value"><?= saldoCossasVisual($usuario) ?></span></a>
        <a href="perfil.php" class="profile">
        <?php if (!empty($usuario['foto'])): ?>
            <img src="<?= htmlspecialchars($usuario['foto']) ?>" alt="" decoding="async">
        <?php else: ?>
            <?= htmlspecialchars(strtoupper(substr($usuario['nome'], 0, 1))) ?>
        <?php endif; ?>
        </a>
    </div>
</header>
