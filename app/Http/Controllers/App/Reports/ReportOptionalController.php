<?php

namespace App\Http\Controllers\App\Reports;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Services\ReportService;
use App\Models\TallyVoucher;
use App\Models\TallyVoucherHead;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; 

class ReportOptionalController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function index()
    {
        return view ('app.reports.optional.index');
    }

    public function getData(Request $request)
    {
        $companyIds = $this->reportService->companyData();

        if (empty($companyIds)) {
            return DataTables::of([])->make(true);
        }

        if ($request->ajax()) {
            $startTime = microtime(true);

            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $customDateRange = $request->get('custom_date_range');
            $isCancelled = $request->get('is_cancelled', 0);
            $isOptional = $request->get('is_optional', 1);
            $voucherTypeName = $request->get('voucher_type_name');

            $voucherTypeName = ($voucherTypeName && strtolower($voucherTypeName) !== 'null' && trim($voucherTypeName) !== '') ? $voucherTypeName : null;
            $startDate = ($startDate && strtolower($startDate) !== 'null') ? $startDate : null;
            $endDate = ($endDate && strtolower($endDate) !== 'null') ? $endDate : null;

            if ($customDateRange) {
                switch ($customDateRange) {
                    case 'this_month':
                        $startDate = now()->startOfMonth()->toDateString();
                        $endDate = now()->endOfMonth()->toDateString();
                        break;
                    case 'last_month':
                        $startDate = now()->subMonth()->startOfMonth()->toDateString();
                        $endDate = now()->subMonth()->endOfMonth()->toDateString();
                        break;
                    case 'this_quarter':
                        $startDate = now()->firstOfQuarter()->toDateString();
                        $endDate = now()->lastOfQuarter()->toDateString();
                        break;
                    case 'prev_quarter':
                        $startDate = now()->subQuarter()->firstOfQuarter()->toDateString();
                        $endDate = now()->subQuarter()->lastOfQuarter()->toDateString();
                        break;
                    case 'this_year':
                        $startDate = now()->startOfYear()->toDateString();
                        $endDate = now()->endOfYear()->toDateString();
                        break;
                    case 'prev_year':
                        $startDate = now()->subYear()->startOfYear()->toDateString();
                        $endDate = now()->subYear()->endOfYear()->toDateString();
                        break;
                    case 'all':
                        break;
                }
            }

            $companyIdsList = implode(',', $companyIds);

            $sql = "CALL get_daybook_data(?, ?, ?, ?, ?, ?)";

            Log::info("Calling Stored Procedure get_daybook_data", [
                'sql' => $sql,
                'params' => [
                    'company_ids' => $companyIdsList,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'is_cancelled' => $isCancelled,
                    'is_optional' => $isOptional,
                    'voucher_type_name' => $voucherTypeName,
                ]
            ]);

            try {
                $dayBook = DB::select($sql, [
                    $companyIdsList,    
                    $startDate,         
                    $endDate,           
                    $isCancelled,       
                    $isOptional,       
                    $voucherTypeName,   
                ]);
            } catch (\Exception $e) {
                Log::error('Error executing stored procedure get_daybook_data:', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json(['error' => 'Failed to retrieve data.'], 500);
            }

            $endTime1 = microtime(true);
            $executionTime1 = $endTime1 - $startTime;
            Log::info('Total first DB request execution time for ReportOptionalController.getData:', [
                'time_taken' => $executionTime1 . ' seconds'
            ]);

            $dataTable = DataTables::of($dayBook)
                ->addIndexColumn()
                ->addColumn('credit', function ($data) {
                    return indian_format(abs($data->total_credit));
                })
                ->addColumn('debit', function ($data) {
                    return indian_format(abs($data->total_debit));
                })
                ->make(true);

            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            Log::info('Total end execution time for ReportOptionalController.getData:', [
                'time_taken' => $executionTime . ' seconds'
            ]);

            return $dataTable;
        }

        return response()->json(['message' => 'Invalid request.'], 400);
    }
}
