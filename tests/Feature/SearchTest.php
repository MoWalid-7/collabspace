<?php

use App\Enums\TaskStatus;
use App\Enums\WorkspaceRole;
use App\Models\File;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;

function phaseFiveSearchWorkspace(User $owner): Workspace
{
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => WorkspaceRole::Admin->value]);

    return $workspace;
}

/**
 * @param  list<User>  $members
 */
function phaseFiveSearchProject(
    Workspace $workspace,
    User $creator,
    string $name,
    array $members = [],
): Project {
    $project = Project::factory()->create([
        'workspace_id' => $workspace->id,
        'created_by' => $creator->id,
        'name' => $name,
    ]);
    $project->members()->attach($creator->id);

    foreach ($members as $member) {
        $project->members()->attach($member->id);
    }

    return $project;
}

function phaseFiveSearchTask(Project $project, User $creator, string $title): Task
{
    return Task::factory()->create([
        'workspace_id' => $project->workspace_id,
        'project_id' => $project->id,
        'created_by' => $creator->id,
        'title' => $title,
        'status' => TaskStatus::Todo,
    ]);
}

it('trims and validates search terms and filters the selected type', function () {
    $admin = User::factory()->create(['name' => 'Workspace owner']);
    $workspace = phaseFiveSearchWorkspace($admin);
    phaseFiveSearchProject($workspace, $admin, 'Alpha Launch');
    phaseFiveSearchProject($workspace, $admin, 'Alpine Release');
    phaseFiveSearchProject($workspace, $admin, 'Delta');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/search?q=%20al%20&types%5B%5D=projects&limit=1')
        ->assertOk()
        ->assertJsonCount(1, 'data.results.projects')
        ->assertJsonPath('data.results.tasks', [])
        ->assertJsonPath('data.results.files', [])
        ->assertJsonPath('data.results.teams', [])
        ->assertJsonPath('data.results.members', [])
        ->assertJsonPath('data.results.projects.0.type', 'project');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/search?q=al')
        ->assertOk()
        ->assertJsonPath('data.results.projects.0.title', 'Alpha Launch')
        ->assertJsonPath('data.results.projects.1.title', 'Alpine Release')
        ->assertJsonPath('data.results.tasks', [])
        ->assertJsonPath('data.results.files', [])
        ->assertJsonPath('data.results.teams', [])
        ->assertJsonPath('data.results.members', []);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/search?q=%20a%20')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['q']);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/search?q=Al&types%5B%5D=messages')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['types.0']);

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/search?q='.str_repeat('a', 101))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['q']);
});

it('limits each search type to five by default and at most twenty results', function () {
    $admin = User::factory()->create();
    $workspace = phaseFiveSearchWorkspace($admin);

    foreach (range(1, 21) as $index) {
        phaseFiveSearchProject($workspace, $admin, 'Matching project '.$index);
    }

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/search?q=Matching&types%5B%5D=projects')
        ->assertOk()
        ->assertJsonCount(5, 'data.results.projects');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/search?q=Matching&types%5B%5D=projects&limit=20')
        ->assertOk()
        ->assertJsonCount(20, 'data.results.projects');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/search?q=Matching&limit=21')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['limit']);
});

