import FlashMessage from '@/components/flash-message';
import AdjustmentsPanel from '@/components/review/adjustments-panel';
import Canvas from '@/components/review/canvas';
import CatalogHeader from '@/components/review/catalog-header';
import { ChainSteps, ReviewActions } from '@/components/review/chain-card';
import ClockBar from '@/components/review/clock-bar';
import Compare from '@/components/review/compare';
import { ApproveDialog, DefectDialog, VersionDialog } from '@/components/review/dialogs';
import PageInfo from '@/components/review/page-info';
import { Drawer, Rail, type RailItem } from '@/components/review/rail';
import ReviewBar, { type Fit } from '@/components/review/review-bar';
import { Badge } from '@/components/ui/badge';
import { useSidebar } from '@/components/ui/sidebar';
import { type ReviewFilter, useReviewPrefs } from '@/hooks/use-review-prefs';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type CatalogSummary, type PageDetail, type SharedData, type TypeOption } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { GitBranch, Info, Layers, LayoutList, ListChecks } from 'lucide-react';
import { type ReactNode, useCallback, useEffect, useRef, useState } from 'react';

interface Props {
    catalog: CatalogSummary;
    page: PageDetail;
    nav: { prev: number | null; next: number | null; count: number };
    types: TypeOption[];
}

const TITLES: Record<ReviewFilter, string> = { all: 'Vista general', status: 'Estado', adjustments: 'Ajustes', info: 'Página' };

