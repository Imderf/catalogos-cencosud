import ColorPicker from '@/components/projects/color-picker';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { type ClientOption, type ProjectSummary } from '@/types/projects';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect } from 'react';

type Form = { name: string; description: string; color: string; client_id: string; template_id: string };

/** Create (no project) or edit (project given) the basic data of a board. */
export default function ProjectFormDialog({
    open,
    onClose,
    project,
    clients,
    templates = [],
}: {
    open: boolean;
    onClose: () => void;
    project?: ProjectSummary;
    clients: ClientOption[];
    /** Boards whose setup (lists, labels, fields, people) a new project can copy. */
    templates?: { id: number; name: string }[];
}) {
    const form = useForm<Form>({
        name: project?.name ?? '',
        description: project?.description ?? '',
        color: project?.color ?? '#EF3B3A',
        client_id: project?.client ? String(project.client.id) : '',
        template_id: '',
    });
    const { setData } = form;

    useEffect(() => {
        if (open) {
            setData({
                name: project?.name ?? '',
                description: project?.description ?? '',
                color: project?.color ?? '#EF3B3A',
                client_id: project?.client ? String(project.client.id) : '',
                template_id: '',
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, project?.id]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const payload = {
            ...form.data,
            client_id: form.data.client_id === '' ? null : Number(form.data.client_id),
            template_id: form.data.template_id === '' ? null : Number(form.data.template_id),
        };
        const opts = { preserveScroll: true, onSuccess: () => onClose() };
        form.transform(() => payload);
        if (project) form.put(route('projects.update', project.id), opts);
        else form.post(route('projects.store'), opts);
    };

    return (
        <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit} className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>{project ? 'Editar proyecto' : 'Nuevo proyecto'}</DialogTitle>
                        <DialogDescription>Un tablero interno de Ogilvy. El cliente nunca lo ve.</DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="p-name">Nombre</Label>
                        <Input
                            id="p-name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            autoFocus
                            placeholder="Octavillas Jumbo · Q4"
                        />
                        {form.errors.name && <p className="text-sm text-red-600">{form.errors.name}</p>}
                    </div>
                    {!project && templates.length > 0 && (
                        <div className="grid gap-2">
                            <Label>Copiar configuración de</Label>
                            <Select value={form.data.template_id || 'none'} onValueChange={(v) => form.setData('template_id', v === 'none' ? '' : v)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Empezar desde cero" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Empezar desde cero (Backlog, Por hacer, En curso, Hecho)</SelectItem>
                                    {templates.map((t) => (
                                        <SelectItem key={t.id} value={String(t.id)}>
                                            {t.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-muted-foreground text-xs">
                                Se copian las listas con su tipo, las etiquetas, los campos personalizados y los miembros con su rol. Las tarjetas no.
                            </p>
                        </div>
                    )}
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
                        <Label htmlFor="p-desc">Descripción</Label>
                        <Textarea
                            id="p-desc"
                            rows={3}
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            placeholder="Qué se entrega, para cuándo, con quién."
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label>Color</Label>
                        <ColorPicker value={form.data.color} onChange={(c) => form.setData('color', c)} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={onClose}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {project ? 'Guardar' : 'Crear proyecto'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
