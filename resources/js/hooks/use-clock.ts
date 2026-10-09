import { type ClockState } from '@/types';
import { useEffect, useState } from 'react';

/**
 * Animates a server-computed countdown. The server hands over the remaining working
 * minutes at render time; the browser ticks it down once per second unless the
 * business day is closed (frozen). Whenever the server value changes, the tick restarts.
 */
export function useCountdown(remaining: number | null, frozen: boolean): number | null {
    const [value, setValue] = useState<number | null>(remaining);

    useEffect(() => {
        setValue(remaining);
        if (remaining === null || frozen) return;
        const started = Date.now();
        const id = setInterval(() => setValue(remaining - (Date.now() - started) / 60000), 1000);
        return () => clearInterval(id);
    }, [remaining, frozen]);

    return value;
}

export function toneOf(remaining: number | null, sla: number): ClockState {
    if (remaining === null) return 'espera';
    if (remaining < 0) return 'vencido';
    return remaining < sla * 0.25 ? 'urgente' : 'corriendo';
}

/** Tailwind classes for the progress track by state. */
export function barClass(state: ClockState, frozen = false): string {
    if (frozen) return 'bg-neutral-400';
    if (state === 'vencido' || state === 'urgente') return 'bg-red-500';
    if (state === 'espera') return 'bg-neutral-300';
    return 'bg-emerald-600';
}

export function textToneClass(state: ClockState, frozen = false): string {
    if (frozen) return 'text-muted-foreground';
    if (state === 'vencido' || state === 'urgente') return 'text-red-600 dark:text-red-400';
    if (state === 'espera') return 'text-muted-foreground';
    return 'text-emerald-700 dark:text-emerald-400';
}
