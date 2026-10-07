const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '..');
const artifacts = path.resolve(root, '../.codex-tmp/estudos-external-fixes');
fs.mkdirSync(artifacts, { recursive: true });

(async () => {
    const browser = await chromium.launch({ headless: true, channel: 'msedge' });
    try {
        const viewports = [
            { width: 1725, height: 767 },
            { width: 1440, height: 900 },
            { width: 390, height: 844 },
            { width: 320, height: 568 },
        ].filter(size => !process.env.STUDY_FIX_WIDTHS || process.env.STUDY_FIX_WIDTHS.split(',').includes(String(size.width)));
        for (const viewport of viewports) {
            const context = await browser.newContext({ viewport, reducedMotion: 'reduce' });
            const page = await context.newPage();
            const pageErrors = [];
            const requests = [];
            page.on('pageerror', error => pageErrors.push(error.message));

            await page.route('http://neo.test/**', async route => {
                const file = path.resolve(root, '.' + new URL(route.request().url()).pathname);
                assert(file.startsWith(root + path.sep));
                if (file.endsWith('manel.php')) {
                    requests.push(route.request().postDataJSON());
                    await new Promise(resolve => setTimeout(resolve, 800));
                    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ study: {
                        title: 'Resumo do site',
                        summary: 'Resumo claro e organizado a partir do conteúdo enviado.',
                        provider: 'OP',
                        sources: [{ title: 'Fonte enviada', url: 'https://example.org/artigo' }],
                        questions: [
                            { prompt: 'Qual é a ideia principal?', options: ['Ideia A', 'Ideia B', 'Ideia C', 'Ideia D'], answer: 0, explanation: 'A ideia A sintetiza o texto.' },
                            { prompt: 'Qual detalhe reforça o resumo?', options: ['Detalhe A', 'Detalhe B', 'Detalhe C', 'Detalhe D'], answer: 1, explanation: 'O detalhe B aparece na fonte.' },
                        ],
                    } }) });
                }
                if (file.endsWith('ai_status.php')) {
                    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, estado: 'pronto', segundos: 0, porcentagem: 100, rotulo: '100%', titulo: 'IA liberada.' }) });
                }
                if (file.endsWith('estudos.php')) {
                    const body = execFileSync('C:/xampp/php/php.exe', [path.join(__dirname, 'welcome-render.php'), 'estudos', '', '101'], { encoding: 'utf8' });
                    assert(!body.includes('Fatal error') && !body.includes('Warning:'), body);
                    return route.fulfill({ contentType: 'text/html; charset=utf-8', body });
                }
                if (!fs.existsSync(file)) return route.fulfill({ status: 404, body: '' });
                const types = { '.css': 'text/css', '.js': 'text/javascript', '.png': 'image/png', '.webp': 'image/webp' };
                return route.fulfill({ contentType: types[path.extname(file)] || 'application/octet-stream', body: fs.readFileSync(file) });
            });

            await page.goto('http://neo.test/estudos.php');
            await page.waitForFunction(() => !document.body.classList.contains('neo-interface-locked'));
            const shell = await page.locator('.study-shell').boundingBox();
            const controls = await page.locator('.study-heading .neo-icon-button:visible').evaluateAll(nodes => nodes.map(node => {
                const rect = node.getBoundingClientRect();
                return { left: rect.left, right: rect.right };
            }));
            const left = Math.min(...controls.map(control => control.left));
            const right = Math.max(...controls.map(control => control.right));
            assert(Math.abs((left + right) / 2 - (shell.x + shell.width / 2)) <= 2, `${viewport.width}: top controls are not centered`);
            assert(left >= shell.x && right <= shell.x + shell.width, `${viewport.width}: top controls are clipped`);

            await page.screenshot({ path: path.join(artifacts, `${viewport.width}-initial.png`), fullPage: true });
            const siteButton = page.locator('[data-study-kind="site"]');
            assert(await siteButton.isVisible(), `${viewport.width}: site button must be visible and clickable`);
            await siteButton.click();
            await page.locator('[data-study-send]').click();
            assert((await page.locator('[data-study-error]').textContent()).includes('Cole o link do site'));
            assert.equal(requests.length, 0);
            assert.notEqual(await page.locator('[data-study-error]').evaluate(node => getComputedStyle(node).borderStyle), 'none');

            await page.locator('#study-request').fill('example.org/artigo');
            await page.locator('#study-quantity').selectOption('2');
            await page.locator('[data-study-send]').click();
            await page.waitForSelector('[data-study-status]:not([hidden])');
            assert((await page.locator('[data-study-status]').textContent()).includes('Abrindo o site'));
            await page.waitForSelector('.study-book');
            assert.equal(requests[0].intent, 'site');
            assert(requests[0].message.includes('https://example.org/artigo'));
            assert(await page.locator('[data-study-status]').isHidden());
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), false, `${viewport.width}: horizontal overflow`);
            assert.deepEqual(pageErrors, []);

            await page.screenshot({ path: path.join(artifacts, `${viewport.width}.png`), fullPage: true });
            console.log(`PASS ${viewport.width}: centered controls and external-site feedback.`);
            await context.close();
        }
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
