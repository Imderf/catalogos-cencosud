import { Input } from '@/components/ui/input';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

/**
 * Date that saves when the person is done, not on every keystroke: the native picker fires partial
 * values (a half-typed year) that would otherwise hit the server and reset the field.
 */
const MIN = '2000-01-01';
const MAX = '2099-12-31';

export default function DateField({
    value,
    onCommit,
    disabled,
    className,
}: {
    value: string | null;
    onCommit: (v: string | null) => void;
    disabled?: boolean;
    className?: string;
}) {
    const [local, setLocal] = useState(value ?? '');
    useEffect(() => setLocal(value ?? ''), [value]);
    /* Four-digit years between 2000 and 2099: the native picker happily takes a ten-digit year otherwise. */
    const valid = local === '' || (/^\d{4}-\d{2}-\d{2}$/.test(local) && local >= MIN && local <= MAX);
    const commit = () => {
        if (!valid) {
            setLocal(value ?? '');
            toast.error('Fecha no válida: usa un año entre 2000 y 2099.');
            return;
        }
        if ((local || null) !== (value || null)) onCommit(local || null);
    };
    return (
        <Input
            type="date"
            min={MIN}
            max={MAX}
            className={className}
            value={local}
            disabled={disabled}
            onChange={(e) => setLocal(e.target.value)}
            onBlur={commit}
            onKeyDown={(e) => e.key === 'Enter' && (e.target as HTMLInputElement).blur()}
        />
    );
}
