import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Progress } from '@/components/ui/progress';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
    SidebarSeparator,
    useSidebar,
} from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BarChart3, Bell, BookImage, CalendarDays, KanbanSquare, LayoutDashboard, PanelLeftClose, PanelLeftOpen, Plus, Settings } from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { auth, workday, inbox, unread } = usePage<SharedData>().props;
    const user = auth.user;

    /* Catalog review: what the client sees too. */
    const catalogItems: NavItem[] = [{ title: 'Catálogos', url: '/catalogos', icon: BookImage }];
    if (user.can.manage_catalogs) catalogItems.push({ title: 'Nuevo catálogo', url: '/catalogos/nuevo', icon: Plus });
    catalogItems.push({ title: 'Reportes', url: '/reportes', icon: BarChart3 });
    /* The client sees the calendar of its projects (read only) next to the catalogs. */
    if (!user.can.projects) catalogItems.push({ title: 'Calendario', url: '/calendario', icon: CalendarDays });

    /* Project boards: Ogilvy's own tool, kept apart from the catalog review. */
    const projectItems: NavItem[] = user.can.projects
        ? [
              { title: 'Panel', url: '/panel', icon: LayoutDashboard },
              { title: 'Proyectos', url: '/proyectos', icon: KanbanSquare },
              { title: 'Calendario', url: '/calendario', icon: CalendarDays },
          ]
        : [];

    /* Shared with both: the inbox mixes catalog and project alerts, and settings covers both areas. */
    const generalItems: NavItem[] = [{ title: 'Notificaciones', url: '/notificaciones', icon: Bell }];
    if (user.can.manage_sla || user.can.manage_users) {
        generalItems.push({
            title: 'Configuración',
            url: '/configuracion',
            icon: Settings,
            match: ['/configuracion', '/tiempos', '/horario', '/admin/usuarios', '/admin/clientes'],
        });
    }

    const bandeja =
        !inbox || inbox.total === 0
            ? 'Nada esperando por tu equipo'
            : user.acts_as_agency
              ? `${inbox.adjustments} ajuste(s) por ejecutar`
              : `${inbox.approvals} página(s) por aprobar · ${inbox.adjustments} ajuste(s) por validar`;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/catalogos" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain label="Catálogos" items={catalogItems} badges={{ '/catalogos': inbox?.total ?? 0 }} />
                {projectItems.length > 0 && (
                    <>
                        <SidebarSeparator className="mx-2" />
                        <NavMain label="Proyectos" items={projectItems} />
                    </>
                )}
                <SidebarSeparator className="mx-2" />
                <NavMain items={generalItems} badges={{ '/notificaciones': unread ?? 0 }} />
                <div className="mt-2 px-4 text-xs group-data-[collapsible=icon]:hidden">
                    <p className="text-sidebar-foreground/80">{bandeja}</p>
                    {workday && <p className={workday.open ? 'text-sidebar-foreground/60 mt-1' : 'mt-1 font-medium text-red-400'}>{workday.label}</p>}
                </div>
            </SidebarContent>

            <SidebarFooter>
                <RoundProgress />
                <CollapseButton />
                <NavUser />
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}

/** Visible way to shrink the menu to icons (and back); Cmd/Ctrl+B does the same. */
function CollapseButton() {
    const { state, toggleSidebar, isMobile } = useSidebar();
    if (isMobile) return null;
    const collapsed = state === 'collapsed';
    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton onClick={toggleSidebar} tooltip="Expandir menú" aria-label={collapsed ? 'Expandir menú' : 'Contraer menú'}>
                    {collapsed ? <PanelLeftOpen /> : <PanelLeftClose />}
                    <span>Contraer menú</span>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}

function RoundProgress() {
    const { round } = usePage<SharedData>().props;
    if (!round || !round.pages) return null;
    return (
        <div className="border-sidebar-border mb-2 rounded-lg border p-3 text-xs group-data-[collapsible=icon]:hidden">
            <p className="text-sidebar-foreground/60 mb-2 tracking-wide uppercase">Ronda de revisión</p>
            <Progress value={Math.round((round.approved / round.pages) * 100)} className="h-1.5" />
            <p className="text-sidebar-foreground/80 mt-2">
                {round.approved} de {round.pages} páginas aprobadas a imprenta
            </p>
        </div>
    );
}
