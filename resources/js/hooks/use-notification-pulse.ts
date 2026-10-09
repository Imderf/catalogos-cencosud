import { armNotificationSound, playNotificationSound } from '@/lib/notification-sound';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';

type Pulse = { unread: number; latest: { id: string; text: string; url: string | null; where: string | null } | null };

const EVERY_MS = 45_000;

/**
 * Without websockets, the browser asks every 45 seconds (only while the tab is visible) whether something
 * new arrived; if so it shows the toast, plays the chime when the person wants it, and refreshes the bell.
 */
export function useNotificationPulse(enabled: boolean) {
    const { auth } = usePage<SharedData>().props;
    const sound = auth.user?.notification_sound ?? true;
    const seen = useRef<string | null>(null);
    const primed = useRef(false);

    useEffect(() => {
        if (!enabled) return;
        const disarm = armNotificationSound();
        let timer: number | undefined;
        let stopped = false;

        const tick = async () => {
            if (document.visibilityState !== 'visible') return;
            try {
                const res = await fetch(route('notifications.pulse'), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!res.ok) return;
                const p = (await res.json()) as Pulse;
                const id = p.latest?.id ?? null;
                if (!primed.current) {
                    primed.current = true; // what is already there is not "new"
                    seen.current = id;
                    return;
                }
                if (id && id !== seen.current) {
                    seen.current = id;
                    if (p.latest?.text) {
                        toast(p.latest.text, {
                            description: p.latest.where ?? undefined,
                            action: p.latest.url ? { label: 'Abrir', onClick: () => router.visit(p.latest!.url as string) } : undefined,
                        });
                    }
                    if (sound) playNotificationSound();
                    router.reload({ only: ['unread', 'inbox'] });
                }
            } catch {
                /* offline or signed out: try again on the next tick */
            }
        };
        const schedule = () => {
            if (stopped) return;
            timer = window.setTimeout(async () => {
                await tick();
                schedule();
            }, EVERY_MS);
        };
        void tick();
        schedule();
        const onVisible = () => document.visibilityState === 'visible' && void tick();
        document.addEventListener('visibilitychange', onVisible);
        return () => {
            stopped = true;
            window.clearTimeout(timer);
            document.removeEventListener('visibilitychange', onVisible);
            disarm();
        };
    }, [enabled, sound]);
}
