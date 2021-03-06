<?php

namespace App\Services;

use App\Exceptions\CouponCodeUnavailableException;
use App\Exceptions\InternalException;
use App\Exceptions\InvalidRequestException;
use App\Jobs\CloseOrder;
use App\Models\CouponCode;
use App\Models\Order;
use App\Models\ProductSku;
use App\Models\User;
use App\Models\UserAddress;
use Carbon\Carbon;

class OrderService
{
    /**
     * 存储订单方法
     *
     * @param \App\Models\User $user
     * @param \App\Models\UserAddress $address
     * @param $remark
     * @param $items
     * @param \App\Models\CouponCode|null $couponCode
     * @return mixed
     * @throws \Throwable
     */
    public function store(User $user, UserAddress $address, $remark, $items, CouponCode $couponCode = null)
    {
        if ($couponCode) {
            // 最开始先校验，否则不执行后续的数据库事务
            $couponCode->checkCodeAvailable($user);
        }

        $order = \DB::transaction(function () use ($user, $address, $remark, $items, $couponCode) {
            // 记录本次地址使用的时间
            $address->update(['last_used_at' => Carbon::now()]);

            // 生成订单数据
            $order = new Order([
                'address' => [
                    'full_address' => $address->full_address,
                    'zip' => $address->zip,
                    'contact_name' => $address->contact_name,
                    'contact_phone' => $address->contact_phone
                ],
                'remark' => $remark,
                'total_amount' => 0,
                'type' => Order::TYPE_NORMAL,
            ]);

            // 插入对应用户ID
            $order->user()->associate($user);

            $order->save();

            // 接下来保存order_items
            $total_amount = 0;

            foreach ($items as $item) {
                $sku = ProductSku::query()->find($item['sku_id']);
                $orderItem = $order->items()->make([
                    'amount' => $item['amount'],
                    'price' => $sku->price,
                ]);
                $orderItem->product_sku()->associate($sku);
                $orderItem->product()->associate($sku->product_id);
                $orderItem->save();
                $total_amount += $item['amount'] * ($sku->price);
                // 因为用的是数据库事务，抛出异常后，前面的保存操作均会回滚
                if ($sku->decreaseStock($item['amount']) <= 0) {
                    throw new InternalException('该商品库存不足');
                }
            }

            // 进行折扣
            if ($couponCode) {
                // 先校验优惠券是否可用，不可用会抛出异常
                $couponCode->checkCodeAvailable($user, $total_amount);

                // 关联优惠券到订单
                $order->couponCode()->associate($couponCode);
                $total_amount = $couponCode->getAdjustPrice($total_amount);

                if ($couponCode->changeUsed(true) <= 0) {
                    throw new CouponCodeUnavailableException('该优惠券已被兑完');
                };
            }

            // 更新订单总金额
            $order->update(['total_amount' => $total_amount]);

            // 从购物车中删除商品
            $sku_id_collection = collect($items)->pluck('sku_id')->all();  // 这里别忘记转换成数组
            app(CartService::class)->remove($sku_id_collection);

            return $order;
        });
        // 分发'订单过期'任务，延迟时间从配置文件中获取
        CloseOrder::dispatch($order)
            ->delay(config('app.order_ttl'));

        return $order;
    }

    public function seckill(User $user, UserAddress $address, ProductSku $sku)
    {
        $order = \DB::transaction(function () use ($user, $address, $sku) {
            // 更新此地址的最后使用时间
            $address->update(['last_used_at' => Carbon::now()]);
            // 扣减对应 SKU 库存
            if ($sku->decreaseStock(1) <= 0) {
                throw new InvalidRequestException('该商品库存不足');
            }
            // 创建一个订单
            $order = new Order([
                'address' => [ // 将地址信息放入订单中
                    'full_address' => $address->full_address,
                    'zip' => $address->zip,
                    'contact_name' => $address->contact_name,
                    'contact_phone' => $address->contact_phone,
                ],
                'remark' => '',
                'total_amount' => $sku->price,
                'type' => Order::TYPE_SECKILL,
            ]);
            // 订单关联到当前用户
            $order->user()->associate($user);
            // 写入数据库
            $order->save();
            // 创建一个新的订单项并与 SKU 关联
            $item = $order->items()->make([
                'amount' => 1, // 秒杀商品只能一份
                'price' => $sku->price,
            ]);
            $item->product()->associate($sku->product_id);
            $item->product_sku()->associate($sku);
            $item->save();

            return $order;
        });
        // 秒杀订单的自动关闭时间与普通订单不同
        dispatch(new CloseOrder($order))->delay(config('app.seckill_order_ttl'));

        return $order;
    }
}