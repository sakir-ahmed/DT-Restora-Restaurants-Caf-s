import { expect, test } from '@playwright/test';

test.use({ serviceWorkers: 'allow' });

test('installed app opens offline, falls back to the offline page, and forgets screens at sign out', async ({ page, context }) => {
    await page.goto('/login');
    await page.locator('input[name="login"]').fill('cashier');
    await page.locator('input[name="password"]').fill('password');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await page.waitForURL(/\/admin/);

    // The worker installs from /sw.js with root scope and stores the shell and the offline page.
    await page.goto('/admin/pos');
    await page.evaluate(() => navigator.serviceWorker.ready);
    await page.reload();
    await expect.poll(() => page.evaluate(() => navigator.serviceWorker.controller !== null)).toBe(true);

    const registration = await page.evaluate(async () => {
        const worker = await navigator.serviceWorker.getRegistration();

        return { scope: worker.scope, script: worker.active.scriptURL };
    });

    expect(registration.script).toMatch(/\/sw\.js$/);
    expect(registration.scope).toMatch(/\/$/);

    const manifest = await page.evaluate(async () => (await fetch(document.querySelector('link[rel=manifest]').href)).json());

    expect(manifest.display).toBe('standalone');
    expect(manifest.icons.map((icon) => icon.purpose)).toContain('maskable');

    // A screen opened once keeps opening with no network.
    await context.setOffline(true);
    await page.reload();
    await expect(page.getByRole('radio', { name: 'Takeaway' })).toBeVisible();

    // A screen never opened on this device shows the offline page, not the browser error.
    await page.goto('/admin/reservations');
    await expect(page.getByRole('heading', { name: 'You are offline' })).toBeVisible();

    // Signing out removes the saved screens of this session.
    await context.setOffline(false);
    await page.goto('/admin/pos');
    await page.locator('header button:has(.rounded-full)').last().click();
    await page.getByRole('button', { name: 'Sign out' }).click();
    await page.waitForURL(/\/login/);

    const cached = await page.evaluate(() => caches.keys());

    expect(cached).not.toContain('pages');
    expect(cached).not.toContain('screens');
});
