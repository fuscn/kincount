<?php

declare(strict_types=1);

namespace app\kincount\controller;

use think\db\exception\DbException;
use app\kincount\model\Stock;
use app\kincount\model\Product;
use app\kincount\model\Warehouse;
use think\facade\Db;

class StockController extends BaseController
{
    /* ===== SKU 维度 ===== */
    public function skuIndex()
    {
        $params = request()->get();
        $query  = \app\kincount\model\Stock::with(['sku.product', 'warehouse'])
            ->where('deleted_at', null);
        if (!empty($params['keyword'])) {
            $query->whereHas('sku', function ($q) use ($params) {
                $q->whereLike('sku_code|spec_text', "%{$params['keyword']}%");
            });
        }
        if (!empty($params['warehouse_id'])) {
            $query->where('warehouse_id', $params['warehouse_id']);
        }
        return $this->paginate($query->paginate(['list_rows' => $params['limit'] ?? 15]));
    }

    public function skuWarehouse($skuId)
    {
        $list = \app\kincount\model\Stock::with('warehouse')
            ->where('sku_id', $skuId)
            ->where('deleted_at', null)
            ->select();
        return $this->success($list);
    }

    public function skuUpdate($skuId)
    {
        $post = request()->put();
        $stock = \app\kincount\model\Stock::where('sku_id', $skuId)
            ->where('warehouse_id', $post['warehouse_id'])
            ->find();
        if (!$stock) return $this->error('库存记录不存在');
        $stock->updateQuantity($post['quantity'], $post['type'] ?? 'adjust');
        return $this->success([], '库存更新成功');
    }

    public function skuWarning()
    {
        $type = request()->get('type', 'low');
        $query = \app\kincount\model\Stock::with(['sku.product', 'warehouse'])
            ->where('deleted_at', null)
            ->whereRaw('quantity <= (select min_stock from products where id = stocks.product_id)');
        if ($type === 'high') {
            $query->whereRaw('quantity >= (select max_stock from products where id = stocks.product_id)');
        }
        return $this->paginate($query->paginate(15));
    }

    public function skuStatistics()
    {
        $totalAmt = \app\kincount\model\Stock::sum('total_amount');
        $skuCnt   = \app\kincount\model\Stock::distinct('sku_id')->count();
        $lowCnt   = \app\kincount\model\Stock::whereRaw('quantity <= (select min_stock from products where id = stocks.product_id)')->count();
        $highCnt  = \app\kincount\model\Stock::whereRaw('quantity >= (select max_stock from products where id = stocks.product_id)')->count();
        return $this->success([
            'total_amount'     => $totalAmt ?: 0,
            'sku_count'        => $skuCnt,
            'low_stock_count'  => $lowCnt,
            'high_stock_count' => $highCnt
        ]);
    }
    public function index()
    {
        $params = [
            'page'         => (int)input('page', 1),
            'limit'        => (int)input('limit', 15),
            'keyword'      => trim(input('keyword', '')),
            'warehouse_id' => (int)input('warehouse_id', 0),
        ];

        $query = Stock::alias('s')
            ->field('s.*,sku.sku_code,sku.spec,sku.unit,p.name product_name,p.product_no,w.name warehouse_name')
            ->join('product_skus sku', 'sku.id = s.sku_id')
            ->join('products p', 'p.id = sku.product_id')
            ->join('warehouses w', 'w.id = s.warehouse_id')
            ->where('s.deleted_at', null);

        if ($params['keyword']) {
            $query->where(function ($q) use ($params) {
                $q->whereLike('sku.sku_code|p.name|p.product_no', "%{$params['keyword']}%");
            });
        }
        if ($params['warehouse_id']) {
            $query->where('s.warehouse_id', $params['warehouse_id']);
        }

        $list = $query->order('s.id', 'desc')
            ->paginate(['list_rows' => $params['limit'], 'page' => $params['page']]);

        // 追加 spec_text
        foreach ($list as &$v) {
            $v['spec_text'] = implode(' | ', array_map(fn($k, $v) => "$k:$v", array_keys($v['spec']), $v['spec']));
        }

        return $this->paginate($list);
    }

    public function read($id)
    {
        $stock = Stock::with(['product', 'warehouse'])
            ->where('stocks.deleted_at', null)
            ->find($id);

        if (!$stock) {
            return $this->error('库存记录不存在2');
        }

        return $this->success($stock);
    }

