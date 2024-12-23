<div class="row row-cols-1 row-cols-md-2 row-cols-xl-5">
    <div class="col">
      <div class="card radius-10 border-start border-0 border-4 border-info">
         <div class="card-body">
             <div class="d-flex align-items-center">
                 <div>
                     <p class="mb-0 text-secondary">Sales</p>
                     <h4 class="my-1 text-info">&#8377 {{ indian_format(abs($chartSaleAmt)) }}</h4>
                 </div>
                 <div class="widgets-icons-2 rounded-circle bg-gradient-blues text-white ms-auto"><i class='bx bxs-cart'></i>
                 </div>
             </div>
         </div>
      </div>
    </div>
    <div class="col">
     <div class="card radius-10 border-start border-0 border-4 border-danger">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div>
                    <p class="mb-0 text-secondary">No. Of Customers</p>
                    <h4 class="my-1 text-danger">{{ ($number_of_customers) }}</h4>
                </div>
                <div class="widgets-icons-2 rounded-circle bg-gradient-burning text-white ms-auto"><i class='bx bxs-group'></i>
                </div>
            </div>
        </div>
     </div>
   </div>
   <div class="col">
     <div class="card radius-10 border-start border-0 border-4 border-success">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div>
                    <p class="mb-0 text-secondary">Avg Sales</p>
                    <h4 class="my-1 text-success">&#8377 {{ indian_format(abs($avg_sales)) }}</h4>
                </div>
                <div class="widgets-icons-2 rounded-circle bg-gradient-ohhappiness text-white ms-auto"><i class='bx bxs-bar-chart-alt-2' ></i>
                </div>
            </div>
        </div>
     </div>
   </div>
   <div class="col">
     <div class="card radius-10 border-start border-0 border-4 border-warning">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div>
                    <p class="mb-0 text-secondary">Budget sales</p>
                    <h4 class="my-1 text-warning">-</h4>
                </div>
                <div class="widgets-icons-2 rounded-circle bg-gradient-orange text-white ms-auto"><i class='bx bxs-group'></i>
                </div>
            </div>
        </div>
     </div>
   </div>
   <div class="col">
     <div class="card radius-10 border-start border-0 border-4 border-dark">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div>
                    <p class="mb-0 text-secondary">Stock Value</p>
                    <h4 class="my-1 text-warning">{{ ($stock_value) }}</h4>
                </div>
                <div class="widgets-icons-2 rounded-circle bg-gradient-moonlit text-white ms-auto"><i class='bx bxs-group'></i>
                </div>
            </div>
        </div>
     </div>
   </div>
 </div><!--end row-->
