<?php

use App\Domain\Settings\SettingsRepository;
use App\Domain\Time\BusinessHours;
use App\Domain\Time\BusinessTime;
use Carbon\CarbonImmutable;

function bt(): BusinessTime
{
    return (new BusinessTime(Mockery::mock(SettingsRepository::class)))->using(new BusinessHours);
}

function bogota(string $s): CarbonImmutable
{
    return CarbonImmutable::parse($s, 'America/Bogota');
}

test('business hours are Monday to Friday 08:30 to 18:00', function () {
    $t = bt();
    expect($t->isOpen(bogota('2026-09-16 08:29')))->toBeFalse()   // wednesday before opening
        ->and($t->isOpen(bogota('2026-09-16 08:30')))->toBeTrue()
        ->and($t->isOpen(bogota('2026-09-16 17:59')))->toBeTrue()
        ->and($t->isOpen(bogota('2026-09-16 18:00')))->toBeFalse()
        ->and($t->isOpen(bogota('2026-09-19 10:00')))->toBeFalse()   // saturday
        ->and($t->isOpen(bogota('2026-09-20 10:00')))->toBeFalse();  // sunday
});

test('next opening skips nights and weekends', function () {
    $t = bt();
    expect($t->nextOpening(bogota('2026-09-16 19:00'))->format('Y-m-d H:i'))->toBe('2026-09-17 08:30')
        ->and($t->nextOpening(bogota('2026-09-18 18:30'))->format('Y-m-d H:i'))->toBe('2026-09-21 08:30') // friday night → monday
        ->and($t->nextOpening(bogota('2026-09-16 10:00'))->format('Y-m-d H:i'))->toBe('2026-09-16 10:00'); // already open
});

test('working minutes only count inside business hours', function () {
    $t = bt();
    expect($t->workingMinutesBetween(bogota('2026-09-16 10:00'), bogota('2026-09-16 11:30')))->toBe(90.0)
        ->and($t->workingMinutesBetween(bogota('2026-09-16 17:00'), bogota('2026-09-17 09:30')))->toBe(120.0) // 60 + 60
        ->and($t->workingMinutesBetween(bogota('2026-09-18 17:30'), bogota('2026-09-21 09:00')))->toBe(60.0)  // weekend frozen
        ->and($t->workingMinutesBetween(bogota('2026-09-16 19:00'), bogota('2026-09-16 21:00')))->toBe(0.0)
        ->and($t->workingMinutesBetween(bogota('2026-09-16 11:00'), bogota('2026-09-16 10:00')))->toBe(0.0);
});

test('a full working day is 570 minutes', function () {
    expect(bt()->workingMinutesBetween(bogota('2026-09-16 00:00'), bogota('2026-09-17 00:00')))->toBe(570.0);
});
