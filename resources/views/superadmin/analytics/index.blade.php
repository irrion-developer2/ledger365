@extends("layouts.main")
@section('title', __('Analytics Dashboard | PreciseCA'))
@section("style")
    <link href="assets/plugins/vectormap/jquery-jvectormap-2.0.2.css" rel="stylesheet"/>
@endsection

@section("wrapper")
    <div class="page-wrapper">
            <div class="page-content">

                @include('superadmin.analytics.partials._analyticstop')
              

                @include('superadmin.analytics.partials._analyticsTopCustomerStockPiechart')

                @include('partials.dashboardSaleReceipt')

                {{-- <div class="row">
                   <div class="col-12 col-lg-8 d-flex">
                      <div class="card radius-10 w-100">
                        <div class="card-header">
                            <div class="d-flex align-items-center">
                                <div>
                                    <h6 class="mb-0">Sales Overview</h6>
                                </div>
                                <div class="dropdown ms-auto">
                                    <a class="dropdown-toggle dropdown-toggle-nocaret" href="#" data-bs-toggle="dropdown"><i class='bx bx-dots-horizontal-rounded font-22 text-option'></i>
                                    </a>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="javascript:;">Action</a>
                                        </li>
                                        <li><a class="dropdown-item" href="javascript:;">Another action</a>
                                        </li>
                                        <li>
                                            <hr class="dropdown-divider">
                                        </li>
                                        <li><a class="dropdown-item" href="javascript:;">Something else here</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                          <div class="card-body">
                            <div class="d-flex align-items-center ms-auto font-13 gap-2 mb-3">
                                <span class="border px-1 rounded cursor-pointer"><i class="bx bxs-circle me-1" style="color: #14abef"></i>Sales</span>
                                <span class="border px-1 rounded cursor-pointer"><i class="bx bxs-circle me-1" style="color: #ffc107"></i>Visits</span>
                            </div>
                            <div class="chart-container-1">
                                <canvas id="chart1"></canvas>
                              </div>
                          </div>
                          <div class="row row-cols-1 row-cols-md-3 row-cols-xl-3 g-0 row-group text-center border-top">
                            <div class="col">
                              <div class="p-3">
                                <h5 class="mb-0">24.15M</h5>
                                <small class="mb-0">Overall Visitor <span> <i class="bx bx-up-arrow-alt align-middle"></i> 2.43%</span></small>
                              </div>
                            </div>
                            <div class="col">
                              <div class="p-3">
                                <h5 class="mb-0">12:38</h5>
                                <small class="mb-0">Visitor Duration <span> <i class="bx bx-up-arrow-alt align-middle"></i> 12.65%</span></small>
                              </div>
                            </div>
                            <div class="col">
                              <div class="p-3">
                                <h5 class="mb-0">639.82</h5>
                                <small class="mb-0">Pages/Visit <span> <i class="bx bx-up-arrow-alt align-middle"></i> 5.62%</span></small>
                              </div>
                            </div>
                          </div>
                      </div>
                   </div>
                   <div class="col-12 col-lg-4 d-flex">
                       <div class="card radius-10 w-100">
                        <div class="card-header">
                            <div class="d-flex align-items-center">
                                <div>
                                    <h6 class="mb-0">Trending Products</h6>
                                </div>
                                <div class="dropdown ms-auto">
                                    <a class="dropdown-toggle dropdown-toggle-nocaret" href="#" data-bs-toggle="dropdown"><i class='bx bx-dots-horizontal-rounded font-22 text-option'></i>
                                    </a>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="javascript:;">Action</a>
                                        </li>
                                        <li><a class="dropdown-item" href="javascript:;">Another action</a>
                                        </li>
                                        <li>
                                            <hr class="dropdown-divider">
                                        </li>
                                        <li><a class="dropdown-item" href="javascript:;">Something else here</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                           <div class="card-body">
                            <div class="chart-container-2">
                                <canvas id="chart2"></canvas>
                              </div>
                           </div>
                           <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex bg-transparent justify-content-between align-items-center border-top">Jeans <span class="badge bg-success rounded-pill">25</span>
                            </li>
                            <li class="list-group-item d-flex bg-transparent justify-content-between align-items-center">T-Shirts <span class="badge bg-danger rounded-pill">10</span>
                            </li>
                            <li class="list-group-item d-flex bg-transparent justify-content-between align-items-center">Shoes <span class="badge bg-primary rounded-pill">65</span>
                            </li>
                            <li class="list-group-item d-flex bg-transparent justify-content-between align-items-center">Lingerie <span class="badge bg-warning text-dark rounded-pill">14</span>
                            </li>
                        </ul>
                       </div>
                   </div>
                </div><!--end row--> --}}

                
                @include('superadmin.analytics.partials._closingStock')


            </div>
    </div>
@endsection