it('escapes percent, underscore, and backslash in search patterns', function () {
    $admin = User::factory()->create();
    $workspace = phaseFiveSearchWorkspace($admin);
    phaseFiveSearchProject($workspace, $admin, 'Release %% marker');
    phaseFiveSearchProject($workspace, $admin, 'Release other marker');
    phaseFiveSearchProject($workspace, $admin, 'Literal %a marker');
    phaseFiveSearchProject($workspace, $admin, 'Literal Xa marker');
    phaseFiveSearchProject($workspace, $admin, 'Feature A_ literal');
    phaseFiveSearchProject($workspace, $admin, 'Feature AB literal');
    phaseFiveSearchProject($workspace, $admin, 'folder\\_name');
    phaseFiveSearchProject($workspace, $admin, 'folderXname');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/search?q='.rawurlencode('%%').'&types%5B%5D=projects')
        ->assertOk()
        ->assertJsonCount(1, 'data.results.projects')
        ->assertJsonPath('data.results.projects.0.title', 'Release %% marker');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/search?q='.rawurlencode('%a').'&types%5B%5D=projects')
        ->assertOk()
        ->assertJsonCount(1, 'data.results.projects')
        ->assertJsonPath('data.results.projects.0.title', 'Literal %a marker');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/search?q='.rawurlencode('A_').'&types%5B%5D=projects')
        ->assertOk()
        ->assertJsonCount(1, 'data.results.projects')
        ->assertJsonPath('data.results.projects.0.title', 'Feature A_ literal');

    $this->actingAs($admin, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/search?q='.rawurlencode('\\_').'&types%5B%5D=projects')
        ->assertOk()
        ->assertJsonCount(1, 'data.results.projects')
        ->assertJsonPath('data.results.projects.0.title', 'folder\\_name');
});

it('searches only visible and non-deleted projects, tasks, and files', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $otherOwner = User::factory()->create();
    $workspace = phaseFiveSearchWorkspace($owner);
    $workspace->members()->attach($member->id, ['role' => WorkspaceRole::Member->value]);
    $visibleProject = phaseFiveSearchProject($workspace, $owner, 'Needle visible project', [$member]);
    $hiddenProject = phaseFiveSearchProject($workspace, $owner, 'Needle hidden project');
    $visibleTask = phaseFiveSearchTask($visibleProject, $owner, 'Needle visible task');
    $deletedTask = phaseFiveSearchTask($visibleProject, $owner, 'Needle deleted task');
    $deletedTask->delete();
    phaseFiveSearchTask($hiddenProject, $owner, 'Needle hidden task');
    $deletedProject = phaseFiveSearchProject($workspace, $owner, 'Needle deleted project', [$member]);
    phaseFiveSearchTask($deletedProject, $owner, 'Needle deleted project task');
    $deletedProject->delete();
    $visibleFile = File::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $visibleProject->id,
        'uploaded_by' => $owner->id,
        'name' => 'Needle visible file',
    ]);
    $deletedFile = File::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $visibleProject->id,
        'uploaded_by' => $owner->id,
        'name' => 'Needle deleted file',
    ]);
    $deletedFile->delete();
    File::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $deletedProject->id,
        'uploaded_by' => $owner->id,
        'name' => 'Needle deleted project file',
    ]);
    File::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $hiddenProject->id,
        'uploaded_by' => $owner->id,
        'name' => 'Needle hidden file',
    ]);

    $otherWorkspace = phaseFiveSearchWorkspace($otherOwner);
    $foreignProject = phaseFiveSearchProject($otherWorkspace, $otherOwner, 'Needle foreign project');
    $otherWorkspace->members()->attach($member->id, ['role' => WorkspaceRole::Member->value]);
    $foreignProject->members()->attach($member->id);
    phaseFiveSearchTask($foreignProject, $otherOwner, 'Needle foreign task');

    $response = $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/search?q=Needle&types%5B%5D=projects&types%5B%5D=tasks&types%5B%5D=files')
        ->assertOk()
        ->assertJsonCount(1, 'data.results.projects')
        ->assertJsonCount(1, 'data.results.tasks')
        ->assertJsonCount(1, 'data.results.files')
        ->assertJsonPath('data.results.tasks.0.id', $visibleTask->id)
        ->assertJsonPath('data.results.tasks.0.project_id', $visibleProject->id)
        ->assertJsonPath('data.results.files.0.id', $visibleFile->id)
        ->assertJsonPath('data.results.files.0.project_id', $visibleProject->id);

    expect($response->json('data.results.projects.0.title'))->toBe('Needle visible project');
});

it('restricts team and member search to the current workspace', function () {
    $owner = User::factory()->create();
    $workspace = phaseFiveSearchWorkspace($owner);
    $workspaceMember = User::factory()->create(['name' => 'Rita Workspace']);
    $workspace->members()->attach($workspaceMember->id, ['role' => WorkspaceRole::Member->value]);
    Team::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Rita Workspace team']);

    $otherOwner = User::factory()->create();
    $otherWorkspace = phaseFiveSearchWorkspace($otherOwner);
    User::factory()->create(['name' => 'Rita Other tenant'])
        ->workspaces()->attach($otherWorkspace->id, ['role' => WorkspaceRole::Member->value]);
    Team::factory()->create(['workspace_id' => $otherWorkspace->id, 'name' => 'Rita Other team']);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson('/api/v1/search?q=Rita&types%5B%5D=members&types%5B%5D=teams')
        ->assertOk()
        ->assertJsonCount(1, 'data.results.members')
        ->assertJsonCount(1, 'data.results.teams')
        ->assertJsonPath('data.results.members.0.title', 'Rita Workspace')
        ->assertJsonPath('data.results.teams.0.title', 'Rita Workspace team');
});

it('requires an authenticated workspace member to use search', function () {
    $this->getJson('/api/v1/search?q=Project')->assertUnauthorized();
});
