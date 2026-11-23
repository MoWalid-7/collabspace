<?php

namespace App\Http\Controllers\Api\V1\Files;

use App\Http\Requests\Files\ListTaskFilesRequest;
use App\Http\Requests\Files\UploadTaskFileRequest;
use App\Http\Resources\FileResource;
use App\Services\Contracts\FileServiceInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TaskFileController
{
    public function index(ListTaskFilesRequest $request, FileServiceInterface $files, int $task): JsonResponse
    {
        $paginator = $files->listForTask($task, (int) $request->validated('per_page', 15));

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

    public function store(UploadTaskFileRequest $request, FileServiceInterface $files, int $task): JsonResponse
    {
        $attributes = $request->validated();
        $file = $files->uploadForTask(
            $task,
            $request->uploadedFile(),
            $attributes['note'] ?? null,
            (int) $request->user()->getKey(),
        );

        return ApiResponse::created([
            'file' => FileResource::make($file),
        ], 'File uploaded successfully.');
    }
}
