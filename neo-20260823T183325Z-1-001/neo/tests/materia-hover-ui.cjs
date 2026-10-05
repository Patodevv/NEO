const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '..');
const php = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const mime = { '.css': 'text/css', '.js': 'text/javascript', '.png': 'image/png', '.svg': 'image/svg+xml', '.webp': 'image/webp', '.woff2': 'font/woff2' };

(async () => {
    const browser = await chromium.launch({ headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || 'msedge' });
    try {
        const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, reducedMotion: 'no-preference' });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('http://neo.test/**', route => {
            const file = path.resolve(root, '.' + decodeURIComponent(new URL(route.request().url()).pathname));
            assert(file.startsWith(root + path.sep));
            if (file.endsWith('.php')) {
                const html = execFileSync(php, [path.join(__dirname, 'welcome-render.php'), path.basename(file, '.php')], { encoding: 'utf8' });
                return route.fulfill({ contentType: 'text/html; charset=utf-8', body: html });
            }
            if (!fs.existsSync(file)) return route.fulfill({ status: 404, body: '' });
            return route.fulfill({ contentType: mime[path.extname(file)] || 'application/octet-stream', body: fs.readFileSync(file) });
        });

        await page.goto('http://neo.test/materias.php');
        const subject = page.locator('.subject-item').first();
        await subject.locator('.subject').hover();
        await page.waitForTimeout(180);
        const semantic = await subject.evaluate(item => ({
            icon: getComputedStyle(item.querySelector('.materia-icon')).color,
            star: getComputedStyle(item.querySelector('.icon-hover-star-core')).fill,
        }));
        assert.equal(semantic.star, semantic.icon);

        await page.locator('[data-subject-add-open]').click();
        await page.locator('.subject-request-submit').hover();
        await page.waitForTimeout(180);
        assert.equal(await page.locator('.subject-request-submit .icon-hover-star-core').evaluate(node => getComputedStyle(node).fill), 'rgb(26, 46, 255)');
        assert.equal(await page.locator('.subject-request-submit .icon-hover-star').evaluate(node => getComputedStyle(node).opacity), '1');
        await page.locator('.subject-request-close').click();
        await page.locator('[data-subject-request-modal]').waitFor({ state: 'hidden' });

        const deleteToggle = page.locator('[data-subject-delete-toggle]');
        await deleteToggle.click();
        await subject.locator('.subject').click();
        await deleteToggle.click();
        await page.locator('.subject-delete-confirm').hover();
        await page.waitForTimeout(180);
        assert.equal(await page.locator('.subject-delete-confirm .icon-hover-star-core').evaluate(node => getComputedStyle(node).fill), 'rgb(255, 59, 92)');
        assert.equal(await page.locator('.subject-delete-confirm .icon-hover-star').evaluate(node => getComputedStyle(node).opacity), '1');

        await page.goto('http://neo.test/index.php');
        const shop = page.locator('.shop-panel');
        await shop.hover();
        await page.waitForTimeout(220);
        const hoverBounds = await page.evaluate(() => {
            const grid = document.querySelector('.dashboard-hero-grid').getBoundingClientRect();
            const banner = document.querySelector('.shop-panel').getBoundingClientRect();
            return { gridTop: grid.top, bannerTop: banner.top };
        });
        assert(hoverBounds.bannerTop >= hoverBounds.gridTop);
        assert.deepEqual(errors, []);
        console.log('PASS: semantic subject stars, blue/red modal stars and unclipped store hover.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
