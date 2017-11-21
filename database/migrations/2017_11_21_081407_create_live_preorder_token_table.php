<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLivePreorderTokenTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('live_preorder_token', function (Blueprint $table) {
            $table->increments('id');
            $table->string('token');
            $table->integer('uid');
            $table->integer('to_uid');
            $table->tinyInteger('disabled');
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
        Schema::dropIfExists('live_preorder_token');
    }
}
