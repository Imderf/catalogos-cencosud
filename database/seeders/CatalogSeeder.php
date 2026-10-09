<?php

namespace Database\Seeders;

use App\Domain\Review\AdjustmentRouter;
use App\Enums\AdjustmentStatus;
use App\Jobs\ProcessPageVersionImage;
use App\Models\Brand;
use App\Models\Catalog;
use App\Models\Page;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Demo data mirroring docs/prototipo: the Jumbo and Metro flyers of 18–20 Sep 2026.
 * Page images are copied from storage/app/seed/paginas when present.
 */
class CatalogSeeder extends Seeder
{
    private const SECTORS = [['rancho'], ['pls'], ['pft'], ['rancho', 'pls'], ['liquidos'], ['liquidos', 'dph'], ['dph'], ['bazar', 'textil', 'electro']];

    public function run(): void
    {
        $this->call([BrandSeeder::class, UserSeeder::class, SettingsSeeder::class]);
        $u = fn (string $email) => User::where('email', $email)->firstOrFail();
        $daniela = $u('daniela.pardo@ogilvy.example');
        $valeria = $u('valeria.ortiz@cencosud.example');
        $tomas = $u('tomas.rincon@cencosud.example');
        $hernan = $u('hernan.castro@cencosud.example');
        $monica = $u('monica.lara@cencosud.example');
        $B = fn ($x, $y, $w, $h) => compact('x', 'y', 'w', 'h');

        $this->catalog('jumbo', 'Jumbo · Octavilla 18–20 Sep 2026', $daniela, [
            1 => ['wait' => 95, 'adj' => [
                [$B(.07, .30, .40, .10), 'precio', 'El precio del lomo de res premium no coincide con la lista maestra: debe quedar en $28.900 el kilo.', $hernan, null, 0],
            ]],
            2 => ['versions' => 2, 'adj' => [
                [$B(.08, .17, .40, .12), 'imagen', 'La foto del queso campesino es la del catálogo anterior — reemplazar por la verificada.', $valeria, 2, 0],
                [$B(.52, .44, .40, .11), 'descripcion', 'Falta el gramaje en la presentación de yogurt griego.', $tomas, 2, 0],
            ]],
            3 => ['adj' => [[$B(.07, .55, .38, .12), 'imagen', 'La imagen del pan tajado salió con el empaque viejo. Cambiarla por la del banco verificado.', $valeria, null, 155]]],
            4 => ['adj' => [
                [$B(.55, .68, .38, .10), 'precio', 'El descuento de la cerveza debe ser 30%, no 25% — así quedó en la negociación.', $valeria, null, 22],
                [$B(.07, .40, .36, .13), 'otro', 'Verificar que el combo de snacks no repita referencia con la octavilla del 11 al 13.', $tomas, null, 0],
            ]],
            5 => ['wait' => 255],
            6 => ['adj' => [[$B(.08, .22, .42, .14), 'diseno', 'El bloque de líquidos compite con el titular: bajar el peso del precio y dar aire entre la foto y el copy.', $valeria, null, 70]]],
            7 => ['approvals' => [[$valeria, 'marketing']]],
            8 => ['wait' => 30, 'approvals' => [[$valeria, 'marketing'], [$tomas, 'planificacion'], [$monica, 'comercial']]],
        ]);

        $this->catalog('metro', 'Metro · Octavilla 18–20 Sep 2026', $daniela, [
            1 => ['wait' => 40],
            2 => ['adj' => [[$B(.10, .26, .36, .11), 'precio', 'El arroz debe quedar en $5.290, no $5.790.', $hernan, null, 0]]],
            3 => ['adj' => [[$B(.09, .47, .38, .12), 'imagen', 'La foto de las galletas quedó pixelada al ampliarla; usar la del banco verificado.', $valeria, null, 35]]],
            4 => ['wait' => 120],
            5 => ['adj' => [[$B(.55, .33, .38, .10), 'precio', 'Corregir el precio del atún: la promo pactada es $4.990 la lata.', $tomas, null, 26]]],
            6 => ['versions' => 2, 'adj' => [[$B(.09, .30, .40, .12), 'imagen', 'El aceite aparece con la presentación de 3L y la promo es de 1L.', $valeria, 2, 0]]],
            7 => ['adj' => [[$B(.52, .20, .40, .12), 'descripcion', 'Aclarar que el 2x1 de shampoo aplica solo sobre la línea familiar.', $tomas, null, 0]]],
            8 => ['adj' => [[$B(.07, .62, .44, .13), 'otro', 'El televisor de 55" ya salió como destacado la semana pasada — confirmar antes de imprimir.', $monica, null, 0]]],
        ]);
    }

