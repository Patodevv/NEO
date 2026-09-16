(function () {
    'use strict';

    var config = document.querySelector('[data-neo-welcome]');
    var face = document.querySelector('[data-neo-companion]');
    var tour = document.querySelector('[data-manel-tour]');
    if (!config || !face || !tour) return;

    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var storageKey = 'neo_manel_tour_v2_user_' + config.dataset.userId;
    var bubble = tour.querySelector('[data-tour-bubble]');
    var surface = tour.querySelector('[data-tour-surface]');
    var speech = tour.querySelector('[data-tour-speech]');
    var next = tour.querySelector('[data-tour-next]');
    var back = tour.querySelector('[data-tour-back]');
    var spotlight = tour.querySelector('[data-tour-spotlight]');
    var arrow = tour.querySelector('[data-tour-arrow]');
    var arrowLine = tour.querySelector('[data-tour-arrow-line]');
    var arrowTip = tour.querySelector('[data-tour-arrow-tip]');
    var shade = tour.querySelector('[data-tour-shade]');
    var dimmer = shade.parentElement;
    var state = null;
    var currentTarget = null;
    var layoutFrame = null;
    var arrivalTimer = null;
    var savedFocus = null;
    var bubbleSize = null;
    var stepAnimations = [];
    var styleValues = new WeakMap();
    var stepShownAt = -Infinity;
    var navigating = false;

    var steps = [
        { target: '[data-neo-companion]', text: 'Vamos conhecer as áreas do NEO e ver onde ficam seus estudos, atividades e recursos.', next: 'Começar tutorial' },
        { target: '.recent-panel', text: 'Aqui ficam os últimos livros que você abriu. Quando quiser continuar uma leitura, é só voltar por aqui.' },
        { target: '.level-bar', text: 'Essa estrela acompanha sua experiência. Ao concluir atividades, você ganha EXP e avança de nível.' },
        { target: '.side-stat-streak', text: 'O foguinho é sua ofensiva da semana: ele mostra os dias estudados em relação à meta que você escolheu no cadastro.' },
        { target: '.cossas-pill', text: 'Estas são suas coças. Você ganha moedas nas atividades e pode usá-las na loja para personalizar seu perfil.' },
        { target: '.topbar .profile', text: 'Sua foto abre o perfil. Ali ficam seus dados e sua personalização. Eu continuo logo aqui embaixo para te ajudar.' },
        { target: '#sidebarToggle', text: 'Este botão abre o menu. Ele começa fechado e só abre quando você quiser.', next: 'Abrir menu' },
        { target: '.sidebar a[href="materias.php"]', text: 'Os quadradinhos levam às matérias. O relógio abre seu histórico; a sacola, a loja; e a engrenagem, suas configurações.', next: 'Ver matérias' },
        { target: '.external-studies-link', text: 'Em Estudos externos, eu venho do canto até o centro para estudar com você. Você pode pedir um tema, colar um texto ou enviar um link.' },
        { target: '.subject-grid .subject', text: 'Escolha uma matéria para continuarmos. Se ela ainda não tiver livros, seu clique prepara os primeiros conteúdos no seu nível.', next: 'Ver depois' },
        { target: '.content-row, .content-list', text: 'Estes são os livros da matéria. Clique no título para ler; a lixeira à direita permite apagar um livro da sua lista.' },
        { target: '.content-generate-row', text: 'Em “Gerar mais livros”, a IA prepara novos conteúdos adaptados ao seu nível atual. Eles aparecem nesta lista.' },
        { target: '.content-request-row', text: 'Quer um tema específico? Em “Pedir um conteúdo”, você digita o assunto. O novo livro vai para o final da lista, no seu nível.' },
        { target: '.content-row-main, .content-list', text: 'Agora vamos conhecer a leitura. Abra um dos livros da lista para continuar comigo.', next: 'Abrir livro' },
        { target: '.reader', text: 'Este é o conteúdo do livro. Leia no seu ritmo. Se precisar de um resumo ou de uma explicação mais simples, é só me chamar.' },
        { target: '.content-tabs', text: 'O livro abre a leitura, e o outro ícone leva às questões. Você alterna entre os dois por esta barra.', next: 'Ver questões' },
        { target: '.question-controls', text: 'Aqui você gera questões no nível indicado. Ao gerar a atividade, a leitura fica trancada até você concluir as respostas. Depois, o livro é liberado e você pode revisar o resultado.' },
        { target: '[data-neo-companion]', text: 'Pronto! Clique em mim quando precisar de ajuda, dicas ou explicações. O botão de interrogação na nossa conversa abre este tutorial de novo.', next: 'Começar a estudar' }
    ];

    function readState() {
        try {
            var value = JSON.parse(sessionStorage.getItem(storageKey));
            if (value && Number.isInteger(value.step) && value.step >= 0 && value.step < steps.length && safeUrl(value.url)) {
                value.history = Array.isArray(value.history) ? value.history.filter(function (entry) {
                    return Number.isInteger(entry.step) && entry.step >= 0 && entry.step < steps.length && safeUrl(entry.url);
                }).slice(-steps.length) : [];
                return value;
            }
        } catch (e) {}
        return null;
    }

    function safeUrl(href) {
        try {
            var url = new URL(href, window.location.href);
            return url.origin === window.location.origin && url.pathname.startsWith(new URL('.', window.location.href).pathname)
                && /\/(index|materias|conteudos|livro|questoes)\.php$/.test(url.pathname);
        } catch (e) { return false; }
    }

    function saveState() {
        try {
            if (state) sessionStorage.setItem(storageKey, JSON.stringify(state));
            else sessionStorage.removeItem(storageKey);
        } catch (e) {}
    }

    function faceState(value) {
        document.dispatchEvent(new CustomEvent('neo:manel-face-state', { detail: { state: value } }));
    }

    function setStep(index, href, replace) {
        if (!state) return;
        var destination = href ? new URL(href, window.location.href).href : window.location.href;
        if (!safeUrl(destination)) return;
        if (!replace) state.history.push({ step: state.step, url: state.url });
        state.step = index;
        state.url = destination;
        saveState();
        if (destination !== window.location.href) {
            navigating = true;
            window.location.assign(destination);
            return;
        }
        showStep();
    }

    function begin() {
        navigating = false;
        savedFocus = document.activeElement;
        document.dispatchEvent(new CustomEvent('neo:manel-close'));
        state = { step: 0, url: new URL('index.php', window.location.href).href, history: [] };
        saveState();
        if (window.location.pathname !== new URL(state.url).pathname) window.location.assign(state.url);
        else {
            state.url = window.location.href;
            saveState();
            showStep();
        }
    }

    function finish() {
        state = null;
        saveState();
        window.clearTimeout(arrivalTimer);
        if (layoutFrame) window.cancelAnimationFrame(layoutFrame);
        layoutFrame = null;
        stopStepAnimations();
        tour.hidden = true;
        document.body.classList.remove('manel-tour-active', 'manel-tour-selecting', 'manel-tour-reading');
        document.body.style.removeProperty('--manel-tour-space');
        currentTarget = null;
        faceState('success');
        var returnTo = savedFocus && savedFocus.isConnected && savedFocus.closest('main') ? savedFocus : face;
        returnTo.focus({ preventScroll: true });
    }

    function targetForStep() {
        if (window.innerWidth <= 760 && state.step === 6) return document.querySelector('#sidebar');
        if (window.innerWidth <= 760 && state.step === 7) return document.querySelector('.sidebar a[href="materias.php"]');
        if (state.step === 6 && document.querySelector('#sidebar.open')) return document.querySelector('#sidebar');
        if (state.step === 7 && !document.querySelector('#sidebar.open')) return document.querySelector('#sidebarToggle');
        return document.querySelector(steps[state.step].target) || face;
    }

    function showStep() {
        if (!state || document.body.classList.contains('neo-interface-locked')) return;
        document.dispatchEvent(new CustomEvent('neo:manel-close'));
        tour.hidden = false;
        document.body.classList.add('manel-tour-active');
        document.body.classList.toggle('manel-tour-selecting', state.step === 9);
        currentTarget = targetForStep();
        var scrollableTarget = currentTarget !== face && !currentTarget.closest('.topbar, .sidebar') && currentTarget.id !== 'sidebarToggle';
        document.body.classList.toggle('manel-tour-reading', window.innerWidth <= 560 && scrollableTarget);
        spotlight.classList.toggle('is-face', currentTarget === face);
        speech.textContent = steps[state.step].text;
        tour.dataset.step = String(state.step + 1);
        stepShownAt = performance.now();
        next.querySelector('[data-tour-next-label]').textContent = steps[state.step].next || 'Continuar';
        if (window.innerWidth <= 760 && state.step === 6) {
            speech.textContent = 'No celular, seu menu fica aqui embaixo. Ele está sempre à mão para trocar de página.';
            next.querySelector('[data-tour-next-label]').textContent = 'Continuar';
        }
        back.disabled = state.history.length === 0;
        if (state.step === 13 && !document.querySelector('.content-row-main')) {
            speech.textContent = 'Esta matéria ainda não tem livros. Use um dos botões de criação quando quiser começar. Eu estarei aqui para ajudar.';
            next.querySelector('[data-tour-next-label]').textContent = 'Continuar';
        }
        faceState('success');
        bubbleSize = null;
        layout();
        animateStep();
        if (scrollableTarget) {
            if (window.innerWidth <= 560) {
                document.body.style.setProperty('--manel-tour-space', (bubble.offsetHeight + 66) + 'px');
                window.scrollTo({ top: Math.max(0, window.scrollY + currentTarget.getBoundingClientRect().top - 84), behavior: reducedMotion ? 'instant' : 'smooth' });
            } else {
                currentTarget.scrollIntoView({ block: 'center', behavior: reducedMotion ? 'instant' : 'smooth' });
            }
        }
        bubble.focus({ preventScroll: true });
        window.clearTimeout(arrivalTimer);
        arrivalTimer = window.setTimeout(layout, 420);
    }

    function clamp(value, min, max) { return Math.max(min, Math.min(max, value)); }

    function snap(value) {
        var ratio = window.devicePixelRatio || 1;
        return Math.round(value * ratio) / ratio;
    }

    function setStyle(element, property, value) {
        var values = styleValues.get(element);
        if (!values) {
            values = {};
            styleValues.set(element, values);
        }
        if (values[property] === value) return;
        values[property] = value;
        element.style[property] = value;
    }

    function setPath(element, value) {
        if (element.getAttribute('d') !== value) element.setAttribute('d', value);
    }

    function stopStepAnimations() {
        stepAnimations.forEach(function (animation) { animation.cancel(); });
        stepAnimations = [];
    }

    function animateStep() {
        stopStepAnimations();
        if (reducedMotion || !surface.animate) return;
        stepAnimations = [
            surface.animate([
                { opacity: .4, transform: 'translate3d(0, 6px, 0) scale(.99)' },
                { opacity: 1, transform: 'translate3d(0, 0, 0) scale(1)' }
            ], { duration: 220, easing: 'cubic-bezier(.22, 1, .36, 1)' }),
            arrow.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 200, easing: 'ease-out' })
        ];
    }

    function layout() {
        if (tour.hidden || !currentTarget || !state || document.hidden) return;
        var viewport = window.visualViewport;
        var vw = viewport ? viewport.width : window.innerWidth;
        var vh = viewport ? viewport.height : window.innerHeight;
        var vy = viewport ? viewport.offsetTop : 0;
        var navSpace = window.innerWidth <= 760 ? 86 : 0;
        var maxHeight = Math.max(160, vh - 32 - navSpace) + 'px';
        if (bubble.style.maxHeight !== maxHeight) {
            bubble.style.maxHeight = maxHeight;
            bubbleSize = null;
        }
        // Measure text only when its size changes, not on every scroll frame.
        if (!bubbleSize) {
            setStyle(bubble, 'overflowY', surface.scrollHeight > vh - 32 - navSpace ? 'auto' : 'visible');
            bubbleSize = { width: bubble.offsetWidth, height: bubble.offsetHeight };
        }
        var bw = bubbleSize.width;
        var bh = bubbleSize.height;
        var rect = currentTarget.getBoundingClientRect();
        var padding = 7;
        var left = snap(clamp(rect.left - padding, 4, vw - 12));
        var top = snap(clamp(rect.top - padding, vy + 4, vy + vh - 12));
        var right = snap(clamp(rect.right + padding, left + 8, vw - 4));
        var bottom = snap(clamp(rect.bottom + padding, top + 8, vy + vh - 4));
        var gap = 34;
        var bx, by;
        if (state.step === 9) {
            var space = (bh + gap + 32) + 'px';
            if (document.body.style.getPropertyValue('--manel-tour-space') !== space) document.body.style.setProperty('--manel-tour-space', space);
            bx = vw <= 560 ? (vw - bw) / 2 : vw - bw - 24;
            by = vy + vh - bh - 16;
        } else if (vw <= 560) {
            bx = (vw - bw) / 2;
            by = bottom + gap + bh <= vy + vh - 16 ? bottom + gap : vy + vh - bh - 16;
            if (by < bottom && top - bh - gap >= vy + 16) by = top - bh - gap;
        } else if (right + gap + bw <= vw - 16) {
            bx = right + gap;
            by = clamp(top, vy + 114, vy + vh - bh - 16);
        } else if (left - bw - gap >= 16) {
            bx = left - bw - gap;
            by = clamp(top, vy + 114, vy + vh - bh - 16);
        } else {
            bx = vw - bw - 24;
            by = bottom + gap + bh <= vy + vh - 16 ? bottom + gap : top - bh - gap;
        }
        bx = snap(clamp(bx, 16, vw - bw - 16));
        by = snap(clamp(by, vy + 16, vy + vh - bh - 16 - navSpace));
        // Keep movement on transforms; avoid animating layout dimensions during scrolling.
        setStyle(spotlight, 'transform', 'translate3d(' + left + 'px, ' + top + 'px, 0)');
        setStyle(spotlight, 'width', (right - left) + 'px');
        setStyle(spotlight, 'height', (bottom - top) + 'px');
        setStyle(bubble, 'transform', 'translate3d(' + bx + 'px, ' + by + 'px, 0)');
        setStyle(dimmer, 'display', currentTarget === face ? 'none' : '');
        if (currentTarget !== face) {
            var radius = Math.min(18, (right - left) / 2, (bottom - top) / 2);
            setPath(shade, 'M0 0H' + window.innerWidth + 'V' + window.innerHeight + 'H0Z'
                + 'M' + (left + radius) + ' ' + top + 'H' + (right - radius)
                + 'Q' + right + ' ' + top + ' ' + right + ' ' + (top + radius)
                + 'V' + (bottom - radius) + 'Q' + right + ' ' + bottom + ' ' + (right - radius) + ' ' + bottom
                + 'H' + (left + radius) + 'Q' + left + ' ' + bottom + ' ' + left + ' ' + (bottom - radius)
                + 'V' + (top + radius) + 'Q' + left + ' ' + top + ' ' + (left + radius) + ' ' + top + 'Z');
        }

        // Connect the nearest edges so the arrow never crosses the speech bubble.
        var tx = clamp(bx + bw / 2, left + 6, right - 6);
        var ty = clamp(by + bh / 2, top + 6, bottom - 6);
        var sx = clamp(tx, bx + 20, bx + bw - 20);
        var sy = clamp(ty, by + 20, by + bh - 20);
        if (by >= bottom) { sy = by; ty = bottom + 3; }
        else if (by + bh <= top) { sy = by + bh; ty = top - 3; }
        else if (bx >= right) { sx = bx; tx = right + 3; }
        else if (bx + bw <= left) { sx = bx + bw; tx = left - 3; }
        else { setStyle(arrow, 'visibility', 'hidden'); return; }
        setStyle(arrow, 'visibility', 'visible');
        var cx = sx + (tx - sx) * .25;
        var cy = sy + (ty - sy) * .75;
        setPath(arrowLine, 'M' + sx + ' ' + sy + ' Q' + cx + ' ' + cy + ' ' + tx + ' ' + ty);
        var angle = Math.atan2(ty - cy, tx - cx);
        var a = { x: tx - 9 * Math.cos(angle - .55), y: ty - 9 * Math.sin(angle - .55) };
        var b = { x: tx - 9 * Math.cos(angle + .55), y: ty - 9 * Math.sin(angle + .55) };
        setPath(arrowTip, 'M' + a.x + ' ' + a.y + ' L' + tx + ' ' + ty + ' L' + b.x + ' ' + b.y);
    }

    function scheduleLayout() {
        if (layoutFrame || tour.hidden || document.hidden) return;
        layoutFrame = window.requestAnimationFrame(function () { layoutFrame = null; layout(); });
    }

    function advance() {
        if (!state || navigating || performance.now() - stepShownAt < 300) return;
        if (state.step === 17) { finish(); return; }
        if (state.step === 6) {
            if (window.innerWidth > 760) document.querySelector('#sidebarToggle').click();
            setStep(7);
            return;
        }
        if (state.step === 7) {
            if (window.innerWidth <= 560) document.querySelector('#sidebarClose').click();
            setStep(8, 'materias.php');
            return;
        }
        if (state.step === 9) { setStep(17); return; }
        if (state.step === 13) {
            var book = document.querySelector('.content-row-main');
            setStep(book ? 14 : 17, book ? book.href : null);
            return;
        }
        if (state.step === 15) {
            var questions = document.querySelector('.content-tab[href*="questoes.php"]');
            setStep(questions ? 16 : 17, questions ? questions.href : null);
            return;
        }
        setStep(state.step + 1);
    }

    next.addEventListener('click', advance);
    back.addEventListener('click', function () {
        if (!state || !state.history.length || navigating || performance.now() - stepShownAt < 300) return;
        var previous = state.history.pop();
        setStep(previous.step, previous.url, true);
    });
    tour.querySelector('[data-tour-skip]').addEventListener('click', finish);
    document.querySelector('[data-manel-tour-start]').addEventListener('click', begin);
    document.addEventListener('keydown', function (event) {
        if (!state || tour.hidden) return;
        if (event.key === 'Escape') { event.stopImmediatePropagation(); finish(); }
        if (event.target.closest('[data-tour-bubble]') && event.key === 'ArrowRight') { event.preventDefault(); advance(); }
        if (event.target.closest('[data-tour-bubble]') && event.key === 'ArrowLeft') { event.preventDefault(); back.click(); }
    }, true);

    // Save before the browser follows a link or posts the user's chosen subject.
    document.addEventListener('click', function (event) {
        if (!state || tour.hidden || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        var selected = event.target.closest('.subject, .content-row-main, .content-tab[href*="questoes.php"], .sidebar a[href="materias.php"]');
        var nextStep = null;
        var destination = null;
        if (selected && state.step === 9 && selected.matches('.subject')) {
            var form = selected.closest('form');
            destination = form ? 'conteudos.php?materia_id=' + encodeURIComponent(form.querySelector('[name="materia_id"]').value) : selected.href;
            nextStep = 10;
        } else if (selected && state.step >= 10 && state.step <= 13 && selected.matches('.content-row-main')) {
            destination = selected.href;
            nextStep = 14;
        } else if (selected && state.step >= 14 && state.step <= 15 && selected.matches('.content-tab')) {
            destination = selected.href;
            nextStep = 16;
        } else if (selected && state.step === 7 && selected.closest('.sidebar')) {
            if (window.innerWidth <= 560) document.querySelector('#sidebarClose').click();
            destination = selected.href;
            nextStep = 8;
        }
        if (nextStep !== null && safeUrl(destination)) {
            if (navigating) { event.preventDefault(); return; }
            navigating = true;
            state.history.push({ step: state.step, url: state.url });
            state.step = nextStep;
            state.url = new URL(destination, window.location.href).href;
            saveState();
        }
        if (state.step === 6 && event.target.closest('#sidebarToggle')) {
            window.setTimeout(function () { if (state && state.step === 6) setStep(7); }, 280);
        }
    }, true);
    document.addEventListener('neo:manel-toggle', function () { if (state && !tour.hidden) finish(); });
    ['pointerover', 'focusin'].forEach(function (name) {
        document.addEventListener(name, function (event) {
            if (!state || state.step !== 9 || tour.hidden) return;
            var subject = event.target.closest('.subject');
            if (subject && subject !== currentTarget) { currentTarget = subject; scheduleLayout(); }
        });
    });
    window.addEventListener('scroll', scheduleLayout, { passive: true, capture: true });
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            navigating = false;
            state = readState();
            if (state) showStep();
            else finish();
        }
    });
    function resizeLayout() {
        bubbleSize = null;
        scheduleLayout();
    }
    window.addEventListener('resize', resizeLayout, { passive: true });
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', resizeLayout);
        window.visualViewport.addEventListener('scroll', scheduleLayout);
    }
    if (window.ResizeObserver) new ResizeObserver(resizeLayout).observe(bubble);
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            if (layoutFrame) window.cancelAnimationFrame(layoutFrame);
            layoutFrame = null;
            stopStepAnimations();
        } else resizeLayout();
    });

    function resume() {
        state = readState();
        if (!state) return;
        var route = window.location.pathname.split('/').pop();
        var expected = new URL(state.url).pathname.split('/').pop();
        if (route !== expected) { state = null; return; }
        state.url = window.location.href;
        saveState();
        showStep();
    }

    function afterIntro(callback) {
        if (!document.body.classList.contains('neo-interface-locked')) { callback(); return; }
        var observer = new MutationObserver(function () {
            if (!document.body.classList.contains('neo-interface-locked')) { observer.disconnect(); callback(); }
        });
        observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
    }

    function setupAwakening() {
        var overlay = document.querySelector('[data-face-overlay]');
        var frame = document.querySelector('[data-face-frame]');
        if (!overlay || !frame) { afterIntro(resume); return; }
        var started = false;
        var revealed = false;
        var docking = false;
        var finished = false;
        var flight = null;
        var animation = null;

        function reveal() {
            if (revealed) return;
            revealed = true;
            document.body.classList.remove('neo-dashboard-awakening');
            document.body.classList.add('neo-dashboard-revealing');
            overlay.classList.add('is-revealing');
        }

        function landed() {
            if (finished) return;
            finished = true;
            reveal();
            if (flight) flight.remove();
            overlay.remove();
            document.body.classList.remove('neo-interface-locked', 'neo-dashboard-revealing', 'neo-face-docking');
            document.dispatchEvent(new CustomEvent('neo:face-arrived'));
            window.removeEventListener('message', receive);
            window.removeEventListener('resize', resizeFlight);
            window.setTimeout(begin, reducedMotion ? 0 : 260);
        }

        function renderedFaceRect(svg) {
            var rect = svg.getBoundingClientRect();
            var width = Math.min(rect.width, rect.height * 300 / 220);
            var height = width * 220 / 300;
            return { left: rect.left + (rect.width - width) / 2, top: rect.top + (rect.height - height) / 2, width: width, height: height };
        }

        function dock() {
            if (docking || finished) return;
            docking = true;
            reveal();
            var original;
            try { original = frame.contentDocument.querySelector('svg'); } catch (e) {}
            if (!original) { landed(); return; }
            // Reuse the exact final SVG, including its wink and smile, across the iframe boundary.
            var from = renderedFaceRect(original);
            var frameRect = frame.getBoundingClientRect();
            from.left += frameRect.left;
            from.top += frameRect.top;
            var to = renderedFaceRect(face.querySelector('svg'));
            flight = original.cloneNode(true);
            flight.removeAttribute('id');
            flight.querySelectorAll('[id]').forEach(function (part) { part.removeAttribute('id'); });
            flight.classList.add('neo-face-flight');
            flight.setAttribute('aria-hidden', 'true');
            Object.assign(flight.style, { left: from.left + 'px', top: from.top + 'px', width: from.width + 'px', height: from.height + 'px' });
            document.body.appendChild(flight);
            frame.style.visibility = 'hidden';
            if (reducedMotion || !flight.animate) { landed(); return; }
            var dx = to.left - from.left;
            var dy = to.top - from.top;
            var scale = to.width / from.width;
            animation = flight.animate([
                { transform: 'translate(0, 0) scale(1)', offset: 0 },
                { transform: 'translate(' + dx * .7 + 'px, ' + dy * .82 + 'px) scale(' + (scale + (1 - scale) * .24) + ')', offset: .65 },
                { transform: 'translate(' + dx + 'px, ' + dy + 'px) scale(' + scale + ')', offset: 1 }
            ], { duration: 920, easing: 'cubic-bezier(.4, 0, .2, 1)', fill: 'forwards' });
            animation.finished.then(landed, landed);
            window.setTimeout(landed, 1250);
        }

        function resizeFlight() {
            if (animation && !finished) animation.finish();
        }

        function start() {
            if (started) return;
            started = true;
            frame.addEventListener('load', function () {
                if (finished) return;
                overlay.classList.remove('is-waiting');
                overlay.classList.add('is-entering');
                window.setTimeout(reveal, 2200);
                window.setTimeout(dock, 3000);
            }, { once: true });
            frame.src = frame.dataset.src;
            window.setTimeout(landed, 6500);
        }

        function receive(event) {
            if (event.origin !== window.location.origin || !event.data) return;
            var intro = document.querySelector('.neo-intro-overlay iframe');
            if (intro && event.source === intro.contentWindow && event.data.type === 'neo-intro-star-done') start();
            if (event.source !== frame.contentWindow) return;
            if (event.data.type === 'neo-face-reveal') reveal();
            if (event.data.type === 'neo-face-done') dock();
        }

        window.addEventListener('message', receive);
        window.addEventListener('resize', resizeFlight);
        if (overlay.dataset.waitForIntro === '1') window.setTimeout(start, 1700);
        else start();
    }

    window.addEventListener('pageshow', function (event) { if (event.persisted) afterIntro(resume); });
    setupAwakening();
})();
