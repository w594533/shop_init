<?php
namespace App\Services;
use App\Models\CartItem;
use App\Models\ProductSku;
use Illuminate\Support\Facades\Auth;

class CartItemService
{
    public function add($request)
    {
        $user = Auth::user();
        $sku_id = $request->input('sku_id');
        $amount = $request->input('amount');
        if ($cart = $user->cartItems()->where('product_sku_id', $sku_id)->first()) {
            $cart->update(['amount' => $cart->amount + $amount]);
        } else {
            $cart = new CartItem([
                'amount' => $amount
            ]);
            $cart->user()->associate($user);
            $cart->productSku()->associate($sku_id);
            $cart->save();
        }
        return [];
    }

    public function index($request)
    {
        $user = Auth::user();

        $cartItems = $user->cartItems()->with(['productSku.product'])->get();
        $addresses = $user->addresses()->orderBy('last_used_at', 'desc')->get();
        return ['cartItems' => $cartItems, 'addresses' => $addresses];
    }

    public function remove($sku)
    {
        $user = Auth::user();
        $user->cartItems()->where('product_sku_id', $sku->id)->delete();
        return [];
    }
}