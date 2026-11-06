<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CollectionPoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CollectionPointController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'sort' => ['sometimes', Rule::in(['name', 'created_at'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ]);

        $query = CollectionPoint::with('wasteTypes:id,name,price_per_kg');
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

    public function show(Request $request, CollectionPoint $titik_setor): JsonResponse
    {
        if (! $titik_setor->is_active && (! $request->user() || $request->user()->role === 'nasabah')) {
            return ApiResponse::error('not_found', 'Titik setor tidak ditemukan.', [], 404);
        }

        return ApiResponse::success($titik_setor->load('wasteTypes:id,name,price_per_kg'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
            'jenis_sampah_ids' => ['required', 'array', 'min:1'],
            'jenis_sampah_ids.*' => ['required', 'integer', 'distinct', Rule::exists('waste_types', 'id')->where('is_active', true)],
        ]);

        $wasteTypeIds = $validated['jenis_sampah_ids'];
        unset($validated['jenis_sampah_ids']);

        $collectionPoint = DB::transaction(function () use ($validated, $wasteTypeIds) {
            $collectionPoint = CollectionPoint::create($validated);
            $collectionPoint->wasteTypes()->sync($wasteTypeIds);

            return $collectionPoint;
        });

        return ApiResponse::success($collectionPoint->load('wasteTypes:id,name,price_per_kg'), 201);
    }

    public function update(Request $request, CollectionPoint $titik_setor): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'address' => ['sometimes', 'required', 'string', 'max:2000'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
            'jenis_sampah_ids' => ['sometimes', 'required', 'array', 'min:1'],
            'jenis_sampah_ids.*' => ['required_with:jenis_sampah_ids', 'integer', 'distinct', Rule::exists('waste_types', 'id')->where('is_active', true)],
        ]);

        $wasteTypeIds = $validated['jenis_sampah_ids'] ?? null;
        unset($validated['jenis_sampah_ids']);

        DB::transaction(function () use ($titik_setor, $validated, $wasteTypeIds) {
            $titik_setor->update($validated);
            if ($wasteTypeIds !== null) {
                $titik_setor->wasteTypes()->sync($wasteTypeIds);
            }
        });

        return ApiResponse::success($titik_setor->refresh()->load('wasteTypes:id,name,price_per_kg'));
    }

    public function destroy(CollectionPoint $titik_setor): JsonResponse
    {
        $titik_setor->update(['is_active' => false]);

        return ApiResponse::success(['message' => 'Titik setor dinonaktifkan.']);
    }
}
