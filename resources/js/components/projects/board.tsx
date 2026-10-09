import { AvatarStack } from '@/components/projects/person-avatar';
import { Button } from '@/components/ui/button';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { firstError } from '@/hooks/use-flash-toast';
import { fmtDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type CardDto, type ListDto, PRIORITY_CLASS, type ProjectBoard } from '@/types/projects';
import {
    closestCorners,
    DndContext,
    type DragEndEvent,
    type DragOverEvent,
    DragOverlay,
    type DragStartEvent,
    PointerSensor,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import { arrayMove, horizontalListSortingStrategy, SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { router } from '@inertiajs/react';
import {
    AlignLeft,
    BookImage,
    CalendarDays,
    CheckCircle2,
    CheckSquare,
    Inbox,
    Loader2,
    MessageSquare,
    MoreHorizontal,
    Paperclip,
    Pencil,
    Plus,
    Trash2,
    X,
} from 'lucide-react';
import { FormEventHandler, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

export interface BoardFilter {
    q: string;
    mine: boolean;
    assignee: number | null;
    label: number | null;
    hideDone: boolean;
}
export const EMPTY_FILTER: BoardFilter = { q: '', mine: false, assignee: null, label: null, hideDone: false };

const listKey = (id: number) => `list-${id}`;
const cardKey = (id: number) => `card-${id}`;
const parse = (key: string): { type: 'list' | 'card'; id: number } => {
    const [type, id] = key.split('-');
    return { type: type as 'list' | 'card', id: Number(id) };
};

/** Float position between two neighbours; the server renumbers when the gaps run out. */
function between(prev?: number, next?: number): number {
    if (prev === undefined && next === undefined) return 1024;
    if (prev === undefined) return (next as number) / 2;
    if (next === undefined) return prev + 1024;
    return (prev + next) / 2;
}

/** Today / overdue / future for the date chip. */
export function dueState(due: string | null, done: boolean, today: string): 'none' | 'overdue' | 'today' | 'future' {
    if (!due || done) return 'none';
    return due < today ? 'overdue' : due === today ? 'today' : 'future';
}

/**
 * Kanban board. Local state mirrors the server lists so drags feel instant;
 * every drop sends one request and the props refresh afterwards.
 */
export default function Board({
    project,
    filter,
    meId,
    onOpenCard,
}: {
    project: ProjectBoard;
    filter: BoardFilter;
    meId: number;
    onOpenCard: (id: number) => void;
}) {
    const [lists, setLists] = useState<ListDto[]>(project.lists);
    const [active, setActive] = useState<{ type: 'list' | 'card'; id: number } | null>(null);
    const [deleteList, setDeleteList] = useState<ListDto | null>(null);
    const [deleting, setDeleting] = useState(false);
    useEffect(() => {
        if (!active) setLists(project.lists);
    }, [project.lists, active]);

    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));
    const filterActive = Boolean(filter.q || filter.mine || filter.assignee || filter.label || filter.hideDone);
    const visible = useMemo(
        () =>
            !filterActive
                ? lists
                : lists.map((l) => ({
                      ...l,
                      cards: l.cards.filter(
                          (c) =>
                              (!filter.q || c.title.toLowerCase().includes(filter.q.toLowerCase())) &&
                              (!filter.mine || c.assignees.some((a) => a.id === meId)) &&
                              (!filter.assignee || c.assignees.some((a) => a.id === filter.assignee)) &&
                              (!filter.label || c.labels.includes(filter.label)) &&
                              (!filter.hideDone || !c.completed_at),
                      ),
                  })),
        [lists, filter, filterActive, meId],
    );

    const findList = (key: string): ListDto | undefined => {
        const { type, id } = parse(key);
        if (type === 'list') return lists.find((l) => l.id === id);
        return lists.find((l) => l.cards.some((c) => c.id === id));
    };

    const onDragStart = ({ active }: DragStartEvent) => setActive(parse(String(active.id)));

    /* While dragging a card over another list, move it there locally so the columns open up. */
    const onDragOver = ({ active, over }: DragOverEvent) => {
        if (!over || parse(String(active.id)).type !== 'card') return;
        const from = findList(String(active.id));
        const to = findList(String(over.id));
        if (!from || !to || from.id === to.id) return;
        const card = from.cards.find((c) => c.id === parse(String(active.id)).id);
        if (!card) return;
        const overParsed = parse(String(over.id));
        const overIndex = overParsed.type === 'card' ? to.cards.findIndex((c) => c.id === overParsed.id) : to.cards.length;
        setLists((ls) =>
            ls.map((l) => {
                if (l.id === from.id) return { ...l, cards: l.cards.filter((c) => c.id !== card.id) };
                if (l.id === to.id) {
                    const cards = [...l.cards];
                    cards.splice(overIndex < 0 ? cards.length : overIndex, 0, { ...card, list_id: to.id });
                    return { ...l, cards };
                }
                return l;
            }),
        );
    };

    const fail = (e: Record<string, string>) => {
        toast.error(firstError(e));
        router.reload({ only: ['project'] });
    };

    const onDragEnd = ({ active, over }: DragEndEvent) => {
        setActive(null);
        if (!over) return;
        const a = parse(String(active.id));
        if (a.type === 'list') {
            const o = parse(String(over.id));
            if (o.type !== 'list' || a.id === o.id) return;
            const oldIndex = lists.findIndex((l) => l.id === a.id);
            const newIndex = lists.findIndex((l) => l.id === o.id);
            const moved = arrayMove(lists, oldIndex, newIndex);
            const position = between(moved[newIndex - 1]?.position, moved[newIndex + 1]?.position);
            setLists(moved.map((l) => (l.id === a.id ? { ...l, position } : l)));
            router.put(route('lists.move', a.id), { position }, { preserveScroll: true, preserveState: true, onError: fail });
            return;
        }
        const list = findList(String(active.id));
        if (!list) return;
        const o = parse(String(over.id));
        const oldIndex = list.cards.findIndex((c) => c.id === a.id);
        const newIndex = o.type === 'card' ? list.cards.findIndex((c) => c.id === o.id) : list.cards.length - 1;
        const cards = arrayMove(list.cards, oldIndex, newIndex < 0 ? oldIndex : newIndex);
        const idx = cards.findIndex((c) => c.id === a.id);
        const position = between(cards[idx - 1]?.position, cards[idx + 1]?.position);
        const original = project.lists.find((l) => l.cards.some((c) => c.id === a.id));
        const originalIdx = original?.cards.findIndex((c) => c.id === a.id) ?? -1;
        setLists((ls) => ls.map((l) => (l.id === list.id ? { ...l, cards: cards.map((c) => (c.id === a.id ? { ...c, position } : c)) } : l)));
        if (original?.id === list.id && originalIdx === idx) return; // dropped where it was
        router.put(route('cards.move', a.id), { board_list_id: list.id, position }, { preserveScroll: true, preserveState: true, onError: fail });
    };

    const removeList = () => {
        if (!deleteList) return;
        setDeleting(true);
        router.delete(route('lists.destroy', deleteList.id), {
            preserveScroll: true,
            onFinish: () => {
                setDeleting(false);
                setDeleteList(null);
            },
        });
    };

    const activeCard = active?.type === 'card' ? lists.flatMap((l) => l.cards).find((c) => c.id === active.id) : null;
    const activeList = active?.type === 'list' ? lists.find((l) => l.id === active.id) : null;
    const today = new Date().toISOString().slice(0, 10);

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCorners}
            onDragStart={onDragStart}
            onDragOver={onDragOver}
            onDragEnd={onDragEnd}
            onDragCancel={() => setActive(null)}
        >
            <div className="flex h-full items-start gap-3 overflow-x-auto pb-3">
                <SortableContext items={lists.map((l) => listKey(l.id))} strategy={horizontalListSortingStrategy}>
                    {visible.map((l) => (
                        <Column key={l.id} list={l} project={project} today={today} onOpenCard={onOpenCard} onDelete={() => setDeleteList(l)} />
                    ))}
                </SortableContext>
                {project.can.manage && <NewList projectId={project.id} />}
                {lists.length === 0 && !project.can.manage && <p className="text-muted-foreground text-sm">Este tablero no tiene listas todavía.</p>}
            </div>
            <DragOverlay>
                {activeCard && (
                    <div className="w-72">
                        <CardView card={activeCard} project={project} today={today} dragging />
                    </div>
                )}
                {activeList && (
                    <div className="bg-muted/90 w-72 rounded-xl border p-3 shadow-xl">
                        <p className="text-sm font-semibold">{activeList.name}</p>
                    </div>
                )}
            </DragOverlay>
            <ConfirmDialog
                open={!!deleteList}
                onClose={() => setDeleteList(null)}
                onConfirm={removeList}
                loading={deleting}
                title="Eliminar lista"
                message="Solo se pueden eliminar listas vacías. ¿Deseas continuar?"
            />
        </DndContext>
    );
}

