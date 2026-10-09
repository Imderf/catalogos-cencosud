import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { type AdjustmentTypeValue, type PageDetail, type TypeOption } from '@/types';
import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useEffect, useRef } from 'react';

interface Base {
    open: boolean;
    onClose: () => void;
    page: PageDetail;
    catalogSlug: string;
}

export function ApproveDialog({ open, onClose, page, catalogSlug, mode = 'validate' }: Base & { mode?: 'validate' | 'print' }) {
    const form = useForm({});
    const stageLabel = (mode === 'print' ? page.print_chain : page.chain).find((c) => c.role === page.stage)?.label ?? '';
    const isPrint = mode === 'print';
    const description = isPrint
        ? page.next_role_label
            ? `Al aprobar, la página baja a ${page.next_role_label} para su aprobación; Marketing, al final, es quien la aprueba a imprenta.`
            : 'Eres el último eslabón. Al aprobar a imprenta la página queda cerrada; solo Marketing puede reabrirla como Post Production Defect.'
        : page.next_role_label
          ? `Al validar, la página queda bloqueada para tu equipo y sube a ${page.next_role_label}.`
          : 'Eres el último en validar. Después, la aprobación a imprenta empieza en Comercial y baja hasta Marketing.';
    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isPrint ? (page.next_role_label ? 'Aprobar' : 'Aprobar a imprenta') : 'Validar'} como {stageLabel}
                    </DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <dl className="grid grid-cols-2 gap-2 text-sm">
                    <dt className="text-muted-foreground">Página</dt>
                    <dd>
                        {page.label} · {page.sectors_label}
                    </dd>
                    <dt className="text-muted-foreground">Versión</dt>
                    <dd className="font-mono">v{page.version}</dd>
                    <dt className="text-muted-foreground">Siguiente</dt>
                    <dd>{page.next_role_label ?? (isPrint ? 'Cierra la página' : 'Aprobación a imprenta')}</dd>
                    <dt className="text-muted-foreground">Ajustes abiertos</dt>
                    <dd className={page.open > 0 ? 'text-red-600 dark:text-red-400' : ''}>{page.open}</dd>
                </dl>
                <DialogFooter>
                    <Button variant="ghost" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button
                        disabled={form.processing}
                        onClick={() =>
                            form.post(route(isPrint ? 'pages.print' : 'pages.validate', [catalogSlug, page.number]), {
                                preserveScroll: true,
                                onFinish: onClose,
                            })
                        }
                    >
                        {form.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        {isPrint
                            ? page.next_role_short
                                ? `Aprobar y enviar a ${page.next_role_short}`
                                : 'Aprobar a imprenta y cerrar'
                            : page.next_role_short
                              ? `Validar y enviar a ${page.next_role_short}`
                              : 'Confirmar validación'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

type DefectForm = { type: AdjustmentTypeValue; reason: string };

export function DefectDialog({ open, onClose, page, catalogSlug, types }: Base & { types: TypeOption[] }) {
    const form = useForm<DefectForm>({ type: 'imagen', reason: '' });
    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reabrir por defecto post-producción</DialogTitle>
                    <DialogDescription>
                        La {page.label} ya está aprobada a imprenta por toda la cadena. Reabrirla anula las validaciones y aprobaciones de la v
                        {page.version}, crea un ajuste marcado como defecto que va directo a Ogilvy y queda contado aparte en el reporte.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label>Tipo</Label>
                        <Select value={form.data.type} onValueChange={(v) => form.setData('type', v as AdjustmentTypeValue)}>
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
                    <div className="grid gap-2">
                        <Label htmlFor="reason">Qué se encontró</Label>
                        <Textarea id="reason" rows={4} autoFocus value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
                        <InputError message={form.errors.reason} />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="ghost" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button
                        variant="destructive"
                        disabled={form.processing || !form.data.reason.trim()}
                        onClick={() =>
                            form.post(route('pages.defect', [catalogSlug, page.number]), {
                                preserveScroll: true,
                                onSuccess: () => {
                                    form.reset();
                                    onClose();
                                },
                            })
                        }
                    >
                        {form.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Reabrir página
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

type VersionForm = { image: File | null; resolved: number[] };

export function VersionDialog({ open, onClose, page, catalogSlug }: Base) {
    const openOnes = page.adjustments.filter((a) => a.status === 'open');
    const form = useForm<VersionForm>({ image: null, resolved: openOnes.map((a) => a.id) });
    const fileRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (open)
            form.setData(
                'resolved',
                openOnes.map((a) => a.id),
            );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cargar nueva versión</DialogTitle>
                    <DialogDescription>
                        Esta carga se guarda como v{page.version + 1} y reinicia la cadena de aprobación. Marca los ajustes que quedan resueltos.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4">
                    <input
                        ref={fileRef}
                        type="file"
                        accept="image/*"
                        className="hidden"
                        onChange={(e) => form.setData('image', e.target.files?.[0] ?? null)}
                    />
                    <button
                        type="button"
                        onClick={() => fileRef.current?.click()}
                        className="text-muted-foreground hover:bg-muted/50 rounded-xl border border-dashed p-6 text-sm"
                    >
                        {form.data.image
                            ? `${form.data.image.name} — clic para cambiar`
                            : `Haz clic para elegir la imagen ajustada de la ${page.label}`}
                    </button>
                    <InputError message={form.errors.image} />
                    {openOnes.length > 0 && (
                        <div className="grid gap-2">
                            {openOnes.map((a) => (
                                <label key={a.id} className="flex items-start gap-2 text-sm">
                                    <Checkbox
                                        checked={form.data.resolved.includes(a.id)}
                                        onCheckedChange={(v) =>
                                            form.setData(
                                                'resolved',
                                                v === true ? [...form.data.resolved, a.id] : form.data.resolved.filter((id) => id !== a.id),
                                            )
                                        }
                                    />
                                    <span>
                                        <span className="font-medium">{a.type_label}</span> · {a.text}
                                    </span>
                                </label>
                            ))}
                        </div>
                    )}
                </div>
                <DialogFooter>
                    <Button variant="ghost" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button
                        disabled={form.processing || !form.data.image}
                        onClick={() =>
                            form.post(route('pages.versions.store', [catalogSlug, page.number]), {
                                forceFormData: true,
                                preserveScroll: true,
                                onSuccess: () => {
                                    form.reset();
                                    onClose();
                                },
                            })
                        }
                    >
                        {form.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Cargar v{page.version + 1}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
