import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

/** Turns the server's one-shot flash into a toast; pages that use it skip the inline banner. */
export function useFlashToast() {
    const { flash } = usePage<SharedData>().props;
    const key = (flash as { key?: string | null })?.key ?? null;
    useEffect(() => {
        if (!key) return;
        if (flash?.error) toast.error(flash.error);
        else if (flash?.success) toast.success(flash.success);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [key]);
}

/** First validation message of an Inertia error bag, for toasts. */
export function firstError(errors: Record<string, string>): string {
    return Object.values(errors)[0] ?? 'No se pudo completar la acción.';
}
