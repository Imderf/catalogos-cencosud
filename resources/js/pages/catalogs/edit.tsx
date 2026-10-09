import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import DeleteCatalogDialog from '@/components/review/delete-catalog-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { pad2 } from '@/lib/format';
import { groupSectors } from '@/lib/sectors';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type CatalogSummary, type PageSector } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Archive, ArchiveRestore, ArrowDown, ArrowUp, LoaderCircle, Upload, X } from 'lucide-react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';

interface Brand {
    id: number;
    client_id?: number | null;
    name: string;
}

interface Sector extends PageSector {
    group: 'food' | 'non_food';
    id: number;
}

interface PageRow {
    id: number;
    number: number;
    label: string;
    version: number;
    sector_ids: number[];
    thumb_url: string | null;
    open: number;
    submitted: boolean;
}

type NewPage = { image: File; sectors: number[] };

interface Props {
    catalog: CatalogSummary & { brand_id: number; project_id: number | null; card_id: number | null };
    projects?: { id: number; name: string; color: string; client_id: number | null; cards: { id: number; title: string; subtasks: number }[] }[];
    pages: PageRow[];
    brands: Brand[];
    sectors: Sector[];
    kinds: { value: string; label: string }[];
}

export default function CatalogEdit({ catalog, pages, brands, sectors, kinds, projects = [] }: Props) {
    const archived = !!catalog.archived_at;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Catálogos', href: '/catalogos' },
        { title: catalog.name, href: route('catalogs.show', catalog.slug) },
        { title: 'Administrar', href: route('catalogs.edit', catalog.slug) },
    ];

    const details = useForm({
        name: catalog.name,
        brand_id: String(catalog.brand_id),
        kind: catalog.kind,
        project_id: catalog.project_id ? String(catalog.project_id) : '',
        card_id: catalog.card_id ? String(catalog.card_id) : '',
    });
    const pending = pages.filter((p) => !p.submitted).length;
    const submitAll = () => {
        if (confirm(`¿Enviar ${pending} página${pending === 1 ? '' : 's'} a Marketing? Empieza la validación y corren los cronómetros.`)) {
            router.post(route('catalogs.submit', catalog.slug), {}, { preserveScroll: true });
        }
    };
    /* Boards of the brand's client first; boards without client stay available. */
    const brandClient = brands.find((b) => String(b.id) === details.data.brand_id)?.client_id ?? null;
    const offered = projects.filter((p) => p.client_id === null || brandClient === null || p.client_id === brandClient);
    const selectedProject = offered.find((p) => String(p.id) === details.data.project_id) ?? null;
    const saveDetails: FormEventHandler = (e) => {
        e.preventDefault();
        details.transform((d) => ({
            ...d,
            project_id: d.project_id === '' ? null : Number(d.project_id),
            card_id: d.card_id === '' ? null : Number(d.card_id),
        }));
        details.put(route('catalogs.update', catalog.slug), { preserveScroll: true });
    };

    /* Order: local until saved */
    const [order, setOrder] = useState(pages.map((p) => p.id));
    useEffect(() => setOrder(pages.map((p) => p.id)), [pages]);
    const move = (i: number, dir: -1 | 1) => {
        const j = i + dir;
        if (j < 0 || j >= order.length) return;
        const next = [...order];
        [next[i], next[j]] = [next[j], next[i]];
        setOrder(next);
    };
    const orderChanged = order.some((id, i) => pages[i]?.id !== id);
    const saveOrder = () => router.put(route('catalogs.reorder', catalog.slug), { order }, { preserveScroll: true });

    const byId = Object.fromEntries(pages.map((p) => [p.id, p]));

    const toggle = (page: PageRow, sectorId: number) => {
        const ids = page.sector_ids.includes(sectorId) ? page.sector_ids.filter((s) => s !== sectorId) : [...page.sector_ids, sectorId];
        router.put(route('catalogs.pages.sectors', [catalog.slug, page.number]), { sectors: ids }, { preserveScroll: true });
    };

    const archive = () => {
        if (
            confirm(
                archived ? '¿Reactivar este catálogo?' : '¿Archivar este catálogo? Sale de la lista y de las bandejas; se puede reactivar después.',
            )
        ) {
            router.post(route(archived ? 'catalogs.unarchive' : 'catalogs.archive', catalog.slug), {}, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Administrar · ${catalog.name}`} />
            <div className="flex h-full flex-1 flex-col gap-8 p-4">
                <FlashMessage />
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading title="Administrar catálogo" description={catalog.name} />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={route('catalogs.show', catalog.slug)}>Ver páginas</Link>
                        </Button>
                        <Button variant={archived ? 'default' : 'outline'} onClick={archive}>
                            {archived ? <ArchiveRestore className="h-4 w-4" /> : <Archive className="h-4 w-4" />}
                            {archived ? 'Reactivar' : 'Archivar'}
                        </Button>
                        <DeleteCatalogDialog catalog={catalog} size="default" />
                    </div>
                </div>

                {archived && (
                    <Badge variant="secondary" className="w-fit">
                        Catálogo archivado: no aparece en la lista ni en las bandejas y nadie puede actuar sobre él.
                    </Badge>
                )}

                <section className="grid gap-4 rounded-xl border p-4">
                    <h3 className="text-sm font-semibold">Detalles</h3>
                    <form onSubmit={saveDetails} className="grid max-w-xl gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nombre</Label>
                            <Input id="name" value={details.data.name} onChange={(e) => details.setData('name', e.target.value)} />
                            <InputError message={details.errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="brand">Marca</Label>
                            <Select value={details.data.brand_id} onValueChange={(v) => details.setData('brand_id', v)}>
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
                        </div>
                        <div className="grid gap-2">
                            <Label>Tipo de entregable</Label>
                            <div role="radiogroup" aria-label="Tipo de entregable" className="flex flex-wrap gap-2">
                                {kinds.map((k) => (
                                    <button
                                        key={k.value}
                                        type="button"
                                        role="radio"
                                        aria-checked={details.data.kind === k.value}
                                        aria-label={k.label}
                                        onClick={() => details.setData('kind', k.value as typeof details.data.kind)}
                                        className={cn(
                                            'rounded-full border px-3 py-1.5 text-sm',
                                            details.data.kind === k.value ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-muted/60',
                                        )}
                                    >
                                        {k.label}
                                    </button>
                                ))}
                            </div>
                        </div>
                        {offered.length > 0 && (
                            <div className="grid gap-2">
                                <Label htmlFor="project">Proyecto (tablero interno)</Label>
                                <Select
                                    value={details.data.project_id || 'none'}
                                    onValueChange={(v) => {
                                        details.setData('project_id', v === 'none' ? '' : v);
                                        details.setData('card_id', '');
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
                                {selectedProject && selectedProject.cards.length > 0 && (
                                    <div className="mt-3 grid gap-2">
                                        <Label htmlFor="card">Tarea del proyecto</Label>
                                        <Select
                                            value={details.data.card_id || 'new'}
                                            onValueChange={(v) => details.setData('card_id', v === 'new' ? '' : v)}
                                        >
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
                        <div>
                            <Button type="submit" disabled={details.processing || !details.isDirty}>
                                Guardar detalles
                            </Button>
                        </div>
                    </form>
                </section>

                <section className="grid gap-4 rounded-xl border p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 className="text-sm font-semibold">Páginas y sectores</h3>
                            <p className="text-muted-foreground text-xs">
                                Cambia el orden con las flechas y guárdalo. Los sectores se guardan al tocarlos.
                            </p>
                        </div>
                        <div className="flex gap-2">
                            {pending > 0 && !archived && (
                                <Button size="sm" variant="default" onClick={submitAll}>
                                    Enviar {pending} a Marketing
                                </Button>
                            )}
                            <Button size="sm" variant="outline" onClick={saveOrder} disabled={!orderChanged || archived}>
                                Guardar orden
                            </Button>
                        </div>
                    </div>
                    <ol className="grid gap-2">
                        {order.map((id, i) => {
                            const p = byId[id];
                            if (!p) return null;
                            return (
                                <li key={id} className="bg-card flex flex-wrap items-center gap-3 rounded-lg border p-2">
                                    <div className="flex flex-col">
                                        <button
                                            type="button"
                                            onClick={() => move(i, -1)}
                                            disabled={i === 0 || archived}
                                            className="text-muted-foreground disabled:opacity-30"
                                            aria-label="Subir"
                                        >
                                            <ArrowUp className="h-4 w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => move(i, 1)}
                                            disabled={i === order.length - 1 || archived}
                                            className="text-muted-foreground disabled:opacity-30"
                                            aria-label="Bajar"
                                        >
                                            <ArrowDown className="h-4 w-4" />
                                        </button>
                                    </div>
                                    <div
                                        className="bg-muted h-16 w-9 shrink-0 rounded bg-cover bg-top"
                                        style={{ backgroundImage: p.thumb_url ? `url('${p.thumb_url}')` : undefined }}
                                    />
                                    <div className="w-28">
                                        <p className="font-mono text-sm">
                                            {pad2(i + 1)} <span className="text-muted-foreground text-xs">(hoy {pad2(p.number)})</span>
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            v{p.version}
                                            {p.open > 0 && <span className="text-red-600 dark:text-red-400"> · {p.open} abiertos</span>}
                                            {!p.submitted && <span className="text-primary"> · en preparación</span>}
                                        </p>
                                    </div>
                                    <div className="space-y-1">
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
                                                        variant={p.sector_ids.includes(s.id) ? 'default' : 'outline'}
                                                        className="h-7 px-2 font-mono text-[11px]"
                                                        disabled={archived}
                                                        onClick={() => toggle(p, s.id)}
                                                    >
                                                        {s.abbr}
                                                    </Button>
                                                ))}
                                            </div>
                                        ))}
                                    </div>
                                </li>
                            );
                        })}
                    </ol>
                </section>

                {!archived && <AddPages catalogSlug={catalog.slug} sectors={sectors} nextNumber={pages.length + 1} />}
            </div>
        </AppLayout>
    );
}

function AddPages({ catalogSlug, sectors, nextNumber }: { catalogSlug: string; sectors: Sector[]; nextNumber: number }) {
    const fileRef = useRef<HTMLInputElement>(null);
    const form = useForm<{ pages: NewPage[] }>({ pages: [] });
    const add = (files: FileList | null) => {
        if (!files) return;
        const nuevas = Array.from(files)
            .sort((a, b) => a.name.localeCompare(b.name, 'es', { numeric: true }))
            .map((image) => ({ image, sectors: [] as number[] }));
        form.setData('pages', [...form.data.pages, ...nuevas]);
    };
    const toggle = (i: number, id: number) =>
        form.setData(
            'pages',
            form.data.pages.map((p, j) =>
                j !== i ? p : { ...p, sectors: p.sectors.includes(id) ? p.sectors.filter((s) => s !== id) : [...p.sectors, id] },
            ),
        );
    const ready = form.data.pages.length > 0 && form.data.pages.every((p) => p.sectors.length > 0);
    const errorKey = Object.keys(form.errors)[0];

    return (
        <section className="grid gap-4 rounded-xl border p-4">
            <div>
                <h3 className="text-sm font-semibold">Agregar páginas</h3>
                <p className="text-muted-foreground text-xs">Se añaden después de la última y entran a revisión en Marketing.</p>
            </div>
            <input ref={fileRef} type="file" accept="image/*" multiple className="hidden" onChange={(e) => add(e.target.files)} />
            <button
                type="button"
                onClick={() => fileRef.current?.click()}
                className="text-muted-foreground hover:bg-muted/50 flex items-center justify-center gap-2 rounded-xl border border-dashed p-6 text-sm"
            >
                <Upload className="h-4 w-4" /> Elegir imágenes
            </button>
            {form.data.pages.length > 0 && (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {form.data.pages.map((p, i) => (
                        <div key={i} className="bg-card relative rounded-lg border p-3">
                            <Thumb file={p.image} />
                            <button
                                type="button"
                                onClick={() =>
                                    form.setData(
                                        'pages',
                                        form.data.pages.filter((_, j) => j !== i),
                                    )
                                }
                                className="absolute top-2 right-2 rounded-full bg-black/70 p-1 text-white"
                                aria-label="Quitar"
                            >
                                <X className="h-3 w-3" />
                            </button>
                            <p className="mt-2 text-sm font-medium">Pág. {pad2(nextNumber + i)}</p>
                            <div className="mt-1 space-y-1">
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
                                                className={cn('h-7 px-2 font-mono text-[11px]')}
                                                onClick={() => toggle(i, s.id)}
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
            )}
            {errorKey && <InputError message={(form.errors as Record<string, string>)[errorKey]} />}
            <div>
                <Button
                    disabled={!ready || form.processing}
                    onClick={() =>
                        form.post(route('catalogs.pages.add', catalogSlug), {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => form.reset(),
                        })
                    }
                >
                    {form.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    Agregar {form.data.pages.length || ''} página{form.data.pages.length === 1 ? '' : 's'}
                </Button>
            </div>
        </section>
    );
}

function Thumb({ file }: { file: File }) {
    const [url, setUrl] = useState('');
    useEffect(() => {
        const u = URL.createObjectURL(file);
        setUrl(u);
        return () => URL.revokeObjectURL(u);
    }, [file]);
    return <div className="bg-muted aspect-[360/700] w-full rounded-md bg-cover bg-center" style={{ backgroundImage: `url('${url}')` }} />;
}
