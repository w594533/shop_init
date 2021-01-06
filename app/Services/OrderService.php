<?php
namespace App\Services;

use Illuminate\Support\Facades\Auth;
use App\Exceptions\InvalidRequestException;
use App\Models\CartItem;
use App\Models\ProductSku;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\Order;
use App\Models\UserAddress;
use Carbon\Carbon;

class OrderService
{
    public function store($request)
    {
        $user = Auth::user();

        $order = \DB::transaction(function () use ($user, $request) {
            $address = UserAddress::find($request->address_id);

            $address->update(['last_used_at' => Carbon::now()]);

            $order = new Order([
                'address' => [
                    'address'       => $address->full_address,
                    'zip'           => $address->zip,
                    'contact_name'  => $address->contact_name,
                    'contact_phone' => $address->contact_phone,
                ],
                'remark' => $request->input('remark', ''),
                'total_amount' => 0
            ]);
            $order->user()->associate($user);
            $order->save();
            
            $total_amount = 0;

            //order item
            foreach($request->input('items') as $item) {
                $order_item = new OrderItem([
                    'amount' => $item['amount'],
                ]);
                $product_sku = ProductSku::find($item['sku_id']);
                $order_item->productSku()->associate($item['sku_id']);
                $order_item->product()->associate($product_sku->product_id);
                $order_item->order()->associate($order);
                $order_item->price = $product_sku->price;

                
                $order_item->save();
                $total_amount += $product_sku->price * $item['amount'];

                if ($product_sku->decreaseStock($item['amount']) <= 0) {
                    throw new InvalidRequestException('该商品库存不足');
                }
            }
            // 更新订单总金额
            $order->update(['total_amount' => $total_amount]);

            $sku_ids = collect($request->input('items'))->pluck('sku_id');
            $user->cartItems()->whereIn('product_sku_id', $sku_ids)->delete();

            return $order;
        });
        return $order;
    }
}