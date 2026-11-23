<?php

use App\Models\File as ProjectFile;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function filesTestWorkspace(User $owner, array $members = []): Workspace
{
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, ['role' => 'admin']);

    foreach ($members as $member) {
        $workspace->members()->attach($member->id, ['role' => 'member']);
    }

    return $workspace;
}

/**
 * @param  list<User>  $members
 */
function filesTestProject(Workspace $workspace, User $creator, array $members = []): Project
{
    $project = Project::factory()->create([
        'workspace_id' => $workspace->id,
        'created_by' => $creator->id,
    ]);
    $project->members()->attach($creator->id);

    foreach ($members as $member) {
        $project->members()->attach($member->id);
    }

    return $project;
}

function filesTestRecord(int $fileId): ProjectFile
{
    return ProjectFile::withoutGlobalScopes()
        ->with('currentVersion')
        ->findOrFail($fileId);
}

it('uploads a private file with its first version and hides its storage path', function () {
    Storage::fake('private');
    Queue::fake();
    $owner = User::factory()->create();
    $workspace = filesTestWorkspace($owner);
    $project = filesTestProject($workspace, $owner);

    $response = $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/projects/{$project->id}/files", [
            'file' => UploadedFile::fake()->image('screen.png'),
            'note' => 'Initial design',
        ])
        ->assertCreated()
        ->assertJsonPath('data.file.name', 'screen.png')
        ->assertJsonPath('data.file.current_version.version_number', 1)
        ->assertJsonMissingPath('data.file.current_version.path');

    $fileId = (int) $response->json('data.file.id');
    $file = filesTestRecord($fileId);

    $this->assertDatabaseHas('files', [
        'id' => $fileId,
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'uploaded_by' => $owner->id,
    ]);
    $this->assertDatabaseHas('file_versions', [
        'file_id' => $fileId,
        'version_number' => 1,
        'original_name' => 'screen.png',
        'note' => 'Initial design',
    ]);
    Storage::disk('private')->assertExists($file->currentVersion->path);
    expect($file->currentVersion->path)
        ->toContain("workspaces/{$workspace->id}/projects/{$project->id}/files/");
});

