import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type CatalogSummary, type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Loader2, Trash2 } from 'lucide-react';
import { useState } from 'react';

/** Administrators only. Deleting is final, so the person has to type the catalog's name to confirm. */
export default function DeleteCatalogDialog({
    catalog,
    size = 'sm',
}: {
    catalog: Pick<CatalogSummary, 'slug' | 'name' | 'pages_count'>;
    size?: 'sm' | 'default';
}) {
    const { auth } = usePage<SharedData>().props;
    const [open, setOpen] = useState(false);
    const [confirm, setConfirm] = useState('');
    const [busy, setBusy] = useState(false);
    if (auth.user.role !== 'admin') return null;
    const matches = confirm.trim() === catalog.name;
    const destroy = () => {
        if (!matches) return;
        setBusy(true);
        router.delete(route('catalogs.destroy', catalog.slug), { data: { confirm }, onFinish: () => setBusy(false) });
    };
    return (
        <>
            <Button
                size={size}
                variant="outline"
                className="text-red-600 hover:text-red-700"
                onClick={() => setOpen(true)}
                title="Eliminar catálogo (solo administradores)"
            >
                <Trash2 className="h-4 w-4" />
                Eliminar
            </Button>
            <Dialog
                open={open}
                onOpenChange={(v) => {
                    setOpen(v);
                    if (!v) setConfirm('');
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Eliminar "{catalog.name}"</DialogTitle>
                        <DialogDescription>
                            Se borran sus {catalog.pages_count} página{catalog.pages_count === 1 ? '' : 's'}, versiones, imágenes, ajustes,
                            aprobaciones y su tarjeta en el tablero. No se puede deshacer. Si solo quieres sacarlo de la lista, usa Archivar.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-1.5">
                        <Label htmlFor="confirm-delete">Escribe el nombre del catálogo para confirmar</Label>
                        <Input
                            id="confirm-delete"
                            autoFocus
                            value={confirm}
                            onChange={(e) => setConfirm(e.target.value)}
                            placeholder={catalog.name}
                            onKeyDown={(e) => e.key === 'Enter' && destroy()}
                        />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="secondary" onClick={() => setOpen(false)}>
                            Cancelar
                        </Button>
                        <Button type="button" variant="destructive" onClick={destroy} disabled={!matches || busy}>
                            {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Trash2 className="h-4 w-4" />}
                            Eliminar definitivamente
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
