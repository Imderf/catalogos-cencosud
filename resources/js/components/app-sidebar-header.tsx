import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { type ReactNode } from 'react';

/** Top bar: the menu trigger plus either breadcrumbs or a page-provided header (catalog screens). */
export function AppSidebarHeader({ breadcrumbs = [], children }: { breadcrumbs?: BreadcrumbItemType[]; children?: ReactNode }) {
    return (
        <header className="border-sidebar-border/50 flex h-12 shrink-0 items-center gap-2 border-b px-3 transition-[width,height] ease-linear md:px-4">
            <SidebarTrigger className="-ml-1 shrink-0" />
            {children ? <div className="flex min-w-0 flex-1 items-center">{children}</div> : <Breadcrumbs breadcrumbs={breadcrumbs} />}
        </header>
    );
}
