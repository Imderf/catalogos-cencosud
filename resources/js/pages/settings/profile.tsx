import { type BreadcrumbItem, type SharedData } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { playNotificationSound } from '@/lib/notification-sound';
import { Volume2 } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mi cuenta', href: '/settings/profile' }];

export default function Profile() {
    const { auth } = usePage<SharedData>().props;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: auth.user.name,
        notification_mode: auth.user.notification_mode ?? 'instant',
        notification_sound: auth.user.notification_sound ?? true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    const sectores = auth.user.sectors.length ? auth.user.sectors.map((s) => s.name).join(' · ') : 'Todas las categorías';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mi cuenta" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Mi cuenta" description="Tu nombre visible. El correo, el rol y los sectores los administra Ogilvy." />

                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nombre</Label>
                            <Input
                                id="name"
                                className="mt-1 block w-full"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                required
                                autoComplete="name"
                            />
                            <InputError className="mt-2" message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">Correo</Label>
                            <Input id="email" type="email" className="mt-1 block w-full" value={auth.user.email} readOnly disabled />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="notification_mode">Correos</Label>
                            <Select value={data.notification_mode} onValueChange={(v) => setData('notification_mode', v as 'instant' | 'daily')}>
                                <SelectTrigger id="notification_mode" className="max-w-md">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="instant">Un correo por cada movimiento</SelectItem>
                                    <SelectItem value="daily">Un resumen cada mañana laboral</SelectItem>
                                </SelectContent>
                            </Select>
                            <p className="text-muted-foreground text-xs">La bandeja de la plataforma siempre se actualiza al instante.</p>
                            <InputError message={errors.notification_mode} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="notification_sound">Sonido</Label>
                            <div className="flex flex-wrap items-center gap-3">
                                <label htmlFor="notification_sound" className="flex items-center gap-2 text-sm">
                                    <Switch
                                        id="notification_sound"
                                        checked={data.notification_sound}
                                        onCheckedChange={(v) => setData('notification_sound', v)}
                                    />
                                    Avisar con un sonido cuando llegue una notificación
                                </label>
                                <Button type="button" variant="outline" size="sm" onClick={playNotificationSound}>
                                    <Volume2 className="h-4 w-4" />
                                    Probar
                                </Button>
                            </div>
                            <p className="text-muted-foreground text-xs">
                                Suena en la pestaña donde tengas abierta la plataforma, después de tu primer clic en ella.
                            </p>
                            <InputError message={errors.notification_sound} />
                        </div>

                        <dl className="text-muted-foreground grid gap-1 text-sm">
                            <div className="flex gap-2">
                                <dt className="w-24 shrink-0">Rol</dt>
                                <dd className="text-foreground">{auth.user.role_label}</dd>
                            </div>
                            <div className="flex gap-2">
                                <dt className="w-24 shrink-0">Sectores</dt>
                                <dd className="text-foreground">{sectores}</dd>
                            </div>
                            {auth.user.organization && (
                                <div className="flex gap-2">
                                    <dt className="w-24 shrink-0">Organización</dt>
                                    <dd className="text-foreground">{auth.user.organization}</dd>
                                </div>
                            )}
                        </dl>

                        <div className="flex items-center gap-4">
                            <Button disabled={processing}>Guardar</Button>

                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-neutral-600">Guardado</p>
                            </Transition>
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
