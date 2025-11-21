<?php
declare (strict_types = 1);

namespace app\kincount\controller;

use app\kincount\model\PurchaseStock;
use app\kincount\model\PurchaseStockItem;
use app\kincount\model\Stock;
use think\facade\Db;

class PurchaseStockController extends BaseController
{
    public function index()
    {
        $page  = (int)input('page', 1);
        $limit = (int)input('limit', 15);
        $kw    = input('keyword', '');
        $supId = (int)input('supplier_id', 0);
        $status= input('status', '');
        $sDate = input('start_date', '');
        $eDate = input('end_date', '');

        $query = PurchaseStock::with(['supplier', 'warehouse', 'creator'])
                              ->where('deleted_at', null);

        if ($kw) $query->whereLike('stock_no', "%{$kw}%");
        if ($supId) $query->where('supplier_id', $supId);
        if ($status !== '') $query->where('status', $status);
        if ($sDate) $query->where('created_at', '>=', $sDate);
        if ($eDate) $query->where('created_at', '<=', $eDate . ' 23:59:59');

        return $this->paginate($query->order('id', 'desc')
                                   ->paginate(['list_rows' => $limit, 'page' => $page]));
    }

    public function read($id)
    {
        $stock = PurchaseStock::with(['supplier', 'warehouse', 'creator', 'auditor'])
                              ->where('deleted_at', null)->find($id);
        if (!$stock) return $this->error('采购入库单不存在');

        $stock['items'] = PurchaseStockItem::with(['product.category'])
                                           ->where('purchase_stock_id', $id)->select();
        return $this->success($stock);
    }

    public function save()
    {
        $post = input('post.');
        $validate = new \think\Validate([
            'supplier_id'  => 'require|integer',
            'warehouse_id' => 'require|integer',
            'items'        => 'require|array|min:1'
        ]);
        if (!$validate->check($post)) return $this->error($validate->getError());

        $stock = Db::transaction(function () use ($post) {
            $stockNo = $this->generateStockNo('PS');
            $total   = 0;

            /* 主表 */
            $stock = PurchaseStock::create([
                'stock_no'     => $stockNo,
                'purchase_order_id' => $post['purchase_order_id'] ?? 0,
                'supplier_id'  => $post['supplier_id'],
                'warehouse_id' => $post['warehouse_id'],
                'status'       => 1,
                'remark'       => $post['remark'] ?? '',
                'created_by'   => $this->getUserId(),
            ]);

            /* 明细 */
            foreach ($post['items'] as $v) {
                if (empty($v['product_id']) || empty($v['quantity']) || empty($v['price'])) {
                    throw new \Exception('商品明细不完整');
                }
                $rowTotal = $v['quantity'] * $v['price'];
                PurchaseStockItem::create([
                    'purchase_stock_id' => $stock->id,
                    'product_id'        => $v['product_id'],
                    'quantity'          => $v['quantity'],
                    'price'             => $v['price'],
                    'total_amount'      => $rowTotal,
                ]);
                $total += $rowTotal;
            }

            $stock->save(['total_amount' => $total]);
            return $stock;
        });

        return $this->success(['id' => $stock->id], '采购入库单创建成功');
    }

    public function audit($id)
    {
        $stock = PurchaseStock::where('deleted_at', null)->find($id);
        if (!$stock) return $this->error('采购入库单不存在');
        if ($stock->status != 1) return $this->error('当前状态无法审核');

        Db::transaction(function () use ($stock) {
            /* 状态 */
            $stock->save(['status' => 2, 'audit_by' => $this->getUserId(), 'audit_time' => date('Y-m-d H:i:s')]);

            /* 库存增加 */
            foreach ($stock->items as $item) {
                $whStock = Stock::where('product_id', $item->product_id)
                                ->where('warehouse_id', $stock->warehouse_id)->find();
                if ($whStock) {
                    $whStock->inc('quantity', $item->quantity)->save();
                } else {
                    Stock::create([
                        'product_id'   => $item->product_id,
                        'warehouse_id' => $stock->warehouse_id,
                        'quantity'     => $item->quantity,
                        'cost_price'   => $item->price,
                        'total_amount' => $item->total_amount,
                    ]);
                }

                /* 回写采购订单已入库数量 */
                if ($stock->purchase_order_id) {
                    \app\kincount\model\PurchaseOrderItem::where('purchase_order_id', $stock->purchase_order_id)
                        ->where('product_id', $item->product_id)
                        ->inc('received_quantity', $item->quantity)->save();
                }
            }
        });

        return $this->success([], '审核成功');
    }

    private function generateStockNo($prefix = 'PS')
    {
        $date = date('Ymd');
        $num  = PurchaseStock::whereLike('stock_no', $prefix . $date . '%')->count() + 1;
        return $prefix . $date . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
    }
}