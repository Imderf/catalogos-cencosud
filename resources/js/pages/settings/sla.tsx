import ConfigTabs from '@/components/admin/config-tabs';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { fmtDur } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Props {
    execution: { type: string; label: string; minutes: number }[];
    validation: number;
    approval: { role: string; label: string; minutes: number }[];
}

type SlaForm = {
    execution: Record<string, number>;
    validation: number;
    approval: Record<string, number>;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuración', href: '/configuracion' },
    { title: 'Tiempos de ajuste', href: '/tiempos' },
];

export default function SlaSettings({ execution, validation, approval }: Props) {
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm<SlaForm>({
        execution: Object.fromEntries(execution.map((e) => [e.type, e.minutes])),
        validation,
        approval: Object.fromEntries(approval.map((a) => [a.role, a.minutes])),
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('sla.update'), { preserveScroll: true });
    };
    const num = (v: string) => Math.max(1, parseInt(v, 10) || 1);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tiempos de ajuste" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <ConfigTabs />
                <FlashMessage />
                <Heading
                    title="Tiempos de ajuste"
                    description="Todo se mide en minutos laborales. El cronómetro de cada página suma los ajustes abiertos, así que fluctúa a medida que entran o se resuelven."
                />
                <form onSubmit={submit} className="grid max-w-3xl gap-8">
                    <section className="grid gap-3">
                        <h3 className="text-sm font-semibold">Ejecución en Ogilvy, por tipo de ajuste</h3>
                        {execution.map((e) => (
                            <Row
                                key={e.type}
                                label={e.label}
                                value={data.execution[e.type]}
                                onChange={(v) => setData('execution', { ...data.execution, [e.type]: v })}
                            />
                        ))}
                        <InputError message={errors['execution'] as string | undefined} />
                    </section>

                    <section className="grid gap-3">
                        <h3 className="text-sm font-semibold">Plazos del cliente</h3>
                        {approval.map((a) => (
                            <Row
                                key={a.role}
                                label={`Aprobación · ${a.label}`}
                                value={data.approval[a.role]}
                                onChange={(v) => setData('approval', { ...data.approval, [a.role]: v })}
                            />
                        ))}
                        <Row label="Validar un ajuste (cada eslabón)" value={data.validation} onChange={(v) => setData('validation', v)} />
                    </section>

                    <p className="text-muted-foreground text-sm">
                        Los días y horas en que corren estos tiempos se definen en{' '}
                        <Link href={route('schedule.edit')} className="text-primary underline">
                            Horario
                        </Link>
                        .
                    </p>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                            Guardar tiempos
                        </Button>
                        {recentlySuccessful && <span className="text-muted-foreground text-sm">Guardado</span>}
                    </div>
                </form>
            </div>
        </AppLayout>
    );

    function Row({ label, value, onChange }: { label: string; value: number; onChange: (v: number) => void }) {
        return (
            <div className="grid grid-cols-[1fr_120px_90px] items-center gap-3 text-sm">
                <span>{label}</span>
                <Input type="number" min={1} value={value} onChange={(e) => onChange(num(e.target.value))} className="font-mono" />
                <span className="text-muted-foreground font-mono text-xs">{fmtDur(value)}</span>
            </div>
        );
    }
}
