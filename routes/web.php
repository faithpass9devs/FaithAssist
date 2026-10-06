<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Catechism\ChildController;
use App\Http\Controllers\Catechism\ExternosController;
use App\Http\Controllers\Catechism\ReinscriptionController;
use App\Http\Controllers\Ecclesiastes\ChapelController;
use App\Http\Controllers\Ecclesiastes\ChurchController;
use App\Http\Controllers\Ecclesiastes\DeaneryController;
use App\Http\Controllers\Ecclesiastes\DioceseController;
use App\Http\Controllers\Masses\IncidenceTypeController;
use App\Http\Controllers\Masses\ManualAttendanceController;
use App\Http\Controllers\Masses\MassAttendanceController;
use App\Http\Controllers\Masses\MassAttendanceIncidentController;
use App\Http\Controllers\Masses\MassController;
use App\Http\Controllers\Masses\WeekendController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Operation\LevelController;
use App\Http\Controllers\Operation\PeriodController;
use App\Http\Controllers\Operation\PeriodMovementController;
use App\Http\Controllers\Operation\PeriodMovementTypeController;
use App\Http\Controllers\Regions\CommunityController;
use App\Http\Controllers\Regions\MunicipalityController;
use App\Http\Controllers\Regions\StateController;
use App\Http\Controllers\Security\ModuleController;
use App\Http\Controllers\Security\PermissionController;
use App\Http\Controllers\Security\RoleController;
use App\Http\Controllers\Security\UserController;
use App\Http\Controllers\UserThemeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::prefix('forgot-password')
        ->name('password.recovery.')
        ->middleware('password.recovery.session')
        ->group(function (): void {
            Route::get('/email', [ForgotPasswordController::class, 'showEmailStep'])->name('email.show');
            Route::post('/email', [ForgotPasswordController::class, 'confirmEmail'])->name('email.confirm');

            Route::get('/', [ForgotPasswordController::class, 'showPhoneStep'])->name('phone.show');
            Route::post('/', [ForgotPasswordController::class, 'confirmPhone'])->name('phone.confirm');

            Route::get('/code', [ForgotPasswordController::class, 'showCodeStep'])->name('code.show');
            Route::post('/code', [ForgotPasswordController::class, 'verifyCode'])->name('code.verify');

            Route::get('/reset', [ForgotPasswordController::class, 'showResetStep'])->name('reset.show');
            Route::post('/reset', [ForgotPasswordController::class, 'updatePassword'])->name('reset.update');
        });
});

Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::get('/notificaciones/pendiente', [NotificationController::class, 'pending'])->name('notifications.pending');
    Route::patch('/notificaciones/{notification}/confirmar', [NotificationController::class, 'acknowledge'])->name('notifications.acknowledge');
    Route::get('/cuenta/restringida', function () {
        return Inertia::render('Account/Restricted');
    })->name('account.restricted');

    Route::get('/', function () {
        return Inertia::render('Dashboard');
    })->name('home');

    Route::get('/profile', function () {
        return Inertia::render('Profile/Show');
    })->name('profile.show');

    Route::get('/profile/password', [AuthController::class, 'showChangePasswordForm'])->name('profile.password.edit');
    Route::patch('/profile/password', [AuthController::class, 'updatePassword'])->name('profile.password.update');

    Route::patch('/profile/theme', [UserThemeController::class, 'update'])->name('profile.theme.update');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Catalogos - Regiones
    Route::resource('estados', StateController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['estados' => 'estado']);

    Route::get('/estados/export', [StateController::class, 'export'])->name('estados.export');

    Route::resource('municipios', MunicipalityController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['municipios' => 'municipio']);

    Route::get('/municipios/export', [MunicipalityController::class, 'export'])->name('municipios.export');

    Route::resource('comunidades', CommunityController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['comunidades' => 'comunidad']);

    Route::get('/comunidades/export', [CommunityController::class, 'export'])->name('comunidades.export');

    // Catalogos - Eclesiasticos
    Route::resource('diocesis', DioceseController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['diocesis' => 'diocesis']);

    Route::resource('decanatos', DeaneryController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['decanatos' => 'decanato']);

    Route::resource('parroquias', ChurchController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->parameters(['parroquias' => 'parroquia']);

    Route::resource('capillas', ChapelController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['capillas' => 'capilla']);

    // Operacion
    Route::resource('periodos', PeriodController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['periodos' => 'periodo']);

    Route::resource('periodo-movimientos', PeriodMovementController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['periodo-movimientos' => 'movimiento']);

    Route::resource('tipos-movimientos-periodo', PeriodMovementTypeController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['tipos-movimientos-periodo' => 'tipo_movimiento_periodo']);

    Route::resource('niveles', LevelController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['niveles' => 'nivel']);

    // Misas
    Route::resource('fines-semana-misas', WeekendController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->parameters(['fines-semana-misas' => 'weekend']);

    Route::resource('misas', MassController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->parameters(['misas' => 'misa']);

    Route::resource('asistencias-manuales', ManualAttendanceController::class)
        ->only(['index', 'store'])
        ->parameters(['asistencias-manuales' => 'manualAttendance']);

    Route::get('misas/{misa}/asistencias', [MassAttendanceController::class, 'index'])
        ->name('misas.asistencias.index');
    Route::post('misas/{misa}/asistencias/scan', [MassAttendanceController::class, 'scan'])
        ->name('misas.asistencias.scan');
    Route::post('misas/{misa}/asistencias/status', [MassAttendanceController::class, 'updateCaptureStatus'])
        ->name('misas.asistencias.status');

    Route::resource('tipos-incidencias', IncidenceTypeController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['tipos-incidencias' => 'incidenceType']);

    Route::resource('incidencias-asistencia', MassAttendanceIncidentController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['incidencias-asistencia' => 'attendanceIncident']);

    // Catechism
    Route::resource('children', ChildController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

    Route::get('/children/export', [ChildController::class, 'export'])->name('children.export');
    Route::post('/children/export-pdf', [ChildController::class, 'exportPdfBatch'])->name('children.export-pdf');
    Route::get('/children/export-pdf/{batch}', [ChildController::class, 'pdfBatchStatus'])->name('children.export-pdf.status');
    Route::get('/children/export-pdf/{batch}/download', [ChildController::class, 'downloadPdfBatch'])->name('children.export-pdf.download');
    Route::get('/children/{child}/badge-pdf', [ChildController::class, 'badgePdf'])->name('children.badge-pdf');

    Route::get('reinscripciones/{child}/create', [ReinscriptionController::class, 'create'])
        ->name('reinscripciones.create');
    Route::resource('reinscripciones', ReinscriptionController::class)
        ->only(['index', 'store']);
    Route::get('/reinscripciones/export', [ReinscriptionController::class, 'export'])->name('reinscripciones.export');

    Route::resource('externos', ExternosController::class)
        ->only(['index', 'show'])
        ->parameters(['externos' => 'externo']);

    Route::get('/externos/{externo}/register', [ExternosController::class, 'register'])
        ->name('externos.register');
    Route::post('/externos/{externo}/import', [ExternosController::class, 'import'])
        ->name('externos.import');

    Route::post('/externos/import-batch', [ExternosController::class, 'importBatch'])
        ->name('externos.import-batch');
    Route::get('/externos/import-batch/preview', [ExternosController::class, 'previewImportBatch'])
        ->name('externos.import-batch.preview');
    Route::get('/externos/import-batch/{batch}', [ExternosController::class, 'batchStatus'])
        ->name('externos.import-batch.show');

    // Seguridad
    Route::resource('modulos', ModuleController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['modulos' => 'modulo']);

    Route::resource('permisos', PermissionController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['permisos' => 'permiso']);

    Route::post('/mi-sesion/ubicacion', [\App\Http\Controllers\Security\DeviceSessionController::class, 'updateOwnLocation'])
        ->name('sesion.ubicacion.update');
    Route::get('/dispositivos-sesiones', [\App\Http\Controllers\Security\DeviceSessionController::class, 'index'])
        ->name('dispositivos-sesiones.index');
    Route::get('/dispositivos-sesiones/usuario/{usuario}', [\App\Http\Controllers\Security\DeviceSessionController::class, 'show'])
        ->name('dispositivos-sesiones.usuario.show');
    Route::delete('/dispositivos-sesiones/{session}', [\App\Http\Controllers\Security\DeviceSessionController::class, 'destroy'])
        ->name('dispositivos-sesiones.destroy');
    Route::delete('/dispositivos-sesiones/usuario/{usuario}', [\App\Http\Controllers\Security\DeviceSessionController::class, 'closeUserSessions'])
        ->name('dispositivos-sesiones.usuario.destroy');
    Route::get('/dispositivos-sesiones/usuario/{usuario}/historial', [\App\Http\Controllers\Security\DeviceSessionController::class, 'downloadHistory'])
        ->name('dispositivos-sesiones.usuario.history');
    Route::post('/dispositivos-sesiones/usuario/{usuario}/advertencia', [\App\Http\Controllers\Security\DeviceSessionController::class, 'sendWarning'])
        ->name('dispositivos-sesiones.usuario.warning');
    Route::patch('/dispositivos-sesiones/usuario/{usuario}/estado', [\App\Http\Controllers\Security\DeviceSessionController::class, 'updateAccountStatus'])
        ->name('dispositivos-sesiones.usuario.status');
    Route::delete('/dispositivos-sesiones/usuario/{usuario}/advertencia/{warning}', [\App\Http\Controllers\Security\DeviceSessionController::class, 'deleteWarning'])
        ->name('dispositivos-sesiones.usuario.warning.destroy');

    Route::resource('roles', RoleController::class)->only(['index', 'create', 'store', 'edit', 'update']);

    Route::resource('usuarios', UserController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->parameters(['usuarios' => 'usuario']);

});
