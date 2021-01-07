<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\OrderRequest;
use App\Services\OrderService;
use App\Models\Order;

class OrdersController extends Controller
{
    public function store(OrderRequest $request, OrderService $service)
    {
        $result = $service->store($request);
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
}
