import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { fmtDate, fmtDur } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Totals } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Download } from 'lucide-react';

interface Row extends Totals {
    slug: string;
    name: string;
    brand: string;
    published_at: string;
    pages: number;
    avg_execution: number | null;
    avg_delay: number | null;
    state: string;
}

interface Kpis extends Totals {
    catalogs: number;
    pages: number;
    avg_execution: number | null;
    avg_delay: number | null;
    average: number;
}

interface Props {
    month: string;
    month_label: string;
    prev: string;
    next: string;
    kpis: Kpis;
    rows: Row[];
    by_sector: { sector: string; value: number }[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reportes', href: '/reportes' }];

export default function MonthlyReport({ month, month_label, prev, next, kpis, rows, by_sector }: Props) {
    const tiles: { value: string | number; label: string; tone?: 'red' | 'dark' }[] = [
        { value: kpis.catalogs, label: 'Catálogos del mes' },
        { value: `${kpis.approved}/${kpis.pages}`, label: 'Páginas aprobadas a imprenta', tone: 'dark' },
        { value: kpis.total, label: 'Ajustes totales' },
        { value: kpis.open, label: 'Pendientes', tone: kpis.open > 0 ? 'red' : undefined },
        { value: kpis.defects, label: 'Defectos tras aprobación', tone: kpis.defects > 0 ? 'red' : undefined },
        { value: kpis.approvalsOverdue, label: 'Aprobaciones vencidas del cliente', tone: kpis.approvalsOverdue > 0 ? 'red' : undefined },
        { value: kpis.avg_execution === null ? '—' : fmtDur(kpis.avg_execution), label: 'Ejecución media de Ogilvy' },
        { value: kpis.avg_delay === null ? '—' : fmtDur(kpis.avg_delay), label: 'Demora media del cliente al aprobar' },
    ];
    const max = Math.max(1, ...by_sector.map((b) => b.value));
    const label = month_label.charAt(0).toUpperCase() + month_label.slice(1);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Reporte · ${label}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading title="Reporte mensual" description="Todos los catálogos publicados en el mes, para la conversación con Cencosud." />
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="icon" asChild aria-label="Mes anterior">
                            <Link href={route('reports.monthly', { mes: prev })}>
                                <ChevronLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <span className="w-40 text-center text-sm font-medium">{label}</span>
                        <Button variant="outline" size="icon" asChild aria-label="Mes siguiente">
                            <Link href={route('reports.monthly', { mes: next })}>
                                <ChevronRight className="h-4 w-4" />
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={route('reports.monthly.csv', { mes: month })}>
                                <Download className="h-4 w-4" />
                                CSV
                            </a>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {tiles.map((t) => (
                        <div
                            key={t.label}
                            className={cn(
                                'rounded-xl border p-4',
                                t.tone === 'dark' && 'bg-foreground text-background',
                                t.tone === 'red' && 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200',
                            )}
                        >
                            <p className="font-mono text-2xl font-semibold">{t.value}</p>
                            <p className={cn('text-xs', t.tone ? 'opacity-80' : 'text-muted-foreground')}>{t.label}</p>
                        </div>
                    ))}
                </div>

                <section className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Catálogo</TableHead>
                                <TableHead>Publicado</TableHead>
                                <TableHead className="text-right">Páginas</TableHead>
                                <TableHead className="text-right">Aprobadas</TableHead>
                                <TableHead className="text-right">Ajustes</TableHead>
                                <TableHead className="text-right">Abiertos</TableHead>
                                <TableHead className="text-right">PPD</TableHead>
                                <TableHead className="text-right">Ejecución Ogilvy</TableHead>
                                <TableHead className="text-right">Demora cliente</TableHead>
                                <TableHead>Estado</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={10} className="text-muted-foreground py-8 text-center">
                                        Ningún catálogo publicado en {label.toLowerCase()}.
                                    </TableCell>
                                </TableRow>
                            )}
                            {rows.map((r) => (
                                <TableRow key={r.slug}>
                                    <TableCell>
                                        <Link href={route('catalogs.report', r.slug)} className="font-medium hover:underline">
                                            {r.name}
                                        </Link>
                                        <div className="text-muted-foreground text-xs">Cencosud ({r.brand})</div>
                                    </TableCell>
                                    <TableCell className="text-xs">{fmtDate(r.published_at)}</TableCell>
                                    <TableCell className="text-right font-mono">{r.pages}</TableCell>
                                    <TableCell className="text-right font-mono">{r.approved}</TableCell>
                                    <TableCell className="text-right font-mono">{r.total}</TableCell>
                                    <TableCell className={cn('text-right font-mono', r.open > 0 && 'font-semibold text-red-600 dark:text-red-400')}>
                                        {r.open}
                                    </TableCell>
                                    <TableCell className={cn('text-right font-mono', r.defects > 0 && 'text-red-600 dark:text-red-400')}>
                                        {r.defects}
                                    </TableCell>
                                    <TableCell className="text-right font-mono text-xs">
                                        {r.avg_execution === null ? '—' : fmtDur(r.avg_execution)}
                                    </TableCell>
                                    <TableCell className="text-right font-mono text-xs">{r.avg_delay === null ? '—' : fmtDur(r.avg_delay)}</TableCell>
                                    <TableCell>
                                        <Badge variant={r.state === 'Cerrado' ? 'default' : r.state === 'Archivado' ? 'outline' : 'secondary'}>
                                            {r.state}
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </section>

                {by_sector.length > 0 && (
                    <section className="max-w-xl rounded-xl border p-4">
                        <h3 className="mb-3 text-sm font-semibold">Ajustes por sector</h3>
                        <div className="grid gap-2">
                            {by_sector.map((b) => (
                                <div key={b.sector} className="grid grid-cols-[80px_1fr_32px] items-center gap-3 text-sm">
                                    <span>{b.sector}</span>
                                    <div className="bg-muted h-2 overflow-hidden rounded">
                                        <div
                                            className={cn('h-full', b.value === max ? 'bg-primary' : 'bg-neutral-400')}
                                            style={{ width: `${Math.max(2, (b.value / max) * 100)}%` }}
                                        />
                                    </div>
                                    <span className="text-right font-mono text-xs">{b.value}</span>
                                </div>
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
