import { barClass, textToneClass, useCountdown } from '@/hooks/use-clock';
import { fmtDur, fmtReloj } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type ClockDto } from '@/types';

/** One bar over the canvas: traffic light, live HH:MM:SS and whose time it is. */
export default function ClockBar({ clock }: { clock: ClockDto | null }) {
    const live = useCountdown(clock?.remaining ?? null, clock?.frozen ?? true);
    if (!clock || live === null) return null;

    const late = !clock.frozen && (clock.state === 'vencido' || clock.state === 'urgente');
    const title =
        clock.type === 'aprobacion' ? (live < 0 ? 'Aprobación vencida hace' : 'El cliente tiene') : live < 0 ? 'Vencido hace' : 'Tiempo restante';

    return (
        <div
            className={cn(
                'flex items-center gap-4 rounded-xl border px-4 py-3',
                late
                    ? 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/40'
                    : clock.frozen
                      ? 'bg-muted'
                      : 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/40',
            )}
        >
            <span className={cn('h-2.5 w-2.5 shrink-0 rounded-full', clock.frozen ? 'bg-neutral-400' : late ? 'bg-red-500' : 'bg-emerald-500')} />
            <div className="min-w-0 flex-1">
                <div className="flex items-baseline justify-between gap-3">
                    <span className="text-foreground/80 text-xs font-medium">{title}</span>
                    <span className={cn('font-mono text-lg font-semibold tabular-nums', textToneClass(clock.state, clock.frozen))}>
                        {fmtReloj(live)}
                    </span>
                </div>
                <div className="bg-background/60 mt-1.5 h-1.5 w-full overflow-hidden rounded">
                    <div className={cn('h-full', barClass(clock.state, clock.frozen))} style={{ width: `${Math.max(1.5, clock.pct * 100)}%` }} />
                </div>
                <p className="text-foreground/80 mt-1 text-xs">
                    {clock.detail} · {fmtDur(clock.total)}
                    {clock.frozen && ' · en pausa fuera de horario'}
                </p>
            </div>
        </div>
    );
}
