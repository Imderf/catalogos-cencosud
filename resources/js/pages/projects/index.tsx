import Heading from '@/components/heading';
import { AvatarStack } from '@/components/projects/person-avatar';
import ProjectFormDialog from '@/components/projects/project-form-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useFlashToast } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { type ClientOption, type ProjectSummary } from '@/types/projects';
import { Head, Link, router } from '@inertiajs/react';
import { Archive, KanbanSquare, Plus } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Proyectos', href: '/proyectos' }];

export default function ProjectsIndex({
    projects,
    clients,
    templates,
    filters,
}: {
    projects: ProjectSummary[];
    clients: ClientOption[];
    templates: { id: number; name: string }[];
    filters: { archivados: boolean; cliente: number | null };
}) {
    useFlashToast();
    const [creating, setCreating] = useState(false);
    const pick = (cliente: number | null) =>
        router.get(
            route('projects.index'),
            { archivados: filters.archivados ? 1 : undefined, cliente: cliente ?? undefined },
            { preserveState: true, replace: true },
        );
    /* Grouped by client, "Sin cliente" last; only when there is more than one group. */
    const groups = Object.values(
        projects.reduce<Record<string, { key: string; client: ClientOption | null; items: ProjectSummary[] }>>((acc, p) => {
            const key = p.client ? String(p.client.id) : 'none';
            (acc[key] ??= { key, client: p.client, items: [] }).items.push(p);
            return acc;
        }, {}),
    ).sort((a, b) => (a.client ? 0 : 1) - (b.client ? 0 : 1) || (a.client?.name ?? '').localeCompare(b.client?.name ?? ''));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Proyectos" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={filters.archivados ? 'Proyectos archivados' : 'Proyectos'}
                        description="Tableros de trabajo del equipo Ogilvy: listas, tarjetas, responsables y fechas. Solo los ve la agencia."
                    />
                    <div className="flex gap-2">
                        <Button variant={filters.archivados ? 'secondary' : 'ghost'} asChild>
                            <Link href={route('projects.index', filters.archivados ? {} : { archivados: 1 })}>
                                <Archive className="h-4 w-4" />
                                {filters.archivados ? 'Ver activos' : 'Archivados'}
                            </Link>
                        </Button>
                        <Button onClick={() => setCreating(true)}>
                            <Plus className="h-4 w-4" />
                            Nuevo proyecto
                        </Button>
                    </div>
                </div>

                {clients.length > 0 && (
                    <div className="flex flex-wrap items-center gap-1.5">
                        <button
                            type="button"
                            onClick={() => pick(null)}
                            className={cn(
                                'rounded-full border px-3 py-1 text-xs font-medium',
                                filters.cliente === null ? 'bg-foreground text-background border-foreground' : 'hover:bg-muted',
                            )}
                        >
                            Todos los clientes
                        </button>
                        {clients.map((c) => (
                            <button
                                key={c.id}
                                type="button"
                                onClick={() => pick(filters.cliente === c.id ? null : c.id)}
                                className={cn(
                                    'flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium',
                                    filters.cliente === c.id ? 'text-white' : 'hover:bg-muted',
                                )}
                                style={filters.cliente === c.id ? { backgroundColor: c.color, borderColor: c.color } : undefined}
                            >
                                {c.logo_url ? (
                                    <img src={c.logo_url} alt="" className="h-4 w-4 rounded object-contain" />
                                ) : (
                                    <span className="h-2 w-2 rounded-full" style={{ backgroundColor: c.color }} />
                                )}
                                {c.name}
                            </button>
                        ))}
                    </div>
                )}
                {projects.length === 0 ? (
                    <div className="text-muted-foreground flex flex-col items-center gap-3 rounded-xl border border-dashed py-16 text-center">
                        <KanbanSquare className="h-8 w-8" />
                        <p className="text-sm">
                            {filters.archivados ? 'No hay proyectos archivados.' : 'Todavía no tienes proyectos. Crea el primero.'}
                        </p>
                        {!filters.archivados && (
                            <Button size="sm" onClick={() => setCreating(true)}>
                                Nuevo proyecto
                            </Button>
                        )}
                    </div>
                ) : (
                    groups.map((g) => (
                        <section key={g.key} className="grid gap-3">
                            {groups.length > 1 && (
                                <h3 className="text-muted-foreground flex items-center gap-2 text-xs font-semibold tracking-wide uppercase">
                                    {g.client && <span className="h-2 w-2 rounded-full" style={{ backgroundColor: g.client.color }} />}
                                    {g.client?.name ?? 'Sin cliente'} · {g.items.length}
                                </h3>
                            )}
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                {g.items.map((p) => (
                                    <Link
                                        key={p.id}
                                        href={route('projects.show', p.id)}
                                        className="bg-card hover:border-foreground/30 group flex flex-col gap-3 rounded-xl border p-4 transition"
                                    >
                                        <div className="flex items-start gap-3">
                                            {p.client?.logo_url ? (
                                                <img
                                                    src={p.client.logo_url}
                                                    alt=""
                                                    className="bg-card h-9 w-9 shrink-0 rounded-lg border object-contain"
                                                />
                                            ) : (
                                                <span
                                                    className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-base font-bold text-white select-none"
                                                    style={{ backgroundColor: p.color }}
                                                >
                                                    {p.name.trim().charAt(0).toUpperCase()}
                                                </span>
                                            )}
                                            <div className="min-w-0 flex-1">
                                                <h3 className="truncate font-semibold">{p.name}</h3>
                                                <p className="text-muted-foreground truncate text-xs">
                                                    {p.client ? p.client.name : 'Sin cliente'}
                                                    {p.owner ? ` · ${p.owner.name}` : ''}
                                                </p>
                                            </div>
                                            {p.my_role === 'manager' && <Badge variant="outline">Moderador</Badge>}
                                        </div>
                                        <p className="text-muted-foreground line-clamp-2 text-sm">{p.description || 'Sin descripción'}</p>
                                        <div className="mt-auto flex items-center justify-between gap-3 pt-1">
                                            <AvatarStack people={p.members} />
                                            <div className="flex gap-1.5 text-xs">
                                                <Badge variant="secondary">{p.counts.open} abiertas</Badge>
                                                {p.counts.mine > 0 && <Badge variant="outline">{p.counts.mine} mías</Badge>}
                                                {p.counts.overdue > 0 && (
                                                    <Badge className="bg-red-600 text-white hover:bg-red-600">{p.counts.overdue} vencidas</Badge>
                                                )}
                                            </div>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        </section>
                    ))
                )}
            </div>
            <ProjectFormDialog open={creating} onClose={() => setCreating(false)} clients={clients} templates={templates} />
        </AppLayout>
    );
}
