(function () {
    'use strict';
    const page = document.querySelector('[data-study-page]');
    if (!page) return;
    const userId = page.dataset.userId;
    const storageKey = 'neo_external_studies_v1_user_' + userId;
    const form = page.querySelector('[data-study-form]');
    const input = form.elements.message;
    const messages = page.querySelector('[data-study-messages]');
    const error = page.querySelector('[data-study-error]');
    const back = page.querySelector('[data-study-back]');
    let atStart = false;
    const send = page.querySelector('[data-study-send]');
    const cancel = page.querySelector('[data-study-cancel]');
    const reset = page.querySelector('[data-study-new]');
    const face = page.querySelector('[data-neo-companion]');
    const faceSlot = page.querySelector('.study-face-slot');
    const thread = page.querySelector('[data-study-thread]');
    const heading = page.querySelector('.study-heading');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let presentation = null;
    let faceFlight = null;
    face.setAttribute('aria-label', 'Escrever para Manel');
    face.removeAttribute('aria-expanded');
    face.addEventListener('click', () => { if (!input.disabled) input.focus(); });
    let request = null;
    let entries = [];
    // Discard history saved by older versions; conversations now live only in this page.
    try { localStorage.removeItem(storageKey); } catch (_) {}
    function validStudy(study) {
        return study && typeof study.title === 'string' && typeof study.summary === 'string'
            && Array.isArray(study.questions) && study.questions.length >= 2 && study.questions.length <= 10
            && study.questions.every(q => q && typeof q.prompt === 'string' && typeof q.explanation === 'string'
                && Array.isArray(q.options) && q.options.length === 4 && q.options.every(o => typeof o === 'string')
                && Number.isInteger(q.answer) && q.answer >= 0 && q.answer < 4);
    }
    function element(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }
    function button(text) {
        const node = element('button', 'neo-icon-button neo-star-hover study-command', text);
        node.type = 'button';
        node.prepend(page.querySelector('[data-study-button-star]').content.cloneNode(true));
        return node;
    }
    function showError(message) {
        error.textContent = message;
        error.hidden = !message;
    }
    function busy(active) {
        page.classList.toggle('is-busy', active);
        page.querySelectorAll('[data-study-starter]').forEach(button => { button.disabled = active; });
        face.setAttribute('aria-busy', String(active));
        back.disabled = active;
        send.hidden = active;
        cancel.hidden = !active;
        reset.disabled = active;
        input.disabled = active;
        form.elements.quantity.disabled = active;
        cancel.title = 'Cancelar pedido';
        cancel.setAttribute('aria-label', cancel.title);
        faceState(active ? 'thinking' : 'neutral');
    }
    function faceState(state) {
        document.dispatchEvent(new CustomEvent('neo:manel-face-state', { detail: { state } }));
    }
    function render() {
        const showStudies = entries.length > 0 && !atStart;
        page.classList.toggle('has-studies', showStudies);
        (showStudies ? heading : thread).prepend(faceSlot);
        messages.hidden = !showStudies;
        back.hidden = !entries.length;
        back.title = atStart ? 'Retomar conversa' : 'Voltar ao início do estudo';
        back.setAttribute('aria-label', back.title);
        messages.replaceChildren();
        entries.forEach((entry, index) => {
            messages.appendChild(element('p', 'study-user', entry.message));
            const study = entry.study;
            const book = element('article', 'study-book');
            book.setAttribute('aria-label', 'Resposta de Manel');
            const heading = element('header', 'study-book-heading');
            const title = element('h2', '', study.title);
            title.tabIndex = -1;
            heading.append(title, element('span', 'study-provider', ['OP', 'GQ'].includes(study.provider) ? study.provider : 'IA'));
            book.append(heading, element('p', 'study-summary', study.summary));
            const sources = element('div', 'study-sources');
            const safeSources = Array.isArray(study.sources) ? study.sources : [];
            for (const source of safeSources) {
                try {
                    const url = new URL(source.url);
                    if (!['http:', 'https:'].includes(url.protocol)) continue;
                    const link = element('a', '', source.title || url.hostname);
                    link.href = url.href;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    sources.appendChild(link);
                } catch (_) {}
            }
            if (!sources.childElementCount) sources.textContent = 'A partir do seu pedido e do contexto da conversa';
            book.appendChild(sources);
            const quiz = element('form', 'study-quiz');
            const answers = Array.isArray(entry.answers) ? entry.answers : [];
            const reviewed = entry.reviewed === true && study.questions.every((q, i) => Number.isInteger(answers[i]) && answers[i] >= 0 && answers[i] < 4);
            let score = 0;
            study.questions.forEach((question, qi) => {
                const field = element('fieldset', 'study-question');
                field.appendChild(element('legend', '', String(qi + 1).padStart(2, '0') + '   ' + question.prompt));
                question.options.forEach((option, oi) => {
                    const label = element('label', 'study-option');
                    const radio = element('input');
                    radio.type = 'radio';
                    radio.name = 'question-' + qi;
                    radio.value = String(oi);
                    radio.required = true;
                    radio.checked = answers[qi] === oi;
                    radio.disabled = reviewed;
                    radio.addEventListener('change', () => {
                        entry.answers = Array.isArray(entry.answers) ? entry.answers : [];
                        entry.answers[qi] = oi;
                    });
                    label.append(radio, element('strong', '', String.fromCharCode(65 + oi)), element('span', '', option));
                    if (reviewed && oi === question.answer) label.classList.add('is-correct');
                    if (reviewed && answers[qi] === oi && oi !== question.answer) label.classList.add('is-wrong');
                    field.appendChild(label);
                });
                if (reviewed) field.appendChild(element('p', 'study-explanation', question.explanation));
                if (answers[qi] === question.answer) score++;
                quiz.appendChild(field);
            });
            const actions = element('div', 'study-quiz-actions');
            if (reviewed) {
                const result = element('span', 'study-score', 'Resultado ' + score + '/' + study.questions.length);
                result.tabIndex = -1;
                actions.appendChild(result);
            }
            const submit = button(reviewed ? 'Tentar novamente' : 'Enviar respostas');
            submit.type = reviewed ? 'button' : 'submit';
            if (reviewed) submit.addEventListener('click', () => {
                entry.reviewed = false;
                entry.answers = [];
                render();
                messages.querySelectorAll('.study-quiz')[index].querySelector('input').focus();
            });
            actions.appendChild(submit);
            quiz.appendChild(actions);
            quiz.addEventListener('submit', event => {
                event.preventDefault();
                if (entry.reviewed || !quiz.reportValidity()) return;
                entry.answers = study.questions.map((q, qi) => Number(new FormData(quiz).get('question-' + qi)));
                entry.reviewed = true;
                render();
                messages.querySelectorAll('.study-quiz')[index].querySelector('.study-score').focus();
            });
            book.appendChild(quiz);
            messages.appendChild(book);
        });
    }

    async function presentReply(previousRect) {
        const book = messages.lastElementChild;
        face.setAttribute('aria-busy', 'false');
        page.classList.remove('is-busy');
        page.classList.add('is-replying');
        messages.inert = true;
        book.setAttribute('aria-busy', 'true');
        cancel.title = 'Mostrar resposta completa';
        cancel.setAttribute('aria-label', cancel.title);
        faceState('neutral');
        const texts = Array.from(book.querySelectorAll('h2, .study-summary, legend, .study-option span')).map(node => ({ node, text: node.textContent, chars: Array.from(node.textContent) }));
        const sections = Array.from(book.querySelectorAll('.study-sources, .study-quiz, .study-question, .study-option, .study-quiz-actions'));
        const sources = book.querySelector('.study-sources');
        function revealLine(item) {
            const question = item.node.closest('.study-question');
            const option = item.node.closest('.study-option');
            if (question) { question.hidden = false; question.closest('.study-quiz').hidden = false; sources.hidden = false; }
            if (option) option.hidden = false;
        }
        const total = texts.reduce((sum, item) => sum + item.chars.length, 0);
        const mouth = face.querySelector('.neo-companion-mouth');
        const restMouth = 'M 128 150 L 172 150';
        const speechShapes = [restMouth, 'M 128 147 Q 150 166 172 147', 'M 142 151 Q 150 140 158 151 Q 150 162 142 151', 'M 128 147 Q 150 166 172 147'];
        let frame = null;
        let settled = false;
        let followText = true;
        const stopFollowing = () => { followText = false; };
        thread.addEventListener('wheel', stopFollowing, { passive: true });
        thread.addEventListener('touchstart', stopFollowing, { passive: true });
        let done;
        const complete = new Promise(resolve => { done = resolve; });
        function finish() {
            if (settled) return;
            settled = true;
            if (frame) cancelAnimationFrame(frame);
            if (faceFlight) faceFlight.cancel();
            faceFlight = null;
            texts.forEach(item => { item.node.textContent = item.text; item.node.classList.remove('is-typing-line'); });
            sections.forEach(section => { section.hidden = false; });
            thread.removeEventListener('wheel', stopFollowing);
            thread.removeEventListener('touchstart', stopFollowing);
            mouth.setAttribute('d', restMouth);
            book.classList.remove('is-entering', 'is-typing');
            book.removeAttribute('aria-busy');
            messages.inert = false;
            page.classList.remove('is-replying');
            faceState('neutral');
            presentation = null;
            done();
        }
        presentation = { finish };
        if (reducedMotion.matches || document.hidden) { finish(); return complete; }
        sections.forEach(section => { section.hidden = true; });
        texts.forEach(item => { item.node.textContent = ''; });
        book.classList.add('is-entering');
        try {
            const target = face.getBoundingClientRect();
            if (previousRect && Math.abs(previousRect.width - target.width) > 1) {
                faceFlight = face.animate([
                    { transform: `translate(${previousRect.x - target.x}px, ${previousRect.y - target.y}px) scale(${previousRect.width / target.width}, ${previousRect.height / target.height})` },
                    { transform: 'translate(0, 0) scale(1)' },
                ], { duration: 620, easing: 'cubic-bezier(.22, 1, .36, 1)' });
                await faceFlight.finished.catch(() => {});
                faceFlight = null;
            }
            if (settled) return complete;
            book.classList.remove('is-entering');
            book.classList.add('is-typing');
            book.animate([{ opacity: 0, transform: 'translate(-8px, -8px) scale(.98)' }, { opacity: 1, transform: 'none' }], { duration: 220, easing: 'ease-out' });
            book.scrollIntoView({ block: 'start' });
            faceState('speaking');
            let elapsed = 0;
            let lastTime = null;
            let lastCount = -1;
            let lastMouth = -1;
            let current = 0;
            let offset = 0;
            let lastScroll = 0;
            const duration = Math.min(14000, Math.max(800, total / 95 * 1000));
            function tick(time) {
                if (settled) return;
                if (document.hidden) { lastTime = null; frame = requestAnimationFrame(tick); return; }
                elapsed += lastTime === null ? 0 : Math.min(64, time - lastTime);
                lastTime = time;
                const count = Math.min(total, Math.floor(total * elapsed / duration));
                if (count !== lastCount) {
                    while (current < texts.length && count >= offset + texts[current].chars.length) {
                        revealLine(texts[current]);
                        texts[current].node.textContent = texts[current].text;
                        if (texts[current].node.classList.contains('study-summary')) sources.hidden = false;
                        texts[current].node.classList.remove('is-typing-line');
                        offset += texts[current].chars.length;
                        current++;
                    }
                    if (current < texts.length) {
                        revealLine(texts[current]);
                        texts[current].node.classList.add('is-typing-line');
                        texts[current].node.textContent = texts[current].chars.slice(0, count - offset).join('');
                    }
                    lastCount = count;
                }
                const mouthIndex = Math.floor(elapsed / 130) % speechShapes.length;
                if (mouthIndex !== lastMouth) { mouth.setAttribute('d', speechShapes[mouthIndex]); lastMouth = mouthIndex; }
                if (followText && current < texts.length && elapsed - lastScroll > 180) {
                    const visibleBottom = thread.getBoundingClientRect().bottom - 16;
                    const textBottom = texts[current].node.getBoundingClientRect().bottom;
                    if (textBottom > visibleBottom) thread.scrollTop += textBottom - visibleBottom;
                    lastScroll = elapsed;
                }
                if (count >= total) { finish(); return; }
                frame = requestAnimationFrame(tick);
            }
            frame = requestAnimationFrame(tick);
        } catch (_) { finish(); }
        return complete;
    }
    window.addEventListener('resize', () => { if (faceFlight) faceFlight.cancel(); });
    window.addEventListener('pagehide', () => { if (presentation) presentation.finish(); });
    reducedMotion.addEventListener('change', () => { if (reducedMotion.matches && presentation) presentation.finish(); });
    input.addEventListener('input', () => { document.getElementById('study-request-count').textContent = input.value.length + '/4000'; });
    page.querySelectorAll('[data-study-starter]').forEach(button => {
        button.title = button.textContent.trim();
        button.addEventListener('click', () => {
            if (request) return;
            input.value = button.dataset.studyStarter;
            input.dispatchEvent(new Event('input'));
            input.focus();
        });
    });
    input.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); form.requestSubmit(); }
    });
    cancel.addEventListener('click', () => {
        if (presentation) presentation.finish();
        else if (request) request.abort();
    });
    back.addEventListener('click', () => {
        if (request) return;
        atStart = !atStart;
        render();
        showError('');
        thread.scrollTop = 0;
        input.focus();
    });
    reset.addEventListener('click', () => {
        if (request) return;
        entries = [];
        atStart = false;
        render();
        showError('');
        input.value = '';
        input.dispatchEvent(new Event('input'));
        input.focus();
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (request || !form.reportValidity()) return;
        const message = input.value.trim();
        if (message.length < 3) { showError('Digite o que você quer estudar.'); return; }
        const controller = new AbortController();
        request = controller;
        const quantity = Number(form.elements.quantity.value);
        const history = entries.slice(-2).flatMap(entry => [
            { role: 'user', content: entry.message },
            { role: 'assistant', content: entry.study.title + '\n' + entry.study.summary },
        ]);
        showError('');
        busy(true);
        face.scrollIntoView({ block: 'nearest' });
        let timedOut = false;
        const timeout = setTimeout(() => { timedOut = true; controller.abort(); }, 210000);
        try {
            const response = await fetch('manel.php', {
                method: 'POST', signal: controller.signal,
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ mode: 'study', userId: Number(userId), message, quantity, history }),
            });
            if (response.redirected) throw new Error('Sua sessão terminou. Entre novamente para continuar.');
            let data;
            try { data = await response.json(); } catch (_) { throw new Error('O servidor não respondeu como esperado. Tente novamente.'); }
            if (!response.ok) throw new Error(data.reply || 'Não consegui preparar seu estudo.');
            if (!validStudy(data.study) || data.study.questions.length !== quantity) throw new Error('O estudo veio incompleto. Tente novamente.');
            clearTimeout(timeout);
            const previousRect = face.getBoundingClientRect();
            entries.push({ message, study: data.study, answers: [], reviewed: false });
            entries = entries.slice(-6);
            atStart = false;
            render();
            input.value = '';
            input.dispatchEvent(new Event('input'));
            await presentReply(previousRect);
            messages.querySelector('.study-book:last-child h2').focus({ preventScroll: true });
        } catch (failure) {
            showError(failure.name === 'AbortError' ? (timedOut ? 'O pedido demorou demais. Tente novamente.' : 'Pedido cancelado.') : failure.message);
        } finally {
            clearTimeout(timeout);
            request = null;
            busy(false);
        }
    });
    render();
})();
