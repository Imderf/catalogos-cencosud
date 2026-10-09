import DateField from '@/components/projects/date-field';
import DuplicateCardDialog from '@/components/projects/duplicate-card-dialog';
import FieldInput from '@/components/projects/field-input';
import { MentionText, MentionTextarea } from '@/components/projects/mentions';
import { MultiSelect } from '@/components/projects/multi-select';
import { PersonAvatar } from '@/components/projects/person-avatar';
import StatusPicker, { StatusIcon } from '@/components/projects/status-picker';
import { Button } from '@/components/ui/button';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { firstError } from '@/hooks/use-flash-toast';
import { fmtAgo, fmtDate, fmtDateTime, fmtSize } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type SharedData, type TypeOption } from '@/types';
import {
    type AttachmentDto,
    type CardDetail,
    type CommentDto,
    type FieldValue,
    type Priority,
    PRIORITY_CLASS,
    type ProjectBoard,
    type ProjectOption,
    type StatusOption,
    type SubtaskDto,
    type SubtaskStatus,
} from '@/types/projects';
import { closestCenter, DndContext, type DragEndEvent, PointerSensor, useSensor, useSensors } from '@dnd-kit/core';
import { arrayMove, SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Link, router, usePage } from '@inertiajs/react';
import {
    BookImage,
    CalendarDays,
    CheckCircle2,
    CheckSquare,
    Columns3,
    Copy,
    ExternalLink,
    FileText,
    FileUp,
    Flag,
    GripVertical,
    History,
    Image as ImageIcon,
    Link2,
    Loader2,
    MessageSquare,
    Paperclip,
    Pencil,
    Plus,
    Reply,
    RotateCcw,
    Send,
    Tag,
    Trash2,
    Upload,
    Users,
    X,
} from 'lucide-react';
import { type ClipboardEvent, type ReactNode, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

const opts = { preserveScroll: true, preserveState: true } as const;

/**
 * The card as a wide modal, like the original board: main column (description, custom fields,
 * attachments, comments) and a side column with state, priority, date, people and labels.
 * Every field saves itself on blur or change; there is no "Guardar".
 */
export default function CardModal({
    card,
    project,
    priorities,
    projects,
    statuses,
    onOpenSubtask,
    onClose,
}: {
    card: CardDetail | null;
    project: ProjectBoard;
    priorities: TypeOption[];
    projects: ProjectOption[];
    statuses: StatusOption[];
    onOpenSubtask: (id: number) => void;
    onClose: () => void;
}) {
    return (
        <Dialog open={!!card} onOpenChange={(v) => !v && onClose()}>
            <DialogContent className="flex h-[90vh] w-[calc(100%-2rem)] max-w-6xl flex-col gap-0 overflow-hidden p-0 [&>button:last-child]:hidden">
                {card && (
                    <Body
                        key={card.id}
                        card={card}
                        project={project}
                        priorities={priorities}
                        projects={projects}
                        statuses={statuses}
                        onOpenSubtask={onOpenSubtask}
                        onClose={onClose}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function Body({
    card,
    project,
    priorities,
    projects,
    statuses,
    onOpenSubtask,
    onClose,
}: {
    card: CardDetail;
    project: ProjectBoard;
    priorities: TypeOption[];
    projects: ProjectOption[];
    statuses: StatusOption[];
    onOpenSubtask: (id: number) => void;
    onClose: () => void;
}) {
    const { auth } = usePage<SharedData>().props;
    const readOnly = !project.can.work;
    const [title, setTitle] = useState(card.title);
    const [desc, setDesc] = useState(card.description ?? '');
    const [duplicate, setDuplicate] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [history, setHistory] = useState(false);
    useEffect(() => {
        setTitle(card.title);
        setDesc(card.description ?? '');
    }, [card.title, card.description]);

    const list = project.lists.find((l) => l.id === card.list_id);
    const canDelete = project.can.work && (project.can.manage || card.created_by === auth.user.id);

    /** Partial update: the server needs the whole editable set, so we send the current values plus the change. */
    const patch = (
        partial: Partial<{
            title: string;
            description: string | null;
            priority: Priority;
            start_date: string | null;
            due_date: string | null;
            assignees: number[];
            labels: number[];
        }>,
    ) =>
        router.put(
            route('cards.update', card.id),
            {
                title: card.title,
                description: card.description,
                priority: card.priority,
                due_date: card.due_date,
                assignees: card.assignees.map((a) => a.id),
                labels: card.labels,
                ...partial,
            },
            { ...opts, onError: (e) => toast.error(firstError(e)) },
        );
    const move = (listId: number) => {
        const target = project.lists.find((l) => l.id === listId);
        const last = target?.cards[target.cards.length - 1];
        router.put(route('cards.move', card.id), { board_list_id: listId, position: (last?.position ?? 0) + 1024 }, opts);
    };
    const setField = (fieldId: number, value: FieldValue) => router.put(route('cards.fields', card.id), { values: { [fieldId]: value } }, opts);
    const uploadFieldImage = (fieldId: number, file: File) =>
        router.post(
            route('cards.fields.image', [card.id, fieldId]),
            { image: file },
            { ...opts, forceFormData: true, onError: (e) => toast.error(firstError(e)) },
        );
    const destroy = () => {
        setDeleting(true);
        router.delete(route('cards.destroy', card.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Tarjeta eliminada');
                onClose();
            },
            onFinish: () => setDeleting(false),
        });
    };

    return (
        <div className="flex min-h-0 flex-1">
            <div className="flex min-w-0 flex-1 flex-col">
                <div className="shrink-0 border-b px-6 pt-5 pb-3">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0 flex-1">
                            <p className="text-muted-foreground mb-1 text-xs">
                                En la lista <span className="text-foreground font-medium">{list?.name}</span>
                                {card.created_by_name && ` · creada por ${card.created_by_name}`}
                            </p>
                            <DialogTitle asChild>
                                <input
                                    value={title}
                                    disabled={readOnly}
                                    onChange={(e) => setTitle(e.target.value)}
                                    onBlur={() => title.trim() && title !== card.title && patch({ title: title.trim() })}
                                    onKeyDown={(e) => e.key === 'Enter' && (e.target as HTMLInputElement).blur()}
                                    className={cn(
                                        'hover:border-border focus:border-primary -ml-2 w-full rounded-md border border-transparent bg-transparent px-2 py-1 text-xl font-semibold focus:outline-none',
                                        card.completed_at && 'text-muted-foreground line-through',
                                    )}
                                    aria-label="Título"
                                />
                            </DialogTitle>
                        </div>
                        <button
                            type="button"
                            onClick={onClose}
                            className="text-muted-foreground hover:bg-muted hover:text-foreground rounded-md p-1"
                            aria-label="Cerrar"
                        >
                            <X className="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <div className="min-h-0 flex-1 space-y-7 overflow-y-auto px-6 py-5">
                    <Section title="Descripción">
                        <Textarea
                            value={desc}
                            disabled={readOnly}
                            onChange={(e) => setDesc(e.target.value)}
                            onBlur={() => desc !== (card.description ?? '') && patch({ description: desc || null })}
                            placeholder="Agrega una descripción detallada…"
                            rows={4}
                        />
                    </Section>
                    {project.fields.length > 0 && (
                        <Section title="Campos personalizados">
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {project.fields.map((f) => (
                                    <div key={f.id} className={f.type === 'textarea' ? 'sm:col-span-2 lg:col-span-3' : undefined}>
                                        <label className="text-muted-foreground mb-1 block text-xs font-medium">
                                            {f.name}
                                            {f.required && <span className="text-red-500"> *</span>}
                                        </label>
                                        <FieldInput
                                            field={f}
                                            value={card.values[f.id] ?? null}
                                            onChange={(v) => setField(f.id, v)}
                                            onUpload={(file) => uploadFieldImage(f.id, file)}
                                            people={project.members}
                                            disabled={readOnly}
                                        />
                                    </div>
                                ))}
                            </div>
                        </Section>
                    )}
                    <Subtasks card={card} project={project} statuses={statuses} readOnly={readOnly} onOpen={onOpenSubtask} />
                    <Attachments card={card} project={project} readOnly={readOnly} />
                    <Comments comments={card.comments} storeUrl={route('cards.comments.store', card.id)} project={project} readOnly={readOnly} />
                    <section>
                        <button
                            type="button"
                            onClick={() => setHistory((h) => !h)}
                            className="text-muted-foreground hover:text-foreground flex items-center gap-1.5 text-xs font-semibold tracking-wide uppercase"
                        >
                            <History className="h-3.5 w-3.5" />
                            Historial ({card.activity.length})
                        </button>
                        {history && (
                            <ol className="mt-2 grid gap-1.5 text-xs">
                                {card.activity.map((a) => (
                                    <li key={a.id}>
                                        <span className="font-medium">{a.actor}</span> {a.text}{' '}
                                        <span className="text-muted-foreground">· {fmtDateTime(a.at)}</span>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </section>
                </div>
            </div>

            <aside className="bg-muted/40 w-72 shrink-0 space-y-5 overflow-y-auto border-l p-5 text-sm">
                {!readOnly && (
                    <>
                        <Button
                            variant={card.completed_at ? 'secondary' : 'default'}
                            className="w-full"
                            onClick={() => router.put(route('cards.complete', card.id), {}, opts)}
                        >
                            {card.completed_at ? <RotateCcw className="h-4 w-4" /> : <CheckCircle2 className="h-4 w-4" />}
                            {card.completed_at ? 'Reabrir' : 'Marcar completada'}
                        </Button>
                        <Button variant="secondary" className="w-full" onClick={() => setDuplicate(true)}>
                            <Copy className="h-4 w-4" />
                            Duplicar
                        </Button>
                    </>
                )}
                <Side icon={Columns3} label="Estado / lista">
                    <Select value={String(card.list_id)} onValueChange={(v) => move(Number(v))} disabled={readOnly}>
                        <SelectTrigger className="bg-card">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {project.lists.map((l) => (
                                <SelectItem key={l.id} value={String(l.id)}>
                                    {l.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Side>
                <Side icon={Flag} label="Prioridad">
                    <Select value={card.priority} onValueChange={(v) => patch({ priority: v as Priority })} disabled={readOnly}>
                        <SelectTrigger className="bg-card">
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
                </Side>
                <Side icon={CalendarDays} label="Fechas">
                    <div className="grid gap-1.5">
                        <label className="text-muted-foreground text-[11px]">Inicio</label>
                        <DateField className="bg-card" value={card.start_date} disabled={readOnly} onCommit={(v) => patch({ start_date: v })} />
                        <label className="text-muted-foreground text-[11px]">Límite</label>
                        <DateField
                            className={cn('bg-card', card.overdue && 'border-red-400 text-red-600')}
                            value={card.due_date}
                            disabled={readOnly}
                            onCommit={(v) => patch({ due_date: v })}
                        />
                    </div>
                </Side>
                <Side icon={Users} label="Asignados">
                    <MultiSelect
                        placeholder="Asignar persona…"
                        emptyText="No hay más miembros"
                        disabled={readOnly}
                        value={card.assignees.map((a) => a.id)}
                        onChange={(ids) => patch({ assignees: ids })}
                        options={project.members.map((m) => ({ id: m.id, label: m.name, hint: m.id === auth.user.id ? 'tú' : undefined, avatar: m }))}
                    />
                </Side>
                <Side icon={Tag} label="Etiquetas">
                    <MultiSelect
                        placeholder="Agregar etiqueta…"
                        emptyText={project.labels.length ? 'No hay más etiquetas' : 'Crea etiquetas en Configuración'}
                        disabled={readOnly}
                        value={card.labels}
                        onChange={(ids) => patch({ labels: ids })}
                        options={project.labels.map((l) => ({ id: l.id, label: l.name, color: l.color }))}
                    />
                </Side>
                {card.catalog && (
                    <Side icon={BookImage} label="Catálogo">
                        <Link href={route('catalogs.show', card.catalog.slug)} className="bg-card block rounded-md border p-2.5 hover:underline">
                            <span className="block truncate font-medium">{card.catalog.name}</span>
                            <span className="text-muted-foreground text-xs">
                                {card.catalog.status_label} · {card.catalog.approved}/{card.catalog.pages} a imprenta
                            </span>
                        </Link>
                    </Side>
                )}
                <p className="text-muted-foreground text-[11px]">
                    Creada {card.created_at ? fmtDateTime(card.created_at) : '—'}
                    <br />
                    Actualizada {card.updated_at ? fmtAgo(card.updated_at) : '—'}
                </p>
                {canDelete && (
                    <Button variant="ghost" size="sm" className="w-full text-red-600 hover:text-red-700" onClick={() => setConfirmDelete(true)}>
                        <Trash2 className="h-4 w-4" />
                        Eliminar tarjeta
                    </Button>
                )}
            </aside>

            {duplicate && <DuplicateCardDialog open card={card} project={project} projects={projects} onClose={() => setDuplicate(false)} />}
            <ConfirmDialog
                open={confirmDelete}
                onClose={() => setConfirmDelete(false)}
                onConfirm={destroy}
                loading={deleting}
                title="Eliminar tarjeta"
                message="Esta acción no se puede deshacer. Se eliminarán también los comentarios y adjuntos."
            />
        </div>
    );
}

const Section = ({ title, icon: Icon, children, actions }: { title: string; icon?: typeof Paperclip; children: ReactNode; actions?: ReactNode }) => (
    <section>
        <div className="mb-2 flex items-center justify-between">
            <h4 className="text-muted-foreground flex items-center gap-1.5 text-xs font-semibold tracking-wide uppercase">
                {Icon && <Icon className="h-3.5 w-3.5" />}
                {title}
            </h4>
            {actions}
        </div>
        {children}
    </section>
);
const Side = ({ icon: Icon, label, children }: { icon: typeof Paperclip; label: string; children: ReactNode }) => (
    <div>
        <h4 className="text-muted-foreground mb-1.5 flex items-center gap-1.5 text-xs font-semibold tracking-wide uppercase">
            <Icon className="h-3.5 w-3.5" />
            {label}
        </h4>
        {children}
    </div>
);

function Attachments({ card, project, readOnly }: { card: CardDetail; project: ProjectBoard; readOnly: boolean }) {
    const fileRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [linkOpen, setLinkOpen] = useState(false);
    const [link, setLink] = useState({ url: '', name: '' });
    const [linkBusy, setLinkBusy] = useState(false);
    const [removing, setRemoving] = useState<AttachmentDto | null>(null);

    const onFiles = (files: FileList | File[] | null) => {
        const list = files ? Array.from(files) : [];
        if (!list.length || readOnly) return;
        setUploading(true);
        router.post(
            route('cards.attachments.store', card.id),
            { files: list },
            {
                ...opts,
                forceFormData: true,
                onSuccess: () => toast.success(list.length > 1 ? 'Archivos subidos' : 'Archivo subido'),
                onError: (e) => toast.error(firstError(e)),
                onFinish: () => {
                    setUploading(false);
                    if (fileRef.current) fileRef.current.value = '';
                },
            },
        );
    };
    const addLink = () => {
        if (!link.url.trim()) return;
        setLinkBusy(true);
        router.post(
            route('cards.attachments.store', card.id),
            { url: link.url, name: link.name || null },
            {
                ...opts,
                onSuccess: () => {
                    setLink({ url: '', name: '' });
                    setLinkOpen(false);
                },
                onError: (e) => toast.error(firstError(e)),
                onFinish: () => setLinkBusy(false),
            },
        );
    };
    const remove = (a: AttachmentDto) => router.delete(route('attachments.destroy', a.id), { ...opts, onFinish: () => setRemoving(null) });
    const canDelete = (a: AttachmentDto) => !readOnly && (a.mine || project.can.manage);
    const images = card.attachments.filter((a) => a.image);
    const others = card.attachments.filter((a) => !a.image);

    return (
        <Section
            title={`Adjuntos (${card.attachments.length})`}
            icon={Paperclip}
            actions={
                !readOnly && (
                    <div className="flex gap-1.5">
                        <input ref={fileRef} type="file" multiple hidden onChange={(e) => onFiles(e.target.files)} />
                        <Button size="sm" variant="secondary" disabled={uploading} onClick={() => fileRef.current?.click()}>
                            {uploading ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Upload className="h-3.5 w-3.5" />}
                            Subir archivo
                        </Button>
                        <Button size="sm" variant="secondary" onClick={() => setLinkOpen((v) => !v)}>
                            <Link2 className="h-3.5 w-3.5" />
                            Agregar enlace
                        </Button>
                    </div>
                )
            }
        >
            {linkOpen && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        addLink();
                    }}
                    className="bg-muted/50 mb-3 flex flex-wrap gap-2 rounded-lg border p-2.5"
                >
                    <Input
                        className="min-w-56 flex-1"
                        type="url"
                        required
                        placeholder="https://…"
                        value={link.url}
                        onChange={(e) => setLink({ ...link, url: e.target.value })}
                        autoFocus
                    />
                    <Input
                        className="w-48"
                        placeholder="Nombre (opcional)"
                        maxLength={120}
                        value={link.name}
                        onChange={(e) => setLink({ ...link, name: e.target.value })}
                    />
                    <Button type="submit" disabled={linkBusy}>
                        {linkBusy && <Loader2 className="h-4 w-4 animate-spin" />}
                        Agregar
                    </Button>
                </form>
            )}
            <div
                onDragOver={(e) => e.preventDefault()}
                onDrop={(e) => {
                    e.preventDefault();
                    onFiles(e.dataTransfer.files);
                }}
            >
                {card.attachments.length === 0 && !linkOpen && (
                    <p className="text-muted-foreground rounded-lg border border-dashed py-4 text-center text-xs">
                        Arrastra archivos aquí o usa los botones. Imágenes, PDF, documentos y enlaces.
                    </p>
                )}
                {images.length > 0 && (
                    <div className="mb-3 grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-5">
                        {images.map((a) => (
                            <div key={a.id} className="group bg-muted relative overflow-hidden rounded-lg border">
                                <a href={a.url} target="_blank" rel="noopener noreferrer">
                                    <img
                                        src={a.url}
                                        alt={a.name}
                                        className="aspect-square w-full object-cover transition group-hover:opacity-90"
                                        loading="lazy"
                                    />
                                </a>
                                <div className="text-muted-foreground truncate px-1.5 py-1 text-[11px]" title={a.name}>
                                    {a.name}
                                </div>
                                {canDelete(a) && (
                                    <button
                                        type="button"
                                        onClick={() => setRemoving(a)}
                                        className="bg-card/90 text-muted-foreground absolute top-1 right-1 hidden rounded-md p-1 shadow group-hover:block hover:text-red-600"
                                        title="Eliminar"
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>
                )}
                {others.length > 0 && (
                    <ul className="divide-y rounded-lg border">
                        {others.map((a) => (
                            <li key={a.id} className="flex items-center gap-3 px-3 py-2 text-sm">
                                <span className="bg-muted text-muted-foreground flex h-8 w-8 shrink-0 items-center justify-center rounded-md">
                                    {a.kind === 'link' ? (
                                        <Link2 className="h-4 w-4" />
                                    ) : a.mime?.startsWith('image/') ? (
                                        <ImageIcon className="h-4 w-4" />
                                    ) : (
                                        <FileText className="h-4 w-4" />
                                    )}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <a
                                        href={a.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex items-center gap-1 truncate font-medium hover:underline"
                                    >
                                        {a.name}
                                        <ExternalLink className="text-muted-foreground h-3 w-3 shrink-0" />
                                    </a>
                                    <p className="text-muted-foreground truncate text-xs">
                                        {a.kind === 'link' ? a.url : fmtSize(a.size)} · {a.author ?? '—'} · {fmtAgo(a.at)}
                                    </p>
                                </div>
                                {canDelete(a) && (
                                    <button
                                        type="button"
                                        onClick={() => setRemoving(a)}
                                        className="text-muted-foreground hover:text-red-600"
                                        title="Eliminar"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            <ConfirmDialog
                open={!!removing}
                onClose={() => setRemoving(null)}
                onConfirm={() => removing && remove(removing)}
                title="Quitar adjunto"
                message={`Se quitará "${removing?.name ?? ''}" de la tarjeta.`}
                confirmLabel="Quitar"
            />
        </Section>
    );
}

export function Comments({
    comments,
    storeUrl,
    project,
    readOnly,
}: {
    comments: CommentDto[];
    storeUrl: string;
    project: ProjectBoard;
    readOnly: boolean;
}) {
    const { auth } = usePage<SharedData>().props;
    const [body, setBody] = useState('');
    const [pending, setPending] = useState<File[]>([]);
    const [busy, setBusy] = useState(false);
    const [removing, setRemoving] = useState<CommentDto | null>(null);
    /* Answering someone: the reply hangs under their comment (one level). */
    const [replyTo, setReplyTo] = useState<CommentDto | null>(null);
    /* Fixing one's own words. */
    const [editing, setEditing] = useState<CommentDto | null>(null);
    const [editBody, setEditBody] = useState('');
    const fileRef = useRef<HTMLInputElement>(null);
    const end = useRef<HTMLDivElement>(null);
    const composer = useRef<HTMLDivElement>(null);
    const me = {
        id: auth.user.id,
        name: auth.user.name,
        initials: auth.user.name
            .split(' ')
            .slice(0, 2)
            .map((w) => w[0])
            .join(''),
    };

    const send = () => {
        if ((!body.trim() && !pending.length) || busy) return;
        setBusy(true);
        router.post(
            storeUrl,
            { body, files: pending, parent_id: replyTo?.id ?? null },
            {
                ...opts,
                forceFormData: pending.length > 0,
                onSuccess: () => {
                    setBody('');
                    setPending([]);
                    setReplyTo(null);
                    setTimeout(() => end.current?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 300);
                },
                onError: (e) => toast.error(firstError(e)),
                onFinish: () => setBusy(false),
            },
        );
    };
    const onPaste = (e: ClipboardEvent) => {
        const files = Array.from(e.clipboardData.files);
        if (files.length) {
            e.preventDefault();
            setPending((p) => [...p, ...files]);
        }
    };
    const remove = (c: CommentDto) => router.delete(route('comments.destroy', c.id), { ...opts, onFinish: () => setRemoving(null) });
    const startReply = (c: CommentDto) => {
        setReplyTo(c);
        setEditing(null);
        setTimeout(() => composer.current?.querySelector('textarea')?.focus(), 50);
    };
    const startEdit = (c: CommentDto) => {
        setEditing(c);
        setEditBody(c.body);
        setReplyTo(null);
    };
    const saveEdit = () => {
        if (!editing || !editBody.trim() || busy) return;
        setBusy(true);
        router.put(
            route('comments.update', editing.id),
            { body: editBody },
            { ...opts, onSuccess: () => setEditing(null), onError: (e) => toast.error(firstError(e)), onFinish: () => setBusy(false) },
        );
    };

    const renderComment = (c: CommentDto, reply = false) => (
        <div className={cn('flex gap-2.5', reply && 'mt-2 ml-8 border-l-2 pl-3')}>
            {c.author ? <PersonAvatar person={c.author} /> : <span className="bg-muted h-6 w-6 rounded-full" />}
            <div
                className={cn(
                    'bg-muted/50 min-w-0 flex-1 rounded-lg px-3 py-2',
                    c.client && 'border border-sky-200 bg-sky-50/50 dark:border-sky-900 dark:bg-sky-950/20',
                )}
            >
                <div className="flex items-center gap-2 text-xs">
                    <span className="font-medium">{c.author?.name ?? 'Usuario retirado'}</span>
                    {c.client && (
                        <Link
                            href={c.client.url}
                            title="Comentario del cliente en la revisión; abrir la página"
                            className="rounded bg-sky-100 px-1 text-[10px] font-semibold text-sky-800 hover:underline dark:bg-sky-900/60 dark:text-sky-200"
                        >
                            cliente · {c.client.role_label ?? ''} · {c.client.page_label}
                            {!c.client.open && ' · resuelto'}
                        </Link>
                    )}
                    <span className="text-muted-foreground" title={fmtDateTime(c.at)}>
                        {fmtAgo(c.at)}
                        {c.edited && ' · editado'}
                    </span>
                    {!readOnly && (
                        <span className="ml-auto flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => startReply(c)}
                                className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1"
                                title="Responder a este comentario"
                            >
                                <Reply className="h-3.5 w-3.5" />
                                Responder
                            </button>
                            {c.can_edit && (
                                <button
                                    type="button"
                                    onClick={() => startEdit(c)}
                                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1"
                                    title="Editar mi comentario"
                                >
                                    <Pencil className="h-3.5 w-3.5" />
                                    Editar
                                </button>
                            )}
                            {(c.mine || project.can.manage) && (
                                <button
                                    type="button"
                                    onClick={() => setRemoving(c)}
                                    className="text-muted-foreground hover:text-red-600"
                                    aria-label="Borrar comentario"
                                >
                                    <Trash2 className="h-3.5 w-3.5" />
                                </button>
                            )}
                        </span>
                    )}
                </div>
                {editing?.id === c.id ? (
                    <div className="mt-2 space-y-2">
                        <MentionTextarea value={editBody} onChange={setEditBody} people={project.members} onSubmit={saveEdit} rows={2} above />
                        <div className="flex justify-end gap-2">
                            <Button type="button" size="sm" variant="ghost" onClick={() => setEditing(null)}>
                                Cancelar
                            </Button>
                            <Button type="button" size="sm" onClick={saveEdit} disabled={busy || !editBody.trim()}>
                                Guardar
                            </Button>
                        </div>
                    </div>
                ) : (
                    <p className="mt-1 text-sm break-words whitespace-pre-wrap">
                        <MentionText body={c.body} />
                    </p>
                )}
                {c.attachments.length > 0 && (
                    <div className="mt-2 flex flex-wrap gap-2">
                        {c.attachments.map((a) =>
                            a.image ? (
                                <a key={a.id} href={a.url} target="_blank" rel="noopener noreferrer" title={a.name}>
                                    <img
                                        src={a.url}
                                        alt={a.name}
                                        className="h-28 max-w-56 rounded-md border object-cover hover:opacity-90"
                                        loading="lazy"
                                    />
                                </a>
                            ) : (
                                <a
                                    key={a.id}
                                    href={a.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="bg-card hover:border-primary/50 flex items-center gap-2 rounded-md border px-2.5 py-1.5 text-xs"
                                >
                                    <FileText className="text-muted-foreground h-4 w-4" />
                                    <span className="max-w-48 truncate font-medium">{a.name}</span>
                                    <span className="text-muted-foreground">{fmtSize(a.size)}</span>
                                </a>
                            ),
                        )}
                    </div>
                )}
            </div>
        </div>
    );

    return (
        <Section title={`Comentarios (${comments.length})`} icon={MessageSquare}>
            {comments.length === 0 && <p className="text-muted-foreground text-sm">Aún no hay comentarios.</p>}
            <div className="space-y-3">
                {comments
                    .filter((c) => !c.parent_id)
                    .map((c) => (
                        <div key={c.id}>
                            {renderComment(c)}
                            {comments
                                .filter((r) => r.parent_id === c.id)
                                .map((r) => (
                                    <div key={r.id}>{renderComment(r, true)}</div>
                                ))}
                        </div>
                    ))}
                <div ref={end} />
            </div>
            {!readOnly && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        send();
                    }}
                    onPaste={onPaste}
                    className="mt-4 border-t pt-3"
                >
                    {pending.length > 0 && (
                        <div className="mb-2 flex flex-wrap gap-2">
                            {pending.map((f, i) => (
                                <span key={i} className="relative">
                                    {f.type.startsWith('image/') ? (
                                        <img src={URL.createObjectURL(f)} alt={f.name} className="h-16 w-16 rounded-md border object-cover" />
                                    ) : (
                                        <span className="bg-muted text-muted-foreground flex h-16 max-w-40 items-center gap-1.5 rounded-md border px-2 text-xs">
                                            <FileText className="h-4 w-4 shrink-0" />
                                            <span className="truncate">{f.name}</span>
                                        </span>
                                    )}
                                    <button
                                        type="button"
                                        onClick={() => setPending((p) => p.filter((_, j) => j !== i))}
                                        className="bg-card text-muted-foreground ring-border absolute -top-1.5 -right-1.5 rounded-full p-0.5 shadow ring-1 hover:text-red-600"
                                        aria-label="Quitar archivo"
                                    >
                                        <X className="h-3 w-3" />
                                    </button>
                                </span>
                            ))}
                        </div>
                    )}
                    {replyTo && (
                        <div className="bg-muted/60 mb-2 ml-10 flex items-center gap-2 rounded-md px-2.5 py-1.5 text-xs">
                            <Reply className="text-muted-foreground h-3.5 w-3.5 shrink-0" />
                            <span className="min-w-0 truncate">
                                Respondiendo a <span className="font-medium">{replyTo.author?.name ?? 'usuario retirado'}</span>: {replyTo.body}
                            </span>
                            <button
                                type="button"
                                onClick={() => setReplyTo(null)}
                                className="text-muted-foreground hover:text-foreground ml-auto"
                                aria-label="Cancelar respuesta"
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        </div>
                    )}
                    <div ref={composer} className="flex items-start gap-2">
                        <PersonAvatar person={me} size="md" className="mt-0.5" />
                        <MentionTextarea
                            value={body}
                            onChange={setBody}
                            people={project.members}
                            onSubmit={send}
                            rows={1}
                            above
                            placeholder={replyTo ? `Responder a ${replyTo.author?.name ?? ''}…` : 'Escribe un comentario…'}
                        />
                        <input
                            ref={fileRef}
                            type="file"
                            multiple
                            hidden
                            onChange={(e) => {
                                const files = Array.from(e.target.files ?? []);
                                e.target.value = '';
                                if (files.length) setPending((p) => [...p, ...files]);
                            }}
                        />
                        <Button
                            type="button"
                            variant="secondary"
                            size="icon"
                            className="h-9 w-9"
                            title="Adjuntar imagen o documento (también puedes pegar una imagen)"
                            onClick={() => fileRef.current?.click()}
                        >
                            <FileUp className="h-4 w-4" />
                        </Button>
                        <Button type="submit" disabled={busy || (!body.trim() && !pending.length)}>
                            {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                            Enviar
                        </Button>
                    </div>
                    <p className="text-muted-foreground mt-1.5 ml-10 text-xs">
                        Usa @ para mencionar a alguien · Enter envía, Shift+Enter salta de línea.
                    </p>
                </form>
            )}
            <ConfirmDialog
                open={!!removing}
                onClose={() => setRemoving(null)}
                onConfirm={() => removing && remove(removing)}
                title="Borrar comentario"
                message="El comentario y sus archivos desaparecen para todos."
                confirmLabel="Borrar"
            />
        </Section>
    );
}

/** The card's subtasks as a table: status, name, people, priority and date; drag to reorder. */
function Subtasks({
    card,
    project,
    statuses,
    readOnly,
    onOpen,
}: {
    card: CardDetail;
    project: ProjectBoard;
    statuses: StatusOption[];
    readOnly: boolean;
    onOpen: (id: number) => void;
}) {
    const [adding, setAdding] = useState(false);
    const [title, setTitle] = useState('');
    const [busy, setBusy] = useState(false);
    const [order, setOrder] = useState(card.subtasks.map((s) => s.id));
    useEffect(() => setOrder(card.subtasks.map((s) => s.id)), [card.subtasks]);
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 4 } }));
    const rows = order.map((id) => card.subtasks.find((s) => s.id === id)).filter(Boolean) as SubtaskDto[];
    const open = rows.filter((s) => !s.closed).length;
    const mine = rows.filter((s) => !s.closed && s.mine).length;
    void project;

    const add = () => {
        if (!title.trim()) return setAdding(false);
        setBusy(true);
        router.post(
            route('cards.subtasks.store', card.id),
            { title: title.trim() },
            { ...opts, onSuccess: () => setTitle(''), onError: (e) => toast.error(firstError(e)), onFinish: () => setBusy(false) },
        );
    };
    const setStatus = (s: SubtaskDto, status: SubtaskStatus) =>
        router.put(route('subtasks.update', s.id), { status }, { ...opts, onError: (e) => toast.error(firstError(e)) });
    const onDragEnd = ({ active, over }: DragEndEvent) => {
        if (!over || active.id === over.id) return;
        const next = arrayMove(order, order.indexOf(Number(active.id)), order.indexOf(Number(over.id)));
        setOrder(next);
        const idx = next.indexOf(Number(active.id));
        const pos = (id: number | undefined) => card.subtasks.find((s) => s.id === id)?.position;
        const prev = pos(next[idx - 1]);
        const after = pos(next[idx + 1]);
        const position =
            prev === undefined && after === undefined
                ? 1024
                : prev === undefined
                  ? (after as number) / 2
                  : after === undefined
                    ? prev + 1024
                    : (prev + after) / 2;
        router.put(route('subtasks.move', Number(active.id)), { position }, opts);
    };

    return (
        <Section
            title={`Subtareas · ${open} abierta${open === 1 ? '' : 's'}`}
            icon={CheckSquare}
            actions={
                <div className="flex items-center gap-2">
                    {mine > 0 && <span className="bg-muted rounded-full px-2 py-0.5 text-[11px] font-medium">{mine} para mí</span>}
                </div>
            }
        >
            {rows.length > 0 && (
                <div className="bg-muted mb-2 h-1.5 overflow-hidden rounded-full">
                    <div
                        className="h-full rounded-full bg-emerald-500 transition-all"
                        style={{ width: `${Math.round(((rows.length - open) / rows.length) * 100)}%` }}
                    />
                </div>
            )}
            <div className="overflow-hidden rounded-lg border">
                <div className="text-muted-foreground bg-muted/50 grid grid-cols-[1fr_120px_96px_96px] items-center gap-2 border-b px-2 py-1.5 text-[11px] font-medium">
                    <span className="pl-7">Nombre</span>
                    <span>Responsables</span>
                    <span>Prioridad</span>
                    <span>Fecha límite</span>
                </div>
                <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
                    <SortableContext items={order} strategy={verticalListSortingStrategy}>
                        <ul className="divide-y">
                            {rows.map((s) => (
                                <SubtaskRow
                                    key={s.id}
                                    subtask={s}
                                    statuses={statuses}
                                    readOnly={readOnly}
                                    onOpen={() => onOpen(s.id)}
                                    onStatus={(st) => setStatus(s, st)}
                                />
                            ))}
                        </ul>
                    </SortableContext>
                </DndContext>
                {rows.length === 0 && !adding && (
                    <p className="text-muted-foreground px-3 py-3 text-xs">Sin subtareas. Divide la tarjeta en pasos con responsables y fechas.</p>
                )}
                {!readOnly &&
                    (adding ? (
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                add();
                            }}
                            className="flex items-center gap-2 px-2 py-1.5"
                        >
                            <Input
                                autoFocus
                                value={title}
                                onChange={(e) => setTitle(e.target.value)}
                                onKeyDown={(e) => e.key === 'Escape' && setAdding(false)}
                                placeholder="Nombre de la subtarea"
                                maxLength={200}
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
                            className="text-muted-foreground hover:bg-muted hover:text-foreground flex w-full items-center gap-1.5 px-3 py-1.5 text-left text-xs"
                        >
                            <Plus className="h-3.5 w-3.5" />
                            Agregar subtarea
                        </button>
                    ))}
            </div>
        </Section>
    );
}

function SubtaskRow({
    subtask: s,
    statuses,
    readOnly,
    onOpen,
    onStatus,
}: {
    subtask: SubtaskDto;
    statuses: StatusOption[];
    readOnly: boolean;
    onOpen: () => void;
    onStatus: (st: SubtaskStatus) => void;
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: s.id, disabled: readOnly });
    return (
        <li
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={cn(
                'bg-card grid grid-cols-[1fr_120px_96px_96px] items-center gap-2 px-2 py-1.5 text-sm',
                isDragging && 'z-10 opacity-70 shadow-lg',
                s.closed && 'opacity-60',
            )}
        >
            <div className="flex min-w-0 items-center gap-1">
                <button
                    type="button"
                    {...attributes}
                    {...listeners}
                    className={cn(
                        'text-muted-foreground/50 hover:text-muted-foreground -ml-1 touch-none rounded p-0.5',
                        readOnly ? 'invisible' : 'cursor-grab active:cursor-grabbing',
                    )}
                    aria-label="Arrastrar"
                >
                    <GripVertical className="h-3.5 w-3.5" />
                </button>
                <StatusPicker value={s.status} options={statuses} onChange={onStatus} disabled={readOnly || s.automatic}>
                    <span
                        title={s.automatic ? `${s.status_label} · sigue la revisión del catálogo` : s.status_label}
                        className="hover:bg-muted flex h-6 w-6 items-center justify-center rounded"
                    >
                        <StatusIcon status={s.status} />
                    </span>
                </StatusPicker>
                <button
                    type="button"
                    onClick={onOpen}
                    className={cn('min-w-0 flex-1 truncate text-left font-medium hover:underline', s.closed && 'line-through')}
                >
                    {s.title}
                </button>
                {s.intake_pending > 0 && (
                    <span
                        className="shrink-0 rounded-full bg-amber-100 px-1.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-900/60 dark:text-amber-200"
                        title="Comentarios del cliente por revisar"
                    >
                        {s.intake_pending} por revisar
                    </span>
                )}
            </div>
            <div className="flex items-center">
                {s.assignees.length ? <AvatarStackInline people={s.assignees} /> : <span className="text-muted-foreground text-xs">—</span>}
            </div>
            <span className={cn('w-fit rounded px-1.5 py-0.5 text-[11px] font-medium', PRIORITY_CLASS[s.priority])}>{s.priority_label}</span>
            <span className={cn('text-xs tabular-nums', s.overdue ? 'font-medium text-red-600' : 'text-muted-foreground')}>
                {s.due_date ? fmtDate(s.due_date) : '—'}
            </span>
        </li>
    );
}

function AvatarStackInline({ people }: { people: SubtaskDto['assignees'] }) {
    const shown = people.slice(0, 3);
    return (
        <span className="flex items-center -space-x-1.5">
            {shown.map((p) => (
                <PersonAvatar key={p.id} person={p} className="ring-background ring-2" />
            ))}
            {people.length > 3 && (
                <span className="bg-muted text-muted-foreground ring-background inline-flex h-6 w-6 items-center justify-center rounded-full text-[10px] ring-2">
                    +{people.length - 3}
                </span>
            )}
        </span>
    );
}
