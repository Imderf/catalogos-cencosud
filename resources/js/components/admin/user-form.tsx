import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { groupSectors } from '@/lib/sectors';
import { cn } from '@/lib/utils';
import { type RoleOption, type RoleValue, type Sector, type User } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { Check, Copy, Eye, EyeOff, LoaderCircle, RefreshCw } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

export type UserFormData = {
    name: string;
    email: string;
    organization: string;
    role: RoleValue | '';
    sectors: number[];
    password: string;
    is_active: boolean;
};

interface UserFormProps {
    user?: User;
    roles: RoleOption[];
    sectors: Sector[];
    isSelf?: boolean;
}

const ORGANIZATIONS: Record<RoleValue, string> = {
    admin: 'Ogilvy Colombia',
    agencia: 'Ogilvy Colombia',
    lectura: 'Ogilvy Colombia',
    marketing: 'Cencosud · Marketing',
    planificacion: 'Cencosud · Planificación',
    comercial: 'Cencosud · Comercial',
};

/** What each role does, in the words the team uses. */
const ROLE_HELP: Record<RoleValue, { side: string; does: string }> = {
    admin: { side: 'Ogilvy', does: 'Crea usuarios, carga catálogos, edita tiempos y ejecuta ajustes.' },
    agencia: { side: 'Ogilvy', does: 'Carga catálogos y versiones nuevas, y ejecuta los ajustes que bajan.' },
    lectura: { side: 'Ogilvy · solo mira', does: 'Ve catálogos, páginas, reportes y calendario; no carga, no aprueba ni comenta.' },
    marketing: { side: 'Cencosud · eslabón 1', does: 'Comenta, valida ajustes y da el primer visto bueno de cada página.' },
    planificacion: { side: 'Cencosud · eslabón 2', does: 'Comenta, valida los ajustes de Comercial y aprueba después de Marketing.' },
    comercial: { side: 'Cencosud · eslabón 3', does: 'Comenta y aprueba solo las páginas de sus categorías. Cierra la cadena.' },
};

function generatePassword(): string {
    const words = ['Jumbo', 'Metro', 'Pagina', 'Octavilla', 'Revision', 'Catalogo', 'Ajuste', 'Sector'];
    const w = words[Math.floor(Math.random() * words.length)];
    const n = Math.floor(1000 + Math.random() * 9000);
    const s = 'abcdefghjkmnpqrstuvwxyz';
    let tail = '';
    for (let i = 0; i < 4; i++) tail += s[Math.floor(Math.random() * s.length)];
    return `${w}-${n}-${tail}`;
}