it('rejects file uploads larger than the configured maximum', function () {
    Storage::fake('private');
    Queue::fake();
    config(['collabspace.files.max_upload_size_kb' => 1]);
    $owner = User::factory()->create();
    $workspace = filesTestWorkspace($owner);
    $project = filesTestProject($workspace, $owner);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/projects/{$project->id}/files", [
            'file' => UploadedFile::fake()->create('large.pdf', 2, 'application/pdf'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

it('rejects unsupported file types', function () {
    Storage::fake('private');
    Queue::fake();
    $owner = User::factory()->create();
    $workspace = filesTestWorkspace($owner);
    $project = filesTestProject($workspace, $owner);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/projects/{$project->id}/files", [
            'file' => UploadedFile::fake()->create('payload.exe', 2, 'application/x-dosexec'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

it('rejects empty file uploads', function () {
    Storage::fake('private');
    Queue::fake();
    $owner = User::factory()->create();
    $workspace = filesTestWorkspace($owner);
    $project = filesTestProject($workspace, $owner);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/projects/{$project->id}/files", [
            'file' => UploadedFile::fake()->create('empty.pdf', 0, 'application/pdf'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

it('creates immutable numbered versions and downloads the selected version', function () {
    Storage::fake('private');
    Queue::fake();
    $owner = User::factory()->create();
    $workspace = filesTestWorkspace($owner);
    $project = filesTestProject($workspace, $owner);
    $response = $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/projects/{$project->id}/files", [
            'file' => UploadedFile::fake()->image('original.png'),
        ])
        ->assertCreated();
    $fileId = (int) $response->json('data.file.id');
    $firstPath = filesTestRecord($fileId)->currentVersion->path;

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/files/{$fileId}/versions", [
            'file' => UploadedFile::fake()->image('revised.png'),
            'note' => 'Updated design',
        ])
        ->assertCreated()
        ->assertJsonPath('data.file.current_version.version_number', 2)
        ->assertJsonPath('data.file.versions_count', 2);

    $file = filesTestRecord($fileId);
    $secondPath = $file->currentVersion->path;
    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('private')->assertExists($firstPath);
    Storage::disk('private')->assertExists($secondPath);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->get("/api/v1/files/{$fileId}/versions/1/download")
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=original.png');

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/files/{$fileId}/versions")
        ->assertOk()
        ->assertJsonPath('data.versions.0.version_number', 2)
        ->assertJsonPath('data.versions.1.version_number', 1);
});

it('limits downloads to project members and hides files across workspaces', function () {
    Storage::fake('private');
    Queue::fake();
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = filesTestWorkspace($owner, [$member]);
    $project = filesTestProject($workspace, $owner, [$member]);
    $outsider = User::factory()->create();
    $otherWorkspace = filesTestWorkspace($outsider);
    $otherProject = filesTestProject($otherWorkspace, $outsider);
    $otherWorkspaceMember = User::factory()->create();
    $otherWorkspace->members()->attach($otherWorkspaceMember->id, ['role' => 'member']);
    $otherProject->members()->attach($otherWorkspaceMember->id);

    $fileResponse = $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/projects/{$project->id}/files", [
            'file' => UploadedFile::fake()->image('private.png'),
        ])
        ->assertCreated();
    $fileId = (int) $fileResponse->json('data.file.id');

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->get("/api/v1/files/{$fileId}/download")
        ->assertOk();

    $nonProjectMember = User::factory()->create();
    $workspace->members()->attach($nonProjectMember->id, ['role' => 'member']);
    $this->actingAs($nonProjectMember, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/files/{$fileId}")
        ->assertForbidden();

    $foreignResponse = $this->actingAs($outsider, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $otherWorkspace->id)
        ->postJson("/api/v1/projects/{$otherProject->id}/files", [
            'file' => UploadedFile::fake()->image('foreign.png'),
        ])
        ->assertCreated();
    $foreignFileId = (int) $foreignResponse->json('data.file.id');

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/files/{$foreignFileId}")
        ->assertNotFound();
});

it('restricts rename and delete to the uploader or workspace managers', function () {
    Storage::fake('private');
    Queue::fake();
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = filesTestWorkspace($owner, [$member]);
    $project = filesTestProject($workspace, $owner, [$member]);
    $response = $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/projects/{$project->id}/files", [
            'file' => UploadedFile::fake()->image('initial.png'),
        ])
        ->assertCreated();
    $fileId = (int) $response->json('data.file.id');

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->patchJson("/api/v1/files/{$fileId}", ['name' => 'No access'])
        ->assertForbidden();

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->patchJson("/api/v1/files/{$fileId}", ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.file.name', 'Renamed');

    $this->actingAs($member, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->deleteJson("/api/v1/files/{$fileId}")
        ->assertForbidden();

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->deleteJson("/api/v1/files/{$fileId}")
        ->assertNoContent();

    $this->assertSoftDeleted('files', ['id' => $fileId]);
});

it('links task attachments to the task project and filters paginated project files', function () {
    Storage::fake('private');
    Queue::fake();
    $owner = User::factory()->create();
    $workspace = filesTestWorkspace($owner);
    $project = filesTestProject($workspace, $owner);
    $task = Task::factory()->create([
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->postJson("/api/v1/tasks/{$task->id}/files", [
            'file' => UploadedFile::fake()->image('diagram-a.png'),
        ])
        ->assertCreated()
        ->assertJsonPath('data.file.task_id', $task->id)
        ->assertJsonPath('data.file.project_id', $project->id);

    foreach (['diagram-b.png', 'notes.pdf'] as $name) {
        $upload = str_ends_with($name, '.png')
            ? UploadedFile::fake()->image($name)
            : UploadedFile::fake()->create($name, 4, 'application/pdf');

        $this->actingAs($owner, 'sanctum')
            ->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->postJson("/api/v1/projects/{$project->id}/files", ['file' => $upload])
            ->assertCreated();
    }

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/tasks/{$task->id}/files")
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1);

    $this->actingAs($owner, 'sanctum')
        ->withHeader('X-Workspace-Id', (string) $workspace->id)
        ->getJson("/api/v1/projects/{$project->id}/files?type=image&search=diagram&sort=name&per_page=1")
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 2)
        ->assertJsonPath('data.pagination.per_page', 1)
        ->assertJsonPath('data.files.0.name', 'diagram-a.png');
});
