@extends("layouts.main")
@section('title', __('Stock Items | PreciseCA'))

@section("style")
    <link href="assets/plugins/vectormap/jquery-jvectormap-2.0.2.css" rel="stylesheet"/>
@endsection

@section("wrapper")
    <div class="page-wrapper">
        <div class="page-content pt-2">
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-2">
                <div class="breadcrumb-title pe-3">Stock Items</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Stock Items</li>
                        </ol>
                    </nav>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="d-lg-flex align-items-center gap-3"></div>

                    <div class="table-responsive table-responsive-scroll border-0">

                        <table id="stockItem-datatable" class="stripe row-border order-column" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Alias</th>
                                    <th>Stock Group</th>
                                    <th>Stock Category</th>
                                    <th>
                                        Stock
                                        <br>
                                        <span style="font-size: smaller;color: gray;">Qty</span>
                                    </th>
                                    <th>
                                        Stock
                                        <br>
                                        <span style="font-size: smaller;color: gray;">Value</span>
                                    </th>
                                    {{-- <th>
                                        Stock On Hand
                                        <br>
                                        <span style="font-size: smaller;color: gray;">Qty</span>
                                    </th>
                                    <th>
                                        Stock On Hand
                                        <br>
                                        <span style="font-size: smaller;color: gray;">Value</span>
                                    </th> --}}
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Data will be populated by AJAX --}}
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>Total</th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    {{-- <th></th>
                                    <th></th> --}}
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
        new DataTable('#stockItem-datatable', {
            fixedColumns: {
                start: 1,
            },
            processing: true,
            serverSide: true,
            paging: false,
            scrollCollapse: true,
            scrollX: true,
            scrollY: 300,
            ajax: {
                url: "{{ route('StockItem.get-data') }}",
                type: 'GET',
            },
            columns: [
                // {data: 'id', name: 'id'},
                {data: 'item_name', name: 'item_name',
                    {{--  render: function(data, type, row) {
                        var url = '{{ route("StockItem.items", ":id") }}';
                        url = url.replace(':id', row.id);
                        return '<a href="' + url + '" style="color: #337ab7;">' + data + '</a>';
                    }  --}}
                },
                {data: 'alias1', name: 'alias1', render: function(data, type, row) {
                    return data ? data : '-';
                }},
                {data: 'parent', name: 'parent', render: function(data, type, row) {
                    return data ? data : '-';
                }},
                {data: 'category', name: 'category', render: function(data, type, row) {
                    return data ? data : '-';
                }},
                {data: 'opening_balance', name: 'opening_balance', className: 'text-end', render: function(data, type, row) {
                    return data ? data : '-';
                }},
                {data: 'opening_value', name: 'opening_value', className: 'text-end', render: function(data, type, row) {
                    return data ? data : '-';
                }},
                // {data: 'stockonhand_opening_balance', name: 'stockonhand_opening_balance', render: function(data, type, row) {
                //     return data ? data : '-';
                // }},
                // {data: 'stockonhand_opening_value', name: 'stockonhand_opening_value', render: function(data, type, row) {
                //     return data ? data : '-';
                // }},
            ],
            {{--  footerCallback: function (row, data, start, end, display) {
                var api = this.api();
                var StockHandBalanceToTotal = 5;
                var StockHandValueToTotal = 7;

                var StockHandBalancetotal = api.column(StockHandBalanceToTotal).data().reduce(function (a, b) {
                    return (parseFloat(sanitizeNumber(a)) || 0) + (parseFloat(sanitizeNumber(b)) || 0);
                }, 0);
                var StockHandValuetotal = api.column(StockHandValueToTotal).data().reduce(function (a, b) {
                    return (parseFloat(sanitizeNumber(a)) || 0) + (parseFloat(sanitizeNumber(b)) || 0);
                }, 0);

                $(api.column(StockHandBalanceToTotal).footer()).html(number_format(Math.abs(StockHandBalancetotal), 2));
                $(api.column(StockHandValueToTotal).footer()).html(number_format(Math.abs(StockHandValuetotal), 2));
            },  --}}
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