export default function PageShow({ catalog, page, nav, types }: Props) {
    const { auth, workday } = usePage<SharedData>().props;
    const { docked, open, setOpen, filter, setFilter } = useReviewPrefs();
    const [fit, setFit] = useState<Fit>('height');
    const [zoom, setZoom] = useState(100);
    const [drawing, setDrawing] = useState(false);
    const [compare, setCompare] = useState(false);
    const [dialog, setDialog] = useState<'none' | 'approve' | 'print' | 'defect' | 'version'>('none');
    const [highlight, setHighlight] = useState<number | null>(null);
    const frozen = !(workday?.open ?? true);
    const isAgency = auth.user.acts_as_agency;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Catálogos', href: '/catalogos' },
        { title: catalog.name, href: route('catalogs.show', catalog.slug) },
        { title: page.label, href: route('pages.show', [catalog.slug, page.number]) },
    ];

    const go = (n: number | null) => n && router.get(route('pages.show', [catalog.slug, n]));

    /* Zooming from a fit mode starts at 100 % of the box width. */
    const zoomStep = useCallback((dir: 1 | -1) => {
        setFit('custom');
        setZoom((z) => Math.min(400, Math.max(25, z + dir * 25)));
    }, []);

    /* Rail icons: "Todo" shows every section; the others filter the panel. Clicking the active one closes it. */
    const pick = (key: ReviewFilter) => {
        if (open && filter === key) {
            setOpen(false);
            return;
        }
        setFilter(key);
        setOpen(true);
    };

    useEffect(() => {
        const key = (e: KeyboardEvent) => {
            const tag = (e.target as HTMLElement)?.tagName;
            if (dialog !== 'none' || tag === 'TEXTAREA' || tag === 'INPUT' || tag === 'SELECT' || e.metaKey || e.ctrlKey || e.altKey) return;
            if (e.key === 'ArrowLeft') go(nav.prev);
            if (e.key === 'ArrowRight') go(nav.next);
            if (e.key === 'Escape') {
                if (drawing) setDrawing(false);
                else if (open && !docked) setOpen(false);
            }
            if ((e.key === 'c' || e.key === 'C') && !isAgency && page.can.comment.allowed) {
                setCompare(false);
                setDrawing((d) => !d);
            }
            if (e.key === 'a' || e.key === 'A') pick('adjustments');
        };
        window.addEventListener('keydown', key);
        const id = setInterval(() => router.reload({ only: ['page', 'catalog', 'workday', 'inbox'] }), 60000);
        return () => {
            window.removeEventListener('keydown', key);
            clearInterval(id);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [nav.prev, nav.next, dialog, drawing, open, filter, docked, isAgency, page.can.comment.allowed]);

    useEffect(() => {
        setDrawing(false);
        setCompare(false);
        /* Coming from the board: land on the comment the link points to. */
        const wanted = Number(new URLSearchParams(window.location.search).get('ajuste'));
        if (wanted && page.adjustments.some((a) => a.id === wanted)) {
            setHighlight(wanted);
            setFilter('adjustments');
            setOpen(true);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page.id, page.viewing_version]);

    const pickAdjustment = (id: number) => {
        setHighlight((h) => (h === id ? null : id));
        if (!open || (filter !== 'all' && filter !== 'adjustments')) {
            setFilter('adjustments');
            setOpen(true);
        }
    };

    const submitPage = () => {
        if (confirm(`¿Enviar la ${page.label} a Marketing? Empieza la validación y corre el cronómetro.`)) {
            router.post(route('catalogs.pages.submit', [catalog.slug, page.number]), {}, { preserveScroll: true });
        }
    };

    const late = page.clock && !page.clock.frozen && (page.clock.state === 'vencido' || page.clock.state === 'urgente');
    const items: RailItem[] = [
        { key: 'all', icon: LayoutList, label: 'Vista general' },
        {
            key: 'status',
            icon: GitBranch,
            label: 'Estado: cronómetro y cadena',
            dot: page.clock ? (page.clock.frozen ? 'off' : late ? 'late' : 'ok') : page.is_mine && page.has_access ? 'late' : null,
        },
        { key: 'adjustments', icon: ListChecks, label: 'Ajustes (A)', badge: page.open },
        ...(page.versions.length > 1 ? [{ key: 'compare', icon: Layers, label: 'Comparar versiones' } as RailItem] : []),
        { key: 'info', icon: Info, label: 'Información de la página' },
    ];
    const select = (key: string | null) => {
        if (key === 'compare' || (key === null && compare)) {
            setCompare((c) => !c);
            setDrawing(false);
            return;
        }
        pick((key ?? filter) as ReviewFilter);
    };

    const actions = auth.user.read_only ? (
        <span className="text-muted-foreground rounded-md border border-dashed px-2 py-1 text-xs" title="Tu acceso es de solo lectura">
            Solo lectura
        </span>
    ) : (
        <ReviewActions
            page={page}
            drawing={drawing}
            actsAsAgency={isAgency}
            role={auth.user.role}
            onToggleDraw={() => {
                setDrawing((d) => !d);
                setCompare(false);
            }}
            onValidate={() => setDialog('approve')}
            onPrint={() => setDialog('print')}
            onReopen={() => setDialog('defect')}
            onNewVersion={() => setDialog('version')}
            onSubmit={submitPage}
        />
    );

    const showStatus = filter === 'all' || filter === 'status';
    const showAdjustments = filter === 'all' || filter === 'adjustments';
    const showInfo = filter === 'all' || filter === 'info';
    const panelBody = (
        <div className="grid gap-6">
            {showStatus && (
                <Section title="Estado" all={filter === 'all'}>
                    <div className="grid gap-3">
                        <ClockBar clock={page.clock} />
                        {!page.clock && page.phase === 'preparation' && (
                            <p className="text-muted-foreground text-sm">
                                En preparación: el cronómetro arranca cuando Ogilvy envíe la página a Marketing.
                            </p>
                        )}
                        <ChainSteps page={page} />
                    </div>
                </Section>
            )}
            {showAdjustments && (
                <Section title="Ajustes" all={filter === 'all'} count={page.open}>
                    <AdjustmentsPanel page={page} types={types} highlight={highlight} onHighlight={setHighlight} frozen={frozen} />
                </Section>
            )}
            {showInfo && (
                <Section title="Página" all={filter === 'all'}>
                    <PageInfo catalog={catalog} page={page} />
                </Section>
            )}
        </div>
    );

    const stage = compare ? (
        <div className="h-full overflow-auto bg-neutral-200 p-4">
            <Compare versions={page.versions} b={page.viewing_version} adjustments={page.adjustments} />
        </div>
    ) : (
        <Canvas
            imageUrl={page.image_url}
            adjustments={page.adjustments}
            zoom={zoom}
            fit={fit}
            fill
            onWheelZoom={zoomStep}
            drawing={drawing}
            historical={page.is_historical}
            types={types}
            storeUrl={route('pages.adjustments.store', [catalog.slug, page.number])}
            onDrawn={() => setDrawing(false)}
            onPickAdjustment={(a) => pickAdjustment(a.id)}
            highlight={highlight}
        />
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs} header={<CatalogHeader catalog={catalog} tab="pagina" pageNumber={page.number} />}>
            <Head title={`${page.label} · ${catalog.name}`} />
            <CollapseSidebar />
            <div className="flex h-[calc(100svh-3rem)] flex-col">
                <ReviewBar
                    catalog={catalog}
                    page={page}
                    nav={nav}
                    onGo={go}
                    compare={compare}
                    onCompare={(v) => {
                        setCompare(v);
                        setDrawing(false);
                    }}
                    fit={fit}
                    zoom={zoom}
                    onFit={setFit}
                    onZoom={zoomStep}
                    onClock={() => pick('status')}
                    actions={actions}
                />
                <div className="relative flex min-h-0 flex-1">
                    <div className="relative min-w-0 flex-1 overflow-hidden">
                        <div className="pointer-events-none absolute top-2 left-2 z-10 flex flex-col gap-2 [&>*]:pointer-events-auto">
                            <FlashMessage />
                            {drawing && <Badge className="w-fit">Dibuja la región a corregir · Esc para cancelar</Badge>}
                        </div>
                        {stage}
                        {open && !docked && (
                            <Drawer title={TITLES[filter]} onClose={() => setOpen(false)} wide={filter !== 'info'}>
                                {panelBody}
                            </Drawer>
                        )}
                    </div>
                    {open && docked && (
                        <aside className="bg-card flex w-[440px] shrink-0 flex-col border-l">
                            <div className="flex items-center justify-between border-b px-4 py-2.5">
                                <h3 className="text-sm font-semibold">{TITLES[filter]}</h3>
                                <button type="button" onClick={() => setOpen(false)} className="text-muted-foreground hover:text-foreground text-xs">
                                    Cerrar
                                </button>
                            </div>
                            <div className="flex-1 overflow-y-auto p-4">{panelBody}</div>
                        </aside>
                    )}
                    <div className="relative w-14 shrink-0">
                        <Rail items={items} active={compare ? 'compare' : open ? filter : null} onSelect={select} />
                    </div>
                </div>
            </div>

            <ApproveDialog open={dialog === 'approve'} onClose={() => setDialog('none')} page={page} catalogSlug={catalog.slug} />
            <ApproveDialog mode="print" open={dialog === 'print'} onClose={() => setDialog('none')} page={page} catalogSlug={catalog.slug} />
            <DefectDialog open={dialog === 'defect'} onClose={() => setDialog('none')} page={page} catalogSlug={catalog.slug} types={types} />
            <VersionDialog open={dialog === 'version'} onClose={() => setDialog('none')} page={page} catalogSlug={catalog.slug} />
        </AppLayout>
    );
}

/** In the general view every section carries its own heading; filtered, the panel title is enough. */
function Section({ title, all, count, children }: { title: string; all: boolean; count?: number; children: ReactNode }) {
    return (
        <section>
            {all && (
                <h4 className={cn('text-muted-foreground mb-2 flex items-center gap-2 text-[11px] font-semibold tracking-wide uppercase')}>
                    {title}
                    {count !== undefined && count > 0 && <span className="rounded-full bg-red-600 px-1.5 text-[10px] text-white">{count}</span>}
                </h4>
            )}
            {children}
        </section>
    );
}

/** The review screen shrinks the app menu to icons and gives it back on exit. */
function CollapseSidebar() {
    const { open, setOpen } = useSidebar();
    const wasOpen = useRef(open);
    useEffect(() => {
        wasOpen.current = open;
        setOpen(false);
        return () => {
            if (wasOpen.current) setOpen(true);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);
    return null;
}
