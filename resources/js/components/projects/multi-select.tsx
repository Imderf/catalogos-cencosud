import { PersonAvatar } from '@/components/projects/person-avatar';
import { cn } from '@/lib/utils';
import { type Person } from '@/types/projects';
import { ChevronDown, Plus, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export interface MultiOption {
    id: number | string;
    label: string;
    hint?: string;
    color?: string;
    avatar?: Person;
}

/** Dropdown with search to pick several items; the chosen ones show as chips (assignees, labels, options). */
export function MultiSelect<T extends number | string>({
    options,
    value,
    onChange,
    placeholder = 'Agregar…',
    emptyText = 'Sin opciones',
    disabled,
}: {
    options: MultiOption[];
    value: T[];
    onChange: (ids: T[]) => void;
    placeholder?: string;
    emptyText?: string;
    disabled?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [q, setQ] = useState('');
    const ref = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    useEffect(() => {
        if (!open) return;
        inputRef.current?.focus();
        const h = (e: MouseEvent) => !ref.current?.contains(e.target as Node) && setOpen(false);
        document.addEventListener('mousedown', h);
        return () => document.removeEventListener('mousedown', h);
    }, [open]);
    const selected = value.map((id) => options.find((o) => o.id === id)).filter(Boolean) as MultiOption[];
    const available = options.filter((o) => !value.includes(o.id as T) && o.label.toLowerCase().includes(q.toLowerCase()));
    const add = (o: MultiOption) => {
        onChange([...value, o.id as T]);
        setQ('');
    };
    return (
        <div ref={ref} className="relative">
            {selected.length > 0 && (
                <div className="mb-1.5 flex flex-wrap gap-1.5">
                    {selected.map((o) => (
                        <span
                            key={o.id}
                            style={o.color ? { backgroundColor: o.color, color: '#fff' } : undefined}
                            className={cn(
                                'inline-flex max-w-full items-center gap-1 rounded-full py-0.5 pr-1 pl-1 text-xs font-medium',
                                !o.color && 'bg-card text-foreground ring-border ring-1',
                            )}
                        >
                            {o.avatar && <PersonAvatar person={o.avatar} className="h-5 w-5 text-[9px]" />}
                            <span className={cn('truncate', !o.avatar && 'pl-1')}>{o.label}</span>
                            {!disabled && (
                                <button
                                    type="button"
                                    onClick={() => onChange(value.filter((v) => v !== o.id))}
                                    className={cn(
                                        'rounded-full p-0.5',
                                        o.color ? 'hover:bg-black/20' : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                    )}
                                    aria-label={`Quitar ${o.label}`}
                                >
                                    <X className="h-3 w-3" />
                                </button>
                            )}
                        </span>
                    ))}
                </div>
            )}
            {!disabled && (
                <button
                    type="button"
                    onClick={() => {
                        setOpen((v) => !v);
                        setQ('');
                    }}
                    className="text-muted-foreground hover:border-foreground/40 hover:text-foreground bg-card flex h-8 w-full items-center gap-1.5 rounded-md border border-dashed px-2.5 text-xs"
                >
                    <Plus className="h-3.5 w-3.5" />
                    <span className="flex-1 text-left">{placeholder}</span>
                    <ChevronDown className="h-3.5 w-3.5" />
                </button>
            )}
            {open && (
                <div className="bg-popover text-popover-foreground absolute left-0 z-40 mt-1 w-full min-w-56 overflow-hidden rounded-lg border shadow-lg">
                    <div className="flex items-center gap-1.5 border-b px-2.5 py-1.5">
                        <Search className="text-muted-foreground h-3.5 w-3.5" />
                        <input
                            ref={inputRef}
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Buscar…"
                            className="placeholder:text-muted-foreground w-full bg-transparent text-sm outline-none"
                            onKeyDown={(e) => {
                                if (e.key === 'Escape') setOpen(false);
                                if (e.key === 'Enter' && available[0]) {
                                    e.preventDefault();
                                    add(available[0]);
                                }
                            }}
                        />
                    </div>
                    <ul className="max-h-56 overflow-y-auto py-1">
                        {available.length === 0 && <li className="text-muted-foreground px-3 py-2 text-xs">{emptyText}</li>}
                        {available.map((o) => (
                            <li key={o.id}>
                                <button
                                    type="button"
                                    onClick={() => add(o)}
                                    className="hover:bg-accent flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm"
                                >
                                    {o.avatar && <PersonAvatar person={o.avatar} className="h-5 w-5 text-[9px]" />}
                                    {o.color && <span className="h-3 w-3 rounded-full" style={{ backgroundColor: o.color }} />}
                                    <span className="flex-1 truncate">{o.label}</span>
                                    {o.hint && <span className="text-muted-foreground truncate text-xs">{o.hint}</span>}
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}
