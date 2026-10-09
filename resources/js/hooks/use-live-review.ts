import { router } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { useRef } from 'react';

interface Change {
    kind: string;
    catalog: string;
    page: number | null;
}

/** Props every screen may carry; Inertia ignores the ones the current page lacks. */
const LIVE_PROPS = ['page', 'pages', 'catalog', 'catalogs', 'pending', 'recent', 'inbox', 'unread', 'round', 'workday'];

/**
 * Listens to the private review channel and refreshes the screen when anything moves.
 * Refreshes are debounced so a burst of events becomes one request.
 * Mount it only when Echo is configured (see LiveReview in the app layout).
 */
export function useLiveReview() {
    const timer = useRef<number | null>(null);

    useEcho<Change>('review', '.review.changed', () => {
        if (timer.current) window.clearTimeout(timer.current);
        timer.current = window.setTimeout(() => router.reload({ only: LIVE_PROPS }), 400);
    });
}
