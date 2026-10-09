import Heading from '@/components/heading';
import { PersonAvatar } from '@/components/projects/person-avatar';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useFlashToast } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { type Person, type Priority } from '@/types/projects';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle2, ChevronLeft, ChevronRight } from 'lucide-react';
import { type CSSProperties, type ReactNode, useMemo } from 'react';

interface CalCard {
    id: number;
    title: string;
    due_date: string;
    completed: boolean;
    priority: Priority;
    project: { id: number; name: string; color: string };
    mine: boolean;
    catalog: string | null;
    assignees: Person[];
    url: string | null;
    parent: string | null;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Calendario', href: '/calendario' }];
const DAYS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
const DOT: Record<Priority, string> = { low: 'bg-neutral-400', medium: 'bg-sky-500', high: 'bg-amber-500', urgent: 'bg-red-500' };
const LABEL: Record<Priority, string> = { low: 'Baja', medium: 'Media', high: 'Alta', urgent: 'Urgente' };

/** Local date arithmetic on "YYYY-MM-DD" strings, no timezone surprises. */
function addDays(iso: string, n: number): string {
    const [y, m, d] = iso.split('-').map(Number);
    const dt = new Date(y, m - 1, d + n);
    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
}

export default function Calendar({
    month,
    label,
    prev,
    next,
    from,
    to,
    today,
    projects,
    clients,
    people,
    filter,
    cards,
    read_only,
}: {
    month: string;
    label: string;
    prev: string;
    next: string;
    from: string;
    to: string;
    today: string;
    projects: { id: number; name: string; color: string; client_id: number | null }[];
    clients: { id: number; name: string; color: string }[];
    people: Person[];
    filter: { proyecto: number | null; persona: number | null; cliente: number | null };
    cards: CalCard[];
    /** Client view: dates only, no people filter, no board links. */
    read_only: boolean;
}) {
    const { auth } = usePage<SharedData>().props;
    useFlashToast();
    const days = useMemo(() => {
        const out: string[] = [];
        for (let d = from; d <= to; d = addDays(d, 1)) out.push(d);
        return out;
    }, [from, to]);
    const weeks = days.length / 7;
    const byDay = useMemo(() => {
        const m = new Map<string, CalCard[]>();
        for (const c of cards) m.set(c.due_date, [...(m.get(c.due_date) ?? []), c]);
        return m;
    }, [cards]);
    const go = (mes: string, proyecto = filter.proyecto, persona = filter.persona, cliente = filter.cliente) =>
        router.get(
            route('calendar'),
            { mes, proyecto: proyecto ?? undefined, persona: persona ?? undefined, cliente: cliente ?? undefined },
            { preserveState: true, replace: true },
        );

    /* Open cards due this month, per person: who carries the load. */
    const workload = useMemo(() => {
        const counts = new Map<number, number>();
        for (const c of cards)
            if (!c.completed && c.due_date.startsWith(month)) for (const a of c.assignees) counts.set(a.id, (counts.get(a.id) ?? 0) + 1);
        return people
            .map((p) => ({ ...p, count: counts.get(p.id) ?? 0 }))
            .filter((p) => p.count > 0)
            .sort((a, b) => b.count - a.count);
    }, [cards, people, month]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Calendario · ${label}`} />
            <div className="flex h-[calc(100svh-3rem)] flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Heading
                        title="Calendario"
                        description={read_only ? 'Fechas límite de las tareas de tus proyectos.' : 'Cronograma de tarjetas por fecha límite.'}
                    />
                    <div className="-mt-6 flex flex-wrap items-center gap-2">
                        {clients.length > 1 && (
                            <Select
                                value={filter.cliente ? String(filter.cliente) : 'all'}
                                onValueChange={(v) => go(month, null, filter.persona, v === 'all' ? null : Number(v))}
                            >
                                <SelectTrigger className="h-9 w-44">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos los clientes</SelectItem>
                                    {clients.map((c) => (
                                        <SelectItem key={c.id} value={String(c.id)}>
                                            {c.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                        <Select
                            value={filter.proyecto ? String(filter.proyecto) : 'all'}
                            onValueChange={(v) => go(month, v === 'all' ? null : Number(v))}
                        >
                            <SelectTrigger className="h-9 w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos los proyectos</SelectItem>
                                {projects.map((p) => (
                                    <SelectItem key={p.id} value={String(p.id)}>
                                        {p.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {!read_only && (
                            <Select
                                value={filter.persona ? String(filter.persona) : 'all'}
                                onValueChange={(v) => go(month, filter.proyecto, v === 'all' ? null : Number(v))}
                            >
                                <SelectTrigger className="h-9 w-48">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todas las personas</SelectItem>
                                    <SelectItem value={String(auth.user.id)}>Mis tarjetas</SelectItem>
                                    {people
                                        .filter((p) => p.id !== auth.user.id)
                                        .map((p) => (
                                            <SelectItem key={p.id} value={String(p.id)}>
                                                {p.name}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                        )}
                        <div className="bg-card flex items-center rounded-md border shadow-xs">
                            <button
                                type="button"
                                onClick={() => go(prev)}
                                className="text-muted-foreground hover:bg-muted p-2"
                                aria-label="Mes anterior"
                            >
                                <ChevronLeft className="h-4 w-4" />
                            </button>
                            <span className="w-36 text-center text-sm font-semibold">{label}</span>
                            <button
                                type="button"
                                onClick={() => go(next)}
                                className="text-muted-foreground hover:bg-muted p-2"
                                aria-label="Mes siguiente"
                            >
                                <ChevronRight className="h-4 w-4" />
                            </button>
                        </div>
                        <Button variant="secondary" onClick={() => go(today.slice(0, 7))}>
                            Hoy
                        </Button>
                    </div>
                </div>

                <div className="flex min-h-0 flex-1 gap-4">
                    <div className="bg-card flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border shadow-sm">
                        <div className="bg-muted/60 text-muted-foreground grid shrink-0 grid-cols-7 border-b text-center text-xs font-medium tracking-wide uppercase">
                            {DAYS.map((d) => (
                                <div key={d} className="py-2">
                                    {d}
                                </div>
                            ))}
                        </div>
                        <div className="grid min-h-0 flex-1 grid-cols-7" style={{ gridTemplateRows: `repeat(${weeks}, minmax(0, 1fr))` }}>
                            {days.map((d) => {
                                const inMonth = d.startsWith(month);
                                const items = byDay.get(d) ?? [];
                                return (
                                    <div key={d} className={cn('flex min-h-0 flex-col border-r border-b p-1.5', !inMonth && 'bg-muted/40')}>
                                        <span
                                            className={cn(
                                                'mb-1 inline-flex h-6 w-6 shrink-0 items-center justify-center self-end rounded-full text-xs',
                                                d === today
                                                    ? 'bg-primary text-primary-foreground font-semibold'
                                                    : inMonth
                                                      ? 'text-foreground'
                                                      : 'text-muted-foreground',
                                            )}
                                        >
                                            {Number(d.slice(8))}
                                        </span>
                                        <div className="min-h-0 flex-1 space-y-1 overflow-y-auto pr-0.5">
                                            {items.map((c) => (
                                                <Item
                                                    key={c.id}
                                                    href={c.url}
                                                    title={`${c.parent ? `${c.parent} ↳ ` : ''}${c.title} · ${c.project.name}${c.catalog ? ` · ${c.catalog}` : ''}`}
                                                    style={{ borderLeftColor: c.project.color }}
                                                    className={cn(
                                                        'group bg-card hover:border-primary/40 flex w-full items-center gap-1 rounded-md border border-l-[3px] px-1.5 py-1 text-left text-[11px] leading-tight shadow-xs hover:shadow',
                                                        c.completed && 'opacity-60',
                                                        !c.completed && d < today && 'bg-red-50 dark:bg-red-950/30',
                                                    )}
                                                >
                                                    <span className={cn('h-1.5 w-1.5 shrink-0 rounded-full', DOT[c.priority])} />
                                                    <span
                                                        className={cn(
                                                            'min-w-0 flex-1 truncate',
                                                            c.completed && 'line-through',
                                                            c.mine && !c.completed && 'font-medium',
                                                        )}
                                                    >
                                                        {c.completed && <CheckCircle2 className="mr-0.5 inline h-3 w-3 text-emerald-600" />}
                                                        {c.parent && <span className="text-muted-foreground">↳ </span>}
                                                        {c.title}
                                                    </span>
                                                    <span className="flex shrink-0 -space-x-1">
                                                        {c.assignees.slice(0, 3).map((a) => (
                                                            <PersonAvatar key={a.id} person={a} className="h-4 w-4 text-[8px]" />
                                                        ))}
                                                    </span>
                                                </Item>
                                            ))}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                    <aside className="hidden w-64 shrink-0 flex-col gap-4 lg:flex">
                        {!read_only && (
                            <section className="bg-card rounded-xl border p-4 shadow-sm">
                                <h3 className="mb-3 text-sm font-semibold">Carga del mes por persona</h3>
                                {workload.length === 0 ? (
                                    <p className="text-muted-foreground text-xs">Sin tarjetas pendientes con fecha este mes.</p>
                                ) : (
                                    <ul className="space-y-1">
                                        {workload.map((p) => (
                                            <li key={p.id}>
                                                <button
                                                    type="button"
                                                    onClick={() => go(month, filter.proyecto, filter.persona === p.id ? null : p.id)}
                                                    className={cn(
                                                        'hover:bg-muted flex w-full items-center gap-2 rounded-md px-1.5 py-1 text-left',
                                                        filter.persona === p.id && 'bg-primary/10',
                                                    )}
                                                >
                                                    <PersonAvatar person={p} />
                                                    <span className="flex-1 truncate text-sm">{p.name}</span>
                                                    <span className="bg-muted rounded-full px-2 text-xs font-medium">{p.count}</span>
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </section>
                        )}
                        <section className="bg-card text-muted-foreground rounded-xl border p-4 text-xs shadow-sm">
                            <h3 className="text-foreground mb-2 text-sm font-semibold">Leyenda</h3>
                            <p className="mb-1.5">El borde izquierdo indica el proyecto; el punto, la prioridad:</p>
                            <ul className="space-y-1">
                                {(Object.keys(DOT) as Priority[]).map((p) => (
                                    <li key={p} className="flex items-center gap-2">
                                        <span className={cn('h-2 w-2 rounded-full', DOT[p])} />
                                        {LABEL[p]}
                                    </li>
                                ))}
                            </ul>
                            <p className="mt-2">
                                Solo aparecen tarjetas con fecha límite. En rojo, lo vencido; tachado, lo completado
                                {read_only ? '' : '; en negrita, lo tuyo'}.
                            </p>
                        </section>
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}

/** A calendar entry: a link into the board (or the catalog) when there is somewhere to go, a plain box otherwise. */
function Item({
    href,
    className,
    children,
    ...props
}: {
    href: string | null;
    className?: string;
    title?: string;
    style?: CSSProperties;
    children: ReactNode;
}) {
    if (!href) {
        return (
            <div {...props} className={cn(className, 'cursor-default hover:border-inherit hover:shadow-xs')}>
                {children}
            </div>
        );
    }
    return (
        <Link href={href} {...props} className={className}>
            {children}
        </Link>
    );
}
