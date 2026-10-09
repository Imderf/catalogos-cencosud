import { cn } from '@/lib/utils';
import { type StatusOption, type SubtaskStatus } from '@/types/projects';
import { CheckCircle2, Circle, CircleDot, Clock, Search } from 'lucide-react';
import { type ReactNode, useEffect, useRef, useState } from 'react';

export const STATUS_ICON: Record<SubtaskStatus, { icon: typeof Circle; className: string }> = {
    pending: { icon: Circle, className: 'text-muted-foreground' },
    in_progress: { icon: Clock, className: 'text-sky-600' },
    adjustments: { icon: CircleDot, className: 'text-amber-600' },
    completed: { icon: CheckCircle2, className: 'text-emerald-600' },
};

export function StatusIcon({ status, className }: { status: SubtaskStatus; className?: string }) {
    const { icon: Icon, className: tone } = STATUS_ICON[status];
    return <Icon className={cn('h-4 w-4 shrink-0', tone, className)} />;
}

/** Searchable status list, open and closed states apart, like the original board's picker. */
export default function StatusPicker({
    value,
    options,
    onChange,
    disabled,
    children,
    align = 'left',
}: {
    value: SubtaskStatus;
    options: StatusOption[];
    onChange: (s: SubtaskStatus) => void;
    disabled?: boolean;
    children: ReactNode;
    align?: 'left' | 'right';
}) {
    const [open, setOpen] = useState(false);
    const [q, setQ] = useState('');
    const ref = useRef<HTMLDivElement>(null);
    const input = useRef<HTMLInputElement>(null);
    useEffect(() => {
        if (!open) return;
        input.current?.focus();
        const h = (e: MouseEvent) => !ref.current?.contains(e.target as Node) && setOpen(false);
        document.addEventListener('mousedown', h);
        return () => document.removeEventListener('mousedown', h);
    }, [open]);
    const shown = options.filter((o) => o.label.toLowerCase().includes(q.toLowerCase()));
    const pick = (s: SubtaskStatus) => {
        onChange(s);
        setOpen(false);
        setQ('');
    };
    const group = (title: string, closed: boolean) => {
        const items = shown.filter((o) => o.closed === closed);
        if (!items.length) return null;
        return (
            <div className="py-1">
                <p className="text-muted-foreground px-2 pb-1 text-[10px] font-semibold tracking-wide uppercase">{title}</p>
                {items.map((o) => (
                    <button
                        key={o.value}
                        type="button"
                        onClick={() => pick(o.value)}
                        className={cn(
                            'hover:bg-accent flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm',
                            o.value === value && 'font-semibold',
                        )}
                    >
                        <StatusIcon status={o.value} />
                        <span className="flex-1">{o.label}</span>
                        {o.value === value && <span className="text-muted-foreground text-xs">✓</span>}
                    </button>
                ))}
            </div>
        );
    };
    return (
        <div ref={ref} className="relative inline-block">
            <button type="button" disabled={disabled} onClick={() => setOpen((v) => !v)} className="disabled:cursor-default">
                {children}
            </button>
            {open && (
                <div
                    className={cn(
                        'bg-popover text-popover-foreground absolute z-40 mt-1 w-56 rounded-lg border p-1 shadow-lg',
                        align === 'right' ? 'right-0' : 'left-0',
                    )}
                >
                    <div className="flex items-center gap-1.5 border-b px-2 py-1.5">
                        <Search className="text-muted-foreground h-3.5 w-3.5" />
                        <input
                            ref={input}
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Buscar…"
                            className="placeholder:text-muted-foreground w-full bg-transparent text-sm outline-none"
                            onKeyDown={(e) => {
                                if (e.key === 'Escape') setOpen(false);
                                if (e.key === 'Enter' && shown[0]) pick(shown[0].value);
                            }}
                        />
                    </div>
                    {group('Estados', false)}
                    {group('Cerrado', true)}
                </div>
            )}
        </div>
    );
}
