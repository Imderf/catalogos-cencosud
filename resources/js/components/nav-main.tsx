import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuBadge, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';

export function NavMain({ items = [], badges = {}, label }: { items: NavItem[]; badges?: Record<string, number>; label?: string }) {
    const page = usePage();
    if (items.length === 0) return null;
    return (
        <SidebarGroup className="px-2 py-0">
            {label && <SidebarGroupLabel>{label}</SidebarGroupLabel>}
            <SidebarMenu>
                {items.map((item) => {
                    const prefixes = item.match ?? [item.url];
                    const active =
                        item.url === '/catalogos'
                            ? page.url.startsWith('/catalogos') && !page.url.startsWith('/catalogos/nuevo')
                            : prefixes.some((p) => page.url.startsWith(p));
                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton asChild isActive={active}>
                                <Link href={item.url} prefetch>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                            {(badges[item.url] ?? 0) > 0 && (
                                <SidebarMenuBadge className="bg-primary text-primary-foreground rounded-full">{badges[item.url]}</SidebarMenuBadge>
                            )}
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
