@extends('layouts.app')
@section('title', '订单列表')

@section('content')
    <div class="row">
        <div class="col-lg-10 col-lg-offset-1">
            <div class="panel panel-default orders-panel">
                <div class="panel-heading">订单列表</div>
                <div class="panel-body">
                    <ul class="list-group">
                        @foreach($orders as $order)
                            <li class="list-group-item">
                                <div class="panel panel-default">
                                    <div class="panel-heading">
                                        订单号：{{ $order->no }}
                                        <span class="pull-right order-created-at">{{ $order->created_at->format('Y-m-d H:i:s') }}</span>
                                    </div>
                                    <div class="panel-body">
                                        <table class="table">
                                            <thead>
                                            <tr>
                                                <th>商品信息</th>
                                                <th class="text-center">单价</th>
                                                <th class="text-center">数量</th>
                                                <th class="text-center">订单总价</th>
                                                <th class="text-center">状态</th>
                                                <th class="text-center">操作</th>
                                            </tr>
                                            </thead>
                                            @foreach($order->items as $index => $item)
                                                <tr>
                                                    <td class="product-info"
                                                        @if($index == 0) style="border-top: 0px;" @endif>
                                                        <div class="preview">
                                                            <a target="_blank"
                                                               href="{{ route('products.show', [$item->product_id]) }}">
                                                                <img src="{{ $item->product->image_url }}">
                                                            </a>
                                                        </div>
                                                        <div>
                                                            <span class="product-title">
                                                               <a target="_blank"
                                                                  href="{{ route('products.show', [$item->product_id]) }}">{{ $item->product->title }}</a>
                                                            </span>
                                                            <div class="sku-attrs">
                                                                @foreach($item->product->skus_attributes as $attribute)
                                                                    <div class="attributes">
                                                                        <span class="title">{{$attribute->name}}：</span>
                                                                        <span class="value">
                                                                            @foreach($attribute->attr_values as $attr_value)
                                                                                @if(in_array($attr_value->symbol, $item->product_sku->attr_array))
                                                                                    {{$attr_value->value}}
                                                                                    @break
                                                                                @endif
                                                                            @endforeach
                                                                        </span>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="sku-price text-center">￥{{ $item->price }}</td>
                                                    <td class="sku-amount text-center">{{ $item->amount }}</td>
                                                    @if($index === 0)
                                                        <td rowspan="{{ count($order->items) }}"
                                                            class="text-center total-amount">
                                                            ￥{{ $order->total_amount }}</td>
                                                        <td rowspan="{{ count($order->items) }}" class="text-center">
                                                            @if($order->paid_at)
                                                                @if($order->refund_status === \App\Models\Order::REFUND_STATUS_PENDING)
                                                                    @if($order->ship_status === \App\Models\Order::SHIP_STATUS_PENDING)
                                                                        已支付
                                                                    @else
                                                                        {{ \App\Models\Order::$shipStatusMap[$order->ship_status] }}
                                                                    @endif
                                                                @else
                                                                    {{ \App\Models\Order::$refundStatusMap[$order->refund_status] }}
                                                                @endif
                                                            @elseif($order->closed)
                                                                已关闭
                                                            @else
                                                                未支付<br>
                                                                请于 {{ $order->created_at->addSeconds(config('app.order_ttl'))->format('H:i') }}
                                                                前完成支付<br>
                                                                否则订单将自动关闭
                                                            @endif
                                                        </td>
                                                        <td rowspan="{{ count($order->items) }}" class="text-center">
                                                            <div class="order-actions">
                                                                <p>
                                                                    <a class="btn btn-success btn-xs"
                                                                       href="{{ route('orders.show', ['order' => $order]) }}">查看订单</a>
                                                                </p>
                                                                @if($order->ship_status === \App\Models\Order::SHIP_STATUS_RECEIVED)
                                                                    <p>
                                                                        <a class="btn {{ $order->reviewed ? 'btn-default' : 'btn-primary'}} btn-xs"
                                                                           href="{{ route('orders.review.show', ['order' => $order]) }}">
                                                                            {{ $order->reviewed ? '查看评价': '评价'}}
                                                                        </a>
                                                                    </p>
                                                                @endif
                                                            </div>
                                                        </td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        </table>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <div class="pull-right">{{ $orders->render() }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection