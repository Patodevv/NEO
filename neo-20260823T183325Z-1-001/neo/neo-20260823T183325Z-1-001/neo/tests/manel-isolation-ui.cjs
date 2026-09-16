const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');

(async () => {
    const browser = await chromium.launch({ headless: true, channel: 'msedge' });
    try {
        const page = await browser.newPage({ reducedMotion: 'reduce' });
        let user = '101';
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('http://neo.test/**', route => {
            const file = path.resolve(root, '.' + new URL(route.request().url()).pathname);
            assert(file.startsWith(root + path.sep));
            if (file.endsWith('.php')) return route.fulfill({ contentType: 'text/html; charset=utf-8', body: execFileSync('C:/xampp/php/php.exe', [path.join(__dirname, 'welcome-render.php'), path.basename(file, '.php'), '', user], { encoding: 'utf8' }) });
            if (!fs.existsSync(file)) return route.fulfill({ status: 404, body: '' });
            return route.fulfill({ contentType: ({ '.js': 'text/javascript', '.css': 'text/css', '.html': 'text/html' })[path.extname(file)] || 'application/octet-stream', body: fs.readFileSync(file) });
        });
        await page.goto('http://neo.test/index.php');
        await page.evaluate(() => {
            const message = content => JSON.stringify([{ role: 'user', content }]);
            localStorage.setItem('neo_manel_thread_v1', message('LEGACY_PRIVATE'));
            localStorage.setItem('neo_manel_thread_v1_user_101', message('ACCOUNT_A_PRIVATE'));
            localStorage.setItem('neo_manel_opened_v1_user_101', '1');
        });
        async function chat() {
            await page.goto('http://neo.test/index.php');
            return page.locator('[data-manel-messages]').textContent();
        }
        assert((await chat()).includes('ACCOUNT_A_PRIVATE'));
        user = '202';
        const b = await chat();
        assert(!b.includes('ACCOUNT_A_PRIVATE') && !b.includes('LEGACY_PRIVATE'));
        assert(await page.locator('[data-manel-panel]').evaluate(el => el.hidden));
        await page.evaluate(() => {
            localStorage.setItem('neo_manel_thread_v1_user_202', JSON.stringify([{ role: 'user', content: 'ACCOUNT_B_PRIVATE' }]));
            document.dispatchEvent(new CustomEvent('neo:manel-toggle'));
        });
        await page.locator('[data-manel-new]').click();
        user = '101';
        const a = await chat();
        assert(a.includes('ACCOUNT_A_PRIVATE') && !a.includes('ACCOUNT_B_PRIVATE') && !a.includes('LEGACY_PRIVATE'));

        async function seed(step, pageName) {
            await page.evaluate(({ step, pageName }) => sessionStorage.setItem('neo_manel_tour_v2_user_101', JSON.stringify({ step, url: 'http://neo.test/' + pageName, history: Array.from({ length: step }, (_, i) => ({ step: i, url: 'http://neo.test/index.php' })) })), { step, pageName });
            await page.goto('http://neo.test/' + pageName);
            await page.waitForTimeout(400);
        }
        const currentStep = () => page.locator('[data-manel-tour]').getAttribute('data-step');
        await seed(9, 'materias.php');
        assert.equal(await currentStep(), '10');
        assert.equal(await page.locator('[data-tour-count]').count(), 0);
        await page.locator('[data-tour-next]').click();
        assert.equal(await currentStep(), '18');
        await page.waitForTimeout(350);
        await page.locator('[data-tour-back]').click();
        assert.equal(await currentStep(), '10');
        await seed(10, 'conteudos.php');
        await page.locator('.content-row-main').first().click();
        await page.waitForURL('**/livro.php*');
        assert.equal(await currentStep(), '15');
        await page.waitForTimeout(350);
        await page.evaluate(() => { const next = document.querySelector('[data-tour-next]'); next.click(); next.click(); next.click(); });
        assert.equal(await currentStep(), '16');
        assert.deepEqual(errors, []);
        console.log('PASS: isolated accounts, legacy ignored, independent clearing, shortened paths, back, rapid clicks.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
