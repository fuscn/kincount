<?php

declare(strict_types=1);

namespace app\kincount\controller;

use app\kincount\model\Product;
use app\kincount\model\ProductSpecDefinition;
use app\kincount\model\ProductSku;
use app\kincount\model\Stock;
use app\kincount\model\Warehouse;
use think\facade\Db;

/**
 * 商品资料控制器
 * 路由已固定指向以下方法，不得随意改名
 */
class ProductController extends BaseController
{
    // 读聚合
    public function aggregate($id)
    {
        $prod = Product::with(['category', 'brand'])->find($id);
        return $this->success($prod);
    }

    // 创建聚合
    public function saveAggregate()
    {
        $prod = Product::create(request()->post());
        
        return $this->success(['id' => $prod->id], '创建成功');
    }

    // 更新聚合
    public function updateAggregate($id)
    {
        $prod = Product::find($id);
        $prod->save(request()->put());
        return $this->success([], '更新成功');
    }
    /* ===== SKU 维度 ===== */
    public function skuIndex()
    {
        $params = request()->get();
        $query  = \app\kincount\model\ProductSku::with(['product', 'warehouse'])
            ->where('deleted_at', null);
        if (!empty($params['keyword'])) {
            $query->whereLike('sku_code|spec_text', "%{$params['keyword']}%");
        }
        return $this->paginate($query->order('id', 'desc')
            ->paginate(['list_rows' => $params['limit'] ?? 15]));
    }

    public function skuRead($id)
    {
        $sku = \app\kincount\model\ProductSku::with(['product', 'stocks'])
            ->where('deleted_at', null)
            ->find($id);
        if (!$sku) return $this->error('SKU不存在');
        return $this->success($sku);
    }

    public function skuSave()
    {
        $post = request()->post();
        $sku  = \app\kincount\model\ProductSku::create($post);
        return $this->success(['id' => $sku->id], 'SKU添加成功');
    }

    public function skuUpdate($id)
    {
        $sku = \app\kincount\model\ProductSku::where('deleted_at', null)->find($id);
        if (!$sku) return $this->error('SKU不存在');
        $sku->save(request()->put());
        return $this->success([], 'SKU更新成功');
    }

    public function skuDelete($id)
    {
        $sku = \app\kincount\model\ProductSku::where('deleted_at', null)->find($id);
        if (!$sku) return $this->error('SKU不存在');
        $sku->delete();
        return $this->success([], 'SKU删除成功');
    }

    public function skuSelect()
    {
        $kw   = request()->get('keyword', '');
        $list = \app\kincount\model\ProductSku::with('product')
            ->where('status', 1)
            ->where('deleted_at', null)
            ->whereLike('sku_code|spec_text', "%{$kw}%")
            ->limit((int)request()->get('limit', 10))
            ->select();
        return $this->success($list);
    }
    /*----------  列表 + 搜索 + 分页  ----------*/
    public function index()
    {
        $page      = (int)input('page', 1);
        $limit     = (int)input('limit', 15);
        $keyword   = input('keyword', '');
        $cateId    = (int)input('category_id', 0);
        $brandId   = (int)input('brand_id', 0);
        $status    = input('status', '');

        $query = Product::with(['category', 'brand'])
            ->where('deleted_at', null);

        if ($keyword !== '') {
            $query->whereLike('name|product_no', "%{$keyword}%");
        }
        if ($cateId)   $query->where('category_id', $cateId);
        if ($brandId)  $query->where('brand_id', $brandId);
        if ($status !== '') $query->where('status', $status);

        $list = $query->order('id', 'desc')
            ->paginate(['list_rows' => $limit, 'page' => $page]);

        /* 追加总库存 */
        foreach ($list as &$v) {
            $v['total_stock'] = Stock::where('sku_id', $v['id'])->sum('quantity');
        }

        return $this->paginate($list);
    }

    /*----------  单条详情  ----------*/
    public function read($id)
    {
        $product = Product::with(['category', 'brand', 'stocks.warehouse'])
            ->where('deleted_at', null)
            ->find($id);

        if (!$product) return $this->error('商品不存在');

        $product->append(['total_stock']);
        return $this->success($product);
    }

