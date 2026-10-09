import { expect, test } from '@playwright/test';
import { USERS, as } from './helpers';

test('rejects a wrong password and accepts the right one', async ({ page, browser }) => {
    await page.goto('/login');
    await page.getByLabel('Correo').fill(USERS.valeria);
    await page.getByLabel('Contraseña', { exact: true }).fill('incorrecta');
    await page.getByRole('button', { name: 'Ingresar' }).click();
    await expect(page.getByText(/credenciales/i)).toBeVisible();

    const valeria = await as(browser, USERS.valeria);
    await expect(valeria).toHaveURL(/\/catalogos$/);
    await expect(valeria.getByRole('heading', { name: 'Catálogos' })).toBeVisible();
    await expect(valeria.getByText('Valeria Ortiz')).toBeVisible();
});

test('a guest is sent to the login page', async ({ page }) => {
    await page.goto('/catalogos');
    await expect(page).toHaveURL(/\/login$/);
});
