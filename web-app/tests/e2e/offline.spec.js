import { expect, test } from '@playwright/test';

test('keeps a POS write in the outbox while the browser is offline', async ({ page, context }) => {
    await page.goto('/');
    await context.setOffline(true);
    await page.getByRole('button', { name: 'Queue' }).click();
    await expect(page.locator('#count')).toHaveText('1');

    await context.setOffline(false);
    await expect(page.locator('#count')).toHaveText('1');
});
