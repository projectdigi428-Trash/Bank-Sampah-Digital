<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Deposit;
use App\Models\User;
use App\Models\WasteTransaction;
use App\Models\WasteType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DepositController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'nasabah_id' => ['sometimes', 'integer', 'exists:users,id'],
            'jenis_sampah_id' => ['sometimes', 'integer', 'exists:waste_types,id'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', Rule::when($request->filled('from'), ['after_or_equal:from'])],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ]);

        $query = Deposit::with(['member:id,name,email', 'officer:id,name', 'wasteType:id,name']);
        if ($request->user()->role === 'nasabah') {
            $query->where('user_id', $request->user()->id);
            if (isset($filters['nasabah_id']) && (int) $filters['nasabah_id'] !== $request->user()->id) {
                return ApiResponse::error('forbidden', 'Anda tidak dapat melihat setoran nasabah lain.', [], 403);
            }
        } elseif (isset($filters['nasabah_id'])) {
            $query->where('user_id', $filters['nasabah_id']);
        }
        if (isset($filters['jenis_sampah_id'])) {
            $query->where('waste_type_id', $filters['jenis_sampah_id']);
        }
        if (isset($filters['from'])) {
            $query->whereDate('recorded_at', '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $query->whereDate('recorded_at', '<=', $filters['to']);
        }

        return ApiResponse::paginated($query
            ->orderBy('recorded_at', $filters['direction'] ?? 'desc')
            ->paginate($filters['per_page'] ?? 15));
    }

    public function show(Request $request, Deposit $setoran): JsonResponse
    {
        if ($request->user()->role === 'nasabah' && $setoran->user_id !== $request->user()->id) {
            return ApiResponse::error('forbidden', 'Anda tidak dapat melihat setoran nasabah lain.', [], 403);
        }

        return ApiResponse::success($setoran->load([
            'member:id,name,email',
            'officer:id,name',
            'wasteType:id,name',
            'transaction:id,deposit_id,amount,balance_after',
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nasabah_id' => ['required', 'integer', 'exists:users,id'],
            'jenis_sampah_id' => ['required', 'integer', 'exists:waste_types,id'],
            'berat_kg' => ['required', 'numeric', 'decimal:0,3', 'min:0.001', 'max:10000'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $deposit = DB::transaction(function () use ($validated, $request) {
            $member = User::whereKey($validated['nasabah_id'])->lockForUpdate()->firstOrFail();
            if ($member->role !== 'nasabah') {
                return null;
            }

            $wasteType = WasteType::whereKey($validated['jenis_sampah_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $wasteType) {
                return null;
            }

            $weight = (int) round((float) $validated['berat_kg'] * 1000);
            $totalPoints = intdiv($weight * $wasteType->price_per_kg + 500, 1000);
            $balance = (int) $member->transactions()->sum('amount');
            $deposit = Deposit::create([
                'user_id' => $member->id,
                'officer_id' => $request->user()->id,
                'waste_type_id' => $wasteType->id,
                'weight_kg' => number_format($weight / 1000, 3, '.', ''),
                'price_per_kg' => $wasteType->price_per_kg,
                'total_points' => $totalPoints,
                'note' => $validated['catatan'] ?? null,
                'recorded_at' => now(),
            ]);

            WasteTransaction::create([
                'user_id' => $member->id,
                'deposit_id' => $deposit->id,
                'type' => 'setoran',
                'amount' => $totalPoints,
                'balance_after' => $balance + $totalPoints,
                'description' => 'Setoran '.$wasteType->name.' ('.number_format($weight / 1000, 3, '.', '').' kg)',
                'occurred_at' => now(),
            ]);

            return $deposit->load([
                'member:id,name,email',
                'officer:id,name',
                'wasteType:id,name',
                'transaction:id,deposit_id,amount,balance_after',
            ]);
        });

        if (! $deposit) {
            return ApiResponse::error(
                'invalid_reference',
                'Nasabah atau jenis sampah tidak ditemukan atau tidak aktif.',
                [],
                422,
            );
        }

        return ApiResponse::success($deposit, 201);
    }
}
