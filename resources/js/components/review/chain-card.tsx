import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { type Ability, type ChainStep, type PageDetail } from '@/types';
import { Check, MessageSquarePlus, Printer, RotateCcw, Send, Upload, Wrench } from 'lucide-react';
import { type ReactNode } from 'react';

/** Where the page is: Ogilvy (adjustments), the validation pass going up, the print pass coming down. */
export function ChainSteps({ page }: { page: PageDetail }) {
    return (
        <div>
            <Lane title="Validación" hint="sube">
                <Step
                    n={<Wrench className="h-3 w-3" />}
                    label="Ogilvy"
                    meta={page.agency_step.label}
                    state={page.agency_step.working ? 'on' : 'done'}
                />
                {page.chain.map((s, i) => (
                    <Step
                        key={s.role}
                        n={i + 1}
                        label={s.label}
                        meta={page.phase === 'preparation' ? 'En espera' : metaOf(s)}
                        state={page.phase === 'preparation' ? 'wait' : stateOf(s)}
                    />
                ))}
            </Lane>
            <Lane title="Aprobación a imprenta" hint="baja" className="mt-2">
                {page.print_chain.map((s, i) => (
                    <Step key={s.role} n={i + 1} label={s.label} meta={metaOf(s)} state={page.phase === 'validation' ? 'wait' : stateOf(s)} />
                ))}
            </Lane>
            {page.suspended && (
                <p className="text-muted-foreground mt-3 text-xs">
                    Hay {page.open} ajuste{page.open === 1 ? '' : 's'} en curso. La cadena se reanuda cuando{' '}
                    {page.open === 1 ? 'se cierre' : 'se cierren'}.
                </p>
            )}
            {!page.has_access && <p className="text-muted-foreground mt-3 text-xs">{page.can.comment.reason}</p>}
        </div>
    );
}

interface ActionsProps {
    page: PageDetail;
    drawing: boolean;
    onToggleDraw: () => void;
    onValidate: () => void;
    onPrint: () => void;
    onReopen: () => void;
    onNewVersion: () => void;
    onSubmit?: () => void;
    actsAsAgency: boolean;
    role: string | null;
    size?: 'sm' | 'default';
}

/** The buttons of the moment. Disabled ones explain why on hover. */
export function ReviewActions({
    page,
    drawing,
    onToggleDraw,
    onValidate,
    onPrint,
    onReopen,
    onNewVersion,
    onSubmit,
    actsAsAgency,
    role,
    size = 'sm',
}: ActionsProps) {
    const closed = page.phase === 'closed';
    const myValidation = page.chain.find((s) => s.role === role);
    const myPrint = page.print_chain.find((s) => s.role === role);

    if (actsAsAgency) {
        return (
            <>
                {!page.submitted && (
                    <Gate ability={page.can.submit}>
                        <Button size={size} onClick={onSubmit} disabled={!page.can.submit.allowed}>
                            <Send className="h-4 w-4" />
                            Enviar a Marketing
                        </Button>
                    </Gate>
                )}
                <Gate ability={page.can.upload_version}>
                    <Button
                        size={size}
                        variant={page.submitted ? 'default' : 'outline'}
                        onClick={onNewVersion}
                        disabled={!page.can.upload_version.allowed}
                    >
                        <Upload className="h-4 w-4" />
                        Cargar nueva versión
                    </Button>
                </Gate>
            </>
        );
    }

    return (
        <>
            <Gate ability={page.can.comment}>
                <Button size={size} variant={drawing ? 'default' : 'outline'} onClick={onToggleDraw} disabled={!page.can.comment.allowed}>
                    <MessageSquarePlus className="h-4 w-4" />
                    {drawing ? 'Marcando región…' : 'Comentar región'}
                </Button>
            </Gate>
            {myValidation?.done_by ? (
                <Button size={size} variant="secondary" disabled>
                    <Check className="h-4 w-4" />
                    Validado
                </Button>
            ) : (
                <Gate ability={page.can.approve}>
                    <Button size={size} onClick={onValidate} disabled={!page.can.approve.allowed}>
                        <Check className="h-4 w-4" />
                        Validar
                    </Button>
                </Gate>
            )}
            {myPrint?.done_by ? (
                <Button size={size} variant="secondary" disabled>
                    <Printer className="h-4 w-4" />
                    {role === 'marketing' ? 'Aprobado a imprenta' : 'Aprobado'}
                </Button>
            ) : (
                <Gate ability={page.can.approve_print}>
                    <Button
                        size={size}
                        onClick={onPrint}
                        disabled={!page.can.approve_print.allowed}
                        className={cn(!page.can.approve_print.allowed && 'opacity-50')}
                    >
                        <Printer className="h-4 w-4" />
                        {role === 'marketing' ? 'Aprobar a imprenta' : 'Aprobar'}
                    </Button>
                </Gate>
            )}
            {role === 'marketing' && closed && (
                <Gate ability={page.can.reopen}>
                    <Button size={size} variant="destructive" onClick={onReopen} disabled={!page.can.reopen.allowed}>
                        <RotateCcw className="h-4 w-4" />
                        Post Production Defect
                    </Button>
                </Gate>
            )}
        </>
    );
}

function metaOf(s: ChainStep): string {
    return s.done_by ?? (s.active ? (s.suspended ? 'En pausa' : 'En revisión') : 'En espera');
}

function stateOf(s: ChainStep): 'done' | 'on' | 'wait' {
    if (s.done_by) return 'done';
    return s.active && !s.suspended ? 'on' : 'wait';
}

function Lane({ title, hint, className, children }: { title: string; hint: string; className?: string; children: ReactNode }) {
    return (
        <div className={className}>
            <p className="text-muted-foreground mb-1 text-[10px] tracking-wide uppercase">
                {title} <span className="opacity-70">· {hint}</span>
            </p>
            <ol className="grid grid-cols-4 gap-1.5">{children}</ol>
        </div>
    );
}

function Step({ n, label, meta, state }: { n: ReactNode; label: string; meta: string; state: 'done' | 'on' | 'wait' }) {
    return (
        <li
            className={cn(
                'rounded-lg border p-2 text-xs',
                state === 'done' && 'bg-foreground text-background border-foreground',
                state === 'on' && 'border-primary',
            )}
        >
            <div className="flex items-center gap-1.5">
                <span
                    className={cn(
                        'flex h-5 w-5 shrink-0 items-center justify-center rounded-full border font-mono text-[10px]',
                        state === 'done' ? 'border-background/40' : state === 'on' ? 'border-primary text-primary' : 'text-muted-foreground',
                    )}
                >
                    {state === 'done' ? <Check className="h-3 w-3" /> : n}
                </span>
                <span className="truncate font-medium">{label}</span>
            </div>
            <p className={cn('mt-1 truncate', state === 'done' ? 'opacity-80' : 'text-muted-foreground')}>{meta}</p>
        </li>
    );
}

/** Wraps a disabled button with the server's reason as a tooltip. */
function Gate({ ability, children }: { ability: Ability; children: ReactNode }) {
    if (ability.allowed || !ability.reason) return <>{children}</>;
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span tabIndex={0}>{children}</span>
            </TooltipTrigger>
            <TooltipContent className="max-w-xs">{ability.reason}</TooltipContent>
        </Tooltip>
    );
}
