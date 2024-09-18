@extends("layouts.main")
@section('title', __('Reports | PreciseCA'))

@section("style")
    <link href="assets/plugins/vectormap/jquery-jvectormap-2.0.2.css" rel="stylesheet"/>
@endsection

@section("wrapper")
    <div class="page-wrapper">
        <div class="page-content pt-2">
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-2">
                <div class="breadcrumb-title pe-3">Reports</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Sales by Items</li>
                        </ol>
                    </nav>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="d-lg-flex align-items-center gap-2">
                    </div>

                    <div class="table-responsive table-responsive-scroll border-0">
                        
                        <table id="itemGroup-datatable" class="stripe row-border order-column" style="width:100%">
                            <thead>
                                <tr>
                                    {{-- <th>Id</th> --}}
                                    <th>Name</th>
                                    <th>Parent</th>
                                    <th>₹ Total Sales</th>
                                    <th>Qty Sold</th>
                                    <th>
                                        Customer
                                        <br>
                                        <span style="font-size: smaller;color: gray;">Count</span>
                                    </th>
                                    <th>
                                        ₹ Avg Sales
                                        <br>
                                        <span style="font-size: smaller;color: gray;">Price</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Data will be populated by AJAX --}}
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>Name</th>
                                    <th>Parent</th>
                                    <th>₹ Total Sales</th>
                                    <th>Qty Sold</th>
                                    <th>
                                        Customer
                                        <br>
                                        <span style="font-size: smaller;color: gray;">Count</span>
                                    </th>
                                    <th>
                                        ₹ Avg Sales
                                        <br>
                                        <span style="font-size: smaller;color: gray;">Price</span>
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section("script")
@include('layouts.includes.datatable-js-css')
<script>
    $(document).ready(function() {

        new DataTable('#itemGroup-datatable', {
            fixedColumns: {
                start: 1,
            },
            paging: false,
            scrollCollapse: true,
            scrollX: true,
            scrollY: 300,
            ajax: {
                url: "{{ route('reports.ItemGroup.get-data') }}",
                type: 'GET',
                data: function (d) {
                }
            },
            columns: [
                // {data: 'id', name: 'id'},
                {data: 'name', name: 'name',
                    render: function(data, type, row) {
                        var url = '{{ route("reports.ItemGroupLedger", ":id") }}';
                        url = url.replace(':id', row.id);
                        return '<a href="' + url + '" style="color: #337ab7;">' + data + '</a>';
                    }
                },
                {data: 'parent', name: 'parent'},
                {data: 'total_sales', name: 'total_sales'},
                {data: 'qty_sold', name: 'qty_sold', className: 'text-end', render: function(data, type, row) {
                    return data ? data : '-';
                }},
                {data: 'customer_count', name: 'customer_count', className: 'text-end', render: function(data, type, row) {
                    return data ? data : '-';
                }},
                {data: 'avg_sales', name: 'avg_sales', className: 'text-end', render: function(data, type, row) {
                    return data ? data : '-';
                }},
            ],
            footerCallback: function (row, data, start, end, display) {
                var api = this.api();
                var SaleToTotal = 2;
                var QtySoldToTotal = 3;
                var CustomerCountToTotal = 4;
                var AvgSaleToTotal = 5;


                var Saletotal = api.column(SaleToTotal).data().reduce(function (a, b) {
                    return (parseFloat(sanitizeNumber(a)) || 0) + (parseFloat(sanitizeNumber(b)) || 0);
                }, 0);

                var QtySoldtotal = api.column(QtySoldToTotal).data().reduce(function (a, b) {
                    return (parseFloat(sanitizeNumber(a)) || 0) + (parseFloat(sanitizeNumber(b)) || 0);
                }, 0);

                var CustomerCounttotal = api.column(CustomerCountToTotal).data().reduce(function (a, b) {
                    return (parseFloat(sanitizeNumber(a)) || 0) + (parseFloat(sanitizeNumber(b)) || 0);
                }, 0);

                var AvgSaletotal = api.column(AvgSaleToTotal).data().reduce(function (a, b) {
                    return (parseFloat(sanitizeNumber(a)) || 0) + (parseFloat(sanitizeNumber(b)) || 0);
                }, 0);


                $(api.column(SaleToTotal).footer()).html(number_format(Math.abs(Saletotal), 2));
                $(api.column(QtySoldToTotal).footer()).html(number_format(Math.abs(QtySoldtotal), 2));
                $(api.column(CustomerCountToTotal).footer()).html(number_format(Math.abs(CustomerCounttotal), 2));
                $(api.column(AvgSaleToTotal).footer()).html(number_format(Math.abs(AvgSaletotal), 2));
            },
            search: {
                orthogonal: {
                    search: 'plain' 
                }
            }
        });

        function sanitizeNumber(value) {
            return value ? value.toString().replace(/[^0-9.-]+/g, "") : "0";
        }

        function number_format(number, decimals) {
            if (isNaN(number)) return 0;
            number = parseFloat(number).toFixed(decimals);
            var parts = number.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            return parts.join('.');
        }
    });
</script>
@endsection