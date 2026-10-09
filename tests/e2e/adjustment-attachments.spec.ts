import { expect, test } from '@playwright/test';
import { METRO, PNG, USERS, as } from './helpers';

const SHOTS = process.env.SHOTS_DIR;

test('the client attaches a PDF, an image and a link to an adjustment and everyone sees them', async ({ browser }) => {
    const valeria = await as(browser, USERS.valeria);
    await valeria.goto(`${METRO}/3`);
    await valeria.getByRole('button', { name: 'Comentar región' }).click();
    const canvas = valeria.getByTestId('canvas');
    const box = (await canvas.boundingBox())!;
    await valeria.mouse.move(box.x + box.width * 0.2, box.y + box.height * 0.2);
    await valeria.mouse.down();
    await valeria.mouse.move(box.x + box.width * 0.5, box.y + box.height * 0.3, { steps: 8 });
    await valeria.mouse.up();

    await valeria.getByPlaceholder('¿Qué hay que cambiar aquí?').fill('Usar la foto y el brief adjuntos');
    await valeria.getByRole('checkbox', { name: 'Imagen' }).click(); // several kinds at once: Precio (default) + Imagen
    await valeria.getByPlaceholder('Enlace a recursos (opcional)').fill('https://drive.google.com/drive/folders/brief-metro');
    await valeria.locator('input[type=file]').setInputFiles([
        { name: 'brief.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF') },
        { name: 'foto.png', mimeType: 'image/png', buffer: PNG },
    ]);
    await expect(valeria.getByText('brief.pdf')).toBeVisible();
    await expect(valeria.getByText('foto.png')).toBeVisible();
    if (SHOTS) await valeria.screenshot({ path: `${SHOTS}/form.png` });

    await valeria.getByRole('button', { name: 'Enviar' }).click();
    await expect(valeria.getByText('Ajuste enviado a Ogilvy', { exact: false })).toBeVisible();
    await expect(valeria.getByText('Precio + Imagen', { exact: false }).first()).toBeVisible();
    const panel = valeria.getByRole('link', { name: 'brief.pdf' });
    await expect(panel).toBeVisible();
    await expect(valeria.getByRole('link', { name: 'drive.google.com/drive/folders/brief-metro' })).toHaveAttribute(
        'href',
        'https://drive.google.com/drive/folders/brief-metro',
    );
    await expect(valeria.getByRole('link', { name: 'foto.png' }).locator('img')).toBeVisible();
    if (SHOTS) await valeria.screenshot({ path: `${SHOTS}/panel.png` });

    // Ogilvy opens the PDF from the same page
    const daniela = await as(browser, USERS.daniela);
    await daniela.goto(`${METRO}/3`);
    const href = await daniela.getByRole('link', { name: 'brief.pdf' }).getAttribute('href');
    const res = await daniela.request.get(href!);
    expect(res.status()).toBe(200);
    expect(res.headers()['content-type']).toContain('application/pdf');
});
