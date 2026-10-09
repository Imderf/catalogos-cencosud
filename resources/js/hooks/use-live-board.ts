import { playNotificationSound } from '@/lib/notification-sound';
import { type SharedData } from '@/types';
import { type Person } from '@/types/projects';
import { router, usePage } from '@inertiajs/react';
import { useEcho, useEchoNotification, useEchoPresence } from '@laravel/echo-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';

interface ActivityPayload {
    actor: { id: number; name: string } | null;
    card: { id: number; title: string };
    text: string;
}

/**
 * One board, live: refreshes on changes, shows teammates' actions as toasts and tracks who is online.
 * Mount only when Echo is configured.
 */
export function useLiveBoard(projectId: number): { online: Person[] } {
    const { auth } = usePage<SharedData>().props;
    const timer = useRef<number | null>(null);
    const [online, setOnline] = useState<Person[]>([]);

    useEcho<{ project: number; kind: string }>(`project.${projectId}`, '.board.changed', () => {
        if (timer.current) window.clearTimeout(timer.current);
        timer.current = window.setTimeout(() => router.reload({ only: ['project', 'card', 'subtask'] }), 300);
    });
    useEcho<ActivityPayload>(`project.${projectId}`, '.activity.new', (a) => {
        if (a.actor && a.actor.id !== auth.user.id) toast.info(`${a.actor.name} ${a.text}`, { description: a.card.title });
    });

    const presence = useEchoPresence<unknown>(`project.${projectId}`);
    const bound = useRef(false);
    const ch = presence.channel();
    if (ch && !bound.current) {
        bound.current = true;
        const asPerson = (u: { id: number; name: string }): Person => ({
            id: u.id,
            name: u.name,
            initials: u.name
                .split(' ')
                .slice(0, 2)
                .map((w) => w[0])
                .join(''),
        });
        ch.here((users: { id: number; name: string }[]) => setOnline(users.map(asPerson)))
            .joining((u: { id: number; name: string }) => setOnline((o) => (o.some((x) => x.id === u.id) ? o : [...o, asPerson(u)])))
            .leaving((u: { id: number; name: string }) => setOnline((o) => o.filter((x) => x.id !== u.id)));
    }

    return { online };
}

/** Personal channel: a mention or an assignment pops up wherever the person is. */
export function useLiveNotifications() {
    const { auth } = usePage<SharedData>().props;
    useEchoNotification<{ text?: string; url?: string; project_name?: string }>(`App.Models.User.${auth.user.id}`, (n) => {
        if (n.text) {
            toast(n.text, {
                description: n.project_name,
                action: n.url ? { label: 'Abrir', onClick: () => router.visit(n.url as string) } : undefined,
            });
            if (auth.user.notification_sound ?? true) playNotificationSound();
            router.reload({ only: ['unread'] });
        }
    });
}
