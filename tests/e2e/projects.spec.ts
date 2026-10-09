import { expect, test } from '@playwright/test';
import { USERS, as } from './helpers';

test('Ogilvy creates a board, works a card in its modal, and the client cannot see projects', async ({ browser }) => {
    const daniela = await as(browser, USERS.daniela);
    await daniela.goto('/proyectos');
    await daniela.getByRole('button', { name: 'Nuevo proyecto' }).first().click();
    await daniela.getByLabel('Nombre').fill('Octavillas E2E');
    await daniela.getByRole('button', { name: 'Crear proyecto' }).click();
    await expect(daniela.getByText('Proyecto creado.')).toBeVisible(); // toast
    await expect(daniela.getByRole('heading', { name: 'Octavillas E2E' })).toBeVisible();

    // default columns, first card by Enter
    await expect(daniela.getByText('Por hacer')).toBeVisible();
    await daniela.getByRole('button', { name: 'Agregar tarjeta' }).first().click();
    await daniela.getByPlaceholder('Título de la tarjeta').fill('Diseñar portada');
    await daniela.getByPlaceholder('Título de la tarjeta').press('Enter');
    await expect(daniela.getByText('Tarjeta creada.')).toBeVisible();

    // the modal saves each field on its own
    await daniela.getByRole('button', { name: 'Diseñar portada' }).click();
    const modal = daniela.getByRole('dialog');
    await expect(modal.getByText('En la lista Backlog')).toBeVisible();
    await modal.locator('input[type="date"]').last().fill('2026-09-15');
    await modal.locator('input[type="date"]').last().blur();
    await modal.getByRole('button', { name: 'Asignar persona…' }).click();
    await modal.getByRole('button', { name: 'LM Daniela Pardo tú' }).click();
    await expect(modal.getByText('tú')).toBeVisible();

    // comment with a mention (Enter sends), then a link attachment
    await modal.getByPlaceholder('Escribe un comentario…').fill('Revisar el logo @Lau');
    await modal.getByRole('button', { name: 'LM Daniela Pardo', exact: true }).click();
    await modal.getByPlaceholder('Escribe un comentario…').press('Enter');
    await expect(modal.locator('p', { hasText: '@Daniela Pardo' })).toBeVisible(); // published, as a chip
    await modal.getByRole('button', { name: 'Agregar enlace' }).click();
    await modal.getByPlaceholder('https://…').fill('https://drive.google.com/x');
    await modal.getByRole('button', { name: 'Agregar', exact: true }).click();
    await expect(daniela.getByText('Adjunto agregado.')).toBeVisible();
    // subtasks: add one, move its status, open its detail
    await modal.getByRole('button', { name: 'Agregar subtarea' }).click();
    await modal.getByPlaceholder('Nombre de la subtarea').fill('FAQ AKI');
    await modal.getByPlaceholder('Nombre de la subtarea').press('Enter');
    await expect(modal.getByText('Subtareas · 1 abierta')).toBeVisible();
    await modal.getByTitle('Pendiente').click();
    await modal.getByRole('button', { name: 'En proceso' }).click();
    await expect(modal.getByTitle('En proceso')).toBeVisible();
    await modal.getByRole('button', { name: 'FAQ AKI' }).click();
    const sub = daniela.getByRole('dialog').last();
    await expect(sub.getByText('Subtarea de Diseñar portada')).toBeVisible();
    await sub.getByRole('button', { name: 'Cerrar subtarea' }).click();
    await expect(daniela.getByText('Subtarea de Diseñar portada')).toBeHidden();
    await modal.getByRole('button', { name: 'Cerrar', exact: true }).click();
    await expect(daniela.getByRole('dialog')).toHaveCount(0);
    await expect(daniela.getByText('15 sep 2026')).toBeVisible();

    // manager settings: a label and a custom field
    await daniela.getByRole('button', { name: 'Configuración' }).click();
    await daniela.getByRole('tab', { name: /Etiquetas/ }).click();
    await daniela.getByPlaceholder('Nombre de la etiqueta').fill('Jumbo');
    await daniela.getByRole('button', { name: 'Crear', exact: true }).click();
    await expect(daniela.getByText('Etiqueta creada.')).toBeVisible();
    await daniela.getByRole('tab', { name: /Campos/ }).click();
    await daniela.getByLabel('Nombre').fill('Formato');
    await daniela.getByRole('button', { name: 'Crear campo' }).click();
    await expect(daniela.getByText('Campo creado.')).toBeVisible();

    const valeria = await as(browser, USERS.valeria);
    await expect(valeria.getByRole('link', { name: 'Proyectos' })).toHaveCount(0);
    await valeria.goto('/proyectos');
    await expect(valeria.getByText('No tienes permiso')).toBeVisible();
});
