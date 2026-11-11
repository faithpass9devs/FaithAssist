<?php

namespace App\Providers;

use App\Models\Catechism\Child;
use App\Models\DeviceSession;
use App\Models\Ecclesiastes\Chapel;
use App\Models\Ecclesiastes\Church;
use App\Models\Ecclesiastes\Deanery;
use App\Models\Ecclesiastes\Diocese;
use App\Models\External\ExternalChild;
use App\Models\Masses\IncidenceType;
use App\Models\Masses\ManualAttendance;
use App\Models\Masses\Mass;
use App\Models\Masses\MassAttendance;
use App\Models\Masses\MassAttendanceIncident;
use App\Models\Masses\Weekend;
use App\Models\Module;
use App\Models\Operation\Level;
use App\Models\Operation\Period;
use App\Models\Operation\PeriodMovement;
use App\Models\Operation\PeriodMovementType;
use App\Models\Regions\Community;
use App\Models\Regions\Municipality;
use App\Models\Regions\State;
use App\Models\Settings\Setting;
use App\Policies\ChapelPolicy;
use App\Policies\ChildPolicy;
use App\Policies\ChurchPolicy;
use App\Policies\CommunityPolicy;
use App\Policies\DeaneryPolicy;
use App\Policies\DeviceSessionPolicy;
use App\Policies\DiocesePolicy;
use App\Policies\ExternosPolicy;
use App\Policies\IncidenceTypePolicy;
use App\Policies\LevelPolicy;
use App\Policies\ManualAttendancePolicy;
use App\Policies\MassAttendanceIncidentPolicy;
use App\Policies\MassAttendancePolicy;
use App\Policies\MassPolicy;
use App\Policies\ModulePolicy;
use App\Policies\MunicipalityPolicy;
use App\Policies\PeriodMovementPolicy;
use App\Policies\PeriodMovementTypePolicy;
use App\Policies\PeriodPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\SettingsPolicy;
use App\Policies\StatePolicy;
use App\Policies\WeekendPolicy;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->prohibitDestructiveCommandsAgainstRemoteDatabases();

        Gate::policy(Module::class, ModulePolicy::class);
        Gate::policy(DeviceSession::class, DeviceSessionPolicy::class);
        Gate::policy(Child::class, ChildPolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(State::class, StatePolicy::class);
        Gate::policy(Municipality::class, MunicipalityPolicy::class);
        Gate::policy(Community::class, CommunityPolicy::class);
        Gate::policy(Diocese::class, DiocesePolicy::class);
        Gate::policy(Deanery::class, DeaneryPolicy::class);
        Gate::policy(Church::class, ChurchPolicy::class);
        Gate::policy(Chapel::class, ChapelPolicy::class);
        Gate::policy(Level::class, LevelPolicy::class);
        Gate::policy(Period::class, PeriodPolicy::class);
        Gate::policy(PeriodMovement::class, PeriodMovementPolicy::class);
        Gate::policy(PeriodMovementType::class, PeriodMovementTypePolicy::class);
        Gate::policy(Weekend::class, WeekendPolicy::class);
        Gate::policy(Mass::class, MassPolicy::class);
        Gate::policy(MassAttendance::class, MassAttendancePolicy::class);
        Gate::policy(ManualAttendance::class, ManualAttendancePolicy::class);
        Gate::policy(IncidenceType::class, IncidenceTypePolicy::class);
        Gate::policy(MassAttendanceIncident::class, MassAttendanceIncidentPolicy::class);
        Gate::policy(ExternalChild::class, ExternosPolicy::class);
        Gate::policy(Setting::class, SettingsPolicy::class);
    }

    /**
     * Bloquea migrate:fresh, migrate:refresh, migrate:reset y db:wipe cuando la
     * conexión seleccionada para el comando apunta a un servidor remoto (p. ej. Hostinger).
     * Para permitirlos de forma explícita: ALLOW_DESTRUCTIVE_DB_COMMANDS=true.
     */
    private function prohibitDestructiveCommandsAgainstRemoteDatabases(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if (! in_array($event->command, [
                'db:wipe',
                'migrate:fresh',
                'migrate:refresh',
                'migrate:reset',
                'migrate:rollback',
            ], true)) {
                return;
            }

            if (config('database.allow_destructive_commands')) {
                DB::prohibitDestructiveCommands(false);

                return;
            }

            $connection = $event->input->getOption('database') ?: config('database.default');
            $configuration = DB::connection($connection)->getConfig();
            $driver = $configuration['driver'] ?? null;
            $host = (string) ($configuration['host'] ?? '');

            $isLocal = $driver === 'sqlite'
                || in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1'], true);

            DB::prohibitDestructiveCommands(! $isLocal);
        });
    }
}
