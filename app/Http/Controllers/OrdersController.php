<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidRequestException;
use App\Http\Requests\ApplyRefundRequest;
use App\Http\Requests\CrowdFundingOrderRequest;
use App\Http\Requests\OrderRequest;
use App\Models\Order;
use App\Models\ProductSku;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class OrdersController extends Controller
{
    public function store(OrderRequest $request,OrderService $orderService)
    {
        $user=$request->user();

        $address = UserAddress::find($request->input('address_id'));

        return $orderService->store($user, $address, $request->input('remark'), $request->input('items'));
    }

    public function index(Request $request)
    {
        $orders=Order::query()
            ->with(['items.product','items.productSku'])
            ->where('user_id',$request->user()->id)
            ->orderBy('created_at','desc')
            ->paginate();

        return view('orders.index', ['orders' => $orders]);
    }

    public function show(Order $order,Request $request)
    {
        $this->authorize('own',$order);

        return view('orders.show', ['order' => $order->load(['items.productSku', 'items.product'])]);
    }

    public function crowdfunding(CrowdFundingOrderRequest $request,OrderService $orderService)
    {
        $user=$request->user();
        $sku=ProductSku::find($request->input('sku_id'));
        $address=UserAddress::find($request->input('address_id'));
        $amount=$request->input('amount');

        return $orderService->crowdfunding($user,$address,$sku,$amount);
    }


    public function received(Order $order,Request $request)
    {
        $this->authorize('own',$order);

        if ($order->ship_status !== Order::SHIP_STATUS_DELIVERED) {
            throw new InvalidRequestException('发货状态不正确');
        }

        $order->update(['ship_status'=>Order::SHIP_STATUS_RECEIVED]);

        return $order;
    }

    public function review(Order $order)
    {
        $this->authorize('own',$order);

        if($order->paid_at){
            throw new InvalidRequestException('该订单未支付，不可评价');
        }
    }

    public function applyRefund(Order $order,ApplyRefundRequest $request)
    {
        $this->authorize('own',$order);

        if(!$this->paid_at){
            throw new InvalidRequestException('该订单未支付，不可退款');
        }

        if ($order->refund_status !== Order::REFUND_STATUS_PENDING) {
            throw new InvalidRequestException('该订单已经申请过退款，请勿重复申请');
        }

        $extra                  = $order->extra ?: [];
        $extra['refund_reason'] = $request->input('reason');

        $order->update([
            'refund_status' => Order::REFUND_STATUS_APPLIED,
            'extra'         => $extra,
        ]);

        return $order;
    }

}
