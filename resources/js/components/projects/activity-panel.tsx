import { PersonAvatar } from '@/components/projects/person-avatar';
import { fmtAgo } from '@/lib/format';
import { type ActivityLine } from '@/types/projects';
import { Loader2, X } from 'lucide-react';

/** Right-hand panel with the board's whole history; opened from the header. */
export default function ActivityPanel({
    activity,
    onClose,
    onOpenCard,
}: {
    activity: ActivityLine[] | undefined;
    onClose: () => void;
    onOpenCard: (id: number) => void;
}) {
    return (
        <aside className="bg-card flex w-80 shrink-0 flex-col border-l">
            <div className="flex h-12 items-center justify-between border-b px-4">
                <h3 className="text-sm font-semibold">Actividad</h3>
                <button type="button" onClick={onClose} className="text-muted-foreground hover:bg-muted rounded p-1" aria-label="Cerrar actividad">
                    <X className="h-4 w-4" />
                </button>
            </div>
            <div className="flex-1 overflow-y-auto p-4">
                {!activity ? (
                    <div className="text-muted-foreground flex justify-center py-10">
                        <Loader2 className="h-5 w-5 animate-spin" />
                    </div>
                ) : activity.length === 0 ? (
                    <p className="text-muted-foreground text-sm">Sin actividad todavía.</p>
                ) : (
                    <ul className="space-y-4">
                        {activity.map((a) => (
                            <li key={a.id} className="flex gap-2.5 text-xs">
                                {a.actor ? <PersonAvatar person={a.actor} /> : <span className="bg-muted h-6 w-6 rounded-full" />}
                                <div className="min-w-0">
                                    <p>
                                        <span className="font-medium">{a.actor?.name ?? 'Sistema'}</span> {a.text}
                                    </p>
                                    {a.card && (
                                        <button
                                            type="button"
                                            onClick={() => onOpenCard(a.card!.id)}
                                            className="text-primary mt-0.5 block max-w-full truncate hover:underline"
                                        >
                                            {a.card.title}
                                        </button>
                                    )}
                                    <p className="text-muted-foreground">{fmtAgo(a.at)}</p>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </aside>
    );
}
