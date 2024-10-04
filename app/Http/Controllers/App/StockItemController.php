<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\DataTables\DataTables;
use App\Models\TallyItem;
use App\Models\TallyVoucherItem;
use App\Models\TallyVoucher;
use App\Models\TallyCompany;
use App\Models\TallyVoucherHead;
use App\Models\TallyLedger;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\ReportService;
use App\DataTables\SuperAdmin\StockItemDataTable;

class StockItemController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function index()
    {
        return View ('app.stock-items.index');
    }

    public function getData(Request $request)
    {

        $companyGuids = $this->reportService->companyData();

        if ($request->ajax()) {
            $startTime = microtime(true);
            $stockItems = TallyItem::with('tallyVoucherItems')
                                    ->whereIn('company_guid', $companyGuids);

                                // dd($stockItems);
            $endTime1 = microtime(true);
            $executionTime1 = $endTime1 - $startTime;

            Log::info('Total first db request execution time for StockItemController.getDATA:', ['time_taken' => $executionTime1 . ' seconds']);

            $dataTable = DataTables::of($stockItems)
                ->addIndexColumn()

                ->addColumn('stockonhand_opening_balance', function ($entry) {
                    $openingBalance = trim($entry->opening_balance);

                    $numericPart = '';
                    $unitPart = '';

                    if (preg_match('/^([\d.,]+)\s*(.*)$/', $openingBalance, $matches)) {
                        $numericPart = $matches[1];
                        $unitPart = isset($matches[2]) ? $matches[2] : '';
                    } else {
                        // \Log::warning("Failed to match opening balance: $openingBalance");
                    }
                    $openingBalanceValue = (float) str_replace([',', ' '], '', $numericPart);

                    $unit = $entry->unit ?? $entry->pluck('unit')->filter()->first();


                    $stockItemData = $this->calculateStockItemVoucherBalance($entry->name);
                    $stockItemVoucherBalance = $stockItemData['balance'];

                    $stockOnHandBalance = $openingBalanceValue - $stockItemVoucherBalance;

                    return $stockOnHandBalance . ' ' . $unit;
                })
                ->addColumn('stockonhand_opening_value', function ($entry) {
                    $stockOnHandBalance = 0;
                    $openingBalance = 0;
                    $stockOnHandValue = 0;

                    // Extract the opening balance and value
                    $openingBalance = $this->reportService->extractNumericValue($entry->opening_balance);
                    $openingValue = $this->reportService->extractNumericValue($entry->opening_value);

                    // Calculate stock item voucher balances and amounts
                    $stockItemData = $this->reportService->calculateStockItemVoucherBalance($entry->name);
                    $stockItemVoucherPurchaseBalance = $stockItemData['purchase_qty'];
                    $stockItemVoucherDebitNoteBalance = $stockItemData['debit_note_qty'];
                    $stockItemVoucherHandBalance = $stockItemData['balance'];

                    $stockAmountData = $this->reportService->calculateStockItemVoucherAmount($entry->name);
                    $stockItemVoucherPurchaseAmount = $stockAmountData['purchase_amt'];
                    $stockItemVoucherDebitNoteAmount = $stockAmountData['debit_note_amt'];

                    // Calculate opening amount and balances
                    $openingAmount = $stockItemVoucherPurchaseAmount + $stockItemVoucherDebitNoteAmount;
                    $finalOpeningValue = $openingValue - $openingAmount;
                    $finalOpeningBalance = $openingBalance + $stockItemVoucherPurchaseBalance - $stockItemVoucherDebitNoteBalance;

                    if ($finalOpeningBalance == 0) {
                        // Prevent division by zero by assigning a default value (e.g., 0 or a calculated fallback)
                        $stockItemVoucherSaleValue = 0;
                        $stockOnHandBalance = 0;
                    } else {
                        $stockItemVoucherSaleValue = $finalOpeningValue / $finalOpeningBalance;
                        $stockItemVoucherSaleValue = number_format($stockItemVoucherSaleValue, 4, '.', '');
                        $stockOnHandBalance = $openingBalance - $stockItemVoucherHandBalance;
                    }

                    // Calculate stock on hand value
                    $stockOnHandValue = $stockItemVoucherSaleValue * $stockOnHandBalance;

                    return number_format($stockOnHandValue, 2);
                })
                ->addColumn('avg_rate', function ($entry) {
                    $stockOnHandBalance = 0;
                    $openingBalance = 0;
                    $stockOnHandValue = 0;

                    $openingBalance = $this->extractNumericValue($entry->opening_balance);
                    $openingValue = $this->extractNumericValue($entry->opening_value);

                    $stockItemData = $this->calculateStockItemVoucherBalance($entry->name);
                    $stockItemVoucherPurchaseBalance = $stockItemData['purchase_qty'];
                    $stockItemVoucherHandBalance = $stockItemData['balance'];

                    $stockAmountData = $this->calculateStockItemVoucherAmount($entry->name);
                    $stockItemVoucherAmount = $stockAmountData['purchase_amt'];

                    $finalOpeningValue = $openingValue - $stockItemVoucherAmount;
                    $finalOpeningBalance = $openingBalance + $stockItemVoucherPurchaseBalance;

                    if ($finalOpeningBalance == 0) {
                        return number_format(0, 2);
                    }

                    $stockItemVoucherSaleValue = $finalOpeningValue / $finalOpeningBalance;
                    $stockItemVoucherSaleValue = number_format($stockItemVoucherSaleValue, 4, '.', '');
                    $stockOnHandBalance = $openingBalance - $stockItemVoucherHandBalance;

                    $stockOnHandValue = $stockItemVoucherSaleValue * $stockOnHandBalance;
                    return number_format($stockItemVoucherSaleValue, 2);
                })
                ->addColumn('voucher_date', function ($entry) {
                    // Calculate the stock item voucher amount details
                    $voucherAmountData = $this->calculateStockItemVoucherAmount($entry->name);

                    // Retrieve purchase and debit note dates
                    $purchaseDate = $voucherAmountData['purchase_date'] ? \Carbon\Carbon::parse($voucherAmountData['purchase_date'])->format('Y-m-d') : '-';
                    $debitNoteDate = $voucherAmountData['debit_note_date'] ? \Carbon\Carbon::parse($voucherAmountData['debit_note_date'])->format('Y-m-d') : '-';

                    // Return the concatenated dates (adjust as needed)
                    return "Purchase Date: $purchaseDate, Debit Note Date: $debitNoteDate";
                })
                ->make(true);

                $endTime = microtime(true);
                $executionTime = $endTime - $startTime;

                Log::info('Total end execution time for StockItemController.getDATA:', ['time_taken' => $executionTime . ' seconds']);

                return $dataTable;
        }
    }

    private function calculateStockItemVoucherBalance($stockItemName)
    {
        // Sum of billed quantities for 'Sales' vouchers
        $stockItemVoucherSaleItem = TallyVoucherItem::where('stock_item_name', $stockItemName)
            ->whereHas('tallyVoucher', function ($query) {
                $query->where('voucher_type', 'Sales');
            })->sum('billed_qty');

        // Sum of billed quantities for 'Purchase' vouchers
        $stockItemVoucherPurchaseItem = TallyVoucherItem::where('stock_item_name', $stockItemName)
            ->whereHas('tallyVoucher', function ($query) {
                $query->where('voucher_type', 'Purchase');
            })->sum('billed_qty');

        // Sum of billed quantities for 'Credit Note' vouchers
        $stockItemVoucherCreditNoteItem = TallyVoucherItem::where('stock_item_name', $stockItemName)
            ->whereHas('tallyVoucher', function ($query) {
                $query->where('voucher_type', 'Credit Note');
            })->sum('billed_qty');

        // Sum of billed quantities for 'Debit Note' vouchers
        $stockItemVoucherDebitNoteItem = TallyVoucherItem::where('stock_item_name', $stockItemName)
            ->whereHas('tallyVoucher', function ($query) {
                $query->where('voucher_type', 'Debit Note');
            })->sum('billed_qty');

        // Calculate total stock item voucher balance
        $stockItemVoucherBalance = ($stockItemVoucherSaleItem - $stockItemVoucherCreditNoteItem) - ($stockItemVoucherPurchaseItem - $stockItemVoucherDebitNoteItem);

        // Optionally, you can return the purchase item billed_qty or use it elsewhere
        return [
            'balance' => $stockItemVoucherBalance,
            'purchase_qty' => $stockItemVoucherPurchaseItem,
            'debit_note_qty' => $stockItemVoucherDebitNoteItem
        ];
    }

    private function calculateStockItemVoucherAmount($stockItemName)
    {
        // Fetch the amount and the earliest voucher date for 'Purchase' vouchers
        $purchaseVoucherData = TallyVoucherItem::where('stock_item_name', $stockItemName)
            ->whereHas('tallyVoucher', function ($query) {
                $query->where('voucher_type', 'Purchase');
            })
            ->selectRaw('SUM(amount) as total_amount, MIN(tally_vouchers.voucher_date) as voucher_date')
            ->join('tally_vouchers', 'tally_voucher_items.tally_voucher_id', '=', 'tally_vouchers.id')
            ->first();

        // Fetch the amount and the earliest voucher date for 'Debit Note' vouchers
        $debitNoteVoucherData = TallyVoucherItem::where('stock_item_name', $stockItemName)
            ->whereHas('tallyVoucher', function ($query) {
                $query->where('voucher_type', 'Debit Note');
            })
            ->selectRaw('SUM(amount) as total_amount, MIN(tally_vouchers.voucher_date) as voucher_date')
            ->join('tally_vouchers', 'tally_voucher_items.tally_voucher_id', '=', 'tally_vouchers.id')
            ->first();

        // Prepare the result
        return [
            'purchase_amt' => $purchaseVoucherData->total_amount ?? 0,
            'purchase_date' => $purchaseVoucherData->voucher_date ?? null,
            'debit_note_amt' => $debitNoteVoucherData->total_amount ?? 0,
            'debit_note_date' => $debitNoteVoucherData->voucher_date ?? null,
        ];
    }


    // private function calculateStockItemVoucherAmount($stockItemName)
    // {
    //     // Sum of billed quantities for 'Sales' vouchers
    //     $stockItemVoucherSaleAmount = TallyVoucherItem::where('stock_item_name', $stockItemName)
    //         ->whereHas('tallyVoucher', function ($query) {
    //             $query->where('voucher_type', 'Sales');
    //         })->sum('amount');

    //     // Sum of billed quantities for 'Purchase' vouchers
    //     $stockItemVoucherPurchaseAmount = TallyVoucherItem::where('stock_item_name', $stockItemName)
    //         ->whereHas('tallyVoucher', function ($query) {
    //             $query->where('voucher_type', 'Purchase');
    //         })->sum('amount');

    //         // dd($stockItemVoucherPurchaseAmount);
    //     // Sum of billed quantities for 'Credit Note' vouchers
    //     $stockItemVoucherCreditNoteAmount = TallyVoucherItem::where('stock_item_name', $stockItemName)
    //         ->whereHas('tallyVoucher', function ($query) {
    //             $query->where('voucher_type', 'Credit Note');
    //         })->sum('amount');

    //     // Sum of billed quantities for 'Debit Note' vouchers
    //     $stockItemVoucherDebitNoteAmount = TallyVoucherItem::where('stock_item_name', $stockItemName)
    //         ->whereHas('tallyVoucher', function ($query) {
    //             $query->where('voucher_type', 'Debit Note');
    //         })->sum('amount');

    //     return [
    //         'purchase_amt' => $stockItemVoucherPurchaseAmount,
    //         'debit_note_amt' => $stockItemVoucherDebitNoteAmount
    //     ];
    // }

    private function extractNumericValue($value)
    {
        // Remove non-numeric characters except for decimal points
        $numericValue = preg_replace('/[^\d.]/', '', $value);

        // Convert to float
        return (float) $numericValue;
    }

    public function AllStockItemReports($stockItemId)
    {
        $companyGuids = $this->reportService->companyData();

        $stockItem = TallyItem::whereIn('company_guid', $companyGuids)
                                    ->findOrFail($stockItemId);
        // dd($stockItem);
        $stockItemVoucherItem = TallyVoucherItem::where('stock_item_name', $stockItem->name)->get();

        //credit note
        $stockItemVoucherCreditNoteItem = TallyVoucherItem::where('stock_item_name', $stockItem->name)
        ->whereHas('tallyVoucher', function ($query) {
            $query->where('voucher_type', 'Credit Note');
        })
        ->get();

        //debit note
        $stockItemVoucherDebitNoteItem = TallyVoucherItem::where('stock_item_name', $stockItem->name)
        ->whereHas('tallyVoucher', function ($query) {
            $query->where('voucher_type', 'Debit Note');
        })
        ->get();

        $stockItemVoucherSaleItem = TallyVoucherItem::where('stock_item_name', $stockItem->name)
        ->whereHas('tallyVoucher', function ($query) {
            $query->where('voucher_type', 'Sales');
        })
        ->get();

        $stockItemVoucherSaleItemBill = $stockItemVoucherSaleItem->sum('billed_qty');

        $stockItemVoucherSaleItemConnect = [];
        foreach ($stockItemVoucherSaleItem as $stockItemVoucher) {
            $id = $stockItemVoucher->tally_voucher_id;

            $tallyVouchers = TallyVoucher::where('id', $id)->whereIn('company_guid', $companyGuids)->get();
            if ($tallyVouchers->isNotEmpty()) {
                $stockItemVoucherSaleItemConnect[] = [
                    'tally_voucher_items' => $stockItemVoucher,
                    'tally_vouchers' => $tallyVouchers,
                ];
            }
        }


        $stockItemVoucherPurchaseItem = TallyVoucherItem::where('stock_item_name', $stockItem->name)
        ->whereHas('tallyVoucher', function ($query) {
            $query->where('voucher_type', 'Purchase');
        })
        ->get();

        $stockItemVoucherPurchaseItemConnect = [];
        foreach ($stockItemVoucherPurchaseItem as $stockItemVoucher) {
            $id = $stockItemVoucher->tally_voucher_id;

            $tallyVouchers = TallyVoucher::where('id', $id)->whereIn('company_guid', $companyGuids)->get();
            if ($tallyVouchers->isNotEmpty()) {
                $stockItemVoucherPurchaseItemConnect[] = [
                    'tally_voucher_items' => $stockItemVoucher,
                    'tally_vouchers' => $tallyVouchers,
                ];
            }
        }

        $stockOnHandBalance = 0;
        $openingBalance = 0;
        $stockOnHandValue = 0;
        $openingBalance = $this->extractNumericValue($stockItem->opening_balance);
        $openingValue = $this->extractNumericValue($stockItem->opening_value);


        $stockItemVoucherSaleBalance =  $stockItemVoucherSaleItem->sum('billed_qty');
        $stockItemVoucherPurchaseBalance =  $stockItemVoucherPurchaseItem->sum('billed_qty');
        $stockItemVoucherCreditNoteBalance =  $stockItemVoucherCreditNoteItem->sum('billed_qty');
        $stockItemVoucherDebitNoteBalance =  $stockItemVoucherDebitNoteItem->sum('billed_qty');
        $stockItemVoucherBalance = ($stockItemVoucherSaleBalance - $stockItemVoucherCreditNoteBalance) - ($stockItemVoucherPurchaseBalance  - $stockItemVoucherDebitNoteBalance);


        $stockItemVoucherSaleAmount =  $stockItemVoucherSaleItem->sum('amount');
        $stockItemVoucherPurchaseAmount =  $stockItemVoucherPurchaseItem->sum('amount');
        $stockItemVoucherCreditNoteAmount =  $stockItemVoucherCreditNoteItem->sum('amount');
        $stockItemVoucherDebitNoteAmount =  $stockItemVoucherDebitNoteItem->sum('amount');

        $stockItemVoucherAmount = $stockItemVoucherSaleAmount + $stockItemVoucherPurchaseAmount + $stockItemVoucherCreditNoteAmount + $stockItemVoucherDebitNoteAmount;

        $openingAmount = ($stockItemVoucherPurchaseAmount + $stockItemVoucherDebitNoteAmount);

        $openingAmountSale = ($stockItemVoucherSaleAmount + $stockItemVoucherCreditNoteAmount);

        $finalOpeningValue = $openingValue - $openingAmount;
        // $finalOpeningValue = $openingValue - $stockItemVoucherPurchaseAmount;
        $finalOpeningBalance = $openingBalance + $stockItemVoucherPurchaseBalance - $stockItemVoucherDebitNoteBalance;

        // dd($finalOpeningValue, $openingAmount, $openingAmountSale, $finalOpeningBalance, $stockItemVoucherBalance);

        if ($openingBalance == 0) {
            $stockItemVoucherSaleValue = $finalOpeningValue / $finalOpeningBalance;
            $stockOnHandBalance = $openingBalance - $stockItemVoucherBalance;
        } else {
            $stockItemVoucherSaleValue = $finalOpeningValue / $finalOpeningBalance;
            $stockItemVoucherSaleValue = number_format($stockItemVoucherSaleValue, 4, '.', '');
            $stockOnHandBalance = $openingBalance - $stockItemVoucherBalance;
        }

        $stockOnHandValue = $stockItemVoucherSaleValue * $stockOnHandBalance;


        // dd($stockOnHandValue, $stockItemVoucherSaleValue, $stockOnHandBalance,);

        $stockItemVoucherSaleHead = [];
        foreach ($stockItemVoucherSaleItem as $stockItemVoucher) {
            $id = $stockItemVoucher->tally_voucher_id;

            $tallyVoucherHeads = TallyVoucherHead::where('tally_voucher_id', $id)->get();
            if ($tallyVoucherHeads->isNotEmpty()) {
                $gstRates = [];

                foreach ($tallyVoucherHeads as $tallyVoucherHead) {
                    // Check if the ledger_name contains specific GST-related keywords
                    if (preg_match('/(SGST|CGST|IGST) @(\d+)%/', $tallyVoucherHead->ledger_name, $matches)) {
                        // Extract the percentage part and convert it to a float
                        $rate = floatval($matches[2]);
                        $gstRates[] = $rate;
                    }
                }

                // Combine and format the GST rates, if any
                if (!empty($gstRates)) {
                    $totalGstRate = array_sum($gstRates);
                    $formattedGstRate = $totalGstRate . '%';
                } else {
                    $formattedGstRate = null;
                }

                $stockItemVoucherSaleHead[] = [
                    'tally_voucher_items' => $stockItemVoucher,
                    'tally_voucher_heads' => $tallyVoucherHeads,
                    'gst_rate' => $formattedGstRate, // Add GST rate to the output
                ];
            }
        }

        $menuItems = TallyItem::whereIn('company_guid', $companyGuids)->get();
        $totalCount = TallyItem::whereIn('company_guid', $companyGuids)->count();

        return view('app.stock-items._stock_item_list', [
            'stockItem' => $stockItem,
            'stockItemId' => $stockItemId ,
            'menuItems' => $menuItems,
            'totalCount' => $totalCount,
            'stockItemVoucherItem' => $stockItemVoucherItem,
            'stockItemVoucherSaleItem' => $stockItemVoucherSaleItem,
            'stockItemVoucherSaleItemConnect' => $stockItemVoucherSaleItemConnect,
            'stockItemVoucherPurchaseItem' => $stockItemVoucherPurchaseItem,
            'stockItemVoucherPurchaseItemConnect' => $stockItemVoucherPurchaseItemConnect,
            'stockOnHandBalance' => $stockOnHandBalance,
            'stockOnHandValue' => $stockOnHandValue,
            'stockItemVoucherSaleHead' =>$stockItemVoucherSaleHead
        ]);
    }


    public function AllSaleStockItemReports($saleStockItemId)
    {
        $companyGuids = $this->reportService->companyData();

        $saleStockItem = TallyItem::whereIn('company_guid', $companyGuids)
                                    ->findOrFail($saleStockItemId);

        $stockItemVoucherItem = TallyVoucherItem::where('stock_item_name', $saleStockItem->name)->get();

        $stockItemVoucherSaleItem = TallyVoucherItem::where('stock_item_name', $saleStockItem->name)
        ->whereHas('tallyVoucher', function ($query) {
            $query->where('voucher_type', 'Sales');
        })
        ->get();

        $menuItems = TallyItem::whereIn('company_guid', $companyGuids)->get();

        return view('app.stock-items._sale_stock_item_list', [
            'saleStockItem' => $saleStockItem,
            'saleStockItemId' => $saleStockItemId ,
            'menuItems' => $menuItems,
        ]);
    }

    // public function getSaleStockItemData($saleStockItemId)
    // {
    //     $saleStockItem = TallyItem::findOrFail($saleStockItemId);
    //     $saleStockItemName = $saleStockItem->name;

    //     $saleStockVoucherItem = TallyVoucherItem::where('stock_item_name', $saleStockItemName)
    //     ->whereHas('tallyVoucher', function ($query) {
    //         $query->where('voucher_type', 'Sales');
    //     })
    //     ->get();


    //     $stockItemVoucherSaleItemConnect = [];
    //     foreach ($saleStockVoucherItem as $saleStockItemVoucher) {
    //         $id = $saleStockItemVoucher->tally_voucher_id;

    //         $tallyVouchers = TallyVoucher::where('id', $id)->get();
    //         if ($tallyVouchers->isNotEmpty()) {
    //             $stockItemVoucherSaleItemConnect[] = [
    //                 'tally_voucher_items' => $saleStockItemVoucher,
    //                 'tally_vouchers' => $tallyVouchers,
    //             ];
    //         }
    //     }

    //     $partyLedgerNames = $saleStockVoucherItem->flatMap(function ($voucherItem) {
    //         return TallyVoucher::where('id', $voucherItem->tally_voucher_id)
    //             ->pluck('party_ledger_name');
    //     })->unique()->toArray();

    //     $partyLedgerNames = array_unique($partyLedgerNames);

    //     $tallyLedgers = TallyLedger::whereIn('language_name', $partyLedgerNames)->get();

    //     // Step 5: Prepare data with party_ledger_name from TallyVoucher
    //     $query = $tallyLedgers->map(function ($ledger) {
    //         // Find the corresponding party_ledger_name from TallyVoucher
    //         $partyLedgerNames = TallyVoucher::where('party_ledger_name', $ledger->language_name)
    //             ->pluck('party_ledger_name');

    //         return [
    //             'language_name' => $ledger->language_name,
    //             'party_ledger_name' => $partyLedgerNames->first(), // Assuming one party_ledger_name per language_name
    //         ];
    //     });

    //     // dd($query);
    //     return DataTables::of($query)
    //         ->addIndexColumn()
    //         ->editColumn('created_at', function ($request) {
    //             return Carbon::parse($request->created_at)->format('Y-m-d H:i:s');
    //         })
    //         ->make(true);
    // }

    public function getSaleStockItemData($saleStockItemId)
    {
        $companyGuids = $this->reportService->companyData();

        $saleStockItem = TallyItem::whereIn('company_guid', $companyGuids)
                                    ->findOrFail($saleStockItemId);

        $saleStockItemName = $saleStockItem->name;

        $saleStockVoucherItems = TallyVoucherItem::where('stock_item_name', $saleStockItemName)
            ->whereHas('tallyVoucher', function ($query) {
                $query->where('voucher_type', 'Sales');
            })
            ->get();

        $partyLedgerNames = $saleStockVoucherItems->flatMap(function ($voucherItem) {
            return TallyVoucher::where('id', $voucherItem->tally_voucher_id)
                ->pluck('party_ledger_name');
        })->unique()->toArray();

        $tallyLedgers = TallyLedger::whereIn('language_name', $partyLedgerNames)->whereIn('company_guid', $companyGuids)->get();

        $ledgerGuids = $tallyLedgers->pluck('guid');

        $query = TallyLedger::select(
                'tally_ledgers.language_name',
                'tally_ledgers.guid',
                'tally_vouchers.voucher_number',
                'tally_voucher_items.amount',
                'tally_voucher_items.stock_item_name',
                'tally_voucher_items.billed_qty',
                'tally_voucher_items.unit',
                'tally_voucher_items.rate'
            )
            ->leftJoin('tally_vouchers', 'tally_ledgers.language_name', '=', 'tally_vouchers.party_ledger_name')
            ->leftJoin('tally_voucher_items', 'tally_vouchers.id', '=', 'tally_voucher_items.tally_voucher_id')
            ->whereIn('tally_ledgers.guid', $ledgerGuids)
            ->where('tally_vouchers.voucher_type', 'Sales')
            ->where('tally_voucher_items.stock_item_name', $saleStockItemName)
            ->whereIn('tally_ledgers.company_guid', $companyGuids)
            ->groupBy('tally_ledgers.language_name', 'tally_ledgers.guid', 'tally_vouchers.voucher_number', 'tally_voucher_items.amount', 'tally_voucher_items.stock_item_name', 'tally_voucher_items.billed_qty', 'tally_voucher_items.unit', 'tally_voucher_items.rate');

        return DataTables::of($query)
            ->addIndexColumn()
            ->make(true);
    }

}