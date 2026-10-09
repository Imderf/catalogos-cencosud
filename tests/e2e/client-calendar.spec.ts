import { expect, test } from '@playwright/test';
import { USERS, as } from './helpers';

const SHOTS = process.env.SHOTS_DIR;

test('a client reaches a read-only calendar from the sidebar: no people filter, no workload, no board links', async ({ browser }) => {
    const valeria = await as(browser, USERS.valeria);
    await valeria.getByRole('link', { name: 'Calendario' }).click();
    await expect(valeria).toHaveURL(/\/calendario/);
    await expect(valeria.getByText('Fechas límite de las tareas de tus proyectos.')).toBeVisible();
    await expect(valeria.getByText('Carga del mes por persona')).toHaveCount(0);
    await expect(valeria.getByText('Todas las personas')).toHaveCount(0);
    await expect(valeria.getByRole('link', { name: /Panel|Proyectos/ })).toHaveCount(0);
    if (SHOTS) await valeria.screenshot({ path: `${SHOTS}/client-calendar.png` });
});
