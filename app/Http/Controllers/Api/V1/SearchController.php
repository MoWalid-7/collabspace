<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Search\SearchQuery;
use App\Http\Requests\Search\SearchRequest;
use App\Http\Resources\SearchResultsResource;
use App\Services\SearchService;
use App\Support\ApiResponse;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;

class SearchController
{
    public function __invoke(
        SearchRequest $request,
        SearchService $search,
        CurrentWorkspace $currentWorkspace,
    ): JsonResponse {
        $results = $search->search(
            SearchQuery::from($request->validated()),
            (int) $request->user()->getAuthIdentifier(),
            $currentWorkspace->requireId(),
        );

        return ApiResponse::success(['results' => SearchResultsResource::make($results)]);
    }
}
