<?php

namespace App\Providers;

use App\Contracts\GoogleIdentityProvider;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Repositories\Contracts\FileStorageRepositoryInterface;
use App\Repositories\Contracts\GoogleIdentityRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\TeamRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Repositories\Eloquent\CommentRepository;
use App\Repositories\Eloquent\GoogleIdentityRepository;
use App\Repositories\Eloquent\LocalFileStorageRepository;
use App\Repositories\Eloquent\ProjectRepository;
use App\Repositories\Eloquent\TaskRepository;
use App\Repositories\Eloquent\TeamRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Eloquent\WorkspaceRepository;
use App\Services\AuthService;
use App\Services\CommentService;
use App\Services\Contracts\AuthServiceInterface;
use App\Services\Contracts\CommentServiceInterface;
use App\Services\Contracts\ProfileServiceInterface;
use App\Services\Contracts\ProjectServiceInterface;
use App\Services\Contracts\TaskServiceInterface;
use App\Services\Contracts\TeamServiceInterface;
use App\Services\Contracts\WorkspaceServiceInterface;
use App\Services\ProfileService;
use App\Services\ProjectService;
use App\Services\SocialiteGoogleIdentityProvider;
use App\Services\TaskService;
use App\Services\TeamService;
use App\Services\WorkspaceService;
use Illuminate\Support\ServiceProvider;

class RepositoriesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GoogleIdentityProvider::class, SocialiteGoogleIdentityProvider::class);
        $this->app->bind(FileStorageRepositoryInterface::class, LocalFileStorageRepository::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(ProfileServiceInterface::class, ProfileService::class);
        $this->app->bind(WorkspaceServiceInterface::class, WorkspaceService::class);
        $this->app->bind(TeamServiceInterface::class, TeamService::class);
        $this->app->bind(ProjectServiceInterface::class, ProjectService::class);
        $this->app->bind(TaskServiceInterface::class, TaskService::class);
        $this->app->bind(CommentServiceInterface::class, CommentService::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(WorkspaceRepositoryInterface::class, WorkspaceRepository::class);
        $this->app->bind(TeamRepositoryInterface::class, TeamRepository::class);
        $this->app->bind(ProjectRepositoryInterface::class, ProjectRepository::class);
        $this->app->bind(TaskRepositoryInterface::class, TaskRepository::class);
        $this->app->bind(CommentRepositoryInterface::class, CommentRepository::class);
        $this->app->bind(GoogleIdentityRepositoryInterface::class, GoogleIdentityRepository::class);
    }
}
