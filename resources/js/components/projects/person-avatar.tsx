import { cn } from '@/lib/utils';
import { type Person } from '@/types/projects';

const HUES = ['bg-red-500', 'bg-sky-600', 'bg-emerald-600', 'bg-amber-600', 'bg-violet-600', 'bg-pink-600', 'bg-cyan-600', 'bg-neutral-500'];

export function PersonAvatar({ person, size = 'sm', className }: { person: Person; size?: 'sm' | 'md'; className?: string }) {
    return (
        <span
            title={person.name}
            className={cn(
                'inline-flex shrink-0 items-center justify-center rounded-full font-semibold text-white uppercase',
                size === 'sm' ? 'h-6 w-6 text-[10px]' : 'h-8 w-8 text-xs',
                HUES[person.id % HUES.length],
                className,
            )}
        >
            {person.initials}
        </span>
    );
}

/** Overlapping avatars with a "+N" tail. */
export function AvatarStack({ people, max = 4 }: { people: Person[]; max?: number }) {
    const shown = people.slice(0, max);
    const rest = people.length - shown.length;
    return (
        <span className="flex -space-x-1.5">
            {shown.map((p) => (
                <PersonAvatar key={p.id} person={p} className="ring-background ring-2" />
            ))}
            {rest > 0 && (
                <span className="bg-muted text-muted-foreground ring-background inline-flex h-6 w-6 items-center justify-center rounded-full text-[10px] ring-2">
                    +{rest}
                </span>
            )}
        </span>
    );
}
