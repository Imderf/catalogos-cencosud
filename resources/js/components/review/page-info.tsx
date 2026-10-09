import { Badge } from '@/components/ui/badge';
import { fmtDate } from '@/lib/format';
import { type CatalogSummary, type PageDetail } from '@/types';

/** Static facts about the page, for the focused-mode info panel. */
export default function PageInfo({ catalog, page }: { catalog: CatalogSummary; page: PageDetail }) {
    return (
        <dl className="grid gap-3 text-sm">
            <Row label="Catálogo" value={`${catalog.name} · ${catalog.client}`} />
            <Row label="Página" value={`${page.label} de ${catalog.pages_count}`} />
            <Row label="Sectores" value={page.sectors_label} />
            <Row label="Estado" value={page.approved ? `Aprobada a imprenta v${page.version}` : page.location.label} />
            <Row label="Ajustes" value={`${page.total} en total · ${page.open} abiertos`} />
            {page.defects.length > 0 && <Row label="Defectos" value={`${page.defects.length} post-producción`} />}
            <div>
                <dt className="text-muted-foreground mb-1">Versiones</dt>
                <dd className="flex flex-wrap gap-1.5">
                    {page.versions.map((v) => (
                        <Badge key={v.number} variant={v.number === page.version ? 'default' : 'outline'} className="font-mono">
                            v{v.number} · {fmtDate(v.date)}
                        </Badge>
                    ))}
                </dd>
            </div>
        </dl>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{value}</dd>
        </div>
    );
}
