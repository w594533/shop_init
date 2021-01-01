<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Services\ProductService;

class ProductsController extends Controller
{
    public function index(Request $request, ProductService $service)
    {
        $result = $service->index($request);

        return view('products.index', $result);
    }
}
