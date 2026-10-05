const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '..');
const artifacts = path.resolve(root, '../.codex-tmp/welcome');
fs.mkdirSync(artifacts, { recursive: true });
const php = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const mime = { '.css': 'text/css', '.js': 'text/javascript', '.html': 'text/html', '.png': 'image/png', '.svg': 'image/svg+xml', '.webp': 'image/webp', '.woff2': 'font/woff2' };

(async () => {
    const browser = await chromium.launch({ headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || 'msedge' });
    try {
        const sizes = [{ width: 1440, height: 900 }, { width: 390, height: 844 }, { width: 320, height: 568 }];
        for (const viewport of sizes.filter(size => !process.env.WELCOME_WIDTHS || process.env.WELCOME_WIDTHS.split(',').includes(String(size.width)))) {
            const context = await browser.newContext({ viewport });
            const page = await context.newPage();
            const errors = [];
            let first = true;
            let posts = 0;
            page.on('pageerror', error => errors.push(error.message));
            await page.route('http://neo.test/**', route => {
                const url = new URL(route.request().url());
                const file = path.resolve(root, '.' + decodeURIComponent(url.pathname));
                assert(file.startsWith(root + path.sep));
                if (route.request().method() === 'POST') posts++;
                if (file.endsWith('.php')) {
                    const html = execFileSync(php, [path.join(__dirname, 'welcome-render.php'), path.basename(file, '.php'), first ? 'first' : ''], { encoding: 'utf8' });
                    first = false;
                    assert(!html.includes('Fatal error') && !html.includes('Warning:'), html);
                    return route.fulfill({ contentType: 'text/html; charset=utf-8', body: html });
                }
                if (!fs.existsSync(file)) return route.fulfill({ status: 404, body: '' });
                return route.fulfill({ contentType: mime[path.extname(file)] || 'application/octet-stream', body: fs.readFileSync(file) });
            });
            await page.goto('http://neo.test/index.php');
            await page.waitForSelector('.neo-face-flight', { timeout: 12000 });
            const start = await page.locator('.neo-face-flight').boundingBox();
            await page.waitForTimeout(250);
            const middle = await page.locator('.neo-face-flight').boundingBox();
            assert(middle.width < start.width && middle.x > start.x, 'The original face must shrink and travel to Manel.');
            await page.screenshot({ path: path.join(artifacts, `${viewport.width}-flight.png`) });
            await page.waitForSelector('[data-manel-tour]:not([hidden])');
            assert.equal(await page.locator('#sidebar').evaluate(el => el.classList.contains('open')), false);
            assert.equal(await page.locator('.neo-face-overlay, .neo-face-flight').count(), 0);

            async function checkStep(number, shot = false) {
                await page.waitForFunction(n => document.querySelector('[data-manel-tour]').dataset.step === String(n), number);
                await page.waitForTimeout(480);
                assert.equal(await page.locator('[data-tour-count]').count(), 0, 'The tutorial must not show a step counter.');
                const bounds = await page.locator('[data-tour-bubble]').boundingBox();
                assert(bounds.x >= 0 && bounds.y >= 0 && bounds.x + bounds.width <= viewport.width + 1 && bounds.y + bounds.height <= viewport.height + 1, `Bubble clipped at ${viewport.width}, step ${number}: ${JSON.stringify(bounds)}`);
                assert(await page.locator('[data-tour-next]').isVisible());
                const button = await page.locator('[data-tour-next]').boundingBox();
                const footer = await page.locator('.manel-tour-controls').boundingBox();
                const label = await page.locator('[data-tour-next-label]').boundingBox();
                const icon = await page.locator('[data-tour-next] > svg:not(.icon-hover-star)').boundingBox();
                assert(Math.abs(button.x - footer.x) < 1 && Math.abs(button.width - footer.width) < 1, 'Next must span both inner margins.');
                assert(button.height >= 56, 'Next must have a generous touch target.');
                assert(Math.abs(label.x + label.width / 2 - button.x - button.width / 2) < 1, 'The label must be centered independently of the arrow.');
                assert(Math.abs(label.y + label.height / 2 - button.y - button.height / 2) < 1 && Math.abs(icon.y + icon.height / 2 - button.y - button.height / 2) < 1, 'Label and arrow must stay vertically centered.');
                const targetSelector = { 3: '.level-bar', 9: '.external-studies-link', 12: '.content-generate-row', 13: '.content-request-row', 17: '.question-controls' }[number];
                if (targetSelector) {
                    const target = await page.locator(targetSelector).boundingBox();
                    const overlaps = bounds.x < target.x + target.width && bounds.x + bounds.width > target.x && bounds.y < target.y + target.height && bounds.y + bounds.height > target.y;
                    if (overlaps) await page.screenshot({ path: path.join(artifacts, `${viewport.width}-overlap-${number}.png`) });
                    assert.equal(overlaps, false, `Speech covers the target at ${viewport.width}, step ${number}: ${JSON.stringify({ bounds, target })}`);
                }
                const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
                assert.equal(overflow, false, `Horizontal overflow at step ${number}`);
                if (shot) await page.screenshot({ path: path.join(artifacts, `${viewport.width}-step-${number}.png`) });
            }
            await checkStep(1, true);
            assert.doesNotMatch(
                await page.locator('[data-tour-speech]').textContent(),
                /(?:eu sou o Manel|assistente virtual|meu nome é Manel)/i,
                'The tutorial must start directly because Manel already introduced himself during registration.'
            );
            const idleWrites = await page.evaluate(() => new Promise(resolve => {
                const writes = [];
                const observer = new MutationObserver(records => { records.forEach(record => writes.push({ element: record.target.className.baseVal || record.target.className, attribute: record.attributeName, old: record.oldValue, current: record.target.getAttribute(record.attributeName) })); });
                observer.observe(document.querySelector('[data-manel-tour]'), { subtree: true, attributes: true, attributeOldValue: true, attributeFilter: ['style', 'd'] });
                for (let i = 0; i < 20; i++) window.dispatchEvent(new Event('scroll'));
                setTimeout(() => { observer.disconnect(); resolve(writes); }, 250);
            }));
            assert.deepEqual(idleWrites, [], 'Unchanged geometry must not trigger repeated DOM writes.');
            for (let number = 2; number <= 10; number++) {
                await page.locator('[data-tour-next]').click();
                await checkStep(number, [3, 7, 8, 9, 10].includes(number));
            }
            await page.locator('.subject').first().click();
            await checkStep(11, true);
            await page.locator('[data-tour-back]').click();
            await checkStep(10);
            await page.locator('.subject').first().click();
            await checkStep(11);
            await page.reload();
            await page.waitForSelector('[data-manel-tour]:not([hidden])', { timeout: 6000 });
            await checkStep(11);
            assert.equal(await page.locator('[data-face-overlay]').count(), 0, 'F5 must not replay the large face.');
            for (let number = 12; number <= 18; number++) {
                await page.locator('[data-tour-next]').click();
                await checkStep(number, [13, 15, 17].includes(number));
            }
            await page.locator('[data-tour-next]').click();
            assert(await page.locator('[data-manel-tour]').isHidden());
            assert.equal(posts, 0, 'The guide must not submit a generation form on its own.');
            await page.reload();
            await page.waitForFunction(() => !document.body.classList.contains('neo-interface-locked'));
            assert(await page.locator('[data-manel-tour]').isHidden(), 'Completed guide must stay closed on F5.');
            const face = await page.locator('[data-neo-companion]').boundingBox();
            await page.mouse.click(face.x + face.width / 2, face.y + face.height / 2);
            await page.locator('[data-manel-tour-start]').click();
            await checkStep(1);
            await page.keyboard.press('Escape');
            assert(await page.locator('[data-manel-tour]').isHidden());
            assert.equal(await page.locator('[data-neo-companion]').evaluate(el => el === document.activeElement), true);

            // An explicit subject click may create its first books; the guide resumes after the POST.
            await page.evaluate(() => {
                sessionStorage.setItem('neo_manel_tour_v2_user_999999', JSON.stringify({ step: 9, url: 'http://neo.test/materias.php', history: Array.from({ length: 9 }, (_, step) => ({ step, url: 'http://neo.test/index.php' })) }));
            });
            await page.goto('http://neo.test/materias.php');
            await checkStep(10);
            await page.locator('form.subject-form .subject').click();
            await checkStep(11);
            assert.equal(posts, 1);
            await page.locator('[data-tour-skip]').click();

            // Reduced motion and a missing intro frame must both leave a usable Dashboard.
            await page.emulateMedia({ reducedMotion: 'reduce' });
            first = true;
            await page.goto('http://neo.test/index.php');
            await checkStep(1);
            assert.equal(await page.locator('.neo-face-flight, [data-face-overlay]').count(), 0);
            await page.locator('[data-tour-skip]').click();
            await page.route('**/rosto_azul.html', route => route.abort());
            first = true;
            await page.goto('http://neo.test/index.php');
            await checkStep(1);
            assert.equal(await page.locator('.neo-face-flight, [data-face-overlay]').count(), 0);
            await page.locator('[data-tour-skip]').click();
            assert.deepEqual(errors, []);
            console.log(`PASS ${viewport.width}x${viewport.height}: morph, counterless 18-step tour, back/reload/replay/skip, explicit subject POST, reduced motion and frame failure recovery.`);
            await context.close();
        }
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
