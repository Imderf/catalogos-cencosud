import ConfigTabs from '@/components/admin/config-tabs';
import Heading from '@/components/heading';
import ColorPicker from '@/components/projects/color-picker';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { firstError, useFlashToast } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Image as ImageIcon, Loader2, Plus, Trash2, Users } from 'lucide-react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuración', href: '/configuracion' },
    { title: 'Clientes', href: '/admin/clientes' },
];

interface ClientRow {
    id: number;
    name: string;
    color: string;
    is_active: boolean;
    logo_url: string | null;
    projects_count: number;
    brands: string[];
}

export default function ClientsIndex({ clients }: { clients: ClientRow[] }) {
    useFlashToast();
    const [creating, setCreating] = useState(false);
    const [managing, setManaging] = useState<ClientRow | null>(null);
    const current = managing ? (clients.find((c) => c.id === managing.id) ?? managing) : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Clientes" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <ConfigTabs />
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Clientes"
                        description="Las empresas para las que trabaja Ogilvy. Los proyectos se agrupan por cliente; las marcas (Jumbo, Metro) cuelgan de Cencosud."
                    />
                    <Button onClick={() => setCreating(true)}>
                        <Plus className="h-4 w-4" />
                        Nuevo cliente
                    </Button>
                </div>
                {clients.length === 0 ? (
                    <p className="text-muted-foreground rounded-xl border border-dashed p-10 text-center text-sm">
                        Aún no hay clientes. Crea el primero.
                    </p>
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-2">
                        {clients.map((c) => (
                            <li
                                key={c.id}
                                className={cn('bg-card flex items-center gap-3 rounded-xl border p-4 shadow-sm', !c.is_active && 'opacity-60')}
                            >
                                <Logo client={c} size="md" />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-semibold">
                                        {c.name}
                                        {!c.is_active && (
                                            <Badge variant="outline" className="ml-2">
                                                Inactivo
                                            </Badge>
                                        )}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {c.brands.length ? `${c.brands.join(', ')} · ` : ''}
                                        {c.projects_count} proyecto{c.projects_count !== 1 ? 's' : ''}
                                    </p>
                                </div>
                                <div className="flex shrink-0 gap-1">
                                    <Button variant="ghost" size="sm" onClick={() => setManaging(c)}>
                                        <Users className="h-4 w-4" />
                                        Gestionar
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            router.put(
                                                route('admin.clients.update', c.id),
                                                { name: c.name, color: c.color, is_active: !c.is_active },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {c.is_active ? 'Desactivar' : 'Activar'}
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            <CreateDialog open={creating} onClose={() => setCreating(false)} />
            {current && <ManageDialog client={current} onClose={() => setManaging(null)} />}
        </AppLayout>
    );
}

function Logo({ client, size }: { client: { name: string; color: string; logo_url: string | null }; size: 'md' | 'lg' }) {
    const s = size === 'lg' ? 'h-16 w-16 text-xl' : 'h-11 w-11 text-base';
    return client.logo_url ? (
        <img src={client.logo_url} alt="" className={cn('bg-card shrink-0 rounded-lg border object-contain', s)} />
    ) : (
        <span
            style={{ backgroundColor: client.color }}
            className={cn('flex shrink-0 items-center justify-center rounded-lg font-bold text-white', s)}
        >
            {client.name.charAt(0).toUpperCase() || '?'}
        </span>
    );
}

function CreateDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
    const form = useForm({ name: '', color: '#0057B8' });
    const { reset } = form;
    useEffect(() => {
        if (open) reset();
    }, [open, reset]);
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.clients.store'), { preserveScroll: true, onSuccess: onClose, onError: (er) => toast.error(firstError(er)) });
    };
    return (
        <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={submit} className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>Nuevo cliente</DialogTitle>
                        <DialogDescription>Nombre y color con el que aparece en los proyectos.</DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="cl-name">Nombre</Label>
                        <Input
                            id="cl-name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            autoFocus
                            maxLength={80}
                            placeholder="Nestlé"
                        />
                        {form.errors.name && <p className="text-sm text-red-600">{form.errors.name}</p>}
                    </div>
                    <div className="grid gap-2">
                        <Label>Color</Label>
                        <ColorPicker value={form.data.color} onChange={(c) => form.setData('color', c)} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={form.processing || !form.data.name.trim()}>
                            {form.processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
                            Crear
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ManageDialog({ client, onClose }: { client: ClientRow; onClose: () => void }) {
    const form = useForm({ name: client.name, color: client.color, is_active: client.is_active });
    const fileRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const save: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('admin.clients.update', client.id), { preserveScroll: true, onError: (er) => toast.error(firstError(er)) });
    };
    const upload = (file?: File) => {
        if (!file) return;
        setUploading(true);
        router.post(
            route('admin.clients.logo.store', client.id),
            { logo: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onError: (er) => toast.error(firstError(er)),
                onFinish: () => {
                    setUploading(false);
                    if (fileRef.current) fileRef.current.value = '';
                },
            },
        );
    };
    return (
        <Dialog open onOpenChange={(v) => !v && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Gestionar {client.name}</DialogTitle>
                    <DialogDescription>Logo, nombre y color. El logo se muestra en los tableros del cliente.</DialogDescription>
                </DialogHeader>
                <form onSubmit={save} className="grid gap-4 sm:grid-cols-[auto_1fr]">
                    <div className="flex flex-col items-center gap-2">
                        <Logo client={{ name: form.data.name, color: form.data.color, logo_url: client.logo_url }} size="lg" />
                        <input
                            ref={fileRef}
                            type="file"
                            accept="image/png,image/jpeg,image/webp,image/gif"
                            hidden
                            onChange={(e) => upload(e.target.files?.[0])}
                        />
                        <Button type="button" size="sm" variant="secondary" disabled={uploading} onClick={() => fileRef.current?.click()}>
                            {uploading ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <ImageIcon className="h-3.5 w-3.5" />}
                            Logo
                        </Button>
                        {client.logo_url && (
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                className="text-red-600"
                                onClick={() => router.delete(route('admin.clients.logo.destroy', client.id), { preserveScroll: true })}
                            >
                                <Trash2 className="h-3.5 w-3.5" />
                                Quitar
                            </Button>
                        )}
                    </div>
                    <div className="grid gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="m-name">Nombre</Label>
                            <Input id="m-name" maxLength={80} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Color</Label>
                            <ColorPicker value={form.data.color} onChange={(c) => form.setData('color', c)} />
                        </div>
                        <div className="flex justify-end">
                            <Button type="submit" size="sm" disabled={form.processing}>
                                {form.processing && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                                Guardar
                            </Button>
                        </div>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
