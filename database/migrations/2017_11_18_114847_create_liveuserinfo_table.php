<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLiveuserinfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('live_user_infos', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('uid')->unique();
            $table->string('usid', 100)->unique()->comment('usid,不能修改');
            $table->string('uname', 100)->unique()->comment('昵称');
            $table->tinyInteger('sex')->comment('性别');
            $table->string('ticket', 171)->unique()->comment('票据');
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
        Schema::dropIfExists('live_user_infos');
    }
}
