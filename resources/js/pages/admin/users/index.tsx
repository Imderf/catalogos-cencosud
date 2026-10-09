import ConfigTabs from '@/components/admin/config-tabs';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData, type User } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, UserX } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuración', href: '/configuracion' },
    { title: 'Usuarios', href: '/admin/usuarios' },
];

interface Props {
    users: User[];
    filters: { q: string };
}

export default function UsersIndex({ users, filters }: Props) {
    const { auth } = usePage<SharedData>().props;
    const [q, setQ] = useState(filters.q);

    const search: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('admin.users.index'), { q }, { preserveState: true, replace: true });
    };

    const deactivate = (user: User) => {
        if (confirm(`¿Desactivar a ${user.name}? No podrá ingresar hasta que la reactives.`)) {
            router.delete(route('admin.users.destroy', user.id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Usuarios" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <ConfigTabs />
                <FlashMessage />
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading title="Usuarios" description="Quién entra a la plataforma, con qué rol y sobre qué sectores actúa." />
                    <Button asChild>
                        <Link href={route('admin.users.create')}>
                            <Plus className="h-4 w-4" />
                            Nuevo usuario
                        </Link>
                    </Button>
                </div>

                <form onSubmit={search} className="flex max-w-md gap-2">
                    <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar por nombre, correo u organización" />
                    <Button type="submit" variant="outline">
                        Buscar
                    </Button>
                </form>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Organización</TableHead>
                                <TableHead>Rol</TableHead>
                                <TableHead>Categorías</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead className="text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground py-8 text-center">
                                        Ningún usuario coincide con la búsqueda.
                                    </TableCell>
                                </TableRow>
                            )}
                            {users.map((u) => (
                                <TableRow key={u.id} className={u.is_active ? '' : 'opacity-60'}>
                                    <TableCell>
                                        <div className="font-medium">{u.name}</div>
                                        <div className="text-muted-foreground text-xs">{u.email}</div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={u.side === 'Ogilvy' ? 'default' : 'outline'}
                                            className={u.side === 'Ogilvy' ? 'bg-foreground text-background hover:bg-foreground' : ''}
                                        >
                                            {u.side ?? '—'}
                                        </Badge>
                                        {u.organization && <div className="text-muted-foreground mt-1 text-xs">{u.organization}</div>}
                                    </TableCell>
                                    <TableCell>{u.role_label ?? '—'}</TableCell>
                                    <TableCell>
                                        {u.role === 'comercial' ? (
                                            <div className="flex flex-wrap gap-1">
                                                {u.sectors.map((s) => (
                                                    <Badge key={s.id} variant="secondary">
                                                        {s.name}
                                                    </Badge>
                                                ))}
                                            </div>
                                        ) : (
                                            <span className="text-muted-foreground text-xs">Todas</span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap gap-1">
                                            <Badge variant={u.is_active ? 'default' : 'outline'}>{u.is_active ? 'Activa' : 'Desactivada'}</Badge>
                                            {u.must_change_password && <Badge variant="outline">Pendiente 1er ingreso</Badge>}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" size="icon" asChild aria-label={`Editar a ${u.name}`}>
                                                <Link href={route('admin.users.edit', u.id)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                            {u.is_active && u.id !== auth.user.id && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => deactivate(u)}
                                                    aria-label={`Desactivar a ${u.name}`}
                                                >
                                                    <UserX className="h-4 w-4" />
                                                </Button>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </AppLayout>
    );
}
