import { PersonAvatar } from '@/components/projects/person-avatar';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { type Person } from '@/types/projects';
import { type ChangeEvent, type KeyboardEvent, type ReactNode, useRef, useState } from 'react';

const MENTION = /@\[([^\]]+)\]\((\d+)\)/g;

const URL_RE = /(https?:\/\/[^\s<]+[^\s<.,;:!?)\]])/g;

/** Plain text with URLs turned into safe links (new tab, no referrer). */
export function Linkify({ text }: { text: string }) {
    const parts = text.split(URL_RE);
    return (
        <>
            {parts.map((p, i) =>
                i % 2 === 1 ? (
                    <a
                        key={i}
                        href={p}
                        target="_blank"
                        rel="noopener noreferrer nofollow"
                        className="text-primary break-all underline underline-offset-2 hover:opacity-80"
                    >
                        {p}
                    </a>
                ) : (
                    p
                ),
            )}
        </>
    );
}

/** Comment body with @[Nombre](id) markup rendered as chips and URLs as links. */
export function MentionText({ body }: { body: string }) {
    const parts: ReactNode[] = [];
    let last = 0;
    for (const m of body.matchAll(MENTION)) {
        const i = m.index ?? 0;
        if (i > last) parts.push(<Linkify key={`t${last}`} text={body.slice(last, i)} />);
        parts.push(
            <span key={`${i}-${m[2]}`} className="bg-primary/10 text-primary rounded px-1 font-medium">
                @{m[1]}
            </span>,
        );
        last = i + m[0].length;
    }
    if (last < body.length) parts.push(<Linkify key={`t${last}`} text={body.slice(last)} />);
    return <span className="whitespace-pre-wrap">{parts}</span>;
}

/** Textarea that offers the project members when the writer types "@". */
/** What the person sees while typing: `@[Nombre](id)` becomes `@Nombre`. */
function toDisplay(markup: string): string {
    return markup.replace(MENTION, '@$1');
}

