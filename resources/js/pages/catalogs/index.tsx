import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import DeleteCatalogDialog from '@/components/review/delete-catalog-dialog';
import ShareDialog from '@/components/review/share-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/app-layout';
import { fmtDate, pad2 } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type CatalogSummary, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Catálogos', href: '/catalogos' }];

export default function CatalogsIndex({
    catalogs,
    filters,
    kinds,
}: {
    catalogs: CatalogSummary[];
    filters: { q: string; archivados: boolean; tipo: string | null };
    kinds: { value: string; label: string }[];
}) {
    const { auth } = usePage<SharedData>().props;
    const [q, setQ] = useState(filters.q);

    const search: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(
            route('catalogs.index'),
            { q, archivados: filters.archivados ? 1 : undefined, tipo: filters.tipo ?? undefined },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Catálogos" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <FlashMessage />
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading title="Catálogos" description={`${catalogs.length} catálogos en proceso · rondas de revisión abiertas con Cencosud`} />
                    <div className="flex gap-2">
                        <form onSubmit={search} className="flex gap-2">
                            <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar catálogo" className="w-56" />
                        </form>
                        <Button variant={filters.archivados ? 'secondary' : 'ghost'} asChild>
                            <Link href={route('catalogs.index', filters.archivados ? {} : { archivados: 1 })}>
                                {filters.archivados ? 'Ver activos' : 'Archivados'}
                            </Link>
                        </Button>
                        {auth.user.can.manage_catalogs && (
                            <Button asChild>
                                <Link href={route('catalogs.create')}>
                                    <Plus className="h-4 w-4" />
                                    Nuevo catálogo
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Link
                        href={route('catalogs.index', { archivados: filters.archivados ? 1 : undefined })}
                        className={cn(
                            'rounded-full border px-3 py-1 text-xs',
                            !filters.tipo ? 'bg-foreground text-background border-foreground' : 'hover:bg-muted',
                        )}
                    >
                        Todos
                    </Link>
                    {kinds.map((k) => (
                        <Link
                            key={k.value}
                            href={route('catalogs.index', { tipo: k.value, archivados: filters.archivados ? 1 : undefined })}
                            className={cn(
                                'rounded-full border px-3 py-1 text-xs',
                                filters.tipo === k.value ? 'bg-foreground text-background border-foreground' : 'hover:bg-muted',
                            )}
                        >
                            {k.label}
                        </Link>
                    ))}
                </div>

                {catalogs.length === 0 && (
                    <div className="text-muted-foreground rounded-xl border border-dashed p-10 text-center text-sm">
                        No hay catálogos que coincidan.
                    </div>
                )}

                <div className="grid gap-4">
                    {catalogs.map((c, i) => (
                        <div key={c.id} className="bg-card flex flex-wrap items-center gap-6 rounded-xl border p-5 shadow-sm">
                            <span className="text-muted-foreground font-mono text-sm">{pad2(i + 1)}</span>
                            <div className="min-w-64 flex-1">
                                <div className="mb-1 flex flex-wrap items-center gap-2">
                                    <Badge variant={c.archived_at ? 'outline' : c.totals.open > 0 ? 'destructive' : 'secondary'}>
                                        {c.archived_at ? 'Archivado' : c.status_label}
                                    </Badge>
                                    <Badge variant="outline">{c.kind_label}</Badge>
                                    {c.unsubmitted > 0 && !c.archived_at && <Badge variant="outline">{c.unsubmitted} en preparación</Badge>}
                                    <span className="text-muted-foreground text-xs">
                                        {c.client} · {c.pages_count} páginas · cargado {fmtDate(c.published_at)}
                                    </span>
                                </div>
                                <Link href={route('catalogs.show', c.slug)} className="text-lg font-semibold hover:underline">
                                    {c.name}
                                </Link>
                                <div className="mt-3 flex items-center gap-4">
                                    <Progress value={c.progress} className="h-1.5 w-48" />
                                    <span className="text-xs font-medium">
                                        {c.totals.approved}/{c.pages_count} a imprenta
                                    </span>
                                    <span
                                        className={
                                            c.totals.open > 0 ? 'text-xs font-medium text-red-600 dark:text-red-400' : 'text-muted-foreground text-xs'
                                        }
                                    >
                                        {c.totals.open} pendientes
                                    </span>
                                    <span className="text-muted-foreground text-xs">{c.totals.total} ajustes</span>
                                    {c.totals.approvalsOverdue > 0 && (
                                        <span className="text-xs font-medium text-red-600 dark:text-red-400">
                                            {c.totals.approvalsOverdue} aprobación(es) vencida(s)
                                        </span>
                                    )}
                                </div>
                            </div>
                            <div className="flex gap-2">
                                <DeleteCatalogDialog catalog={c} />
                                <ShareDialog catalog={c} />
                                <Button asChild size="sm">
                                    <Link href={route('catalogs.show', c.slug)}>Abrir</Link>
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
