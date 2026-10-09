import { Alert, AlertDescription } from '@/components/ui/alert';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { CheckCircle2, CircleAlert } from 'lucide-react';

/** Shows the one-shot success/error message flashed by the server. */
export default function FlashMessage() {
    const { flash } = usePage<SharedData>().props;

    if (!flash?.success && !flash?.error) return null;

    const isError = !!flash.error;

    return (
        <Alert
            variant={isError ? 'destructive' : 'default'}
            className={isError ? '' : 'border-green-200 bg-green-50 text-green-900 dark:border-green-900 dark:bg-green-950/40 dark:text-green-100'}
        >
            {isError ? <CircleAlert className="h-4 w-4" /> : <CheckCircle2 className="h-4 w-4 text-green-700" />}
            <AlertDescription>{flash.error ?? flash.success}</AlertDescription>
        </Alert>
    );
}
