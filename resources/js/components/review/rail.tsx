import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { type LucideIcon, X } from 'lucide-react';
import { type ReactNode } from 'react';

export interface RailItem {
    key: string;
    icon: LucideIcon;
    label: string;
    badge?: number;
    dot?: 'ok' | 'late' | 'off' | null;
}

/** Vertical icon bar on the right edge, like a document viewer. One panel open at a time. */
export function Rail({ items, active, onSelect }: { items: RailItem[]; active: string | null; onSelect: (key: string | null) => void }) {
    return (
        <nav className="bg-card absolute inset-0 z-20 flex flex-col items-center gap-1 border-l py-3" aria-label="Paneles de revisión">
            {items.map((it) => {
                const on = active === it.key;
                return (
                    <Tooltip key={it.key}>
                        <TooltipTrigger asChild>
                            <button
                                type="button"
                                onClick={() => onSelect(on ? null : it.key)}
                                aria-label={it.label}
                                aria-pressed={on}
                                className={cn(
                                    'relative flex h-10 w-10 items-center justify-center rounded-lg transition-colors',
                                    on ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                )}
                            >
                                <it.icon className="h-5 w-5" />
                                {it.badge !== undefined && it.badge > 0 && (
                                    <span className="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white">
                                        {it.badge}
                                    </span>
                                )}
                                {it.dot && (
                                    <span
                                        className={cn(
                                            'absolute right-1 bottom-1 h-2 w-2 rounded-full ring-2',
                                            on ? 'ring-primary' : 'ring-card',
                                            it.dot === 'late' ? 'bg-red-500' : it.dot === 'ok' ? 'bg-emerald-500' : 'bg-neutral-400',
                                        )}
                                    />
                                )}
                            </button>
                        </TooltipTrigger>
                        <TooltipContent side="left">{it.label}</TooltipContent>
                    </Tooltip>
                );
            })}
        </nav>
    );
}

/** Floating panel next to the rail; the page underneath keeps its size. */
export function Drawer({ title, onClose, children, wide = false }: { title: string; onClose: () => void; children: ReactNode; wide?: boolean }) {
    return (
        <aside
            className={cn(
                'bg-card absolute inset-y-0 right-14 z-10 flex flex-col border-l shadow-xl',
                wide ? 'w-[520px] max-w-[85vw]' : 'w-[400px] max-w-[85vw]',
            )}
        >
            <div className="flex items-center justify-between border-b px-4 py-2.5">
                <h3 className="text-sm font-semibold">{title}</h3>
                <button
                    type="button"
                    onClick={onClose}
                    className="text-muted-foreground hover:text-foreground rounded-md p-1"
                    aria-label="Cerrar panel"
                >
                    <X className="h-4 w-4" />
                </button>
            </div>
            <div className="flex-1 overflow-y-auto p-4">{children}</div>
        </aside>
    );
}
