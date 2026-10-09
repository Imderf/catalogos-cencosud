<?php

namespace App\Domain\Settings;

use App\Domain\Sla\SlaSettings;
use App\Domain\Time\BusinessHours;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/** Editable platform settings (SLA and business hours), persisted as JSON rows. */
class SettingsRepository
{
    public const SLA = 'sla';

    public const BUSINESS_HOURS = 'business_hours';

    public const REVIEW = 'review';

    public function sla(): SlaSettings
    {
        return SlaSettings::fromArray($this->get(self::SLA));
    }

    public function businessHours(): BusinessHours
    {
        return BusinessHours::fromArray($this->get(self::BUSINESS_HOURS));
    }

    /** When true, people may comment, validate and approve outside business hours; clocks still freeze. */
    public function actionsOutsideHours(): bool
    {
        return (bool) ($this->get(self::REVIEW)['actions_outside_hours'] ?? false);
    }

    public function putActionsOutsideHours(bool $allowed): void
    {
        $this->put(self::REVIEW, [...$this->get(self::REVIEW), 'actions_outside_hours' => $allowed]);
    }

    public function putSla(SlaSettings $sla): void
    {
        $this->put(self::SLA, $sla->toArray());
    }

    public function putBusinessHours(BusinessHours $hours): void
    {
        $this->put(self::BUSINESS_HOURS, $hours->toArray());
    }

    private function get(string $key): array
    {
        return Cache::rememberForever("settings.{$key}", fn () => Setting::find($key)?->value ?? []);
    }

    private function put(string $key, array $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("settings.{$key}");
    }
}
