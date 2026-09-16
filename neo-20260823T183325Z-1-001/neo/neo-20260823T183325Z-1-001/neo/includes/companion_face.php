<?php
$manelNoCanto = empty($rostoNoPainelEstudo);
$manelSorridente = $manelNoCanto || !empty($rostoSorridenteNoPainelEstudo);
$manelBocaDescanso = $manelSorridente ? 'M 128 147 Q 150 166 172 147' : 'M 128 150 L 172 150';
?>
<button type="button" class="neo-companion-face<?= $manelNoCanto ? ' is-corner-smiling' : '' ?><?= $manelSorridente ? ' is-smiling' : '' ?>" data-neo-companion data-rest-mouth="<?= htmlspecialchars($manelBocaDescanso, ENT_QUOTES, 'UTF-8') ?>" aria-label="Abrir Manel" aria-expanded="false" title="Manel">
    <span class="neo-companion-bubble" data-neo-companion-bubble>Manel</span>
    <svg class="neo-companion-svg" viewBox="0 0 300 220" aria-hidden="true" focusable="false">
        <g class="neo-companion-eyes">
            <rect class="neo-companion-eye neo-companion-eye-left" x="95" y="70" width="30" height="60" rx="15" ry="15"></rect>
            <rect class="neo-companion-eye neo-companion-eye-right" x="175" y="70" width="30" height="60" rx="15" ry="15"></rect>
            <path class="neo-companion-eye-wink" d="M 175 100 Q 190 84 205 100"></path>
        </g>
        <path class="neo-companion-mouth" d="<?= htmlspecialchars($manelBocaDescanso, ENT_QUOTES, 'UTF-8') ?>"></path>
    </svg>
</button>
