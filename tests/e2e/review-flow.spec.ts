import { expect, test } from '@playwright/test';
import { METRO, PNG, USERS, as } from './helpers';

test.describe.configure({ mode: 'serial' });

/** Metro page 1 starts with no adjustments, waiting for Marketing, sector Food. */
const PAGE = `${METRO}/1`;

test('a commercial marks a region and the adjustment starts descending', async ({ browser }) => {
    const hernan = await as(browser, USERS.hernan);
    await hernan.goto(PAGE);
    await expect(hernan.getByText('Validación · Marketing').first()).toBeVisible();

    await hernan.getByRole('button', { name: 'Comentar región' }).click();
    const canvas = hernan.getByTestId('canvas');
    const box = (await canvas.boundingBox())!;
    await hernan.mouse.move(box.x + box.width * 0.2, box.y + box.height * 0.2);
    await hernan.mouse.down();
    await hernan.mouse.move(box.x + box.width * 0.5, box.y + box.height * 0.3, { steps: 8 });
    await hernan.mouse.up();

    await hernan.getByPlaceholder('¿Qué hay que cambiar aquí?').fill('El arroz debe quedar en $5.290, no $5.790.');
    await hernan.getByRole('button', { name: 'Enviar' }).click();

    await expect(hernan.getByText('Ajuste enviado a Planificación para validación')).toBeVisible();
    await expect(hernan.getByText('#1 · Precio')).toBeVisible();
    await expect(hernan.getByText('En Planificación').first()).toBeVisible();
    await expect(hernan.getByText('Planificación valida', { exact: false }).first()).toBeVisible();
});

test('Planificación and Marketing validate it down to Ogilvy', async ({ browser }) => {
    const tomas = await as(browser, USERS.tomas);
    await tomas.goto(PAGE);
    await tomas.getByRole('button', { name: 'Validar y enviar' }).click();
    await tomas.getByPlaceholder('Nota para el siguiente eslabón (opcional)').fill('Confirmado con el proveedor');
    await tomas.getByRole('button', { name: 'Validar ajuste' }).click();
    await expect(tomas.getByText('Ajuste enviado a Marketing')).toBeVisible();
    await expect(tomas.getByText('En Marketing').first()).toBeVisible();
    await expect(tomas.getByText('Confirmado con el proveedor')).toBeVisible();

    const valeria = await as(browser, USERS.valeria);
    await valeria.goto(PAGE);
    await valeria.getByRole('button', { name: 'Validar y enviar' }).click();
    await valeria.getByRole('button', { name: 'Validar ajuste' }).click();
    await expect(valeria.getByText('Ajuste validado y enviado a Ogilvy')).toBeVisible();
    await expect(valeria.getByText('En Ogilvy').first()).toBeVisible();
    await expect(valeria.getByText('Ogilvy ejecuta', { exact: false }).first()).toBeVisible();
});

test('Ogilvy resolves it and the approval chain resumes', async ({ browser }) => {
    const daniela = await as(browser, USERS.daniela);
    await daniela.goto(PAGE);
    await expect(daniela.getByRole('button', { name: 'Validar', exact: true })).toHaveCount(0);
    await daniela.getByRole('button', { name: 'Marcar resuelto' }).click();
    await expect(daniela.getByText('Ajuste resuelto · vuelve a la cadena de aprobación')).toBeVisible();
    await expect(daniela.getByText('Resuelto en v1')).toBeVisible();
    await expect(daniela.getByText('Validación · Marketing').first()).toBeVisible();
});

