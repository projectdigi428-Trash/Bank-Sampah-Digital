<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Deposit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function monthly(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'tahun' => ['sometimes', 'integer', 'between:2000,2100'],
            'bulan' => ['sometimes', 'integer', 'between:1,12'],
        ]);

        $period = now()->setDate(
            $filters['tahun'] ?? now()->year,
            $filters['bulan'] ?? now()->month,
            1,
        );
        $start = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();

        $summary = Deposit::query()
            ->join('waste_types', 'waste_types.id', '=', 'deposits.waste_type_id')
            ->whereBetween('deposits.recorded_at', [$start, $end])
            ->groupBy('waste_types.id', 'waste_types.name')
            ->orderBy('waste_types.name')
            ->selectRaw('waste_types.id as jenis_sampah_id, waste_types.name as jenis_sampah, COUNT(deposits.id) as jumlah_setoran, SUM(deposits.weight_kg) as total_berat_kg, SUM(deposits.total_points) as total_poin')
            ->get()
            ->map(fn ($item) => [
                'jenis_sampah_id' => (int) $item->jenis_sampah_id,
                'jenis_sampah' => $item->jenis_sampah,
                'jumlah_setoran' => (int) $item->jumlah_setoran,
                'total_berat_kg' => (float) $item->total_berat_kg,
                'total_poin' => (int) $item->total_poin,
            ]);

        return ApiResponse::success([
            'tahun' => $period->year,
            'bulan' => $period->month,
            'per_jenis_sampah' => $summary,
            'total_setoran' => $summary->sum('jumlah_setoran'),
            'total_berat_kg' => $summary->sum('total_berat_kg'),
            'total_poin' => $summary->sum('total_poin'),
        ]);
    }
}
