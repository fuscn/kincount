<?php
declare (strict_types = 1);

namespace app\kincount\controller;

use app\kincount\model\StockTake;
use app\kincount\model\StockTakeItem;
use app\kincount\model\Stock;
use think\facade\Db;

class StockTakeController extends BaseController
{
    public function index()
    {
        $page  = (int)input('page', 1);
        $limit = (int)input('limit', 15);
        $kw    = input('keyword', '');
        $whId  = (int)input('warehouse_id', 0);
        $status= input('status', '');
        $sDate = input('start_date', '');
        $eDate = input('end_date', '');

        $query = StockTake::with(['warehouse', 'creator'])
                          ->where('deleted_at', null);

        if ($kw) $query->whereLike('take_no', "%{$kw}%");
        if ($whId) $query->where('warehouse_id', $whId);
        if ($status !== '') $query->where('status', $status);
        if ($sDate) $query->where('created_at', '>=', $sDate);
        if ($eDate) $query->where('created_at', '<=', $eDate . ' 23:59:59');

        return $this->paginate($query->order('id', 'desc')
                                   ->paginate(['list_rows' => $limit, 'page' => $page]));
    }

    public function read($id)
    {
        $take = StockTake::with(['warehouse', 'creator', 'auditor'])
                         ->where('deleted_at', null)->find($id);
        if (!$take) return $this->error('盘点单不存在');

        $take['items'] = StockTakeItem::with(['product.category'])
                                      ->where('stock_take_id', $id)->select();
        return $this->success($take);
    }

    public function save()
    {
        $post = input('post.');
        $validate = new \think\Validate(['warehouse_id' => 'require|integer']);
        if (!$validate->check($post)) return $this->error($validate->getError());

        $take = Db::transaction(function () use ($post) {
            $takeNo = $this->generateTakeNo('ST');
            $total  = 0;

            /* 主表 */
            $take = StockTake::create([
                'take_no'     => $takeNo,
                'warehouse_id'=> $post['warehouse_id'],
                'status'      => 1,
                'remark'      => $post['remark'] ?? '',
                'created_by'  => $this->getUserId(),
            ]);

            /* 明细 */
            if (!empty($post['items']) && is_array($post['items'])) {
                foreach ($post['items'] as $v) {
                    if (empty($v['product_id']) || !isset($v['actual_quantity'])) {
                        throw new \Exception('盘点明细不完整');
                    }
                    $whStock = Stock::where('product_id', $v['product_id'])
                                    ->where('warehouse_id', $post['warehouse_id'])->find();
                    $sysQty  = $whStock ? $whStock->quantity : 0;
                    $cost    = $whStock ? $whStock->cost_price : 0;
                    $diffQty = $v['actual_quantity'] - $sysQty;
                    $diffAmt = $diffQty * $cost;

                    StockTakeItem::create([
                        'stock_take_id'    => $take->id,
                        'product_id'       => $v['product_id'],
                        'system_quantity'  => $sysQty,
                        'actual_quantity'  => $v['actual_quantity'],
                        'difference_quantity'=> $diffQty,
                        'cost_price'       => $cost,
                        'difference_amount'=> $diffAmt,
                        'reason'           => $v['reason'] ?? '',
                    ]);
                    $total += $diffAmt;
                }
                $take->save(['total_difference' => $total]);
            }

            return $take;
        });

        return $this->success(['id' => $take->id], '盘点单创建成功');
    }

    public function update($id)
    {
        $take = StockTake::where('deleted_at', null)->find($id);
        if (!$take) return $this->error('盘点单不存在');
        if ($take->status != 1) return $this->error('只有盘点中可修改');

        $post = input('post.');
        Db::transaction(function () use ($take, $post) {
            /* 基础信息 */
            $take->save([
                'warehouse_id' => $post['warehouse_id'] ?? $take->warehouse_id,
                'remark'       => $post['remark'] ?? $take->remark,
            ]);

            /* 若传了 items 则整体替换 */
            if (!empty($post['items']) && is_array($post['items'])) {
                StockTakeItem::where('stock_take_id', $take->id)->delete();
                $total = 0;
                foreach ($post['items'] as $v) {
                    $whStock = Stock::where('product_id', $v['product_id'])
                                    ->where('warehouse_id', $post['warehouse_id'] ?? $take->warehouse_id)->find();
                    $sysQty  = $whStock ? $whStock->quantity : 0;
                    $cost    = $whStock ? $whStock->cost_price : 0;
                    $diffQty = $v['actual_quantity'] - $sysQty;
                    $diffAmt = $diffQty * $cost;

                    StockTakeItem::create([
                        'stock_take_id'    => $take->id,
                        'product_id'       => $v['product_id'],
                        'system_quantity'  => $sysQty,
                        'actual_quantity'  => $v['actual_quantity'],
                        'difference_quantity'=> $diffQty,
                        'cost_price'       => $cost,
                        'difference_amount'=> $diffAmt,
                        'reason'           => $v['reason'] ?? '',
                    ]);
                    $total += $diffAmt;
                }
                $take->save(['total_difference' => $total]);
            }
        });

        return $this->success([], '盘点单更新成功');
    }

