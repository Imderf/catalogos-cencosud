import Heading from '@/components/heading';
import { AvatarStack, PersonAvatar } from '@/components/projects/person-avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useFlashToast } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { fmtAgo, fmtDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type CatalogSummary, type SharedData } from '@/types';
import { type CardDto, type Person, type Priority, type ProjectSummary, PRIORITY_CLASS } from '@/types/projects';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, BookImage, CalendarClock, CheckCircle2, CircleDot, MessageSquare, Paperclip, UserCheck, X } from 'lucide-react';
import { type ReactNode } from 'react';

type Row = CardDto & { project: { id: number; name: string; color: string }; list: string | null; url: string; parent?: string | null };
type Activity = { id: number; actor: string; text: string; card: string | null; project: string | null; at: string; url: string };
type ProjectRow = ProjectSummary & { total: number; done: number; lists: { id: number; name: string; count: number }[] };

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Panel', href: '/panel' }];
const PRIORITIES: { key: Priority; label: string; dot: string }[] = [
    { key: 'low', label: 'Baja', dot: 'bg-neutral-400' },
    { key: 'medium', label: 'Media', dot: 'bg-sky-500' },
    { key: 'high', label: 'Alta', dot: 'bg-amber-500' },
    { key: 'urgent', label: 'Urgente', dot: 'bg-red-500' },
];
const LIST_COLORS = [
    '#2563eb',
    '#7c3aed',
    '#0d9488',
    '#d97706',
    '#dc2626',
    '#0891b2',
    '#65a30d',
    '#db2777',
    '#4f46e5',
    '#ea580c',
    '#059669',
    '#64748b',
];

