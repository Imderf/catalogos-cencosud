import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { type CatalogSummary } from '@/types';
import { Link2 } from 'lucide-react';
import { useState } from 'react';

export default function ShareDialog({ catalog, size = 'sm' }: { catalog: CatalogSummary; size?: 'sm' | 'default' }) {
    const url = `${window.location.origin}/catalogos/${catalog.slug}`;
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(url);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            /* clipboard blocked: the field is selectable anyway */
        }
    };

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline" size={size}>
                    <Link2 className="h-4 w-4" />
                    Compartir
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Link directo para el cliente</DialogTitle>
                    <DialogDescription>Este link lleva al cliente directo a {catalog.name}. Necesita su usuario para entrar.</DialogDescription>
                </DialogHeader>
                <div className="flex gap-2">
                    <Input readOnly value={url} onFocus={(e) => e.currentTarget.select()} className="font-mono text-xs" />
                    <Button onClick={copy}>{copied ? 'Copiado' : 'Copiar'}</Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