    public function delete($id)
    {
        $take = StockTake::where('deleted_at', null)->find($id);
        if (!$take) return $this->error('盘点单不存在');
        if ($take->status != 1) return $this->error('只有盘点中可删除');

        Db::transaction(function () use ($take) {
            StockTakeItem::where('stock_take_id', $take->id)->delete();
            $take->delete();
        });
        return $this->success([], '盘点单删除成功');
    }

    public function audit($id)
    {
        $take = StockTake::where('deleted_at', null)->find($id);
        if (!$take) return $this->error('盘点单不存在');
        if ($take->status != 1) return $this->error('当前状态无法审核');

        Db::transaction(function () use ($take) {
            /* 状态 */
            $take->save(['status' => 2, 'audit_by' => $this->getUserId(), 'audit_time' => date('Y-m-d H:i:s')]);

            /* 库存修正 */
            foreach ($take->items as $v) {
                Stock::where('product_id', $v->product_id)
                     ->where('warehouse_id', $take->warehouse_id)
                     ->update(['quantity' => $v->actual_quantity]);
            }
        });

        return $this->success([], '审核成功');
    }

    public function complete($id)
    {
        $take = StockTake::where('deleted_at', null)->find($id);
        if (!$take) return $this->error('盘点单不存在');
        if ($take->status != 1) return $this->error('只有盘点中可完成');

        $take->save(['status' => 2]);
        return $this->success([], '盘点完成');
    }

    public function cancel($id)
    {
        $take = StockTake::where('deleted_at', null)->find($id);
        if (!$take) return $this->error('盘点单不存在');
        if ($take->status != 1) return $this->error('当前状态不能取消');

        $take->save(['status' => 3]);
        return $this->success([], '已取消');
    }

    public function items($id)
    {
        return $this->success(
            StockTakeItem::with(['product.category'])->where('stock_take_id', $id)->select()
        );
    }

    public function addItem($id)
    {
        $take = StockTake::where('deleted_at', null)->find($id);
        if (!$take) return $this->error('盘点单不存在');
        if ($take->status != 1) return $this->error('只有盘点中可添加明细');

        $post = input('post.');
        $validate = new \think\Validate([
            'product_id'      => 'require|integer',
            'actual_quantity' => 'require|integer'
        ]);
        if (!$validate->check($post)) return $this->error($validate->getError());

        Db::transaction(function () use ($take, $post) {
            $whStock = Stock::where('product_id', $post['product_id'])
                            ->where('warehouse_id', $take->warehouse_id)->find();
            $sysQty  = $whStock ? $whStock->quantity : 0;
            $cost    = $whStock ? $whStock->cost_price : 0;
            $diffQty = $post['actual_quantity'] - $sysQty;
            $diffAmt = $diffQty * $cost;

            StockTakeItem::create([
                'stock_take_id'    => $take->id,
                'product_id'       => $post['product_id'],
                'system_quantity'  => $sysQty,
                'actual_quantity'  => $post['actual_quantity'],
                'difference_quantity'=> $diffQty,
                'cost_price'       => $cost,
                'difference_amount'=> $diffAmt,
                'reason'           => $post['reason'] ?? '',
            ]);
            $take->inc('total_difference', $diffAmt)->save();
        });

        return $this->success([], '明细添加成功');
    }

    public function updateItem($id, $item_id)
    {
        $take = StockTake::where('deleted_at', null)->find($id);
        if (!$take) return $this->error('盘点单不存在');
        if ($take->status != 1) return $this->error('只有盘点中可修改明细');

        $item = StockTakeItem::where('stock_take_id', $id)->find($item_id);
        if (!$item) return $this->error('明细不存在');

        $post = input('post.');
        $oldAmt = $item->difference_amount;

        Db::transaction(function () use ($item, $take, $oldAmt, $post) {
            $whStock = Stock::where('product_id', $item->product_id)
                            ->where('warehouse_id', $take->warehouse_id)->find();
            $sysQty  = $whStock ? $whStock->quantity : 0;
            $cost    = $whStock ? $whStock->cost_price : 0;
            $newAct  = $post['actual_quantity'] ?? $item->actual_quantity;
            $newDiff = $newAct - $sysQty;
            $newAmt  = $newDiff * $cost;

            $item->save([
                'system_quantity'   => $sysQty,
                'actual_quantity'   => $newAct,
                'difference_quantity'=> $newDiff,
                'cost_price'        => $cost,
                'difference_amount' => $newAmt,
                'reason'            => $post['reason'] ?? $item->reason,
            ]);
            $take->inc('total_difference', $newAmt - $oldAmt)->save();
        });

        return $this->success([], '明细更新成功');
    }

    public function deleteItem($id, $item_id)
    {
        $take = StockTake::where('deleted_at', null)->find($id);
        if (!$take) return $this->error('盘点单不存在');
        if ($take->status != 1) return $this->error('只有盘点中可删除明细');

        $item = StockTakeItem::where('stock_take_id', $id)->find($item_id);
        if (!$item) return $this->error('明细不存在');

        Db::transaction(function () use ($item, $take) {
            $take->dec('total_difference', $item->difference_amount)->save();
            $item->delete();
        });

        return $this->success([], '明细删除成功');
    }

    private function generateTakeNo($prefix = 'ST')
    {
        $date = date('Ymd');
        $num  = StockTake::whereLike('take_no', $prefix . $date . '%')->count() + 1;
        return $prefix . $date . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
    }
}