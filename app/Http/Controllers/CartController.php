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
}
