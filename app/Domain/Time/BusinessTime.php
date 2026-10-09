<?php

namespace App\Domain\Time;

use App\Domain\Settings\SettingsRepository;
use App\Models\ScheduleException;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * All platform time is measured in WORKING MINUTES: outside business hours nobody
 * comments or approves, and every clock freezes. The weekly schedule can be changed
 * for a single day (extended hours, closed day) or opened for specific people.
 */
class BusinessTime
{
    private ?BusinessHours $hours = null;

    /** @var array<string, ScheduleException>|null date => exception (extend/closed only) */
    private ?array $dayExceptions = null;

    public function __construct(private readonly SettingsRepository $settings) {}

    public function hours(): BusinessHours
    {
        return $this->hours ??= $this->settings->businessHours();
    }

    /** Override for tests or previews: fixed hours and no day exceptions. */
    public function using(BusinessHours $hours): static
    {
        $this->hours = $hours;
        $this->dayExceptions = [];

        return $this;
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->hours()->timezone);
    }

    private function local(CarbonInterface $at): CarbonImmutable
    {
        return CarbonImmutable::instance($at)->setTimezone($this->hours()->timezone);
    }

    /** @return array<string, ScheduleException> */
    private function dayExceptions(): array
    {
        return $this->dayExceptions ??= Cache::rememberForever(ScheduleException::CACHE, fn () => ScheduleException::whereIn('kind', ['extend', 'closed'])
            ->get()->keyBy(fn (ScheduleException $e) => $e->date->format('Y-m-d'))->all());
    }

    /** Forget cached exceptions (tests and admin screens). */
    public function refresh(): static
    {
        $this->dayExceptions = null;
        $this->hours = null;
        Cache::forget(ScheduleException::CACHE);

        return $this;
    }

    /**
     * The working window of a given day, after day exceptions.
     *
     * @return array{from: float, to: float}|null
     */
    public function windowFor(CarbonInterface $day): ?array
    {
        $d = $this->local($day);
        $e = $this->dayExceptions()[$d->format('Y-m-d')] ?? null;
        if ($e) {
            return $e->kind === 'closed' ? null : ['from' => $e->from_minutes / 60, 'to' => $e->to_minutes / 60];
        }

        return $this->hours()->window($d->dayOfWeekIso);
    }

    public function isOpen(?CarbonInterface $at = null): bool
    {
        $d = $this->local($at ?? $this->now());
        $w = $this->windowFor($d);
        if (! $w) {
            return false;
        }
        $frac = $d->hour + $d->minute / 60 + $d->second / 3600;

        return $frac >= $w['from'] && $frac < $w['to'];
    }

    /** Whether people may act right now: open hours, the global setting, or a personal exception. */
    public function actionsAllowed(): bool
    {
        return $this->isOpen() || $this->settings->actionsOutsideHours();
    }

    public function actionsAllowedFor(User $user, ?CarbonInterface $at = null): bool
    {
        if ($this->isOpen($at) || $this->settings->actionsOutsideHours()) {
            return true;
        }

        return $this->personalException($user, $at) !== null;
    }

    /** A 'users' exception covering this person right now, if any. */
    public function personalException(User $user, ?CarbonInterface $at = null): ?ScheduleException
    {
        $d = $this->local($at ?? $this->now());
        $minutes = $d->hour * 60 + $d->minute;

        return ScheduleException::where('kind', 'users')
            ->whereDate('date', $d->format('Y-m-d'))
            ->where('from_minutes', '<=', $minutes)
            ->where('to_minutes', '>', $minutes)
            ->whereHas('users', fn ($q) => $q->whereKey($user->id))
            ->first();
    }

    /** Next instant at which business time resumes, from $at (inclusive if already open). */
    public function nextOpening(?CarbonInterface $at = null): CarbonImmutable
    {
        $d = $this->local($at ?? $this->now());
        if ($this->isOpen($d)) {
            return $d;
        }
        for ($i = 0; $i < 60; $i++) {
            $day = $d->addDays($i);
            $w = $this->windowFor($day);
            if ($w) {
                $opens = $this->at($day, $w['from']);
                if ($d < $opens) {
                    return $opens;
                }
            }
        }

        return $d;
    }

    /** Working minutes elapsed between two instants. */
    public function workingMinutesBetween(CarbonInterface $from, CarbonInterface $to): float
    {
        $a = $this->local($from);
        $b = $this->local($to);
        if ($b <= $a) {
            return 0.0;
        }
        $total = 0.0;
        $day = $a->startOfDay();
        for ($guard = 0; $day < $b && $guard < 400; $guard++, $day = $day->addDay()) {
            $w = $this->windowFor($day);
            if (! $w) {
                continue;
            }
            $start = max($a->getTimestamp(), $this->at($day, $w['from'])->getTimestamp());
            $end = min($b->getTimestamp(), $this->at($day, $w['to'])->getTimestamp());
            if ($end > $start) {
                $total += ($end - $start) / 60;
            }
        }

        return $total;
    }

    private function at(CarbonImmutable $day, float $frac): CarbonImmutable
    {
        return $day->startOfDay()->addMinutes((int) round($frac * 60));
    }
}
