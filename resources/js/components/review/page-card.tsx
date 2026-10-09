import { Badge } from '@/components/ui/badge';
import { barClass, useCountdown } from '@/hooks/use-clock';
import { fmtReloj } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type PageCard as PageCardDto } from '@/types';
import { Link } from '@inertiajs/react';

export function locationBadgeVariant(p: PageCardDto): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (p.approved) return 'default';
    if (!p.submitted) return 'outline';
    if (p.is_mine && p.has_access) return 'destructive';
    if (p.location.role === 'agencia') return 'outline';
    return 'secondary';
}

export default function PageCard({ page, catalogSlug }: { page: PageCardDto; catalogSlug: string }) {
    const clock = page.clock;
    const live = useCountdown(clock?.remaining ?? null, clock?.frozen ?? true);

    return (
        <Link
            href={route('pages.show', [catalogSlug, page.number])}
            className={cn(
                'bg-card group flex flex-col overflow-hidden rounded-xl border shadow-sm transition-shadow hover:shadow-md',
                !page.has_access && 'opacity-70',
            )}
        >
            <div className="relative aspect-[360/640] overflow-hidden bg-neutral-100">
                {page.thumb_url && (
                    <div
                        className={cn('absolute inset-0 bg-contain bg-center bg-no-repeat', !page.has_access && 'grayscale')}
                        style={{ backgroundImage: `url('${page.thumb_url}')` }}
                    />
                )}
                <span className="absolute top-2 left-2 rounded bg-black/75 px-1.5 py-0.5 font-mono text-[11px] text-white">
                    Pág. {String(page.number).padStart(2, '0')}
                </span>
                {page.open > 0 && (
                    <span className="absolute top-2 right-2 rounded-full bg-red-600 px-2 py-0.5 text-[11px] font-semibold text-white">
                        {page.open}
                    </span>
                )}
                {!page.has_access && (
                    <span className="absolute inset-x-0 bottom-10 mx-auto w-fit rounded border bg-white/90 px-2 py-0.5 text-[11px] font-medium tracking-wide text-neutral-700 uppercase">
                        Otra categoría
                    </span>
                )}
                {clock && live !== null && (
                    <div className="absolute inset-x-0 bottom-0 bg-black/75 px-2 py-1.5 text-white">
                        <div className="flex items-center justify-between">
                            <span className="text-[10px] tracking-wide uppercase opacity-80">{clock.where}</span>
                            <span
                                className={cn(
                                    'font-mono text-xs',
                                    clock.frozen
                                        ? 'opacity-60'
                                        : clock.state === 'vencido' || clock.state === 'urgente'
                                          ? 'text-red-300'
                                          : 'text-emerald-300',
                                )}
                            >
                                {clock.overdue > 0 && clock.type === 'ajuste'
                                    ? `${clock.overdue} vencido${clock.overdue > 1 ? 's' : ''}`
                                    : fmtReloj(live)}
                            </span>
                        </div>
                        <div className="mt-1 h-1 w-full overflow-hidden rounded bg-white/20">
                            <div
                                className={cn('h-full', barClass(clock.state, clock.frozen))}
                                style={{ width: `${Math.max(1.5, clock.pct * 100)}%` }}
                            />
                        </div>
                    </div>
                )}
            </div>
            <div className="flex items-center justify-between gap-2 px-3 py-2">
                <div className="min-w-0">
                    <p className="truncate text-xs font-medium">{page.sectors_label}</p>
                    <p className="text-muted-foreground font-mono text-[11px]">v{page.version}</p>
                </div>
                <Badge variant={locationBadgeVariant(page)} className="shrink-0 text-[11px]">
                    {page.approved ? 'Aprobada a imprenta' : page.location.label}
                </Badge>
            </div>
        </Link>
    );
}
