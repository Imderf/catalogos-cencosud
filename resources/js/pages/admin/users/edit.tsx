import UserForm from '@/components/admin/user-form';
import Heading from '@/components/heading';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type RoleOption, type Sector, type SharedData, type User } from '@/types';
import { Head, usePage } from '@inertiajs/react';

export default function UsersEdit({ user, roles, sectors }: { user: User; roles: RoleOption[]; sectors: Sector[] }) {
    const { auth } = usePage<SharedData>().props;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Configuración', href: '/configuracion' },
        { title: 'Usuarios', href: '/admin/usuarios' },
        { title: user.name, href: `/admin/usuarios/${user.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar · ${user.name}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading title={user.name} description={user.email} />
                <UserForm user={user} roles={roles} sectors={sectors} isSelf={user.id === auth.user.id} />
            </div>
        </AppLayout>
    );
}
