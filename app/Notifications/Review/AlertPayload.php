<?php

namespace App\Notifications\Review;

use App\Models\Catalog;
use App\Models\Page;

/** What an alert says and where it points. Stored as-is in the bandeja. */
final class AlertPayload
{
    public function __construct(
        /** carga | ajuste | validacion | resuelto | aprobacion | version | defecto | urgente | vencido */
        public readonly string $kind,
        public readonly string $text,
        public readonly string $actor,
        public readonly string $catalogSlug,
        public readonly string $catalogName,
        public readonly ?int $pageNumber = null,
    ) {}

    public static function about(string $kind, string $text, string $actor, Catalog $catalog, ?Page $page = null): self
    {
        return new self($kind, $text, $actor, $catalog->slug, $catalog->name, $page?->number);
    }

    public function url(): string
    {
        return $this->pageNumber
            ? route('pages.show', [$this->catalogSlug, $this->pageNumber])
            : route('catalogs.show', $this->catalogSlug);
    }

    public function toArray(): array
    {
        return [
            'kind' => $this->kind, 'text' => $this->text, 'actor' => $this->actor,
            'catalog_slug' => $this->catalogSlug, 'catalog_name' => $this->catalogName, 'page_number' => $this->pageNumber,
        ];
    }
}
