<?php

namespace App\Domain\Review;

/**
 * The single clock of a page: it belongs to whoever holds the ball.
 * Minutes are working minutes; `remaining` goes negative once overdue.
 */
final class PageClock
{
    public function __construct(
        /** 'ajuste' while adjustments are open, 'aprobacion' while waiting for the client */
        public readonly string $type,
        /** 'corriendo' | 'urgente' | 'vencido' | 'espera' */
        public readonly string $state,
        public readonly string $where,
        public readonly float $total,
        public readonly float $consumed,
        public readonly float $remaining,
        public readonly int $openCount = 0,
        public readonly int $overdueCount = 0,
        public readonly bool $frozen = false,
        /** Longer wording for the page bar, e.g. "Planificación: tiempo sugerido para aprobar" */
        public readonly ?string $detail = null,
    ) {}

    public function pct(): float
    {
        return $this->total > 0 ? min(1.0, max(0.0, $this->consumed / $this->total)) : 0.0;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type, 'state' => $this->state, 'where' => $this->where,
            'total' => round($this->total, 2), 'consumed' => round($this->consumed, 2), 'remaining' => round($this->remaining, 2),
            'pct' => round($this->pct(), 4), 'open' => $this->openCount, 'overdue' => $this->overdueCount, 'frozen' => $this->frozen,
            'detail' => $this->detail ?? $this->where,
        ];
    }
}
