<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\ProjectPolicy;
use App\Policies\TeamPolicy;
use App\Policies\WorkspacePolicy;
use App\Support\CurrentWorkspace;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentWorkspace::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(Team::class, TeamPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);

        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            $url = rtrim((string) config('app.frontend_url'), '/').'/reset-password';

            return $url.'?'.http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });
    }
}
