import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { textToneClass, useCountdown } from '@/hooks/use-clock';
import AppLayout from '@/layouts/app-layout';
import { fmtAgo, fmtReloj } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type ClockState, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

interface Pending {
    kind: 'approval' | 'adjustment';
    title: string;
    detail: string;
    remaining: number | null;
    state: ClockState;
    url: string;
}

interface Recent {
    id: string;
    kind: string;
    text: string;
    actor: string;
    catalog_name: string;
    page_number: number | null;
    url: string;
    read: boolean;
    at: string;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notificaciones', href: '/notificaciones' }];

export default function NotificationsIndex({ pending, recent, role_label }: { pending: Pending[]; recent: Recent[]; role_label: string }) {
    const { workday } = usePage<SharedData>().props;
    const frozen = !(workday?.open ?? true);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notificaciones" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Notificaciones"
                    description={
                        pending.length ? `Esto espera por ${role_label} ahora mismo.` : 'No hay nada esperando por tu equipo en este momento.'
                    }
                />

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="grid content-start gap-3">
                        <h3 className="text-sm font-semibold">Lo que espera por ti</h3>
                        {pending.length === 0 && (
                            <p className="text-muted-foreground rounded-xl border border-dashed p-6 text-center text-sm">Nada pendiente.</p>
                        )}
                        {pending.map((p, i) => (
                            <PendingRow key={i} item={p} frozen={frozen} />
                        ))}
                    </section>

                    <section className="grid content-start gap-3">
                        <h3 className="text-sm font-semibold">Actividad reciente</h3>
                        {recent.length === 0 && (
                            <p className="text-muted-foreground rounded-xl border border-dashed p-6 text-center text-sm">Sin actividad todavía.</p>
                        )}
                        <ol className="grid gap-2">
                            {recent.map((r) => (
                                <li key={r.id}>
                                    <Link
                                        href={r.url}
                                        className={cn(
                                            'bg-card hover:bg-muted/50 flex items-start gap-3 rounded-xl border p-3 text-sm',
                                            !r.read && 'border-primary/40',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'mt-1.5 h-2 w-2 shrink-0 rounded-full',
                                                ['ajuste', 'defecto', 'vencido', 'urgente'].includes(r.kind)
                                                    ? 'bg-red-500'
                                                    : ['aprobacion', 'version'].includes(r.kind)
                                                      ? 'bg-foreground'
                                                      : 'bg-neutral-300',
                                            )}
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="block">{r.text}</span>
                                            <span className="text-muted-foreground text-xs">
                                                {r.actor} · {fmtAgo(r.at)}
                                                {!r.read && (
                                                    <Badge variant="outline" className="ml-2 text-[10px]">
                                                        Nueva
                                                    </Badge>
                                                )}
                                            </span>
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ol>
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}

function PendingRow({ item, frozen }: { item: Pending; frozen: boolean }) {
    const live = useCountdown(item.remaining, frozen);
    return (
        <Link href={item.url} className="bg-card hover:bg-muted/50 flex items-center gap-3 rounded-xl border p-3 text-sm">
            <div className="min-w-0 flex-1">
                <p className="font-medium">{item.title}</p>
                <p className="text-muted-foreground truncate text-xs">{item.detail}</p>
            </div>
            {live !== null && <span className={cn('font-mono text-xs font-medium', textToneClass(item.state, frozen))}>{fmtReloj(live)}</span>}
            <ArrowRight className="text-muted-foreground h-4 w-4" />
        </Link>
    );
}
