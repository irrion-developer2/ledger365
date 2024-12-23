<?php

namespace App\Http\Controllers\App\Reports;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\DataTables\DataTables;
use App\Models\TallyLedgerGroup;
use App\Models\TallyLedger;
use App\Models\TallyVoucherHead;
use App\Models\TallyItem;
use App\Models\TallyCompany;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\DataTables\SuperAdmin\GeneralLedgerDataTable;
use Illuminate\Support\Facades\DB;
use App\Services\ReportService;

class ReportGeneralLedgerController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function index(GeneralLedgerDataTable $dataTable)
    {
        return $dataTable->render('app.reports.generalLedger.index');
    }

    public function AllGeneralLedgerReports($generalLedgerId)
    {
        $companyIds = $this->reportService->companyData();

        $generalLedger = TallyLedgerGroup::whereIn('company_guid', $companyGuids)
                                    ->findOrFail($generalLedgerId);

        $menuItems = TallyLedgerGroup::where('parent', '')->orWhereNull('parent')->whereIn('company_guid', $companyGuids)->get();

        return view('app.reports.generalLedger._general_ledger_details', [
            'generalLedger' => $generalLedger,
            'generalLedgerId' => $generalLedgerId ,
            'menuItems' => $menuItems
        ]);
    }

    private $normalizedNames = [
        'Direct Expenses, Expenses (Direct)' => 'Direct Expenses',
        'Direct Incomes, Income (Direct)' => 'Direct Incomes',
        'Indirect Expenses, Expenses (Indirect)' => 'Indirect Expenses',
        'Indirect Incomes, Income (Indirect)' => 'Indirect Incomes',
    ];


    public function getGeneralLedgerData($generalLedgerId)
    {
        $companyGuids = $this->reportService->companyData();

        $generalLedger = TallyLedgerGroup::find($generalLedgerId);

        $data = collect();

        if ($generalLedger) {
            $query = TallyLedgerGroup::select(
                'tally_ledger_groups.id',
                'tally_ledger_groups.name',
                \DB::raw('COUNT(tally_ledgers.id) as ledgers_count'),
                \DB::raw('SUM(CASE WHEN tally_voucher_heads.entry_type = "debit" THEN tally_voucher_heads.amount ELSE 0 END) as total_debit'),
                \DB::raw('SUM(CASE WHEN tally_voucher_heads.entry_type = "credit" THEN tally_voucher_heads.amount ELSE 0 END) as total_credit'),
                \DB::raw('tally_ledgers.opening_balance'),
                \DB::raw('(tally_ledgers.opening_balance +
                            SUM(CASE WHEN tally_voucher_heads.entry_type = "debit" THEN tally_voucher_heads.amount ELSE 0 END) +
                            SUM(CASE WHEN tally_voucher_heads.entry_type = "credit" THEN tally_voucher_heads.amount ELSE 0 END)) as closing_balance')
            )
            ->leftJoin('tally_ledgers', 'tally_ledger_groups.name', '=', 'tally_ledgers.parent')
            ->leftJoin('tally_voucher_heads', 'tally_ledgers.guid', '=', 'tally_voucher_heads.ledger_guid')
            ->leftJoin('tally_vouchers', 'tally_voucher_heads.tally_voucher_id', '=', 'tally_vouchers.id')
            ->where('tally_ledger_groups.parent', $generalLedger->name)                        
            ->whereNot('tally_vouchers.is_cancelled', 'Yes')
            ->whereNot('tally_vouchers.is_optional', 'Yes')
            ->whereIn('tally_ledger_groups.company_guid', $companyGuids)
            ->whereIn('tally_vouchers.company_guid', $companyGuids)
            ->groupBy('tally_ledger_groups.id', 'tally_ledger_groups.name', 'tally_ledgers.opening_balance')
            ->get();


            if (!$query->isEmpty()) {
                $data = $query;
            }
        }

        // If $data is still empty, set default data
        if ($data->isEmpty()) {
            $data = collect([
                (object)[
                    'id' => $generalLedgerId,
                    'name' => $generalLedger->name,
                    'opening_balance' => 0,
                    'total_debit' => 0,
                    'total_credit' => 0,
                    'closing_balance' => 0,
                    'created_at' => now(),
                ]
            ]);
        }

        return DataTables::of($data)
            ->addIndexColumn()
            ->editColumn('total_debit', function ($row) {
                return $this->formatNumber($row->total_debit);
            })
            ->editColumn('total_credit', function ($row) {
                return $this->formatNumber($row->total_credit);
            })
            ->editColumn('opening_balance', function ($row) {
                return $this->formatNumber($row->opening_balance);
            })
            ->editColumn('closing_balance', function ($row) {
                return $this->formatNumber($row->closing_balance);
            })
            ->editColumn('name', function ($row) {
                return $row->name;
            })
            ->editColumn('created_at', function ($row) {
                return Carbon::parse($row->created_at)->format('Y-m-d H:i:s');
            })
            ->make(true);
    }

    public function AllGeneralGroupLedgerReports($generalLedgerId)
    {
        $companyGuids = $this->reportService->companyData();

        $generalLedger = TallyLedgerGroup::whereIn('company_guid', $companyGuids)->findOrFail($generalLedgerId);

        $menuItems = TallyLedgerGroup::where('parent', $generalLedger->parent)->whereIn('company_guid', $companyGuids)->get();

        return view('app.reports.generalLedger._general_group_ledger_details', [
            'generalLedger' => $generalLedger,
            'generalLedgerId' => $generalLedgerId ,
            'menuItems' => $menuItems
        ]);
    }

    public function getGeneralGroupLedgerData($generalLedgerId)
    {
        $companyGuids = $this->reportService->companyData();

        $generalLedger = TallyLedgerGroup::whereIn('company_guid', $companyGuids)
                                    ->find($generalLedgerId);

        if (!$generalLedger) {
            return response()->json(['message' => 'General ledger not found.'], 404);
        }

        $generalLedgerName = $generalLedger->name;

        $normalizedNames = [
            'Direct Expenses, Expenses (Direct)' => 'Direct Expenses',
            'Direct Incomes, Income (Direct)' => 'Direct Incomes',
            'Indirect Expenses, Expenses (Indirect)' => 'Indirect Expenses',
            'Indirect Incomes, Income (Indirect)' => 'Indirect Incomes',
        ];

        if (array_key_exists($generalLedgerName, $normalizedNames)) {
            $generalLedgerName = $normalizedNames[$generalLedgerName];
        }

        $query = TallyLedger::where('parent', $generalLedgerName)
            ->leftJoin('tally_voucher_heads', 'tally_ledgers.guid', '=', 'tally_voucher_heads.ledger_guid')
            ->leftJoin('tally_vouchers', 'tally_voucher_heads.tally_voucher_id', '=', 'tally_vouchers.id')
            ->select(
                'tally_ledgers.name',
                'tally_ledgers.guid',
                'tally_ledgers.opening_balance',
                DB::raw('SUM(CASE WHEN tally_voucher_heads.entry_type = "debit" THEN tally_voucher_heads.amount ELSE 0 END) as debit'),
                DB::raw('SUM(CASE WHEN tally_voucher_heads.entry_type = "credit" THEN tally_voucher_heads.amount ELSE 0 END) as credit'),
                DB::raw('(tally_ledgers.opening_balance +
                        SUM(CASE WHEN tally_voucher_heads.entry_type = "debit" THEN tally_voucher_heads.amount ELSE 0 END) +
                        SUM(CASE WHEN tally_voucher_heads.entry_type = "credit" THEN tally_voucher_heads.amount ELSE 0 END)) as closing_balance')
            )                        
            ->whereNot('tally_vouchers.is_cancelled', 'Yes')
            ->whereNot('tally_vouchers.is_optional', 'Yes')
            ->whereIn('tally_vouchers.company_guid', $companyGuids) // Corrected here
            ->groupBy('tally_ledgers.name', 'tally_ledgers.guid', 'tally_ledgers.opening_balance');

        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('opening_balance', function ($row) {
                return $this->formatNumber($row->opening_balance);
            })
            ->editColumn('debit', function ($row) {
                return $this->formatNumber($row->debit);
            })
            ->editColumn('credit', function ($row) {
                return $this->formatNumber($row->credit);
            })
            ->editColumn('closing_balance', function ($row) {
                return $this->formatNumber($row->closing_balance);
            })
            ->make(true);
    }

    protected function formatNumber($value)
    {
        if (!is_numeric($value) || $value == 0) {
            return '-';
        }

        $floatValue = (float) $value;
        return number_format(abs($floatValue), 2, '.', '');
    }

}
