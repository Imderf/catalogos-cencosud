import ConfigTabs from '@/components/admin/config-tabs';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { fmtDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { CalendarClock, Check, LoaderCircle, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type DayRow = {
    day: number;
    label: string;
    open: boolean;
    from: string;
    to: string;
};

interface Person {
    id: number;
    name: string;
    side: 'Ogilvy' | 'Cencosud';
    role_label: string | null;
}

interface Exception {
    id: number;
    date: string;
    kind: 'extend' | 'closed' | 'users';
    kind_label: string;
    from: string | null;
    to: string | null;
    note: string | null;
    users: { id: number; name: string }[];
    created_by: string | null;
    past: boolean;
}

interface Props {
    week: DayRow[];
    actions_outside_hours: boolean;
    exceptions: Exception[];
    people: Person[];
    today: string;
}

type WeekForm = { week: DayRow[]; actions_outside_hours: boolean };
type ExceptionForm = { date: string; kind: 'extend' | 'closed' | 'users'; from: string; to: string; users: number[]; note: string };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuración', href: '/configuracion' },
    { title: 'Horario', href: '/horario' },
];

export default function Schedule({ week, actions_outside_hours, exceptions, people, today }: Props) {
    const weekForm = useForm<WeekForm>({ week, actions_outside_hours });
    const saveWeek: FormEventHandler = (e) => {
        e.preventDefault();
        weekForm.put(route('schedule.update'), { preserveScroll: true });
    };
    const setDay = (i: number, patch: Partial<DayRow>) =>
        weekForm.setData(
            'week',
            weekForm.data.week.map((d, j) => (j === i ? { ...d, ...patch } : d)),
        );

    const ex = useForm<ExceptionForm>({ date: today, kind: 'extend', from: '08:30', to: '20:00', users: [], note: '' });
    const [showPast, setShowPast] = useState(false);
    const submitException: FormEventHandler = (e) => {
        e.preventDefault();
        ex.post(route('schedule.exceptions.store'), { preserveScroll: true, onSuccess: () => ex.reset('users', 'note') });
    };
    const togglePerson = (id: number) =>
        ex.setData('users', ex.data.users.includes(id) ? ex.data.users.filter((u) => u !== id) : [...ex.data.users, id]);
    const remove = (item: Exception) => {
        if (confirm(`¿Eliminar la excepción del ${fmtDate(item.date)}?`)) {
            router.delete(route('schedule.exceptions.destroy', item.id), { preserveScroll: true });
        }
    };

    const upcoming = exceptions.filter((e) => !e.past);
    const past = exceptions.filter((e) => e.past);
    const exErrors = ex.errors as Record<string, string>;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Horario" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <ConfigTabs />
                <FlashMessage />
                <Heading
                    title="Horario"
                    description="Cuándo corren los cronómetros y cuándo se puede comentar, validar y aprobar. Lo que cambia un solo día se maneja con excepciones."
                />

                <div className="grid gap-6 xl:grid-cols-2">
                    <form onSubmit={saveWeek}>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Semana</CardTitle>
                                <CardDescription>
                                    Fuera de estas horas los cronómetros se congelan y nadie puede actuar, salvo excepciones.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                {weekForm.data.week.map((d, i) => (
                                    <div
                                        key={d.day}
                                        className={cn('grid grid-cols-[110px_44px_1fr_16px_1fr] items-center gap-2 text-sm', !d.open && 'opacity-60')}
                                    >
                                        <span className="font-medium">{d.label}</span>
                                        <Switch checked={d.open} onCheckedChange={(v) => setDay(i, { open: v })} aria-label={`${d.label} abierto`} />
                                        <Input
                                            type="time"
                                            value={d.from}
                                            disabled={!d.open}
                                            onChange={(e) => setDay(i, { from: e.target.value })}
                                            className="font-mono"
                                        />
                                        <span className="text-muted-foreground text-center">a</span>
                                        <Input
                                            type="time"
                                            value={d.to}
                                            disabled={!d.open}
                                            onChange={(e) => setDay(i, { to: e.target.value })}
                                            className="font-mono"
                                        />
                                    </div>
                                ))}
                                <InputError message={(weekForm.errors as Record<string, string>)['week']} />
                                <div className="mt-2 flex items-start gap-3 rounded-lg border p-3">
                                    <Switch
                                        id="outside"
                                        checked={weekForm.data.actions_outside_hours}
                                        onCheckedChange={(v) => weekForm.setData('actions_outside_hours', v)}
                                    />
                                    <div>
                                        <Label htmlFor="outside">Permitir actuar fuera de horario a todo el mundo</Label>
                                        <p className="text-muted-foreground text-xs">
                                            Los cronómetros siguen en pausa. Para un día o unas personas en concreto, usa una excepción.
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3">
                                    <Button type="submit" disabled={weekForm.processing}>
                                        {weekForm.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                        Guardar semana
                                    </Button>
                                    {weekForm.recentlySuccessful && <span className="text-muted-foreground text-sm">Guardado</span>}
                                </div>
                            </CardContent>
                        </Card>
                    </form>

                    <form onSubmit={submitException}>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Nueva excepción</CardTitle>
                                <CardDescription>
                                    Un día concreto: ampliar el horario para todos (los cronómetros corren), cerrar el día, o habilitar a personas
                                    puntuales mientras los cronómetros siguen en pausa.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="date">Fecha</Label>
                                        <Input id="date" type="date" value={ex.data.date} onChange={(e) => ex.setData('date', e.target.value)} />
                                        <InputError message={exErrors.date} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Tipo</Label>
                                        <Select value={ex.data.kind} onValueChange={(v) => ex.setData('kind', v as ExceptionForm['kind'])}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="extend">Ampliar horario para todos</SelectItem>
                                                <SelectItem value="users">Habilitar personas</SelectItem>
                                                <SelectItem value="closed">Día cerrado</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                {ex.data.kind !== 'closed' && (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="from">Desde</Label>
                                            <Input
                                                id="from"
                                                type="time"
                                                value={ex.data.from}
                                                onChange={(e) => ex.setData('from', e.target.value)}
                                                className="font-mono"
                                            />
                                            <InputError message={exErrors.from} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="to">Hasta</Label>
                                            <Input
                                                id="to"
                                                type="time"
                                                value={ex.data.to}
                                                onChange={(e) => ex.setData('to', e.target.value)}
                                                className="font-mono"
                                            />
                                            <InputError message={exErrors.to} />
                                        </div>
                                    </div>
                                )}
                                {ex.data.kind === 'users' && (
                                    <div className="grid gap-2">
                                        <Label>Personas habilitadas</Label>
                                        <div className="flex flex-wrap gap-2">
                                            {people.map((p) => {
                                                const on = ex.data.users.includes(p.id);
                                                return (
                                                    <button
                                                        key={p.id}
                                                        type="button"
                                                        role="checkbox"
                                                        aria-checked={on}
                                                        aria-label={p.name}
                                                        onClick={() => togglePerson(p.id)}
                                                        className={cn(
                                                            'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm',
                                                            on ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-muted/60',
                                                        )}
                                                    >
                                                        {on && <Check className="h-3.5 w-3.5" />}
                                                        {p.name}
                                                        <span className={cn('text-[10px] uppercase', on ? 'opacity-80' : 'text-muted-foreground')}>
                                                            {p.side}
                                                        </span>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                        <InputError message={exErrors.users} />
                                    </div>
                                )}
                                <div className="grid gap-2">
                                    <Label htmlFor="note">Motivo (opcional)</Label>
                                    <Input
                                        id="note"
                                        value={ex.data.note}
                                        onChange={(e) => ex.setData('note', e.target.value)}
                                        placeholder="Cierre de octavilla, festivo, revisión de última hora…"
                                    />
                                    <InputError message={exErrors.note} />
                                </div>
                                <div>
                                    <Button type="submit" disabled={ex.processing}>
                                        {ex.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                        <CalendarClock className="h-4 w-4" />
                                        Guardar excepción
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </form>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Excepciones programadas</CardTitle>
                        <CardDescription>
                            {upcoming.length ? `${upcoming.length} próxima${upcoming.length === 1 ? '' : 's'}.` : 'No hay excepciones próximas.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        {[...upcoming, ...(showPast ? past : [])].map((e) => (
                            <ExceptionRow key={e.id} item={e} onRemove={() => remove(e)} />
                        ))}
                        {past.length > 0 && (
                            <Button type="button" variant="ghost" size="sm" className="w-fit" onClick={() => setShowPast((v) => !v)}>
                                {showPast ? 'Ocultar pasadas' : `Ver ${past.length} pasada${past.length === 1 ? '' : 's'}`}
                            </Button>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function ExceptionRow({ item, onRemove }: { item: Exception; onRemove: () => void }) {
    return (
        <div className={cn('flex flex-wrap items-center gap-3 rounded-lg border p-3 text-sm', item.past && 'opacity-60')}>
            <span className="w-28 font-mono text-xs">{fmtDate(item.date)}</span>
            <Badge variant={item.kind === 'closed' ? 'outline' : item.kind === 'extend' ? 'default' : 'secondary'}>{item.kind_label}</Badge>
            {item.from && (
                <span className="font-mono text-xs">
                    {item.from}–{item.to}
                </span>
            )}
            {item.users.length > 0 && <span className="text-muted-foreground text-xs">{item.users.map((u) => u.name).join(', ')}</span>}
            {item.note && <span className="text-muted-foreground text-xs">· {item.note}</span>}
            <span className="text-muted-foreground ml-auto text-xs">{item.created_by}</span>
            <Button type="button" variant="ghost" size="icon" onClick={onRemove} aria-label="Eliminar excepción">
                <Trash2 className="h-4 w-4" />
            </Button>
        </div>
    );
}
