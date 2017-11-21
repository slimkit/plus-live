<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateUserExtrasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_extras', function (Blueprint $table) {
            $table->integer('live_zans_count')->default(0)->comment('获取的赞的总数');
            $table->integer('live_zans_remain')->default(0)->comment('剩余赞的数量');
            $table->integer('live_time')->default(0)->comment('直播时长');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_extras', function (Blueprint $table) {
            $table->dropColumn(['live_zans_count', 'live_zans_remain', 'live_time']);
        });
    }
}