export default function Dashboard({
    filters,
    options,
    stats,
    mine,
    due_soon,
    projects,
    catalogs,
    activity,
    completed_per_day,
    by_priority,
    by_assignee,
}: {
    filters: { proyecto: number | null; persona: number | null; desde: string | null; hasta: string | null };
    options: { projects: { id: number; name: string }[]; people: Person[] };
    stats: { projects: number; open: number; mine: number; overdue: number; due_week: number; completed: number };
    mine: Row[];
    due_soon: Row[];
    projects: ProjectRow[];
    catalogs: CatalogSummary[];
    activity: Activity[];
    completed_per_day: { date: string; count: number }[];
    by_priority: Partial<Record<Priority, number>>;
    by_assignee: { user: Person; count: number }[];
}) {
    const { auth } = usePage<SharedData>().props;
    useFlashToast();
    const first = auth.user.name.split(' ')[0];
    const tiles = [
        { label: 'Tarjetas abiertas', value: stats.open, icon: CircleDot, tone: 'text-sky-600 bg-sky-50 dark:bg-sky-950/40' },
        { label: 'Completadas', value: stats.completed, icon: CheckCircle2, tone: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40' },
        { label: 'Vencidas', value: stats.overdue, icon: AlertTriangle, tone: 'text-red-600 bg-red-50 dark:bg-red-950/40' },
        { label: 'Vencen esta semana', value: stats.due_week, icon: CalendarClock, tone: 'text-amber-600 bg-amber-50 dark:bg-amber-950/40' },
        { label: 'Asignadas a mí', value: stats.mine, icon: UserCheck, tone: 'text-violet-600 bg-violet-50 dark:bg-violet-950/40' },
    ];
    const maxDay = Math.max(1, ...completed_per_day.map((d) => d.count));
    const prioTotal = Object.values(by_priority).reduce((a, b) => a + (b ?? 0), 0) || 1;
    const maxAssignee = Math.max(1, by_assignee[0]?.count ?? 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Panel" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading title={`Hola, ${first}`} description="Resumen de tus proyectos, tarjetas y catálogos." />
                <FilterBar filters={filters} options={options} />

                <div className="grid grid-cols-2 gap-3 md:grid-cols-5">
                    {tiles.map((t) => (
                        <div key={t.label} className="bg-card rounded-xl border p-4 shadow-sm">
                            <div className={cn('mb-2 inline-flex rounded-lg p-2', t.tone)}>
                                <t.icon className="h-4 w-4" />
                            </div>
                            <p className="text-2xl font-semibold tabular-nums">{t.value}</p>
                            <p className="text-muted-foreground text-xs">{t.label}</p>
                        </div>
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card title="Tarjetas completadas (últimos 30 días)" className="lg:col-span-2">
                        <div className="flex h-32 items-end gap-[3px]">
                            {completed_per_day.map((d) => (
                                <div
                                    key={d.date}
                                    title={`${fmtDate(d.date)}: ${d.count}`}
                                    className="bg-primary/80 hover:bg-primary flex-1 rounded-t-sm transition-all"
                                    style={{ height: `${Math.max(4, (d.count / maxDay) * 100)}%` }}
                                />
                            ))}
                        </div>
                        <div className="text-muted-foreground mt-1 flex justify-between text-[10px]">
                            <span>{completed_per_day[0] ? fmtDate(completed_per_day[0].date) : ''}</span>
                            <span>Hoy</span>
                        </div>
                    </Card>
                    <Card title="Abiertas por prioridad">
                        <div className="space-y-2.5">
                            {PRIORITIES.map((p) => {
                                const n = by_priority[p.key] ?? 0;
                                return (
                                    <div key={p.key}>
                                        <div className="mb-1 flex justify-between text-xs">
                                            <span className="text-muted-foreground">{p.label}</span>
                                            <span className="font-medium">{n}</span>
                                        </div>
                                        <div className="bg-muted h-2 rounded-full">
                                            <div className={cn('h-2 rounded-full', p.dot)} style={{ width: `${(n / prioTotal) * 100}%` }} />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </Card>
                    <Card title="Flujo de trabajo por proyecto" className="lg:col-span-3">
                        {projects.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                Sin proyectos.{' '}
                                <Link href={route('projects.index')} className="text-primary hover:underline">
                                    Crea el primero
                                </Link>
                                .
                            </p>
                        ) : (
                            <div className="space-y-8">
                                {projects.map((p) => (
                                    <Pipeline key={p.id} project={p} />
                                ))}
                            </div>
                        )}
                    </Card>
                    <Card title="Carga por persona">
                        {by_assignee.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Sin asignaciones.</p>
                        ) : (
                            <div className="space-y-2">
                                {by_assignee.map((a) => (
                                    <div key={a.user.id} className="flex items-center gap-2">
                                        <PersonAvatar person={a.user} />
                                        <span className="flex-1 truncate text-sm">{a.user.name}</span>
                                        <div className="bg-muted h-1.5 w-24 rounded-full">
                                            <div
                                                className="h-1.5 rounded-full bg-violet-500"
                                                style={{ width: `${(a.count / maxAssignee) * 100}%` }}
                                            />
                                        </div>
                                        <span className="w-6 text-right text-xs font-medium">{a.count}</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>
                    <Card title={`Mis tarjetas pendientes (${mine.length})`}>
                        <TaskList cards={mine} empty="No tienes tarjetas asignadas pendientes." />
                    </Card>
                    <Card title="Actividad reciente">
                        {activity.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Sin actividad.</p>
                        ) : (
                            <ul className="max-h-96 space-y-3 overflow-y-auto pr-1">
                                {activity.map((a) => (
                                    <li key={a.id} className="text-xs">
                                        <Link href={a.url} className="hover:underline">
                                            <span className="font-medium">{a.actor}</span> {a.text}
                                        </Link>
                                        <p className="text-muted-foreground">
                                            {a.card} · {a.project} · {fmtAgo(a.at)}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                    <Card title={`Próximas a vencer en tus proyectos (${due_soon.length})`} className="lg:col-span-3">
                        <TaskList cards={due_soon} empty="No hay tarjetas vencidas ni que venzan en los próximos días." showAssignees />
                    </Card>
                    <Card title="Catálogos en proceso" className="lg:col-span-3">
                        {catalogs.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No hay catálogos activos.</p>
                        ) : (
                            <ul className="divide-y">
                                {catalogs.map((c) => (
                                    <li key={c.id}>
                                        <Link
                                            href={route('catalogs.show', c.slug)}
                                            className="hover:bg-muted/50 flex items-center gap-3 rounded-md px-2 py-2.5 text-sm"
                                        >
                                            <BookImage className="text-muted-foreground h-4 w-4 shrink-0" />
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate font-medium">{c.name}</p>
                                                <p className="text-muted-foreground truncate text-xs">
                                                    {c.kind_label} · {c.brand} · {c.pages_count} páginas · {c.status_label}
                                                    {c.project && ` · ${c.project.name}`}
                                                </p>
                                            </div>
                                            {c.unsubmitted > 0 && <Badge variant="outline">{c.unsubmitted} en preparación</Badge>}
                                            <Badge variant={c.totals.open > 0 ? 'destructive' : 'secondary'}>{c.totals.open} pendientes</Badge>
                                            <div className="w-32">
                                                <div className="bg-muted h-1.5 rounded-full">
                                                    <div className="h-1.5 rounded-full bg-emerald-500" style={{ width: `${c.progress}%` }} />
                                                </div>
                                                <p className="text-muted-foreground mt-1 text-right text-[10px]">
                                                    {c.totals.approved}/{c.pages_count} a imprenta
                                                </p>
                                            </div>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

/** Horizontal pipeline: one box per list in board order. */
function Pipeline({ project: p }: { project: ProjectRow }) {
    const max = Math.max(1, ...p.lists.map((l) => l.count));
    return (
        <div>
            <div className="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2">
                <Link href={route('projects.show', p.id)} className="flex items-center gap-2 text-sm font-semibold hover:underline">
                    <span
                        className="flex h-6 w-6 items-center justify-center rounded-md text-xs font-bold text-white"
                        style={{ backgroundColor: p.color }}
                    >
                        {p.name.trim().charAt(0).toUpperCase()}
                    </span>
                    {p.name}
                </Link>
                {p.client && (
                    <span
                        className="rounded-full border px-2 py-0.5 text-[11px] font-medium"
                        style={{ borderColor: p.client.color, color: p.client.color }}
                    >
                        {p.client.name}
                    </span>
                )}
                <span className="text-muted-foreground text-xs">
                    {p.total - p.done} en flujo · {p.done} completadas
                    {p.counts.overdue > 0 && ` · ${p.counts.overdue} vencidas`}
                </span>
                <span className="ml-auto">
                    <AvatarStack people={p.members} max={4} />
                </span>
            </div>
            <div className="grid [grid-template-columns:repeat(auto-fill,minmax(150px,1fr))] gap-2">
                {p.lists.map((l, i) => {
                    const color = LIST_COLORS[i % LIST_COLORS.length];
                    const share = p.total ? Math.round((l.count / p.total) * 100) : 0;
                    return (
                        <div
                            key={l.id}
                            className={cn(
                                'flex flex-col justify-between rounded-lg border p-3 transition',
                                l.count === max && l.count > 0 ? 'bg-card shadow-sm' : 'bg-muted/40',
                            )}
                            title={`${l.name}: ${l.count} (${share}%)`}
                        >
                            <p className="text-muted-foreground text-[11px] leading-snug font-medium" style={{ minHeight: '2.4em' }}>
                                <span className="bg-muted mr-1.5 inline-block rounded-full px-1.5 text-[10px] tabular-nums">{i + 1}</span>
                                {l.name}
                            </p>
                            <div className="mt-2 flex items-baseline gap-2">
                                <span className={cn('text-2xl font-semibold tabular-nums', !l.count && 'text-muted-foreground/50')}>{l.count}</span>
                                <span className="text-muted-foreground text-[11px]">{share}%</span>
                            </div>
                            <div className="bg-muted mt-1.5 h-1.5 w-full rounded-full">
                                <div
                                    className="h-1.5 rounded-full"
                                    style={{ width: `${(l.count / max) * 100}%`, backgroundColor: color, opacity: l.count ? 1 : 0 }}
                                />
                            </div>
                        </div>
                    );
                })}
                {p.lists.length === 0 && <p className="text-muted-foreground text-xs">Sin listas.</p>}
            </div>
        </div>
    );
}

function TaskList({ cards, empty, showAssignees }: { cards: Row[]; empty: string; showAssignees?: boolean }) {
    if (!cards.length) return <p className="text-muted-foreground text-sm">{empty}</p>;
    const today = new Date().toISOString().slice(0, 10);
    return (
        <ul className="max-h-96 divide-y overflow-y-auto pr-1">
            {cards.map((c) => {
                const st = !c.due_date ? 'none' : c.due_date < today ? 'overdue' : c.due_date === today ? 'today' : 'future';
                return (
                    <li key={c.id}>
                        <Link href={c.url} className="hover:bg-muted/50 flex gap-3 rounded-md px-2 py-2.5">
                            <span className="mt-1 h-9 w-1 shrink-0 rounded-full" style={{ backgroundColor: c.project.color }} />
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium">
                                    {c.parent && <span className="text-muted-foreground font-normal">{c.parent} ↳ </span>}
                                    {c.title}
                                </p>
                                <p className="text-muted-foreground mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                                    <span className="font-medium">{c.project.name}</span>
                                    {c.list && <span>· {c.list}</span>}
                                    {c.comments_count > 0 && (
                                        <span className="flex items-center gap-0.5">
                                            <MessageSquare className="h-3 w-3" />
                                            {c.comments_count}
                                        </span>
                                    )}
                                    {c.attachments_count > 0 && (
                                        <span className="flex items-center gap-0.5">
                                            <Paperclip className="h-3 w-3" />
                                            {c.attachments_count}
                                        </span>
                                    )}
                                </p>
                            </div>
                            <div className="flex shrink-0 flex-col items-end gap-1">
                                <div className="flex items-center gap-2">
                                    {showAssignees && <AvatarStack people={c.assignees} max={3} />}
                                    <span className={cn('rounded px-1.5 py-0.5 text-[10px] font-medium', PRIORITY_CLASS[c.priority])}>
                                        {c.priority_label}
                                    </span>
                                </div>
                                {c.due_date ? (
                                    <span
                                        className={cn(
                                            'text-xs',
                                            st === 'overdue'
                                                ? 'font-medium text-red-600'
                                                : st === 'today'
                                                  ? 'font-medium text-amber-600'
                                                  : 'text-muted-foreground',
                                        )}
                                    >
                                        {st === 'overdue' ? 'Vencida · ' : st === 'today' ? 'Hoy · ' : ''}
                                        {fmtDate(c.due_date)}
                                    </span>
                                ) : (
                                    <span className="text-muted-foreground text-xs">Sin fecha</span>
                                )}
                            </div>
                        </Link>
                    </li>
                );
            })}
        </ul>
    );
}

const Card = ({ title, children, className }: { title: string; children: ReactNode; className?: string }) => (
    <section className={cn('bg-card rounded-xl border p-4 shadow-sm', className)}>
        <h2 className="mb-3 text-sm font-semibold">{title}</h2>
        {children}
    </section>
);

/** Narrows everything below: one project, one person, a window of dates (start or due date inside it). */
function FilterBar({
    filters,
    options,
}: {
    filters: { proyecto: number | null; persona: number | null; desde: string | null; hasta: string | null };
    options: { projects: { id: number; name: string }[]; people: Person[] };
}) {
    const active = Boolean(filters.proyecto || filters.persona || filters.desde || filters.hasta);
    const go = (next: Partial<typeof filters>) => {
        const f = { ...filters, ...next };
        router.get(
            route('panel'),
            { proyecto: f.proyecto ?? undefined, persona: f.persona ?? undefined, desde: f.desde ?? undefined, hasta: f.hasta ?? undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };
    return (
        <div className="bg-card -mt-4 flex flex-wrap items-center gap-2 rounded-xl border p-3">
            <Select
                value={filters.proyecto ? String(filters.proyecto) : 'all'}
                onValueChange={(v) => go({ proyecto: v === 'all' ? null : Number(v) })}
            >
                <SelectTrigger className="h-9 w-52">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Todos los proyectos</SelectItem>
                    {options.projects.map((p) => (
                        <SelectItem key={p.id} value={String(p.id)}>
                            {p.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Select value={filters.persona ? String(filters.persona) : 'all'} onValueChange={(v) => go({ persona: v === 'all' ? null : Number(v) })}>
                <SelectTrigger className="h-9 w-52">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Todas las personas</SelectItem>
                    {options.people.map((p) => (
                        <SelectItem key={p.id} value={String(p.id)}>
                            {p.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <label className="text-muted-foreground flex items-center gap-1.5 text-xs">
                Desde
                <Input
                    type="date"
                    className="h-9 w-40"
                    value={filters.desde ?? ''}
                    max={filters.hasta ?? undefined}
                    onChange={(e) => go({ desde: e.target.value || null })}
                />
            </label>
            <label className="text-muted-foreground flex items-center gap-1.5 text-xs">
                Hasta
                <Input
                    type="date"
                    className="h-9 w-40"
                    value={filters.hasta ?? ''}
                    min={filters.desde ?? undefined}
                    onChange={(e) => go({ hasta: e.target.value || null })}
                />
            </label>
            {active && (
                <Button variant="ghost" size="sm" onClick={() => go({ proyecto: null, persona: null, desde: null, hasta: null })}>
                    <X className="h-3.5 w-3.5" />
                    Limpiar filtros
                </Button>
            )}
            {active && (
                <span className="text-muted-foreground ml-auto text-xs">
                    Los contadores, las gráficas, las listas y la actividad siguen estos filtros; los catálogos no.
                </span>
            )}
        </div>
    );
}
