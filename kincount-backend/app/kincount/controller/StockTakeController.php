<?php
declare (strict_types = 1);

namespace app\kincount\controller;

use app\kincount\model\StockTake;
use app\kincount\model\StockTakeItem;
use app\kincount\model\Stock;
use think\facade\Db;

class StockTakeController extends BaseController
{
    /**
     * 检查同一仓库同一SKU是否有待盘点或盘点中的数据
     * @param int $warehouseId 仓库ID
     * @param array $skuIds SKU ID数组
     * @param int|null $excludeTakeId 排除的盘点单ID（用于更新时）
     * @return array 冲突的SKU信息
     */
    private function checkExistingStockTakes($warehouseId, $skuIds, $excludeTakeId = null)
    {
        if (empty($skuIds)) {
            return [];
        }
        
        // 查询待盘点(status=0)和盘点中(status=1)的盘点单
        $query = StockTake::where('warehouse_id', $warehouseId)
                         ->where('deleted_at', null)
                         ->where('status', 'in', [0, 1]);
        
        // 如果是更新操作，排除当前的盘点单
        if ($excludeTakeId) {
            $query->where('id', '<>', $excludeTakeId);
        }
        
        $existingTakes = $query->column('id');
        
        if (empty($existingTakes)) {
            return [];
        }
        
        // 查询这些盘点单中是否包含指定的SKU
        $conflictItems = StockTakeItem::whereIn('stock_take_id', $existingTakes)
                                     ->whereIn('sku_id', $skuIds)
                                     ->alias('sti')
                                     ->join('stock_takes st', 'sti.stock_take_id = st.id')
                                     ->field('sti.sku_id, st.take_no, st.status, sti.product_id')
                                     ->select()
                                     ->toArray();
        
        return $conflictItems;
    }

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

