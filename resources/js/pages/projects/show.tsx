import ActivityPanel from '@/components/projects/activity-panel';
import Board, { type BoardFilter, EMPTY_FILTER } from '@/components/projects/board';
import CardModal from '@/components/projects/card-modal';
import { PersonAvatar } from '@/components/projects/person-avatar';
import ProjectSettingsDialog from '@/components/projects/project-settings-dialog';
import SubtaskDialog from '@/components/projects/subtask-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { useLiveBoard } from '@/hooks/use-live-board';
import AppLayout from '@/layouts/app-layout';
import { LIVE } from '@/lib/live';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData, type TypeOption } from '@/types';
import {
    type ActivityLine,
    type CardDetail,
    type ClientOption,
    type Person,
    type ProjectBoard,
    type ProjectOption,
    type StatusOption,
    type SubtaskDetail,
} from '@/types/projects';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, History, Search, Settings2, UserCheck, X } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Props {
    project: ProjectBoard;
    card: CardDetail | null;
    subtask: SubtaskDetail | null;
    subtask_statuses: StatusOption[];
    priorities: TypeOption[];
    field_types: TypeOption[];
    clients: ClientOption[];
    people: Person[];
    all_projects: ProjectOption[];
    activity?: ActivityLine[];
}

export default function ProjectShow({
    project,
    card,
    subtask,
    subtask_statuses,
    priorities,
    field_types,
    clients,
    people,
    all_projects,
    activity,
}: Props) {
    const { auth } = usePage<SharedData>().props;
    useFlashToast();
    const [settings, setSettings] = useState(false);
    const [showActivity, setShowActivity] = useState(false);
    const [filter, setFilter] = useState<BoardFilter>(EMPTY_FILTER);
    const filterActive = Boolean(filter.q || filter.mine || filter.assignee || filter.label || filter.hideDone);
    const total = project.lists.reduce((n, l) => n + l.cards.length, 0);
    const shown = filterActive
        ? project.lists.reduce(
              (n, l) =>
                  n +
                  l.cards.filter(
                      (c) =>
                          (!filter.q || c.title.toLowerCase().includes(filter.q.toLowerCase())) &&
                          (!filter.mine || c.assignees.some((a) => a.id === auth.user.id)) &&
                          (!filter.assignee || c.assignees.some((a) => a.id === filter.assignee)) &&
                          (!filter.label || c.labels.includes(filter.label)) &&
                          (!filter.hideDone || !c.completed_at),
                  ).length,
              0,
          )
        : total;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Proyectos', href: '/proyectos' },
        { title: project.name, href: route('projects.show', project.id) },
    ];

    /* The open card lives in the URL (?tarjeta=): reloads and redirects keep the modal where it was. */
    const openCard = (id: number | null) =>
        router.get(
            route('projects.show', id ? { project: project.id, tarjeta: id } : { project: project.id }),
            {},
            { only: ['card', 'subtask'], preserveState: true, preserveScroll: true, replace: true },
        );
    /* A subtask opens over its card and also lives in the URL (?subtarea=). */
    const openSubtask = (id: number | null) =>
        card &&
        router.get(
            route('projects.show', id ? { project: project.id, tarjeta: card.id, subtarea: id } : { project: project.id, tarjeta: card.id }),
            {},
            { only: ['subtask'], preserveState: true, preserveScroll: true, replace: true },
        );

    useEffect(() => {
        const id = setInterval(() => router.reload({ only: ['project', 'card', 'subtask', ...(showActivity ? ['activity'] : [])] }), 60000);
        return () => clearInterval(id);
    }, [showActivity]);
    useEffect(() => {
        if (showActivity) router.reload({ only: ['activity'] });
    }, [showActivity, project]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={project.name} />
            <div className="flex h-[calc(100svh-3rem)] flex-col">
                <header className="flex h-14 shrink-0 items-center gap-3 border-b px-4">
                    <Link
                        href={route('projects.index')}
                        className="text-muted-foreground hover:bg-muted hover:text-foreground rounded-md p-1"
                        aria-label="Volver a proyectos"
                    >
                        <ChevronLeft className="h-5 w-5" />
                    </Link>
                    {project.client?.logo_url ? (
                        <img src={project.client.logo_url} alt={project.client.name} className="bg-card h-7 w-7 rounded-md border object-contain" />
                    ) : (
                        <span
                            className="flex h-7 w-7 items-center justify-center rounded-md text-sm font-bold text-white select-none"
                            style={{ backgroundColor: project.color }}
                        >
                            {project.name.trim().charAt(0).toUpperCase()}
                        </span>
                    )}
                    <div className="min-w-0">
                        <h1 className="truncate text-base font-semibold">{project.name}</h1>
                    </div>
                    {project.client && (
                        <Badge variant="outline" style={{ borderColor: project.client.color, color: project.client.color }}>
                            {project.client.name}
                        </Badge>
                    )}
                    {project.archived_at && <Badge variant="secondary">Archivado</Badge>}
                    <div className="ml-auto flex items-center gap-3">
                        {LIVE && <Online projectId={project.id} />}
                        <span className="text-muted-foreground text-xs">{project.members.length} miembros</span>
                        <Button variant={showActivity ? 'secondary' : 'outline'} size="sm" onClick={() => setShowActivity((v) => !v)}>
                            <History className="h-4 w-4" />
                            Actividad
                        </Button>
                        {project.my_role === 'manager' && (
                            <Button variant="outline" size="sm" onClick={() => setSettings(true)}>
                                <Settings2 className="h-4 w-4" />
                                Configuración
                            </Button>
                        )}
                    </div>
                </header>
                <div className="bg-muted/30 flex shrink-0 flex-wrap items-center gap-2 border-b px-4 py-2">
                    <div className="relative">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2" />
                        <Input
                            className="bg-card h-8 w-56 pl-8 text-xs"
                            placeholder="Buscar tarjetas…"
                            value={filter.q}
                            onChange={(e) => setFilter({ ...filter, q: e.target.value })}
                        />
                    </div>
                    <button
                        type="button"
                        onClick={() => setFilter({ ...filter, mine: !filter.mine })}
                        className={cn(
                            'flex h-8 items-center gap-1.5 rounded-md border px-2.5 text-xs font-medium transition',
                            filter.mine ? 'border-primary bg-primary text-primary-foreground' : 'bg-card text-muted-foreground hover:bg-muted',
                        )}
                    >
                        <UserCheck className="h-3.5 w-3.5" />
                        Mis tarjetas
                    </button>
                    <Select
                        value={filter.assignee ? String(filter.assignee) : 'any'}
                        onValueChange={(v) => setFilter({ ...filter, assignee: v === 'any' ? null : Number(v) })}
                    >
                        <SelectTrigger className="bg-card h-8 w-44 text-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="any">Cualquier responsable</SelectItem>
                            {project.members.map((m) => (
                                <SelectItem key={m.id} value={String(m.id)}>
                                    {m.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={filter.label ? String(filter.label) : 'any'}
                        onValueChange={(v) => setFilter({ ...filter, label: v === 'any' ? null : Number(v) })}
                    >
                        <SelectTrigger className="bg-card h-8 w-40 text-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="any">Cualquier etiqueta</SelectItem>
                            {project.labels.map((l) => (
                                <SelectItem key={l.id} value={String(l.id)}>
                                    {l.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <label className="text-muted-foreground flex h-8 items-center gap-1.5 text-xs">
                        <Checkbox checked={filter.hideDone} onCheckedChange={(v) => setFilter({ ...filter, hideDone: v === true })} />
                        Ocultar completadas
                    </label>
                    {filterActive && (
                        <button
                            type="button"
                            onClick={() => setFilter(EMPTY_FILTER)}
                            className="text-muted-foreground hover:text-foreground flex items-center gap-1 text-xs"
                        >
                            <X className="h-3.5 w-3.5" />
                            Limpiar filtros
                        </button>
                    )}
                    {filterActive && (
                        <span className="text-muted-foreground ml-auto text-xs">
                            {shown} de {total} tarjetas
                        </span>
                    )}
                </div>
                <div className="flex min-h-0 flex-1">
                    <div className="min-w-0 flex-1 p-4">
                        <Board project={project} filter={filter} meId={auth.user.id} onOpenCard={openCard} />
                    </div>
                    {showActivity && <ActivityPanel activity={activity} onClose={() => setShowActivity(false)} onOpenCard={openCard} />}
                </div>
            </div>
            <CardModal
                card={card}
                project={project}
                priorities={priorities}
                projects={all_projects}
                statuses={subtask_statuses}
                onOpenSubtask={openSubtask}
                onClose={() => openCard(null)}
            />
            <SubtaskDialog
                subtask={subtask}
                parentTitle={card?.title ?? ''}
                project={project}
                priorities={priorities}
                statuses={subtask_statuses}
                onClose={() => openSubtask(null)}
            />
            {project.my_role === 'manager' && (
                <ProjectSettingsDialog
                    open={settings}
                    onClose={() => setSettings(false)}
                    project={project}
                    clients={clients}
                    people={people}
                    fieldTypes={field_types}
                />
            )}
        </AppLayout>
    );
}

/** Who has this board open right now (presence channel). */
function Online({ projectId }: { projectId: number }) {
    const { online } = useLiveBoard(projectId);
    if (online.length === 0) return null;
    return (
        <div className="flex items-center -space-x-1.5" title="Miembros conectados">
            {online.map((p) => (
                <span key={p.id} className="relative">
                    <PersonAvatar person={p} className="ring-background ring-2" />
                    <span className="border-background absolute -right-0.5 -bottom-0.5 h-2.5 w-2.5 rounded-full border-2 bg-emerald-500" />
                </span>
            ))}
        </div>
    );
}
