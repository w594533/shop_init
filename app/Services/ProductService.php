<?php
namespace App\Services;
use App\Models\Product;
use App\Models\ProductSku;
use Illuminate\Support\Facades\Auth;
use App\Exceptions\InvalidRequestException;

class ProductService
{
    public function index($request)
    {
        $builder = Product::query()->where('on_sale', true);

        $builder = $this->list_query($builder, $request);

        $search = $request->input('search', '');
        $order = $request->input('order', '');

        $products = $builder->paginate(16);

        return [
            'products' => $products, 
            'filters'  => [
                'search' => $search,
                'order'  => $order,
            ]
        ];
    }

    public function list_query($builder, $request)
    {
        if ($search = $request->input('search', '')) {
            $like = '%' . $search . '%';
            // 模糊搜索商品标题、商品详情、SKU 标题、SKU描述
            $builder = $builder->where(function ($query) use ($like) {
                $query->where('title', 'like', $like)->orWhere('description', 'like', $like)->orWhereHas('skus', function ($query) use ($like) {
                    $query->where('title', 'like', $like)->orWhere('description', 'like', $like);
                });
            });
        }

        //order 排序
        if ($order = $request->input('order', '')) {
            // 是否是以 _asc 或者 _desc 结尾
            if (preg_match('/^(.+)_(asc|desc)$/', $order, $m)) {
                // 如果字符串的开头是这 3 个字符串之一，说明是一个合法的排序值
                if (in_array($m[1], ['price', 'sold_count', 'rating'])) {
                    // 根据传入的排序值来构造排序参数
                    $builder->orderBy($m[1], $m[2]);
                }
            }
        }
        return $builder;
    }

    public function show($product)
    {
        if (!$product->on_sale) {
            throw new InvalidRequestException('商品未上架');
        }
        $favored = false;
        if (Auth::check()) {
            $favored = boolval(Auth::user()->favoriteProducts()->find($product->id));
        }
        return ['product' => $product, 'favored' =>$favored];
    }

    public function favor($product)
    {
        $user = Auth::user();
        if ($user->favoriteProducts()->find($product->id)) {
            return [];
        }
        $user->favoriteProducts()->attach($product);
        return [];
    }

    public function disfavor($product)
    {
        $user = Auth::user();
        $user->favoriteProducts()->detach($product);

        return [];
    }

    public function favorites($request)
    {
        $user = Auth::user();
        $builder = $user->favoriteProducts();
        $builder = $this->list_query($builder, $request);
        $search = $request->input('search', '');
        $order = $request->input('order', '');

        $products = $builder->paginate(16);

        return [
            'products' => $products, 
            'filters'  => [
                'search' => $search,
                'order'  => $order,
            ]
        ];
    }
}