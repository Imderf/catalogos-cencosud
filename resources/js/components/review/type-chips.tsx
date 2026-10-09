import { cn } from '@/lib/utils';
import { type AdjustmentTypeValue, type TypeOption } from '@/types';

/** Kinds of adjustment as toggle chips: one or several at once (a price and an image, say). */
export function TypeChips({
    types,
    value,
    onChange,
    className,
}: {
    types: TypeOption[];
    value: AdjustmentTypeValue[];
    onChange: (next: AdjustmentTypeValue[]) => void;
    className?: string;
}) {
    const toggle = (v: AdjustmentTypeValue) => onChange(value.includes(v) ? value.filter((x) => x !== v) : [...value, v]);
    return (
        <div role="group" aria-label="Tipo de ajuste" className={cn('flex flex-wrap gap-1', className)}>
            {types.map((t) => {
                const on = value.includes(t.value);
                return (
                    <button
                        key={t.value}
                        type="button"
                        role="checkbox"
                        aria-checked={on}
                        onClick={() => toggle(t.value)}
                        className={cn(
                            'rounded-full border px-2 py-0.5 text-xs transition-colors',
                            on ? 'border-primary bg-primary text-primary-foreground' : 'bg-card hover:bg-muted text-foreground',
                        )}
                    >
                        {t.label}
                    </button>
                );
            })}
        </div>
    );
}
