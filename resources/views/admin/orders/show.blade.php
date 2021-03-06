<div class="box box-info">
    <div class="box-header with-border">
        <h3 class="box-title">订单流水号：{{ $order->no }}</h3>
        <div class="box-tools">
            <div class="btn-group pull-right" style="margin-right: 10px">
                <a href="{{ route('admin.orders.index') }}" class="btn btn-sm btn-default"><i class="fa fa-list"></i> 列表</a>
            </div>
        </div>
    </div>
    <div class="box-body">
        <table class="table table-bordered">
            <tbody>
            <tr>
                <td>买家：</td>
                <td>{{ $order->user->name }}</td>
                <td>支付时间：</td>
                <td>{{ $order->paid_at->format('Y-m-d H:i:s') }}</td>
            </tr>
            <tr>
                <td>支付方式：</td>
                <td>{{ $order->payment_method }}</td>
                <td>支付渠道单号：</td>
                <td>{{ $order->payment_no }}</td>
            </tr>
            <tr>
                <td>收货地址</td>
                <td colspan="3">
                    {{ $order->address['contact_name'] }}, {{ $order->address['contact_phone'] }}
                    , {{ $order->address['full_address'] }}, {{ $order->address['zip'] }}
                </td>
            </tr>
            </tbody>
        </table>

        {{--退款未成功前均可发货--}}
        @if($order->refund_status !== \App\Models\Order::REFUND_STATUS_SUCCESS)
            <table class="table table-bordered" style="margin-top: 20px;">
                <tr>
                    <th>发货状态</th>
                    <th>物流公司</th>
                    <th>物流单号</th>
                    <th>操作</th>
                </tr>
                <tr>
                    <td width="10%" @if($order->ship_status == 'pending')
                    style="color: #DD2727; vertical-align: middle;"
                        @else style="color:#00A65B; vertical-align: middle;"
                            @endif>
                        {{\App\Models\Order::$shipStatusMap[$order->ship_status]}}
                    </td>
                    @if($order->ship_status === \App\Models\Order::SHIP_STATUS_PENDING)
                        <td style="vertical-align: middle;">
                            <div class="form-group {{ $errors->has('express_company') ? 'has-error' : '' }}"
                                 style="margin-bottom: 0;">
                                <input type="text" class="form-control" name="express_company"
                                       placeholder="请输入物流公司">
                                @if($errors->has('express_company'))
                                    @foreach($errors->get('express_company') as $msg)
                                        <span class="help-block">{{ $msg }}</span>
                                    @endforeach
                                @endif
                            </div>
                        </td>
                        <td style="vertical-align: middle;">
                            <div class="form-group {{ $errors->has('express_company') ? 'has-error' : '' }}"
                                 style="margin-bottom: 0;">
                                <input type="text" class="form-control" name="express_no" placeholder="请输入物流单号">
                                @if($errors->has('express_no'))
                                    @foreach($errors->get('express_no') as $msg)
                                        <span class="help-block">{{ $msg }}</span>
                                    @endforeach
                                @endif
                            </div>
                        </td>
                        <td style="width: 10%; text-align: center">
                            <button class="btn btn-success btn-deliver">点击发货</button>
                        </td>
                    @else
                        <td style="vertical-align: middle;">
                            {{$order->ship_data['express_company']}}
                        </td>
                        <td style="vertical-align: middle;">
                            {{$order->ship_data['express_no']}}
                        </td>
                        <td style="width: 10%; text-align: center">
                            <button class="btn btn-success" disabled="disabled">已发货</button>
                        </td>
                    @endif
                </tr>
            </table>
        @endif

        <table class="table table-bordered" style="margin-top: 20px;">
            <tr>
                <th>商品名称</th>
                <th>商品属性</th>
                <th>单价</th>
                <th>数量</th>
            </tr>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->product->title }} {{ $item->product_sku->title }}</td>
                    <td>
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
                    </td>
                    <td>￥{{ $item->price }}</td>
                    <td>{{ $item->amount }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="2" style="font-weight: 600;">订单金额：</td>
                <td style="color: #DD2727;font-size: 18px;font-weight: 600;" colspan="2">
                    ￥{{ $order->total_amount }}</td>
            </tr>
        </table>
    </div>
</div>

<script>
    $(document).ready(function () {
        $(this).on('click', 'button.btn-deliver', function (e) {
            let express_company = $('input[name=express_company]').val();
            let express_no = $('input[name=express_no]').val();
            if (!express_company || !express_no) {
                swal('请先填写物流信息', '', 'warning');
                return;
            }

            swal({
                title: '确认发货？',
                text: '请注意物流信息是否填写正确',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '确认',
                cancelButtonText: '取消',
                focusCancel: true,
            }).then(function (result) {
                if (result.value) {
                    $.ajax({
                        url: "{{ route('admin.order.ship', ['order' => $order->id])}}",
                        type: 'POST',
                        data: JSON.stringify({
                            _token: LA.token,
                            express_company: express_company,
                            express_no: express_no,
                        }),
                        contentType: 'application/json',
                        success: function () {
                            swal({
                                title: '物流信息提交成功',
                                type: 'success'
                            }).then(function () {
                                $.pjax.reload('#pjax-container');
                            });
                        },
                        error: function (error) {
                            swal({
                                title: '服务器错误',
                                type: 'error',
                            });
                        },
                    });
                }
            });
        });
    });
</script>