<?php

namespace App\Http\Controllers\Review;

use App\Domain\Settings\SettingsRepository;
use App\Domain\Time\BusinessHours;
use App\Domain\Time\BusinessTime;
use App\Http\Controllers\Controller;
use App\Models\ScheduleException;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** "Horario": the weekly calendar and its one-day exceptions, for Ogilvy. */
class ScheduleController extends Controller
{
    private const DAYS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    public function __construct(private readonly SettingsRepository $settings, private readonly BusinessTime $time) {}

    public function edit(): Response
    {
        $this->authorize('sla.manage');
        $hours = $this->settings->businessHours();
        $today = $this->time->now()->startOfDay();

        $exceptions = ScheduleException::with('users', 'creator')->orderByDesc('date')->get()->map(fn (ScheduleException $e) => [
            'id' => $e->id, 'date' => $e->date->format('Y-m-d'), 'kind' => $e->kind, 'kind_label' => $e->kindLabel(),
            'from' => $e->fromLabel(), 'to' => $e->toLabel(), 'note' => $e->note,
            'users' => $e->users->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values(),
            'created_by' => $e->creator?->name, 'past' => $e->date->lt($today),
        ]);

        return Inertia::render('settings/schedule', [
            'week' => collect(self::DAYS)->map(fn ($label, $d) => [
                'day' => $d, 'label' => $label, 'open' => $hours->window($d) !== null,
                'from' => BusinessHours::formatHour($hours->window($d)['from'] ?? 8.5),
                'to' => BusinessHours::formatHour($hours->window($d)['to'] ?? 18.0),
            ])->values(),
            'actions_outside_hours' => $this->settings->actionsOutsideHours(),
            'exceptions' => $exceptions,
            'people' => User::where('is_active', true)->orderBy('name')->get()->map(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'side' => $u->isOgilvySide() ? 'Ogilvy' : 'Cencosud', 'role_label' => $u->role()?->short(),
            ]),
            'today' => $today->format('Y-m-d'),
        ]);
    }

    public function updateWeek(Request $request): RedirectResponse
    {
        $this->authorize('sla.manage');
        $data = $request->validate([
            'week' => ['required', 'array', 'size:7'],
            'week.*.day' => ['required', 'integer', 'between:1,7'],
            'week.*.open' => ['required', 'boolean'],
            'week.*.from' => ['required', 'date_format:H:i'],
            'week.*.to' => ['required', 'date_format:H:i'],
            'actions_outside_hours' => ['required', 'boolean'],
        ]);
        $windows = [];
        foreach ($data['week'] as $row) {
            $from = BusinessHours::parseHour($row['from']);
            $to = BusinessHours::parseHour($row['to']);
            if ($row['open'] && $to <= $from) {
                return back()->with('error', 'El cierre debe ser después de la apertura ('.self::DAYS[$row['day']].').');
            }
            $windows[(int) $row['day']] = $row['open'] ? ['from' => $from, 'to' => $to] : null;
        }
        if (array_filter($windows) === []) {
            return back()->with('error', 'Deja al menos un día abierto.');
        }
        $this->settings->putBusinessHours(BusinessHours::weekly($windows));
        $this->settings->putActionsOutsideHours((bool) $data['actions_outside_hours']);
        $this->time->refresh();

        return back()->with('success', 'Horario semanal guardado.');
    }

    public function storeException(Request $request): RedirectResponse
    {
        $this->authorize('sla.manage');
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'kind' => ['required', Rule::in(['extend', 'closed', 'users'])],
            'from' => ['required_unless:kind,closed', 'nullable', 'date_format:H:i'],
            'to' => ['required_unless:kind,closed', 'nullable', 'date_format:H:i', 'after:from'],
            'users' => ['required_if:kind,users', 'array'],
            'users.*' => ['integer', Rule::exists('users', 'id')],
            'note' => ['nullable', 'string', 'max:160'],
        ], [
            'users.required_if' => 'Elige al menos una persona.',
            'to.after' => 'La hora final debe ser después de la inicial.',
        ], ['date' => 'fecha', 'from' => 'desde', 'to' => 'hasta', 'note' => 'nota']);

        $mins = fn (?string $hm) => $hm ? (int) round(BusinessHours::parseHour($hm) * 60) : null;
        $e = ScheduleException::create([
            'date' => $data['date'], 'kind' => $data['kind'],
            'from_minutes' => $data['kind'] === 'closed' ? null : $mins($data['from']),
            'to_minutes' => $data['kind'] === 'closed' ? null : $mins($data['to']),
            'note' => $data['note'] ?? null, 'created_by' => $request->user()->id,
        ]);
        if ($data['kind'] === 'users') {
            $e->users()->sync($data['users']);
        }
        $this->time->refresh();

        return back()->with('success', 'Excepción guardada para el '.$e->date->locale('es')->isoFormat('dddd D [de] MMMM').'.');
    }

    public function destroyException(ScheduleException $exception): RedirectResponse
    {
        $this->authorize('sla.manage');
        $exception->delete();
        $this->time->refresh();

        return back()->with('success', 'Excepción eliminada.');
    }
}
