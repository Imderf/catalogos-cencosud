import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { type FieldDto, type FieldValue, type Person } from '@/types/projects';
import { ImagePlus, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/** One custom field, rendered by type. Text saves on blur; the rest on change. */
export default function FieldInput({
    field,
    value,
    onChange,
    people,
    disabled,
    onUpload,
}: {
    field: FieldDto;
    value: FieldValue;
    onChange: (v: FieldValue) => void;
    people: Person[];
    disabled?: boolean;
    onUpload?: (file: File) => void;
}) {
    switch (field.type) {
        case 'textarea':
            return <CommitText value={value} onChange={onChange} disabled={disabled} long />;
        case 'image':
            return (
                <ImageField value={typeof value === 'string' ? value : null} onUpload={onUpload} onClear={() => onChange(null)} disabled={disabled} />
            );
        case 'checkbox':
            return (
                <label className="flex h-9 items-center gap-2 text-sm">
                    <Checkbox checked={value === true} onCheckedChange={(v) => onChange(v === true)} disabled={disabled} />
                    {value === true ? 'Sí' : 'No'}
                </label>
            );
        case 'number':
            return (
                <CommitText
                    value={value === null || value === undefined ? null : String(value)}
                    onChange={(v) => onChange(v === null ? null : Number(v))}
                    disabled={disabled}
                    number
                />
            );
        case 'date':
            return (
                <Input
                    type="date"
                    value={typeof value === 'string' ? value : ''}
                    onChange={(e) => onChange(e.target.value || null)}
                    disabled={disabled}
                />
            );
        case 'url':
            return <CommitText value={value} onChange={onChange} disabled={disabled} url />;
        case 'select':
            return (
                <Select
                    value={typeof value === 'string' ? value : 'none'}
                    onValueChange={(v) => onChange(v === 'none' ? null : v)}
                    disabled={disabled}
                >
                    <SelectTrigger>
                        <SelectValue placeholder="—" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">—</SelectItem>
                        {field.options.map((o) => (
                            <SelectItem key={o} value={o}>
                                {o}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            );
        case 'multiselect': {
            const chosen = Array.isArray(value) ? value : [];
            return (
                <div className="flex flex-wrap gap-1.5">
                    {field.options.map((o) => {
                        const on = chosen.includes(o);
                        return (
                            <button
                                key={o}
                                type="button"
                                disabled={disabled}
                                onClick={() => onChange(on ? chosen.filter((x) => x !== o) : [...chosen, o])}
                                className={cn(
                                    'rounded-full border px-2.5 py-0.5 text-xs font-medium transition',
                                    on ? 'bg-foreground text-background border-foreground' : 'hover:bg-muted',
                                )}
                            >
                                {o}
                            </button>
                        );
                    })}
                    {field.options.length === 0 && <span className="text-muted-foreground text-xs">Sin opciones.</span>}
                </div>
            );
        }
        case 'user':
            return (
                <Select
                    value={typeof value === 'number' ? String(value) : 'none'}
                    onValueChange={(v) => onChange(v === 'none' ? null : Number(v))}
                    disabled={disabled}
                >
                    <SelectTrigger>
                        <SelectValue placeholder="—" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">—</SelectItem>
                        {people.map((p) => (
                            <SelectItem key={p.id} value={String(p.id)}>
                                {p.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            );
        default:
            return <CommitText value={value} onChange={onChange} disabled={disabled} />;
    }
}

/** Text that saves on blur, not on every keystroke. */
function CommitText({
    value,
    onChange,
    disabled,
    long,
    number,
    url,
}: {
    value: FieldValue;
    onChange: (v: string | null) => void;
    disabled?: boolean;
    long?: boolean;
    number?: boolean;
    url?: boolean;
}) {
    const initial = typeof value === 'string' ? value : '';
    const [local, setLocal] = useState(initial);
    useEffect(() => setLocal(initial), [initial]);
    const commit = () => local !== initial && onChange(local || null);
    if (long)
        return (
            <Textarea
                rows={3}
                value={local}
                onChange={(e) => setLocal(e.target.value)}
                onBlur={commit}
                maxLength={5000}
                placeholder="Escribe aquí…"
                disabled={disabled}
            />
        );
    return (
        <Input
            type={number ? 'number' : url ? 'url' : 'text'}
            step={number ? 'any' : undefined}
            placeholder={url ? 'https://' : undefined}
            value={local}
            onChange={(e) => setLocal(e.target.value)}
            onBlur={commit}
            onKeyDown={(e) => e.key === 'Enter' && (e.target as HTMLInputElement).blur()}
            maxLength={1000}
            disabled={disabled}
        />
    );
}

/** Image field: upload, replace or remove one image. */
function ImageField({
    value,
    onUpload,
    onClear,
    disabled,
}: {
    value: string | null;
    onUpload?: (file: File) => void;
    onClear: () => void;
    disabled?: boolean;
}) {
    const ref = useRef<HTMLInputElement>(null);
    return (
        <div className="flex items-start gap-2">
            <input
                ref={ref}
                type="file"
                accept="image/*"
                hidden
                onChange={(e) => {
                    const f = e.target.files?.[0];
                    e.target.value = '';
                    if (f) onUpload?.(f);
                }}
            />
            {value ? (
                <a href={value} target="_blank" rel="noopener noreferrer" className="bg-muted block shrink-0 overflow-hidden rounded-md border">
                    <img src={value} alt="" className="h-24 w-32 object-cover hover:opacity-90" />
                </a>
            ) : (
                <button
                    type="button"
                    onClick={() => ref.current?.click()}
                    disabled={disabled}
                    className="text-muted-foreground hover:border-primary hover:text-primary bg-muted/40 flex h-24 w-32 shrink-0 flex-col items-center justify-center gap-1 rounded-md border border-dashed text-xs"
                >
                    <ImagePlus className="h-5 w-5" />
                    Subir imagen
                </button>
            )}
            {value && !disabled && (
                <div className="flex flex-col gap-1">
                    <Button type="button" size="sm" variant="secondary" onClick={() => ref.current?.click()}>
                        Cambiar
                    </Button>
                    <Button type="button" size="sm" variant="ghost" className="text-red-600" onClick={onClear}>
                        <Trash2 className="h-3.5 w-3.5" />
                        Quitar
                    </Button>
                </div>
            )}
        </div>
    );
}
