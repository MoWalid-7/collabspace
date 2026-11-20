<?php

namespace App\Http\Middleware;

use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Support\CurrentWorkspace;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentWorkspace
{
    public function __construct(private WorkspaceRepositoryInterface $workspaces) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        $workspaceId = $request->header('X-Workspace-Id');

        if (! is_string($workspaceId) || ! ctype_digit($workspaceId) || (int) $workspaceId < 1) {
            throw ValidationException::withMessages([
                'X-Workspace-Id' => ['A valid X-Workspace-Id header is required.'],
            ]);
        }

        $workspace = $this->workspaces->findForMember((int) $workspaceId, $user->getKey());

        abort_if($workspace === null, 404);

        app(CurrentWorkspace::class)->set($workspace);

        return $next($request);
    }
}
