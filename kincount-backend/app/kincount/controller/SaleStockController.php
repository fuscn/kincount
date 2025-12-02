<?php

declare(strict_types=1);

namespace app\kincount\controller;

use app\kincount\model\SaleStock;
use app\kincount\model\SaleStockItem;
use app\kincount\model\Stock;
use think\facade\Db;

class SaleStockController extends BaseController
{
    public function index()
    {
        $page  = (int)input('page', 1);
        $limit = (int)input('limit', 15);
        $kw    = input('keyword', '');
        $cusId = (int)input('customer_id', 0);
        $status = input('status', '');
        $sDate = input('start_date', '');
        $eDate = input('end_date', '');

        $query = SaleStock::with(['customer', 'warehouse', 'creator'])
            ->where('deleted_at', null);

        if ($kw) $query->whereLike('stock_no', "%{$kw}%");
        if ($cusId) $query->where('customer_id', $cusId);
        if ($status !== '') $query->where('status', $status);
        if ($sDate) $query->where('created_at', '>=', $sDate);
        if ($eDate) $query->where('created_at', '<=', $eDate . ' 23:59:59');

        return $this->paginate($query->order('id', 'desc')
            ->paginate(['list_rows' => $limit, 'page' => $page]));
    }

    public function read($id)
    {
        $stock = SaleStock::with(['customer', 'warehouse', 'creator', 'auditor'])
            ->where('deleted_at', null)->find($id);
        if (!$stock) return $this->error('销售出库单不存在');

        $stock['items'] = SaleStockItem::with(['product.category'])
            ->where('sale_stock_id', $id)->select();
        return $this->success($stock);
    }
    public function save()
    {
        $post = input('post.');
        $validate = new \think\Validate([
            'customer_id'  => 'require|integer',
            'warehouse_id' => 'require|integer',
            'items'        => 'require|array|min:1'
        ]);
        if (!$validate->check($post)) return $this->error($validate->getError());

        try {
            $stock = Db::transaction(function () use ($post) {
                $stockNo = $this->generateStockNo('SS');
                $total   = 0;

                /* 主表 */
                $stock = SaleStock::create([
                    'stock_no'      => $stockNo,
                    'sale_order_id' => $post['sale_order_id'] ?? 0,
                    'customer_id'   => $post['customer_id'],
                    'warehouse_id'  => $post['warehouse_id'],
                    'status'        => 1,
                    'remark'        => $post['remark'] ?? '',
                    'created_by'    => $this->getUserId(),
                ]);

                /* 明细 + 库存扣减（修改为SKU维度） */
                foreach ($post['items'] as $v) {
                    if (empty($v['sku_id']) || empty($v['quantity']) || empty($v['price'])) {
                        throw new \Exception('商品明细不完整');
                    }

                    // 通过sku_id获取product_id
                    $sku = \app\kincount\model\ProductSku::find($v['sku_id']);
                    if (!$sku) {
                        throw new \Exception('SKU不存在');
                    }

                    // ========== 新增：检查销售订单剩余数量 ==========
                    if (!empty($post['sale_order_id'])) {
                        // 查找销售订单中对应的商品明细
                        $orderItem = \app\kincount\model\SaleOrderItem::where('sale_order_id', $post['sale_order_id'])
                            ->where('sku_id', $v['sku_id'])
                            ->find();

                        if (!$orderItem) {
                            throw new \Exception("商品 {$sku->sku_code} 不在销售订单中");
                        }

                        // 计算剩余可出库数量
                        $remainingQuantity = $orderItem->quantity - $orderItem->delivered_quantity;

                        // 计算已创建但未审核的出库单数量
                        $pendingOutbound = \app\kincount\model\SaleStockItem::alias('i')
                            ->join('sale_stocks s', 's.id = i.sale_stock_id')
                            ->where('s.sale_order_id', $post['sale_order_id'])
                            ->where('s.status', 1)  // 待审核状态
                            ->where('i.sku_id', $v['sku_id'])
                            ->sum('i.quantity');

                        // 真正的剩余数量 = 订单总数量 - 已出库数量 - 待审核出库数量
                        $realRemaining = $remainingQuantity - $pendingOutbound;

                        if ($v['quantity'] > $realRemaining) {
                            $message = "商品 {$sku->sku_code} 出库数量 {$v['quantity']} 超过订单剩余数量";
                            if ($realRemaining < 0) {
                                $message .= "（已有 {$pendingOutbound} 个在待审核出库单中）";
                            }
                            throw new \Exception($message);
                        }
                    }
                    // ========== 新增结束 ==========

                    // 库存检查（SKU维度）
                    $whStock = Stock::where('sku_id', $v['sku_id'])
                        ->where('warehouse_id', $post['warehouse_id'])->find();
                    if (!$whStock || $whStock->quantity < $v['quantity']) {
                        $prod = \app\kincount\model\Product::find($sku->product_id);
                        $stockMsg = $whStock ? "当前库存：{$whStock->quantity}" : "无库存";
                        throw new \Exception("商品 {$prod->name} (SKU: {$sku->sku_code}) 库存不足，{$stockMsg}");
                    }

                    $rowTotal = $v['quantity'] * $v['price'];
                    SaleStockItem::create([
                        'sale_stock_id' => $stock->id,
                        'product_id'    => $sku->product_id,
                        'sku_id'        => $v['sku_id'],
                        'quantity'      => $v['quantity'],
                        'price'         => $v['price'],
                        'total_amount'  => $rowTotal,
                    ]);
                    $total += $rowTotal;
                }

                $stock->save(['total_amount' => $total]);
                return $stock;
            });

            return $this->success(['id' => $stock->id], '销售出库单创建成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function audit($id)
    {
        $stock = SaleStock::where('deleted_at', null)->find($id);
        if (!$stock) return $this->error('销售出库单不存在');
        if ($stock->status != 1) return $this->error('当前状态无法审核');

        Db::transaction(function () use ($stock) {
            /* 状态 */
            $stock->save(['status' => 2, 'audit_by' => $this->getUserId(), 'audit_time' => date('Y-m-d H:i:s')]);

            /* 库存扣减 + 回写销售订单已出库数量（SKU维度） */
            foreach ($stock->items as $item) {
                // 修改为 SKU 维度
                Stock::where('sku_id', $item->sku_id)
                    ->where('warehouse_id', $stock->warehouse_id)
                    ->dec('quantity', $item->quantity)->save();

                if ($stock->sale_order_id) {
                    \app\kincount\model\SaleOrderItem::where('sale_order_id', $stock->sale_order_id)
                        ->where('product_id', $item->product_id)
                        ->where('sku_id', $item->sku_id) // 添加 sku_id 条件
                        ->inc('delivered_quantity', $item->quantity)->save();
                }
            }
        });

        return $this->success([], '审核成功');
    }
    /**
     * 完成销售出库单
     */
    public function complete($id)
    {
        $stock = SaleStock::where('deleted_at', null)->find($id);
        if (!$stock) return $this->error('销售出库单不存在');

        // 只有已审核的出库单才能完成
        if ($stock->status != 2) {
            return $this->error('只有已审核的出库单才能完成');
        }

        try {
            Db::transaction(function () use ($stock) {
                // 更新状态为已完成 (3)
                $stock->save([
                    'status' => 3,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                // 这里可以添加其他完成操作，比如：
                // 1. 生成财务记录
                // 2. 更新客户欠款
                // 3. 发送通知等

                // 示例：更新客户欠款（如果业务需要）
                // if ($stock->customer_id) {
                //     $customer = \app\kincount\model\Customer::find($stock->customer_id);
                //     if ($customer) {
                //         $customer->arrears_amount += $stock->total_amount;
                //         $customer->save();
                //     }
                // }

            });

            return $this->success([], '出库单已完成');
        } catch (\Exception $e) {
            return $this->error('完成操作失败: ' . $e->getMessage());
        }
    }

    /**
     * 取消销售出库单
     */
    public function cancel($id)
    {
        $stock = SaleStock::where('deleted_at', null)->find($id);
        if (!$stock) return $this->error('销售出库单不存在');

        if (!in_array($stock->status, [1, 2])) {
            return $this->error('当前状态无法取消');
        }

        Db::transaction(function () use ($stock) {
            // 如果已经审核过，需要恢复库存
            if ($stock->status == 2) {
                foreach ($stock->items as $item) {
                    // 恢复库存（SKU维度）
                    Stock::where('sku_id', $item->sku_id)
                        ->where('warehouse_id', $stock->warehouse_id)
                        ->inc('quantity', $item->quantity)->save();

                    // 恢复销售订单的出库数量
                    if ($stock->sale_order_id) {
                        \app\kincount\model\SaleOrderItem::where('sale_order_id', $stock->sale_order_id)
                            ->where('product_id', $item->product_id)
                            ->where('sku_id', $item->sku_id) // 添加 sku_id 条件
                            ->dec('delivered_quantity', $item->quantity)->save();
                    }
                }
            }

            // 更新状态为已取消 (4)
            $stock->save(['status' => 4, 'updated_at' => date('Y-m-d H:i:s')]);
        });

        return $this->success([], '取消成功');
    }
    private function generateStockNo($prefix = 'SS')
    {
        $date = date('Ymd');
        $num  = SaleStock::whereLike('stock_no', $prefix . $date . '%')->count() + 1;
        return $prefix . $date . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
    }
}