    public function warning()
    {
        $type  = input('type', 'low'); // low | high
        $page  = (int)input('page', 1);
        $limit = (int)input('limit', 15);

        $query = Stock::with(['product', 'warehouse'])
            ->alias('stocks')
            ->where('stocks.deleted_at', null)
            ->join('products', 'stocks.product_id = products.id', 'INNER', []);

        if ($type === 'low') {
            $query->whereRaw('stocks.quantity <= products.min_stock');
        }

        if ($type === 'high') {
            $query->whereRaw('stocks.quantity >= products.max_stock');
        }

        $list = $query->order('stocks.quantity', 'asc')
            ->paginate(['list_rows' => $limit, 'page' => $page]);

        return $this->paginate($list);
    }
    public function statistics()
    {
        // 修正：需要确保只查询未删除的记录
        $totalAmt = Stock::where('deleted_at', null)->sum('total_amount');

        // 修正：获取商品种类数的方法
        $skuCnt = Stock::where('deleted_at', null)
            ->distinct(true)
            ->count('product_id');

        // 修正：低库存统计（需要关联 products 表）
        $lowCnt = Stock::alias('s')
            ->join('products p', 'p.id = s.product_id')
            ->where('s.deleted_at', null)
            ->where('p.deleted_at', null)
            ->whereRaw('s.quantity <= p.min_stock')
            ->count();

        // 修正：高库存统计（需要关联 products 表）
        $highCnt = Stock::alias('s')
            ->join('products p', 'p.id = s.product_id')
            ->where('s.deleted_at', null)
            ->where('p.deleted_at', null)
            ->whereRaw('s.quantity >= p.max_stock')
            ->count();

        // 修正：仓库统计
        $whStats = Stock::with('warehouse')
            ->where('stocks.deleted_at', null)
            ->field('warehouse_id, SUM(total_amount) as amount, COUNT(product_id) as product_count')
            ->group('warehouse_id')
            ->select();

        return $this->success([
            'total_amount'     => $totalAmt ?: 0,
            'product_count'    => $skuCnt,
            'low_stock_count'  => $lowCnt,
            'high_stock_count' => $highCnt,
            'warehouse_stats'  => $whStats,
        ]);
    }

    public function warehouseStocks($product_id)
    {
        $stocks = Stock::with('warehouse')
            ->where('product_id', $product_id)
            ->where('deleted_at', null)
            ->select();

        return $this->success($stocks);
    }

    /**
     * 更新库存数量
     */
    public function updateQuantity($id)
    {
        $quantity = (int)input('quantity', 0);
        $type = input('type', 'adjust'); // adjust:调整, in:入库, out:出库

        $stock = Stock::where('deleted_at', null)->find($id);
        if (!$stock) {
            return $this->error('库存记录不存在1');
        }

        try {
            Db::startTrans();

            $oldQuantity = $stock->quantity;

            // 根据类型更新库存
            switch ($type) {
                case 'in':
                    $stock->quantity += $quantity;
                    break;
                case 'out':
                    if ($stock->quantity < $quantity) {
                        throw new \Exception('库存不足');
                    }
                    $stock->quantity -= $quantity;
                    break;
                default:
                    $stock->quantity = $quantity;
            }

            // 更新总金额
            $stock->total_amount = bcmul($stock->quantity, $stock->cost_price, 2);
            $stock->save();

            // 记录库存流水（需要创建 stock_records 表）
            // $this->recordStockChange($stock, $oldQuantity, $type);

            Db::commit();

            return $this->success('更新成功', $stock);
        } catch (\Exception $e) {
            Db::rollback();
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取库存预警列表（简化版，用于仪表盘）
     */
    public function alerts()
    {
        $limit = (int)input('limit', 10);

        $lowStocks = Stock::with(['product', 'warehouse'])
            ->alias('s')
            ->join('products p', 'p.id = s.product_id')
            ->where('s.deleted_at', null)
            ->where('p.deleted_at', null)
            ->whereRaw('s.quantity <= p.min_stock')
            ->field('s.*, p.name as product_name, p.min_stock, p.max_stock')
            ->order('s.quantity', 'asc')
            ->limit($limit)
            ->select();

        return $this->success([
            'low_stocks' => $lowStocks,
            'total' => count($lowStocks)
        ]);
    }
    /**
     * 应用筛选条件
     */
    protected function applyFilters($query, $params)
    {
        // 关键词搜索
        if (!empty($params['keyword'])) {
            $query->where(function ($q) use ($params) {
                $q->whereLike('p.name', "%{$params['keyword']}%")
                    ->whereOrLike('p.product_no', "%{$params['keyword']}%")
                    ->whereOrLike('p.spec', "%{$params['keyword']}%");
            });
        }

        // 分类筛选
        if ($params['category_id'] > 0) {
            $query->where('p.category_id', $params['category_id']);
        }

        // 仓库筛选
        if ($params['warehouse_id'] > 0) {
            $query->where('s.warehouse_id', $params['warehouse_id']);
        }
    }

    /**
     * 格式化分页响应
     */
    protected function formatPaginateResponse($paginate)
    {
        return json([
            'code' => 200,
            'message' => 'success',
            'data' => [
                'list' => $paginate->items(),
                'total' => $paginate->total(),
                'page' => $paginate->currentPage(),
                'limit' => $paginate->listRows(),
                'pages' => $paginate->lastPage()
            ]
        ]);
    }

}
