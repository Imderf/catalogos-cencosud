import { type Browser, type Page, expect } from '@playwright/test';

export const USERS = {
    admin: 'admin@ogilvy.example',
    daniela: 'daniela.pardo@ogilvy.example', // agencia
    valeria: 'valeria.ortiz@cencosud.example', // marketing
    tomas: 'tomas.rincon@cencosud.example', // planificación
    hernan: 'hernan.castro@cencosud.example', // comercial · food
};

/** Opens a fresh session for the given user (one browser context per person). */
export async function as(browser: Browser, email: string, password = 'password'): Promise<Page> {
    const page = await browser.newContext().then((c) => c.newPage());
    await page.goto('/login');
    await page.getByLabel('Correo').fill(email);
    await page.getByLabel('Contraseña', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Ingresar' }).click();
    await expect(page).not.toHaveURL(/\/login$/);
    return page;
}

export const METRO = '/catalogos/metro-octavilla-18-20-sep/paginas';

/** A 1×1 PNG, enough for the image validator. */
export const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64');
