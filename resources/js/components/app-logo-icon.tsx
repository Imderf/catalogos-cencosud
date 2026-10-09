import { cn } from '@/lib/utils';
import { ImgHTMLAttributes } from 'react';

/** The app icon (red tile with a catalog and a magnifier). Files live in public/: icon-512.png and friends. */
export default function AppLogoIcon({ className, ...props }: ImgHTMLAttributes<HTMLImageElement>) {
    return <img src="/icon-192.png" alt="Revisión de Catálogos" draggable={false} {...props} className={cn('rounded-md select-none', className)} />;
}
