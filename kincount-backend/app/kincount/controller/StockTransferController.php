<?php
declare (strict_types = 1);

namespace app\kincount\controller;

use app\kincount\model\StockTransfer;
use app\kincount\model\StockTransferItem;
use app\kincount\model\Stock;
use think\facade\Db;

class StockTransferController extends BaseController
{
    public function index()
    {
        $page   = (int)input('page', 1);
        $limit  = (int)input('limit', 15);
        $kw     = input('keyword', '');
        $fromId = (int)input('from_warehouse_id', 0);
        $toId   = (int)input('to_warehouse_id', 0);
        $status = input('status', '');
        $sDate  = input('start_date', '');
        $eDate  = input('end_date', '');

        $query = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'creator'])
                              ->where('deleted_at', null);

        if ($kw) $query->whereLike('transfer_no', "%{$kw}%");
        if ($fromId) $query->where('from_warehouse_id', $fromId);
        if ($toId) $query->where('to_warehouse_id', $toId);
        if ($status !== '') $query->where('status', $status);
        if ($sDate) $query->where('created_at', '>=', $sDate);
        if ($eDate) $query->where('created_at', '<=', $eDate . ' 23:59:59');

        return $this->paginate($query->order('id', 'desc')
                                   ->paginate(['list_rows' => $limit, 'page' => $page]));
    }

    public function read($id)
    {
        $transfer = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'creator', 'auditor'])
                                 ->where('deleted_at', null)->find($id);
        if (!$transfer) return $this->error('库存调拨单不存在');

        $transfer['items'] = StockTransferItem::with(['product.category'])
                                              ->where('stock_transfer_id', $id)->select();
        return $this->success($transfer);
    }

    public function save()
    {
        $post = input('post.');
        $validate = new \think\Validate([
            'from_warehouse_id' => 'require|integer',
            'to_warehouse_id'   => 'require|integer',
            'items'             => 'require|array|min:1'
        ]);
        if (!$validate->check($post)) return $this->error($validate->getError());
        if ($post['from_warehouse_id'] == $post['to_warehouse_id']) return $this->error('调出与调入仓库不能相同');

        $transfer = Db::transaction(function () use ($post) {
            $transferNo = $this->generateTransferNo('TR');
            $total      = 0;

            /* 主表 */
            $transfer = StockTransfer::create([
                'transfer_no'      => $transferNo,
                'from_warehouse_id'=> $post['from_warehouse_id'],
                'to_warehouse_id'  => $post['to_warehouse_id'],
                'status'           => 1,
                'remark'           => $post['remark'] ?? '',
                'created_by'       => $this->getUserId(),
            ]);

            /* 明细 + 库存校验 */
            foreach ($post['items'] as $v) {
                if (empty($v['product_id']) || empty($v['quantity'])) {
                    throw new \Exception('商品明细不完整');
                }
                $fromStock = Stock::where('product_id', $v['product_id'])
                                  ->where('warehouse_id', $post['from_warehouse_id'])->find();
                if (!$fromStock || $fromStock->quantity < $v['quantity']) {
                    $prod = \app\kincount\model\Product::find($v['product_id']);
                    throw new \Exception("商品 {$prod->name} 在调出仓库库存不足");
                }
                $cost   = $fromStock->cost_price;
                $rowTotal = $v['quantity'] * $cost;
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id'        => $v['product_id'],
                    'quantity'          => $v['quantity'],
                    'cost_price'        => $cost,
                    'total_amount'      => $rowTotal,
                ]);
                $total += $rowTotal;
            }

            $transfer->save(['total_amount' => $total]);
            return $transfer;
        });

        return $this->success(['id' => $transfer->id], '调拨单创建成功');
    }

    public function update($id)
    {
        $transfer = StockTransfer::where('deleted_at', null)->find($id);
        if (!$transfer) return $this->error('库存调拨单不存在');
        if ($transfer->status != 1) return $this->error('只有待调拨可修改');

        $post = input('post.');
        Db::transaction(function () use ($transfer, $post) {
            /* 基础信息 */
            $from = $post['from_warehouse_id'] ?? $transfer->from_warehouse_id;
            $to   = $post['to_warehouse_id'] ?? $transfer->to_warehouse_id;
            if ($from == $to) throw new \Exception('调出与调入仓库不能相同');
            $transfer->save([
                'from_warehouse_id' => $from,
                'to_warehouse_id'   => $to,
                'remark'            => $post['remark'] ?? $transfer->remark,
            ]);

            /* 若传了 items 则整体替换 */
            if (!empty($post['items']) && is_array($post['items'])) {
                StockTransferItem::where('stock_transfer_id', $transfer->id)->delete();
                $total = 0;
                foreach ($post['items'] as $v) {
                    $fromStock = Stock::where('product_id', $v['product_id'])
                                      ->where('warehouse_id', $from)->find();
                    if (!$fromStock || $fromStock->quantity < $v['quantity']) {
                        $prod = \app\kincount\model\Product::find($v['product_id']);
                        throw new \Exception("商品 {$prod->name} 在调出仓库库存不足");
                    }
                    $cost   = $fromStock->cost_price;
                    $rowTotal = $v['quantity'] * $cost;
                    StockTransferItem::create([
                        'stock_transfer_id' => $transfer->id,
                        'product_id'        => $v['product_id'],
                        'quantity'          => $v['quantity'],
                        'cost_price'        => $cost,
                        'total_amount'      => $rowTotal,
                    ]);
                    $total += $rowTotal;
                }
                $transfer->save(['total_amount' => $total]);
            }
        });

        return $this->success([], '调拨单更新成功');
    }

    public function delete($id)
    {
        $transfer = StockTransfer::where('deleted_at', null)->find($id);
        if (!$transfer) return $this->error('库存调拨单不存在');
        if ($transfer->status != 1) return $this->error('只有待调拨可删除');

        Db::transaction(function () use ($transfer) {
            StockTransferItem::where('stock_transfer_id', $transfer->id)->delete();
            $transfer->delete();
        });
        return $this->success([], '调拨单删除成功');
    }

    public function audit($id)
    {
        $transfer = StockTransfer::where('deleted_at', null)->find($id);
        if (!$transfer) return $this->error('库存调拨单不存在');
        if ($transfer->status != 1) return $this->error('当前状态无法审核');

        Db::transaction(function () use ($transfer) {
            /* 状态 */
            $transfer->save(['status' => 2, 'audit_by' => $this->getUserId(), 'audit_time' => date('Y-m-d H:i:s')]);

            /* 执行调拨：减调出 + 增调入 */
            foreach ($transfer->items as $item) {
                /* 减调出 */
                Stock::where('product_id', $item->product_id)
                     ->where('warehouse_id', $transfer->from_warehouse_id)
                     ->dec('quantity', $item->quantity)->save();

                /* 增调入 */
                $toStock = Stock::where('product_id', $item->product_id)
                                ->where('warehouse_id', $transfer->to_warehouse_id)->find();
                if ($toStock) {
                    $toStock->inc('quantity', $item->quantity)->save();
                } else {
                    Stock::create([
                        'product_id'   => $item->product_id,
                        'warehouse_id' => $transfer->to_warehouse_id,
                        'quantity'     => $item->quantity,
                        'cost_price'   => $item->cost_price,
                        'total_amount' => $item->total_amount,
                    ]);
                }
            }

            /* 自动完成 */
            $transfer->save(['status' => 3]);
        });

        return $this->success([], '审核成功');
    }

    public function complete($id)
    {
        $transfer = StockTransfer::where('deleted_at', null)->find($id);
        if (!$transfer) return $this->error('库存调拨单不存在');
        if ($transfer->status != 2) return $this->error('只有调拨中可完成');

        $transfer->save(['status' => 3]);
        return $this->success([], '调拨完成');
    }

    public function cancel($id)
    {
        $transfer = StockTransfer::where('deleted_at', null)->find($id);
        if (!$transfer) return $this->error('库存调拨单不存在');
        if (!in_array($transfer->status, [1, 2])) return $this->error('当前状态不能取消');

        $transfer->save(['status' => 4]);
        return $this->success([], '已取消');
    }

    public function items($id)
    {
        return $this->success(
            StockTransferItem::with(['product.category'])->where('stock_transfer_id', $id)->select()
        );
    }

    public function addItem($id)
    {
        $transfer = StockTransfer::where('deleted_at', null)->find($id);
        if (!$transfer) return $this->error('库存调拨单不存在');
        if ($transfer->status != 1) return $this->error('只有待调拨可添加明细');

        $post = input('post.');
        $validate = new \think\Validate([
            'product_id' => 'require|integer',
            'quantity'   => 'require|integer|gt:0'
        ]);
        if (!$validate->check($post)) return $this->error($validate->getError());

        Db::transaction(function () use ($transfer, $post) {
            $fromStock = Stock::where('product_id', $post['product_id'])
                              ->where('warehouse_id', $transfer->from_warehouse_id)->find();
            if (!$fromStock || $fromStock->quantity < $post['quantity']) {
                $prod = \app\kincount\model\Product::find($post['product_id']);
                throw new \Exception("商品 {$prod->name} 在调出仓库库存不足");
            }
            $cost   = $fromStock->cost_price;
            $rowTotal = $post['quantity'] * $cost;
            StockTransferItem::create([
                'stock_transfer_id' => $transfer->id,
                'product_id'        => $post['product_id'],
                'quantity'          => $post['quantity'],
                'cost_price'        => $cost,
                'total_amount'      => $rowTotal,
            ]);
            $transfer->inc('total_amount', $rowTotal)->save();
        });

        return $this->success([], '明细添加成功');
    }

    public function updateItem($id, $item_id)
    {
        $transfer = StockTransfer::where('deleted_at', null)->find($id);
        if (!$transfer) return $this->error('库存调拨单不存在');
        if ($transfer->status != 1) return $this->error('只有待调拨可修改明细');

        $item = StockTransferItem::where('stock_transfer_id', $id)->find($item_id);
        if (!$item) return $this->error('明细不存在');

        $post = input('post.');
        $oldTotal = $item->total_amount;
        $newQty   = $post['quantity'] ?? $item->quantity;
        $cost     = $item->cost_price;
        $newTotal = $newQty * $cost;

        Db::transaction(function () use ($item, $transfer, $oldTotal, $newQty, $newTotal, $post) {
            if ($newQty != $item->quantity) {
                $fromStock = Stock::where('product_id', $item->product_id)
                                  ->where('warehouse_id', $transfer->from_warehouse_id)->find();
                if (!$fromStock || $fromStock->quantity < $newQty) {
                    $prod = \app\kincount\model\Product::find($item->product_id);
                    throw new \Exception("商品 {$prod->name} 在调出仓库库存不足");
                }
            }
            $item->save(['quantity' => $newQty, 'total_amount' => $newTotal]);
            $transfer->inc('total_amount', $newTotal - $oldTotal)->save();
        });

        return $this->success([], '明细更新成功');
    }

    public function deleteItem($id, $item_id)
    {
        $transfer = StockTransfer::where('deleted_at', null)->find($id);
        if (!$transfer) return $this->error('库存调拨单不存在');
        if ($transfer->status != 1) return $this->error('只有待调拨可删除明细');

        $item = StockTransferItem::where('stock_transfer_id', $id)->find($item_id);
        if (!$item) return $this->error('明细不存在');

        Db::transaction(function () use ($item, $transfer) {
            $transfer->dec('total_amount', $item->total_amount)->save();
            $item->delete();
        });

        return $this->success([], '明细删除成功');
    }

    private function generateTransferNo($prefix = 'TR')
    {
        $date = date('Ymd');
        $num  = StockTransfer::whereLike('transfer_no', $prefix . $date . '%')->count() + 1;
        return $prefix . $date . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
    }
}