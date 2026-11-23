<?php

namespace App\Http\Controllers\Api\V1\Files;

use App\Http\Requests\Files\DeleteFileRequest;
use App\Http\Requests\Files\DownloadFileRequest;
use App\Http\Requests\Files\DownloadFileVersionRequest;
use App\Http\Requests\Files\ListFileVersionsRequest;
use App\Http\Requests\Files\RenameFileRequest;
use App\Http\Requests\Files\ShowFileRequest;
use App\Http\Requests\Files\UploadFileVersionRequest;
use App\Http\Resources\FileDetailResource;
use App\Http\Resources\FileVersionResource;
use App\Services\Contracts\FileServiceInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController
{
    public function show(ShowFileRequest $request, FileServiceInterface $files, int $file): JsonResponse
    {
        return ApiResponse::success([
            'file' => FileDetailResource::make($files->show($file)),
        ]);
    }

    public function update(RenameFileRequest $request, FileServiceInterface $files, int $file): JsonResponse
    {
        $updatedFile = $files->rename($file, $request->validated('name'));

        return ApiResponse::success([
            'file' => FileDetailResource::make($updatedFile),
        ], 'File renamed successfully.');
    }

    public function destroy(DeleteFileRequest $request, FileServiceInterface $files, int $file): Response
    {
        $files->delete($file);

        return ApiResponse::noContent();
    }

    public function download(DownloadFileRequest $request, FileServiceInterface $files, int $file): StreamedResponse
    {
        return $files->download($file);
    }

    public function versions(ListFileVersionsRequest $request, FileServiceInterface $files, int $file): JsonResponse
    {
        return ApiResponse::success([
            'versions' => FileVersionResource::collection($files->versions($file)),
        ]);
    }

    public function storeVersion(
        UploadFileVersionRequest $request,
        FileServiceInterface $files,
        int $file,
    ): JsonResponse {
        $attributes = $request->validated();
        $updatedFile = $files->addVersion(
            $files->show($file),
            $request->uploadedFile(),
            $attributes['note'] ?? null,
            (int) $request->user()->getKey(),
        );

        return ApiResponse::created([
            'file' => FileDetailResource::make($updatedFile),
        ], 'File version uploaded successfully.');
    }

    public function downloadVersion(
        DownloadFileVersionRequest $request,
        FileServiceInterface $files,
        int $file,
        int $version,
    ): StreamedResponse {
        return $files->downloadVersion($file, $version);
    }
}
