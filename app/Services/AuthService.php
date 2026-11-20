<?php

namespace App\Services;

use App\Data\Auth\ForgotPasswordData;
use App\Data\Auth\GoogleAccessTokenData;
use App\Data\Auth\LoginData;
use App\Data\Auth\RegisterUserData;
use App\Data\Auth\ResetPasswordData;
use App\Data\AuthenticatedUserData;
use App\Data\IssuedApiTokenData;
use App\Enums\AvailabilityStatus;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Contracts\GoogleIdentityRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\Contracts\AuthServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService implements AuthServiceInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
        private WorkspaceRepositoryInterface $workspaces,
        private GoogleIdentityRepositoryInterface $googleIdentities,
    ) {}

    public function register(RegisterUserData $data): AuthenticatedUserData
    {
        return DB::transaction(function () use ($data): AuthenticatedUserData {
            $user = $this->users->createUser([
                'name' => $data->fullName,
                'email' => $data->email,
                'password' => $data->password,
                'availability_status' => AvailabilityStatus::Available,
            ]);

            $workspace = $this->createDefaultWorkspace($data->fullName, $user);

            $this->workspaces->addMember($workspace, $user, WorkspaceRole::Admin);

            return new AuthenticatedUserData(
                $user,
                $this->issueToken($user),
            );
        });
    }

    public function login(LoginData $data): AuthenticatedUserData
    {
        $user = $this->users->findByEmail($data->email);

        if ($user === null || ! Hash::check($data->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return new AuthenticatedUserData(
            $user,
            $this->issueToken($user, $data->rememberMe),
        );
    }

    public function google(GoogleAccessTokenData $data): AuthenticatedUserData
    {
        $identity = $this->googleIdentities->findByAccessToken($data->accessToken);

        return DB::transaction(function () use ($identity): AuthenticatedUserData {
            $user = $this->users->findByGoogleId($identity->id);

            if ($user === null) {
                if ($this->users->emailExists($identity->email)) {
                    throw ValidationException::withMessages([
                        'email' => ['This email is already registered. Sign in with your password first.'],
                    ]);
                }

                $user = $this->users->createUser([
                    'name' => $identity->name,
                    'email' => $identity->email,
                    'password' => Str::random(64),
                    'google_id' => $identity->id,
                    'availability_status' => AvailabilityStatus::Available,
                ]);
                $workspace = $this->createDefaultWorkspace($identity->name, $user);
                $this->workspaces->addMember($workspace, $user, WorkspaceRole::Admin);
            }

            return new AuthenticatedUserData($user, $this->issueToken($user));
        });
    }

    public function logout(User $user): void
    {
        $this->users->revokeCurrentToken($user);
    }

    public function currentUser(int $userId): User
    {
        return $this->users->findUserOrFail($userId);
    }

    public function forgotPassword(ForgotPasswordData $data): void
    {
        $this->users->sendPasswordResetLink($data->email);
    }

    public function resetPassword(ResetPasswordData $data): void
    {
        $passwordWasReset = $this->users->resetPassword($data->credentials());

        if (! $passwordWasReset) {
            throw ValidationException::withMessages([
                'email' => ['The password reset link is invalid or has expired.'],
            ]);
        }
    }

    private function issueToken(User $user, bool $rememberMe = false): IssuedApiTokenData
    {
        $expiresAt = CarbonImmutable::now()->addDays($rememberMe ? 30 : 1);

        return $this->users->createApiToken($user, $expiresAt);
    }

    private function createDefaultWorkspace(string $name, User $owner): Workspace
    {
        $workspaceName = $name."'s Workspace";
        $slug = Str::slug($workspaceName);

        if ($this->workspaces->slugExists($slug)) {
            $slug .= '-'.Str::lower(Str::random(8));
        }

        return $this->workspaces->createWorkspace([
            'name' => $workspaceName,
            'slug' => $slug,
            'owner_id' => $owner->getKey(),
        ]);
    }
}
