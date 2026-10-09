import AppLogoIcon from '@/components/app-logo-icon';
import { Link } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    title?: string;
    description?: string;
}

/**
 * shadcn "login-04": one card, form on the left, brand panel on the right.
 * The panel is decorative and never shows client artwork: the login is public.
 */
export default function AuthSplitCardLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div className="bg-muted flex min-h-svh flex-col items-center justify-center p-6 md:p-10">
            <div className="w-full max-w-sm md:max-w-4xl">
                <div className="bg-card text-card-foreground grid overflow-hidden rounded-xl border shadow-sm md:grid-cols-2">
                    <div className="flex flex-col gap-6 p-6 md:p-10">
                        <div className="flex flex-col items-center gap-2 text-center">
                            <Link href={route('home')} className="flex items-center gap-2 font-medium">
                                <AppLogoIcon className="size-8" />
                                <span className="sr-only">Revisión de Catálogos</span>
                            </Link>
                            <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
                            <p className="text-muted-foreground text-sm text-balance">{description}</p>
                        </div>
                        {children}
                    </div>
                    <div className="relative hidden bg-neutral-950 text-white md:block">
                        <div
                            className="absolute inset-0 opacity-40"
                            style={{
                                backgroundImage:
                                    'radial-gradient(circle at 20% 20%, rgba(239,59,58,.55), transparent 45%), radial-gradient(circle at 80% 80%, rgba(239,59,58,.35), transparent 50%)',
                            }}
                        />
                        <div className="relative flex h-full flex-col justify-between p-10">
                            <p className="font-mono text-xs tracking-widest text-white/60 uppercase">Ogilvy × Cencosud</p>
                            <div className="grid gap-6">
                                <p className="text-3xl leading-tight font-semibold">
                                    Los catálogos de Jumbo y Metro, revisados sobre la página misma.
                                </p>
                                <ol className="text-sm text-white/75">
                                    <li className="flex gap-3 py-1.5">
                                        <span className="text-primary font-mono">01</span> Ogilvy carga las páginas
                                    </li>
                                    <li className="flex gap-3 py-1.5">
                                        <span className="text-primary font-mono">02</span> Marketing, Planificación y Comercial revisan y aprueban
                                    </li>
                                    <li className="flex gap-3 py-1.5">
                                        <span className="text-primary font-mono">03</span> Los ajustes bajan a Ogilvy con su cronómetro
                                    </li>
                                </ol>
                            </div>
                            <p className="text-xs text-white/40">Plataforma de revisión de catálogos impresos</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
