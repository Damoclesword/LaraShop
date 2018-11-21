<?php

namespace App\Admin\Controllers;

use App\Models\Product;
use App\Models\ProductSku;
use App\Http\Controllers\Controller;
use App\Models\ProductSkuAttributes;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class ProductSkusController extends Controller
{
    use HasResourceActions;

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index($product_id, Content $content)
    {
        return $content
            ->header('商品SKU列表')
            ->description('Product SKU List')
            ->body($this->grid($product_id));
    }

    /**
     * Edit interface.
     *
     * @param mixed $id
     * @param Content $content
     * @return Content
     */
    public function edit($product_id, $id, Content $content)
    {
        return $content
            ->header('编辑商品SKU')
            ->description('Edit Product SKU')
            ->body($this->form($product_id)->edit($id));
    }

    /**
     * Create interface.
     *
     * @param Content $content
     * @return Content
     */
    public function create($product_id, Content $content)
    {
        return $content
            ->header('新建商品SKU')
            ->description('Create Product SKU')
            ->body($this->form($product_id));
    }

    /**
     * SKU展示Grid
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid($product_id)
    {
        $grid = new Grid(new ProductSku);

        $grid->model()->where('product_id', $product_id);

        $grid->id('Id')->sortable();
        $grid->product_id('商品名称')
            ->display(function ($id) {
                $title = Product::where('id', $id)->pluck('title')->first();
                return $title;
            });
        $grid->title('SKU 名称');
        $grid->description('SKU 描述');
        $grid->price('价格');
        $grid->stock('库存');

        $grid->actions(function ($actions) {
            $actions->disableView();
            $actions->disableDelete();
        });

        $grid->tools(function ($tools) {
            // 禁用批量删除按钮
            $tools->batch(function ($batch) {
                $batch->disableDelete();
            });
        });

        return $grid;
    }

    /**
     * 编辑SKU表单
     * Make a form builder.
     *
     * @return Form
     */
    protected function form($product_id)
    {
        $form = new Form(new ProductSku);

        // Get All the attributes from this product
        $attributes = ProductSkuAttributes::all()
            ->where('product_id', $product_id)
            ->mapWithKeys(function ($item) {
                return [$item['id'] => $item['name']];
            });

        $form->tab('基本内容', function ($form) use ($attributes, $product_id) {
            $form->display('', '商品名称')
                ->with(function() use ($product_id) {
                $title = Product::where('id', $product_id)->pluck('title')->first();
                return $title;
            });
            $form->text('title', 'SKU 名称')->rules('required');
            $form->text('description', 'SKU 描述')->rules('required');
            $form->decimal('price', 'SKU 单价');
            $form->number('stock', '库存');

            $form->embeds('attributes', '商品属性', function ($form) use ($attributes) {
                foreach ($attributes as $id => $name) {
                    $form->text($id, $name)->rules('required', [
                        'required' => $name . '必须填写!',
                    ]);
                }
            });
        });

        $form->saving(function (Form $form) use ($product_id) {
            $form->model()->product_id = $product_id;
        });

        $form->saved(function (Form $form) use ($product_id) {
            $product = Product::all()->where('id', $product_id)->first();
            $minPrice = ProductSku::all()->where('product_id', $product_id)->min('price');

            // Use the sku's min price to update product's price
            $product->price != $minPrice ? $product->update(['price' => $minPrice]) : '';
        });

        return $form;
    }

    /**
     * @param $product_id
     */
    public function store($product_id)
    {
        $this->form($product_id)->store();
    }

    /**
     * @param $product_id
     * @param $id
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function update($product_id, $id)
    {
        return $this->form($product_id)->update($id);
    }


    public function destroy($product_id, $id)
    {
        if ($this->form($product_id)->destroy($id)) {
            $data = [
                'status' => true,
                'message' => trans('admin.delete_succeeded'),
            ];

            // If destroy success, update the product price to the min one or zero.
            $product = Product::all()->where('id', $product_id)->first();
            $productSku = ProductSku::all()->where('product_id', $product_id);
            if (!$productSku->isEmpty()) {
                $product->update(['price' => $productSku->min('price')]);
            } else {
                $product->update(['price' => '0.00']);
            }
        } else {
            $data = [
                'status' => false,
                'message' => trans('admin.delete_failed'),
            ];
        }

        return response()->json($data);
    }
}
