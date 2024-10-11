<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tally_voucher_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tally_voucher_id')->nullable();
            $table->foreign('tally_voucher_id')->references('id')->on('tally_vouchers')->onDelete('cascade');
            
            $table->unsignedBigInteger('voucher_head_id')->nullable();
            $table->foreign('voucher_head_id')->references('id')->on('tally_voucher_heads')->onDelete('cascade');
       
            $table->string('company_guid')->nullable();
            $table->foreign('company_guid')->references('guid')->on('tally_companies')->onDelete('cascade');

            $table->string('head_ledger_guid')->nullable();
           
            $table->string('stock_item_name')->nullable();
            $table->string('gst_taxability')->nullable();
            $table->string('gst_source_type')->nullable();
            $table->string('gst_item_source')->nullable();
            $table->string('gst_ledger_source')->nullable();
            $table->string('hsn_source_type')->nullable();
            $table->string('hsn_item_source')->nullable();
            $table->string('gst_rate_infer_applicability')->nullable();
            $table->string('gst_hsn_infer_applicability')->nullable();

            $table->decimal('rate', 15, 2)->nullable(); 
            $table->string('unit')->nullable();
            $table->decimal('billed_qty', 15, 2)->nullable(); 
            $table->decimal('amount', 15, 2)->nullable(); 
            $table->decimal('discount', 15, 2)->nullable();
            $table->decimal('igst_rate', 15, 2)->nullable();
            $table->string('gst_hsn_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tally_voucher_items');
    }
};
