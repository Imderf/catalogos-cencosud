import { type Sector } from '@/types';

/** The two families of sectors, in the order the business names them. */
export const SECTOR_GROUPS: { key: Sector['group']; label: string }[] = [
    { key: 'food', label: 'Food' },
    { key: 'non_food', label: 'Non Food' },
];

/** Sectors split by family, keeping their own order inside each; empty families are skipped. */
export function groupSectors(sectors: Sector[]): { key: string; label: string; items: Sector[] }[] {
    return SECTOR_GROUPS.map((g) => ({ ...g, items: sectors.filter((s) => s.group === g.key) })).filter((g) => g.items.length > 0);
}
