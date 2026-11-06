<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Models\WasteTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'role' => ['sometimes', Rule::in(['nasabah', 'petugas', 'admin'])],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'sort' => ['sometimes', Rule::in(['name', 'email', 'created_at'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ]);

        $query = User::query();
        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%'));
        }

        return ApiResponse::paginated($query
            ->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc')
            ->paginate($filters['per_page'] ?? 15));
    }

    public function show(Request $request, User $nasabah): JsonResponse
    {
        if (! $this->canAccess($request, $nasabah)) {
            return ApiResponse::error('forbidden', 'Anda tidak dapat melihat data nasabah lain.', [], 403);
        }

        return ApiResponse::success($nasabah);
    }

    public function update(Request $request, User $nasabah): JsonResponse
    {
        if (! $this->canAccess($request, $nasabah)) {
            return ApiResponse::error('forbidden', 'Anda tidak dapat mengubah data nasabah lain.', [], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $nasabah->update($validated);

        return ApiResponse::success($nasabah->refresh());
    }

    public function updateRole(Request $request, User $nasabah): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(['nasabah', 'petugas', 'admin'])],
        ]);

        $nasabah->update($validated);

        return ApiResponse::success($nasabah->refresh());
    }

    public function balance(Request $request, User $nasabah): JsonResponse
    {
        if (! $this->canAccess($request, $nasabah)) {
            return ApiResponse::error('forbidden', 'Anda tidak dapat melihat saldo nasabah lain.', [], 403);
        }

        $balance = (int) $nasabah->transactions()->sum('amount');

        return ApiResponse::success([
            'nasabah_id' => $nasabah->id,
            'saldo' => $balance,
            'currency' => 'IDR',
        ]);
    }

    public function transactions(Request $request, User $nasabah): JsonResponse
    {
        if (! $this->canAccess($request, $nasabah)) {
            return ApiResponse::error('forbidden', 'Anda tidak dapat melihat transaksi nasabah lain.', [], 403);
        }

        $filters = $request->validate([
            'type' => ['sometimes', Rule::in(['setoran', 'penukaran'])],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', Rule::when($request->filled('from'), ['after_or_equal:from'])],
            'dari' => ['sometimes', 'date'],
            'sampai' => ['sometimes', 'date', Rule::when($request->filled('dari'), ['after_or_equal:dari'])],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ]);

        $query = $nasabah->transactions()->with(['deposit.wasteType', 'redemption.reward']);
        $filters['from'] = $filters['from'] ?? $filters['dari'] ?? null;
        $filters['to'] = $filters['to'] ?? $filters['sampai'] ?? null;
        $this->applyTransactionFilters($query, $filters);

        return ApiResponse::paginated($query
            ->orderBy('occurred_at', $filters['direction'] ?? 'desc')
            ->paginate($filters['per_page'] ?? 15));
    }

    public static function applyTransactionFilters(Builder $query, array $filters): void
    {
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (isset($filters['from'])) {
            $query->whereDate('occurred_at', '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $query->whereDate('occurred_at', '<=', $filters['to']);
        }
    }

    private function canAccess(Request $request, User $nasabah): bool
    {
        return in_array($request->user()->role, ['admin', 'petugas'], true)
            || $request->user()->is($nasabah);
    }
}
