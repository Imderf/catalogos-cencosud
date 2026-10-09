import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { pad2 } from '@/lib/format';
import { groupSectors } from '@/lib/sectors';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type PageSector } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle, Upload, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type Step = 'detalles' | 'subir' | 'categorizar';

type WizardPage = {
    image: File;
    sectors: number[];
};

type WizardForm = {
    name: string;
    brand_id: string;
    kind: string;
    project_id: string;
    card_id: string;
    pages: WizardPage[];
};

interface Brand {
    id: number;
    client_id?: number | null;
    slug: string;
    name: string;
}

interface Sector extends PageSector {
    group: 'food' | 'non_food';
    id: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Catálogos', href: '/catalogos' },
    { title: 'Nuevo catálogo', href: '/catalogos/nuevo' },
];

export default function CatalogsCreate({
    brands,
    sectors,
    kinds,
    projects = [],
}: {
    brands: Brand[];
    sectors: Sector[];
    kinds: { value: string; label: string }[];
    projects?: { id: number; name: string; color: string; client_id: number | null; cards: { id: number; title: string; subtasks: number }[] }[];
}) {
    const [step, setStep] = useState<Step>('detalles');
    const fileRef = useRef<HTMLInputElement>(null);
    const { data, setData, post, processing, errors } = useForm<WizardForm>({
        name: '',
        brand_id: String(brands[0]?.id ?? ''),
        kind: kinds[0]?.value ?? 'octavilla',
        project_id: '',
        card_id: '',
        pages: [],
    });

    /* Boards of the brand's client first; boards without client stay available. */
    const brandClient = brands.find((b) => String(b.id) === data.brand_id)?.client_id ?? null;
    const offered = projects.filter((p) => p.client_id === null || brandClient === null || p.client_id === brandClient);
    const selectedProject = offered.find((p) => String(p.id) === data.project_id) ?? null;
    const steps: { key: Step; label: string }[] = [
        { key: 'detalles', label: 'Detalles' },
        { key: 'subir', label: 'Subir páginas' },
        { key: 'categorizar', label: 'Categorizar' },
    ];

    const addFiles = (files: FileList | null) => {
        if (!files) return;
        const nuevas = Array.from(files)
            .sort((a, b) => a.name.localeCompare(b.name, 'es', { numeric: true }))
            .map((image) => ({ image, sectors: [] as number[] }));
        setData('pages', [...data.pages, ...nuevas]);
    };

    const toggleSector = (i: number, id: number) => {
        const pages = data.pages.map((p, j) =>
            j !== i ? p : { ...p, sectors: p.sectors.includes(id) ? p.sectors.filter((s) => s !== id) : [...p.sectors, id].sort((a, b) => a - b) },
        );
        setData('pages', pages);
    };

    const publish = () => post(route('catalogs.store'), { forceFormData: true });
    const categorized = data.pages.filter((p) => p.sectors.length).length;
    const pageError = Object.keys(errors).find((k) => k.startsWith('pages'));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo catálogo" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading title="Nuevo catálogo" description="Sube las páginas del arte final y marca a qué sector pertenece cada una." />

                <div className="flex gap-1 border-b">
                    {steps.map((s, i) => (
                        <button
                            key={s.key}
                            type="button"
                            onClick={() => setStep(s.key)}
                            className={cn(
                                'border-b-2 px-3 py-2 text-sm',
                                s.key === step ? 'border-primary font-medium' : 'text-muted-foreground border-transparent',
                            )}
                        >
                            <span className="text-muted-foreground mr-2 font-mono text-xs">0{i + 1}</span>
                            {s.label}
                        </button>
                    ))}
                </div>

                {step === 'detalles' && (
                    <div className="grid max-w-xl gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nombre del catálogo</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Jumbo · Octavilla 25–27 Sep 2026"
                                autoFocus
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="brand">Marca</Label>
                            <Select value={data.brand_id} onValueChange={(v) => setData('brand_id', v)}>
                                <SelectTrigger id="brand">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {brands.map((b) => (
                                        <SelectItem key={b.id} value={String(b.id)}>
                                            Cencosud ({b.name})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.brand_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Tipo de entregable</Label>
                            <div role="radiogroup" aria-label="Tipo de entregable" className="flex flex-wrap gap-2">
                                {kinds.map((k) => (
                                    <button
                                        key={k.value}
                                        type="button"
                                        role="radio"
                                        aria-checked={data.kind === k.value}
                                        aria-label={k.label}
                                        onClick={() => setData('kind', k.value)}
                                        className={cn(
                                            'rounded-full border px-3 py-1.5 text-sm',
                                            data.kind === k.value ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-muted/60',
                                        )}
                                    >
                                        {k.label}
                                    </button>
                                ))}
                            </div>
                            <InputError message={errors.kind} />
                        </div>
                        {offered.length > 0 && (
                            <div className="grid gap-2">
                                <Label htmlFor="project">Proyecto (tablero interno)</Label>
                                <Select
                                    value={data.project_id || 'none'}
                                    onValueChange={(v) => {
                                        setData('project_id', v === 'none' ? '' : v);
                                        setData('card_id', '');
                                    }}
                                >
                                    <SelectTrigger id="project">
                                        <SelectValue placeholder="Sin proyecto" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Sin proyecto</SelectItem>
                                        {offered.map((p) => (
                                            <SelectItem key={p.id} value={String(p.id)}>
                                                {p.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-muted-foreground text-xs">
                                    El catálogo aparece como tarjeta y avanza sola por las listas según la revisión.
                                </p>
                                {selectedProject && selectedProject.cards.length > 0 && (
                                    <div className="mt-3 grid gap-2">
                                        <Label htmlFor="card">Tarea del proyecto</Label>
                                        <Select value={data.card_id || 'new'} onValueChange={(v) => setData('card_id', v === 'new' ? '' : v)}>
                                            <SelectTrigger id="card">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="new">Crear una tarea nueva para este catálogo</SelectItem>
                                                {selectedProject.cards.map((c) => (
                                                    <SelectItem key={c.id} value={String(c.id)}>
                                                        {c.title}
                                                        {c.subtasks > 0 ? ` · ${c.subtasks} subtarea${c.subtasks === 1 ? '' : 's'}` : ''}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <p className="text-muted-foreground text-xs">
                                            Si eliges una tarea existente, sus subtareas pasan a ser las páginas del catálogo en orden (la primera
                                            subtarea es la página 01) y los comentarios del cliente llegan a ellas.
                                        </p>
                                    </div>
                                )}
                            </div>
                        )}
                        <div className="flex gap-2">
                            <Button type="button" onClick={() => (data.name.trim() ? setStep('subir') : null)} disabled={!data.name.trim()}>
                                Continuar
                            </Button>
                            <Button variant="ghost" asChild>
                                <Link href={route('catalogs.index')}>Cancelar</Link>
                            </Button>
                        </div>
                    </div>
                )}

                {step === 'subir' && (
                    <div className="grid gap-6">
                        <input ref={fileRef} type="file" accept="image/*" multiple className="hidden" onChange={(e) => addFiles(e.target.files)} />
                        <button
                            type="button"
                            onClick={() => fileRef.current?.click()}
                            className="text-muted-foreground hover:bg-muted/50 flex flex-col items-center gap-2 rounded-xl border border-dashed p-10 text-sm"
                        >
                            <Upload className="h-6 w-6" />
                            Haz clic para elegir las páginas (JPG o PNG). Se ordenan por nombre de archivo.
                        </button>
                        <p className="text-sm">
                            {data.pages.length} {data.pages.length === 1 ? 'página cargada' : 'páginas cargadas'}
                        </p>
                        <PageThumbs
                            pages={data.pages}
                            onRemove={(i) =>
                                setData(
                                    'pages',
                                    data.pages.filter((_, j) => j !== i),
                                )
                            }
                        />
                        <div className="flex gap-2">
                            <Button type="button" variant="outline" onClick={() => setStep('detalles')}>
                                Atrás
                            </Button>
                            <Button type="button" onClick={() => setStep('categorizar')} disabled={!data.pages.length}>
                                Continuar
                            </Button>
                        </div>
                    </div>
                )}

                {step === 'categorizar' && (
                    <div className="grid gap-6">
                        <p className="text-muted-foreground text-sm">
                            {categorized}/{data.pages.length} categorizadas · una página puede pertenecer a varios sectores a la vez.
                        </p>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {data.pages.map((p, i) => (
                                <div key={i} className="bg-card rounded-xl border p-3">
                                    <Thumb file={p.image} />
                                    <div className="mt-2 flex items-center justify-between">
                                        <span className="text-sm font-medium">Pág. {pad2(i + 1)}</span>
                                        <span className={cn('text-xs', p.sectors.length ? '' : 'text-muted-foreground')}>
                                            {p.sectors.length
                                                ? sectors
                                                      .filter((s) => p.sectors.includes(s.id))
                                                      .map((s) => s.name)
                                                      .join(' · ')
                                                : 'Sin sector'}
                                        </span>
                                    </div>
                                    <div className="mt-2 space-y-1">
                                        {groupSectors(sectors).map((g) => (
                                            <div key={g.key} className="flex flex-wrap items-center gap-1">
                                                <span className="text-muted-foreground mr-1 w-14 shrink-0 text-[10px] font-semibold tracking-wide uppercase">
                                                    {g.label}
                                                </span>
                                                {g.items.map((s) => (
                                                    <Button
                                                        key={s.id}
                                                        type="button"
                                                        size="sm"
                                                        variant={p.sectors.includes(s.id) ? 'default' : 'outline'}
                                                        className="h-7 px-2 font-mono text-[11px]"
                                                        onClick={() => toggleSector(i, s.id)}
                                                    >
                                                        {s.abbr}
                                                    </Button>
                                                ))}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                        {pageError && <InputError message={errors[pageError as keyof typeof errors] as string} />}
                        <div className="flex gap-2">
                            <Button type="button" variant="outline" onClick={() => setStep('subir')}>
                                Atrás
                            </Button>
                            <Button type="button" onClick={publish} disabled={processing || categorized < data.pages.length}>
                                {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                Publicar catálogo
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function Thumb({ file }: { file: File }) {
    const [url, setUrl] = useState<string>('');
    useEffect(() => {
        const u = URL.createObjectURL(file);
        setUrl(u);
        return () => URL.revokeObjectURL(u);
    }, [file]);
    return <div className="bg-muted aspect-[360/700] w-full rounded-md bg-cover bg-center" style={{ backgroundImage: `url('${url}')` }} />;
}

function PageThumbs({ pages, onRemove }: { pages: WizardPage[]; onRemove: (i: number) => void }) {
    if (!pages.length) return null;
    return (
        <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
            {pages.map((p, i) => (
                <div key={i} className="relative">
                    <Thumb file={p.image} />
                    <span className="absolute top-2 left-2 rounded bg-black/70 px-1.5 py-0.5 font-mono text-[11px] text-white">
                        Pág. {pad2(i + 1)}
                    </span>
                    <button
                        type="button"
                        onClick={() => onRemove(i)}
                        className="absolute top-2 right-2 rounded-full bg-black/70 p-1 text-white hover:bg-black"
                        aria-label="Quitar página"
                    >
                        <X className="h-3 w-3" />
                    </button>
                </div>
            ))}
        </div>
    );
}
