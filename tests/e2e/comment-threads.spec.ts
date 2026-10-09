import { expect, test } from '@playwright/test';
import { USERS, as } from './helpers';

const SHOTS = process.env.SHOTS_DIR;

test("a comment can be answered and one's own comment edited from the card modal", async ({ browser }) => {
    const daniela = await as(browser, USERS.daniela);
    await daniela.goto('/proyectos');
    await daniela.getByRole('button', { name: 'Nuevo proyecto' }).first().click();
    await daniela.getByLabel('Nombre').fill('Hilos E2E');
    await daniela.getByRole('button', { name: 'Crear proyecto' }).click();
    await expect(daniela.getByRole('heading', { name: 'Hilos E2E' })).toBeVisible();
    await daniela.getByRole('button', { name: 'Agregar tarjeta' }).first().click();
    await daniela.getByPlaceholder('Título de la tarjeta').fill('Tarjeta con hilo');
    await daniela.getByPlaceholder('Título de la tarjeta').press('Enter');
    await expect(daniela.getByText('Tarjeta creada.')).toBeVisible();
    await daniela.getByRole('button', { name: 'Tarjeta con hilo' }).click();
    const modal = daniela.getByRole('dialog');

    // while typing, a picked mention shows as a readable chip, never as @[Nombre](id)
    await modal.getByPlaceholder('Escribe un comentario…').fill('Primer comentario @Lau');
    await modal.getByRole('button', { name: 'LM Daniela Pardo', exact: true }).click();
    await expect(modal.getByPlaceholder('Escribe un comentario…')).toHaveValue('Primer comentario @Daniela Pardo ');
    if (SHOTS) await daniela.screenshot({ path: `${SHOTS}/mention-typing.png` });
    await modal.getByPlaceholder('Escribe un comentario…').press('Enter');
    await expect(modal.locator('p', { hasText: 'Primer comentario' })).toBeVisible();
    await expect(modal.locator('p', { hasText: 'Primer comentario' }).getByText('@Daniela Pardo')).toBeVisible();

    // reply: the composer shows whom it answers and the reply hangs under the comment
    await modal.getByRole('button', { name: 'Responder' }).first().click();
    await expect(modal.getByText(/Respondiendo a/)).toBeVisible();
    await modal.getByPlaceholder(/Responder a/).fill('Una respuesta');
    await modal.getByPlaceholder(/Responder a/).press('Enter');
    await expect(modal.locator('p', { hasText: 'Una respuesta' })).toBeVisible();
    await expect(modal.getByText(/Respondiendo a/)).toHaveCount(0);

    // edit: inline box, save, "editado" mark
    await modal.getByRole('button', { name: 'Editar' }).first().click();
    const boxes = modal.locator('textarea'); // the edit box sits right before the composer's own textarea
    const box = boxes.nth((await boxes.count()) - 2);
    await expect(box).toHaveValue('Primer comentario @Daniela Pardo');
    await box.fill('Primer comentario, corregido');
    await modal.getByRole('button', { name: 'Guardar' }).click();
    await expect(modal.getByRole('button', { name: 'Guardar' })).toBeHidden(); // saved: the inline box closes
    await expect(modal.locator('p', { hasText: 'Primer comentario, corregido' })).toBeVisible();
    // the 'editado' mark appears only when the edit comes more than 5 s after the comment; Pest covers the flag
    if (SHOTS) await daniela.screenshot({ path: `${SHOTS}/comments.png` });
});
