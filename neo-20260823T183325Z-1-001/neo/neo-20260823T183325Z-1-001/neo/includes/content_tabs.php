<?php
require_once __DIR__ . '/materia_icon.php';

$conteudoIdTabs = (int)($conteudo['id'] ?? 0);
$abaConteudoAtiva = $contentTabActive ?? 'livro';
$questoesGeradasTabs = !empty($questoesGeradasTabs);
$conteudoBloqueadoTabs = isset($conteudoBloqueadoTabs) ? !empty($conteudoBloqueadoTabs) : $questoesGeradasTabs;
$classeLivroTab = trim('content-tab ' . ($abaConteudoAtiva === 'livro' ? 'active ' : '') . ($conteudoBloqueadoTabs ? 'locked' : ''));
$livroIcone = '
    <svg class="content-tab-symbol" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
        <path d="M5 5h7a4 4 0 0 1 4 4v10H9a4 4 0 0 0-4-4V5Z"></path>
        <path d="M16 9a4 4 0 0 1 4-4v10a4 4 0 0 0-4 4"></path>
    </svg>
';
?>
<nav class="content-tabs" aria-label="Navegação do conteúdo">
    <?php if ($conteudoBloqueadoTabs): ?>
        <span class="<?= htmlspecialchars($classeLivroTab) ?>" title="Conteúdo bloqueado" aria-label="Conteúdo bloqueado" aria-disabled="true">
            <?= estrelaHoverNeo() ?>
            <?= $livroIcone ?>
            <span class="content-tab-lock" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                    <rect x="6.5" y="10" width="11" height="9" rx="2"></rect>
                    <path d="M8.5 10V7.6a3.5 3.5 0 0 1 7 0V10"></path>
                </svg>
            </span>
        </span>
    <?php else: ?>
        <a class="<?= htmlspecialchars($classeLivroTab) ?>" href="livro.php?conteudo_id=<?= $conteudoIdTabs ?>" title="Conteúdo do livro" aria-label="Conteúdo do livro">
            <?= estrelaHoverNeo() ?>
            <?= $livroIcone ?>
        </a>
    <?php endif; ?>
    <a class="content-tab <?= $abaConteudoAtiva === 'questoes' ? 'active' : '' ?>" href="questoes.php?conteudo_id=<?= $conteudoIdTabs ?>" title="Gerar questões" aria-label="Gerar questões">
        <?= estrelaHoverNeo() ?>
        <svg class="content-tab-symbol" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
            <path d="M8 5h9a2 2 0 0 1 2 2v12H7a2 2 0 0 1-2-2V8"></path>
            <path d="M8 5V3"></path>
            <path d="M8 11h7"></path>
            <path d="M8 15h5"></path>
            <path d="M5 8l3-3 3 3"></path>
        </svg>
    </a>
</nav>
