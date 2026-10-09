import { cn } from '@/lib/utils';
import { PALETTE } from '@/types/projects';

/** Eight swatches plus a free hex input; enough for projects, labels and clients. */
export default function ColorPicker({ value, onChange }: { value: string; onChange: (hex: string) => void }) {
    return (
        <div className="flex flex-wrap items-center gap-2">
            {PALETTE.map((c) => (
                <button
                    key={c}
                    type="button"
                    aria-label={c}
                    onClick={() => onChange(c)}
                    className={cn(
                        'h-7 w-7 rounded-full border-2 transition',
                        value.toLowerCase() === c.toLowerCase() ? 'border-foreground scale-110' : 'border-transparent',
                    )}
                    style={{ backgroundColor: c }}
                />
            ))}
            <input
                type="color"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="h-7 w-9 cursor-pointer rounded border bg-transparent p-0.5"
                aria-label="Otro color"
            />
        </div>
    );
}
