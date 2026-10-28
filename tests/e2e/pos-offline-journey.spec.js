import { expect, test } from '@playwright/test';

test('cashier sells tea offline, prints the receipt, and syncs it once', async ({ page, context }) => {
    await page.addInitScript(() => {
        window.print = () => {
            document.documentElement.dataset.printed = '1';
        };
    });

    await page.goto('/login');
    await page.locator('input[name="login"]').fill('cashier');
    await page.locator('input[name="password"]').fill('password');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await page.waitForURL(/\/admin/);

    await page.goto('/admin/pos');
    await page.getByRole('radio', { name: 'Takeaway' }).click();
    await expect(page.getByRole('button', { name: /Tea/ })).toBeVisible();

    await context.setOffline(true);
    await page.getByRole('button', { name: /Tea/ }).click();
    await page.getByRole('button', { name: 'Send to kitchen' }).click();

    await expect(page.getByLabel('Offline')).toBeVisible();
    await expect(page.locator('[data-outbox-count]')).toHaveText('1');

    const invoice = (await page.locator('[data-pos-invoice]').innerText()).trim();
    expect(invoice).toMatch(/^BR01-T01-\d{8}-\d{6}-[0-9A-HJKMNP-TV-Z]{4}$/);

    await page.getByRole('button', { name: 'Exact' }).click();
    await page.getByRole('button', { name: 'Confirm payment' }).click();
    await expect(page.locator('[data-outbox-count]')).toHaveText('1');

    await page.getByRole('button', { name: 'Print', exact: true }).click();
    const receipt = page.frameLocator('#receipt-print-frame');
    await expect(receipt.locator('body')).toContainText(invoice);
    await expect(receipt.locator('body')).toContainText('Tea');
    await expect(receipt.locator('body')).toContainText('120.00');

    await context.route('**/api/v1/sync/push', (route) => route.abort());
    await context.setOffline(false);
    await expect(page.locator('[data-outbox-count]')).toHaveText('1');

    await page.close();

    const reopened = await context.newPage();
    await reopened.goto('/admin/pos');
    await expect(reopened.locator('[data-outbox-count]')).toHaveText('1');

    await context.unroute('**/api/v1/sync/push');
    await reopened.evaluate(() => window.dispatchEvent(new Event('online')));
    await expect(reopened.locator('[data-outbox-count]')).toHaveCount(0);

    await reopened.goto(`/admin/orders?search=${invoice}`);
    await expect(reopened.getByText(invoice)).toHaveCount(1);

    await reopened.evaluate(() => window.dispatchEvent(new Event('online')));
    await reopened.goto(`/admin/orders?search=${invoice}`);
    await expect(reopened.getByText(invoice)).toHaveCount(1);
});
