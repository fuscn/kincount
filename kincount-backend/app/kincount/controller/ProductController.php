<?php

declare(strict_types=1);

namespace app\kincount\controller;

use app\kincount\model\Product;
use app\kincount\model\ProductSpecDefinition;
use app\kincount\model\ProductSku;
use app\kincount\model\Stock;
use app\kincount\model\Warehouse;
use think\facade\Db;
use think\exception\ValidateException;

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
        $query  = ProductSku::with(['product', 'warehouse'])
            ->where('deleted_at', null);
        if (!empty($params['keyword'])) {
            $query->whereLike('sku_code|spec_text', "%{$params['keyword']}%");
        }
        return $this->paginate($query->order('id', 'desc')
            ->paginate(['list_rows' => $params['limit'] ?? 15]));
    }

    public function skuRead($id)
    {
        $sku = ProductSku::with(['product', 'stocks'])
            ->where('deleted_at', null)
            ->find($id);
        if (!$sku) return $this->error('SKU不存在');
        return $this->success($sku);
    }

    public function skuSave()
    {
        $post = request()->post();
        $sku  = ProductSku::create($post);
        return $this->success(['id' => $sku->id], 'SKU添加成功');
    }

    public function skuUpdate($id)
    {
        $sku = ProductSku::where('deleted_at', null)->find($id);
        if (!$sku) return $this->error('SKU不存在');
        $sku->save(request()->put());
        return $this->success([], 'SKU更新成功');
    }

    public function skuDelete($id)
    {
        $sku = ProductSku::where('deleted_at', null)->find($id);
        if (!$sku) return $this->error('SKU不存在');
        $sku->delete();
        return $this->success([], 'SKU删除成功');
    }

    public function skuSelect()
    {
        $kw   = request()->get('keyword', '');
        $list = ProductSku::with('product')
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
    /**
     * 批量新增 SKU（TP8助手函数版）
     */
    public function skuBatchSave()
    {
        try {
            // 1. TP8助手函数：获取POST参数
            $post = request()->post();

            // 2. TP8助手函数：验证规则（简化版验证，也可使用独立验证器）
            $validateRule = [
                'product_id' => 'require|integer',
                'skus'       => 'require|array',
                'skus.*.spec' => 'require|array',
                'skus.*.cost_price' => 'require|float|egt:0',
                'skus.*.sale_price' => 'require|float|egt:0',
                'skus.*.unit' => 'require',
                'skus.*.stock' => 'integer|egt:0',
                'skus.*.status' => 'integer|in:0,1',
            ];
            // 验证失败抛出异常
            validate($validateRule)->check($post);

            $productId = $post['product_id'];
            $skusData = $post['skus'];

            // 验证商品是否存在
            $product = Product::where('id', $productId)->where('deleted_at', null)->find();
            if (!$product) {
                return $this->error('商品不存在');
            }

            // 3. TP8助手函数：数据库事务
            DB::transaction(function () use ($productId, $skusData) {
                foreach ($skusData as $item) {
                    // 模型自动生成sku_code和barcode（无需手动处理）
                    $sku = ProductSku::create([
                        'product_id'  => $productId,
                        'spec'        => $item['spec'],
                        'cost_price'  => $item['cost_price'],
                        'sale_price'  => $item['sale_price'],
                        'barcode'     => $item['barcode'],
                        'unit'        => $item['unit'],
                        'status'      => $item['status'] ?? 1,
                    ]);

                    // 初始化仓库库存
                    $stockQuantity = $item['stock'] ?? 0;
                    Warehouse::where('status', 1)->select()->each(function ($wh) use ($sku, $stockQuantity) {
                        Stock::create([
                            'sku_id'       => $sku->id,
                            'warehouse_id' => $wh->id,
                            'quantity'     => $stockQuantity,
                            'cost_price'   => $sku->cost_price,
                            'total_amount' => $stockQuantity * $sku->cost_price,
                        ]);
                    });
                }
            });

            return $this->success([], '批量新增SKU成功');
        } catch (ValidateException $e) {
            // 验证异常捕获
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            // 全局异常捕获
            return $this->error($e->getMessage());
        }
    }

    /**
     * 批量更新 SKU（根据商品ID）（TP8助手函数版）
     * @param int $product_id 路由参数中的商品ID
     */
    public function skuBatchUpdate($product_id)
    {
        try {
            // 1. TP8助手函数：获取PUT参数
            $post = request()->put();

            // 2. 验证规则
            $validateRule = [
                'skus'       => 'require|array',
                'skus.*.id'  => 'require|integer',
                'skus.*.cost_price' => 'float|egt:0',
                'skus.*.sale_price' => 'float|egt:0',
                'skus.*.unit' => 'chsAlphaNum',
                'skus.*.status' => 'integer|in:0,1',
            ];
            validate($validateRule)->check($post);

            $skusData = $post['skus'];

            // 验证商品是否存在
            $product = Product::where('id', $product_id)->where('deleted_at', null)->find();
            if (!$product) {
                return $this->error('商品不存在');
            }

            // 数据库事务
            DB::transaction(function () use ($product_id, $skusData) {
                foreach ($skusData as $item) {
                    $sku = ProductSku::where('id', $item['id'])
                        ->where('product_id', $product_id)
                        ->where('deleted_at', null)
                        ->find();
                    if (!$sku) {
                        throw new \Exception("SKU ID:{$item['id']} 不存在或不属于当前商品");
                    }

                    // 过滤更新字段
                    $updateData = array_filter($item, function ($key) {
                        return in_array($key, ['spec', 'cost_price', 'sale_price', 'barcode', 'unit', 'status']);
                    }, ARRAY_FILTER_USE_KEY);

                    $sku->save($updateData);
                }
            });

            return $this->success([], '批量更新SKU成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 根据商品ID获取所有SKU（TP8助手函数版）
     * @param int $product_id 路由参数中的商品ID
     */
    public function skuByProduct($product_id)
    {
        // 验证商品是否存在
        $product = Product::where('id', $product_id)->where('deleted_at', null)->find();
        if (!$product) {
            return $this->error('商品不存在');
        }

        // 查询SKU并关联库存、仓库信息
        $list = ProductSku::with(['stocks.warehouse'])
            ->where('product_id', $product_id)
            ->where('deleted_at', null)
            ->select();

        return $this->success($list);
    }

    /**
     * 批量删除SKU（TP8助手函数版）
     */
    public function skuBatchDelete()
    {
        try {
            // 1. TP8助手函数：获取DELETE请求参数（TP8中delete请求参数需通过request()->delete()获取）
            $post = request()->delete();

            // 验证参数
            if (empty($post['ids']) || !is_array($post['ids'])) {
                return $this->error('请选择要删除的SKU');
            }
            $ids = array_unique($post['ids']); // 去重

            // 数据库事务
            DB::transaction(function () use ($ids) {
                // 验证SKU是否存在
                $skus = ProductSku::whereIn('id', $ids)->where('deleted_at', null)->select();
                if (count($skus) != count($ids)) {
                    throw new \Exception('部分SKU不存在或已被删除');
                }

                // 批量软删SKU
                ProductSku::whereIn('id', $ids)->delete();

                // 同步删除关联库存
                Stock::whereIn('sku_id', $ids)->delete();
            });

            return $this->success([], '批量删除SKU成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