/** Meaning of a list for linked catalogs: where a catalog waits, is reviewed or ends. */
const LIST_KINDS: { value: string; label: string; hint: string }[] = [
    { value: 'backlog', label: 'Backlog', hint: 'Los catálogos caen aquí mientras Ogilvy los prepara' },
    { value: 'preparation', label: 'Preparación', hint: 'Alternativa al Backlog para catálogos en preparación' },
    { value: 'review', label: 'En revisión', hint: 'Catálogos que el cliente está revisando' },
    { value: 'approved', label: 'Aprobados', hint: 'Catálogos aprobados a imprenta' },
];

/** Column: the whole header is the drag handle, like the original board. */
function Column({
    list,
    project,
    today,
    onOpenCard,
    onDelete,
}: {
    list: ListDto;
    project: ProjectBoard;
    today: string;
    onOpenCard: (id: number) => void;
    onDelete: () => void;
}) {
    const canManage = project.can.manage;
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: listKey(list.id),
        disabled: !canManage,
        data: { type: 'list' },
    });
    const { setNodeRef: setDropRef } = useDroppable({ id: listKey(list.id) });
    const [editing, setEditing] = useState(false);
    const [name, setName] = useState(list.name);
    const [adding, setAdding] = useState(false);

    const rename = () => {
        const n = name.trim() || list.name;
        if (n !== list.name) router.put(route('lists.update', list.id), { name: n }, { preserveScroll: true, preserveState: true });
        setEditing(false);
    };

    return (
        <div
            ref={setNodeRef}
            style={{ transform: CSS.Translate.toString(transform), transition }}
            className={cn('bg-muted/70 flex max-h-full w-72 shrink-0 flex-col rounded-xl', isDragging && 'opacity-40')}
        >
            <div
                {...attributes}
                {...listeners}
                className={cn('flex items-center gap-2 px-3 py-2.5', canManage && 'cursor-grab active:cursor-grabbing')}
            >
                {editing ? (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            rename();
                        }}
                        className="flex-1"
                    >
                        <Input
                            autoFocus
                            className="bg-card h-7 py-0 text-sm font-semibold"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            onBlur={rename}
                            maxLength={60}
                        />
                    </form>
                ) : (
                    <h3 className="flex-1 truncate text-sm font-semibold" onDoubleClick={() => canManage && setEditing(true)}>
                        {list.name}
                    </h3>
                )}
                {list.kind && (
                    <span
                        className="text-muted-foreground rounded border px-1 text-[10px] uppercase"
                        title={LIST_KINDS.find((k) => k.value === list.kind)?.hint}
                    >
                        {LIST_KINDS.find((k) => k.value === list.kind)?.label ?? list.kind}
                    </span>
                )}
                <span className="bg-card text-muted-foreground rounded-full px-1.5 text-[11px] font-medium">{list.cards.length}</span>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button
                            type="button"
                            onPointerDown={(e) => e.stopPropagation()}
                            className="text-muted-foreground hover:bg-background/70 rounded p-0.5"
                            aria-label="Opciones de la lista"
                        >
                            <MoreHorizontal className="h-4 w-4" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        {canManage && (
                            <DropdownMenuItem
                                onSelect={() => {
                                    setName(list.name);
                                    setEditing(true);
                                }}
                            >
                                <Pencil className="h-4 w-4" />
                                Renombrar
                            </DropdownMenuItem>
                        )}
                        {project.can.work && (
                            <DropdownMenuItem onSelect={() => setAdding(true)}>
                                <Plus className="h-4 w-4" />
                                Agregar tarjeta
                            </DropdownMenuItem>
                        )}
                        {canManage && (
                            <DropdownMenuSub>
                                <DropdownMenuSubTrigger>Tipo de lista</DropdownMenuSubTrigger>
                                <DropdownMenuSubContent>
                                    <DropdownMenuRadioGroup
                                        value={list.kind ?? 'none'}
                                        onValueChange={(v) =>
                                            router.put(
                                                route('lists.update', list.id),
                                                { kind: v === 'none' ? null : v },
                                                { preserveScroll: true, preserveState: true },
                                            )
                                        }
                                    >
                                        <DropdownMenuRadioItem value="none">Ninguno</DropdownMenuRadioItem>
                                        {LIST_KINDS.map((k) => (
                                            <DropdownMenuRadioItem key={k.value} value={k.value} title={k.hint}>
                                                {k.label}
                                            </DropdownMenuRadioItem>
                                        ))}
                                    </DropdownMenuRadioGroup>
                                </DropdownMenuSubContent>
                            </DropdownMenuSub>
                        )}
                        {canManage && <DropdownMenuSeparator />}
                        {canManage && (
                            <DropdownMenuItem onSelect={onDelete} className="text-red-600">
                                <Trash2 className="h-4 w-4" />
                                Eliminar lista
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
            <div ref={setDropRef} className="flex-1 space-y-2 overflow-y-auto px-2 pb-2">
                <SortableContext items={list.cards.map((c) => cardKey(c.id))} strategy={verticalListSortingStrategy}>
                    {list.cards.map((c) => (
                        <SortableCard
                            key={c.id}
                            card={c}
                            project={project}
                            today={today}
                            onOpen={() => onOpenCard(c.id)}
                            disabled={!project.can.work}
                        />
                    ))}
                </SortableContext>
                {list.cards.length === 0 && !adding && (
                    <div className="text-muted-foreground rounded-lg border border-dashed py-4 text-center text-xs">Suelta tarjetas aquí</div>
                )}
            </div>
            {project.can.work && (
                <div className="p-2 pt-0">
                    {adding ? (
                        <NewCard projectId={project.id} listId={list.id} onDone={() => setAdding(false)} />
                    ) : (
                        <button
                            type="button"
                            onClick={() => setAdding(true)}
                            className="text-muted-foreground hover:bg-background/70 hover:text-foreground flex w-full items-center gap-1.5 rounded-md px-2 py-1.5 text-sm"
                        >
                            <Plus className="h-4 w-4" />
                            Agregar tarjeta
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}

function SortableCard({
    card,
    project,
    today,
    onOpen,
    disabled,
}: {
    card: CardDto;
    project: ProjectBoard;
    today: string;
    onOpen: () => void;
    disabled: boolean;
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: cardKey(card.id),
        disabled,
        data: { type: 'card' },
    });
    return (
        /* The sortable wrapper is the accessible button: it opens the card on click or Enter and drags on pointer move. */
        <div
            ref={setNodeRef}
            style={{ transform: CSS.Translate.toString(transform), transition }}
            {...attributes}
            {...listeners}
            onClick={onOpen}
            onKeyDown={(e) => {
                if (e.key === 'Enter') onOpen();
                listeners?.onKeyDown?.(e);
            }}
            className={cn('cursor-pointer', isDragging && 'opacity-30')}
        >
            <CardView card={card} project={project} today={today} />
        </div>
    );
}

function CardView({ card, project, today, dragging }: { card: CardDto; project: ProjectBoard; today: string; dragging?: boolean }) {
    const labels = project.labels.filter((l) => card.labels.includes(l.id));
    const done = !!card.completed_at;
    const st = dueState(card.due_date, done, today);
    return (
        <div
            className={cn(
                'group bg-card hover:border-primary/40 overflow-hidden rounded-lg border shadow-xs transition hover:shadow-md',
                dragging && 'ring-primary/60 rotate-2 shadow-xl ring-2',
                done && 'opacity-70',
            )}
        >
            {card.cover && <img src={card.cover} alt="" className="h-28 w-full object-cover" loading="lazy" />}
            <div className="p-2.5">
                {labels.length > 0 && (
                    <div className="mb-1.5 flex flex-wrap gap-1">
                        {labels.map((l) => (
                            <span key={l.id} title={l.name} className="h-1.5 w-8 rounded-full" style={{ backgroundColor: l.color }} />
                        ))}
                    </div>
                )}
                <p className={cn('text-sm leading-snug', done && 'line-through')}>
                    {done && <CheckCircle2 className="mr-1 inline h-3.5 w-3.5 text-emerald-600" />}
                    {card.title}
                </p>
                {card.catalog && (
                    <div className="mt-2 grid gap-1">
                        <div className="text-muted-foreground flex items-center gap-1 text-[11px]">
                            <BookImage className="h-3 w-3" />
                            <span className="truncate">
                                {card.catalog.kind_label} · {card.catalog.status_label}
                            </span>
                            <span className="ml-auto tabular-nums">
                                {card.catalog.approved}/{card.catalog.pages}
                            </span>
                        </div>
                        <div className="bg-muted h-1 overflow-hidden rounded-full">
                            <div className="h-full rounded-full bg-emerald-500" style={{ width: `${card.catalog.progress}%` }} />
                        </div>
                    </div>
                )}
                <div className="text-muted-foreground mt-2 flex items-center gap-2 text-[11px]">
                    <span className={cn('rounded px-1.5 py-0.5 font-medium', PRIORITY_CLASS[card.priority])}>{card.priority_label}</span>
                    {card.due_date && (
                        <span
                            className={cn(
                                'flex items-center gap-1',
                                st === 'overdue' && 'font-medium text-red-600',
                                st === 'today' && 'font-medium text-amber-600',
                            )}
                        >
                            <CalendarDays className="h-3 w-3" />
                            {card.start_date && `${fmtDate(card.start_date)} → `}
                            {fmtDate(card.due_date)}
                        </span>
                    )}
                    {card.has_description && <AlignLeft className="h-3.5 w-3.5" />}
                    {card.comments_count > 0 && (
                        <span className="flex items-center gap-0.5">
                            <MessageSquare className="h-3 w-3" />
                            {card.comments_count}
                        </span>
                    )}
                    {card.attachments_count > 0 && (
                        <span className="flex items-center gap-0.5">
                            <Paperclip className="h-3 w-3" />
                            {card.attachments_count}
                        </span>
                    )}
                    {card.intake_pending > 0 && (
                        <span
                            className="flex items-center gap-0.5 rounded bg-amber-100 px-1 font-medium text-amber-800 dark:bg-amber-900/60 dark:text-amber-200"
                            title="Comentarios del cliente por revisar"
                        >
                            <Inbox className="h-3 w-3" />
                            {card.intake_pending} por revisar
                        </span>
                    )}
                    {card.subtasks_total > 0 && (
                        <span
                            className={cn('flex items-center gap-0.5', card.subtasks_open === 0 && 'text-emerald-600')}
                            title="Subtareas completadas"
                        >
                            <CheckSquare className="h-3 w-3" />
                            {card.subtasks_total - card.subtasks_open}/{card.subtasks_total}
                        </span>
                    )}
                    {card.assignees.length > 0 && (
                        <span className="ml-auto">
                            <AvatarStack people={card.assignees} max={3} />
                        </span>
                    )}
                </div>
            </div>
        </div>
    );
}

function NewCard({ projectId, listId, onDone }: { projectId: number; listId: number; onDone: () => void }) {
    const [title, setTitle] = useState('');
    const [busy, setBusy] = useState(false);
    const submit = () => {
        if (!title.trim()) return onDone();
        setBusy(true);
        router.post(
            route('projects.cards.store', projectId),
            { board_list_id: listId, title: title.trim() },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setTitle(''),
                onError: (e) => toast.error(firstError(e)),
                onFinish: () => setBusy(false),
            },
        );
    };
    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                submit();
            }}
            className="space-y-2"
        >
            <Textarea
                autoFocus
                rows={2}
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        submit();
                    }
                    if (e.key === 'Escape') onDone();
                }}
                placeholder="Título de la tarjeta"
                maxLength={200}
                className="bg-card min-h-0 text-sm"
            />
            <div className="flex gap-1">
                <Button size="sm" type="submit" disabled={busy}>
                    {busy && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                    Agregar
                </Button>
                <Button size="sm" variant="ghost" type="button" onClick={onDone} aria-label="Cancelar">
                    <X className="h-4 w-4" />
                </Button>
            </div>
        </form>
    );
}

