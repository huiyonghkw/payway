<?php

namespace App\Http\Controllers\Api\Inner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OrderRequest;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use App\Models\Channel;
use App\Models\ChannelPayWay;
use Carbon\Carbon;
use App\Events\InternalRequestOrder;
use App\Events\InternalRequestRefund;
use App\Events\ExternalRequestOrder;
use App\Events\ExternalRequestRefund;
use Illuminate\Support\Facades\Event;
use App\Services\WechatMwebService;
use App\Services\WechatMiniService;
use App\Services\WechatService;
use App\Http\Resources\OrderResource;
use App\Models\Refund;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    /**
     * Create Order
     * 
     * @param OrderRequest $request
     * @return OrderResource
     */
    public function store(OrderRequest $request)
    {
        $params = $response = [];
        //接收创建订单参数
        //验证签名
        DB::beginTransaction();
        try {
            $channel = Channel::where('client_id', Auth::user()->id)
                ->where('channel', $request->channel)
                ->first();
            $payWay = ChannelPayWay::where('payment_channel_id', $channel->id)
                                    ->where('way', $request->pay_way)
                                    ->first();
            $order = Order::where('out_trade_no', $request->out_trade_no)
                            ->whereNotIn('status', [Order::PAY_STATUS_CLOSED, Order::PAY_STATUS_CANCELED])
                            ->where('payment_channel_id', $channel->id)
                            ->where('payment_channel_pay_way_id', $payWay->id)
                            ->orderBy('created_at', 'desc')
                            ->first();
            if ($order) {
                //订单是否支的付成功
                if ($order->status == Order::PAY_STATUS_SUCCESS) {
                    abort(403, '订单已经支付成功');
                }
                //订单是否过期
                if (Carbon::now()->gte($order->expired_at)) {
                    $order = $this->createOrder($request, $channel, $payWay);
                } else {
                    //返回预付单信息
                    return new OrderResource($order);
                }
            } else {
                //生成新订单
                $order = $this->createOrder($request, $channel, $payWay);
            }
            //创建生成新订单请求日志
            Event::fire(new InternalRequestOrder($request, $order));
            //创建渠道订单请求
            switch ($request->pay_way) {
                case Order::CHANNEL_PAY_WAY_WECHAT_MWEB:
                    $payment = new WechatMwebService($order, $channel, $payWay);
                    $response = $payment->pay($params);
                    break;
                case Order::CHANNEL_PAY_WAY_WECHAT_MINI:
                    $payment = new WechatMiniService($order, $channel, $payWay);
                    $response = $payment->pay($params);
                    break;
            }
            //创建生成小程序支付请求日志
            Event::fire(new ExternalRequestOrder($order, $params, $response));
            //更新订单状态
            $order->update([
                'status' => Order::PAY_STATUS_PROCESSING
            ]);
            DB::commit();
            return new OrderResource($order);
        } catch (\Exception $e) {
            logger($e);
            DB::rollBack();
        }
        //创建订单请求日志（业务系统请求网关）监听器
    }

    /**
     * Create order
     * 
     * @param OrderRequest $request
     * @param Channel $channel
     * @param ChannelPayWay $payWay
     * @return Order
     */
    protected function createOrder(OrderRequest $request, Channel $channel, ChannelPayWay $payWay)
    {
        //创建订单基本信息
        $order = Order::create([
            'out_trade_no' => $request->out_trade_no,
            'client_id' => $channel->client_id,
            'payment_channel_id' => $channel->id,
            'channel' => $channel->channel,
            'payment_channel_pay_way_id' => $payWay->id,
            'pay_way' => $request->pay_way,
            'subject' => $request->subject,
            'amount' => intval($request->amount),
            'body' => $request->body,
            'detail' => $request->detail,
            'extra' => $request->extra,
            'buyer' => $request->buyer,
            'seller' => $request->has('seller') ? $request->seller : $payWay->merchant_id,
            'pay_at' => Carbon::now(),
            'expired_at' => Carbon::now()->addHour(2)
        ]);
        //订单号生成并回写
        $orderNo = sprintf(
            '%s%s',
            Carbon::now()->timezone('Asia/Shanghai')->format('YmdHis'),
            str_pad($order->id, 4, 0, STR_PAD_LEFT)
        );
        $order->update([
            'trade_no' => $orderNo
        ]);
        return $order;
    }

    /**
     * Refund
     * 
     * @param $request
     * @return OrderResource
     */
    public function refund($request)
    {
        //提交订单号
        $order = Order::where('trade_no', $request->trade_no)
            ->where('status', Order::PAY_STATUS_SUCCESS)
            ->first();
        if (!$order) {
            abort(404, '没有该订单');
        }
        //是否已经退款完成
        if ($order->successfulRefund()) {
            abort(404, '订单已经退款完成');
        }
        if ($order->processingRefund()) {
            abort(404, '订单正在退款中');
        }
        //创建退款单号, 开启事务
        DB::beginTransaction();
        try {
            $refund = $this->createRefund($order);
            //创建退款请求日志
            Event::fire(new InternalRequestRefund($refund, $request));
            //创建渠道订单请求
            switch ($order->channel) {
                case Order::CHANNEL_WECHAT:
                    $ref = new WechatService($order, $channel, $payWay, $refund);
                    $response = $ref->refund();
                    break;
            }
            Event::fire(new ExternalRequestRefund($refund, $request, $response));
            DB::commit();
            return new OrderResource($order);
        } catch (\Exception $e) {
            DB::rollBack();
        }
    }

    /**
     * Crearte refund
     * 
     * @param  Order $order
     * @return Refund
     */
    protected function createRefund(Order $order)
    {
        $refund = Refund::create([
            'client_id' => $order->client_id,
            'payment_channel_id' => $order->payment_channel_id,
            'payment_order_id' => $order->id,
            'trade_no' => $order->trade_no,
            'amount' => $order->amount,
            'reason' => '用户退货',
        ]);
        //订单号生成并回写
        $refundNo = sprintf(
            '%s%s',
            Carbon::now()->timezone('Asia/Shanghai')->format('YmdHis'),
            str_pad($order->id, 4, 0, STR_PAD_LEFT)
        );
        $refund->update([
            'refund_no' => $refundNo
        ]);
        return $refund;
    }
}