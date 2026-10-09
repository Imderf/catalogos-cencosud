import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem } from '@/types';
import { type ReactNode } from 'react';

interface AppLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    /** Replaces the breadcrumbs in the top bar (catalog screens). */
    header?: ReactNode;
}

export default ({ children, breadcrumbs, header, ...props }: AppLayoutProps) => (
    <AppLayoutTemplate breadcrumbs={breadcrumbs} header={header} {...props}>
        {children}
    </AppLayoutTemplate>
);
