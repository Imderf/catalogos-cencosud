import { TypeChips } from '@/components/review/type-chips';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useImageRatio } from '@/hooks/use-image-ratio';
import { cn } from '@/lib/utils';
import { type AdjustmentDto, type AdjustmentTypeValue, type BoxDto, type TypeOption } from '@/types';
import { useForm } from '@inertiajs/react';
import { Link2, LoaderCircle, Paperclip, X } from 'lucide-react';
import { type PointerEvent, useEffect, useRef, useState } from 'react';

interface Props {
    imageUrl: string | null;
    adjustments: AdjustmentDto[];
    zoom: number;
    drawing: boolean;
    historical: boolean;
    types: TypeOption[];
    storeUrl: string;
    onDrawn: () => void;
    onPickAdjustment: (a: AdjustmentDto) => void;
    highlight: number | null;
    /** 'height' shows the whole page, 'width' fills the box, 'custom' uses zoom */
    fit?: 'height' | 'width' | 'custom';
    /** fill the parent instead of a capped height (focused mode) */
    fill?: boolean;
    onWheelZoom?: (dir: 1 | -1) => void;
}

type DraftForm = { types: AdjustmentTypeValue[]; text: string; box: BoxDto; files: File[]; url: string };

const ACCEPT = 'application/pdf,image/jpeg,image/png,image/gif,image/webp';

/** The page image with numbered regions; in draw mode a drag creates a new adjustment. */

