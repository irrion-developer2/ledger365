@extends("layouts.main")
@section('title', __('Ledger Group | PreciseCA'))

@section("style")
    <link href="https://unpkg.com/vue2-datepicker@3.10.2/index.css" rel="stylesheet">
    <style>
        .text-wrap {
            white-space: normal !important; /* Allow text wrapping */
            word-wrap: break-word; /* Break long words */
            max-width: 200px; /* Set a reasonable max width */
        }
    </style>

@endsection

@section("wrapper")
    <div class="page-wrapper">
        <div class="page-content pt-2">
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-2">
                <div class="breadcrumb-title pe-3">Report</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">All Ledger and Group</li>
                        </ol>
                    </nav>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <!-- Vue App for Date Picker -->
                    <div id="vue-datepicker-app">
                        <div class="d-lg-flex align-items-center gap-2">
                            <div class="col-lg-3">
                                <form id="dateRangeForm">
                                    <div class="input-group">
                                        <date-picker
                                            v-model="dateRange"
                                            :range="true"
                                            format="YYYY-MM-DD"
                                            :number-of-months="2"
                                            placeholder="Select Date Range"
                                            :time-picker="false"
                                            value-type="format">
                                        </date-picker>
                                    </div>
                                </form>
                            </div>

                            <div class="col-lg-2">
                                <form id="customDateForm">
                                    <select id="custom_date_range" name="custom_date_range" class="form-select" @change="updateCustomRange">
                                        <template v-for="group in customDateRangeOptions">
                                            <optgroup :label="group.label">
                                                <option
                                                    v-for="option in group.options"
                                                    :key="option.value"
                                                    :value="option.value"
                                                    :selected="option.value === customDateRange">
                                                    @{{ option.text }}
                                                </option>
                                            </optgroup>
                                        </template>
                                    </select>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive table-responsive-scroll border-0">
                        <table id="ledger-group-datatable" class="stripe row-border order-column" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Level</th>
                                    <th>Ledger Group</th>
                                    <th>Type</th>
                                    {{--  <th>Id</th>  --}}
                                    <th>Ledger Name</th>
                                    <th>Opening Balance</th>
                                    <th>Total Debit</th>
                                    <th>Total Credit</th>
                                    <th>Closing Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Data will be populated by AJAX --}}
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    {{--  <th></th>  --}}
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

<script src="https://cdn.jsdelivr.net/npm/vue@2.6.14/dist/vue.js"></script>
<script src="https://unpkg.com/vue2-datepicker@3.10.2/index.min.js"></script>
<script src="{{ url('assets/js/NumberFormatter.js') }}"></script>

<script>
    Vue.component('date-picker', window.DatePicker.default || window.DatePicker);

    new Vue({
        el: '#vue-datepicker-app',
        data: {
            dateRange: [],
            customDateRange: "{{ request('custom_date_range') }}",
            tableInitialized: false,
            customDateRangeOptions: [
                {
                    label: "General",
                    options: [
                        { text: "All", value: "all" }
                    ]
                },
                {
                    label: "Monthly",
                    options: [
                        { text: "This Month", value: "this_month" },
                        { text: "Last Month", value: "last_month" }
                    ]
                },
                {
                    label: "Quarterly",
                    options: [
                        { text: "This Quarter", value: "this_quarter" },
                        { text: "Prev Quarter", value: "prev_quarter" }
                    ]
                },
                {
                    label: "Yearly",
                    options: [
                        { text: "This Year", value: "this_year" },
                        { text: "Prev Year", value: "prev_year" }
                    ]
                }
            ]
        },
        methods: {
            resetDateRange() {
                this.dateRange = [];
                this.updateURL();
                this.reloadTableData();
            },
            updateCustomRange(event) {
                this.customDateRange = event.target.value;
                this.updateURL();
                this.reloadTableData();
            },
            updateURL() {
                const url = new URL(window.location.href);
                if (this.dateRange.length === 2) {
                    url.searchParams.set('start_date', this.dateRange[0]);
                    url.searchParams.set('end_date', this.dateRange[1]);
                } else {
                    url.searchParams.delete('start_date');
                    url.searchParams.delete('end_date');
                }
                url.searchParams.set('custom_date_range', this.customDateRange);
                window.history.pushState({}, '', url.toString());
            },
            reloadTableData() {
                if (this.tableInitialized) {
                    $('#ledger-group-datatable').DataTable().ajax.reload(null, false);
                }
            }
        },
        watch: {
            dateRange(newRange) {
                if (newRange.length === 2) {
                    this.updateURL();
                    this.reloadTableData();
                }
            }
        },
        mounted() {
            const urlParams = new URLSearchParams(window.location.search);
            const startDate = urlParams.get('start_date');
            const endDate = urlParams.get('end_date');
            if (startDate && endDate) {
                this.dateRange = [startDate, endDate];
            }
            $('#ledger-group-datatable').on('init.dt', () => {
                this.tableInitialized = true;
            });
        }
    });


    $(document).ready(function() {
        const dataTable = $('#ledger-group-datatable').DataTable({
            fixedColumns: { start: 1 },
            processing: true,
            serverSide: true,
            paging: false,
            order: [[1, 'asc']],
            scrollCollapse: true,
            scrollX: true,
            scrollY: 300,
            ajax: {
                url: "{{ route('LedgerGroup.get-data') }}",
                type: 'GET',
                data: function (d) {
                    const vueInstance = document.getElementById('vue-datepicker-app').__vue__;
                    if (vueInstance.dateRange.length === 2) {
                        d.start_date = vueInstance.dateRange[0];
                        d.end_date = vueInstance.dateRange[1];
                    }
                    d.custom_date_range = vueInstance.customDateRange || "all";
                }
            },
            columns: [
                {data: 'level', name: 'level', render: data => data || '-'},
                {
                    data: 'hierarchy',
                    name: 'hierarchy',
                    className: 'text-wrap', // Custom class for wrapping text
                    render: data => data || '-' // Render function remains the same
                },
                {data: 'type', name: 'type', render: data => data || '-'},    
                {{--  {data: 'id', name: 'id', render: data => data || '-'},      --}}
                {data: 'name', name: 'name', render: data => data || '-'},                
                {data: 'opening_balance', name: 'opening_balance', className: 'text-end', render: data => data || '-'},
                {data: 'total_debit', name: 'total_debit', className: 'text-end', render: data => data || '-'},
                {data: 'total_credit', name: 'total_credit', className: 'text-end', render: data => data || '-'},
                {data: 'closing_balance', name: 'closing_balance', className: 'text-end', render: data => data || '-'},
            ],
            search: {
                orthogonal: { search: 'plain' }
            }
        });

        function sanitizeNumber(value) {
            return value ? value.toString().replace(/[^0-9.-]+/g, "") : "0";
        }
    });
</script>
@endsection
