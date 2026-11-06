<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Reward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RewardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'sort' => ['sometimes', Rule::in(['name', 'points_required', 'created_at'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ]);

        $query = Reward::query();
        if (! $request->user() || $request->user()->role === 'nasabah') {
            $query->where('is_active', true);
        }
        if (isset($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        return ApiResponse::paginated($query
            ->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc')
            ->paginate($filters['per_page'] ?? 15));
    }

    public function show(Request $request, Reward $hadiah): JsonResponse
    {
        if (! $hadiah->is_active && (! $request->user() || $request->user()->role === 'nasabah')) {
            return ApiResponse::error('not_found', 'Hadiah tidak ditemukan.', [], 404);
        }

        return ApiResponse::success($hadiah);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:rewards,name'],
            'description' => ['nullable', 'string', 'max:2000'],
            'points_required' => ['required', 'integer', 'between:1,1000000000000'],
            'stock' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return ApiResponse::success(Reward::create($validated), 201);
    }

    public function update(Request $request, Reward $hadiah): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('rewards', 'name')->ignore($hadiah->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'points_required' => ['sometimes', 'required', 'integer', 'between:1,1000000000000'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $hadiah->update($validated);

        return ApiResponse::success($hadiah->refresh());
    }

    public function destroy(Reward $hadiah): JsonResponse
    {
        $hadiah->update(['is_active' => false]);

        return ApiResponse::success(['message' => 'Hadiah dinonaktifkan.']);
    }
}