    private function catalog(string $brandSlug, string $name, User $by, array $pages): void
    {
        $slug = $brandSlug.'-octavilla-18-20-sep';
        if (Catalog::where('slug', $slug)->exists()) {
            return;
        }
        $catalog = Catalog::create([
            'brand_id' => Brand::where('slug', $brandSlug)->value('id'), 'slug' => $slug, 'name' => $name,
            'created_by' => $by->id, 'published_at' => now()->subDays(6),
        ]);
        foreach ($pages as $n => $spec) {
            $versions = $spec['versions'] ?? 1;
            $page = Page::create([
                'catalog_id' => $catalog->id, 'number' => $n, 'current_version' => $versions,
                'submitted_at' => now()->subDays(6), 'waiting_since' => now()->subMinutes($spec['wait'] ?? 0),
            ]);
            $page->sectors()->sync(Sector::whereIn('slug', self::SECTORS[$n - 1])->pluck('id'));
            for ($v = 1; $v <= $versions; $v++) {
                $version = $page->versions()->create([
                    'number' => $v, 'uploaded_by' => $by->id,
                    'image_path' => $this->image($slug, $brandSlug, $n, $v),
                    'created_at' => now()->subDays($v === 1 ? 6 : 5),
                ]);
                ProcessPageVersionImage::dispatchSync($version);
            }
            foreach ($spec['adj'] ?? [] as [$box, $type, $text, $author, $resolvedIn, $receivedAgo]) {
                $role = $author->role();
                $route = AdjustmentRouter::routeFrom($role);
                $page->adjustments()->create([
                    'version' => 1, 'author_id' => $author->id, 'origin_role' => $role->value, 'type' => $type, 'text' => $text,
                    'box' => $box, 'status' => $resolvedIn ? AdjustmentStatus::Resolved->value : AdjustmentStatus::Open->value,
                    'resolved_in_version' => $resolvedIn, 'resolved_at' => $resolvedIn ? now()->subDays(5) : null,
                    'route' => $resolvedIn ? [] : $route, 'is_defect' => false,
                    'stage_since' => now()->subMinutes($receivedAgo), 'received_by_agency_at' => $route[0] === 'agencia' ? now()->subMinutes($receivedAgo) : null,
                    'agency_started_at' => $route[0] === 'agencia' ? now()->subMinutes($receivedAgo) : null,
                    'created_at' => now()->subDays(5),
                ]);
            }
            foreach ($spec['approvals'] ?? [] as [$user, $role]) {
                $page->approvals()->create(['version' => 1, 'user_id' => $user->id, 'role' => $role, 'created_at' => now()->subDays(5)]);
            }
        }
    }

    /** Copies the seed JPG (if present) into the private disk and returns its path. */
    private function image(string $catalogSlug, string $brandSlug, int $n, int $v): string
    {
        $file = $brandSlug.'-p'.$n.($v > 1 ? '-v'.$v : '').'.jpg';
        $src = storage_path('app/seed/paginas/'.$file);
        $dest = sprintf('catalogos/%s/p%02d-v%d.jpg', $catalogSlug, $n, $v);
        if (File::exists($src)) {
            Storage::disk('local')->put($dest, File::get($src));
        }

        return $dest;
    }
}
