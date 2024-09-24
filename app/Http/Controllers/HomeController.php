<?php

namespace App\Http\Controllers;

use DateTime;
use App\Models\User;
use App\Models\TallyItem;
use App\Models\TallyVoucher;
use App\Models\TallyGroup;
use Yajra\DataTables\DataTables;
use App\Models\TallyLedger;
use App\Models\TallyVoucherHead;
use App\Models\TallyVoucherAccAllocationHead;
use App\Models\TallyVoucherItem;
use App\Models\TallyCompany;
use Illuminate\Http\Request;
use App\Services\ReportService;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function index()
    {
        $companyGuids = $this->reportService->companyData();
        $user = User::count();
        $role = auth()->user()->role;

        /* cashBankAmount */
        $cashBank = TallyGroup::whereIn('company_guid', $companyGuids)
                    ->where('name', 'Bank Accounts')
                    ->first();

        $cashBankName = $cashBank ? $cashBank->name : 'Bank Accounts';

        $cashBankLedgerIds = TallyLedger::where('parent', $cashBankName)
                            ->whereIn('company_guid', $companyGuids)
                            ->pluck('guid');

        $cashBankAmountHead = TallyVoucherHead::join('tally_vouchers', 'tally_voucher_heads.tally_voucher_id', '=', 'tally_vouchers.id')
        ->whereIn('tally_voucher_heads.ledger_guid', $cashBankLedgerIds)
        ->whereIn('tally_vouchers.company_guid', $companyGuids)
        ->sum('tally_voucher_heads.amount');

        $cashBankAmountAcc = TallyVoucherAccAllocationHead::join('tally_vouchers', 'tally_voucher_acc_allocation_heads.tally_voucher_id', '=', 'tally_vouchers.id')
        ->whereIn('tally_voucher_acc_allocation_heads.ledger_guid', $cashBankLedgerIds)
        ->whereIn('tally_vouchers.company_guid', $companyGuids)
        ->sum('tally_voucher_acc_allocation_heads.amount');

        $cashBankAmount = $cashBankAmountHead + $cashBankAmountAcc;
        /* cashBankAmount */

        /* Inventory Amount */
        // $stockItemVoucherBalance = $this->reportService->calculateStockValue($companyGuids);
        /* Inventory Amount */

         /* Payables */
        $payableCreditNote = $this->calculatePayableCreditNote($companyGuids);
         /* Payables */

        /* Sales Receipt chart */
        $chartData = $this->chartSaleReceipt($companyGuids);
        $chartSaleAmt = abs(array_sum($chartData['sales']));
        $chartReceiptAmt = abs(array_sum($chartData['receipts']));
        $lastMonthsTotal = $this->getLastMonthsTotal($chartData);
        /* Sales Receipt chart */

        /* pie chart */
        $pieChartData = $this->getPieChartData($companyGuids);
        $pieChartDataTotal = $pieChartData['total'];
        $pieChartDataOverall = $pieChartData['data'];
        /* pie chart */

        /* Cash Amount */
        $cashGroup = TallyGroup::where('name', 'Cash-in-Hand')->whereIn('company_guid', $companyGuids)->first();
        $cashName = $cashGroup ? $cashGroup->name : 'Cash-in-Hand';

        $cashAmount = TallyVoucherHead::whereIn('ledger_guid', function($query) use ($cashName, $companyGuids) {
            $query->select('guid')
                ->from('tally_ledgers')
                ->where('parent', $cashName)
                ->whereIn('company_guid', $companyGuids);
        })->sum('amount');
        /* Cash Amount */


        if ($role == 'SuperAdmin') {
            return view('dashboard', compact('user'));
        } elseif ($role == 'Users') {
            return view('users-dashboard', compact('user','cashBankAmount','cashAmount','payableCreditNote','chartSaleAmt','chartReceiptAmt','chartData','lastMonthsTotal','pieChartDataOverall','pieChartDataTotal'));
        }
        abort(403, 'Unauthorized action.');
    }

    public function getPieChartData($companyGuids)
    {
        $pieChartData = DB::table('tally_ledgers as tl')
            ->leftJoin('tally_voucher_heads as tvh', 'tl.guid', '=', 'tvh.ledger_guid')
            ->select('tl.language_name', DB::raw('COALESCE(SUM(tvh.amount), 0) AS total_amount'))
            ->where('tl.parent', 'Sundry Debtors')
            ->whereIn('tl.company_guid', $companyGuids)
            ->groupBy('tl.language_name')
            ->pluck('total_amount', 'language_name');

        $pieChartDataArray = $pieChartData->toArray();

        $totalAmount = array_sum(array_map('abs', $pieChartDataArray));
        return [
            'data' => $pieChartDataArray,
            'total' => $totalAmount
        ];
    }

    private function getLastMonthsTotal(array $chartData)
    {
        $currentDate = new \DateTime();
        $months = [];
        for ($i = 0; $i < 1; $i++) {
            $date = new \DateTime("first day of -$i month");
            $months[] = $date->format('F');
        }
        $receiptTotal = 0;
        $salesTotal = 0;

        foreach ($months as $month) {
            if (isset($chartData['receipts'][$month])) {
                $receiptTotal += $chartData['receipts'][$month];
            }
            if (isset($chartData['sales'][$month])) {
                $salesTotal += $chartData['sales'][$month];
            }
        }

        return [
            'sales' => $salesTotal,
            'receipts' => $receiptTotal,
        ];
    }

    private function chartSaleReceipt($companyGuids)
    {
        $salesData = [];
        $receiptData = [];

        for ($month = 4; $month <= 12; $month++) {
            $monthName = DateTime::createFromFormat('!m', $month)->format('F');

            $totalSales = TallyVoucher::join('tally_voucher_heads', function($join) {
                $join->on('tally_voucher_heads.ledger_guid', '=', 'tally_vouchers.ledger_guid')
                     ->on('tally_voucher_heads.tally_voucher_id', '=', 'tally_vouchers.id');
            })
            ->where('tally_vouchers.voucher_type', 'Sales')
            ->whereIn('tally_vouchers.company_guid', $companyGuids)
            ->whereMonth('tally_vouchers.voucher_date', $month)
            ->sum('tally_voucher_heads.amount');

            $totalReceipts = TallyVoucher::join('tally_voucher_heads', function($join) {
                $join->on('tally_voucher_heads.ledger_guid', '=', 'tally_vouchers.ledger_guid')
                     ->on('tally_voucher_heads.tally_voucher_id', '=', 'tally_vouchers.id');
            })
            ->where('tally_vouchers.voucher_type', 'Receipt')
            ->whereIn('tally_vouchers.company_guid', $companyGuids)
            ->whereMonth('tally_vouchers.voucher_date', $month)
            ->sum('tally_voucher_heads.amount');

            $salesData[$monthName] = $totalSales;
            $receiptData[$monthName] = $totalReceipts;
        }

        return [
            'sales' => $salesData,
            'receipts' => $receiptData,
        ];
    }

    private function calculatePayableCreditNote($companyGuids)
    {
        $CreditAmount = TallyVoucher::join('tally_voucher_heads', 'tally_voucher_heads.ledger_guid', '=', 'tally_vouchers.ledger_guid')
                ->where('tally_vouchers.voucher_type', 'credit note')
                ->whereIn('tally_vouchers.company_guid', $companyGuids)
                ->sum('tally_voucher_heads.amount');

        $DebitAmount = TallyVoucher::join('tally_voucher_heads', 'tally_voucher_heads.ledger_guid', '=', 'tally_vouchers.ledger_guid')
                ->where('tally_vouchers.voucher_type', 'debit note')
                ->whereIn('tally_vouchers.company_guid', $companyGuids)
                ->sum('tally_voucher_heads.amount');

        // $PurchaseledgerIds = TallyVoucher::where('voucher_type', 'Purcahse')
        //                 ->whereIn('company_guid', $companyGuids)
        //                 ->pluck('ledger_guid');

        // $PurcahseAmount = TallyVoucherHead::whereIn('ledger_guid', $PurchaseledgerIds)
        //     ->sum('amount');

        // $SaleledgerIds = TallyVoucher::where('voucher_type', 'Sales')
        //                 ->whereIn('company_guid', $companyGuids)
        //                 ->pluck('ledger_guid');

        // $SaleAmount = TallyVoucherHead::whereIn('ledger_guid', $SaleledgerIds)
        //     ->sum('amount');

        $total = $CreditAmount + $DebitAmount;

        return $total;
    }

}
