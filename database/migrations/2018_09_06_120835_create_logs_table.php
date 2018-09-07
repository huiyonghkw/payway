<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedTinyInteger('event')->index()->default(0)->comment('日志事件类型');
            $table->unsignedInteger('logger_id')->default(0)->comment('日志多态类型ID');
            $table->string('logger_type')->comment('日志多态类型');
            $table->json('context')->nullable()->comment('请求内容');
            $table->timestamps();
            $table->unique('logger', ['event', 'logger_id', 'logger_type']);
        });
        DB::statement("ALTER TABLE `payment_logs` comment '支付订单日志'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('logs');
    }
}