        $take['items'] = StockTakeItem::with(['product.category', 'sku'])
                                      ->where('stock_take_id', $id)->select();
        return $this->success($take);
    }

    public function save()
    {
        $post = input('post.');
        $validate = new \think\Validate(['warehouse_id' => 'require|integer']);
        if (!$validate->check($post)) return $this->error($validate->getError());

        // 检查是否有冲突的SKU
        if (!empty($post['items']) && is_array($post['items'])) {
            $skuIds = array_column($post['items'], 'sku_id');
            $conflicts = $this->checkExistingStockTakes($post['warehouse_id'], $skuIds);
            
            if (!empty($conflicts)) {
                $conflictSkus = [];
                $conflictTakes = [];
                foreach ($conflicts as $conflict) {
                    $conflictSkus[] = "SKU ID: {$conflict['sku_id']}";
                    $conflictTakes[] = "盘点单: {$conflict['take_no']}";
                }
                $errorMsg = "以下SKU已在其他盘点单中使用：" . implode(', ', array_unique($conflictSkus)) . 
                            "。相关盘点单：" . implode(', ', array_unique($conflictTakes));
                return $this->error($errorMsg);
            }
        }

        $take = Db::transaction(function () use ($post) {
            $takeNo = $this->generateTakeNo('ST');
            $total  = 0;

            /* 主表 */
            $take = StockTake::create([
                'take_no'     => $takeNo,
                'warehouse_id'=> $post['warehouse_id'],
                'status'      => 0,
                'remark'      => $post['remark'] ?? '',
                'created_by'  => $this->getUserId(),
            ]);

            /* 明细 */
            if (!empty($post['items']) && is_array($post['items'])) {
                foreach ($post['items'] as $v) {
                    if (empty($v['sku_id']) || !isset($v['actual_quantity'])) {
                        throw new \Exception('盘点明细不完整');
                    }
                    $whStock = Stock::where('sku_id', $v['sku_id'])
                                    ->where('warehouse_id', $post['warehouse_id'])->find();
                    $sysQty  = $whStock ? $whStock->quantity : 0;
                    $cost    = $whStock ? $whStock->cost_price : 0;
                    $diffQty = $v['actual_quantity'] - $sysQty;
                    $diffAmt = $diffQty * $cost;

                    StockTakeItem::create([
                        'stock_take_id'    => $take->id,
                        'product_id'       => $v['product_id'],
                        'sku_id'           => $v['sku_id'],
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

    /**
     * 更新盘点单
     */
    public function update($id)
    {
        $take = StockTake::where('deleted_at', null)->find($id);
        if (!$take) return $this->error('盘点单不存在');
        // 已审核、已完成或已取消的盘点单不能修改
        if ($take->status >= 2) {
            return $this->error('已审核、已完成或已取消的盘点单不能修改');
        }

        $post = input('post.');
        
        // 检查是否有冲突的SKU
        if (!empty($post['items']) && is_array($post['items'])) {
            $skuIds = array_column($post['items'], 'sku_id');
            $warehouseId = $post['warehouse_id'] ?? $take->warehouse_id;
            $conflicts = $this->checkExistingStockTakes($warehouseId, $skuIds, $id);
            
            if (!empty($conflicts)) {
                $conflictSkus = [];
                $conflictTakes = [];
                foreach ($conflicts as $conflict) {
                    $conflictSkus[] = "SKU ID: {$conflict['sku_id']}";
                    $conflictTakes[] = "盘点单: {$conflict['take_no']}";
                }
                $errorMsg = "以下SKU已在其他盘点单中使用：" . implode(', ', array_unique($conflictSkus)) . 
                            "。相关盘点单：" . implode(', ', array_unique($conflictTakes));
                return $this->error($errorMsg);
            }
        }
        
        Db::transaction(function () use ($take, $post) {
            /* 基础信息 */
            $take->save([
                'warehouse_id' => $post['warehouse_id'] ?? $take->warehouse_id,
                'remark'       => $post['remark'] ?? $take->remark,
            ]);

            /* 若传了 items 则整体替换 */
            if (!empty($post['items']) && is_array($post['items'])) {
                // 使用硬删除，直接从数据库中删除所有明细记录
                // 使用Db表操作，绕过模型的软删除机制
                Db::table('stock_take_items')->where('stock_take_id', $take->id)->delete();
                
                $total = 0;
                foreach ($post['items'] as $v) {
                    $whStock = Stock::where('sku_id', $v['sku_id'])
                                    ->where('warehouse_id', $post['warehouse_id'] ?? $take->warehouse_id)->find();
                    $sysQty  = $whStock ? $whStock->quantity : 0;
                    $cost    = $whStock ? $whStock->cost_price : 0;
                    $diffQty = $v['actual_quantity'] - $sysQty;
                    $diffAmt = $diffQty * $cost;

                    StockTakeItem::create([
                        'stock_take_id'    => $take->id,
                        'product_id'       => $v['product_id'],
                        'sku_id'           => $v['sku_id'],
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

    /**
     * 删除盘点单
     */
    public function delete($id)
    {
        $take = StockTake::where('deleted_at', null)->find($id);
        if (!$take) return $this->error('盘点单不存在');
        // 已审核、已完成或已取消的盘点单不能删除
        if ($take->status >= 2) {
            return $this->error('已审核、已完成或已取消的盘点单不能删除');
        }

        Db::transaction(function () use ($take) {
            // 使用直接更新方式执行软删除（避免多条数据时delete()报错）
            Db::table('stock_take_items')->where('stock_take_id', $take->id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
            $take->update(['deleted_at' => date('Y-m-d H:i:s')]);
        });
        return $this->success([], '盘点单删除成功');
    }

    public function audit($id)
    {
        $take = StockTake::where('deleted_at', null)->find($id);
        if (!$take) return $this->error('盘点单不存在');
        // 检查状态：已审核、已完成、已取消的不允许操作
        if (in_array($take->status, [2, 3, 4])) {
            return $this->error('该盘点单已审核，不允许重复审核');
        }

        // 检查是否有盘点明细
        $hasItems = StockTakeItem::where('stock_take_id', $id)->count() > 0;
        if (!$hasItems) {
            return $this->error('请先添加盘点明细');
        }

        Db::transaction(function () use ($take, $id) {
            /* 状态设置为盘点中(1) */
            $take->save(['status' => 1, 'audit_by' => $this->getUserId(), 'audit_time' => date('Y-m-d H:i:s')]);
            // 注意：盘点开始时不应该立即修正库存，库存修正应该在完成盘点时进行
        });

        return $this->success([], '盘点开始');
    }

    public function complete($id)
    {
        $take = StockTake::where('deleted_at', null)->find($id);
        if (!$take) return $this->error('盘点单不存在');
        // 检查状态：只有盘点中(1)的才允许完成
        if (!in_array($take->status, [1])) {
            return $this->error('只有盘点中的单才允许完成');
        }

        Db::transaction(function () use ($take, $id) {
            /* 状态设置为已完成(2) */
            $take->save(['status' => 2]);

            /* 库存修正 - 在完成盘点时执行 */
            // 直接查询明细，不使用关联关系，避免可能的 exists() 方法调用
            $items = StockTakeItem::where('stock_take_id', $id)->select();
            foreach ($items as $v) {
                $stock = Stock::where('sku_id', $v->sku_id)
                              ->where('warehouse_id', $take->warehouse_id)
                              ->find();
                if ($stock) {
                    // 如果库存记录存在，更新数量
                    $stock->save(['quantity' => $v->actual_quantity]);
                } else {
                    // 如果库存记录不存在，创建新记录
                    Stock::create([
                        'sku_id'       => $v->sku_id,
                        'warehouse_id' => $take->warehouse_id,
                        'quantity'     => $v->actual_quantity,
                        'cost_price'   => $v->cost_price,
                        'total_amount' => $v->actual_quantity * $v->cost_price
                    ]);
                }
            }
        });

        return $this->success([], '盘点完成');
    }

    /**
     * 取消盘点
     */
    public function cancel($id)
    {
        $take = StockTake::where('deleted_at', null)->find($id);
        if (!$take) return $this->error('盘点单不存在');
        // 已审核、已完成或已取消的盘点单不能取消
        if ($take->status >= 2) {
            return $this->error('已审核、已完成或已取消的盘点单不能取消');
        }

        $take->save(['status' => 4]); // 修改为状态4（已取消）
        return $this->success([], '盘点单取消成功');
    }

    public function items($id)
    {
        return $this->success(
            StockTakeItem::with(['product.category', 'sku'])->where('stock_take_id', $id)->select()
        );
    }

    public function addItem($id)
    {
        $take = StockTake::where('deleted_at', null)->find($id);
        if (!$take) return $this->error('盘点单不存在');
        // 检查状态：已审核、已完成、已取消的不允许操作
        if (in_array($take->status, [2, 3, 4])) {
            return $this->error('该盘点单已审核，不允许添加商品');
        }

        $post = input('post.');
        $validate = new \think\Validate([
            'product_id'      => 'require|integer',
            'sku_id'          => 'require|integer',
            'actual_quantity' => 'require|integer'
        ]);
        if (!$validate->check($post)) return $this->error($validate->getError());

        // 检查SKU是否已在其他盘点单中使用
        $conflicts = $this->checkExistingStockTakes($take->warehouse_id, [$post['sku_id']], $id);
        if (!empty($conflicts)) {
            $conflict = $conflicts[0];
            return $this->error("SKU ID: {$conflict['sku_id']} 已在盘点单 {$conflict['take_no']} 中使用，无法重复添加");
        }

        Db::transaction(function () use ($take, $post) {
            $whStock = Stock::where('sku_id', $post['sku_id'])
                            ->where('warehouse_id', $take->warehouse_id)->find();
            $sysQty  = $whStock ? $whStock->quantity : 0;
            $cost    = $whStock ? $whStock->cost_price : 0;
            $diffQty = $post['actual_quantity'] - $sysQty;
            $diffAmt = $diffQty * $cost;

            StockTakeItem::create([
                'stock_take_id'    => $take->id,
                'product_id'       => $post['product_id'],
                'sku_id'           => $post['sku_id'],
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
        // 检查状态：已审核、已完成、已取消的不允许操作
        if (in_array($take->status, [2, 3, 4])) {
            return $this->error('该盘点单已审核，不允许更新商品');
        }

        $item = StockTakeItem::where('stock_take_id', $id)->find($item_id);
        if (!$item) return $this->error('明细不存在');

        $post = input('post.');
        $oldAmt = $item->difference_amount;

        Db::transaction(function () use ($item, $take, $oldAmt, $post) {
            $whStock = Stock::where('sku_id', $item->sku_id)
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
        // 检查状态：已审核、已完成、已取消的不允许操作
        if (in_array($take->status, [2, 3, 4])) {
            return $this->error('该盘点单已审核，不允许删除商品');
        }

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