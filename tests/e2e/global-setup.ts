import { execSync } from 'node:child_process';
import { copyFileSync, existsSync, readFileSync } from 'node:fs';

/** Fresh e2e database with the demo catalogs and business hours open around the clock. */
export default function globalSetup() {
    if (!existsSync('.env.e2e')) copyFileSync('.env.e2e.example', '.env.e2e');
    const env = { ...process.env, APP_ENV: 'e2e' };
    if (!/^APP_KEY=base64:/m.test(readFileSync('.env.e2e', 'utf8'))) {
        execSync('php artisan key:generate --env=e2e --force', { env, stdio: 'inherit' });
    }
    execSync('php artisan migrate:fresh --force --seed --seeder=Database\\\\Seeders\\\\E2ESeeder', { env, stdio: 'inherit' });
}
