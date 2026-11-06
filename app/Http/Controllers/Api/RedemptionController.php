<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\User;
use App\Models\WasteTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RedemptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'nasabah_id' => ['sometimes', 'integer', 'exists:users,id'],
            'hadiah_id' => ['sometimes', 'integer', 'exists:rewards,id'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', Rule::when($request->filled('from'), ['after_or_equal:from'])],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ]);

        $query = Redemption::with(['member:id,name,email', 'reward:id,name']);
        if ($request->user()->role === 'nasabah') {
            $query->where('user_id', $request->user()->id);
            if (isset($filters['nasabah_id']) && (int) $filters['nasabah_id'] !== $request->user()->id) {
                return ApiResponse::error('forbidden', 'Anda tidak dapat melihat penukaran nasabah lain.', [], 403);
            }
        } elseif (isset($filters['nasabah_id'])) {
            $query->where('user_id', $filters['nasabah_id']);
        }
        if (isset($filters['hadiah_id'])) {
            $query->where('reward_id', $filters['hadiah_id']);
        }
        if (isset($filters['from'])) {
            $query->whereDate('redeemed_at', '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $query->whereDate('redeemed_at', '<=', $filters['to']);
        }

        return ApiResponse::paginated($query
            ->orderBy('redeemed_at', $filters['direction'] ?? 'desc')
            ->paginate($filters['per_page'] ?? 15));
    }

    public function show(Request $request, Redemption $penukaran): JsonResponse
    {
        if ($request->user()->role === 'nasabah' && $penukaran->user_id !== $request->user()->id) {
            return ApiResponse::error('forbidden', 'Anda tidak dapat melihat penukaran nasabah lain.', [], 403);
        }

        return ApiResponse::success($penukaran->load([
            'member:id,name,email',
            'reward:id,name',
            'transaction:id,redemption_id,amount,balance_after',
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hadiah_id' => ['required', 'integer', 'exists:rewards,id'],
            'jumlah' => ['required', 'integer', 'between:1,10000'],
        ]);

        $result = DB::transaction(function () use ($validated, $request) {
            $member = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $reward = Reward::whereKey($validated['hadiah_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $reward) {
                return ['error' => 'invalid_reference'];
            }

            if ($reward->stock < $validated['jumlah']) {
                return ['error' => 'insufficient_stock'];
            }

            $totalPoints = $reward->points_required * $validated['jumlah'];
            $balance = (int) $member->transactions()->sum('amount');
            if ($balance < $totalPoints) {
                return [
                    'error' => 'insufficient_points',
                    'balance' => $balance,
                    'required' => $totalPoints,
                ];
            }

            $redemption = Redemption::create([
                'user_id' => $member->id,
                'reward_id' => $reward->id,
                'quantity' => $validated['jumlah'],
                'points_per_item' => $reward->points_required,
                'total_points' => $totalPoints,
                'status' => 'completed',
                'redeemed_at' => now(),
            ]);
            $reward->decrement('stock', $validated['jumlah']);

            WasteTransaction::create([
                'user_id' => $member->id,
                'redemption_id' => $redemption->id,
                'type' => 'penukaran',
                'amount' => -$totalPoints,
                'balance_after' => $balance - $totalPoints,
                'description' => 'Penukaran '.$reward->name.' ('.$validated['jumlah'].' item)',
                'occurred_at' => now(),
            ]);

            return ['redemption' => $redemption->load([
                'member:id,name,email',
                'reward:id,name',
                'transaction:id,redemption_id,amount,balance_after',
            ])];
        });

        if (isset($result['error'])) {
            if ($result['error'] === 'insufficient_points') {
                return ApiResponse::error(
                    'insufficient_points',
                    'Saldo poin tidak mencukupi untuk penukaran ini.',
                    ['saldo' => $result['balance'], 'dibutuhkan' => $result['required']],
                    422,
                );
            }

            return ApiResponse::error(
                $result['error'],
                $result['error'] === 'insufficient_stock' ? 'Stok hadiah tidak mencukupi.' : 'Hadiah tidak ditemukan atau tidak aktif.',
                [],
                422,
            );
        }

        return ApiResponse::success($result['redemption'], 201);
    }
}
