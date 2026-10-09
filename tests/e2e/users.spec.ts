import { expect, test } from '@playwright/test';
import { USERS, as } from './helpers';

test('the administrator creates a user who must change the password on first login', async ({ browser }) => {
    const admin = await as(browser, USERS.admin);
    await admin.goto('/admin/usuarios/create');
    await admin.getByLabel('Nombre').fill('Isabel Moreno');
    await admin.getByLabel('Correo').fill('isabel.moreno@cencosud.example');
    await admin.getByRole('radio', { name: 'Sector / Comercial' }).click();
    await admin.getByRole('checkbox', { name: 'Bazar' }).click();
    await expect(admin.getByText('solo podrá comentar y aprobar las páginas de Bazar')).toBeVisible();
    await admin.getByLabel('Contraseña inicial').fill('Inicial-2026');
    await admin.getByRole('button', { name: 'Crear usuario' }).click();
    await expect(admin.getByText('Usuario Isabel Moreno creado')).toBeVisible();
    await expect(admin.getByText('Pendiente 1er ingreso')).toBeVisible();

    const isabel = await as(browser, 'isabel.moreno@cencosud.example', 'Inicial-2026');
    await expect(isabel).toHaveURL(/\/password\/force$/);
    await isabel.getByLabel('Contraseña que te entregaron').fill('Inicial-2026');
    await isabel.getByLabel('Nueva contraseña', { exact: true }).fill('MiClave-2026');
    await isabel.getByLabel('Repite la nueva contraseña').fill('MiClave-2026');
    await isabel.getByRole('button', { name: 'Guardar y entrar' }).click();
    await expect(isabel).toHaveURL(/\/catalogos$/);
    await expect(isabel.getByText('Contraseña actualizada')).toBeVisible();
});

test('a commercial sees other sectors greyed out and cannot act on them', async ({ browser }) => {
    const hernan = await as(browser, USERS.hernan);
    await hernan.goto('/catalogos/jumbo-octavilla-18-20-sep/paginas/7'); // DPH
    await expect(hernan.getByText('otra categoría', { exact: false }).first()).toBeVisible();
    await expect(hernan.getByRole('button', { name: 'Comentar región' })).toBeDisabled();
});
