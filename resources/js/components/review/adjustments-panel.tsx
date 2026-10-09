import { TypeChips } from '@/components/review/type-chips';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { barClass, textToneClass, useCountdown } from '@/hooks/use-clock';
import { fmtDate, fmtDur, fmtReloj } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type AdjustmentDto, type AdjustmentTypeValue, type PageDetail, type TypeOption } from '@/types';
import { Link, router } from '@inertiajs/react';
import { Check, ExternalLink, FileText, KanbanSquare, MessageSquare, Pencil, Send, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface Props {
    page: PageDetail;
    types: TypeOption[];
    highlight: number | null;
    onHighlight: (id: number | null) => void;
    frozen: boolean;
}

type Filter = 'todos' | 'open' | 'resolved';

/** The side list: adjustments, approvals and defects on one timeline, newest first. */
export default function AdjustmentsPanel({ page, types, highlight, onHighlight, frozen }: Props) {
    const [estado, setEstado] = useState<Filter>('todos');
    const [tipo, setTipo] = useState('todos');

    let items = page.adjustments;
    if (estado !== 'todos') items = items.filter((a) => a.status === estado);
    if (tipo !== 'todos') items = items.filter((a) => a.types.includes(tipo as AdjustmentTypeValue));
    const list = [...items].sort((a, b) => b.date.localeCompare(a.date));

    const timeline: { ts: string; node: React.ReactNode }[] = list.map((a) => ({
        ts: a.date,
        node: (
            <AdjustmentItem
                key={`a${a.id}`}
                a={a}
                types={types}
                frozen={frozen}
                active={highlight === a.id}
                onClick={() => onHighlight(highlight === a.id ? null : a.id)}
            />
        ),
    }));
    if (estado === 'todos' && tipo === 'todos') {
        page.approvals.forEach((ap, i) =>
            timeline.push({
                ts: ap.date,
                node: (
                    <div key={`ap${i}`} className="bg-card rounded-xl border p-3 text-sm">
                        <div className="mb-1 flex items-center justify-between">
                            <span className="font-medium">
                                {ap.phase === 'print' ? 'Aprobación a imprenta' : 'Validación'} {ap.role_label} · v{ap.version}
                            </span>
                            <Badge>{ap.phase === 'print' ? 'A imprenta' : 'Validada'}</Badge>
                        </div>
                        <p className="text-muted-foreground text-xs">
                            {ap.phase === 'print' ? 'Aprobación a imprenta' : 'Validación'} de {ap.role_full} registrada por {ap.by} ·{' '}
                            {fmtDate(ap.date)}
                            {ap.delay > 0 && <span className="text-red-600 dark:text-red-400"> · {fmtDur(ap.delay)} tarde</span>}
                        </p>
                    </div>
                ),
            }),
        );
        page.defects.forEach((d, i) =>
            timeline.push({
                ts: d.date,
                node: (
                    <div key={`d${i}`} className="rounded-xl border border-red-200 bg-red-50 p-3 text-sm dark:border-red-900 dark:bg-red-950/40">
                        <div className="mb-1 flex items-center justify-between">
                            <span className="font-medium">Defecto post-producción · v{d.version}</span>
                            <Badge variant="destructive">Reabierta</Badge>
                        </div>
                        <p>{d.reason}</p>
                        <p className="text-muted-foreground mt-1 text-xs">
                            {d.by} · {d.role_label} · {fmtDate(d.date)}
                        </p>
                    </div>
                ),
            }),
        );
    }
    timeline.sort((p, q) => q.ts.localeCompare(p.ts));

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center gap-2">
                <div className="bg-muted flex rounded-lg p-0.5 text-xs">
                    {(
                        [
                            ['todos', 'Todos'],
                            ['open', 'Pendientes'],
                            ['resolved', 'Resueltos'],
                        ] as [Filter, string][]
                    ).map(([k, label]) => (
                        <button
                            key={k}
                            type="button"
                            onClick={() => setEstado(k)}
                            className={cn('rounded-md px-3 py-1', estado === k && 'bg-background shadow-sm')}
                        >
                            {label}
                        </button>
                    ))}
                </div>
                <Select value={tipo} onValueChange={setTipo}>
                    <SelectTrigger className="h-8 w-40 text-xs">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="todos">Todos los tipos</SelectItem>
                        {types.map((t) => (
                            <SelectItem key={t.value} value={t.value}>
                                {t.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            {timeline.length === 0 && (
                <p className="text-muted-foreground rounded-xl border border-dashed p-6 text-center text-sm">Sin ajustes en esta página.</p>
            )}
            {timeline.map((t) => t.node)}
        </div>
    );
}

function AdjustmentItem({
    a,
    types,
    frozen,
    active,
    onClick,
}: {
    a: AdjustmentDto;
    types: TypeOption[];
    frozen: boolean;
    active: boolean;
    onClick: () => void;
}) {
    const open = a.status === 'open';
    const live = useCountdown(a.remaining, frozen || !open);
    const card = useRef<HTMLDivElement>(null);
    useEffect(() => {
        if (active) card.current?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, [active]);
    const [reply, setReply] = useState<'none' | 'reply' | 'validate' | 'edit'>('none');
    const [text, setText] = useState('');
    const [editTypes, setEditTypes] = useState<AdjustmentTypeValue[]>(a.types);
    const [busy, setBusy] = useState(false);

    const withdraw = () => {
        if (confirm('¿Retirar este ajuste? Desaparece de la página y del reporte.')) {
            setBusy(true);
            router.delete(route('adjustments.destroy', a.id), { preserveScroll: true, onFinish: () => setBusy(false) });
        }
    };

    const post = (url: string, data: Record<string, string>, done?: () => void) => {
        setBusy(true);
        router.post(url, data, {
            preserveScroll: true,
            onFinish: () => {
                setBusy(false);
                done?.();
            },
        });
    };

    return (
        <div
            ref={card}
            className={cn(
                'bg-card rounded-xl border p-3 text-sm',
                open && 'border-red-200 dark:border-red-900',
                a.is_defect && 'border-l-4 border-l-red-500',
                active && 'ring-primary/40 ring-2',
            )}
        >
            <button type="button" onClick={onClick} title="Ver la región en la página" className="block w-full text-left">
                <span className="mb-1 flex items-center justify-between">
                    <span className="font-medium">
                        #{a.index} · {a.type_label}
                        {a.is_defect && <span className="ml-2 text-xs text-red-600 dark:text-red-400">PPD</span>}
                    </span>
                    <Badge variant={open ? 'destructive' : 'secondary'}>{open ? `En ${a.stage_label}` : `Resuelto en v${a.resolved_in}`}</Badge>
                </span>
                <span className="block">{a.text}</span>
            </button>
            <p className="text-muted-foreground mt-1 text-xs">
                {a.author} · {fmtDate(a.date)} · v{a.version} · Ruta: {a.trace.join(' › ')}
            </p>
            {a.attachments.length > 0 && <Attachments items={a.attachments} />}

            {open && a.queued && (
                <p className="mt-2 flex items-center gap-1.5 text-xs text-neutral-600 dark:text-neutral-400">
                    <span className="inline-block size-2 rounded-full bg-neutral-400" />
                    En cola · el cronómetro de Ogilvy arranca cuando se resuelvan los ajustes de tareas más urgentes
                    {a.task_priority ? ` (esta tarea es prioridad ${a.task_priority.toLowerCase()})` : ''}
                </p>
            )}

            {open && live !== null && (
                <div className="mt-2">
                    <div className="flex items-center justify-between text-xs">
                        <span className="text-muted-foreground">
                            {a.where} · SLA {fmtDur(a.sla)}
                        </span>
                        <span className={cn('font-mono font-medium', textToneClass(a.state, frozen))}>{fmtReloj(live)}</span>
                    </div>
                    <div className="bg-muted mt-1 h-1 w-full overflow-hidden rounded">
                        <div className={cn('h-full', barClass(a.state, frozen))} style={{ width: `${Math.max(1.5, a.consumed_pct * 100)}%` }} />
                    </div>
                </div>
            )}

            {a.validations.length > 0 && (
                <ul className="mt-2 grid gap-1 border-l-2 pl-3 text-xs">
                    {a.validations.map((v, i) => (
                        <li key={i}>
                            <span className="text-foreground">{v.note ?? 'Validado sin comentarios adicionales.'}</span>
                            <span className="text-muted-foreground">
                                {' '}
                                · {v.role_label} · {v.by} · {fmtDate(v.date)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
            {a.replies.length > 0 && (
                <ul className="mt-2 grid gap-1 text-xs">
                    {a.replies.map((r, i) => (
                        <li key={i} className="bg-muted rounded-md px-2 py-1">
                            {r.text}{' '}
                            <span className="text-muted-foreground">
                                · {r.by} · {fmtDate(r.date)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}

            {(a.can.validate || a.can.resolve || a.can.reply || a.can.update || a.can.delete) && (
                <div className="mt-3 flex flex-wrap gap-2">
                    {a.can.validate && (
                        <Button size="sm" onClick={() => setReply(reply === 'validate' ? 'none' : 'validate')} disabled={busy}>
                            <Check className="h-4 w-4" />
                            Validar y enviar
                        </Button>
                    )}
                    {a.can.resolve && (
                        <Button size="sm" onClick={() => post(route('adjustments.resolve', a.id), {})} disabled={busy}>
                            <Check className="h-4 w-4" />
                            Marcar resuelto
                        </Button>
                    )}
                    {a.can.reply && (
                        <Button size="sm" variant="ghost" onClick={() => setReply(reply === 'reply' ? 'none' : 'reply')} disabled={busy}>
                            <MessageSquare className="h-4 w-4" />
                            Responder
                        </Button>
                    )}
                    {a.board && (
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={a.board.url} title="Abrir en el tablero de Ogilvy">
                                <KanbanSquare className="h-3.5 w-3.5" />
                                {a.board.status === 'pending'
                                    ? 'Por revisar en el tablero'
                                    : a.board.status === 'approved'
                                      ? 'Entregable en el tablero'
                                      : 'Descartado en el tablero'}
                            </Link>
                        </Button>
                    )}
                    {a.can.update && (
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => {
                                setEditTypes(a.types);
                                setText(a.text);
                                setReply(reply === 'edit' ? 'none' : 'edit');
                            }}
                            disabled={busy}
                        >
                            <Pencil className="h-4 w-4" />
                            Editar
                        </Button>
                    )}
                    {a.can.delete && (
                        <Button size="sm" variant="ghost" className="text-red-600 dark:text-red-400" onClick={withdraw} disabled={busy}>
                            <Trash2 className="h-4 w-4" />
                            Retirar
                        </Button>
                    )}
                </div>
            )}
            {reply !== 'none' && (
                <div className="mt-2 grid gap-2">
                    {reply === 'edit' && <TypeChips types={types} value={editTypes} onChange={setEditTypes} />}
                    <Textarea
                        rows={2}
                        autoFocus
                        value={text}
                        onChange={(e) => setText(e.target.value)}
                        placeholder={
                            reply === 'validate'
                                ? 'Nota para el siguiente eslabón (opcional)'
                                : reply === 'edit'
                                  ? 'Qué hay que cambiar'
                                  : 'Tu respuesta'
                        }
                        className="text-sm"
                    />
                    <div className="flex justify-end gap-2">
                        <Button size="sm" variant="ghost" onClick={() => setReply('none')}>
                            Cancelar
                        </Button>
                        <Button
                            size="sm"
                            disabled={busy || (reply !== 'validate' && !text.trim())}
                            onClick={() => {
                                const done = () => {
                                    setReply('none');
                                    setText('');
                                };
                                if (reply === 'edit') {
                                    setBusy(true);
                                    router.put(
                                        route('adjustments.update', a.id),
                                        { types: editTypes, text },
                                        {
                                            preserveScroll: true,
                                            onFinish: () => {
                                                setBusy(false);
                                                done();
                                            },
                                        },
                                    );
                                    return;
                                }
                                post(
                                    reply === 'validate' ? route('adjustments.validate', a.id) : route('adjustments.reply', a.id),
                                    reply === 'validate' ? { note: text } : { text },
                                    done,
                                );
                            }}
                        >
                            <Send className="h-4 w-4" />
                            {reply === 'validate' ? 'Validar ajuste' : reply === 'edit' ? 'Guardar' : 'Enviar'}
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}

/** What the client attached: image thumbnails, PDF chips and links, all opening in a new tab. */
function Attachments({ items }: { items: AdjustmentDto['attachments'] }) {
    const images = items.filter((f) => f.image);
    const rest = items.filter((f) => !f.image);
    return (
        <div className="mt-2 space-y-1.5">
            {images.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                    {images.map((f) => (
                        <a
                            key={f.id}
                            href={f.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            title={f.name}
                            className="block overflow-hidden rounded border"
                        >
                            <img src={f.url} alt={f.name} className="h-16 w-16 object-cover" loading="lazy" />
                        </a>
                    ))}
                </div>
            )}
            {rest.length > 0 && (
                <div className="flex flex-wrap gap-1">
                    {rest.map((f) => (
                        <a
                            key={f.id}
                            href={f.url}
                            target="_blank"
                            rel="noopener noreferrer nofollow"
                            title={f.kind === 'link' ? f.url : f.name}
                            className="bg-muted hover:bg-muted/70 inline-flex max-w-full items-center gap-1 rounded px-1.5 py-0.5 text-xs"
                        >
                            {f.kind === 'link' ? <ExternalLink className="size-3 shrink-0" /> : <FileText className="size-3 shrink-0" />}
                            <span className="truncate">{f.kind === 'link' ? f.url.replace(/^https?:\/\//, '') : f.name}</span>
                        </a>
                    ))}
                </div>
            )}
        </div>
    );
}