    /*----------  新增  ----------*/
    public function save()
    {
        $post = input('post.');
        validate([
            'name'        => 'require',
            'category_id' => 'require|integer',
            'unit'        => 'require',
            'cost_price'  => 'require|float|egt:0',
            'sale_price'  => 'require|float|egt:0',
        ])->check($post);

        $product = Db::transaction(function () use ($post) {
            // 1. 主表
            $product = Product::create(array_merge($post, [
                'images' => isset($post['images']) && is_array($post['images'])
                    ? json_encode($post['images'], JSON_UNESCAPED_UNICODE)
                    : null,
            ]));

            // 2. 规格定义（前端传数组）
            foreach ($post['spec_definitions'] ?? [] as $spec) {
                ProductSpecDefinition::create([
                    'sku_id'  => $product->id,
                    'spec_name'   => $spec['name'],
                    'spec_values' => $spec['values'],
                    'sort'        => $spec['sort'] ?? 0,
                ]);
            }

            // 3. 自动生成全部 SKU
            $combinations = $this->generateSpecCombinations(
                ProductSpecDefinition::where('sku_id', $product->id)->select()
            );
            foreach ($combinations as $combo) {
                $sku = ProductSku::create([
                    'sku_id' => $product->id,
                    'sku_code'   => ProductSku::generateSkuCode($product->id),
                    'spec'       => $combo,
                    'cost_price' => $post['cost_price'],
                    'sale_price' => $post['sale_price'],
                    'unit'       => $post['unit'],
                ]);

                // 4. 各仓库库存初始化
                Warehouse::where('status', 1)->select()->each(function ($wh) use ($sku) {
                    Stock::create([
                        'sku_id'       => $sku->id,
                        'warehouse_id' => $wh->id,
                        'quantity'     => 0,
                        'cost_price'   => $sku->cost_price,
                        'total_amount' => 0,
                    ]);
                });
            }

            return $product;
        });

        return $this->success(['id' => $product->id], '商品+SKU 创建完成');
    }

    /*----------  更新  ----------*/
    public function update($id)
    {
        $product = Product::where('deleted_at', null)->find($id);
        if (!$product) return $this->error('商品不存在');

        $post = input('post.');
        $validate = new \think\Validate([
            'product_no' => 'unique:products,' . $id,
            'cost_price' => 'float|egt:0',
            'sale_price' => 'float|egt:0',
        ]);
        if (!$validate->check($post)) return $this->error($validate->getError());

        if (isset($post['images']) && is_array($post['images'])) {
            $post['images'] = json_encode($post['images'], JSON_UNESCAPED_UNICODE);
        }

        $product->save($post);
        return $this->success([], '商品更新成功');
    }

    /*----------  删除（软删） ----------*/
    public function delete($id)
    {
        $product = Product::where('deleted_at', null)->find($id);
        if (!$product) return $this->error('商品不存在');

        $used = $product->purchaseOrderItems()->find() || $product->saleOrderItems()->find();
        if ($used) return $this->error('该商品已被业务单据使用，无法删除');

        Db::transaction(function () use ($product) {
            $product->delete();          // 软删
            Stock::where('sku_id', $product->id)->delete(); // 同步
        });

        return $this->success([], '商品删除成功');
    }

    /*----------  批量操作  ----------*/
    public function batch()
    {
        $action = input('action');
        $ids    = input('ids/a', []);
        if (empty($ids)) return $this->error('请选择要操作的商品');

        switch ($action) {
            case 'enable':
                Product::whereIn('id', $ids)->update(['status' => 1]);
                return $this->success([], '批量上架成功');
            case 'disable':
                Product::whereIn('id', $ids)->update(['status' => 0]);
                return $this->success([], '批量下架成功');
            case 'delete':
                Db::transaction(function () use ($ids) {
                    Product::whereIn('id', $ids)->delete();
                    Stock::whereIn('sku_id', $ids)->delete();
                });
                return $this->success([], '批量删除成功');
            default:
                return $this->error('不支持的操作类型');
        }
    }

    /*----------  选择器搜索（远程下拉） ----------*/
    public function selectSearch()
    {
        $keyword = input('keyword', '');
        $limit   = (int)input('limit', 10);

        $list = Product::where('status', 1)
            ->where('deleted_at', null)
            ->where(function ($q) use ($keyword) {
                $q->whereLike('name|product_no', "%{$keyword}%");
            })
            ->field('id, product_no, name, spec, unit, cost_price, sale_price')
            ->order('id', 'desc')
            ->limit($limit)
            ->select();

        return $this->success($list);
    }
    // 工具：生成规格组合
    private function generateSpecCombinations($defs)
    {
        $result = [[]];
        foreach ($defs as $def) {
            $temp = [];
            foreach ($result as $item) {
                foreach ($def['spec_values'] as $v) {
                    $temp[] = array_merge($item, [$def['spec_name'] => $v]);
                }
            }
            $result = $temp;
        }
        return $result;
    }
}
