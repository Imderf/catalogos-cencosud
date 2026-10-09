import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCountdown } from '@/hooks/use-clock';
import { fmtReloj } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type CatalogSummary, type PageDetail } from '@/types';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Minus, MoveHorizontal, MoveVertical, Plus } from 'lucide-react';
import { type ReactNode } from 'react';

export type Fit = 'height' | 'width' | 'custom';

interface Props {
    catalog: CatalogSummary;
    page: PageDetail;
    nav: { prev: number | null; next: number | null; count: number };
    onGo: (n: number | null) => void;
    compare: boolean;
    onCompare: (v: boolean) => void;
    fit: Fit;
    zoom: number;
    onFit: (f: Fit) => void;
    onZoom: (dir: 1 | -1) => void;
    onClock: () => void;
    actions: ReactNode;
}

/** Two rows above the page: where you are and what you can do; view controls. */
export default function ReviewBar({ catalog, page, nav, onGo, compare, onCompare, fit, zoom, onFit, onZoom, onClock, actions }: Props) {
    const clock = page.clock;
    const live = useCountdown(clock?.remaining ?? null, clock?.frozen ?? true);
    const late = clock && !clock.frozen && (clock.state === 'vencido' || clock.state === 'urgente');
    const status = page.approved ? `Aprobada a imprenta v${page.version}` : page.location.label;

    return (
        <div className="bg-card shrink-0 border-b">
            <div className="flex flex-wrap items-center gap-2 px-3 py-2 text-sm">
                <div className="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8"
                        onClick={() => onGo(nav.prev)}
                        disabled={!nav.prev}
                        aria-label="Página anterior"
                    >
                        <ChevronLeft className="h-4 w-4" />
                    </Button>
                    <span className="font-semibold">
                        {page.label} <span className="text-muted-foreground font-normal">/ {nav.count}</span>
                    </span>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8"
                        onClick={() => onGo(nav.next)}
                        disabled={!nav.next}
                        aria-label="Página siguiente"
                    >
                        <ChevronRight className="h-4 w-4" />
                    </Button>
                </div>
                <span className="text-muted-foreground hidden text-xs md:inline">{page.sectors_label}</span>
                <Badge variant={page.approved ? 'default' : page.is_mine && page.has_access ? 'destructive' : 'secondary'}>{status}</Badge>
                {!page.has_access && (
                    <Badge variant="outline" title={page.can.comment.reason ?? undefined}>
                        Otra categoría · solo lectura
                    </Badge>
                )}
                {page.open > 0 && !page.approved && (
                    <span className="text-muted-foreground text-xs">
                        · {page.open} ajuste{page.open === 1 ? '' : 's'} en curso
                    </span>
                )}
                {clock && live !== null && (
                    <button
                        type="button"
                        onClick={onClock}
                        className={cn(
                            'flex items-center gap-1.5 rounded-md border px-2 py-1 font-mono text-xs',
                            late
                                ? 'border-red-200 text-red-600 dark:border-red-900 dark:text-red-400'
                                : clock.frozen
                                  ? 'text-muted-foreground'
                                  : 'text-emerald-700 dark:text-emerald-400',
                        )}
                        title={clock.detail}
                    >
                        <span className={cn('h-2 w-2 rounded-full', clock.frozen ? 'bg-neutral-400' : late ? 'bg-red-500' : 'bg-emerald-500')} />
                        {fmtReloj(live)}
                    </button>
                )}
                <div className="ml-auto flex flex-wrap items-center gap-2">{actions}</div>
            </div>
            <div className="flex flex-wrap items-center gap-2 border-t px-3 py-1.5 text-xs">
                <div className="flex gap-1">
                    {page.versions.map((v) => (
                        <Link
                            key={v.number}
                            href={route('pages.show', { catalog: catalog.slug, page: page.number, version: v.number })}
                            preserveScroll
                            className={cn(
                                'rounded-full border px-2 py-0.5 font-mono',
                                v.number === page.viewing_version ? 'bg-foreground text-background border-foreground' : 'hover:bg-muted',
                            )}
                        >
                            v{v.number}
                        </Link>
                    ))}
                </div>
                {page.versions.length > 1 && (
                    <div className="bg-muted flex rounded-lg p-0.5">
                        <button
                            type="button"
                            onClick={() => onCompare(false)}
                            className={cn('rounded-md px-2 py-1', !compare && 'bg-background shadow-sm')}
                        >
                            Revisión
                        </button>
                        <button
                            type="button"
                            onClick={() => onCompare(true)}
                            className={cn('rounded-md px-2 py-1', compare && 'bg-background shadow-sm')}
                        >
                            Comparar
                        </button>
                    </div>
                )}
                {page.is_historical && <Badge variant="outline">Versión histórica · reemplazada por v{page.version}</Badge>}
                {!compare && (
                    <div className="ml-auto flex items-center gap-0.5">
                        <Button
                            variant={fit === 'height' ? 'secondary' : 'ghost'}
                            size="icon"
                            className="h-7 w-7"
                            onClick={() => onFit('height')}
                            aria-label="Ajustar a la altura"
                            title="Página completa"
                        >
                            <MoveVertical className="h-3.5 w-3.5" />
                        </Button>
                        <Button
                            variant={fit === 'width' ? 'secondary' : 'ghost'}
                            size="icon"
                            className="h-7 w-7"
                            onClick={() => onFit('width')}
                            aria-label="Ajustar al ancho"
                            title="Ajustar al ancho"
                        >
                            <MoveHorizontal className="h-3.5 w-3.5" />
                        </Button>
                        <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => onZoom(-1)} aria-label="Alejar">
                            <Minus className="h-3.5 w-3.5" />
                        </Button>
                        <span className="w-12 text-center font-mono">{fit === 'custom' ? `${zoom}%` : fit === 'height' ? 'alto' : 'ancho'}</span>
                        <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => onZoom(1)} aria-label="Acercar">
                            <Plus className="h-3.5 w-3.5" />
                        </Button>
                        <span className="text-muted-foreground ml-2 hidden lg:inline">Ctrl + rueda para zoom · arrastra para moverte</span>
                    </div>
                )}
            </div>
        </div>
    );
}
