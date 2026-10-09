<?php

use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Projects\BoardListController;
use App\Http\Controllers\Projects\CalendarController;
use App\Http\Controllers\Projects\CardAttachmentController;
use App\Http\Controllers\Projects\CardCommentController;
use App\Http\Controllers\Projects\CardController;
use App\Http\Controllers\Projects\CustomFieldController;
use App\Http\Controllers\Projects\DashboardController;
use App\Http\Controllers\Projects\DeliverableController;
use App\Http\Controllers\Projects\IntakeController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Projects\ProjectLabelController;
use App\Http\Controllers\Projects\ProjectMemberController;
use App\Http\Controllers\Projects\SubtaskController;
use App\Http\Controllers\Review\AdjustmentController;
use App\Http\Controllers\Review\CatalogController;
use App\Http\Controllers\Review\CatalogManageController;
use App\Http\Controllers\Review\ImageController;
use App\Http\Controllers\Review\MonthlyReportController;
use App\Http\Controllers\Review\NotificationController;
use App\Http\Controllers\Review\PageController;
use App\Http\Controllers\Review\ReportController;
use App\Http\Controllers\Review\ScheduleController;
use App\Http\Controllers\Review\SlaSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'catalogs.index' : 'login'))->name('home');
Route::get('salud', HealthController::class)->name('health');

Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::get('dashboard', fn () => redirect()->route(auth()->user()->can('projects.access') ? 'panel' : 'catalogs.index'))->name('dashboard');

    Route::get('imagenes/{path}', [ImageController::class, 'show'])->where('path', '.*')->name('images.show');
    Route::get('clientes/{client}/logo', [ClientController::class, 'showLogo'])->name('clients.logo');

    Route::get('catalogos', [CatalogController::class, 'index'])->name('catalogs.index');
    Route::get('catalogos/nuevo', [CatalogController::class, 'create'])->name('catalogs.create');
    Route::post('catalogos', [CatalogController::class, 'store'])->name('catalogs.store');
    Route::get('catalogos/{catalog:slug}', [CatalogController::class, 'show'])->name('catalogs.show');
    Route::middleware('can:catalogs.manage')->group(function () {
        Route::get('catalogos/{catalog:slug}/editar', [CatalogManageController::class, 'edit'])->name('catalogs.edit');
        Route::put('catalogos/{catalog:slug}', [CatalogManageController::class, 'update'])->name('catalogs.update');
        Route::post('catalogos/{catalog:slug}/paginas', [CatalogManageController::class, 'addPages'])->name('catalogs.pages.add');
        Route::put('catalogos/{catalog:slug}/orden', [CatalogManageController::class, 'reorder'])->name('catalogs.reorder');
        Route::put('catalogos/{catalog:slug}/paginas/{page:number}/sectores', [CatalogManageController::class, 'pageSectors'])->scopeBindings()->name('catalogs.pages.sectors');
        Route::post('catalogos/{catalog:slug}/enviar', [CatalogManageController::class, 'submit'])->name('catalogs.submit');
        Route::post('catalogos/{catalog:slug}/paginas/{page:number}/enviar', [CatalogManageController::class, 'submit'])->scopeBindings()->name('catalogs.pages.submit');
        Route::post('catalogos/{catalog:slug}/archivar', [CatalogManageController::class, 'archive'])->name('catalogs.archive');
        Route::post('catalogos/{catalog:slug}/reactivar', [CatalogManageController::class, 'unarchive'])->name('catalogs.unarchive');
        Route::delete('catalogos/{catalog:slug}', [CatalogManageController::class, 'destroy'])->name('catalogs.destroy');
    });
    Route::get('catalogos/{catalog:slug}/reporte', [ReportController::class, 'show'])->name('catalogs.report');
    Route::get('catalogos/{catalog:slug}/reporte.csv', [ReportController::class, 'csv'])->name('catalogs.report.csv');

    Route::scopeBindings()->prefix('catalogos/{catalog:slug}/paginas/{page:number}')->name('pages.')->group(function () {
        Route::get('/', [PageController::class, 'show'])->name('show');
        Route::post('validar', [PageController::class, 'validate'])->name('validate');
        Route::post('imprenta', [PageController::class, 'approvePrint'])->name('print');
        Route::post('defecto', [PageController::class, 'defect'])->name('defect');
        Route::post('versiones', [PageController::class, 'storeVersion'])->name('versions.store');
        Route::post('ajustes', [AdjustmentController::class, 'store'])->name('adjustments.store');
    });

    Route::middleware('throttle:60,1')->group(function () {
        Route::post('ajustes/{adjustment}/validar', [AdjustmentController::class, 'validateStep'])->name('adjustments.validate');
        Route::post('ajustes/{adjustment}/resolver', [AdjustmentController::class, 'resolve'])->name('adjustments.resolve');
        Route::post('ajustes/{adjustment}/respuestas', [AdjustmentController::class, 'reply'])->name('adjustments.reply');
        Route::put('ajustes/{adjustment}', [AdjustmentController::class, 'update'])->name('adjustments.update');
        Route::delete('ajustes/{adjustment}', [AdjustmentController::class, 'destroy'])->name('adjustments.destroy');
        Route::get('ajustes/{adjustment}/adjuntos/{attachment}', [AdjustmentController::class, 'attachment'])->name('adjustments.attachments.show');
    });

    Route::get('notificaciones', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notificaciones/pulso', [NotificationController::class, 'pulse'])->name('notifications.pulse');
    Route::get('reportes', [MonthlyReportController::class, 'show'])->name('reports.monthly');
    Route::get('reportes.csv', [MonthlyReportController::class, 'csv'])->name('reports.monthly.csv');

    Route::get('configuracion', fn () => redirect()->route(auth()->user()->can('sla.manage') ? 'sla.edit' : 'admin.users.index'))->name('config');

    Route::get('horario', [ScheduleController::class, 'edit'])->name('schedule.edit');
    Route::put('horario', [ScheduleController::class, 'updateWeek'])->name('schedule.update');
    Route::post('horario/excepciones', [ScheduleController::class, 'storeException'])->name('schedule.exceptions.store');
    Route::delete('horario/excepciones/{exception}', [ScheduleController::class, 'destroyException'])->name('schedule.exceptions.destroy');

    Route::get('tiempos', [SlaSettingsController::class, 'edit'])->name('sla.edit');
    Route::put('tiempos', [SlaSettingsController::class, 'update'])->name('sla.update');

    /* Boards: Ogilvy only. */
    /* The calendar is shared: Ogilvy works from it, the client only looks at the dates of its projects. */
    Route::get('calendario', CalendarController::class)->name('calendar');

    Route::middleware('can:projects.access')->group(function () {
        Route::get('panel', DashboardController::class)->name('panel');
        Route::get('proyectos', [ProjectController::class, 'index'])->name('projects.index');
        Route::post('proyectos', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('proyectos/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::put('proyectos/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('proyectos/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
        Route::post('proyectos/{project}/archivar', [ProjectController::class, 'archive'])->name('projects.archive');
        Route::post('proyectos/{project}/reactivar', [ProjectController::class, 'unarchive'])->name('projects.unarchive');
        Route::post('proyectos/{project}/miembros', [ProjectMemberController::class, 'store'])->name('projects.members.store');
        Route::put('proyectos/{project}/miembros/{user}', [ProjectMemberController::class, 'update'])->name('projects.members.update');
        Route::delete('proyectos/{project}/miembros/{user}', [ProjectMemberController::class, 'destroy'])->name('projects.members.destroy');
        Route::post('proyectos/{project}/etiquetas', [ProjectLabelController::class, 'store'])->name('projects.labels.store');
        Route::put('etiquetas/{label}', [ProjectLabelController::class, 'update'])->name('labels.update');
        Route::delete('etiquetas/{label}', [ProjectLabelController::class, 'destroy'])->name('labels.destroy');
        Route::post('proyectos/{project}/listas', [BoardListController::class, 'store'])->name('projects.lists.store');
        Route::put('listas/{list}', [BoardListController::class, 'update'])->name('lists.update');
        Route::put('listas/{list}/mover', [BoardListController::class, 'move'])->name('lists.move');
        Route::delete('listas/{list}', [BoardListController::class, 'destroy'])->name('lists.destroy');
        Route::post('proyectos/{project}/tarjetas', [CardController::class, 'store'])->name('projects.cards.store');
        Route::put('tarjetas/{card}', [CardController::class, 'update'])->name('cards.update');
        Route::put('tarjetas/{card}/mover', [CardController::class, 'move'])->name('cards.move');
        Route::put('tarjetas/{card}/completar', [CardController::class, 'complete'])->name('cards.complete');
        Route::delete('tarjetas/{card}', [CardController::class, 'destroy'])->name('cards.destroy');
        Route::put('tarjetas/{card}/campos', [CardController::class, 'fields'])->name('cards.fields');
        Route::post('tarjetas/{card}/duplicar', [CardController::class, 'duplicate'])->name('cards.duplicate');
        Route::post('tarjetas/{card}/campos/{field}/imagen', [CardController::class, 'fieldImage'])->name('cards.fields.image');
        Route::post('tarjetas/{card}/subtareas', [SubtaskController::class, 'store'])->name('cards.subtasks.store');
        Route::put('subtareas/{subtask}', [SubtaskController::class, 'update'])->name('subtasks.update');
        Route::put('subtareas/{subtask}/mover', [SubtaskController::class, 'move'])->name('subtasks.move');
        Route::post('subtareas/{subtask}/comentarios', [SubtaskController::class, 'comment'])->name('subtasks.comments.store');
        Route::delete('subtareas/{subtask}', [SubtaskController::class, 'destroy'])->name('subtasks.destroy');
        Route::post('ingresos/{intake}/aprobar', [IntakeController::class, 'approve'])->name('intakes.approve');
        Route::post('ingresos/{intake}/descartar', [IntakeController::class, 'dismiss'])->name('intakes.dismiss');
        Route::post('subtareas/{subtask}/entregables', [DeliverableController::class, 'store'])->name('subtasks.deliverables.store');
        Route::put('entregables/{deliverable}', [DeliverableController::class, 'update'])->name('deliverables.update');
        Route::delete('entregables/{deliverable}', [DeliverableController::class, 'destroy'])->name('deliverables.destroy');
        Route::post('tarjetas/{card}/comentarios', [CardCommentController::class, 'store'])->name('cards.comments.store');
        Route::put('comentarios/{comment}', [CardCommentController::class, 'update'])->name('comments.update');
        Route::delete('comentarios/{comment}', [CardCommentController::class, 'destroy'])->name('comments.destroy');
        Route::post('tarjetas/{card}/adjuntos', [CardAttachmentController::class, 'store'])->name('cards.attachments.store');
        Route::get('adjuntos/{attachment}', [CardAttachmentController::class, 'show'])->name('attachments.show');
        Route::delete('adjuntos/{attachment}', [CardAttachmentController::class, 'destroy'])->name('attachments.destroy');
        Route::post('proyectos/{project}/campos', [CustomFieldController::class, 'store'])->name('projects.fields.store');
        Route::put('proyectos/{project}/campos/orden', [CustomFieldController::class, 'reorder'])->name('projects.fields.reorder');
        Route::put('campos/{field}', [CustomFieldController::class, 'update'])->name('fields.update');
        Route::delete('campos/{field}', [CustomFieldController::class, 'destroy'])->name('fields.destroy');
    });

    Route::prefix('admin')->name('admin.')->middleware('can:users.manage')->group(function () {
        Route::resource('usuarios', UserController::class)
            ->names('users')
            ->parameters(['usuarios' => 'usuario'])
            ->except(['show']);
        Route::get('clientes', [ClientController::class, 'index'])->name('clients.index');
        Route::post('clientes', [ClientController::class, 'store'])->name('clients.store');
        Route::put('clientes/{client}', [ClientController::class, 'update'])->name('clients.update');
        Route::post('clientes/{client}/logo', [ClientController::class, 'logo'])->name('clients.logo.store');
        Route::delete('clientes/{client}/logo', [ClientController::class, 'destroyLogo'])->name('clients.logo.destroy');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
