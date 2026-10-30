// Run after: npm install --prefix .tools/browser --no-package-lock playwright
// Start the site separately on 127.0.0.1:8765.
import { chromium } from '../.tools/browser/node_modules/playwright/index.mjs';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';

const base = process.env.SITE_URL || 'http://127.0.0.1:8765';
const browser = await chromium.launch({
    headless: true,
    channel: process.env.BROWSER_CHANNEL || 'chrome',
});
const errors = [];
const failures = [];
const context = await browser.newContext({ viewport: { width: 1365, height: 900 } });
const page = await context.newPage();
page.on('pageerror', error => errors.push(error.message));
page.on('response', response => {
    if (response.url().startsWith(base) && response.status() >= 400) failures.push(`${response.status()} ${response.url()}`);
});
await mkdir('.tools/screenshots', { recursive: true });

try {
    for (const path of ['/', '/catalogue', '/contact', '/connexion', '/suivre-ma-commande', '/admin/login']) {
        const response = await page.goto(base + path, { waitUntil: 'domcontentloaded' });
        assert.equal(response.status(), 200, path);
        console.log(`OK ${path}`);
    }
    await page.goto(base, { waitUntil: 'domcontentloaded' });
    await page.locator('.hero > img').evaluate(image => image.decode());
    assert.match(await page.locator('.hero > img').evaluate(image => image.currentSrc), /\.webp$/);
    const productUrl = await page.locator('.pcard .media').first().getAttribute('href');
    const tabs = page.locator('.ctab');
    if (await tabs.count() > 1) {
        await tabs.nth(1).click();
        assert.ok(await page.locator('#shopTrack .slide[hidden]').count() > 0);
    }
    await page.screenshot({ path: '.tools/screenshots/home-desktop.png' });

    await page.goto(base + '/catalogue', { waitUntil: 'domcontentloaded' });
    await page.locator('.sort select').selectOption('prix_asc');
    await page.waitForURL(url => url.searchParams.getAll('tri').includes('prix_asc'));
    assert.equal(await page.locator('.sort select').inputValue(), 'prix_asc');

    await page.goto(productUrl, { waitUntil: 'domcontentloaded' });
    await page.locator('#mainPic').evaluate(image => image.decode());
    const thumbs = page.locator('.thumbs button');
    if (await thumbs.count() > 1) {
        const original = await page.locator('#mainPic').getAttribute('src');
        await thumbs.nth(1).click();
        await page.locator('#mainPic').evaluate(image => image.decode());
        assert.notEqual(await page.locator('#mainPic').getAttribute('src'), original);
    }
    const size = page.locator('[data-size]:not([data-size="custom"]):not([disabled])').first();
    if (await size.count()) await size.click();
    await page.locator('#qty').fill('999');
    await page.locator('#orderBtn').click();
    await page.locator('#orderModal.open').waitFor();
    const quantity = Number(await page.locator('#fQty').inputValue());
    assert.ok(quantity >= 1 && quantity <= 10);
    await page.keyboard.press('Escape');
    await page.locator('#orderModal.open').waitFor({ state: 'hidden' });
    await page.screenshot({ path: '.tools/screenshots/product-desktop.png' });
    console.log('OK gallery, sizes, quantity limits and order dialog');

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(base, { waitUntil: 'domcontentloaded' });
    await page.locator('.hero > img').evaluate(image => image.decode());
    await page.locator('.burger').click();
    assert.ok(await page.locator('#nav.open').count());
    await page.locator('.burger').click();
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
    assert.equal(overflow, false, 'No horizontal overflow on mobile');
    await page.screenshot({ path: '.tools/screenshots/home-mobile.png' });
    console.log('OK mobile menu and layout');
    assert.deepEqual(errors, [], 'JavaScript errors');
    assert.deepEqual(failures, [], 'Failed local resources');
    console.log('Browser checks passed; no JavaScript errors or failed local resources.');
} finally {
    await browser.close();
}
