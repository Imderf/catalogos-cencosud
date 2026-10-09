const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

/** Date-only strings ("2026-09-15") are local days, not UTC midnight: parse them without a timezone shift. */
function parse(iso: string): Date {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
    return m ? new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])) : new Date(iso);
}

/** "12 sep 2026" */
export function fmtDate(iso: string | null | undefined): string {
    if (!iso) return '—';
    const d = parse(iso);
    return `${d.getDate()} ${MESES[d.getMonth()]} ${d.getFullYear()}`;
}

/** "12 sep · 14:05" */
export function fmtDateTime(iso: string | null | undefined): string {
    if (!iso) return '—';
    const d = new Date(iso);
    return `${d.getDate()} ${MESES[d.getMonth()]} · ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

/** Minutes → "2h 30m" (negative keeps the sign). */
export function fmtDur(min: number): string {
    const neg = min < 0;
    const m = Math.round(Math.abs(min));
    const h = Math.floor(m / 60);
    const r = m % 60;
    return (neg ? '-' : '') + (h > 0 ? `${h}h ${String(r).padStart(2, '0')}m` : `${r}m`);
}

/** Minutes → "HH:MM:SS"; overdue shows a leading "+" and counts up. */
export function fmtReloj(min: number): string {
    const neg = min < 0;
    let s = Math.floor(Math.abs(min) * 60);
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    s = s % 60;
    return (neg ? '+' : '') + [h, m, s].map((x) => String(x).padStart(2, '0')).join(':');
}

/** Bytes → "1,2 MB" */
export function fmtSize(bytes: number | null | undefined): string {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} MB`;
}

export function pad2(n: number): string {
    return String(n).padStart(2, '0');
}

/** "hace 5 min" / "hace 2 h" */
export function fmtAgo(iso: string): string {
    const min = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000));
    if (min < 60) return `hace ${min} min`;
    if (min < 60 * 24) return `hace ${Math.round(min / 60)} h`;
    return `hace ${Math.round(min / 1440)} d`;
}