function NewList({ projectId }: { projectId: number }) {
    const [open, setOpen] = useState(false);
    const [name, setName] = useState('');
    const [busy, setBusy] = useState(false);
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!name.trim()) return setOpen(false);
        setBusy(true);
        router.post(
            route('projects.lists.store', projectId),
            { name: name.trim() },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setName('');
                    setOpen(false);
                },
                onError: (er) => toast.error(firstError(er)),
                onFinish: () => setBusy(false),
            },
        );
    };
    return (
        <div className="w-72 shrink-0">
            {open ? (
                <form onSubmit={submit} className="bg-muted/70 space-y-2 rounded-xl p-2">
                    <Input
                        autoFocus
                        placeholder="Nombre de la lista"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        onKeyDown={(e) => e.key === 'Escape' && setOpen(false)}
                        maxLength={60}
                        className="bg-card"
                    />
                    <div className="flex gap-1">
                        <Button size="sm" type="submit" disabled={busy}>
                            {busy && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                            Agregar lista
                        </Button>
                        <Button size="sm" variant="ghost" type="button" onClick={() => setOpen(false)}>
                            Cancelar
                        </Button>
                    </div>
                </form>
            ) : (
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className="bg-muted/50 text-muted-foreground hover:bg-muted hover:text-foreground flex w-full items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium"
                >
                    <Plus className="h-4 w-4" />
                    Agregar lista
                </button>
            )}
        </div>
    );
}
