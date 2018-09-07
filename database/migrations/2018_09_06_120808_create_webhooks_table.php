<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateWebhooksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('client_id')->default(0)->comment('客户端ID');
            $table->unsignedInteger('payment_channel_id')->default(0)->comment('支付渠道ID');
            $table->string('trade_no', 64)->nullable()->comment('交易号');
            $table->unsignedInteger('payment_order_id')->default(0)->comment('支付订单ID');
            $table->string('out_trade_no', 64)->nullable()->comment('商户交易号');
            $table->string('channel_trade_no')->nullable()->comment('渠道交易号');
            $table->string('url')->nullable()->comment('通知URL');
            $table->text('context')->nullable()->comment('通知内容');
            $table->text('channel_context')->nullable()->comment('渠道通知内容');
            $table->string('response')->nullable()->comment('响应内容');
            $table->unsignedTinyInteger('status')->index()->default(0)->comment('通知结果\n0 待通知\n1 通知成功\n2 通知失败');
            $table->timestamps();
        });
        DB::statement("ALTER TABLE `payment_webhooks` comment '业务系统异步通知日志'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('webhooks');
    }
}