import FlashMessage from '@/components/flash-message';
import CatalogHeader from '@/components/review/catalog-header';
import PageCard from '@/components/review/page-card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type CatalogSummary, type PageCard as PageCardDto } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useEffect } from 'react';

export default function CatalogShow({ catalog, pages }: { catalog: CatalogSummary; pages: PageCardDto[] }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Catálogos', href: '/catalogos' },
        { title: catalog.name, href: route('catalogs.show', catalog.slug) },
    ];

    /* Clocks are server truth: refresh them every minute without losing scroll. */
    useEffect(() => {
        const id = setInterval(() => router.reload({ only: ['pages', 'catalog'] }), 60000);
        return () => clearInterval(id);
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs} header={<CatalogHeader catalog={catalog} tab="paginas" />}>
            <Head title={catalog.name} />
            <div className="flex h-full flex-1 flex-col gap-4">
                <div className="flex flex-col gap-4 p-4">
                    <FlashMessage />
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {pages.map((p) => (
                            <PageCard key={p.id} page={p} catalogSlug={catalog.slug} />
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
