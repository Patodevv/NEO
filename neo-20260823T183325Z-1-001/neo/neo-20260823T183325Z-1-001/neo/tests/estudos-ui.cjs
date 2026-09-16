const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const artifacts = path.resolve(root, '../.codex-tmp/estudos');
fs.mkdirSync(artifacts, { recursive: true });

(async () => {
    const browser = await chromium.launch({ headless: true, channel: 'msedge' });
    try {
        const viewports = [{ width: 1440, height: 900 }, { width: 390, height: 844 }, { width: 320, height: 568 }];
        for (const viewport of viewports.filter(size => !process.env.STUDY_WIDTHS || process.env.STUDY_WIDTHS.split(',').includes(String(size.width)))) {
            const context = await browser.newContext({ viewport, reducedMotion: 'reduce' });
            const page = await context.newPage();
            const errors = [];
            const requests = [];
            let user = '101';
            let responseMode = 'success';
            page.on('pageerror', error => errors.push(error.message));
            await page.route('http://neo.test/**', async route => {
                const file = path.resolve(root, '.' + new URL(route.request().url()).pathname);
                assert(file.startsWith(root + path.sep));
                if (file.endsWith('manel.php')) {
                    requests.push(route.request().postDataJSON());
                    await new Promise(resolve => setTimeout(resolve, 350));
                    if (responseMode === 'error') return route.fulfill({ status: 502, contentType: 'application/json', body: JSON.stringify({ reply: 'O site não liberou a leitura.' }) });
                    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ study: {
                        title: 'Ecologia: relações entre os seres vivos', summary: 'Resumo autoral para estudar relações ecológicas.\n\n<script>window.UNSAFE=true</script>', provider: 'OP', sources: [{ title: 'Fonte consultada', url: 'https://example.org/ecologia' }],
                        questions: [{ prompt: 'Qual relação beneficia os dois organismos?', options: ['Predação', 'Mutualismo', 'Competição', 'Parasitismo'], answer: 1, explanation: 'No mutualismo, ambos se beneficiam.' }, { prompt: 'Qual alternativa descreve a predação?', options: ['Um organismo se alimenta de outro.', 'Ambos se beneficiam.', 'Nenhum é afetado.', 'Ambos produzem seu alimento.'], answer: 0, explanation: 'O predador se alimenta da presa.' }],
                    } }) });
                }
                if (file.endsWith('.php')) {
                    const body = execFileSync('C:/xampp/php/php.exe', [path.join(__dirname, 'welcome-render.php'), path.basename(file, '.php'), '', user], { encoding: 'utf8' });
                    assert(!body.includes('Fatal error') && !body.includes('Warning:'), body);
                    return route.fulfill({ contentType: 'text/html; charset=utf-8', body });
                }
                if (!fs.existsSync(file)) return route.fulfill({ status: 404, body: '' });
                return route.fulfill({ contentType: ({ '.css': 'text/css', '.js': 'text/javascript', '.html': 'text/html', '.png': 'image/png' })[path.extname(file)] || 'application/octet-stream', body: fs.readFileSync(file) });
            });
            async function unlocked() { await page.waitForFunction(() => !document.body.classList.contains('neo-interface-locked')); }
            async function fit() { assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), false, 'Horizontal overflow'); }
            await page.goto('http://neo.test/materias.php');
            await unlocked();
            await page.locator('[data-mobile-preview-toggle]').click();
            const preview = page.frameLocator('.neo-mobile-preview-viewport');
            await preview.locator('[data-mobile-preview-toggle]').waitFor({ state: 'visible' });
            await preview.locator('[data-mobile-preview-toggle]').click();
            await page.waitForSelector('.neo-mobile-preview-stage', { state: 'detached' });
            assert.equal(await page.locator('[data-mobile-preview-toggle]').getAttribute('aria-pressed'), 'false');
            const heading = await page.locator('.user-heading').boundingBox();
            const actions = await page.locator('.topbar-actions').boundingBox();
            assert(heading.x + heading.width <= actions.x, 'The title must not overlap the preview button.');
            if (viewport.width <= 760) {
                assert(await page.locator('#sidebarToggle').isHidden());
                const nav = await page.locator('#sidebar').boundingBox();
                assert(nav.y > viewport.height - 90 && nav.width > viewport.width - 25);
                for (const link of await page.locator('#sidebar a.nav-btn').all()) {
                    assert(await link.isVisible());
                    const rect = await link.boundingBox();
                    assert(rect.x >= 0 && rect.x + rect.width <= viewport.width);
                }
                assert(await page.locator('.cossas-pill').isVisible());
            } else {
                assert(await page.locator('#sidebarToggle').isVisible());
                await page.locator('#sidebarToggle').click();
                assert(await page.locator('#sidebar').evaluate(el => el.classList.contains('open')));
            }
            await page.screenshot({ path: path.join(artifacts, `${viewport.width}-materias.png`), fullPage: true });
            assert.equal(await page.locator('.external-studies-link .materia-icon').evaluate(el => getComputedStyle(el).color), 'rgb(26, 46, 255)', 'External studies icon must use NEO blue.');
            await page.locator('.external-studies-link').click();
            await page.waitForURL('**/estudos.php');
            assert.equal(await page.locator('.study-heading [data-study-starter]').count(), 3);
            assert.equal(await page.locator('[data-study-empty], .study-manel-name').count(), 0);
            const composer = await page.locator('[data-study-form]').boundingBox();
            assert(composer.y >= 0 && composer.y + composer.height <= viewport.height, 'Composer must remain in the viewport');
            assert.equal(await page.locator('[data-neo-companion]').count(), 1, 'Only the original face should be rendered');
            assert.equal(await page.locator('.study-shell [data-neo-companion]').count(), 1, 'The original face belongs inside the study balloon');
            assert(await page.locator('[data-neo-companion]').evaluate(el => el.classList.contains('is-smiling')), 'Central Manel must keep its smiling state.');
            assert((await page.locator('.neo-companion-mouth').getAttribute('d')).includes('Q'), 'Central Manel must display a curved smile.');
            const shell = await page.locator('.study-shell').boundingBox();
            const companion = await page.locator('[data-neo-companion]').boundingBox();
            const headingBottom = await page.locator('.study-heading').boundingBox();
            assert(companion.x >= shell.x && companion.x + companion.width <= shell.x + shell.width, 'Face must fit the balloon');
            assert(companion.y >= headingBottom.y + headingBottom.height, 'Face must not cover the header controls');
            await page.locator('[data-neo-companion]').press('Enter');
            assert(await page.locator('[data-manel-panel]').isHidden(), 'The embedded face must not open a duplicate chat');
            assert(await page.locator('#study-request').evaluate(el => el === document.activeElement));
            await page.evaluate(() => document.dispatchEvent(new CustomEvent('neo:manel-face-state', { detail: { state: 'thinking' } })));
            assert(await page.locator('[data-neo-companion]').evaluate(el => el.classList.contains('is-thinking')), 'The original thinking state must work');
            await page.evaluate(() => document.dispatchEvent(new CustomEvent('neo:manel-face-state', { detail: { state: 'neutral' } })));
            if (viewport.width <= 760) {
                const dock = await page.locator('#sidebar').boundingBox();
                assert(composer.y + composer.height <= dock.y, 'Navigation must not cover the composer');
            }
            await page.locator('[data-study-starter]').first().click();
            assert.equal(await page.locator('#study-request').inputValue(), 'Quero estudar ');
            assert.equal(requests.length, 0, 'A suggestion must not submit automatically');
            await page.screenshot({ path: path.join(artifacts, `${viewport.width}-empty.png`), fullPage: true });
            await fit();
            await page.locator('#study-request').fill('Quero estudar ecologia: https://example.org/ecologia');
            await page.locator('#study-quantity').selectOption('2');
            await page.locator('#study-request').press('Shift+Enter');
            assert.equal(requests.length, 0, 'Shift+Enter must not submit');
            await page.locator('#study-request').press('Enter');
            await page.waitForSelector('[data-neo-companion].is-thinking');
            assert.equal(await page.locator('[data-study-loading]').count(), 0);
            await page.waitForSelector('.study-book');
            assert.equal(requests[0].userId, 101);
            assert.equal(requests[0].quantity, 2);
            assert.equal(requests[0].mode, 'study');
            assert.equal(await page.evaluate(() => window.UNSAFE), undefined, 'Untrusted markup must not execute');
            assert.equal(await page.locator('.study-provider').textContent(), 'OP');
            await page.locator('[data-study-back]').click();
            assert(await page.locator('[data-study-messages]').isHidden());
            assert.equal(await page.locator('.study-thread > .study-face-slot').count(), 1);
            await page.locator('[data-study-back]').click();
            assert(await page.locator('[data-study-messages]').isVisible());
            assert.equal(await page.locator('.study-book').count(), 1, 'Back must preserve the study');
            await page.locator('.study-quiz button').click();
            assert.equal(await page.locator('.study-score').count(), 0, 'Incomplete quizzes must not be graded');
            await page.locator('.study-question').nth(0).locator('input').nth(1).check();
            await page.locator('.study-question').nth(1).locator('input').nth(2).check();
            await page.locator('.study-quiz button').click();
            assert.equal(await page.locator('.study-score').textContent(), 'Resultado 1/2');
            assert.equal(await page.locator('.is-correct').count(), 2);
            await fit();
            await page.screenshot({ path: path.join(artifacts, `${viewport.width}-quiz.png`), fullPage: true });
            await page.locator('.study-quiz button').click();
            assert.equal(await page.locator('.study-explanation').count(), 0);
            assert.equal(await page.locator('.study-quiz input:disabled').count(), 0);
            responseMode = 'error';
            await page.locator('#study-request').fill('Quero aprofundar esse assunto');
            await page.locator('[data-study-send]').click();
            await page.waitForSelector('[data-study-error]:not([hidden])');
            assert.equal(await page.locator('#study-request').inputValue(), 'Quero aprofundar esse assunto');
            assert.equal(await page.locator('.study-book').count(), 1);
            assert(requests[1].history.some(item => item.content.includes('ecologia')));
            await page.locator('[data-study-send]').click();
            await page.waitForSelector('[data-study-cancel]:not([hidden])');
            await page.locator('[data-study-cancel]').click();
            await page.waitForFunction(() => !document.querySelector('#study-request').disabled);
            assert.equal(await page.locator('[data-study-error]').textContent(), 'Pedido cancelado.');
            assert.equal(await page.locator('.study-book').count(), 1);
            await page.locator('[data-study-send]').hover();
            await page.waitForTimeout(250);
            assert.equal(await page.locator('[data-study-send] .icon-hover-star').evaluate(el => getComputedStyle(el).opacity), '1');
            user = '202';
            await page.goto('http://neo.test/estudos.php');
            assert.equal(await page.locator('.study-book').count(), 0);
            user = '101';
            await page.goto('http://neo.test/estudos.php');
            assert.equal(await page.locator('.study-book').count(), 0);
            await page.locator('[data-study-new]').click();
            assert.equal(await page.locator('.study-book').count(), 0);
            responseMode = 'success';
            await page.emulateMedia({ reducedMotion: 'no-preference' });
            await page.locator('#study-quantity').selectOption('2');
            await page.locator('#study-request').fill('Quero estudar ecologia novamente');
            const largeFace = await page.locator('[data-neo-companion]').boundingBox();
            await page.locator('[data-study-send]').click();
            await page.waitForSelector('.study-page.is-replying');
            await page.waitForTimeout(180);
            const movingFace = await page.locator('[data-neo-companion]').boundingBox();
            assert(movingFace.width < largeFace.width, 'Face should shrink toward the corner');
            await page.waitForSelector('.is-study-talking');
            assert.equal(await page.locator('.study-heading [data-neo-companion]').count(), 1);
            const firstText = await page.locator('.study-summary').textContent();
            assert(firstText.length < 120, 'The new reply must type progressively');
            const mouths = new Set();
            for (let i = 0; i < 5; i++) {
                mouths.add(await page.locator('.neo-companion-mouth').getAttribute('d'));
                await page.waitForTimeout(90);
            }
            assert(mouths.size > 1, 'The mouth must move during typing');
            await page.screenshot({ path: path.join(artifacts, `${viewport.width}-speaking.png`) });
            if (viewport.width === 1440) await page.waitForSelector('.study-page:not(.is-replying)', { timeout: 18000 });
            else await page.locator('[data-study-cancel]').click();
            await page.waitForFunction(() => !document.querySelector('#study-request').disabled);
            assert.equal(await page.locator('.is-study-talking, .is-typing-line').count(), 0);
            assert.equal(await page.locator('.neo-companion-mouth').getAttribute('d'), 'M 128 147 Q 150 166 172 147');
            assert.equal(await page.locator('[data-study-messages]').evaluate(el => el.inert), false);
            await page.reload();
            await unlocked();
            assert.equal(await page.locator('.is-replying, .is-study-talking').count(), 0, 'Reload must not replay the reply');
            assert.equal(await page.locator('.study-summary, .study-user').count(), 0, 'Reload must clear the conversation');
            assert.equal(await page.locator('.study-thread > .study-face-slot').count(), 1);
            assert.equal(await page.locator('.neo-companion-mouth').getAttribute('d'), 'M 128 147 Q 150 166 172 147');
            await page.locator('a[aria-label="Voltar às matérias"]').click();
            await page.waitForSelector('.study-face-flight');
            const departingLarge = await page.locator('.study-face-flight').boundingBox();
            await page.waitForTimeout(180);
            const departingSmall = await page.locator('.study-face-flight').boundingBox();
            assert(departingSmall.width < departingLarge.width && departingSmall.x > departingLarge.x && departingSmall.y < departingLarge.y, 'Manel must shrink while moving to the upper-right corner before leaving.');
            await page.waitForURL('**/materias.php');
            await page.locator('.external-studies-link').click();
            await page.waitForURL('**/estudos.php');
            await page.waitForSelector('.study-face-flight');
            const arrivingSmall = await page.locator('.study-face-flight').boundingBox();
            await page.waitForTimeout(260);
            const arrivingLarge = await page.locator('.study-face-flight').boundingBox();
            assert(arrivingLarge.width > arrivingSmall.width && arrivingLarge.x < arrivingSmall.x, 'Manel must grow from the corner toward the center on every entry.');
            await page.waitForSelector('.study-face-flight', { state: 'detached' });
            assert.deepEqual(errors, []);
            console.log(`PASS ${viewport.width}: blue icon, smiling Manel, entry/exit flight, study flow, reload clearing, account isolation and XSS.`);
            await context.close();
        }
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