function escapeRe(s: string): string {
    return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/** Back to the stored form: every `@Nombre` of a known person becomes `@[Nombre](id)` (longest names first). */
function toMarkup(display: string, people: Person[]): string {
    let out = display;
    for (const p of [...people].sort((a, b) => b.name.length - a.name.length)) {
        out = out.replace(new RegExp(`@${escapeRe(p.name)}(?![\\p{L}\\p{N}])`, 'gu'), `@[${p.name}](${p.id})`);
    }
    return out;
}

/** The display text with the mentions wrapped as chips, drawn behind the (transparent) textarea text. */
function Highlight({ text, people }: { text: string; people: Person[] }) {
    const names = [...people].map((p) => p.name).sort((a, b) => b.length - a.length);
    if (!names.length || !text.includes('@')) return <>{text}</>;
    const re = new RegExp(`@(?:${names.map(escapeRe).join('|')})(?![\\p{L}\\p{N}])`, 'gu');
    const parts: ReactNode[] = [];
    let last = 0;
    for (const m of text.matchAll(re)) {
        const i = m.index ?? 0;
        if (i > last) parts.push(text.slice(last, i));
        parts.push(
            <span key={i} className="bg-primary/10 text-primary ring-primary/10 rounded-sm ring-2">
                {m[0]}
            </span>,
        );
        last = i + m[0].length;
    }
    if (last < text.length) parts.push(text.slice(last));
    return <>{parts}</>;
}

export function MentionTextarea({
    value,
    onChange,
    people,
    placeholder,
    rows = 3,
    autoFocus,
    onSubmit,
    above,
}: {
    value: string;
    onChange: (v: string) => void;
    people: Person[];
    placeholder?: string;
    rows?: number;
    autoFocus?: boolean;
    onSubmit?: () => void;
    /** Show the suggestions above the box (for composers pinned at the bottom). */
    above?: boolean;
}) {
    const ref = useRef<HTMLTextAreaElement>(null);
    const back = useRef<HTMLDivElement>(null);
    const [query, setQuery] = useState<{ start: number; text: string } | null>(null);
    const [index, setIndex] = useState(0);
    /* The box works on the readable form; the markup with ids is what leaves through onChange. */
    const display = toDisplay(value);

    const matches = query ? people.filter((p) => p.name.toLowerCase().includes(query.text.toLowerCase())).slice(0, 6) : [];

    const detect = (text: string, caret: number) => {
        const before = text.slice(0, caret);
        const at = before.lastIndexOf('@');
        if (at < 0 || (at > 0 && !/\s/.test(before[at - 1]))) return setQuery(null);
        const q = before.slice(at + 1);
        if (/[\n\](]/.test(q) || q.length > 30) return setQuery(null);
        /* Right after a completed mention there is nothing to suggest. */
        if (people.some((p) => q === p.name || q.startsWith(`${p.name} `))) return setQuery(null);
        setQuery({ start: at, text: q });
        setIndex(0);
    };

    const change = (e: ChangeEvent<HTMLTextAreaElement>) => {
        onChange(toMarkup(e.target.value, people));
        detect(e.target.value, e.target.selectionStart);
    };

    const pick = (p: Person) => {
        if (!query) return;
        const caret = ref.current?.selectionStart ?? display.length;
        const next = `${display.slice(0, query.start)}@${p.name} ${display.slice(caret)}`;
        onChange(toMarkup(next, people));
        setQuery(null);
        requestAnimationFrame(() => {
            const pos = query.start + p.name.length + 2;
            ref.current?.focus();
            ref.current?.setSelectionRange(pos, pos);
        });
    };

    const key = (e: KeyboardEvent<HTMLTextAreaElement>) => {
        if (query && matches.length) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setIndex((i) => (i + 1) % matches.length);
                return;
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                setIndex((i) => (i - 1 + matches.length) % matches.length);
                return;
            }
            if (e.key === 'Enter' || e.key === 'Tab') {
                e.preventDefault();
                pick(matches[index]);
                return;
            }
            if (e.key === 'Escape') {
                setQuery(null);
                return;
            }
        }
        /* Enter sends, Shift+Enter breaks the line, like the original board. */
        if (e.key === 'Enter' && !e.shiftKey && onSubmit) {
            e.preventDefault();
            onSubmit();
        }
    };

    const oneRow = rows === 1;
    /* The backdrop mirrors the textarea's box exactly (same padding, font and wrapping) so the chips sit under the words. */
    const boxClass = cn('px-3 py-2 text-base md:text-sm', oneRow ? 'h-9 min-h-9 overflow-hidden' : 'min-h-[80px]');

    return (
        <div className="relative min-w-0 flex-1">
            <div
                ref={back}
                aria-hidden
                className={cn(
                    'pointer-events-none absolute inset-0 overflow-hidden rounded-md border border-transparent',
                    boxClass,
                    oneRow ? 'whitespace-pre' : 'break-words whitespace-pre-wrap',
                )}
            >
                <Highlight text={display} people={people} />
                {display.endsWith('\n') && <br />}
            </div>
            <Textarea
                ref={ref}
                value={display}
                onChange={change}
                onKeyDown={key}
                onScroll={(e) => {
                    if (!back.current) return;
                    back.current.scrollTop = e.currentTarget.scrollTop;
                    back.current.scrollLeft = e.currentTarget.scrollLeft;
                }}
                rows={rows}
                placeholder={placeholder}
                autoFocus={autoFocus}
                /* Text is transparent: the backdrop paints it (with chips); the caret stays visible. A one-row composer never grows. */
                className={cn(
                    'caret-foreground relative bg-transparent text-transparent',
                    oneRow ? 'h-9 min-h-9 resize-none overflow-hidden py-2 whitespace-pre' : 'py-2 break-words whitespace-pre-wrap',
                )}
                /* One row behaves like an input: a single line that scrolls sideways with the caret. */
                wrap={oneRow ? 'off' : undefined}
            />
            {query && matches.length > 0 && (
                <ul
                    className={cn(
                        'bg-popover text-popover-foreground absolute left-0 z-20 max-h-48 w-64 overflow-y-auto rounded-md border p-1 shadow-md',
                        above ? 'bottom-full mb-1' : 'right-0 mt-1',
                    )}
                >
                    {matches.map((p, i) => (
                        <li key={p.id}>
                            <button
                                type="button"
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => pick(p)}
                                className={cn('flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm', i === index && 'bg-accent')}
                            >
                                <PersonAvatar person={p} />
                                {p.name}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
