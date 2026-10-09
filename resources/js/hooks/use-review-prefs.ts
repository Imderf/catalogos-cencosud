import { useCallback, useEffect, useState } from 'react';

/** 'all' shows every section stacked; a single key filters the panel to that section. */
export type ReviewFilter = 'all' | 'status' | 'adjustments' | 'info';

const FILTER = 'review.filter';
const OPEN = 'review.open';

function read(key: string): string | null {
    try {
        return localStorage.getItem(key);
    } catch {
        return null;
    }
}

function write(key: string, value: string) {
    try {
        localStorage.setItem(key, value);
    } catch {
        /* private mode */
    }
}

/** Review screen preferences: panel open or closed, and which section filter is active. */
export function useReviewPrefs() {
    const [open, setOpenState] = useState<boolean>(() => read(OPEN) !== '0');
    const [filter, setFilterState] = useState<ReviewFilter>(() => {
        const v = read(FILTER);
        return v === 'status' || v === 'adjustments' || v === 'info' ? v : 'all';
    });
    const [wide, setWide] = useState<boolean>(() => (typeof window !== 'undefined' ? window.matchMedia('(min-width: 1280px)').matches : true));

    useEffect(() => {
        const mq = window.matchMedia('(min-width: 1280px)');
        const on = () => setWide(mq.matches);
        mq.addEventListener('change', on);
        return () => mq.removeEventListener('change', on);
    }, []);

    const setOpen = useCallback((v: boolean) => {
        setOpenState(v);
        write(OPEN, v ? '1' : '0');
    }, []);
    const setFilter = useCallback((v: ReviewFilter) => {
        setFilterState(v);
        write(FILTER, v);
    }, []);

    return { docked: wide, open, setOpen, filter, setFilter };
}
