<?php

namespace App\Http\Controllers\Api\V1\Files;

use App\Data\Files\FileFilters;
use App\Http\Requests\Files\ListProjectFilesRequest;
use App\Http\Requests\Files\UploadProjectFileRequest;
use App\Http\Resources\FileResource;
use App\Services\Contracts\FileServiceInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProjectFileController
{
    public function index(ListProjectFilesRequest $request, FileServiceInterface $files, int $project): JsonResponse
    {
        $paginator = $files->list(FileFilters::from($project, $request->validated()));

        return ApiResponse::success([
            'files' => FileResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(UploadProjectFileRequest $request, FileServiceInterface $files, int $project): JsonResponse
    {
        $attributes = $request->validated();
        $file = $files->upload(
            $project,
            isset($attributes['task_id']) ? (int) $attributes['task_id'] : null,
            $request->uploadedFile(),
            $attributes['note'] ?? null,
            (int) $request->user()->getKey(),
        );

        return ApiResponse::created([
            'file' => FileResource::make($file),
        ], 'File uploaded successfully.');
    }
}
