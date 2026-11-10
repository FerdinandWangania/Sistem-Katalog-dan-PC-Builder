<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan keuangan (FR-023).
 * Akses: role `keuangan` dan `superadmin` (middleware `finance`).
 */
class FinanceController extends Controller
{
    public function __construct(private FinanceService $finance) {}

    /**
     * Laba rugi per periode.
     * GET /api/admin/finance/profit-loss?from=2026-11-01&to=2026-11-30&format=csv
     */
    public function profitLoss(Request $request): JsonResponse|StreamedResponse
    {
        [$from, $to] = $this->period($request);
        $report = $this->finance->profitLoss($from, $to);

        if ($this->wantsCsv($request)) {
            return $this->csv(
                "laba-rugi-{$from->toDateString()}-{$to->toDateString()}.csv",
                ['Metrik', 'Nilai'],
                $this->flatten($report),
            );
        }

        return response()->json([
            'status' => 'success',
            'data' => $report,
        ]);
    }

    /**
     * PPN keluaran per periode (PPN inklusif).
     * GET /api/admin/finance/tax?from=&to=&format=csv
     */
    public function tax(Request $request): JsonResponse|StreamedResponse
    {
        [$from, $to] = $this->period($request);
        $report = $this->finance->tax($from, $to);

        if ($this->wantsCsv($request)) {
            $rows = array_map(fn (array $line) => [
                $line['order_number'],
                $line['date'],
                $line['gross_amount'],
                $line['dpp'],
                $line['ppn'],
            ], $report['orders']);

            $rows[] = ['TOTAL', '', $report['gross_amount'], $report['dpp'], $report['ppn']];

            return $this->csv(
                "ppn-keluaran-{$from->toDateString()}-{$to->toDateString()}.csv",
                ['No. Order', 'Tanggal', 'Total', 'DPP', 'PPN'],
                $rows,
            );
        }

        return response()->json([
            'status' => 'success',
            'data' => $report,
        ]);
    }

    /**
     * Margin per produk / kategori / brand.
     * GET /api/admin/finance/margin?group_by=product|category|brand&format=csv
     */
    public function margin(Request $request): JsonResponse|StreamedResponse
    {
        $validated = $request->validate([
            'group_by' => 'nullable|in:product,category,brand',
        ]);

        [$from, $to] = $this->period($request);
        $groupBy = $validated['group_by'] ?? 'product';
        $report = $this->finance->margin($from, $to, $groupBy);

        if ($this->wantsCsv($request)) {
            $rows = array_map(fn (array $row) => [
                $row['label'],
                $row['quantity'],
                $row['revenue'],
                $row['cogs'],
                $row['profit'],
                $row['margin_percent'],
                $row['missing_hpp_lines'],
            ], $report['rows']);

            return $this->csv(
                "margin-{$groupBy}-{$from->toDateString()}-{$to->toDateString()}.csv",
                ['Grup', 'Qty', 'Pendapatan', 'HPP', 'Laba', 'Margin %', 'Baris tanpa HPP'],
                $rows,
            );
        }

        return response()->json([
            'status' => 'success',
            'data' => $report,
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(Request $request): array
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $from = ! empty($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $to = ! empty($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : now()->endOfDay();

        if ($to->lt($from)) {
            abort(response()->json([
                'status' => 'error',
                'message' => 'Rentang tanggal tidak valid: `to` lebih awal dari `from`.',
            ], 422));
        }

        return [$from, $to];
    }

    private function wantsCsv(Request $request): bool
    {
        return strtolower((string) $request->query('format', 'json')) === 'csv';
    }

    /**
     * Ratakan laporan ringkasan menjadi baris [metrik, nilai].
     */
    private function flatten(array $report): array
    {
        $rows = [];

        foreach ($report as $key => $value) {
            if (is_array($value) || $key === 'period') {
                continue;
            }

            $rows[] = [$key, is_bool($value) ? ($value ? 'true' : 'false') : $value];
        }

        return $rows;
    }

    private function csv(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers, ',', '"', '');

            foreach ($rows as $row) {
                fputcsv($output, $row, ',', '"', '');
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
