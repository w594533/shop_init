<?php
namespace App\Services;
use App\Models\Product;
use App\Models\ProductSku;
use Illuminate\Support\Facades\Auth;

class ProductService
{
    public function index($request)
    {
        $products = Product::query()->where('on_sale', true)->paginate();
        return ['products' => $products];
    }
}