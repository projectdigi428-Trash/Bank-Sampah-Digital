<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\WasteTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'nasabah_id' => ['sometimes', 'integer', 'exists:users,id'],
            'type' => ['sometimes', Rule::in(['setoran', 'penukaran'])],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', Rule::when($request->filled('from'), ['after_or_equal:from'])],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ]);

        $query = WasteTransaction::with(['member:id,name,email', 'deposit.wasteType', 'redemption.reward']);
        if ($request->user()->role === 'nasabah') {
            $query->where('user_id', $request->user()->id);
            if (isset($filters['nasabah_id']) && (int) $filters['nasabah_id'] !== $request->user()->id) {
                return ApiResponse::error('forbidden', 'Anda tidak dapat melihat transaksi nasabah lain.', [], 403);
            }
        } elseif (isset($filters['nasabah_id'])) {
            $query->where('user_id', $filters['nasabah_id']);
        }
        MemberController::applyTransactionFilters($query, $filters);

        return ApiResponse::paginated($query
            ->orderBy('occurred_at', $filters['direction'] ?? 'desc')
            ->paginate($filters['per_page'] ?? 15));
    }

    public function show(Request $request, WasteTransaction $transaksi): JsonResponse
    {
        if ($request->user()->role === 'nasabah' && $transaksi->user_id !== $request->user()->id) {
            return ApiResponse::error('forbidden', 'Anda tidak dapat melihat transaksi nasabah lain.', [], 403);
        }

        return ApiResponse::success($transaksi->load([
            'member:id,name,email',
            'deposit.wasteType',
            'redemption.reward',
        ]));
    }
}
