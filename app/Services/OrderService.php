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
use App\Jobs\CloseOrder;
use App\Events\OrderReviewed;
use App\Models\CouponCode;
use App\Exceptions\CouponCodeUnavailableException;

class OrderService
{
    public function store($request)
    {
        $user = Auth::user();

        $coupon  = null;

        // 如果用户提交了优惠码
        if ($code = $request->input('coupon_code')) {
            $coupon = CouponCode::where('code', $code)->first();
            if (!$coupon) {
                throw new CouponCodeUnavailableException('优惠券不存在');
            }
        }

        // 如果传入了优惠券，则先检查是否可用
        if ($coupon) {
            // 但此时我们还没有计算出订单总金额，因此先不校验
            $coupon->checkAvailable($user);
        }

        $order = \DB::transaction(function () use ($user, $request, $coupon) {
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
            if ($coupon) {
                // 总金额已经计算出来了，检查是否符合优惠券规则
                $coupon->checkAvailable($user, $total_amount);
                // 把订单金额修改为优惠后的金额
                $totalAmount = $coupon->getAdjustedPrice($total_amount);
                // 将订单与优惠券关联
                $order->couponCode()->associate($coupon);
                // 增加优惠券的用量，需判断返回值
                if ($coupon->changeUsed() <= 0) {
                    throw new CouponCodeUnavailableException('该优惠券已被兑完');
                }
            }

            // 更新订单总金额
            $order->update(['total_amount' => $totalAmount]);

            $sku_ids = collect($request->input('items'))->pluck('sku_id');
            $user->cartItems()->whereIn('product_sku_id', $sku_ids)->delete();

            // dispatch(new CloseOrder($order, 1800));//秒
            return $order;
        });
        return $order;
    }

    public function index($request)
    {
        $user = Auth::user();

        $orders = Order::query()
            // 使用 with 方法预加载，避免N + 1问题
            ->with(['items.product', 'items.productSku'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate();
        return ['orders' => $orders];
    }

    public function received($order, $request)
    {
        if (!Auth::user()->can('own', $order)) {
            \abort(403);
        }

        // 判断订单的发货状态是否为已发货
        if ($order->ship_status !== Order::SHIP_STATUS_DELIVERED) {
            throw new InvalidRequestException('发货状态不正确');
        }

        // 更新发货状态为已收到
        $order->update(['ship_status' => Order::SHIP_STATUS_RECEIVED]);

        // 返回原页面
        return $order;
    }

    public function review(Order $order)
    {
        if (!Auth::user()->can('own', $order)) {
            \abort(403);
        }
        // 判断是否已经支付
        if (!$order->paid_at) {
            throw new InvalidRequestException('该订单未支付，不可评价');
        }
        // 使用 load 方法加载关联数据，避免 N + 1 性能问题
        $order = $order->load(['items.productSku', 'items.product']);
        return ['order' => $order];
    }

    public function sendReview(Order $order, $request)
    {
        // 校验权限
        if (!Auth::user()->can('own', $order)) {
            \abort(403);
        }
        
        if (!$order->paid_at) {
            throw new InvalidRequestException('该订单未支付，不可评价');
        }
        // 判断是否已经评价
        if ($order->reviewed) {
            throw new InvalidRequestException('该订单已评价，不可重复提交');
        }
        $reviews = $request->input('reviews');
        // 开启事务
        $order = \DB::transaction(function () use ($reviews, $order) {
            // 遍历用户提交的数据
            foreach ($reviews as $review) {
                $orderItem = $order->items()->find($review['id']);
                // 保存评分和评价
                $orderItem->update([
                    'rating'      => $review['rating'],
                    'review'      => $review['review'],
                    'reviewed_at' => Carbon::now(),
                ]);
            }
            // 将订单标记为已评价
            $order->update(['reviewed' => true]);
            return $order;
        });
        event(new OrderReviewed($order));
        return ['order' => $order];
    }

    public function applyRefund(Order $order, $request)
    {
        // 校验订单是否属于当前用户
        if (!Auth::user()->can('own', $order)) {
            \abort(403);
        }
        // 判断订单是否已付款
        if (!$order->paid_at) {
            throw new InvalidRequestException('该订单未支付，不可退款');
        }
        // 判断订单退款状态是否正确
        if ($order->refund_status !== Order::REFUND_STATUS_PENDING) {
            throw new InvalidRequestException('该订单已经申请过退款，请勿重复申请');
        }
        // 将用户输入的退款理由放到订单的 extra 字段中
        $extra                  = $order->extra ?: [];
        $extra['refund_reason'] = $request->input('reason');
        // 将订单退款状态改为已申请退款
        $order->update([
            'refund_status' => Order::REFUND_STATUS_APPLIED,
            'extra'         => $extra,
        ]);

        return $order;
    }

    // 新建一个 crowdfunding 方法用于实现众筹商品下单逻辑
    public function crowdfunding($request)
    {
        $sku     = ProductSku::find($request->input('sku_id'));
        $address = UserAddress::find($request->input('address_id'));
        $amount  = $request->input('amount');

        $user = Auth::user();

        // 开启事务
        $order = \DB::transaction(function () use ($amount, $sku, $user, $address) {
            // 更新地址最后使用时间
            $address->update(['last_used_at' => Carbon::now()]);
            // 创建一个订单
            $order = new Order([
                'address'      => [ // 将地址信息放入订单中
                    'address'       => $address->full_address,
                    'zip'           => $address->zip,
                    'contact_name'  => $address->contact_name,
                    'contact_phone' => $address->contact_phone,
                ],
                'remark'       => '',
                'total_amount' => $sku->price * $amount,
            ]);
            // 订单关联到当前用户
            $order->user()->associate($user);
            // 写入数据库
            $order->save();
            // 创建一个新的订单项并与 SKU 关联
            $item = $order->items()->make([
                'amount' => $amount,
                'price'  => $sku->price,
            ]);
            $item->product()->associate($sku->product_id);
            $item->productSku()->associate($sku);
            $item->save();
            // 扣减对应 SKU 库存
            if ($sku->decreaseStock($amount) <= 0) {
                throw new InvalidRequestException('该商品库存不足');
            }

            return $order;
        });

        // 众筹结束时间减去当前时间得到剩余秒数
        $crowdfundingTtl = $sku->product->crowdfunding->end_at->getTimestamp() - time();
        // 剩余秒数与默认订单关闭时间取较小值作为订单关闭时间
        // dispatch(new CloseOrder($order, min(config('app.order_ttl'), $crowdfundingTtl)));

        return $order;
    }
}