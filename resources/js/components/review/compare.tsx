import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useImageRatio } from '@/hooks/use-image-ratio';
import { detectChanges, type DiffResult } from '@/lib/diff';
import { fmtDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type AdjustmentDto, type PageVersionDto } from '@/types';
import { type PointerEvent, useEffect, useRef, useState } from 'react';

interface Props {
    versions: PageVersionDto[];
    b: number;
    adjustments: AdjustmentDto[];
}

/** Compare happens on the canvas itself: side by side, or a curtain you drag. */
export default function Compare({ versions, b, adjustments }: Props) {
    const [a, setA] = useState(Math.max(1, b - 1));
    const [mode, setMode] = useState<'lado' | 'cortina'>('lado');
    const [x, setX] = useState(0.5);
    const [diff, setDiff] = useState<DiffResult | null>(null);
    const dragging = useRef(false);
    const va = versions.find((v) => v.number === a);
    const vb = versions.find((v) => v.number === b);
    /* Each version keeps its own proportion; nothing is cropped. */
    const ratioA = useImageRatio(va?.url);
    const ratioB = useImageRatio(vb?.url);

    useEffect(() => {
        let alive = true;
        setDiff(null);
        detectChanges(va?.url ?? null, vb?.url ?? null).then((r) => alive && setDiff(r));
        return () => {
            alive = false;
        };
    }, [va?.url, vb?.url]);

    const posX = (e: PointerEvent<HTMLDivElement>) => {
        const r = e.currentTarget.getBoundingClientRect();
        return Math.min(1, Math.max(0, (e.clientX - r.left) / r.width));
    };
    const resolved = adjustments.filter((c) => c.resolved_in === b);
    const status = !diff
        ? 'Detectando cambios…'
        : diff.state === 'cambios'
          ? `${diff.boxes.length} región(es) con cambios detectados`
          : diff.state === 'iguales'
            ? 'Sin diferencias de imagen entre las dos versiones'
            : 'No se pudieron comparar las imágenes';

    const img = (url?: string) => ({ backgroundImage: url ? `url('${url}')` : undefined });
    const marks = diff?.boxes.map((bx, i) => (
        <div
            key={i}
            className="pointer-events-none absolute rounded border-2 border-red-500 bg-red-500/10"
            style={{ left: `${bx.x * 100}%`, top: `${bx.y * 100}%`, width: `${bx.w * 100}%`, height: `${bx.h * 100}%` }}
        >
            <span className="absolute -top-2.5 -left-2.5 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-semibold text-white">
                {i + 1}
            </span>
        </div>
    ));

    return (
        <div className="grid gap-4">
            <div className="flex flex-wrap items-center gap-3">
                <div className="flex items-center gap-2 text-sm">
                    <span className="text-muted-foreground">Comparar</span>
                    <Select value={String(a)} onValueChange={(v) => setA(parseInt(v, 10))}>
                        <SelectTrigger className="h-8 w-44 text-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {versions
                                .filter((v) => v.number < b)
                                .map((v) => (
                                    <SelectItem key={v.number} value={String(v.number)}>
                                        v{v.number} · {fmtDate(v.date)}
                                    </SelectItem>
                                ))}
                        </SelectContent>
                    </Select>
                    <span className="text-muted-foreground">contra</span>
                    <Badge variant="outline">v{b}</Badge>
                </div>
                <div className="bg-muted flex rounded-lg p-0.5 text-xs">
                    {(['lado', 'cortina'] as const).map((m) => (
                        <button
                            key={m}
                            type="button"
                            onClick={() => setMode(m)}
                            className={cn('rounded-md px-3 py-1', mode === m && 'bg-background shadow-sm')}
                        >
                            {m === 'lado' ? 'Lado a lado' : 'Cortina'}
                        </button>
                    ))}
                </div>
                <Badge variant={diff?.state === 'cambios' ? 'destructive' : 'secondary'}>{status}</Badge>
            </div>

            {mode === 'lado' ? (
                <div className="grid grid-cols-2 gap-3 rounded-xl border bg-neutral-200 p-3">
                    {[
                        { v: va, label: `v${a}`, marks: null, ratio: ratioA },
                        { v: vb, label: `v${b}`, marks, ratio: ratioB },
                    ].map((side) => (
                        <div
                            key={side.label}
                            className="relative overflow-hidden rounded bg-white shadow"
                            style={{ aspectRatio: String(side.ratio) }}
                        >
                            <div className="absolute inset-0 bg-cover bg-top" style={img(side.v?.url)} />
                            {side.marks}
                            <span className="absolute top-2 left-2 rounded bg-black/75 px-1.5 py-0.5 font-mono text-[11px] text-white">
                                {side.label}
                            </span>
                        </div>
                    ))}
                </div>
            ) : (
                <div className="rounded-xl border bg-neutral-200 p-3">
                    <div
                        className="relative mx-auto max-w-[760px] cursor-col-resize overflow-hidden rounded bg-white shadow select-none"
                        style={{ touchAction: 'none', aspectRatio: String(ratioB) }}
                        onPointerDown={(e) => {
                            e.currentTarget.setPointerCapture(e.pointerId);
                            dragging.current = true;
                            setX(posX(e));
                        }}
                        onPointerMove={(e) => dragging.current && setX(posX(e))}
                        onPointerUp={() => (dragging.current = false)}
                        onPointerCancel={() => (dragging.current = false)}
                    >
                        <div className="absolute inset-0 bg-cover bg-top" style={img(vb?.url)} />
                        {marks}
                        <div
                            className="absolute inset-0 bg-cover bg-top"
                            style={{ ...img(va?.url), clipPath: `inset(0 ${((1 - x) * 100).toFixed(1)}% 0 0)` }}
                        />
                        <div className="absolute inset-y-0 w-0.5 bg-red-500" style={{ left: `${(x * 100).toFixed(1)}%` }}>
                            <span className="absolute top-1/2 -left-3 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-full bg-red-500 text-[10px] text-white shadow">
                                ⇔
                            </span>
                        </div>
                        <span className="absolute top-2 left-2 rounded bg-black/75 px-1.5 py-0.5 font-mono text-[11px] text-white">v{a}</span>
                        <span className="absolute top-2 right-2 rounded bg-black/75 px-1.5 py-0.5 font-mono text-[11px] text-white">v{b}</span>
                    </div>
                </div>
            )}

            <div className="rounded-xl border p-4">
                <h4 className="mb-2 text-sm font-semibold">Ajustes que cerró la v{b}</h4>
                {resolved.length === 0 && <p className="text-muted-foreground text-sm">Esta versión no cerró ajustes.</p>}
                <ul className="grid gap-2">
                    {resolved.map((c) => (
                        <li key={c.id} className="text-sm">
                            <Badge variant="secondary" className="mr-2">
                                {c.type_label}
                            </Badge>
                            {c.text}{' '}
                            <span className="text-muted-foreground text-xs">
                                · {c.author} · pedido en v{c.version}
                            </span>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}