@section("script")
    <script src="assets/plugins/vectormap/jquery-jvectormap-2.0.2.min.js"></script>
    <script src="assets/plugins/vectormap/jquery-jvectormap-world-mill-en.js"></script>
    <script src="assets/plugins/chartjs/js/chart.js"></script>
    <script src="assets/js/index.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var ctx = document.getElementById('closingStockChart').getContext('2d');
            
            var closingStockData = @json($closingStockData); // Pass PHP data to JavaScript
            var labels = Object.keys(closingStockData); // Extract month labels
            var data = Object.values(closingStockData); // Extract data values
    
            new Chart(ctx, {
                type: 'line', // Use line type for wave chart
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Closing Stock Amount',
                        data: data,
                        borderColor: '#42A5F5',
                        backgroundColor: 'rgba(66, 165, 245, 0.2)',
                        fill: true,
                        tension: 0.4 // Make it more wavy
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Month'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Amount'
                            },
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    </script>

    <script>
        // Prepare data for the chart
        const salesData = @json($customerCategory);
        
        // Extract labels and data for the pie chart
        const labels = salesData.map(data => data.gst_registration_type);
        const data = salesData.map(data => data.total_sales);
        
        // Render the pie chart using Chart.js
        const ctx = document.getElementById('chartCustomerCategory').getContext('2d');
        const chartCustomerCategory = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: ['#007bff', '#dc3545', '#ffc107', '#28a745', '#17a2b8'], // Define colors for each segment
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            generateLabels: function(chart) {
                                const dataset = chart.data.datasets[0];
                                return chart.data.labels.map((label, i) => {
                                    return {
                                        text: `${label}: ${new Intl.NumberFormat().format(dataset.data[i])}`, // Display category with value in legend
                                        fillStyle: dataset.backgroundColor[i],
                                        hidden: isNaN(dataset.data[i]) || chart.getDatasetMeta(0).data[i].hidden,
                                        index: i
                                    };
                                });
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += new Intl.NumberFormat().format(context.raw); // Display value in the tooltip
                                return label;
                            }
                        }
                    }
                }
            }
        });
    </script>


    {{-- <script>
        // Prepare data for the chart
        const salesData = @json($customerCategory);
        
        // Extract labels and data for the pie chart
        const labels = salesData.map(data => data.gst_registration_type);
        const data = salesData.map(data => data.total_sales);
        
        // Render the pie chart using Chart.js
        const ctx = document.getElementById('chartCustomerCategory').getContext('2d');
        const chartCustomerCategory = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: ['#007bff', '#dc3545', '#ffc107', '#28a745', '#17a2b8'], // Define colors for each segment
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += new Intl.NumberFormat().format(context.raw);
                                return label;
                            }
                        }
                    }
                }
            }
        });
    </script> --}}


    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var ctx = document.getElementById('salereceiptchart').getContext('2d');
    
            var chartData = @json($chartData);

            function preprocessData(data) {
                return Object.values(data).map(value => {
                    return parseFloat(Math.abs(value)).toFixed(2);
                });
            }
    
            var salesData = preprocessData(chartData.sales);
            var receiptsData = preprocessData(chartData.receipts);
    
            var chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: Object.keys(chartData.sales),
                    datasets: [
                        {
                            label: 'Sales',
                            data: salesData,
                            backgroundColor: '#14abef', // Updated color
                            borderColor: '#14abef', // Updated border color
                            borderWidth: 2, // Updated border width
                            barPercentage: 0.5, // Thinner bars
                            borderRadius: 10 // Rounded corners
                        },
                        {
                            label: 'Receipts',
                            data: receiptsData,
                            backgroundColor: '#ffc107', // Updated color
                            borderColor: '#ffc107', // Updated border color
                            borderWidth: 2, // Updated border width
                            barPercentage: 0.5, // Thinner bars
                            borderRadius: 10 // Rounded corners
                        }
                    ]
                },
                options: {
                    responsive: true, // Make chart responsive
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: '#333'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(tooltipItem) {
                                    // Customize tooltip to display rounded values correctly
                                    return tooltipItem.dataset.label + ': ' + parseFloat(Math.abs(tooltipItem.raw)).toFixed(2);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Months' // Label for x-axis
                            },
                            ticks: {
                                color: '#555' // Color of x-axis ticks
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Amt' // Label for y-axis
                            },
                            ticks: {
                                color: '#555', // Color of y-axis ticks
                                callback: function(value) {
                                    // Display rounded values to two decimal places on the y-axis
                                    return parseFloat(Math.abs(value)).toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>

    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sample data (Replace this with @json($pieChartDataOverall) in a real scenario)
            var pieChartData = @json($pieChartDataOverall);

            // Prepare data for Chart.js
            var labels = Object.keys(pieChartData);
            var data = Object.values(pieChartData).map(function(value) {
                return Math.ceil(Math.abs(value)); // Remove negative signs and round up values
            });

            // Combine labels and data into an array of objects
            var combinedData = labels.map(function(label, index) {
                return { label: label, value: data[index] };
            });

            // Sort the array by value in descending order
            combinedData.sort(function(a, b) {
                return b.value - a.value;
            });

            // Limit to the top 5 highest amounts
            var topData = combinedData.slice(0, 5);

            // Extract labels and data after sorting
            labels = topData.map(function(item) {
                return item.label;
            });
            data = topData.map(function(item) {
                return item.value;
            });

            // Define a set of colors
            var colors = ['#14abef', '#ffc107', '#b02a37', '#4bc0c0', '#ff9f40', '#36a2eb', '#ff6384', '#cc65fe', '#ffce56', '#fd6b19'];

            // Generate background colors dynamically
            var backgroundColors = colors.slice(0, labels.length);

            // Create the doughnut chart
            var ctx = document.getElementById('pieChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',  // Changed to doughnut chart
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: backgroundColors,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false // Hide legend if badges are used instead
                        }
                    }
                }
            });

            // Generate badges dynamically for the top 5 highest amounts
            var badgeList = document.getElementById('badge-list');
            badgeList.innerHTML = '';  // Clear existing badges

            labels.forEach((label, index) => {
                var listItem = document.createElement('li');
                listItem.className = 'list-group-item d-flex bg-transparent justify-content-between align-items-center';

                var badge = document.createElement('span');
                badge.className = 'badge rounded-pill';
                badge.style.backgroundColor = backgroundColors[index];
                badge.style.color = '#fff'; // Ensure text is readable on colored backgrounds
                badge.textContent = `₹ ${data[index]}`; // Display the amount with Rupees sign

                listItem.appendChild(document.createTextNode(label)); // Add label text
                listItem.appendChild(badge); // Add badge

                badgeList.appendChild(listItem);
            });
        });
    </script>

@endsection