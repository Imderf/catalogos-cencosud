<?php

namespace App\Http\Controllers\Review;

use App\Domain\Settings\SettingsRepository;
use App\Domain\Sla\SlaSettings;
use App\Enums\AdjustmentType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** "Tiempos de ajuste": deadlines, editable by Ogilvy. Business hours live in ScheduleController. */
class SlaSettingsController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function edit(): Response
    {
        $this->authorize('sla.manage');
        $sla = $this->settings->sla();

        return Inertia::render('settings/sla', [
            'execution' => array_map(fn (AdjustmentType $t) => ['type' => $t->value, 'label' => $t->label(), 'minutes' => $sla->executionMinutes($t)], AdjustmentType::cases()),
            'validation' => $sla->validation,
            'approval' => array_map(fn (Role $r) => ['role' => $r->value, 'label' => $r->label(), 'minutes' => $sla->approvalMinutes($r)], Role::chain()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('sla.manage');
        $data = $request->validate([
            'execution' => ['required', 'array'],
            'execution.*' => ['required', 'integer', 'min:1', 'max:100000'],
            'validation' => ['required', 'integer', 'min:1', 'max:100000'],
            'approval' => ['required', 'array'],
            'approval.*' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        $this->settings->putSla(new SlaSettings(
            array_map('intval', $data['execution']), (int) $data['validation'], array_map('intval', $data['approval'])
        ));

        return back()->with('success', 'Tiempos guardados.');
    }
}
