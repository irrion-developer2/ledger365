<?php

namespace App\Http\Controllers\SuperAdmin\Reports;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\DataTables\DataTables;
use App\Models\TallyGroup;
use App\Models\TallyLedger;
use App\Models\TallyVoucher;
use App\Models\TallyVoucherItem;
use App\Models\TallyItem;
use App\Models\TallyCompany;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; 
use Illuminate\Support\Facades\DB;
use App\Services\ReportService;

class ReportItemGroupController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function index()
    {
        return View('superadmin.reports.itemGroup.index');
    }

    public function getData(Request $request)
    {
        $companyGuids = $this->reportService->companyData();

        if ($request->ajax()) {
            $query = TallyItem::select('tally_items.*')
                ->join('tally_voucher_items', 'tally_items.name', '=', 'tally_voucher_items.stock_item_name')
                ->join('tally_vouchers', 'tally_voucher_items.tally_voucher_id', '=', 'tally_vouchers.id')
                ->groupBy('tally_items.id')
                ->havingRaw('SUM(CASE WHEN tally_vouchers.voucher_type = "Sales" THEN tally_voucher_items.amount ELSE 0 END) > 0')
                ->whereIn('tally_vouchers.company_guid', $companyGuids)
                ->get();

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('total_sales', function ($data) {

                    $stockItemVoucherSaleItem = TallyVoucherItem::where('stock_item_name', $data->name)
                        ->whereHas('tallyVoucher', function ($query) {
                            $query->where('voucher_type', 'Sales');
                        })
                        ->sum('amount');

                    $stockItemVoucherCreditNoteItem = TallyVoucherItem::where('stock_item_name', $data->name)
                        ->whereHas('tallyVoucher', function ($query) {
                            $query->where('voucher_type', 'Credit Note');
                        })
                        ->sum('amount');

                    $totalSales = $stockItemVoucherSaleItem + $stockItemVoucherCreditNoteItem;

                    return number_format($totalSales, 2);
                })
                ->addColumn('qty_sold', function ($data) {

                    $stockItemVoucherSaleItem = TallyVoucherItem::where('stock_item_name', $data->name)
                        ->whereHas('tallyVoucher', function ($query) {
                            $query->where('voucher_type', 'Sales');
                        })
                        ->sum('billed_qty');

                    $stockItemVoucherCreditNoteItem = TallyVoucherItem::where('stock_item_name', $data->name)
                        ->whereHas('tallyVoucher', function ($query) {
                            $query->where('voucher_type', 'Credit Note');
                        })
                        ->sum('billed_qty');

                    $qtySold = $stockItemVoucherSaleItem - $stockItemVoucherCreditNoteItem;

                    $unit = $data->unit ?? $data->pluck('unit')->filter()->first();

                    return number_format($qtySold, 2) . ' ' . $unit;
                })
                ->addColumn('customer_count', function ($data) {

                    $stockItemVoucherSaleItem = TallyVoucherItem::where('stock_item_name', $data->name)
                        ->whereHas('tallyVoucher', function ($query) {
                            $query->where('voucher_type', 'Sales');
                        })
                        ->count();

                    $stockItemVoucherCreditNoteItem = TallyVoucherItem::where('stock_item_name', $data->name)
                        ->whereHas('tallyVoucher', function ($query) {
                            $query->where('voucher_type', 'Credit Note');
                        })
                        ->count();
                        

                    $CustomerCount = $stockItemVoucherSaleItem - $stockItemVoucherCreditNoteItem;

                    return number_format($CustomerCount, 2);
                })
                ->addColumn('avg_sales', function ($data) {

                    $stockItemVoucherSaleItem = TallyVoucherItem::where('stock_item_name', $data->name)
                        ->whereHas('tallyVoucher', function ($query) {
                            $query->where('voucher_type', 'Sales');
                        })
                        ->orderByDesc('id')
                        ->value('rate');

                    $stockItemVoucherCreditNoteItem = TallyVoucherItem::where('stock_item_name', $data->name)
                        ->whereHas('tallyVoucher', function ($query) {
                            $query->where('voucher_type', 'Credit Note');
                        })
                        ->sum('rate');

                    $totalSales = $stockItemVoucherSaleItem - $stockItemVoucherCreditNoteItem;

                    return number_format($stockItemVoucherSaleItem, 2);
                })
                ->make(true);
        }
    }


    public function AllItemGroupLedgerReports($itemGroupLedgerId)
    {
        $companyGuids = $this->reportService->companyData();

        $itemGroupLedger = TallyItem::whereIn('company_guid', $companyGuids)->findOrFail($itemGroupLedgerId);

        $menuItems = TallyItem::select('tally_items.*')
                    ->join('tally_voucher_items', 'tally_items.name', '=', 'tally_voucher_items.stock_item_name')
                    ->join('tally_vouchers', 'tally_voucher_items.tally_voucher_id', '=', 'tally_vouchers.id')
                    ->groupBy('tally_items.id')
                    ->havingRaw('SUM(CASE WHEN tally_vouchers.voucher_type = "Sales" THEN tally_voucher_items.amount ELSE 0 END) > 0')
                    ->orderBy('tally_items.name', 'asc') 
                    ->whereIn('tally_items.company_guid', $companyGuids)
                    ->get();


        return view('superadmin.reports.itemGroup._item_group_ledger', [
            'itemGroupLedger' => $itemGroupLedger,
            'itemGroupLedgerId' => $itemGroupLedgerId ,
            'menuItems' => $menuItems
        ]);
    }


    public function ledgergetData($itemGroupLedgerId)
    {
        $companyGuids = $this->reportService->companyData();
        
        $itemGroupLedger = TallyItem::whereIn('company_guid', $companyGuids)
                                    ->findOrFail($itemGroupLedgerId);
                                    
        $itemGroupLedgerName = $itemGroupLedger->name;

        $query = TallyLedger::whereHas('tallyVouchers', function ($query) use ($itemGroupLedgerName) {
                $query->whereIn('voucher_type', ['Sales', 'Credit Note'])
                    ->whereHas('tallyVoucherItems', function ($subQuery) use ($itemGroupLedgerName) {
                        $subQuery->where('stock_item_name', $itemGroupLedgerName);
                    });
            })
            ->whereExists(function ($subQuery) use ($itemGroupLedgerName) {
                $subQuery->select(DB::raw(1))
                    ->from('tally_vouchers')
                    ->whereColumn('tally_vouchers.party_ledger_name', 'tally_ledgers.language_name')
                    ->whereIn('tally_vouchers.voucher_type', ['Sales', 'Credit Note'])
                    // ->whereIn('tally_vouchers.company_guid', $companyGuids)
                    ->whereExists(function ($innerQuery) use ($itemGroupLedgerName) {
                        $innerQuery->select(DB::raw(1))
                            ->from('tally_voucher_items')
                            ->whereColumn('tally_voucher_items.tally_voucher_id', 'tally_vouchers.id')
                            ->where('tally_voucher_items.stock_item_name', $itemGroupLedgerName);
                    });
            })
            ->get();

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('total_sales', function ($data) use ($itemGroupLedgerName) {

                $stockItemVoucherSaleItem = TallyVoucherItem::where('stock_item_name', $itemGroupLedgerName)
                    ->whereHas('tallyVoucher', function ($query) use ($data){
                        $query->where('voucher_type', 'Sales');
                        $query->where('party_ledger_name', $data->language_name);
                    })
                    ->orderByDesc('id')
                    ->value('amount');
                    // ->sum('amount');

                $stockItemVoucherCreditNoteItem = TallyVoucherItem::where('stock_item_name', $itemGroupLedgerName)
                    ->whereHas('tallyVoucher', function ($query) use ($data){
                        $query->where('voucher_type', 'Credit Note');
                        $query->where('party_ledger_name', $data->language_name);
                    })
                    ->orderByDesc('id')
                    ->value('amount');
                    // ->sum('amount');

                $totalSales = $stockItemVoucherSaleItem + $stockItemVoucherCreditNoteItem;

                return number_format($totalSales, 2);
            })
            // ->addColumn('qty_sold', function ($data) use ($itemGroupLedgerName) {

            //     $stockItemVoucherSaleItem = TallyVoucherItem::where('stock_item_name', $itemGroupLedgerName)
            //         ->whereHas('tallyVoucher', function ($query) {
            //             $query->where('voucher_type', 'Sales');
            //         })
            //         ->sum('billed_qty');

            //     $stockItemVoucherCreditNoteItem = TallyVoucherItem::where('stock_item_name', $itemGroupLedgerName)
            //         ->whereHas('tallyVoucher', function ($query) {
            //             $query->where('voucher_type', 'Credit Note');
            //         })
            //         ->sum('billed_qty');

            //     $qtySold = $stockItemVoucherSaleItem - $stockItemVoucherCreditNoteItem;

            //     // $unit = $data->unit ?? $data->pluck('unit')->filter()->first();

            //     return number_format($qtySold, 2);
            // })
            ->addColumn('qty_sold', function ($data) use ($itemGroupLedgerName) {

                $stockItemVoucherSaleItem = TallyVoucherItem::where('stock_item_name', $itemGroupLedgerName)
                    ->whereHas('tallyVoucher', function ($query) use ($data) {
                        $query->where('voucher_type', 'Sales');
                        $query->where('party_ledger_name', $data->language_name);
                    })
                    ->count();

                $stockItemVoucherCreditNoteItem = TallyVoucherItem::where('stock_item_name', $itemGroupLedgerName)
                    ->whereHas('tallyVoucher', function ($query) use ($data) {
                        $query->where('voucher_type', 'Credit Note');
                        $query->where('party_ledger_name', $data->language_name);
                    })
                    ->count();

                $CustomerCount = $stockItemVoucherSaleItem + $stockItemVoucherCreditNoteItem;

                return number_format($CustomerCount, 2);
            })
            ->addColumn('avg_sales', function ($data) use ($itemGroupLedgerName) {

                $stockItemAmount = TallyVoucherItem::where('stock_item_name', $itemGroupLedgerName)
                    ->whereHas('tallyVoucher', function ($query) use ($data){
                        $query->where('voucher_type', 'Sales');
                        $query->where('party_ledger_name', $data->language_name);
                    })
                    ->orderByDesc('id')
                    ->value('amount');

                $stockItemQty = TallyVoucherItem::where('stock_item_name', $itemGroupLedgerName)
                    ->whereHas('tallyVoucher', function ($query) use ($data){
                        $query->where('voucher_type', 'Sales');
                        $query->where('party_ledger_name', $data->language_name);
                    })
                    ->orderByDesc('id')
                    ->value('billed_qty');

                // $stockItemRate = TallyVoucherItem::where('stock_item_name', $itemGroupLedgerName)
                //     ->whereHas('tallyVoucher', function ($query) use ($data){
                //         $query->where('voucher_type', 'Sales');
                //         $query->where('party_ledger_name', $data->language_name);
                //     })
                //     ->orderByDesc('id')
                //     // ->value('rate');
                //     // ->get();
                //     ->value('rate');

                //     // dd($stockItemVoucherSaleItem);

                // $stockItemVoucherCreditNoteItem = TallyVoucherItem::where('stock_item_name', $itemGroupLedgerName)
                //     ->whereHas('tallyVoucher', function ($query) use ($data){
                //         $query->where('voucher_type', 'Credit Note');
                //         // $query->where('party_ledger_name', $data->language_name);
                //     })
                //     ->sum('rate');

                // $totalSales = $stockItemVoucherSaleItem - $stockItemVoucherCreditNoteItem;

                $AvgSales = $stockItemAmount / $stockItemQty;
                return number_format($AvgSales, 2);
            })
            ->make(true);
    }


    // public function ledgergetData($itemGroupLedgerId)
    // {
    //     $itemGroupLedger = TallyItem::findOrFail($itemGroupLedgerId);
    //     $itemGroupLedgerName = $itemGroupLedger->name;
    //     // dd($itemGroupLedgerName);
        
    //         $query = TallyLedger::whereHas('tallyVouchers', function ($query) use ($itemGroupLedgerName) {
    //             $query->whereIn('voucher_type', ['Sales', 'Credit Note'])
    //                 ->whereHas('tallyVoucherItems', function ($subQuery) use ($itemGroupLedgerName) {
    //                     $subQuery->where('stock_item_name', $itemGroupLedgerName);
    //                 });
    //         })
    //         ->whereExists(function ($subQuery) use ($itemGroupLedgerName) {
    //             $subQuery->select(DB::raw(1))
    //                 ->from('tally_vouchers')
    //                 ->whereColumn('tally_vouchers.party_ledger_name', 'tally_ledgers.language_name')
    //                 ->whereIn('tally_vouchers.voucher_type', ['Sales', 'Credit Note'])
    //                 ->whereExists(function ($innerQuery) use ($itemGroupLedgerName) {
    //                     $innerQuery->select(DB::raw(1))
    //                         ->from('tally_voucher_items')
    //                         ->whereColumn('tally_voucher_items.tally_voucher_id', 'tally_vouchers.id')
    //                         ->where('tally_voucher_items.stock_item_name', $itemGroupLedgerName);
    //                 });
    //         })
    //         ->get();
            
    //         return DataTables::of($query)
    //             ->addIndexColumn()
    //             // ->addColumn('total_sales', function ($data) {

    //             // $ledgerData = TallyVoucher::where('ledger_guid', $data->guid)
    //             //     ->where('voucher_type', 'Sales')
    //             //     ->pluck('id', 'ledger_guid');


    //             //     $ledgerGuids = $ledgerData->keys();
    //             //     $tallyVoucherIds = $ledgerData->values();

                            

    //             //     // $stockItemVoucherSaleItem = TallyVoucherItem::where('stock_item_name', $itemGroupLedgerName)
    //             //     //     ->whereHas('tallyVoucher', function ($query) {
    //             //     //         $query->where('voucher_type', 'Sales');
    //             //     //     })
    //             //     //     ->sum('amount');

    //             //         // dd($stockItemVoucherSaleItem);


    //             //     // $stockItemVoucherSaleItem = TallyVoucherItem::where('stock_item_name', $data->name)
    //             //     //     ->whereHas('tallyVoucher', function ($query) {
    //             //     //         $query->where('voucher_type', 'Sales');
    //             //     //     })
    //             //     //     ->sum('amount');

    //             //     // $stockItemVoucherCreditNoteItem = TallyVoucherItem::where('stock_item_name', $data->name)
    //             //     //     ->whereHas('tallyVoucher', function ($query) {
    //             //     //         $query->where('voucher_type', 'Credit Note');
    //             //     //     })
    //             //     //     ->sum('amount');

    //             //     // $totalSales = $stockItemVoucherSaleItem + $stockItemVoucherCreditNoteItem;

    //             //     return number_format($stockItemVoucherSaleItem, 2);
    //             // })
    //             ->make(true);
      
    // }


    // public function getData(Request $request)
    // {
    //     if ($request->ajax()) {
    //         $query = TallyItem::get();

    //         return DataTables::of($query)
    //             ->addIndexColumn()
    //             ->addColumn('total_sales', function ($data) {

    //                 $stockItemVoucherSaleItem = TallyVoucherItem::where('stock_item_name', $data->name)
    //                 ->whereHas('tallyVoucher', function ($query) {
    //                     $query->where('voucher_type', 'Sales');
    //                 })
    //                 ->sum('amount');

    //                 $stockItemVoucherCreditNoteItem = TallyVoucherItem::where('stock_item_name', $data->name)
    //                 ->whereHas('tallyVoucher', function ($query) {
    //                     $query->where('voucher_type', 'Credit Note');
    //                 })
    //                 ->sum('amount');

    //                 $totalSales = $stockItemVoucherSaleItem + $stockItemVoucherCreditNoteItem;
        
    //                 return number_format($totalSales, 2);
    //             })
    //             ->addColumn('qty_sold', function ($data) {

    //                 $stockItemVoucherSaleItem = TallyVoucherItem::where('stock_item_name', $data->name)
    //                 ->whereHas('tallyVoucher', function ($query) {
    //                     $query->where('voucher_type', 'Sales');
    //                 })
    //                 ->sum('billed_qty');

    //                 $stockItemVoucherCreditNoteItem = TallyVoucherItem::where('stock_item_name', $data->name)
    //                 ->whereHas('tallyVoucher', function ($query) {
    //                     $query->where('voucher_type', 'Credit Note');
    //                 })
    //                 ->sum('billed_qty');

    //                 $qtySold = $stockItemVoucherSaleItem - $stockItemVoucherCreditNoteItem;

    //                 $unit = $data->unit ?? $data->pluck('unit')->filter()->first();
        
    //                 return number_format($qtySold, 2) . ' ' . $unit;

    //             })
    //             ->addColumn('customer_count', function ($data) {

    //                 $stockItemVoucherSaleItem = TallyVoucherItem::where('stock_item_name', $data->name)
    //                 ->whereHas('tallyVoucher', function ($query) {
    //                     $query->where('voucher_type', 'Sales');
    //                 })
    //                 ->count();

    //                 $stockItemVoucherCreditNoteItem = TallyVoucherItem::where('stock_item_name', $data->name)
    //                 ->whereHas('tallyVoucher', function ($query) {
    //                     $query->where('voucher_type', 'Credit Note');
    //                 })
    //                 ->count();

    //                 $CustomerCount = $stockItemVoucherSaleItem - $stockItemVoucherCreditNoteItem;
        
    //                 return number_format($CustomerCount, 2);
    //             })
    //             ->addColumn('avg_sales', function ($data) {

    //                 $stockItemVoucherSaleItem = TallyVoucherItem::where('stock_item_name', $data->name)
    //                 ->whereHas('tallyVoucher', function ($query) {
    //                     $query->where('voucher_type', 'Sales');
    //                 })
    //                 ->orderByDesc('id') // Order by descending id to get the last record
    //                 ->value('rate'); // Retrieve the 'rate' value from the last record
                

    //                 $stockItemVoucherCreditNoteItem = TallyVoucherItem::where('stock_item_name', $data->name)
    //                 ->whereHas('tallyVoucher', function ($query) {
    //                     $query->where('voucher_type', 'Credit Note');
    //                 })
    //                 ->sum('rate');

    //                 $totalSales = $stockItemVoucherSaleItem - $stockItemVoucherCreditNoteItem;

        
    //                 return number_format($stockItemVoucherSaleItem, 2);
    //             })
    //             ->make(true);
    //     }
    // }

}