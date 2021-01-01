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

    public function show(Product $product, ProductService $service)
    {
        $result = $service->show($product);
        return view('products.show', $result);
    }

    public function favor(Product $product, ProductService $service)
    {
        $result = $service->favor($product);
        return $result;
    }

    public function disfavor(Product $product, ProductService $service)
    {
        $result = $service->disfavor($product);
        return $result;
    }
}
