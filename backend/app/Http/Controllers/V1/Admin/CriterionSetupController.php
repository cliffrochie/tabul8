<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCriterionRequest;
use App\Http\Resources\CriterionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Criterion;
use App\Models\Round;
use App\Models\User;
use App\Services\EventSetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CriterionSetupController extends Controller
{
    public function store(StoreCriterionRequest $request, Round $round, EventSetupService $service): JsonResponse
    {
        $result = $service->createCriterion($round, $request->validated());

        return ApiResponse::success(
            [
                'criterion' => new CriterionResource($result['criterion']),
                'leaf_weight_sum' => $result['leaf_weight_sum'],
            ],
            'Criterion added.',
            201
        );
    }

    public function update(StoreCriterionRequest $request, Criterion $criterion, EventSetupService $service): JsonResponse
    {
        $result = $service->updateCriterion($criterion, $request->validated());

        return ApiResponse::success(
            [
                'criterion' => new CriterionResource($result['criterion']),
                'leaf_weight_sum' => $result['leaf_weight_sum'],
            ],
            'Criterion updated.'
        );
    }

    public function destroy(Request $request, Criterion $criterion, EventSetupService $service): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User || ! $actor->is_active || ! in_array($actor->role, ['super_admin', 'tabulator'], true)) {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        $leafWeightSum = $service->deleteCriterion($criterion);

        return ApiResponse::success(
            ['leaf_weight_sum' => $leafWeightSum],
            'Criterion deleted.'
        );
    }
}
