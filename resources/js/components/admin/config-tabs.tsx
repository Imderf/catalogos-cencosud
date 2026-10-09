import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Building2, CalendarClock, Timer, Users } from 'lucide-react';

/** One "Configuración" area with three tabs; each tab shows only to who may use it. */
export default function ConfigTabs() {
    const page = usePage<SharedData>();
    const { auth } = page.props;
    const current = page.url.split('?')[0];
    const tabs = [
        { key: 'tiempos', label: 'Tiempos de ajuste', href: '/tiempos', icon: Timer, show: auth.user.can.manage_sla },
        { key: 'horario', label: 'Horario', href: '/horario', icon: CalendarClock, show: auth.user.can.manage_sla },
        { key: 'usuarios', label: 'Usuarios', href: '/admin/usuarios', icon: Users, show: auth.user.can.manage_users },
        { key: 'clientes', label: 'Clientes', href: '/admin/clientes', icon: Building2, show: auth.user.can.manage_clients },
    ].filter((t) => t.show);

    return (
        <nav className="flex flex-wrap gap-1 border-b" aria-label="Configuración">
            {tabs.map((t) => {
                const active = current.startsWith(t.href);
                return (
                    <Link
                        key={t.key}
                        href={t.href}
                        className={cn(
                            'flex items-center gap-2 border-b-2 px-3 py-2 text-sm transition-colors',
                            active ? 'border-primary text-foreground font-medium' : 'text-muted-foreground hover:text-foreground border-transparent',
                        )}
                    >
                        <t.icon className="h-4 w-4" />
                        {t.label}
                    </Link>
                );
            })}
        </nav>
    );
}
