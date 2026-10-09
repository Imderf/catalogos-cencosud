import UserForm from '@/components/admin/user-form';
import Heading from '@/components/heading';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type RoleOption, type Sector } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuración', href: '/configuracion' },
    { title: 'Usuarios', href: '/admin/usuarios' },
    { title: 'Nuevo usuario', href: '/admin/usuarios/create' },
];

export default function UsersCreate({ roles, sectors }: { roles: RoleOption[]; sectors: Sector[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo usuario" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Nuevo usuario"
                    description="Quién es, de qué lado está, qué categorías puede tocar y con qué contraseña entra la primera vez."
                />
                <UserForm roles={roles} sectors={sectors} />
            </div>
        </AppLayout>
    );
}
