<?php
$manelNoCanto = empty($rostoNoPainelEstudo);
$manelSorridente = $manelNoCanto || !empty($rostoSorridenteNoPainelEstudo);
$manelBocaDescanso = $manelSorridente ? 'M 128 147 Q 150 166 172 147' : 'M 128 150 L 172 150';
$nomeManelVisual = trim((string)($nomeManelAtivo ?? 'Manel')) ?: 'Manel';
$classeVarianteManel = trim((string)($varianteManelAtiva ?? ''));
$personalidadeManel = trim((string)($personalidadeManelAtiva ?? ''));
?>
<button type="button" class="neo-companion-face<?= $classeVarianteManel !== '' ? ' ' . htmlspecialchars($classeVarianteManel, ENT_QUOTES, 'UTF-8') : '' ?><?= $manelNoCanto ? ' is-corner-smiling' : '' ?><?= $manelSorridente ? ' is-smiling' : '' ?>" data-neo-companion data-rest-mouth="<?= htmlspecialchars($manelBocaDescanso, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($nomeManelVisual) ?>, assistente do NEO" data-manel-name="<?= htmlspecialchars($nomeManelVisual, ENT_QUOTES, 'UTF-8') ?>"<?= $personalidadeManel !== '' ? ' data-manel-personality="' . htmlspecialchars($personalidadeManel, ENT_QUOTES, 'UTF-8') . '"' : '' ?> data-manel-tip="Sou o <?= htmlspecialchars($nomeManelVisual, ENT_QUOTES, 'UTF-8') ?>. Te mostro dicas rápidas quando você passa o mouse pelas opções do site." aria-expanded="false">
    <span class="neo-companion-bubble" data-neo-companion-bubble><?= htmlspecialchars($nomeManelVisual) ?></span>
    <svg class="neo-companion-svg" viewBox="0 0 300 220" aria-hidden="true" focusable="false">
        <?= decoracoesRostoManelSvg() ?>
        <g class="neo-companion-eyes">
            <rect class="neo-companion-eye neo-companion-eye-left" x="95" y="70" width="30" height="60" rx="15" ry="15"></rect>
            <rect class="neo-companion-eye neo-companion-eye-right" x="175" y="70" width="30" height="60" rx="15" ry="15"></rect>
            <path class="neo-companion-eye-wink" d="M 175 100 Q 190 84 205 100"></path>
        </g>
        <path class="neo-companion-mouth" d="<?= htmlspecialchars($manelBocaDescanso, ENT_QUOTES, 'UTF-8') ?>"></path>
    </svg>
</button>

