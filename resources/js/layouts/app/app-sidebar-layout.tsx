import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import Toaster from '@/components/ui/toaster';
import { useLiveNotifications } from '@/hooks/use-live-board';
import { useLiveReview } from '@/hooks/use-live-review';
import { useNotificationPulse } from '@/hooks/use-notification-pulse';
import { LIVE } from '@/lib/live';
import { type BreadcrumbItem } from '@/types';
import { type ReactNode } from 'react';

/** Subscribes to real-time changes; rendered only when a Reverb key is configured. */
function LiveReview() {
    useLiveReview();
    useLiveNotifications();
    return null;
}

/** Without websockets, a light poll keeps the bell, the toast and the chime working. */
function Pulse() {
    useNotificationPulse(!LIVE);
    return null;
}

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
    header,
}: {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    header?: ReactNode;
}) {
    return (
        <AppShell variant="sidebar">
            {LIVE ? <LiveReview /> : <Pulse />}
            <AppSidebar />
            <AppContent variant="sidebar">
                <AppSidebarHeader breadcrumbs={breadcrumbs}>{header}</AppSidebarHeader>
                {children}
            </AppContent>
            <Toaster />
        </AppShell>
    );
}
