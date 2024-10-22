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
        Schema::create('tally_ledger_groups', function (Blueprint $table) {
            $table->bigIncrements('ledger_group_id');
            
            $table->string('guid', 100)->unique();
            
            $table->unsignedBigInteger('company_id')->nullable();
            $table->foreign('company_id')->references('company_id')->on('tally_companies')->onDelete('cascade');
            
            $table->integer('alter_id');
            $table->string('ledger_group_name', 100)->nullable()->index();
            $table->string('parent', 100)->nullable();
            $table->boolean('affects_stock')->default(false);
            $table->string('primary_group', 100)->nullable();
            
            $table->unique(['company_id', 'ledger_group_name']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tally_ledger_groups');
    }
};
