<?php

use App\Data\Files\StoredFile;
use App\Events\FileVersionAdded;
use App\Models\File;
use App\Models\FileVersion;
use App\Repositories\Contracts\FileRepositoryInterface;
use App\Services\FileService;
use App\Services\Storage\Contracts\FileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Mockery;

it('allocates the next version number and dispatches the version event', function () {
    Event::fake([FileVersionAdded::class]);
    $upload = UploadedFile::fake()->image('revision.png');
    $file = new File;
    $file->setAttribute('id', 42);
    $file->setAttribute('workspace_id', 11);
    $file->setAttribute('project_id', 21);
    $file->setAttribute('name', 'original.png');
    $version = new FileVersion;
    $version->setAttribute('version_number', 4);
    $storage = Mockery::mock(FileStorage::class);
    $repository = Mockery::mock(FileRepositoryInterface::class);
    $storedFile = new StoredFile(
        disk: 'private',
        path: 'workspaces/11/projects/21/files/uuid',
        size: 1200,
        mime: 'image/png',
        checksum: str_repeat('a', 64),
    );

    $storage->shouldReceive('put')
        ->once()
        ->with($upload, 'workspaces/11/projects/21/files')
        ->andReturn($storedFile);
    $repository->shouldReceive('findForVersioning')->once()->with($file)->andReturn($file);
    $repository->shouldReceive('nextVersionNumber')->once()->with($file)->andReturn(4);
    $repository->shouldReceive('createVersion')
        ->once()
        ->with($file, [
            'version_number' => 4,
            'disk' => 'private',
            'path' => $storedFile->path,
            'original_name' => 'revision.png',
            'mime' => 'image/png',
            'size' => 1200,
            'checksum' => str_repeat('a', 64),
            'uploaded_by' => 9,
            'note' => 'Revised artwork',
        ])
        ->andReturn($version);
    $repository->shouldReceive('findInCurrentWorkspaceOrFail')->once()->with(42)->andReturn($file);

    $updatedFile = (new FileService($repository, $storage))->addVersion(
        $file,
        $upload,
        'Revised artwork',
        9,
    );

    expect($updatedFile)->toBe($file);
    Event::assertDispatched(
        FileVersionAdded::class,
        fn (FileVersionAdded $event): bool => $event->version->version_number === 4
            && $event->actorId === 9,
    );
});
