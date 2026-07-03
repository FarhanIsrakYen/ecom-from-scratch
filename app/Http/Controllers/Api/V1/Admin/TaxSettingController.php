<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\StoreTaxSettingRequest;
use App\Http\Requests\Pricing\UpdateTaxSettingRequest;
use App\Http\Resources\TaxSettingResource;
use App\Models\TaxSetting;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxSettingController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        $settings = TaxSetting::query()
            ->when($filters['status'] ?? null, fn ($query, mixed $status) => $query->where('status', $status))
            ->latest()
            ->get();

        return $this->success(TaxSettingResource::collection($settings), 'Tax settings retrieved.');
    }

    public function store(StoreTaxSettingRequest $request): JsonResponse
    {
        return $this->success(
            new TaxSettingResource(TaxSetting::query()->create($request->validated())),
            'Tax setting created.',
            201,
        );
    }

    public function show(TaxSetting $taxSetting): JsonResponse
    {
        return $this->success(new TaxSettingResource($taxSetting), 'Tax setting retrieved.');
    }

    public function update(UpdateTaxSettingRequest $request, TaxSetting $taxSetting): JsonResponse
    {
        $taxSetting->update($request->validated());

        return $this->success(new TaxSettingResource($taxSetting->refresh()), 'Tax setting updated.');
    }

    public function destroy(TaxSetting $taxSetting): JsonResponse
    {
        $taxSetting->delete();

        return $this->success(null, 'Tax setting deleted.');
    }
}
