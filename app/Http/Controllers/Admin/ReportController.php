<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Wastage;
use App\Services\Metrics\MetricsService;
use Barryvdh\DomPDF\PDF;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Reports read from the same MetricsService as the dashboard, so
 * totals always match. Export supports json, pdf and xlsx.
 */
class ReportController extends Controller
{
    public function __construct(private readonly MetricsService $metrics) {}

    public function index(Request $request): Response
    {
        $this->authorize('reports.view');

        [$from, $to] = $this->period($request);
        $branchId = $this->branchId($request);

        [$title, $description, $rows, $columns, $summaryCards] = $this->build(
            (string) $request->input('type', 'sales'),
            $branchId,
            $from,
            $to,
        );

        return Inertia::render('admin/reports/Index', [
            'type' => (string) $request->input('type', 'sales'),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'title' => $title,
            'description' => $description,
            'columns' => $columns,
            'rows' => $rows,
            'summaryCards' => $summaryCards,
            'branches' => Branch::query()->orderBy('code')->get(['id', 'code', 'name']),
            'branchId' => $branchId,
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize('reports.view');

        [$from, $to] = $this->period($request);
        $branchId = $this->branchId($request);
        $type = (string) $request->input('type', 'sales');

        [$title, , $rows, $columns] = $this->build($type, $branchId, $from, $to);
        $format = (string) $request->input('format', 'pdf');

        $filename = $type.'-'.$from->toDateString().'-'.$to->toDateString();

        if ($format === 'xlsx') {
            return $this->xlsx($filename, $title, $columns, $rows, $branchId, $from, $to);
        }

        if ($format === 'csv') {
            return $this->csv($filename, $columns, $rows);
        }

        return $this->pdf($filename, $title, $columns, $rows, $branchId, $from, $to);
    }

    private function build(string $type, ?int $branchId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return match ($type) {
            'wastage' => $this->wastageReport($branchId, $from, $to),
            'stock-valuation' => $this->stockValuationReport($branchId),
            'food-cost' => $this->foodCostReport($branchId, $from, $to),
            'branch-pl' => $this->branchPlReport($from, $to),
            'item-sales' => $this->itemSalesReport($branchId, $from, $to),
            default => $this->salesReport($branchId, $from, $to),
        };
    }

    private function salesReport(?int $branchId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->metrics->salesByDayDetailed($branchId, $from, $to);
        $summary = $this->metrics->summary($branchId, $from, $to);

        return [
            __('reports.types.sales'),
            __('reports.descriptions.sales'),
            $rows,
            [
                ['key' => 'date', 'label' => __('reports.date'), 'type' => 'date'],
                ['key' => 'orders', 'label' => __('reports.orders'), 'type' => 'number'],
                ['key' => 'gross', 'label' => __('reports.gross_sales'), 'type' => 'money'],
                ['key' => 'discount', 'label' => __('reports.discount'), 'type' => 'money'],
                ['key' => 'net', 'label' => __('reports.net_sales'), 'type' => 'money'],
                ['key' => 'vat', 'label' => __('reports.vat'), 'type' => 'money'],
                ['key' => 'total', 'label' => __('reports.grand_total'), 'type' => 'money'],
            ],
            [
                ['label' => __('reports.gross_sales'), 'value' => $summary['gross_sales']],
                ['label' => __('reports.net_sales'), 'value' => $summary['net_sales']],
                ['label' => __('reports.vat'), 'value' => $summary['vat']],
                ['label' => __('reports.orders'), 'value' => (string) $summary['orders']],
            ],
        ];
    }

    private function itemSalesReport(?int $branchId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->metrics->topItems($branchId, $from, $to, 50);

        $totalRevenue = array_sum(array_map(fn (array $row) => (float) $row['revenue'], $rows));

        return [
            __('reports.types.item_sales'),
            __('reports.descriptions.item_sales'),
            $rows,
            [
                ['key' => 'name', 'label' => __('reports.item'), 'type' => 'text'],
                ['key' => 'qty', 'label' => __('reports.quantity'), 'type' => 'number'],
                ['key' => 'revenue', 'label' => __('reports.revenue'), 'type' => 'money'],
            ],
            [
                ['label' => __('reports.items'), 'value' => (string) count($rows)],
                ['label' => __('reports.revenue'), 'value' => number_format($totalRevenue, 2, '.', '')],
            ],
        ];
    }

    private function wastageReport(?int $branchId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Wastage::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->with(['ingredient:id,name', 'unit:id,symbol', 'user:id,name'])
            ->latest()
            ->limit(1000)
            ->get()
            ->map(fn ($wastage) => [
                'date' => $wastage->created_at->toDateString(),
                'ingredient' => $wastage->ingredient->name ?? '',
                'qty' => trim((string) $wastage->qty.' '.($wastage->unit->symbol ?? '')),
                'reason' => __('reasons.'.$wastage->reason_code),
                'user' => $wastage->user->name ?? '',
                'cost' => (string) $wastage->cost,
            ])
            ->all();

        $total = array_sum(array_map(fn (array $row) => (float) $row['cost'], $rows));

        return [
            __('reports.types.wastage'),
            __('reports.descriptions.wastage'),
            $rows,
            [
                ['key' => 'date', 'label' => __('reports.date'), 'type' => 'date'],
                ['key' => 'ingredient', 'label' => __('reports.ingredient'), 'type' => 'text'],
                ['key' => 'qty', 'label' => __('reports.quantity'), 'type' => 'text'],
                ['key' => 'reason', 'label' => __('reports.reason'), 'type' => 'text'],
                ['key' => 'user', 'label' => __('reports.user'), 'type' => 'text'],
                ['key' => 'cost', 'label' => __('reports.cost'), 'type' => 'money'],
            ],
            [
                ['label' => __('reports.entries'), 'value' => (string) count($rows)],
                ['label' => __('reports.total_cost'), 'value' => number_format($total, 2, '.', '')],
            ],
        ];
    }

    private function stockValuationReport(?int $branchId): array
    {
        $rows = DB::table('ingredient_stocks')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->join('ingredients', 'ingredients.id', '=', 'ingredient_stocks.ingredient_id')
            ->join('units', 'units.id', '=', 'ingredients.unit_id')
            ->orderBy('ingredients.name')
            ->get(['ingredient_stocks.*', 'ingredients.name as ingredient_name', 'units.symbol as unit_symbol'])
            ->map(fn ($row) => [
                'ingredient' => (string) $row->ingredient_name,
                'stock_qty' => (string) $row->stock_qty,
                'unit' => (string) $row->unit_symbol,
                'avg_cost' => (string) $row->avg_cost,
                'value' => number_format((float) $row->stock_qty * (float) $row->avg_cost, 2, '.', ''),
            ])
            ->all();

        $totalValue = array_sum(array_map(fn (array $row) => (float) $row['value'], $rows));

        return [
            __('reports.types.stock_valuation'),
            __('reports.descriptions.stock_valuation'),
            $rows,
            [
                ['key' => 'ingredient', 'label' => __('reports.ingredient'), 'type' => 'text'],
                ['key' => 'stock_qty', 'label' => __('reports.stock_qty'), 'type' => 'number'],
                ['key' => 'unit', 'label' => __('reports.unit'), 'type' => 'text'],
                ['key' => 'avg_cost', 'label' => __('reports.avg_cost'), 'type' => 'money'],
                ['key' => 'value', 'label' => __('reports.value'), 'type' => 'money'],
            ],
            [
                ['label' => __('reports.ingredients'), 'value' => (string) count($rows)],
                ['label' => __('reports.total_value'), 'value' => number_format($totalValue, 2, '.', '')],
            ],
        ];
    }

    private function foodCostReport(?int $branchId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $summary = $this->metrics->summary($branchId, $from, $to);

        $rows = [
            ['line' => __('reports.net_sales'), 'amount' => $summary['net_sales']],
            ['line' => __('reports.food_cost'), 'amount' => $summary['food_cost']],
            ['line' => __('reports.food_cost_percent'), 'amount' => $summary['net_sales'] !== '0.00'
                ? number_format((float) $summary['food_cost'] / (float) $summary['net_sales'] * 100, 2, '.', '').'%'
                : '0.00%'],
            ['line' => __('reports.wastage_cost'), 'amount' => $summary['wastage_cost']],
        ];

        return [
            __('reports.types.food_cost'),
            __('reports.descriptions.food_cost'),
            $rows,
            [
                ['key' => 'line', 'label' => __('reports.line'), 'type' => 'text'],
                ['key' => 'amount', 'label' => __('reports.amount'), 'type' => 'money'],
            ],
            [
                ['label' => __('reports.food_cost'), 'value' => $summary['food_cost']],
                ['label' => __('reports.food_cost_percent'), 'amount' => null, 'value' => ((float) $summary['net_sales']) > 0
                    ? number_format((float) $summary['food_cost'] / (float) $summary['net_sales'] * 100, 2, '.', '').'%'
                    : '0.00%'],
            ],
        ];
    }

    private function branchPlReport(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->metrics->branchComparison($from, $to);

        return [
            __('reports.types.branch_pl'),
            __('reports.descriptions.branch_pl'),
            $rows,
            [
                ['key' => 'code', 'label' => __('branches.code'), 'type' => 'text'],
                ['key' => 'name', 'label' => __('branches.name'), 'type' => 'text'],
                ['key' => 'net_sales', 'label' => __('reports.net_sales'), 'type' => 'money'],
                ['key' => 'food_cost', 'label' => __('reports.food_cost'), 'type' => 'money'],
                ['key' => 'wastage_cost', 'label' => __('reports.wastage_cost'), 'type' => 'money'],
                ['key' => 'salary_cost', 'label' => __('reports.salary_cost'), 'type' => 'money'],
                ['key' => 'expenses', 'label' => __('reports.expenses'), 'type' => 'money'],
                ['key' => 'net_profit', 'label' => __('reports.net_profit'), 'type' => 'money'],
            ],
            [],
        ];
    }

    private function pdf(string $filename, string $title, array $columns, array $rows, ?int $branchId, CarbonImmutable $from, CarbonImmutable $to)
    {
        $branchName = $branchId !== null
            ? (string) Branch::query()->whereKey($branchId)->value('name')
            : __('reports.all_branches');

        $pdf = app(PDF::class);
        $pdf->loadView('reports.export', [
            'title' => $title,
            'columns' => $columns,
            'rows' => $rows,
            'branchName' => $branchName,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'generatedAt' => now()->format('d M Y, h:i A'),
        ]);

        return $pdf->download($filename.'.pdf');
    }

    private function xlsx(string $filename, string $title, array $columns, array $rows, ?int $branchId, CarbonImmutable $from, CarbonImmutable $to)
    {
        $writer = new Writer;
        $writer->openToBrowser($filename.'.xlsx');

        $sheet = $writer->getCurrentSheet();
        $sheet->setName(mb_substr($title, 0, 30));

        $writer->addRow(Row::fromValues(
            array_map(fn (array $column) => $column['label'], $columns),
        ));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(
                array_map(fn (array $column) => (string) ($row[$column['key']] ?? ''), $columns),
            ));
        }

        $writer->close();

        return response('', 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function csv(string $filename, array $columns, array $rows)
    {
        $callback = function () use ($columns, $rows): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, array_map(fn (array $column) => $column['label'], $columns));

            foreach ($rows as $row) {
                fputcsv($out, array_map(fn (array $column) => (string) ($row[$column['key']] ?? ''), $columns));
            }

            fclose($out);
        };

        return ResponseFacade::stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.csv"',
        ]);
    }

    private function period(Request $request): array
    {
        $from = CarbonImmutable::parse((string) $request->input('from', now()->subDays(29)->toDateString()))->startOfDay();
        $to = CarbonImmutable::parse((string) $request->input('to', now()->toDateString()))->endOfDay();

        return [$from, $to];
    }

    private function branchId(Request $request): ?int
    {
        $user = $request->user('staff');

        if ($user->branch_id !== null) {
            return (int) $user->branch_id;
        }

        $branchParam = (int) $request->input('branch', 0);

        return $branchParam > 0 ? $branchParam : null;
    }
}
