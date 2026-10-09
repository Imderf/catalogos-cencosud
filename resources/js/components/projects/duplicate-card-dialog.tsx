import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { firstError } from '@/hooks/use-flash-toast';
import { type CardDetail, type ProjectBoard, type ProjectOption } from '@/types/projects';
import { router } from '@inertiajs/react';
import { Copy, Loader2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

/** Duplicate with options: title, target board and list, what travels along, how many copies. */
export default function DuplicateCardDialog({
    card,
    project,
    projects,
    open,
    onClose,
}: {
    card: CardDetail;
    project: ProjectBoard;
    projects: ProjectOption[];
    open: boolean;
    onClose: () => void;
}) {
    const [projectId, setProjectId] = useState(project.id);
    const [form, setForm] = useState({
        title: `Copia de ${card.title}`,
        listId: card.list_id,
        copyDueDate: false,
        copyAssignees: true,
        copyAttachments: true,
        count: 1,
    });
    const [busy, setBusy] = useState(false);
    const target = projects.find((p) => p.id === projectId) ?? {
        id: project.id,
        name: project.name,
        lists: project.lists.map((l) => ({ id: l.id, name: l.name })),
    };
    const listId = target.lists.some((l) => l.id === form.listId) ? form.listId : (target.lists[0]?.id ?? 0);

    const submit = () => {
        setBusy(true);
        router.post(
            route('cards.duplicate', card.id),
            {
                title: form.title,
                project_id: projectId,
                board_list_id: listId,
                copy_due_date: form.copyDueDate,
                copy_assignees: form.copyAssignees,
                copy_attachments: form.copyAttachments,
                count: form.count,
            },
            { onSuccess: onClose, onError: (e) => toast.error(firstError(e)), onFinish: () => setBusy(false) },
        );
    };

    return (
        <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Copy className="text-primary h-4 w-4" />
                        Duplicar tarjeta
                    </DialogTitle>
                    <DialogDescription>Se copian descripción, prioridad, etiquetas y campos. Los comentarios nunca se copian.</DialogDescription>
                </DialogHeader>
                <div className="grid gap-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="d-title">Título de la copia</Label>
                        <Input
                            id="d-title"
                            autoFocus
                            value={form.title}
                            onChange={(e) => setForm({ ...form, title: e.target.value })}
                            maxLength={200}
                        />
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label>Proyecto destino</Label>
                            <Select value={String(projectId)} onValueChange={(v) => setProjectId(Number(v))}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {projects.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Lista destino</Label>
                            <Select value={String(listId)} onValueChange={(v) => setForm({ ...form, listId: Number(v) })}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {target.lists.map((l) => (
                                        <SelectItem key={l.id} value={String(l.id)}>
                                            {l.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="bg-muted/50 grid gap-2 rounded-lg border p-3 text-sm">
                        <label className="flex items-center gap-2">
                            <Checkbox checked={form.copyAttachments} onCheckedChange={(v) => setForm({ ...form, copyAttachments: v === true })} />
                            Adjuntos y enlaces{' '}
                            {card.attachments.length > 0 && <span className="text-muted-foreground text-xs">({card.attachments.length})</span>}
                        </label>
                        <label className="flex items-center gap-2">
                            <Checkbox checked={form.copyAssignees} onCheckedChange={(v) => setForm({ ...form, copyAssignees: v === true })} />
                            Responsables{' '}
                            {projectId !== project.id && (
                                <span className="text-muted-foreground text-xs">(solo si son miembros del proyecto destino)</span>
                            )}
                        </label>
                        <label className="flex items-center gap-2">
                            <Checkbox checked={form.copyDueDate} onCheckedChange={(v) => setForm({ ...form, copyDueDate: v === true })} />
                            Fecha límite {card.due_date && <span className="text-muted-foreground text-xs">({card.due_date})</span>}
                        </label>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="d-count">Cantidad de copias</Label>
                        <Input
                            id="d-count"
                            type="number"
                            min={1}
                            max={10}
                            className="w-24"
                            value={form.count}
                            onChange={(e) => setForm({ ...form, count: Math.max(1, Math.min(10, Number(e.target.value) || 1)) })}
                        />
                        <p className="text-muted-foreground text-xs">Con más de una, se numeran (1), (2)…</p>
                    </div>
                    {projectId !== project.id && (
                        <p className="text-xs text-amber-700 dark:text-amber-400">
                            En otro proyecto, las etiquetas y campos se emparejan por nombre; los que no existan allí no se copian.
                        </p>
                    )}
                </div>
                <DialogFooter>
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button type="button" onClick={submit} disabled={busy || !listId || !form.title.trim()}>
                        {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Copy className="h-4 w-4" />}
                        {form.count > 1 ? `Crear ${form.count} copias` : 'Duplicar'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
