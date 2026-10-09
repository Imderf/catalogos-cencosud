import { Comments } from '@/components/projects/card-modal';
import DateField from '@/components/projects/date-field';
import { MultiSelect } from '@/components/projects/multi-select';
import StatusPicker, { StatusIcon } from '@/components/projects/status-picker';
import { Button } from '@/components/ui/button';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { firstError } from '@/hooks/use-flash-toast';
import { fmtAgo, fmtDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type SharedData, type TypeOption } from '@/types';
import {
    type DeliverableDto,
    type IntakeDto,
    type Priority,
    type ProjectBoard,
    type StatusOption,
    type SubtaskDetail,
    type SubtaskStatus,
} from '@/types/projects';
import { Link, router, usePage } from '@inertiajs/react';
import {
    BookImage,
    CalendarDays,
    Check,
    ChevronDown,
    ClipboardList,
    ExternalLink,
    Flag,
    History,
    Inbox,
    Loader2,
    Lock,
    Plus,
    Trash2,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

const opts = { preserveScroll: true, preserveState: true } as const;

/** One subtask, opened over the card: status, people, date, description and its own conversation. */
export default function SubtaskDialog({
    subtask,
    parentTitle,
    project,
    priorities,
    statuses,
    onClose,
}: {
    subtask: SubtaskDetail | null;
    parentTitle: string;
    project: ProjectBoard;
    priorities: TypeOption[];
    statuses: StatusOption[];
    onClose: () => void;
}) {
    return (
        <Dialog open={!!subtask} onOpenChange={(v) => !v && onClose()}>
            <DialogContent className="flex max-h-[90vh] w-[calc(100%-2rem)] max-w-2xl flex-col gap-0 overflow-hidden p-0 [&>button:last-child]:hidden">
                {subtask && (
                    <Body
                        key={subtask.id}
                        subtask={subtask}
                        parentTitle={parentTitle}
                        project={project}
                        priorities={priorities}
                        statuses={statuses}
                        onClose={onClose}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function Body({
    subtask: s,
    parentTitle,
    project,
    priorities,
    statuses,
    onClose,
}: {
    subtask: SubtaskDetail;
    parentTitle: string;
    project: ProjectBoard;
    priorities: TypeOption[];
    statuses: StatusOption[];
    onClose: () => void;
}) {
    const { auth } = usePage<SharedData>().props;
    const readOnly = !project.can.work;
    const [title, setTitle] = useState(s.title);
    const [desc, setDesc] = useState(s.description ?? '');
    const [history, setHistory] = useState(false);
    const [confirm, setConfirm] = useState(false);
    const [deleting, setDeleting] = useState(false);
    useEffect(() => {
        setTitle(s.title);
        setDesc(s.description ?? '');
    }, [s.title, s.description]);

    const patch = (
        partial: Partial<{
            title: string;
            description: string | null;
            status: SubtaskStatus;
            priority: Priority;
            due_date: string | null;
            assignees: number[];
        }>,
    ) => router.put(route('subtasks.update', s.id), partial, { ...opts, onError: (e) => toast.error(firstError(e)) });
    const destroy = () => {
        setDeleting(true);
        router.delete(route('subtasks.destroy', s.id), {
            preserveScroll: true,
            onSuccess: onClose,
            onError: (e) => toast.error(firstError(e)),
            onFinish: () => setDeleting(false),
        });
    };
    const canDelete = project.can.work && !s.automatic && (project.can.manage || s.created_by === auth.user.id);

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <div className="shrink-0 border-b px-6 pt-5 pb-3">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0 flex-1">
                        <p className="text-muted-foreground mb-1 text-xs">
                            Subtarea de <span className="text-foreground font-medium">{parentTitle}</span>
                            {s.page
                                ? ' · página del catálogo: su nombre y estado siguen la revisión'
                                : s.created_by_name && ` · creada por ${s.created_by_name}`}
                        </p>
                        <DialogTitle asChild>
                            <input
                                value={title}
                                disabled={readOnly || s.automatic}
                                onChange={(e) => setTitle(e.target.value)}
                                onBlur={() => title.trim() && title !== s.title && patch({ title: title.trim() })}
                                onKeyDown={(e) => e.key === 'Enter' && (e.target as HTMLInputElement).blur()}
                                className={cn(
                                    'hover:border-border focus:border-primary -ml-2 w-full rounded-md border border-transparent bg-transparent px-2 py-1 text-lg font-semibold focus:outline-none',
                                    s.closed && 'text-muted-foreground line-through',
                                )}
                                aria-label="Título de la subtarea"
                            />
                        </DialogTitle>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-muted-foreground hover:bg-muted hover:text-foreground rounded-md p-1"
                        aria-label="Cerrar subtarea"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>
                <div className="mt-3 flex flex-wrap items-center gap-2 text-sm">
                    {s.automatic ? (
                        /* A page subtask: its status follows the catalog review, nobody moves it by hand. */
                        <button
                            type="button"
                            title="El estado sigue la revisión del catálogo: cambia solo cuando la página avanza"
                            onClick={() => toast.info('El estado de esta subtarea lo mueve la revisión del catálogo; no se cambia a mano.')}
                            className="bg-muted/40 text-muted-foreground inline-flex h-8 cursor-help items-center gap-1.5 rounded-md border border-dashed px-2.5 text-sm"
                        >
                            <StatusIcon status={s.status} />
                            {s.status_label}
                            <Lock className="h-3 w-3" />
                        </button>
                    ) : (
                        <StatusPicker value={s.status} options={statuses} onChange={(st) => patch({ status: st })} disabled={readOnly}>
                            <span className="bg-card hover:bg-muted inline-flex h-8 items-center gap-1.5 rounded-md border px-2.5 text-sm">
                                <StatusIcon status={s.status} />
                                {s.status_label}
                                <ChevronDown className="text-muted-foreground h-3.5 w-3.5" />
                            </span>
                        </StatusPicker>
                    )}
                    <Select value={s.priority} onValueChange={(v) => patch({ priority: v as Priority })} disabled={readOnly}>
                        <SelectTrigger className="h-8 w-36">
                            <Flag className="h-3.5 w-3.5" />
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {priorities.map((p) => (
                                <SelectItem key={p.value} value={p.value}>
                                    {p.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <span className="inline-flex items-center gap-1">
                        <CalendarDays className="text-muted-foreground h-3.5 w-3.5" />
                        <DateField
                            className={cn('h-8 w-40', s.overdue && 'border-red-400 text-red-600')}
                            value={s.due_date}
                            disabled={readOnly}
                            onCommit={(v) => patch({ due_date: v })}
                        />
                    </span>
                    {s.page?.url && (
                        <Button variant="outline" size="sm" className="ml-auto" asChild>
                            <Link href={s.page.url}>
                                <BookImage className="h-3.5 w-3.5" />
                                Abrir {s.page.label}
                            </Link>
                        </Button>
                    )}
                </div>
            </div>
            <div className="min-h-0 flex-1 space-y-6 overflow-y-auto px-6 py-5">
                <section>
                    <h4 className="text-muted-foreground mb-1.5 flex items-center gap-1.5 text-xs font-semibold tracking-wide uppercase">
                        <Users className="h-3.5 w-3.5" />
                        Responsables
                    </h4>
                    <MultiSelect
                        placeholder="Asignar persona…"
                        emptyText="No hay más miembros"
                        disabled={readOnly}
                        value={s.assignees.map((a) => a.id)}
                        onChange={(ids) => patch({ assignees: ids })}
                        options={project.members.map((m) => ({ id: m.id, label: m.name, hint: m.id === auth.user.id ? 'tú' : undefined, avatar: m }))}
                    />
                </section>
                <section>
                    <h4 className="text-muted-foreground mb-1.5 text-xs font-semibold tracking-wide uppercase">Descripción</h4>
                    <Textarea
                        value={desc}
                        disabled={readOnly}
                        rows={3}
                        placeholder="Qué hay que hacer…"
                        onChange={(e) => setDesc(e.target.value)}
                        onBlur={() => desc !== (s.description ?? '') && patch({ description: desc || null })}
                    />
                </section>
                {(s.page || s.intakes.length > 0) && <IntakeSection subtask={s} project={project} />}
                <Deliverables subtask={s} project={project} readOnly={readOnly} />
                <Comments comments={s.comments} storeUrl={route('subtasks.comments.store', s.id)} project={project} readOnly={readOnly} />
                <section>
                    <button
                        type="button"
                        onClick={() => setHistory((h) => !h)}
                        className="text-muted-foreground hover:text-foreground flex items-center gap-1.5 text-xs font-semibold tracking-wide uppercase"
                    >
                        <History className="h-3.5 w-3.5" />
                        Historial ({s.activity.length})
                    </button>
                    {history && (
                        <ol className="mt-2 grid gap-1.5 text-xs">
                            {s.activity.map((a) => (
                                <li key={a.id}>
                                    <span className="font-medium">{a.actor}</span> {a.text}{' '}
                                    <span className="text-muted-foreground">· {fmtDateTime(a.at)}</span>
                                </li>
                            ))}
                        </ol>
                    )}
                </section>
                {canDelete && (
                    <div className="border-t pt-4">
                        <Button type="button" variant="ghost" size="sm" className="text-red-600 hover:text-red-700" onClick={() => setConfirm(true)}>
                            <Trash2 className="h-4 w-4" />
                            Eliminar subtarea
                        </Button>
                    </div>
                )}
            </div>
            <ConfirmDialog
                open={confirm}
                onClose={() => setConfirm(false)}
                onConfirm={destroy}
                loading={deleting}
                title="Eliminar subtarea"
                message="Se borran también sus comentarios y archivos."
            />
        </div>
    );
}

/** Client comments on this page, waiting for a moderator to let them in or dismiss them. */
function IntakeSection({ subtask: s, project }: { subtask: SubtaskDetail; project: ProjectBoard }) {
    const pending = s.intakes.filter((i) => i.status === 'pending');
    const decided = s.intakes.filter((i) => i.status !== 'pending');
    const [showDecided, setShowDecided] = useState(false);
    const [dismissing, setDismissing] = useState<IntakeDto | null>(null);
    const [reason, setReason] = useState('');
    const [busy, setBusy] = useState<number | null>(null);
    const approve = (i: IntakeDto) => {
        setBusy(i.id);
        router.post(route('intakes.approve', i.id), {}, { ...opts, onError: (e) => toast.error(firstError(e)), onFinish: () => setBusy(null) });
    };
    const dismiss = () => {
        if (!dismissing) return;
        setBusy(dismissing.id);
        router.post(
            route('intakes.dismiss', dismissing.id),
            { reason },
            {
                ...opts,
                onSuccess: () => {
                    setDismissing(null);
                    setReason('');
                },
                onError: (e) => toast.error(firstError(e)),
                onFinish: () => setBusy(null),
            },
        );
    };
    return (
        <section>
            <h4 className="text-muted-foreground mb-1.5 flex items-center gap-1.5 text-xs font-semibold tracking-wide uppercase">
                <Inbox className="h-3.5 w-3.5" />
                Comentarios del cliente por revisar ({pending.length})
            </h4>
            {pending.length === 0 ? (
                <p className="text-muted-foreground text-sm">Nada por revisar. Los comentarios que el cliente deje en esta página llegan aquí.</p>
            ) : (
                <ul className="grid gap-2">
                    {pending.map((i) => (
                        <li
                            key={i.id}
                            className="rounded-lg border border-amber-200 bg-amber-50/60 p-3 text-sm dark:border-amber-900 dark:bg-amber-950/30"
                        >
                            <IntakeBody intake={i} />
                            {project.can.manage ? (
                                <div className="mt-2 flex flex-wrap gap-2">
                                    <Button size="sm" onClick={() => approve(i)} disabled={busy === i.id}>
                                        {busy === i.id ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Check className="h-3.5 w-3.5" />}
                                        Aprobar ingreso
                                    </Button>
                                    <Button size="sm" variant="outline" onClick={() => setDismissing(i)} disabled={busy === i.id}>
                                        <X className="h-3.5 w-3.5" />
                                        Descartar
                                    </Button>
                                </div>
                            ) : (
                                <p className="text-muted-foreground mt-2 text-xs">Un moderador del proyecto decide si entra como entregable.</p>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {decided.length > 0 && (
                <div className="mt-2">
                    <button type="button" onClick={() => setShowDecided((v) => !v)} className="text-muted-foreground hover:text-foreground text-xs">
                        {showDecided ? 'Ocultar' : 'Ver'} revisados ({decided.length})
                    </button>
                    {showDecided && (
                        <ul className="mt-2 grid gap-2">
                            {decided.map((i) => (
                                <li key={i.id} className="bg-muted/40 rounded-lg border p-3 text-sm opacity-80">
                                    <IntakeBody intake={i} />
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {i.status === 'approved' ? 'Aprobado' : 'Descartado'} por {i.decided_by ?? '—'}
                                        {i.decided_at && ` · ${fmtAgo(i.decided_at)}`}
                                        {i.reason && ` · ${i.reason}`}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
            <Dialog open={!!dismissing} onOpenChange={(v) => !v && setDismissing(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogTitle>Descartar comentario</DialogTitle>
                    <p className="text-muted-foreground text-sm">
                        No entra como entregable. El cliente no ve esta decisión; su comentario sigue su curso en la revisión.
                    </p>
                    <div className="grid gap-1.5">
                        <Label htmlFor="dismiss-reason">Motivo (opcional)</Label>
                        <Input
                            id="dismiss-reason"
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            placeholder="Ya está resuelto en la versión 2…"
                            maxLength={500}
                        />
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button variant="secondary" onClick={() => setDismissing(null)}>
                            Cancelar
                        </Button>
                        <Button variant="destructive" onClick={dismiss} disabled={busy !== null}>
                            Descartar
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </section>
    );
}

function IntakeBody({ intake: i }: { intake: IntakeDto }) {
    const a = i.adjustment;
    if (!a) return <p className="text-muted-foreground">El comentario ya no existe.</p>;
    return (
        <div>
            <p className="text-muted-foreground flex flex-wrap items-center gap-x-2 text-xs">
                <span className="text-foreground font-medium">{a.author}</span>
                {a.role_label && <span>· {a.role_label}</span>}
                <span>· {a.type_label}</span>
                <span>· v{a.version}</span>
                <span>· {fmtAgo(a.at)}</span>
                {!a.open && <span className="text-emerald-700">· resuelto en la revisión</span>}
                {a.url && (
                    <Link href={a.url} className="text-primary ml-auto inline-flex items-center gap-1 hover:underline">
                        Ver en la página
                        <ExternalLink className="h-3 w-3" />
                    </Link>
                )}
            </p>
            <p className="mt-1 whitespace-pre-wrap">{a.text}</p>
        </div>
    );
}

/** What Ogilvy commits to on this page: from approved comments or added by hand. */
function Deliverables({ subtask: s, project, readOnly }: { subtask: SubtaskDetail; project: ProjectBoard; readOnly: boolean }) {
    const [adding, setAdding] = useState(false);
    const [title, setTitle] = useState('');
    const [busy, setBusy] = useState(false);
    const [removing, setRemoving] = useState<DeliverableDto | null>(null);
    const open = s.deliverables.filter((d) => d.status !== 'done').length;
    const add = () => {
        if (!title.trim()) return setAdding(false);
        setBusy(true);
        router.post(
            route('subtasks.deliverables.store', s.id),
            { title: title.trim() },
            { ...opts, onSuccess: () => setTitle(''), onError: (e) => toast.error(firstError(e)), onFinish: () => setBusy(false) },
        );
    };
    const patch = (d: DeliverableDto, data: { title?: string; status?: string; assignee_id?: number | null; due_date?: string | null }) =>
        router.put(route('deliverables.update', d.id), data, { ...opts, onError: (e) => toast.error(firstError(e)) });
    const remove = () => removing && router.delete(route('deliverables.destroy', removing.id), { ...opts, onFinish: () => setRemoving(null) });
    return (
        <section>
            <h4 className="text-muted-foreground mb-1.5 flex items-center gap-1.5 text-xs font-semibold tracking-wide uppercase">
                <ClipboardList className="h-3.5 w-3.5" />
                Entregables · {open} abierto{open === 1 ? '' : 's'}
            </h4>
            <div className="overflow-hidden rounded-lg border">
                {s.deliverables.length > 0 && (
                    <ul className="divide-y">
                        {s.deliverables.map((d) => (
                            <li
                                key={d.id}
                                className={cn(
                                    'grid grid-cols-[1fr_140px_130px_120px_28px] items-center gap-2 px-2 py-1.5 text-sm',
                                    d.status === 'done' && 'opacity-60',
                                )}
                            >
                                <span className="flex min-w-0 items-center gap-1.5">
                                    {d.from_client && (
                                        <span
                                            className="shrink-0 rounded bg-sky-100 px-1 text-[10px] font-semibold text-sky-800 dark:bg-sky-900/60 dark:text-sky-200"
                                            title="Viene de un comentario del cliente"
                                        >
                                            cliente
                                        </span>
                                    )}
                                    <span className={cn('truncate', d.status === 'done' && 'line-through')} title={d.title}>
                                        {d.title}
                                    </span>
                                </span>
                                <Select
                                    value={d.assignee ? String(d.assignee.id) : 'none'}
                                    onValueChange={(v) => patch(d, { assignee_id: v === 'none' ? null : Number(v) })}
                                    disabled={readOnly}
                                >
                                    <SelectTrigger className="h-7 text-xs">
                                        <SelectValue placeholder="Responsable" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Sin responsable</SelectItem>
                                        {project.members.map((m) => (
                                            <SelectItem key={m.id} value={String(m.id)}>
                                                {m.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select value={d.status} onValueChange={(v) => patch(d, { status: v })} disabled={readOnly}>
                                    <SelectTrigger className="h-7 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {s.deliverable_statuses.map((o) => (
                                            <SelectItem key={o.value} value={o.value}>
                                                {o.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <DateField
                                    className={cn('h-7 text-xs', d.overdue && 'border-red-400 text-red-600')}
                                    value={d.due_date}
                                    disabled={readOnly}
                                    onCommit={(v) => patch(d, { due_date: v })}
                                />
                                {project.can.manage ? (
                                    <button
                                        type="button"
                                        onClick={() => setRemoving(d)}
                                        className="text-muted-foreground hover:text-red-600"
                                        aria-label="Quitar entregable"
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </button>
                                ) : (
                                    <span />
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                {s.deliverables.length === 0 && !adding && (
                    <p className="text-muted-foreground px-3 py-3 text-xs">Sin entregables. Aprueba un comentario del cliente o agrega uno a mano.</p>
                )}
                {!readOnly &&
                    (adding ? (
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                add();
                            }}
                            className="flex items-center gap-2 border-t px-2 py-1.5"
                        >
                            <Input
                                autoFocus
                                value={title}
                                onChange={(e) => setTitle(e.target.value)}
                                onKeyDown={(e) => e.key === 'Escape' && setAdding(false)}
                                placeholder="Qué hay que entregar"
                                maxLength={300}
                                className="h-8 text-sm"
                            />
                            <Button type="submit" size="sm" disabled={busy}>
                                {busy && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                                Agregar
                            </Button>
                            <Button type="button" size="sm" variant="ghost" onClick={() => setAdding(false)}>
                                Cancelar
                            </Button>
                        </form>
                    ) : (
                        <button
                            type="button"
                            onClick={() => setAdding(true)}
                            className="text-muted-foreground hover:bg-muted hover:text-foreground flex w-full items-center gap-1.5 border-t px-3 py-1.5 text-left text-xs"
                        >
                            <Plus className="h-3.5 w-3.5" />
                            Agregar entregable
                        </button>
                    ))}
            </div>
            {s.deliverables.some((d) => d.due_date) && (
                <p className="text-muted-foreground mt-1 text-[11px]">
                    Las fechas en rojo están vencidas. Un entregable que viene de un comentario se marca listo solo cuando Ogilvy sube la versión que
                    lo resuelve.
                </p>
            )}
            <ConfirmDialog
                open={!!removing}
                onClose={() => setRemoving(null)}
                onConfirm={remove}
                title="Quitar entregable"
                message={`Se quita "${removing?.title ?? ''}" de la página.`}
                confirmLabel="Quitar"
            />
        </section>
    );
}
