<?php

namespace App\Http\Controllers\SuperAdmin\Reports;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; 
use App\DataTables\SuperAdmin\ReceiptRegisterDataTable;

class ReportReceiptRegisterController extends Controller
{

    public function index(ReceiptRegisterDataTable $dataTable)
    {
        return $dataTable->render('superadmin.reports.receiptRegister.index');
    }

}