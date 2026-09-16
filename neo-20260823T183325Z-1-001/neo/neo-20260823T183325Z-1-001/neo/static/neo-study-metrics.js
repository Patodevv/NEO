(() => {
    'use strict';
    const form = document.querySelector('.question-form');
    if (!form || !window.IntersectionObserver) return;
    const cards = [...form.querySelectorAll('[data-question-id]')];
    const visible = new Map();
    const elapsed = new Map();
    const inputs = new Map();
    let active = null;
    let tick = performance.now();
    function account() {
        const now = performance.now();
        if (active && !document.hidden) elapsed.set(active, Math.min(7200000, (elapsed.get(active) || 0) + Math.min(now - tick, 5000)));
        tick = now;
    }
    function choose() {
        account();
        active = [...visible.entries()].filter(([, ratio]) => ratio > 0).sort((a, b) => b[1] - a[1])[0]?.[0] || null;
    }
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => visible.set(entry.target.dataset.questionId, entry.intersectionRatio));
        choose();
    }, { threshold: [0, .1, .25, .5, .75, 1] });
    cards.forEach(card => {
        const id = card.dataset.questionId;
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = `tempos[${id}]`; input.value = '';
        form.append(input); inputs.set(id, input);
        observer.observe(card);
        card.addEventListener('focusin', () => { account(); active = id; });
    });
    document.addEventListener('visibilitychange', () => { tick = performance.now(); });
    const timer = window.setInterval(account, 1000);
    form.addEventListener('submit', () => {
        account();
        inputs.forEach((input, id) => { input.value = elapsed.has(id) ? (elapsed.get(id) / 1000).toFixed(1) : ''; });
    });
    window.addEventListener('pagehide', () => { clearInterval(timer); observer.disconnect(); }, { once: true });
})();
