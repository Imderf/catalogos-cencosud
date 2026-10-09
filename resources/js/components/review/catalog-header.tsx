import ShareDialog from '@/components/review/share-dialog';
import { Badge } from '@/components/ui/badge';
import { fmtDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type CatalogSummary, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { KanbanSquare, Settings2 } from 'lucide-react';

export type CatalogTab = 'paginas' | 'pagina' | 'reporte';

/** The catalog's one-line header, rendered inside the app's top bar on every catalog screen. */
export default function CatalogHeader({ catalog, tab, pageNumber }: { catalog: CatalogSummary; tab: CatalogTab; pageNumber?: number }) {
    const { auth } = usePage<SharedData>().props;
    const tabs: { key: CatalogTab; label: string; href: string }[] = [
        { key: 'paginas', label: 'Páginas', href: route('catalogs.show', catalog.slug) },
        { key: 'pagina', label: 'Revisión', href: route('pages.show', [catalog.slug, pageNumber ?? 1]) },
        { key: 'reporte', label: 'Reporte', href: route('catalogs.report', catalog.slug) },
    ];

    return (
        <div className="flex min-w-0 flex-1 items-center gap-3 overflow-x-auto text-sm">
            <Link href={route('catalogs.index')} className="text-muted-foreground shrink-0 text-xs hover:underline">
                Catálogos
            </Link>
            <span className="text-muted-foreground shrink-0 text-xs">/</span>
            <Link href={route('catalogs.show', catalog.slug)} className="shrink-0 font-semibold hover:underline">
                {catalog.name}
            </Link>
            <span className="text-muted-foreground hidden shrink-0 text-xs lg:inline">
                {catalog.kind_label} · {catalog.client} · {catalog.pages_count} páginas · cargado {fmtDate(catalog.published_at)}
            </span>
            <nav className="flex shrink-0 gap-1">
                {tabs.map((t) => (
                    <Link
                        key={t.key}
                        href={t.href}
                        className={cn(
                            'rounded-md px-2 py-1 text-xs',
                            t.key === tab ? 'bg-muted font-medium' : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {t.label}
                    </Link>
                ))}
            </nav>
            <div className="ml-auto flex shrink-0 items-center gap-2">
                {catalog.project && (
                    <Link
                        href={route('projects.show', { project: catalog.project.id, tarjeta: catalog.project.card_id })}
                        className="hover:bg-muted inline-flex h-8 items-center gap-1.5 rounded-md border px-2.5 text-xs"
                        title="Tarjeta del catálogo en el tablero"
                    >
                        <KanbanSquare className="h-3.5 w-3.5" style={{ color: catalog.project.color }} />
                        <span className="hidden xl:inline">{catalog.project.name}</span>
                    </Link>
                )}
                {catalog.archived_at && <Badge variant="secondary">Archivado</Badge>}
                {catalog.unsubmitted > 0 && !catalog.archived_at && (
                    <Badge variant="outline" title="Páginas que Ogilvy todavía no envió a revisión">
                        {catalog.unsubmitted} en preparación
                    </Badge>
                )}
                <Badge variant={catalog.totals.open > 0 ? 'destructive' : 'secondary'}>{catalog.totals.open} pendientes</Badge>
                <Badge variant="outline">
                    {catalog.totals.approved}/{catalog.pages_count} a imprenta
                </Badge>
                <ShareDialog catalog={catalog} />
                {auth.user.can.manage_catalogs && (
                    <Link
                        href={route('catalogs.edit', catalog.slug)}
                        className="hover:bg-muted inline-flex h-8 items-center gap-1.5 rounded-md border px-3 text-sm"
                    >
                        <Settings2 className="h-4 w-4" />
                        Administrar
                    </Link>
                )}
            </div>
        </div>
    );
}
