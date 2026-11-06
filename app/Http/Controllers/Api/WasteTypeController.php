<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\WasteType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WasteTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'sort' => ['sometimes', Rule::in(['name', 'price_per_kg', 'created_at'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ]);

        $query = WasteType::query();
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

    public function show(Request $request, WasteType $jenis_sampah): JsonResponse
    {
        if (! $jenis_sampah->is_active && (! $request->user() || $request->user()->role === 'nasabah')) {
            return ApiResponse::error('not_found', 'Jenis sampah tidak ditemukan.', [], 404);
        }

        return ApiResponse::success($jenis_sampah);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:waste_types,name'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price_per_kg' => ['required', 'integer', 'between:1,1000000000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return ApiResponse::success(WasteType::create($validated), 201);
    }

    public function update(Request $request, WasteType $jenis_sampah): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('waste_types', 'name')->ignore($jenis_sampah->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'price_per_kg' => ['sometimes', 'required', 'integer', 'between:1,1000000000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $jenis_sampah->update($validated);

        return ApiResponse::success($jenis_sampah->refresh());
    }

    public function destroy(WasteType $jenis_sampah): JsonResponse
    {
        $jenis_sampah->update(['is_active' => false]);

        return ApiResponse::success(['message' => 'Jenis sampah dinonaktifkan.']);
    }
}
