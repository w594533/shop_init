<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\OrderRequest;
use App\Services\OrderService;
use App\Models\Order;
use Carbon\Carbon;
use App\Http\Requests\SendReviewRequest;
use App\Http\Requests\ApplyRefundRequest;
use App\Http\Requests\SeckillOrderRequest;
class OrdersController extends Controller
{
    public function store(OrderRequest $request, OrderService $service)
    {
        $result = $service->store($request);
        return $result;
    }

    public function seckill(SeckillOrderRequest $request, OrderService $service)
    {
        $result = $service->seckill($request);

        return $result;
    }

    public function index(Request $request, OrderService $service)
    {
        $result = $service->index($request);
        return view('orders.index', $result);
    }

    public function show(Order $order, Request $request)
    {
        $this->authorize('own', $order);
        
        return view('orders.show', ['order' => $order->load(['items.productSku', 'items.product'])]);
    }

    public function received(Order $order, Request $request, OrderService $service)
    {
        $result = $service->received($order, $request);

        // 返回原页面
        return $result;
    }

    public function review(Order $order, OrderService $service)
    {
        $result = $service->review($order);
        // 使用 load 方法加载关联数据，避免 N + 1 性能问题
        return view('orders.review', $result);
    }

    public function sendReview(Order $order, SendReviewRequest $request, OrderService $service)
    {
        $result = $service->sendReview($order, $request);

        return redirect()->back();
    }

    public function applyRefund(Order $order, ApplyRefundRequest $request, OrderService $service)
    {
        $result = $service->applyRefund($order, $request);

        return $result;
    }

    // 创建一个新的方法用于接受众筹商品下单请求
    public function crowdfunding(CrowdFundingOrderRequest $request, OrderService $service)
    {
        $result = $service->crowdfunding($request);

        return $result;
    }
}
