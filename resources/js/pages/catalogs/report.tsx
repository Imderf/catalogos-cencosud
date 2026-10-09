import CatalogHeader from '@/components/review/catalog-header';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { fmtDate, pad2 } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type CatalogSummary, type Totals } from '@/types';
import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';

interface Kpis extends Totals {
    pages: number;
    multi_version: number;
    average: number;
}

interface Row {
    number: number;
    sectors: string;
    version: number;
    total: number;
    open: number;
    state: string;
}

interface LogEntry {
    ts: string | null;
    kind: 'carga' | 'ajuste' | 'validacion' | 'version' | 'aprobacion' | 'defecto';
    page: number;
    text: string;
    by: string;
}

interface Props {
    catalog: CatalogSummary;
    kpis: Kpis;
    by_sector: { sector: string; value: number }[];
    rows: Row[];
    log: LogEntry[];
}

export default function CatalogReport({ catalog, kpis, by_sector, rows, log }: Props) {
    const [filter, setFilter] = useState('todas');
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Catálogos', href: '/catalogos' },
        { title: catalog.name, href: route('catalogs.show', catalog.slug) },
        { title: 'Reporte', href: route('catalogs.report', catalog.slug) },
    ];
    const tiles: { value: string | number; label: string; tone?: 'red' | 'dark' }[] = [
        { value: kpis.total, label: 'Ajustes totales' },
        { value: kpis.open, label: 'Pendientes', tone: 'red' },
        { value: `${kpis.approved}/${kpis.pages}`, label: 'Aprobadas a imprenta', tone: 'dark' },
        { value: kpis.defects, label: 'Defectos tras aprobación', tone: kpis.defects > 0 ? 'red' : undefined },
        { value: kpis.overdue, label: 'Ajustes fuera de tiempo' },
        { value: kpis.approvalsOverdue, label: 'Aprobaciones vencidas del cliente', tone: kpis.approvalsOverdue > 0 ? 'red' : undefined },
        { value: `${kpis.multi_version}/${kpis.pages}`, label: 'Con +1 versión' },
        { value: kpis.average, label: 'Promedio por página' },
    ];
    const max = Math.max(1, ...by_sector.map((b) => b.value));
    const entries = filter === 'todas' ? log : log.filter((l) => String(l.page) === filter);

    return (
        <AppLayout breadcrumbs={breadcrumbs} header={<CatalogHeader catalog={catalog} tab="reporte" />}>
            <Head title={`Reporte · ${catalog.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4">
                <div className="flex flex-col gap-6 p-4">
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {tiles.map((t) => (
                            <div
                                key={t.label}
                                className={cn(
                                    'rounded-xl border p-4',
                                    t.tone === 'dark' && 'bg-foreground text-background',
                                    t.tone === 'red' &&
                                        'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200',
                                )}
                            >
                                <p className="font-mono text-2xl font-semibold">{t.value}</p>
                                <p className={cn('text-xs', t.tone ? 'opacity-80' : 'text-muted-foreground')}>{t.label}</p>
                            </div>
                        ))}
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <section className="rounded-xl border p-4">
                            <h3 className="mb-3 text-sm font-semibold">Ajustes por sector</h3>
                            <div className="grid gap-2">
                                {by_sector.map((b) => (
                                    <div key={b.sector} className="grid grid-cols-[80px_1fr_32px] items-center gap-3 text-sm">
                                        <span>{b.sector}</span>
                                        <div className="bg-muted h-2 overflow-hidden rounded">
                                            <div
                                                className={cn('h-full', b.value === max && b.value > 0 ? 'bg-primary' : 'bg-neutral-400')}
                                                style={{ width: `${Math.max(2, (b.value / max) * 100)}%` }}
                                            />
                                        </div>
                                        <span className="text-right font-mono text-xs">{b.value}</span>
                                    </div>
                                ))}
                            </div>
                        </section>
                        <section className="rounded-xl border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Página</TableHead>
                                        <TableHead>Sector</TableHead>
                                        <TableHead>Versión</TableHead>
                                        <TableHead className="text-right">Ajustes</TableHead>
                                        <TableHead className="text-right">Abiertos</TableHead>
                                        <TableHead>Estado</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rows.map((r) => (
                                        <TableRow key={r.number}>
                                            <TableCell>Página {pad2(r.number)}</TableCell>
                                            <TableCell className="text-muted-foreground text-xs">{r.sectors}</TableCell>
                                            <TableCell className="font-mono text-xs">v{r.version}</TableCell>
                                            <TableCell className="text-right font-mono">{r.total}</TableCell>
                                            <TableCell
                                                className={cn(
                                                    'text-right font-mono',
                                                    r.open > 0 ? 'font-semibold text-red-600 dark:text-red-400' : 'text-muted-foreground',
                                                )}
                                            >
                                                {r.open}
                                            </TableCell>
                                            <TableCell className="text-xs">{r.state}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </section>
                    </div>

                    <section className="rounded-xl border p-4">
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                            <h3 className="text-sm font-semibold">Bitácora</h3>
                            <div className="flex gap-2">
                                <Select value={filter} onValueChange={setFilter}>
                                    <SelectTrigger className="w-40">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="todas">Todas las páginas</SelectItem>
                                        {rows.map((r) => (
                                            <SelectItem key={r.number} value={String(r.number)}>
                                                Página {r.number}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Button variant="outline" asChild>
                                    <a href={route('catalogs.report.csv', catalog.slug)}>
                                        <Download className="h-4 w-4" />
                                        CSV
                                    </a>
                                </Button>
                            </div>
                        </div>
                        <ol className="grid gap-2">
                            {entries.map((l, i) => (
                                <li key={i} className="grid grid-cols-[10px_90px_70px_1fr] items-baseline gap-3 text-sm">
                                    <span
                                        className={cn(
                                            'mt-1.5 h-2 w-2 rounded-full',
                                            l.kind === 'ajuste' || l.kind === 'defecto'
                                                ? 'bg-red-500'
                                                : l.kind === 'version' || l.kind === 'aprobacion'
                                                  ? 'bg-foreground'
                                                  : 'bg-neutral-300',
                                        )}
                                    />
                                    <span className="text-muted-foreground text-xs">{fmtDate(l.ts)}</span>
                                    <span className="font-mono text-xs">Pág. {pad2(l.page)}</span>
                                    <span>
                                        — {l.text} <span className="text-muted-foreground">· {l.by}</span>
                                    </span>
                                </li>
                            ))}
                            {entries.length === 0 && <li className="text-muted-foreground text-sm">Sin movimientos.</li>}
                        </ol>
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
