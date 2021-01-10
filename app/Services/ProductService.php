<?php
namespace App\Services;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Auth;
use App\Exceptions\InvalidRequestException;
use App\Services\CategoryService;
use App\Models\Category;

class ProductService
{
    public function index($request)
    {
        $builder = Product::query()->where('on_sale', true);

        $builder = $this->list_query($builder, $request);

        $search = $request->input('search', '');
        $order = $request->input('order', '');
        $category = null;
        if ($request->input('category_id')) {
            $category = Category::find($request->input('category_id'));
        }

        $products = $builder->paginate(16);

        return [
            'products' => $products, 
            'category' => $category,
            'categoryTree' => (new CategoryService())->getCategoryTree(),
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
                    $builder = $builder->orderBy($m[1], $m[2]);
                }
            }
        }

        // 如果有传入 category_id 字段，并且在数据库中有对应的类目
        if ($request->input('category_id') && $category = Category::find($request->input('category_id'))) {
            // 如果这是一个父类目
            if ($category->is_directory) {
                // 则筛选出该父类目下所有子类目的商品
                $builder = $builder->whereHas('category', function ($query) use ($category) {
                    // 这里的逻辑参考本章第一节
                    $query->where('path', 'like', $category->path.$category->id.'-%');
                });
            } else {
                // 如果这不是一个父类目，则直接筛选此类目下的商品
                $builder = $builder->where('category_id', $category->id);
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

        $reviews = OrderItem::query()
            ->with(['order.user', 'productSku']) // 预先加载关联关系
            ->where('product_id', $product->id)
            ->whereNotNull('reviewed_at') // 筛选出已评价的
            ->orderBy('reviewed_at', 'desc') // 按评价时间倒序
            ->limit(10) // 取出 10 条
            ->get();

        return ['product' => $product, 'favored' =>$favored, 'reviews' => $reviews];
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