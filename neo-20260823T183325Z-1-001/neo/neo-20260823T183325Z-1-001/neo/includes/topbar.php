<?php
require_once __DIR__ . '/../services/ai_usage.php';
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
    'meu-aprendizado' => 'static/images/meu-aprendizado-title.png',
    'aprendizado' => 'static/images/meu-aprendizado-title.png',
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
$decoracaoTopbarImagem = '';
$decoracaoTopbarCodigo = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($usuario['decoracao_perfil'] ?? ''));
if (isset($cosmeticosUsuario['items']['decoracao_perfil']) && is_array($cosmeticosUsuario['items']['decoracao_perfil'])) {
    $decoracaoTopbarImagem = trim((string)($cosmeticosUsuario['items']['decoracao_perfil']['imagem'] ?? ''));
}
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
$classeTituloImagemTopbar = $chaveImagemTituloAtiva !== null ? ' user-heading-title-' . preg_replace('/[^a-z0-9_-]/', '', $chaveImagemTituloAtiva) : '';

$diasOfensivaTopbar = 0;
$metaOfensivaTopbar = isset($pdo, $usuario['id']) && function_exists('metaOfensivaSemanalUsuario')
    ? metaOfensivaSemanalUsuario($pdo, (int)$usuario['id'])
    : 3;