test('Marketing validates and the page moves to Planificación', async ({ browser }) => {
    const valeria = await as(browser, USERS.valeria);
    await valeria.goto(PAGE);
    await valeria.getByRole('button', { name: 'Validar', exact: true }).click();
    await expect(valeria.getByRole('heading', { name: 'Validar como Marketing' })).toBeVisible();
    await valeria.getByRole('button', { name: 'Validar y enviar a Planificación' }).click();
    await expect(valeria.getByText('Página 01 validada.')).toBeVisible();
    await expect(valeria.getByText('Validación · Planificación').first()).toBeVisible();
    await expect(valeria.getByRole('button', { name: 'Validado' })).toBeDisabled();
    await expect(valeria.getByRole('button', { name: 'Aprobar a imprenta' })).toBeDisabled();
    // Marketing already validated: it can no longer comment on this version
    await expect(valeria.getByRole('button', { name: 'Comentar región' })).toBeDisabled();
});

test('a new version restarts the chain from Marketing', async ({ browser }) => {
    const daniela = await as(browser, USERS.daniela);
    await daniela.goto(PAGE);
    await daniela.getByRole('button', { name: 'Cargar nueva versión' }).click();
    await daniela.locator('input[type=file]').setInputFiles({ name: 'metro-p1-v2.png', mimeType: 'image/png', buffer: PNG });
    await daniela.getByRole('button', { name: 'Cargar v2' }).click();
    await expect(daniela.getByText('Versión v2 cargada')).toBeVisible();
    await expect(daniela.getByRole('link', { name: 'v2', exact: true })).toBeVisible();
    await expect(daniela.getByText('Validación · Marketing').first()).toBeVisible();
    await expect(daniela.getByRole('button', { name: 'Comparar', exact: true })).toBeVisible();
});

test('validation goes up, print approval comes down, and Marketing reopens it as a defect', async ({ browser }) => {
    const page4 = `${METRO}/4`;
    type P = Awaited<ReturnType<typeof as>>;
    const validate = async (who: P, next: string | null) => {
        await who.goto(page4);
        await who.getByRole('button', { name: 'Validar', exact: true }).click();
        await who.getByRole('button', { name: next ? `Validar y enviar a ${next}` : 'Confirmar validación' }).click();
        await expect(who.getByText('Página 04 validada.')).toBeVisible();
    };
    const print = async (who: P, next: string | null) => {
        await who.goto(page4);
        /* Only Marketing, the last link, "approves for print"; Comercial and Planificación just approve. */
        await who.getByRole('button', { name: next ? 'Aprobar' : 'Aprobar a imprenta', exact: true }).click();
        await who.getByRole('button', { name: next ? `Aprobar y enviar a ${next}` : 'Aprobar a imprenta y cerrar' }).click();
        await expect(who.getByText('Página 04 aprobada a imprenta.')).toBeVisible();
    };

    const valeria = await as(browser, USERS.valeria);
    const tomas = await as(browser, USERS.tomas);
    const hernan = await as(browser, USERS.hernan);

    await validate(valeria, 'Planificación');
    await validate(tomas, 'Comercial');
    await validate(hernan, null);
    await expect(hernan.getByText('Imprenta · Comercial').first()).toBeVisible();

    await print(hernan, 'Planificación');
    await print(tomas, 'Marketing');
    await print(valeria, null);
    await expect(valeria.getByText('Aprobada a imprenta v1').first()).toBeVisible();

    // only Marketing sees the Post Production Defect button, and only now
    await hernan.goto(page4);
    await expect(hernan.getByRole('button', { name: 'Post Production Defect' })).toHaveCount(0);

    await valeria.goto(page4);
    await valeria.getByRole('button', { name: 'Post Production Defect' }).click();
    await valeria.getByLabel('Qué se encontró').fill('El televisor salió con el precio de la semana pasada.');
    await valeria.getByRole('button', { name: 'Reabrir página' }).click();
    await expect(valeria.getByText('reabierta como defecto post-producción')).toBeVisible();
    await expect(valeria.getByText('En Ogilvy').first()).toBeVisible();
    await expect(valeria.getByText('PPD', { exact: true })).toBeVisible();

    const daniela = await as(browser, USERS.daniela);
    await daniela.goto('/notificaciones');
    await expect(daniela.getByText('Post Production Defect en la Página 04 · página reabierta')).toBeVisible();
});
