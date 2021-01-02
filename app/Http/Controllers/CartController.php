<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\AddCartRequest;
use App\Services\CartItemService;

class CartController extends Controller
{
    public function add(AddCartRequest $request, CartItemService $service)
    {
        $result = $service->add($request);
        return $result;
    }

    public function index(Request $request, CartItemService $service)
    {
        $result = $service->index($request);
        return view('cart.index', $result);
    }

    public function remove(\App\Models\ProductSku $sku, CartItemService $service)
    {
        $result = $service->remove($sku);
        return $result;
    }
}
