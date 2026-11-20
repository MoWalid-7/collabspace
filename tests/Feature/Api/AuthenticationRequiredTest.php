<?php

dataset('protected API endpoints', [
    'logout' => ['POST', '/api/v1/auth/logout'],
    'current user' => ['GET', '/api/v1/auth/me'],
    'workspace list' => ['GET', '/api/v1/workspaces'],
    'workspace creation' => ['POST', '/api/v1/workspaces'],
    'workspace detail' => ['GET', '/api/v1/workspaces/1', ['X-Workspace-Id' => '1']],
    'workspace update' => ['PUT', '/api/v1/workspaces/1', ['X-Workspace-Id' => '1']],
    'member invitation' => ['POST', '/api/v1/workspaces/1/members', ['X-Workspace-Id' => '1']],
    'member role change' => ['PATCH', '/api/v1/workspaces/1/members/1', ['X-Workspace-Id' => '1']],
    'member removal' => ['DELETE', '/api/v1/workspaces/1/members/1', ['X-Workspace-Id' => '1']],
    'team list' => ['GET', '/api/v1/teams'],
    'team creation' => ['POST', '/api/v1/teams'],
    'team detail' => ['GET', '/api/v1/teams/1'],
    'team update' => ['PUT', '/api/v1/teams/1'],
    'team deletion' => ['DELETE', '/api/v1/teams/1'],
    'team member addition' => ['POST', '/api/v1/teams/1/members'],
    'team member update' => ['PATCH', '/api/v1/teams/1/members/1'],
    'team member removal' => ['DELETE', '/api/v1/teams/1/members/1'],
    'project list' => ['GET', '/api/v1/projects'],
    'project creation' => ['POST', '/api/v1/projects'],
    'project detail' => ['GET', '/api/v1/projects/1'],
    'project update' => ['PUT', '/api/v1/projects/1'],
    'project deletion' => ['DELETE', '/api/v1/projects/1'],
    'project member list' => ['GET', '/api/v1/projects/1/members'],
    'project member addition' => ['POST', '/api/v1/projects/1/members'],
    'project member removal' => ['DELETE', '/api/v1/projects/1/members/1'],
    'project team attachment' => ['POST', '/api/v1/projects/1/teams'],
    'project team detachment' => ['DELETE', '/api/v1/projects/1/teams/1'],
    'profile read' => ['GET', '/api/v1/profile'],
    'profile update' => ['PUT', '/api/v1/profile'],
    'password update' => ['PUT', '/api/v1/profile/password'],
    'avatar upload' => ['POST', '/api/v1/profile/avatar'],
]);

it('requires authentication for protected API endpoints', function (
    string $method,
    string $uri,
    array $headers = [],
) {
    $this->json($method, $uri, [], $headers)
        ->assertUnauthorized()
        ->assertJsonPath('success', false);
})->with('protected API endpoints');