if (isset($pdo, $usuario['id']) && function_exists('inicioSemanaNeo') && function_exists('tabelaExiste') && tabelaExiste($pdo, 'ofensivas_semanais')) {
    $stmtOfensivaTopbar = $pdo->prepare("SELECT dias_ativos FROM ofensivas_semanais WHERE user_id = ? AND semana_inicio = ?");
    $stmtOfensivaTopbar->execute([(int)$usuario['id'], inicioSemanaNeo()->format('Y-m-d')]);
    $diasOfensivaTopbar = min($metaOfensivaTopbar, (int)($stmtOfensivaTopbar->fetchColumn() ?: 0));
}
$recargaIaTopbar = [
    'segundos' => 0,
    'livro' => 0,
    'questoes' => 0,
    'falha' => 0,
    'pronto' => false,
    'estado' => 'sem_leitura',
    'provedor_configurado' => false,
    'porcentagem' => null,
    'tokens_restantes' => null,
    'limite_tokens' => null,
    'requisicoes_restantes' => null,
    'limite_requisicoes' => null,
    'provedor' => null,
];
if (isset($pdo, $usuario['id'])) {
    try {
        $recargaIaTopbar = statusRecargaIA($pdo, (int)$usuario['id']);
    } catch (Throwable) {
        $recargaIaTopbar['estado'] = 'sem_leitura';
    }
}
$formatarTempoIaTopbar = static function (int $segundos): string {
    $segundos = max(0, $segundos);
    if ($segundos >= 3600) {
        return floor($segundos / 3600) . 'h ' . str_pad((string)floor(($segundos % 3600) / 60), 2, '0', STR_PAD_LEFT) . 'm';
    }
    if ($segundos >= 60) {
        return floor($segundos / 60) . 'm ' . str_pad((string)($segundos % 60), 2, '0', STR_PAD_LEFT) . 's';
    }
    return $segundos . 's';
};
$estadoIaTopbar = (string)($recargaIaTopbar['estado'] ?? 'sem_leitura');
$segundosIaTopbar = (int)($recargaIaTopbar['segundos'] ?? 0);
$porcentagemIaTopbar = $recargaIaTopbar['porcentagem'];
$porcentagemNumeroIa = $porcentagemIaTopbar !== null ? max(0, min(100, (int)$porcentagemIaTopbar)) : null;
$segundosCargaTotalIa = max($segundosIaTopbar, (int)($recargaIaTopbar['segundos_recarga_total'] ?? 0));
$textoProntoIa = $porcentagemNumeroIa !== null ? ($porcentagemNumeroIa . '%') : 'IA';
$textoRecargaIa = match ($estadoIaTopbar) {
    'pronto' => $textoProntoIa,
    'recarga' => $porcentagemNumeroIa !== null ? ($porcentagemNumeroIa . '%') : $formatarTempoIaTopbar($segundosIaTopbar),
    'falha' => 'erro',
    'sem_chave' => 'off',
    default => '--',
};
$classeEstadoIa = match ($estadoIaTopbar) {
    'pronto' => ' is-ready',
    'recarga' => ' is-cooling',
    'falha' => ' is-error',
    default => ' is-offline',
};
$detalheTokensIa = '';
if ($recargaIaTopbar['tokens_restantes'] !== null && $recargaIaTopbar['limite_tokens'] !== null) {
    $detalheTokensIa = ' Tokens Groq: ' . (int)$recargaIaTopbar['tokens_restantes'] . '/' . (int)$recargaIaTopbar['limite_tokens'] . ' por minuto.';
}
$detalheRequisicoesIa = '';
if ($recargaIaTopbar['requisicoes_restantes'] !== null && $recargaIaTopbar['limite_requisicoes'] !== null) {
    $detalheRequisicoesIa = ' Requisições: ' . (int)$recargaIaTopbar['requisicoes_restantes'] . '/' . (int)$recargaIaTopbar['limite_requisicoes'] . ' por dia.';
}
$tituloProntoIa = 'IA liberada.' . ($detalheTokensIa ?: ' Gere algo para atualizar a leitura real da Groq.') . $detalheRequisicoesIa;
$textoCargaIa = $porcentagemNumeroIa !== null ? (' Carga atual: ' . $porcentagemNumeroIa . '%.') : '';
$tituloRecargaIa = match ($estadoIaTopbar) {
    'pronto' => $tituloProntoIa,
    'recarga' => 'IA carregando: ' . $formatarTempoIaTopbar($segundosIaTopbar) . ' restantes.' . $textoCargaIa . $detalheTokensIa . ' Livros em ' . $formatarTempoIaTopbar((int)($recargaIaTopbar['livro'] ?? 0)) . '; questões em ' . $formatarTempoIaTopbar((int)($recargaIaTopbar['questoes'] ?? 0)) . '.',
    'falha' => 'A IA falhou recentemente. Tente novamente em ' . $formatarTempoIaTopbar(max(1, $segundosIaTopbar)) . '.',
    'sem_chave' => 'IA sem chave configurada. Verifique a chave da Groq ou OpenAI.',
    default => 'IA sem leitura recente da Groq. Use uma geração para atualizar a carga real.',
};
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
        <span class="side-stat side-stat-streak" data-manel-tip="Sua ofensiva registra <?= $diasOfensivaTopbar ?> de <?= $metaOfensivaTopbar ?> dias da meta desta semana." aria-label="Ofensiva semanal: <?= $diasOfensivaTopbar ?> de <?= $metaOfensivaTopbar ?> dias da sua meta">
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
        <span class="side-stat side-stat-ai<?= $classeEstadoIa ?>" data-ai-recharge data-ai-state="<?= htmlspecialchars($estadoIaTopbar, ENT_QUOTES, 'UTF-8') ?>" data-seconds="<?= $segundosIaTopbar ?>" data-charge-total="<?= $segundosCargaTotalIa ?>" data-percent="<?= $porcentagemNumeroIa !== null ? $porcentagemNumeroIa : '' ?>" data-ready-label="<?= htmlspecialchars($textoProntoIa, ENT_QUOTES, 'UTF-8') ?>" data-ready-title="<?= htmlspecialchars($tituloProntoIa, ENT_QUOTES, 'UTF-8') ?>" data-manel-tip="<?= htmlspecialchars($tituloRecargaIa, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($tituloRecargaIa, ENT_QUOTES, 'UTF-8') ?>" style="--ai-charge: <?= $porcentagemNumeroIa !== null ? $porcentagemNumeroIa : 0 ?>%;">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M12 2.8c.9 4.2 3.5 6.8 7.8 7.7-4.3.9-6.9 3.5-7.8 7.8-.9-4.3-3.5-6.9-7.8-7.8 4.3-.9 6.9-3.5 7.8-7.7Z"></path>
                <path d="M18.4 15.8c.4 1.7 1.4 2.7 3.1 3.1-1.7.4-2.7 1.4-3.1 3.1-.4-1.7-1.4-2.7-3.1-3.1 1.7-.4 2.7-1.4 3.1-3.1Z"></path>
            </svg>
            <b data-ai-recharge-label><?= htmlspecialchars($textoRecargaIa) ?></b>
        </span>
    </div>
    <div class="user-heading<?= htmlspecialchars($classeTituloImagemTopbar, ENT_QUOTES, 'UTF-8') ?>">
        <span class="eyebrow">NEOMIND</span>
        <strong><?= htmlspecialchars($usuario['nome']) ?></strong>
        <?php if ($imagemTitulo): ?>
            <img class="page-title-image page-title-<?= htmlspecialchars($chaveImagemTituloAtiva) ?>" src="<?= htmlspecialchars(str_replace('%2F', '/', rawurlencode($imagemTitulo)) . '?v=' . $versaoImagemTitulo) ?>" alt="<?= htmlspecialchars($tituloVisual) ?>" decoding="async">
        <?php else: ?>
            <span class="page-title"><?= htmlspecialchars($tituloVisual) ?></span>
        <?php endif; ?>
    </div>
    <div class="topbar-actions">
        <a href="loja.php" class="cossas-pill" aria-label="Abrir loja; saldo de <?= saldoCossasVisual($usuario) ?> moedas" data-manel-tip="Abre a loja. Você tem <?= saldoCossasVisual($usuario) ?> moedas."><span class="coin"></span><span class="cossas-pill-value"><?= saldoCossasVisual($usuario) ?></span></a>
        <a href="perfil.php" class="profile<?= $decoracaoTopbarImagem !== '' ? ' has-frame-art frame-' . htmlspecialchars($decoracaoTopbarCodigo, ENT_QUOTES, 'UTF-8') : '' ?>" aria-label="Abrir meu perfil" data-manel-tip="Abre seu perfil, nível e conquistas.">
            <span class="topbar-profile-photo">
                <?php if (!empty($usuario['foto'])): ?>
                    <img src="<?= htmlspecialchars($usuario['foto']) ?>" alt="" decoding="async">
                <?php else: ?>
                    <?= htmlspecialchars(strtoupper(substr($usuario['nome'], 0, 1))) ?>
                <?php endif; ?>
            </span>
            <?php if ($decoracaoTopbarImagem !== ''): ?>
                <img class="profile-frame-art" src="<?= htmlspecialchars($decoracaoTopbarImagem) ?>" alt="" decoding="async">
            <?php endif; ?>
        </a>
    </div>
