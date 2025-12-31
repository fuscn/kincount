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
use think\Exception;


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
        
        $list = $query->order('id', 'desc')
            ->paginate(['list_rows' => $params['limit'] ?? 15]);
        
        // 添加商品单位信息到每个SKU
        foreach ($list as &$sku) {
            if ($sku->product) {
                $sku->unit = $sku->product->unit;
            }
        }
        
        return $this->paginate($list);
    }

    public function skuRead($id)
    {
        $sku = ProductSku::with(['product', 'stocks'])
            ->where('deleted_at', null)
            ->find($id);
        if (!$sku) return $this->error('SKU不存在');
        
        // 添加商品单位信息
        if ($sku->product) {
            $sku->unit = $sku->product->unit;
        }
        
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
    //删除逻辑：当 SKU 库存为零时，不仅删除 SKU，同时也会删除相关的库存记录。
    public function skuDelete($id)
    {
        Db::startTrans();

        try {
            $sku = ProductSku::where('deleted_at', null)->find($id);
            if (!$sku) {
                Db::rollback();
                return $this->error('SKU不存在');
            }

            // 检查库存总量
            $totalStock = Stock::where('sku_id', $id)
                ->where('deleted_at', null)
                ->sum('quantity');

            if ($totalStock > 0) {
                Db::rollback();
                return $this->error('无法删除SKU，当前库存数量为 ' . $totalStock . '，请先清空库存');
            }

            // 方案一：手动更新 deleted_at（立即解决问题）
            $deletedStockCount = Stock::where('sku_id', $id)
                ->where('deleted_at', null)
                ->update([
                    'deleted_at' => date('Y-m-d H:i:s')
                ]);

            // 删除 SKU
            $sku->delete();

            Db::commit();

            return $this->success([
                'deleted_stock_records' => $deletedStockCount
            ], 'SKU删除成功，同时删除了 ' . $deletedStockCount . ' 条库存记录');
        } catch (\Exception $e) {
            Db::rollback();
            return $this->error('删除失败：' . $e->getMessage());
        }
    }

    /*----------  列表 + 搜索 + 分页+品牌过滤+分类过滤  ----------*/
public function skuSelect()
{
    try {
        $kw = request()->get('keyword', '');
        $categoryId = request()->get('category_id', '');
        $brandId = request()->get('brand_id', '');
        $limit = (int)request()->get('limit', 20);
        $page = (int)request()->get('page', 1);

        // 直接使用 JOIN 方式来处理产品表的关联查询，避免复杂的子查询解析问题
        $query = ProductSku::alias('sku')
            ->join('products p', 'sku.product_id = p.id', 'LEFT')
            ->field('sku.*, p.name as product_name, p.product_no as product_no, p.category_id, p.brand_id, p.unit')
            ->where('sku.status', 1)
            ->where('sku.deleted_at', null)
            ->where('p.deleted_at', null);

        // 关键词搜索
        if (!empty($kw)) {
            $query->where(function ($q) use ($kw) {
                $q->whereLike('sku.sku_code', "%{$kw}%")
                    ->whereOr('p.name', 'like', "%{$kw}%")
                    ->whereOr('p.product_no', 'like', "%{$kw}%");
            });
        }

        // 分类过滤
        if (!empty($categoryId)) {
            $query->where('p.category_id', $categoryId);
        }

        // 品牌过滤
        if (!empty($brandId)) {
            $query->where('p.brand_id', $brandId);
        }

        // 分页
        $list = $query->page($page, $limit)->select();

        // 批量获取所有SKU的库存数量，避免N+1查询
        $skuIds = $list->column('id');
        if (!empty($skuIds)) {
            $stockQuantities = Stock::whereIn('sku_id', $skuIds)
                ->where('deleted_at', null)
                ->group('sku_id')
                ->field('sku_id, SUM(quantity) as total_quantity')
                ->select()
                ->column('total_quantity', 'sku_id');
        } else {
            $stockQuantities = [];
        }

        // 为每个SKU添加库存数量
        foreach ($list as &$sku) {
            $sku->stock_quantity = isset($stockQuantities[$sku->id]) ? (int)$stockQuantities[$sku->id] : 0;
        }

        return $this->success($list);
    } catch (\Exception $e) {
        return $this->error('查询失败: ' . $e->getMessage());
    }
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

        /* 追加总库存和SKU数量 */
        foreach ($list as &$v) {
            // 正确计算总库存：先获取该商品的所有SKU，再计算这些SKU的库存总和
            $skuIds = ProductSku::where('product_id', $v['id'])->where('deleted_at', null)->column('id');
            if (!empty($skuIds)) {
                $v['total_stock'] = Stock::whereIn('sku_id', $skuIds)->sum('quantity');
            } else {
                $v['total_stock'] = 0;
            }
            // 添加SKU数量
            $v['sku_count'] = ProductSku::where('product_id', $v['id'])->where('deleted_at', null)->count();
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
        $list = ProductSku::where('product_id', $product_id)
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
    public function skuBatchUpdate($product_id)
    {
        try {
            // 验证商品是否存在
            $product = \app\kincount\model\Product::where('id', $product_id)
                ->whereNull('deleted_at')
                ->find();

            if (!$product) {
                return json(['code' => 404, 'message' => '商品不存在']);
            }

            // 获取商品单位
            $productUnit = $product->unit;

            // 获取请求数据
            $input = json_decode(file_get_contents('php://input'), true);
            $skusData = $input['skus'] ?? [];

            if (empty($skusData)) {
                return json(['code' => 400, 'message' => 'SKU数据不能为空']);
            }

            Db::startTrans();
            $results = [];
            $successCount = 0;
            $errorCount = 0;

            // 用于记录已经生成的SKU编码和条码，避免本次批量中的重复
            $generatedSkuCodes = [];
            $generatedBarcodes = [];

            foreach ($skusData as $index => $skuData) {
                try {
                    // 基本验证
                    if (empty($skuData['spec']) || !is_array($skuData['spec'])) {
                        throw new Exception("第" . ($index + 1) . "个SKU规格信息无效");
                    }

                    if (!isset($skuData['cost_price']) || $skuData['cost_price'] === '') {
                        throw new Exception("第" . ($index + 1) . "个SKU成本价不能为空");
                    }

                    if (!isset($skuData['sale_price']) || $skuData['sale_price'] === '') {
                        throw new Exception("第" . ($index + 1) . "个SKU销售价不能为空");
                    }

                    // 自动使用商品单位，无需前端提交
                    $skuData['unit'] = $productUnit;

                    $specJson = json_encode($skuData['spec'], JSON_UNESCAPED_UNICODE);

                    // 查找现有SKU - 关键修复：正确识别哪些是更新，哪些是新增
                    $existingSku = null;

                    // 情况1：如果请求中明确提供了id，说明是更新已有SKU
                    if (isset($skuData['id']) && !empty($skuData['id'])) {
                        $existingSku = \app\kincount\model\ProductSku::where('product_id', $product_id)
                            ->where('id', $skuData['id'])
                            ->whereNull('deleted_at')
                            ->find();

                        if (!$existingSku) {
                            throw new Exception("第" . ($index + 1) . "个SKU不存在（ID: {$skuData['id']}）");
                        }
                    }
                    // 情况2：如果没有提供id，但根据规格找到了匹配的SKU，也是更新
                    else {
                        $existingSku = \app\kincount\model\ProductSku::where('product_id', $product_id)
                            ->where('spec', $specJson)
                            ->whereNull('deleted_at')
                            ->find();
                    }

                    if ($existingSku) {
                        // 更新已有SKU
                        $updateData = [
                            'cost_price' => floatval($skuData['cost_price']),
                            'sale_price' => floatval($skuData['sale_price']),
                            'unit' => $skuData['unit'],
                            'status' => isset($skuData['status']) ? intval($skuData['status']) : 1
                        ];

                        // 记录更新前的条码，用于后续检查
                        $oldBarcode = $existingSku->barcode;

                        // 如果提供了条码且不为空，则更新条码
                        if (isset($skuData['barcode']) && !empty(trim($skuData['barcode']))) {
                            $newBarcode = trim($skuData['barcode']);

                            // 只有当新旧条码不同时才需要检查
                            if ($newBarcode !== $oldBarcode) {
                                // 检查条码是否被其他SKU使用（排除自己）
                                $barcodeExists = \app\kincount\model\ProductSku::where('barcode', $newBarcode)
                                    ->where('id', '<>', $existingSku->id)
                                    ->whereNull('deleted_at')
                                    ->find();

                                if ($barcodeExists) {
                                    throw new Exception("条码 '{$newBarcode}' 已被其他SKU使用");
                                }
                            }
                            $updateData['barcode'] = $newBarcode;
                        }

                        $existingSku->save($updateData);

                        $results[] = [
                            'index' => $index,
                            'spec' => $skuData['spec'],
                            'action' => 'updated',
                            'id' => $existingSku->id,
                            'sku_code' => $existingSku->sku_code,
                            'barcode' => $existingSku->barcode,
                            'success' => true
                        ];
                        $successCount++;
                    } else {
                        // 创建新SKU - 只有在前端没有提供id且数据库中没有匹配规格时才执行
                        $newSkuData = [
                            'product_id' => $product_id,
                            'spec' => $skuData['spec'],
                            'cost_price' => floatval($skuData['cost_price']),
                            'sale_price' => floatval($skuData['sale_price']),
                            'unit' => $skuData['unit'],
                            'status' => isset($skuData['status']) ? intval($skuData['status']) : 1
                        ];

                        // 生成SKU编码（使用修复后的方法）
                        $skuCode = \app\kincount\model\ProductSku::generateSkuCode($product_id, $skuData['spec']);

                        // 检查SKU编码是否在本次批量中已生成
                        if (in_array($skuCode, $generatedSkuCodes)) {
                            throw new Exception("SKU编码 '{$skuCode}' 在本次批量中重复");
                        }

                        // 检查SKU编码是否在数据库中已存在
                        $skuCodeExists = \app\kincount\model\ProductSku::where('sku_code', $skuCode)
                            ->whereNull('deleted_at')
                            ->find();

                        if ($skuCodeExists) {
                            throw new Exception("SKU编码 '{$skuCode}' 在数据库中已存在");
                        }

                        $generatedSkuCodes[] = $skuCode;
                        $newSkuData['sku_code'] = $skuCode;

                        // 处理条码
                        $barcode = '';
                        if (isset($skuData['barcode']) && !empty(trim($skuData['barcode']))) {
                            $barcode = trim($skuData['barcode']);

                            // 检查条码是否在本次批量中已生成
                            if (in_array($barcode, $generatedBarcodes)) {
                                throw new Exception("条码 '{$barcode}' 在本次批量中重复");
                            }

                            // 检查条码是否在数据库中已存在
                            $barcodeExists = \app\kincount\model\ProductSku::where('barcode', $barcode)
                                ->whereNull('deleted_at')
                                ->find();

                            if ($barcodeExists) {
                                throw new Exception("条码 '{$barcode}' 在数据库中已存在");
                            }

                            $generatedBarcodes[] = $barcode;
                            $newSkuData['barcode'] = $barcode;
                        } else {
                            // 自动生成条码
                            $barcode = \app\kincount\model\ProductSku::generateBarcode();

                            // 检查生成的条码是否重复
                            $attempts = 0;
                            while (in_array($barcode, $generatedBarcodes) && $attempts < 5) {
                                $barcode = \app\kincount\model\ProductSku::generateBarcode();
                                $attempts++;
                            }

                            if ($attempts >= 5) {
                                throw new Exception("无法生成唯一的条码");
                            }

                            $generatedBarcodes[] = $barcode;
                            $newSkuData['barcode'] = $barcode;
                        }

                        $newSku = new \app\kincount\model\ProductSku($newSkuData);
                        $newSku->save();

                        $results[] = [
                            'index' => $index,
                            'spec' => $skuData['spec'],
                            'action' => 'created',
                            'id' => $newSku->id,
                            'sku_code' => $newSku->sku_code,
                            'barcode' => $newSku->barcode,
                            'success' => true
                        ];
                        $successCount++;
                    }
                } catch (Exception $e) {
                    $results[] = [
                        'index' => $index,
                        'spec' => $skuData['spec'] ?? [],
                        'action' => 'error',
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                    $errorCount++;
                }
            }

            // 如果有错误且没有成功操作，则回滚
            if ($errorCount > 0 && $successCount === 0) {
                Db::rollback();
                return json([
                    'code' => 400,
                    'message' => '所有SKU操作失败',
                    'data' => $results
                ]);
            }

            Db::commit();

            $message = "批量操作完成";
            if ($successCount > 0 && $errorCount > 0) {
                $message = "成功{$successCount}个，失败{$errorCount}个";
            } elseif ($successCount > 0) {
                $message = "全部{$successCount}个SKU操作成功";
            }

            return json([
                'code' => 200,
                'message' => $message,
                'data' => [
                    'total' => count($skusData),
                    'success' => $successCount,
                    'error' => $errorCount,
                    'results' => $results
                ]
            ]);
        } catch (Exception $e) {
            Db::rollback();
            return json([
                'code' => 500,
                'message' => '操作失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }
}