export default function UserForm({ user, roles, sectors, isSelf = false }: UserFormProps) {
    const isEdit = !!user;
    const [showPassword, setShowPassword] = useState(false);
    const [copied, setCopied] = useState(false);

    const { data, setData, post, put, processing, errors } = useForm<UserFormData>({
        name: user?.name ?? '',
        email: user?.email ?? '',
        organization: user?.organization ?? '',
        role: user?.role ?? '',
        sectors: user?.sectors.map((s) => s.id) ?? [],
        password: '',
        is_active: user?.is_active ?? true,
    });

    const usesSectors = data.role === 'comercial';
    const chosenSectors = sectors.filter((s) => data.sectors.includes(s.id));
    const sectorsMissing = usesSectors && data.sectors.length === 0;

    const onRoleChange = (role: RoleValue) => {
        if (isSelf) return;
        setData((prev) => ({
            ...prev,
            role,
            organization:
                prev.organization === '' || Object.values(ORGANIZATIONS).includes(prev.organization) ? ORGANIZATIONS[role] : prev.organization,
            sectors: role === 'comercial' ? prev.sectors : [],
        }));
    };

    const toggleSector = (id: number) => {
        if (!usesSectors) return;
        setData('sectors', data.sectors.includes(id) ? data.sectors.filter((s) => s !== id) : [...data.sectors, id].sort((a, b) => a - b));
    };

    const copyPassword = async () => {
        try {
            await navigator.clipboard.writeText(data.password);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch {
            /* clipboard blocked */
        }
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(route('admin.users.update', user.id), { preserveScroll: true });
        } else {
            post(route('admin.users.store'), { preserveScroll: true });
        }
    };

    const firstName = data.name.trim().split(/\s+/)[0] || 'La persona';
    const summary = !data.role
        ? 'Elige un rol para ver qué podrá hacer.'
        : usesSectors
          ? chosenSectors.length
              ? `${firstName} verá todos los catálogos, pero solo podrá comentar y aprobar las páginas de ${chosenSectors.map((s) => s.name).join(', ')}.`
              : `${firstName} verá todos los catálogos, pero no podrá actuar sobre ninguna página hasta que le asignes al menos una categoría.`
          : data.role === 'admin' || data.role === 'agencia' || data.role === 'lectura'
            ? `${firstName} trabaja del lado de Ogilvy: ${ROLE_HELP[data.role].does.toLowerCase()}`
            : `${firstName} actúa sobre todas las categorías: ${ROLE_HELP[data.role].does.toLowerCase()}`;

    return (
        <form onSubmit={submit} className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
            <div className="grid gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Quién es</CardTitle>
                        <CardDescription>El correo es el usuario de ingreso y donde llegan las alertas.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nombre completo</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                required
                                autoFocus
                                placeholder="Valeria Ortiz"
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Correo</Label>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                required
                                placeholder="nombre@cencosud.com"
                            />
                            <InputError message={errors.email} />
                        </div>
                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="organization">Organización</Label>
                            <Input
                                id="organization"
                                value={data.organization}
                                onChange={(e) => setData('organization', e.target.value)}
                                placeholder="Se completa al elegir el rol"
                            />
                            <InputError message={errors.organization} />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Rol</CardTitle>
                        <CardDescription>Define de qué lado de la mesa está y en qué eslabón de la cadena de aprobación.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        <div role="radiogroup" aria-label="Rol" className="grid gap-2 sm:grid-cols-2">
                            {roles.map((r) => {
                                const active = data.role === r.value;
                                const help = ROLE_HELP[r.value];
                                return (
                                    <button
                                        key={r.value}
                                        type="button"
                                        role="radio"
                                        aria-checked={active}
                                        aria-label={r.label}
                                        disabled={isSelf}
                                        onClick={() => onRoleChange(r.value)}
                                        className={cn(
                                            'flex items-start gap-3 rounded-lg border p-3 text-left transition-colors disabled:opacity-60',
                                            active ? 'border-primary bg-primary/5 ring-primary/30 ring-1' : 'hover:bg-muted/50',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border',
                                                active ? 'border-primary bg-primary text-primary-foreground' : 'border-muted-foreground/40',
                                            )}
                                        >
                                            {active && <Check className="h-3 w-3" />}
                                        </span>
                                        <span className="min-w-0">
                                            <span className="flex flex-wrap items-center gap-2">
                                                <span className="text-sm font-medium">{r.label}</span>
                                                <Badge variant="outline" className="text-[10px]">
                                                    {help.side}
                                                </Badge>
                                            </span>
                                            <span className="text-muted-foreground mt-0.5 block text-xs">{help.does}</span>
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                        <InputError message={errors.role} />
                    </CardContent>
                </Card>

                <Card className={cn(sectorsMissing && 'border-red-300 dark:border-red-800')}>
                    <CardHeader>
                        <CardTitle className="text-base">Categorías</CardTitle>
                        <CardDescription>
                            {usesSectors
                                ? 'Un comercial ve todo el catálogo, pero solo comenta y aprueba las páginas de sus categorías. Elige una o varias.'
                                : data.role
                                  ? 'Este rol actúa sobre todas las categorías; no hace falta elegir.'
                                  : 'Solo se asignan al rol Sector / Comercial.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        <div role="group" aria-label="Categorías" className="space-y-2">
                            {groupSectors(sectors).map((g) => (
                                <div key={g.key} className="flex flex-wrap items-center gap-2">
                                    <span className="text-muted-foreground mr-1 w-14 shrink-0 text-[10px] font-semibold tracking-wide uppercase">
                                        {g.label}
                                    </span>
                                    {g.items.map((s) => {
                                        const on = usesSectors ? data.sectors.includes(s.id) : !!data.role;
                                        return (
                                            <button
                                                key={s.id}
                                                type="button"
                                                role="checkbox"
                                                aria-checked={usesSectors ? data.sectors.includes(s.id) : undefined}
                                                aria-label={s.name}
                                                disabled={!usesSectors}
                                                onClick={() => toggleSector(s.id)}
                                                className={cn(
                                                    'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition-colors',
                                                    usesSectors ? 'hover:bg-muted/60' : 'cursor-default opacity-70',
                                                    on && usesSectors && 'border-primary bg-primary text-primary-foreground hover:bg-primary/90',
                                                    on && !usesSectors && 'border-dashed',
                                                )}
                                            >
                                                <span className="font-mono text-[11px] opacity-80">{s.abbr}</span>
                                                {s.name}
                                                {on && usesSectors && <Check className="h-3.5 w-3.5" />}
                                            </button>
                                        );
                                    })}
                                </div>
                            ))}
                        </div>
                        {sectorsMissing && (
                            <p className="text-xs text-red-600 dark:text-red-400">Un usuario comercial necesita al menos una categoría.</p>
                        )}
                        <InputError message={errors.sectors} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Acceso</CardTitle>
                        <CardDescription>
                            {isEdit
                                ? 'Déjala vacía para no cambiar la contraseña.'
                                : 'Entrégale la contraseña en persona. Deberá cambiarla en su primer ingreso; nunca se envía por correo.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="password">{isEdit ? 'Nueva contraseña (opcional)' : 'Contraseña inicial'}</Label>
                            <div className="flex gap-2">
                                <Input
                                    id="password"
                                    type={showPassword ? 'text' : 'password'}
                                    autoComplete="new-password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    required={!isEdit}
                                    className="font-mono"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    onClick={() => setShowPassword((v) => !v)}
                                    aria-label={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                                >
                                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        setData('password', generatePassword());
                                        setShowPassword(true);
                                    }}
                                >
                                    <RefreshCw className="h-4 w-4" />
                                    Generar
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    onClick={copyPassword}
                                    disabled={!data.password}
                                    aria-label="Copiar contraseña"
                                >
                                    {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
                                </Button>
                            </div>
                            <InputError message={errors.password} />
                        </div>
                        <div className="flex items-center gap-3">
                            <Switch id="is_active" checked={data.is_active} onCheckedChange={(v) => setData('is_active', v)} disabled={isSelf} />
                            <Label htmlFor="is_active">Cuenta activa</Label>
                            <span className="text-muted-foreground text-xs">
                                Una cuenta desactivada no puede entrar, pero su historial se conserva.
                            </span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <aside className="grid content-start gap-4 xl:sticky xl:top-4 xl:self-start">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Resumen</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 text-sm">
                        <dl className="grid gap-1.5">
                            <Row label="Nombre" value={data.name || '—'} />
                            <Row label="Correo" value={data.email || '—'} />
                            <Row label="Rol" value={roles.find((r) => r.value === data.role)?.label ?? '—'} />
                            <Row
                                label="Categorías"
                                value={usesSectors ? chosenSectors.map((s) => s.name).join(' · ') || 'Ninguna todavía' : data.role ? 'Todas' : '—'}
                            />
                            <Row label="Estado" value={data.is_active ? 'Activa' : 'Desactivada'} />
                        </dl>
                        <p className="text-muted-foreground border-t pt-3 text-xs">{summary}</p>
                    </CardContent>
                </Card>
                <div className="flex flex-wrap items-center gap-3">
                    <Button type="submit" disabled={processing || sectorsMissing || !data.role}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        {isEdit ? 'Guardar cambios' : 'Crear usuario'}
                    </Button>
                    <Button type="button" variant="ghost" asChild>
                        <Link href={route('admin.users.index')}>Cancelar</Link>
                    </Button>
                </div>
            </aside>
        </form>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="grid grid-cols-[88px_1fr] gap-2">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="truncate">{value}</dd>
        </div>
    );
}
