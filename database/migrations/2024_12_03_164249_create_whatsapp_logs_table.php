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
        Schema::create('whatsapp_logs', function (Blueprint $table) {
            $table->increments('whatsapp_id');  
            $table->integer('company_id')->nullable();
            $table->integer('ledger_id')->nullable();
            $table->string('phone_number',50)->nullable();
            $table->text('message')->nullable();  
            $table->string('pdf_path',200)->nullable();  
            $table->text('json_response')->nullable();   
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('whatsapp_logs');
    }
};
