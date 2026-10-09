import ColorPicker from '@/components/projects/color-picker';
import { PersonAvatar } from '@/components/projects/person-avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { firstError } from '@/hooks/use-flash-toast';
import { cn } from '@/lib/utils';
import { type TypeOption } from '@/types';
import { type ClientOption, type FieldDto, type FieldType, type LabelDto, type Person, type ProjectBoard, type ProjectRole } from '@/types/projects';
import { closestCenter, DndContext, type DragEndEvent, PointerSensor, useSensor, useSensors } from '@dnd-kit/core';
import { arrayMove, SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { router, useForm } from '@inertiajs/react';
import { Archive, ArchiveRestore, Check, GripVertical, Loader2, Pencil, Plus, Trash2, X } from 'lucide-react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

/** General data, members, labels and custom fields of a board. Only managers get here. */
export default function ProjectSettingsDialog({
    open,
    onClose,
    project,
    clients,
    people,
    fieldTypes,
}: {
    open: boolean;
    onClose: () => void;
    project: ProjectBoard;
    clients: ClientOption[];
    people: Person[];
    fieldTypes: TypeOption[];
}) {
    return (
        <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Configuración del proyecto</DialogTitle>
                    <DialogDescription>{project.name}</DialogDescription>
                </DialogHeader>
                <Tabs defaultValue="general">
                    <TabsList className="grid w-full grid-cols-4">
                        <TabsTrigger value="general">General</TabsTrigger>
                        <TabsTrigger value="members">Miembros ({project.members.length})</TabsTrigger>
                        <TabsTrigger value="labels">Etiquetas ({project.labels.length})</TabsTrigger>
                        <TabsTrigger value="fields">Campos ({project.fields.length})</TabsTrigger>
                    </TabsList>
                    <TabsContent value="general" className="pt-4">
                        <GeneralTab project={project} clients={clients} onClose={onClose} />
                    </TabsContent>
                    <TabsContent value="members" className="pt-4">
                        <MembersTab project={project} people={people} />
                    </TabsContent>
                    <TabsContent value="labels" className="pt-4">
                        <LabelsTab project={project} />
                    </TabsContent>
                    <TabsContent value="fields" className="pt-4">
                        <FieldsTab project={project} types={fieldTypes} />
                    </TabsContent>
                </Tabs>
            </DialogContent>
        </Dialog>
    );
}

type General = { name: string; description: string; color: string; client_id: string };

function GeneralTab({ project, clients, onClose }: { project: ProjectBoard; clients: ClientOption[]; onClose: () => void }) {
    const form = useForm<General>({
        name: project.name,
        description: project.description ?? '',
        color: project.color,
        client_id: project.client ? String(project.client.id) : '',
    });
    const [confirm, setConfirm] = useState<'archive' | 'delete' | null>(null);
    const [busy, setBusy] = useState(false);
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, client_id: d.client_id === '' ? null : Number(d.client_id) }));
        form.put(route('projects.update', project.id), { preserveScroll: true, onError: (er) => toast.error(firstError(er)) });
    };
    const archive = () => {
        setBusy(true);
        router.post(route('projects.archive', project.id), {}, { preserveScroll: true, onSuccess: onClose, onFinish: () => setBusy(false) });
    };
    const unarchive = () => router.post(route('projects.unarchive', project.id), {}, { preserveScroll: true, onSuccess: onClose });
    const destroy = () => {
        setBusy(true);
        router.delete(route('projects.destroy', project.id), { onFinish: () => setBusy(false) });
    };

    return (
        <form onSubmit={submit} className="grid gap-4">
            <div className="grid gap-2">
                <Label htmlFor="g-name">Nombre</Label>
                <Input id="g-name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                {form.errors.name && <p className="text-sm text-red-600">{form.errors.name}</p>}
            </div>
            <div className="grid gap-2">
                <Label>Cliente</Label>
                <Select value={form.data.client_id || 'none'} onValueChange={(v) => form.setData('client_id', v === 'none' ? '' : v)}>
                    <SelectTrigger>
                        <SelectValue placeholder="Sin cliente" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">Sin cliente</SelectItem>
                        {clients.map((c) => (
                            <SelectItem key={c.id} value={String(c.id)}>
                                {c.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="g-desc">Descripción</Label>
                <Textarea id="g-desc" rows={3} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
            </div>
            <div className="grid gap-2">
                <Label>Color</Label>
                <ColorPicker value={form.data.color} onChange={(c) => form.setData('color', c)} />
            </div>
            <div className="flex flex-wrap items-center gap-2 border-t pt-4">
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Loader2 className="h-4 w-4 animate-spin" />}
                    Guardar
                </Button>
                {project.archived_at ? (
                    <Button type="button" variant="outline" onClick={unarchive}>
                        <ArchiveRestore className="h-4 w-4" />
                        Reactivar
                    </Button>
                ) : (
                    <Button type="button" variant="outline" onClick={() => setConfirm('archive')}>
                        <Archive className="h-4 w-4" />
                        Archivar
                    </Button>
                )}
                {project.can.delete && (
                    <Button type="button" variant="ghost" className="ml-auto text-red-600 hover:text-red-700" onClick={() => setConfirm('delete')}>
                        <Trash2 className="h-4 w-4" />
                        Eliminar proyecto
                    </Button>
                )}
            </div>
            <ConfirmDialog
                open={confirm === 'archive'}
                onClose={() => setConfirm(null)}
                onConfirm={archive}
                loading={busy}
                title="Archivar proyecto"
                message="Deja de aparecer en la lista y nadie podrá moverle tarjetas. Puedes reactivarlo después."
                confirmLabel="Archivar"
            />
            <ConfirmDialog
                open={confirm === 'delete'}
                onClose={() => setConfirm(null)}
                onConfirm={destroy}
                loading={busy}
                title="Eliminar proyecto"
                message={`Se eliminará "${project.name}" con todas sus listas, tarjetas y actividad. Esta acción es irreversible.`}
            />
        </form>
    );
}

function MembersTab({ project, people }: { project: ProjectBoard; people: Person[] }) {
    const candidates = people.filter((p) => !project.members.some((m) => m.id === p.id));
    const [q, setQ] = useState('');
    const [picked, setPicked] = useState<Person | null>(null);
    const [role, setRole] = useState<ProjectRole>('member');
    const [focus, setFocus] = useState(false);
    const [busy, setBusy] = useState(false);
    const [removing, setRemoving] = useState<Person | null>(null);
    const boxRef = useRef<HTMLDivElement>(null);
    useEffect(() => {
        const h = (e: MouseEvent) => !boxRef.current?.contains(e.target as Node) && setFocus(false);
        document.addEventListener('mousedown', h);
        return () => document.removeEventListener('mousedown', h);
    }, []);
    const suggestions =
        focus && !picked && q.trim() ? candidates.filter((c) => c.name.toLowerCase().includes(q.trim().toLowerCase())).slice(0, 6) : [];

    const add: FormEventHandler = (e) => {
        e.preventDefault();
        if (!picked) return;
        setBusy(true);
        router.post(
            route('projects.members.store', project.id),
            { user_id: picked.id, role },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setPicked(null);
                    setQ('');
                },
                onError: (er) => toast.error(firstError(er)),
                onFinish: () => setBusy(false),
            },
        );
    };
    const setMemberRole = (m: Person, r: ProjectRole) =>
        router.put(route('projects.members.update', [project.id, m.id]), { role: r }, { preserveScroll: true });
    const remove = () =>
        removing &&
        router.delete(route('projects.members.destroy', [project.id, removing.id]), { preserveScroll: true, onFinish: () => setRemoving(null) });

    return (
        <div className="grid gap-4">
            <form onSubmit={add} className="bg-muted/50 rounded-lg border p-3">
                <p className="text-muted-foreground mb-2 text-xs font-medium">Escribe el nombre de alguien de Ogilvy y elígelo de la lista.</p>
                <div className="flex flex-wrap gap-2">
                    <div ref={boxRef} className="relative min-w-48 flex-1">
                        <Input
                            placeholder={candidates.length ? 'Nombre…' : 'Todos ya son miembros'}
                            autoComplete="off"
                            value={q}
                            onChange={(e) => {
                                setQ(e.target.value);
                                setPicked(null);
                                setFocus(true);
                            }}
                            onFocus={() => setFocus(true)}
                        />
                        {suggestions.length > 0 && (
                            <ul className="bg-popover text-popover-foreground absolute top-full left-0 z-40 mt-1 w-full overflow-hidden rounded-lg border py-1 shadow-lg">
                                {suggestions.map((c) => (
                                    <li key={c.id}>
                                        <button
                                            type="button"
                                            onMouseDown={(e) => {
                                                e.preventDefault();
                                                setPicked(c);
                                                setQ(c.name);
                                                setFocus(false);
                                            }}
                                            className="hover:bg-accent flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm"
                                        >
                                            <PersonAvatar person={c} />
                                            {c.name}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                    <Select value={role} onValueChange={(v) => setRole(v as ProjectRole)}>
                        <SelectTrigger className="w-32">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="member">Miembro</SelectItem>
                            <SelectItem value="manager">Moderador</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="submit" disabled={!picked || busy}>
                        {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
                        Agregar
                    </Button>
                </div>
            </form>
            <ul className="divide-y rounded-lg border">
                {project.members.map((m) => (
                    <li key={m.id} className="flex items-center gap-3 px-3 py-2 text-sm">
                        <PersonAvatar person={m} size="md" />
                        <span className="min-w-0 flex-1 truncate">
                            {m.name}
                            {project.owner?.id === m.id && <span className="text-muted-foreground"> · dueño</span>}
                        </span>
                        <Select
                            value={m.role ?? 'member'}
                            onValueChange={(v) => setMemberRole(m, v as ProjectRole)}
                            disabled={project.owner?.id === m.id}
                        >
                            <SelectTrigger className="h-8 w-32">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="manager">Moderador</SelectItem>
                                <SelectItem value="member">Miembro</SelectItem>
                            </SelectContent>
                        </Select>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-red-600"
                            disabled={project.owner?.id === m.id}
                            onClick={() => setRemoving(m)}
                            aria-label="Retirar"
                        >
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </li>
                ))}
            </ul>
            <p className="text-muted-foreground text-xs">
                Los moderadores editan el proyecto, sus listas, miembros, etiquetas y campos. Los miembros trabajan las tarjetas.
            </p>
            <ConfirmDialog
                open={!!removing}
                onClose={() => setRemoving(null)}
                onConfirm={remove}
                title="Retirar del proyecto"
                message={`${removing?.name ?? ''} deja de ver el tablero y se le quitan las tarjetas asignadas.`}
                confirmLabel="Retirar"
            />
        </div>
    );
}

function LabelsTab({ project }: { project: ProjectBoard }) {
    const [editing, setEditing] = useState<LabelDto | null>(null);
    const [name, setName] = useState('');
    const [color, setColor] = useState('#6B6B76');
    const [busy, setBusy] = useState(false);
    const [removing, setRemoving] = useState<LabelDto | null>(null);
    const start = (l: LabelDto | null) => {
        setEditing(l);
        setName(l?.name ?? '');
        setColor(l?.color ?? '#6B6B76');
    };
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        setBusy(true);
        const opts = {
            preserveScroll: true,
            onSuccess: () => start(null),
            onError: (er: Record<string, string>) => toast.error(firstError(er)),
            onFinish: () => setBusy(false),
        };
        if (editing) router.put(route('labels.update', editing.id), { name, color }, opts);
        else router.post(route('projects.labels.store', project.id), { name, color }, opts);
    };
    const remove = () => removing && router.delete(route('labels.destroy', removing.id), { preserveScroll: true, onFinish: () => setRemoving(null) });

    return (
        <div className="grid gap-4">
            <form onSubmit={submit} className="bg-muted/50 grid gap-3 rounded-lg border p-3">
                <div className="flex gap-2">
                    <Input
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        placeholder={editing ? `Editar "${editing.name}"` : 'Nombre de la etiqueta'}
                        maxLength={40}
                        required
                    />
                    <Button type="submit" disabled={busy || name.trim() === ''}>
                        {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
                        {editing ? 'Guardar' : 'Crear'}
                    </Button>
                    {editing && (
                        <Button type="button" variant="ghost" onClick={() => start(null)}>
                            Cancelar
                        </Button>
                    )}
                </div>
                <ColorPicker value={color} onChange={setColor} />
            </form>
            {project.labels.length === 0 ? (
                <p className="text-muted-foreground text-sm">No hay etiquetas.</p>
            ) : (
                <ul className="divide-y rounded-lg border">
                    {project.labels.map((l) => (
                        <li key={l.id} className="flex items-center gap-3 px-3 py-2">
                            <span className="h-4 w-8 rounded-full" style={{ backgroundColor: l.color }} />
                            <span className="flex-1 text-sm">{l.name}</span>
                            <Button type="button" variant="ghost" size="icon" className="h-8 w-8" onClick={() => start(l)} aria-label="Editar">
                                <Pencil className="h-4 w-4" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="h-8 w-8 text-red-600"
                                onClick={() => setRemoving(l)}
                                aria-label="Eliminar"
                            >
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
            <ConfirmDialog
                open={!!removing}
                onClose={() => setRemoving(null)}
                onConfirm={remove}
                title="Eliminar etiqueta"
                message={`"${removing?.name ?? ''}" se quita de todas las tarjetas.`}
            />
        </div>
    );
}

/** Custom fields every card of the project shows. Drag to reorder; edit inline. */
function FieldsTab({ project, types }: { project: ProjectBoard; types: TypeOption[] }) {
    const [form, setForm] = useState({ name: '', type: 'text' as FieldType, options: '', required: false });
    const [order, setOrder] = useState(project.fields.map((f) => f.id));
    const [busy, setBusy] = useState(false);
    const [removing, setRemoving] = useState<FieldDto | null>(null);
    useEffect(() => setOrder(project.fields.map((f) => f.id)), [project.fields]);
    const withOptions = form.type === 'select' || form.type === 'multiselect';
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 4 } }));

    const create: FormEventHandler = (e) => {
        e.preventDefault();
        setBusy(true);
        router.post(
            route('projects.fields.store', project.id),
            {
                name: form.name,
                type: form.type,
                required: form.required,
                options: withOptions
                    ? form.options
                          .split(',')
                          .map((o) => o.trim())
                          .filter(Boolean)
                    : [],
            },
            {
                preserveScroll: true,
                onSuccess: () => setForm({ name: '', type: 'text', options: '', required: false }),
                onError: (er) => toast.error(firstError(er)),
                onFinish: () => setBusy(false),
            },
        );
    };
    const onDragEnd = ({ active, over }: DragEndEvent) => {
        if (!over || active.id === over.id) return;
        const next = arrayMove(order, order.indexOf(Number(active.id)), order.indexOf(Number(over.id)));
        setOrder(next);
        router.put(route('projects.fields.reorder', project.id), { order: next }, { preserveScroll: true, preserveState: true });
    };
    const remove = () => removing && router.delete(route('fields.destroy', removing.id), { preserveScroll: true, onFinish: () => setRemoving(null) });
    const fields = order.map((id) => project.fields.find((f) => f.id === id)).filter(Boolean) as FieldDto[];

    return (
        <div className="grid gap-4">
            <p className="text-muted-foreground text-xs">
                Los campos aparecen en todas las tarjetas del proyecto en este mismo orden. Solo los moderadores los definen; cualquier miembro los
                completa. Arrastra para reordenar.
            </p>
            <form onSubmit={create} className="bg-muted/50 grid gap-3 rounded-lg border p-3">
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="grid gap-1.5">
                        <Label htmlFor="f-name">Nombre</Label>
                        <Input
                            id="f-name"
                            required
                            value={form.name}
                            onChange={(e) => setForm({ ...form, name: e.target.value })}
                            placeholder="Formato, Páginas, Entrega…"
                            maxLength={60}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label>Tipo</Label>
                        <Select value={form.type} onValueChange={(v) => setForm({ ...form, type: v as FieldType })}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {types.map((t) => (
                                    <SelectItem key={t.value} value={t.value}>
                                        {t.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                {withOptions && (
                    <div className="grid gap-1.5">
                        <Label htmlFor="f-options">Opciones</Label>
                        <Input
                            id="f-options"
                            required
                            value={form.options}
                            onChange={(e) => setForm({ ...form, options: e.target.value })}
                            placeholder="A4, A5, Media carta"
                        />
                        <p className="text-muted-foreground text-xs">Separadas por coma.</p>
                    </div>
                )}
                <div className="flex items-center justify-between">
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={form.required} onCheckedChange={(v) => setForm({ ...form, required: v === true })} />
                        Obligatorio
                    </label>
                    <Button type="submit" size="sm" disabled={busy || form.name.trim() === ''}>
                        {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
                        Crear campo
                    </Button>
                </div>
            </form>
            {fields.length === 0 ? (
                <p className="text-muted-foreground text-sm">No hay campos personalizados.</p>
            ) : (
                <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
                    <SortableContext items={order} strategy={verticalListSortingStrategy}>
                        <ul className="divide-y rounded-lg border">
                            {fields.map((f) => (
                                <FieldRow key={f.id} field={f} onDelete={() => setRemoving(f)} />
                            ))}
                        </ul>
                    </SortableContext>
                </DndContext>
            )}
            <ConfirmDialog
                open={!!removing}
                onClose={() => setRemoving(null)}
                onConfirm={remove}
                title="Eliminar campo"
                message={`Se pierden los valores de "${removing?.name ?? ''}" en todas las tarjetas.`}
            />
        </div>
    );
}

function FieldRow({ field, onDelete }: { field: FieldDto; onDelete: () => void }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: field.id });
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState({ name: field.name, options: field.options.join(', '), required: field.required });
    const [busy, setBusy] = useState(false);
    const withOptions = field.type === 'select' || field.type === 'multiselect';
    const save: FormEventHandler = (e) => {
        e.preventDefault();
        setBusy(true);
        router.put(
            route('fields.update', field.id),
            {
                name: draft.name,
                type: field.type,
                required: draft.required,
                options: withOptions
                    ? draft.options
                          .split(',')
                          .map((o) => o.trim())
                          .filter(Boolean)
                    : [],
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setEditing(false),
                onError: (er) => toast.error(firstError(er)),
                onFinish: () => setBusy(false),
            },
        );
    };
    return (
        <li
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={cn('bg-card flex items-start gap-2 px-2 py-2', isDragging && 'z-10 opacity-70 shadow-lg')}
        >
            <button
                type="button"
                {...attributes}
                {...listeners}
                className="text-muted-foreground/60 hover:bg-muted hover:text-muted-foreground mt-1 cursor-grab touch-none rounded p-1 active:cursor-grabbing"
                aria-label="Arrastrar"
            >
                <GripVertical className="h-4 w-4" />
            </button>
            {editing ? (
                <form onSubmit={save} className="flex-1 space-y-2">
                    <div className="grid gap-2 sm:grid-cols-2">
                        <Input
                            required
                            maxLength={60}
                            value={draft.name}
                            onChange={(e) => setDraft({ ...draft, name: e.target.value })}
                            placeholder="Nombre"
                        />
                        {withOptions && (
                            <Input
                                required
                                value={draft.options}
                                onChange={(e) => setDraft({ ...draft, options: e.target.value })}
                                placeholder="Opciones separadas por coma"
                            />
                        )}
                    </div>
                    <div className="flex items-center justify-between">
                        <label className="flex items-center gap-2 text-xs">
                            <Checkbox checked={draft.required} onCheckedChange={(v) => setDraft({ ...draft, required: v === true })} />
                            Obligatorio
                        </label>
                        <div className="flex gap-1">
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() => {
                                    setEditing(false);
                                    setDraft({ name: field.name, options: field.options.join(', '), required: field.required });
                                }}
                            >
                                <X className="h-3.5 w-3.5" />
                                Cancelar
                            </Button>
                            <Button type="submit" size="sm" disabled={busy}>
                                {busy ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Check className="h-3.5 w-3.5" />}
                                Guardar
                            </Button>
                        </div>
                    </div>
                </form>
            ) : (
                <>
                    <div className="min-w-0 flex-1">
                        <p className="text-sm font-medium">
                            {field.name}
                            {field.required && <span className="text-red-500"> *</span>}
                        </p>
                        <p className="text-muted-foreground truncate text-xs">
                            {field.type_label}
                            {field.options.length > 0 && ` · ${field.options.join(', ')}`}
                        </p>
                    </div>
                    <Button type="button" variant="ghost" size="icon" className="h-8 w-8" title="Editar" onClick={() => setEditing(true)}>
                        <Pencil className="h-4 w-4" />
                    </Button>
                    <Button type="button" variant="ghost" size="icon" className="h-8 w-8 text-red-600" title="Eliminar" onClick={onDelete}>
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </>
            )}
        </li>
    );
}