</header>
<script>
(() => {
    const chip = document.querySelector('[data-ai-recharge]');
    if (!chip) return;
    const label = chip.querySelector('[data-ai-recharge-label]');
    let seconds = Math.max(0, Number.parseInt(chip.dataset.seconds || '0', 10) || 0);
    let initialSeconds = Math.max(1, seconds || 1);
    let state = chip.dataset.aiState || 'sem_leitura';
    let percent = chip.dataset.percent === '' ? NaN : Number.parseFloat(chip.dataset.percent || '0');
    let readyLabel = chip.dataset.readyLabel || 'IA';
    let readyTitle = chip.dataset.readyTitle || 'IA liberada.';
    let stateLabel = label ? label.textContent.trim() : readyLabel;
    const storageKey = 'neo-ai-battery-state';
    const statusUrl = new URL('ai_status.php', window.location.href).toString();
    const clampPercent = (value) => Math.max(0, Math.min(100, value));
    if (state !== 'sem_leitura' && state !== 'recarga' && state !== 'falha') {
        try { window.localStorage.removeItem(storageKey); } catch (error) {}
    }
    const format = (value) => {
        if (value <= 0) return readyLabel;
        if (value >= 3600) {
            const hours = Math.floor(value / 3600);
            const minutes = Math.floor((value % 3600) / 60);
            return `${hours}h ${String(minutes).padStart(2, '0')}m`;
        }
        const minutes = Math.floor(value / 60);
        const rest = value % 60;
        return minutes > 0 ? `${minutes}m ${String(rest).padStart(2, '0')}s` : `${rest}s`;
    };
    try {
        const cached = JSON.parse(window.localStorage.getItem(storageKey) || 'null');
        const now = Date.now();
        if (cached && cached.until > now && state === 'sem_leitura') {
            state = cached.state || state;
            seconds = Math.max(0, Math.ceil((cached.until - now) / 1000));
            initialSeconds = Math.max(1, cached.initialSeconds || seconds);
            percent = Number.isFinite(cached.percent) ? cached.percent : percent;
        }
    } catch (error) {}
    const storeLiveState = () => {
        if (state !== 'recarga' && state !== 'falha') {
            try { window.localStorage.removeItem(storageKey); } catch (error) {}
            return;
        }
        try {
            window.localStorage.setItem(storageKey, JSON.stringify({
                state,
                percent: Number.isFinite(percent) ? percent : null,
                initialSeconds,
                until: Date.now() + Math.max(0, seconds) * 1000
            }));
        } catch (error) {}
    };
    const setStateClass = (currentState) => {
        chip.classList.toggle('is-ready', currentState === 'pronto');
        chip.classList.toggle('is-cooling', currentState === 'recarga');
        chip.classList.toggle('is-error', currentState === 'falha');
        chip.classList.toggle('is-offline', currentState !== 'pronto' && currentState !== 'recarga' && currentState !== 'falha');
    };
    const currentPercent = () => {
        if (!Number.isFinite(percent)) return NaN;
        if (state !== 'recarga' || initialSeconds <= 0) return clampPercent(percent);
        const elapsed = Math.max(0, initialSeconds - seconds);
        return clampPercent(percent + ((100 - percent) * (elapsed / initialSeconds)));
    };
    const render = () => {
        if (!label) return;
        const charge = currentPercent();
        if (state === 'falha') {
            chip.style.setProperty('--ai-charge', '0%');
            label.textContent = 'erro';
        } else if (Number.isFinite(charge)) {
            const rounded = Math.round(charge);
            chip.style.setProperty('--ai-charge', `${rounded}%`);
            label.textContent = `${rounded}%`;
        } else if (state === 'recarga') {
            chip.style.setProperty('--ai-charge', '0%');
            label.textContent = format(seconds);
        } else {
            chip.style.setProperty('--ai-charge', '0%');
            label.textContent = stateLabel || readyLabel;
        }
        const tip = state === 'recarga'
            ? `IA carregando: ${format(seconds)} restantes${Number.isFinite(charge) ? ` · ${Math.round(charge)}%` : ''}.`
            : (state === 'falha' ? `IA em espera: ${format(seconds)} restantes.` : (chip.dataset.manelTip || readyTitle));
        chip.dataset.manelTip = tip;
        chip.setAttribute('aria-label', tip);
        setStateClass(state);
    };
    const applyServerStatus = (payload) => {
        if (!payload || payload.ok !== true) return;
        state = payload.estado || state;
        seconds = Math.max(0, Number.parseInt(payload.segundos || '0', 10) || 0);
        initialSeconds = Math.max(1, seconds || Number.parseInt(payload.segundos_recarga_total || '0', 10) || 1);
        percent = payload.porcentagem === null || payload.porcentagem === undefined ? NaN : Number.parseFloat(payload.porcentagem);
        stateLabel = payload.rotulo || (Number.isFinite(percent) ? `${Math.round(percent)}%` : readyLabel);
        readyLabel = Number.isFinite(percent) ? `${Math.round(percent)}%` : 'IA';
        readyTitle = payload.titulo_pronto || payload.titulo || readyTitle;
        chip.dataset.aiState = state;
        chip.dataset.seconds = String(seconds);
        chip.dataset.percent = Number.isFinite(percent) ? String(Math.round(percent)) : '';
        chip.dataset.readyLabel = readyLabel;
        if (payload.titulo) {
            chip.dataset.manelTip = payload.titulo;
            chip.setAttribute('aria-label', payload.titulo);
        }
        storeLiveState();
        render();
        ensureTickTimer();
    };
    const refreshStatus = async () => {
        if (!navigator.onLine || document.hidden) return;
        try {
            const response = await fetch(statusUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store'
            });
            if (!response.ok) return;
            applyServerStatus(await response.json());
        } catch (error) {}
    };
    let tickTimer = null;
    const tick = () => {
        render();
        if (seconds > 0) {
            storeLiveState();
            seconds -= 1;
            return;
        }
        if (state !== 'recarga' && state !== 'falha') return;
        state = 'pronto';
        percent = 100;
        try { window.localStorage.removeItem(storageKey); } catch (error) {}
        render();
        ensureTickTimer();
    };
    function ensureTickTimer() {
        const liveCountdown = state === 'recarga' || state === 'falha';
        if (liveCountdown && tickTimer === null) tickTimer = window.setInterval(tick, 1000);
        if (!liveCountdown && tickTimer !== null) {
            window.clearInterval(tickTimer);
            tickTimer = null;
        }
    }
    tick();
    ensureTickTimer();
    window.setTimeout(refreshStatus, 1800);
    window.setInterval(refreshStatus, 60000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshStatus();
    });
    window.addEventListener('neo:ai-status-refresh', refreshStatus);
})();
</script>