export default function Canvas({
    imageUrl,
    adjustments,
    zoom,
    drawing,
    historical,
    types,
    storeUrl,
    onDrawn,
    onPickAdjustment,
    highlight,
    fit = 'custom',
    fill = false,
    onWheelZoom,
}: Props) {
    const start = useRef<{ x: number; y: number } | null>(null);
    const [box, setBox] = useState({ w: 0, h: 0 });
    const boxRefs = useRef<Record<number, HTMLButtonElement | null>>({});

    /* Drag to pan: the page lives in its own scroll box, so zooming in never traps you. */
    const viewport = useRef<HTMLDivElement>(null);
    const pan = useRef<{ x: number; y: number; left: number; top: number; active: boolean } | null>(null);
    const [panning, setPanning] = useState(false);
    /* Measure the box for fit modes, and zoom with Ctrl (or Cmd) + wheel without scrolling the browser. */
    useEffect(() => {
        const el = viewport.current;
        if (!el) return;
        const ro = new ResizeObserver(() => setBox({ w: el.clientWidth, h: el.clientHeight }));
        ro.observe(el);
        const wheel = (e: WheelEvent) => {
            if (!(e.ctrlKey || e.metaKey) || !onWheelZoom) return;
            e.preventDefault();
            onWheelZoom(e.deltaY < 0 ? 1 : -1);
        };
        el.addEventListener('wheel', wheel, { passive: false });
        return () => {
            ro.disconnect();
            el.removeEventListener('wheel', wheel);
        };
    }, [onWheelZoom]);

    /* The stage takes the artwork's own proportion: a wider or narrower page is never cropped. */
    const ratio = useImageRatio(imageUrl);
    const pageStyle =
        fit === 'height'
            ? { width: Math.max(160, (box.h - 32) * ratio) }
            : fit === 'width'
              ? { width: '100%' }
              : { width: zoom === 100 ? '100%' : `${zoom}%`, maxWidth: zoom === 100 && !fill ? 760 : undefined };

    const canPan = !drawing;
    const onPanDown = (e: PointerEvent<HTMLDivElement>) => {
        if (!canPan || e.button !== 0 || !viewport.current) return;
        pan.current = { x: e.clientX, y: e.clientY, left: viewport.current.scrollLeft, top: viewport.current.scrollTop, active: false };
    };
    const onPanMove = (e: PointerEvent<HTMLDivElement>) => {
        const p = pan.current;
        if (!p || !viewport.current) return;
        const dx = e.clientX - p.x;
        const dy = e.clientY - p.y;
        if (!p.active) {
            if (Math.abs(dx) < 4 && Math.abs(dy) < 4) return; // a click on a marker is not a drag
            p.active = true;
            setPanning(true);
            viewport.current.setPointerCapture(e.pointerId);
        }
        viewport.current.scrollLeft = p.left - dx;
        viewport.current.scrollTop = p.top - dy;
    };
    const onPanEnd = () => {
        pan.current = null;
        setPanning(false);
    };

    /* When an adjustment is picked from the list, bring its region into view. */
    useEffect(() => {
        if (highlight === null) return;
        boxRefs.current[highlight]?.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
    }, [highlight]);
    const [temp, setTemp] = useState<BoxDto | null>(null);
    const [draft, setDraft] = useState<BoxDto | null>(null);
    const form = useForm<DraftForm>({ types: ['precio'], text: '', box: { x: 0, y: 0, w: 0, h: 0 }, files: [], url: '' });
    const fileRef = useRef<HTMLInputElement>(null);
    const addFiles = (list: FileList | null) => {
        if (!list) return;
        form.setData('files', [...form.data.files, ...Array.from(list)].slice(0, 5));
        if (fileRef.current) fileRef.current.value = '';
    };

    const pct = (e: PointerEvent<HTMLDivElement>) => {
        const r = e.currentTarget.getBoundingClientRect();
        return { x: Math.min(1, Math.max(0, (e.clientX - r.left) / r.width)), y: Math.min(1, Math.max(0, (e.clientY - r.top) / r.height)) };
    };
    const rect = (a: { x: number; y: number }, b: { x: number; y: number }): BoxDto => ({
        x: Math.min(a.x, b.x),
        y: Math.min(a.y, b.y),
        w: Math.abs(b.x - a.x),
        h: Math.abs(b.y - a.y),
    });

    const onDown = (e: PointerEvent<HTMLDivElement>) => {
        if (!drawing || draft) return;
        e.currentTarget.setPointerCapture(e.pointerId);
        start.current = pct(e);
        setTemp({ ...start.current, w: 0, h: 0 });
    };
    const onMove = (e: PointerEvent<HTMLDivElement>) => {
        if (!drawing || !start.current || draft) return;
        setTemp(rect(start.current, pct(e)));
    };
    const onUp = (e: PointerEvent<HTMLDivElement>) => {
        if (!drawing || !start.current || draft) return;
        const a = start.current;
        start.current = null;
        let b = rect(a, pct(e));
        if (b.w < 0.03 || b.h < 0.02) {
            const w = 0.18;
            const h = 0.08;
            b = { x: Math.max(0, Math.min(1 - w, a.x - w / 2)), y: Math.max(0, Math.min(1 - h, a.y - h / 2)), w, h };
        }
        setTemp(b);
        setDraft(b);
        form.setData({ types: ['precio'], text: '', box: b, files: [], url: '' });
    };

    const cancel = () => {
        setDraft(null);
        setTemp(null);
        form.reset();
        onDrawn();
    };
    const send = () => {
        if (!form.data.text.trim() || form.data.types.length === 0) return;
        form.post(storeUrl, { preserveScroll: true, forceFormData: form.data.files.length > 0, onSuccess: cancel });
    };

    const style = (b: BoxDto) => ({ left: `${b.x * 100}%`, top: `${b.y * 100}%`, width: `${b.w * 100}%`, height: `${b.h * 100}%` });

    return (
        /* The form for a new adjustment sits over the stage, never inside the page: a region near an edge would clip it. */
        <div className={cn('relative', fill && 'h-full w-full')}>
            <div
                ref={viewport}
                className={cn(
                    'overflow-auto bg-neutral-200 p-4 select-none',
                    fill ? 'h-full w-full' : 'max-h-[calc(100vh-13rem)] rounded-xl border',
                    canPan && (panning ? 'cursor-grabbing' : 'cursor-grab'),
                )}
                style={{ touchAction: drawing ? 'none' : 'pan-x pan-y' }}
                onPointerDown={onPanDown}
                onPointerMove={onPanMove}
                onPointerUp={onPanEnd}
                onPointerCancel={onPanEnd}
            >
                <div className="relative mx-auto shadow-lg" style={pageStyle}>
                    <div className="relative w-full overflow-hidden bg-white" style={{ aspectRatio: String(ratio) }}>
                        {imageUrl && (
                            <div
                                className={cn('absolute inset-0 bg-cover bg-top select-none', historical && 'opacity-60 grayscale')}
                                style={{ backgroundImage: `url('${imageUrl}')` }}
                            />
                        )}
                        <div
                            data-testid="canvas"
                            className="absolute inset-0"
                            style={{ cursor: drawing ? 'crosshair' : 'default', touchAction: drawing ? 'none' : 'auto' }}
                            onPointerDown={onDown}
                            onPointerMove={onMove}
                            onPointerUp={onUp}
                            onPointerCancel={() => {
                                start.current = null;
                                setTemp(null);
                            }}
                        >
                            {adjustments.map((a) => {
                                const open = a.status === 'open';
                                return (
                                    <button
                                        key={a.id}
                                        ref={(el) => {
                                            boxRefs.current[a.id] = el;
                                        }}
                                        type="button"
                                        title={`#${a.index} · ${a.type_label}`}
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            onPickAdjustment(a);
                                        }}
                                        className={cn(
                                            'absolute rounded-lg border-2 transition-shadow',
                                            open
                                                ? 'border-red-500 bg-red-500/10 shadow-[0_0_0_5px_rgba(239,59,58,.16)]'
                                                : 'border-neutral-400 bg-white/5',
                                            highlight === a.id && 'z-[5] animate-pulse ring-4 ring-red-500/60 ring-offset-2',
                                        )}
                                        style={style(a.box)}
                                    >
                                        <span
                                            className={cn(
                                                'absolute -top-3 -left-3 flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold shadow',
                                                open ? 'bg-red-500 text-white' : 'bg-white text-neutral-900',
                                            )}
                                        >
                                            {a.index}
                                        </span>
                                    </button>
                                );
                            })}
                            {(temp ?? draft) && (
                                <div
                                    className="pointer-events-none absolute rounded-lg border-2 border-dashed border-red-500 bg-red-500/10"
                                    style={style((temp ?? draft) as BoxDto)}
                                />
                            )}
                        </div>
                    </div>
                </div>
            </div>
            {draft && (
                <div
                    className="bg-card absolute right-4 bottom-4 z-20 w-72 max-w-[calc(100%-2rem)] rounded-xl border p-3 shadow-xl"
                    onPointerDown={(e) => e.stopPropagation()}
                >
                    <p className="mb-2 text-xs font-medium">Nuevo ajuste</p>
                    <TypeChips types={types} value={form.data.types} onChange={(v) => form.setData('types', v)} className="mb-2" />
                    {form.errors.types && <p className="mb-1 text-xs text-red-600 dark:text-red-400">{form.errors.types}</p>}
                    <Textarea
                        autoFocus
                        rows={3}
                        placeholder="¿Qué hay que cambiar aquí?"
                        value={form.data.text}
                        onChange={(e) => form.setData('text', e.target.value)}
                        className="text-sm"
                    />
                    {form.errors.text && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{form.errors.text}</p>}

                    {/* Reference material for Ogilvy: a PDF or image, and/or a link to resources. */}
                    <div className="mt-2 space-y-1.5">
                        <div className="flex items-center gap-1.5">
                            <Link2 className="text-muted-foreground size-3.5 shrink-0" />
                            <Input
                                type="url"
                                inputMode="url"
                                placeholder="Enlace a recursos (opcional)"
                                value={form.data.url}
                                onChange={(e) => form.setData('url', e.target.value)}
                                className="h-8 text-xs"
                            />
                        </div>
                        {form.errors.url && <p className="text-xs text-red-600 dark:text-red-400">{form.errors.url}</p>}
                        <input ref={fileRef} type="file" accept={ACCEPT} multiple className="hidden" onChange={(e) => addFiles(e.target.files)} />
                        <div className="flex flex-wrap items-center gap-1">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="h-7 gap-1 px-2 text-xs"
                                onClick={() => fileRef.current?.click()}
                            >
                                <Paperclip className="size-3.5" /> PDF o imagen
                            </Button>
                            {form.data.files.map((f, i) => (
                                <span
                                    key={`${f.name}-${i}`}
                                    className="bg-muted inline-flex max-w-full items-center gap-1 rounded px-1.5 py-0.5 text-xs"
                                >
                                    <span className="truncate">{f.name}</span>
                                    <button
                                        type="button"
                                        aria-label={`Quitar ${f.name}`}
                                        className="text-muted-foreground hover:text-foreground"
                                        onClick={() =>
                                            form.setData(
                                                'files',
                                                form.data.files.filter((_, j) => j !== i),
                                            )
                                        }
                                    >
                                        <X className="size-3" />
                                    </button>
                                </span>
                            ))}
                        </div>
                        {Object.entries(form.errors)
                            .filter(([k]) => k.startsWith('files'))
                            .slice(0, 1)
                            .map(([k, v]) => (
                                <p key={k} className="text-xs text-red-600 dark:text-red-400">
                                    {v}
                                </p>
                            ))}
                    </div>
                    <div className="mt-2 flex justify-end gap-2">
                        <Button size="sm" variant="ghost" onClick={cancel}>
                            Cancelar
                        </Button>
                        <Button size="sm" onClick={send} disabled={form.processing || !form.data.text.trim() || form.data.types.length === 0}>
                            {form.processing && <LoaderCircle className="h-3 w-3 animate-spin" />}
                            Enviar
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
