import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner } from 'sonner';

/** Bottom-right toasts, like the original board: success, error and live activity. */
export default function Toaster() {
    const { appearance } = useAppearance();
    return <Sonner position="bottom-right" theme={appearance === 'system' ? 'system' : appearance} richColors closeButton duration={4500} toastOptions={{ classNames: { toast: 'text-sm' } }} />;
}
