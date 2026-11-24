<?php

namespace App\Providers;

use App\Contracts\GoogleIdentityProvider;
use App\Repositories\Contracts\ActivityRepositoryInterface;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Repositories\Contracts\FileRepositoryInterface;
use App\Repositories\Contracts\GoogleIdentityRepositoryInterface;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\ReportsRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\TeamRepositoryInterface;
use App\Repositories\Contracts\TimeTrackingRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Repositories\Eloquent\ActivityRepository;
use App\Repositories\Eloquent\CommentRepository;
use App\Repositories\Eloquent\DashboardRepository;
use App\Repositories\Eloquent\FileRepository;
use App\Repositories\Eloquent\GoogleIdentityRepository;
use App\Repositories\Eloquent\NotificationRepository;
use App\Repositories\Eloquent\ProjectRepository;
use App\Repositories\Eloquent\ReportsRepository;
use App\Repositories\Eloquent\TaskRepository;
use App\Repositories\Eloquent\TeamRepository;
use App\Repositories\Eloquent\TimeTrackingRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Eloquent\WorkspaceRepository;
use App\Services\ActivityService;
use App\Services\AuthService;
use App\Services\CommentService;
use App\Services\Contracts\ActivityServiceInterface;
use App\Services\Contracts\AuthServiceInterface;
use App\Services\Contracts\CommentServiceInterface;
use App\Services\Contracts\FileServiceInterface;
use App\Services\Contracts\NotificationServiceInterface;
use App\Services\Contracts\ProfileServiceInterface;
use App\Services\Contracts\ProjectServiceInterface;
use App\Services\Contracts\ReportsServiceInterface;
use App\Services\Contracts\TaskServiceInterface;
use App\Services\Contracts\TeamServiceInterface;
use App\Services\Contracts\TimeTrackingServiceInterface;
use App\Services\Contracts\WorkspaceServiceInterface;
use App\Services\FileService;
use App\Services\NotificationRecipientResolver;
use App\Services\NotificationService;
use App\Services\ProfileService;
use App\Services\ProjectService;
use App\Services\ReportsService;
use App\Services\SocialiteGoogleIdentityProvider;
use App\Services\Storage\Contracts\FileStorage;
use App\Services\Storage\LocalFileStorage;
use App\Services\TaskService;
use App\Services\TeamService;
use App\Services\TimeTrackingService;
use App\Services\WorkspaceService;
use Illuminate\Support\ServiceProvider;

class RepositoriesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GoogleIdentityProvider::class, SocialiteGoogleIdentityProvider::class);
        $this->app->bind(FileStorage::class, LocalFileStorage::class);
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
        $this->app->bind(DashboardRepositoryInterface::class, DashboardRepository::class);
        $this->app->bind(TaskRepositoryInterface::class, TaskRepository::class);
        $this->app->bind(CommentRepositoryInterface::class, CommentRepository::class);
        $this->app->bind(FileRepositoryInterface::class, FileRepository::class);
        $this->app->bind(NotificationRepositoryInterface::class, NotificationRepository::class);
        $this->app->bind(ActivityRepositoryInterface::class, ActivityRepository::class);
        $this->app->bind(FileServiceInterface::class, FileService::class);
        $this->app->bind(NotificationServiceInterface::class, NotificationService::class);
        $this->app->bind(ActivityServiceInterface::class, ActivityService::class);
        $this->app->bind(NotificationRecipientResolver::class, NotificationRecipientResolver::class);
        $this->app->bind(GoogleIdentityRepositoryInterface::class, GoogleIdentityRepository::class);
        $this->app->bind(TimeTrackingRepositoryInterface::class, TimeTrackingRepository::class);
        $this->app->bind(TimeTrackingServiceInterface::class, TimeTrackingService::class);
        $this->app->bind(ReportsRepositoryInterface::class, ReportsRepository::class);
        $this->app->bind(ReportsServiceInterface::class, ReportsService::class);
        $this->app->bind(\App\Repositories\Contracts\TimeTrackingRepositoryInterface::class, \App\Repositories\Eloquent\TimeTrackingRepository::class);
        $this->app->bind(\App\Services\Contracts\TimeTrackingServiceInterface::class, \App\Services\TimeTrackingService::class);
        $this->app->bind(\App\Repositories\Contracts\ReportsRepositoryInterface::class, \App\Repositories\Eloquent\ReportsRepository::class);
        $this->app->bind(\App\Services\Contracts\ReportsServiceInterface::class, \App\Services\ReportsService::class);
    }
}
