<?php

namespace App\Domain\Time;

/**
 * Weekly working calendar: one optional window per ISO weekday (1 = Monday … 7 = Sunday).
 * Hours are fractions: 8.5 = 08:30.
 */
final class BusinessHours
{
    /** @var array<int, array{from: float, to: float}|null> */
    public readonly array $windows;

    /**
     * Uniform schedule (the historical shape): the same window on every listed day.
     *
     * @param  list<int>  $days
     */
    public function __construct(
        array $days = [1, 2, 3, 4, 5],
        float $from = 8.5,
        float $to = 18.0,
        public readonly string $timezone = 'America/Bogota',
        ?array $windows = null,
    ) {
        $w = [];
        for ($d = 1; $d <= 7; $d++) {
            $w[$d] = $windows !== null
                ? ($windows[$d] ?? null)
                : (in_array($d, $days, true) ? ['from' => $from, 'to' => $to] : null);
        }
        $this->windows = $w;
    }

    /** @param  array<int, array{from: float, to: float}|null>  $windows */
    public static function weekly(array $windows, string $timezone = 'America/Bogota'): self
    {
        return new self(timezone: $timezone, windows: $windows);
    }

    public static function fromArray(array $a): self
    {
        $tz = (string) ($a['timezone'] ?? 'America/Bogota');
        if (isset($a['windows']) && is_array($a['windows'])) {
            $windows = [];
            for ($d = 1; $d <= 7; $d++) {
                $w = $a['windows'][$d] ?? $a['windows'][(string) $d] ?? null;
                $windows[$d] = $w ? ['from' => (float) $w['from'], 'to' => (float) $w['to']] : null;
            }

            return self::weekly($windows, $tz);
        }

        return new self(
            array_values(array_map('intval', $a['days'] ?? [1, 2, 3, 4, 5])),
            (float) ($a['from'] ?? 8.5),
            (float) ($a['to'] ?? 18.0),
            $tz,
        );
    }

    public function toArray(): array
    {
        return ['timezone' => $this->timezone, 'windows' => $this->windows];
    }

    /** @return array{from: float, to: float}|null */
    public function window(int $isoWeekday): ?array
    {
        return $this->windows[$isoWeekday] ?? null;
    }

    /** @return list<int> */
    public function days(): array
    {
        return array_keys(array_filter($this->windows));
    }

    public static function formatHour(float $frac): string
    {
        $h = (int) floor($frac);
        $m = (int) round(($frac - $h) * 60);

        return sprintf('%02d:%02d', $h, $m);
    }

    public static function parseHour(string $hm): float
    {
        [$h, $m] = array_map('intval', explode(':', $hm) + [0, 0]);

        return $h + $m / 60;
    }

    /** "L–V de 08:30 a 18:00" when uniform, otherwise "L–J 08:30–18:00 · V 08:30–15:00". */
    public function label(): string
    {
        $names = [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S', 7 => 'D'];
        $groups = [];
        foreach ($this->windows as $d => $w) {
            if (! $w) {
                continue;
            }
            $key = self::formatHour($w['from']).'–'.self::formatHour($w['to']);
            $groups[$key][] = $d;
        }
        if ($groups === []) {
            return 'sin horario';
        }
        $parts = [];
        foreach ($groups as $range => $days) {
            $consecutive = count($days) > 1 && $days === range($days[0], end($days));
            $label = $consecutive ? $names[$days[0]].'–'.$names[end($days)] : implode(' ', array_map(fn ($d) => $names[$d], $days));
            $parts[] = $label.' '.$range;
        }
        if (count($parts) === 1) {
            [$daysLabel, $range] = explode(' ', $parts[0], 2);
            [$from, $to] = explode('–', $range);

            return $daysLabel.' de '.$from.' a '.$to;
        }

        return implode(' · ', $parts);
    }
}
