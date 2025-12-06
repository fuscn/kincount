<?php

declare(strict_types=1);

namespace app\kincount\controller;

use app\kincount\model\ReturnStock;
use app\kincount\model\ReturnStockItem;
use app\kincount\model\ReturnOrder;
use app\kincount\model\Stock;
use app\kincount\model\ReturnOrderItem;
use app\kincount\model\ProductSku;
use app\kincount\model\Customer;
use app\kincount\model\Supplier;
use think\facade\Db;
use think\Validate;

class ReturnStockController extends BaseController
{
    /**
     * 退货出入库单列表（修复关联字段返回格式）
     */
    public function index()
    {
        $page  = input('page/d', 1); // TP8 助手函数：d 表示强制转换为整数
        $limit = input('limit/d', 15);
        $kw    = input('keyword/s', ''); // s 表示强制转换为字符串
        $status = input('status/s', '');
        $returnId = input('return_id/d', 0);
        $warehouseId = input('warehouse_id/d', 0);
        $sDate = input('start_date/s', '');
        $eDate = input('end_date/s', '');

        $query = ReturnStock::with([
            'return' => function ($q) {
                $q->field('id, return_no, type, total_amount'); // 只返回需要的字段
            },
            'target' => function ($q) {
                // 统一返回目标名称（客户/供应商），避免数组返回
                $q->field('id, name');
            },
            'warehouse' => function ($q) {
                $q->field('id, name, code');
            },
            'creator' => function ($q) {
                $q->field('id, real_name');
            },
            'auditor' => function ($q) {
                $q->field('id, real_name');
            }
        ])->where('deleted_at', null);

        if ($kw) {
            $query->whereLike('stock_no', "%{$kw}%")
                ->orWhereHas('return', function ($subQuery) use ($kw) {
                    $subQuery->whereLike('return_no', "%{$kw}%");
                });
        }

        if ($status !== '') $query->where('status', $status);
        if ($returnId) $query->where('return_id', $returnId);
        if ($warehouseId) $query->where('warehouse_id', $warehouseId);
        if ($sDate) $query->where('created_at', '>=', $sDate);
        if ($eDate) $query->where('created_at', '<=', $eDate . ' 23:59:59');

        $list = $query->order('id', 'desc')
            ->paginate(['list_rows' => $limit, 'page' => $page]);

        // 处理返回数据，确保目标名称是字符串
        $list->each(function ($item) {
            // 将 target 数组转换为字符串名称
            $item->target_name = $item->target ? $item->target->name : '';
            unset($item->target); // 移除原始关联数据
            return $item;
        });

        return $this->paginate($list);
    }

    /**
     * 退货出入库单详情（修复 SKU spec 字段和关联字段）
     */
public function read($id)
{
    // 修复：添加 findOrEmpty 的参数
    $stock = ReturnStock::with([
        'return' => function ($q) {
            $q->field('id, return_no, type, total_amount, target_id, status');
        },
        'target' => function ($q) {
            $q->field('id, name, contact_person, phone');
        },
        'warehouse' => function ($q) {
            $q->field('id, name, code, address');
        },
        'creator' => function ($q) {
            $q->field('id, real_name, phone');
        },
        'auditor' => function ($q) {
            $q->field('id, real_name');
        }
    ])->where('deleted_at', null)->findOrEmpty($id); // 修复：添加 $id 参数

    if ($stock->isEmpty()) return $this->error('退货出入库单不存在');

    // 加载明细，处理 SKU 的 spec 字段
    $stock['items'] = ReturnStockItem::with([
        'product' => function ($q) {
            $q->field('id, name, product_no, category_id, brand_id, sale_price');
        },
        'sku' => function ($q) {
            $q->field('id, sku_code, spec, barcode, cost_price, sale_price');
        },
        'ReturnOrderItem' => function ($q) {
            $q->field('id, return_quantity, processed_quantity, price');
        }
    ])->where('return_stock_id', $id)
        ->whereNull('deleted_at')
        ->select()
        ->each(function ($item) {
            // 修复：处理 spec 字段的各种可能类型
            if (!empty($item->sku) && isset($item->sku->spec)) {
                $spec = $item->sku->spec;
                
                // 如果 spec 是字符串，尝试解析 JSON
                if (is_string($spec)) {
                    $specArr = json_decode($spec, true);
                } 
                // 如果 spec 是 stdClass 对象，转换为数组
                elseif (is_object($spec)) {
                    $specArr = (array)$spec;
                }
                // 如果 spec 已经是数组，直接使用
                elseif (is_array($spec)) {
                    $specArr = $spec;
                }
                // 其他情况设为空数组
                else {
                    $specArr = [];
                }
                
                // 将规格数组转换为字符串描述
                if (is_array($specArr) && !empty($specArr)) {
                    $item->sku->spec_text = implode('，', array_map(function ($k, $v) {
                        return "{$k}:{$v}";
                    }, array_keys($specArr), $specArr));
                } else {
                    $item->sku->spec_text = is_string($spec) ? $spec : '';
                }
            } else {
                // 确保 spec_text 属性存在
                if (!empty($item->sku)) {
                    $item->sku->spec_text = '';
                }
            }
            return $item;
        });

    // 处理 target 字段，确保前端获取字符串类型
    if (!empty($stock->target)) {
        $stock->target_info = [
            'id' => $stock->target->id ?? 0,
            'name' => $stock->target->name ?? '',
            'contact' => $stock->target->contact_person ?? '',
            'phone' => $stock->target->phone ?? ''
        ];
    } else {
        $stock->target_info = [
            'id' => 0,
            'name' => '',
            'contact' => '',
            'phone' => ''
        ];
    }
    unset($stock->target);

    return $this->success($stock);
}

    /**
     * 创建退货出入库单（优化验证规则和数据类型）
     */
    public function save()
    {
        $post = input('post.');

        // 使用 TP8 验证器语法，明确字段类型
        $validate = new Validate([
            'return_id'    => 'require|integer',
            'warehouse_id' => 'require|integer',
            'items'        => 'require|array|min:1',
            'items.*.return_item_id' => 'require|integer',
            'items.*.quantity'       => 'require|integer|gt:0',
            'items.*.price'          => 'require|float|egt:0'
        ], [
            'return_id.require' => '退货单ID不能为空',
            'warehouse_id.require' => '仓库ID不能为空',
            'items.require' => '退货明细不能为空',
            'items.array' => '退货明细必须是数组格式',
        ]);

        if (!$validate->check($post)) {
            return $this->error($validate->getError());
        }

        try {
            return Db::transaction(function () use ($post) {
                // 获取退货单信息（使用 findOrFail 抛出异常）
                $return = ReturnOrder::findOrFail($post['return_id']);

                // 创建主表记录（使用 TP8 的 create 方法，自动过滤非法字段）
                $stock = ReturnStock::create([
                    'return_id'    => $post['return_id'],
                    'target_id'    => $return->target_id,
                    'warehouse_id' => $post['warehouse_id'],
                    'status'       => ReturnStock::STATUS_PENDING_AUDIT,
                    'remark'       => input('post.remark/s', ''), // 强制转换为字符串
                    'created_by'   => $this->getUserId(),
                ]);

                $totalAmount = 0.00;

                // 处理明细
                foreach ($post['items'] as $itemData) {
                    // 验证明细数据
                    $itemValidate = new Validate([
                        'return_item_id' => 'require|integer',
                        'quantity'       => 'require|integer|gt:0',
                        'price'          => 'require|float|egt:0'
                    ]);

                    if (!$itemValidate->check($itemData)) {
                        $errorMsg = is_array($itemValidate->getError())
                            ? implode('，', $itemValidate->getError())
                            : (string)$itemValidate->getError();
                        throw new \Exception($errorMsg);
                    }

                    // 验证退货明细是否存在
                    $ReturnOrderItem = ReturnOrderItem::where([
                        'id' => $itemData['return_item_id'],
                        'return_id' => $post['return_id']
                    ])->findOrFail();

                    // 检查数量是否超过可处理数量
                    $availableQuantity = $ReturnOrderItem->return_quantity - $ReturnOrderItem->processed_quantity;
                    if ($itemData['quantity'] > $availableQuantity) {
                        throw new \Exception("退货数量超过可处理数量，最多可处理{$availableQuantity}");
                    }

                    // 获取SKU信息
                    $sku = ProductSku::findOrFail($ReturnOrderItem->sku_id);

                    // 计算金额（使用 bc 函数确保精度）
                    $itemTotal = bcmul((string)$itemData['quantity'], (string)$itemData['price'], 2);
                    $totalAmount = bcadd((string)$totalAmount, $itemTotal, 2);

                    // 创建明细记录
                    ReturnStockItem::create([
                        'return_stock_id' => $stock->id,
                        'return_item_id'  => $itemData['return_item_id'],
                        'product_id'      => $ReturnOrderItem->product_id,
                        'sku_id'          => $ReturnOrderItem->sku_id,
                        'quantity'        => $itemData['quantity'],
                        'price'           => $itemData['price'],
                        'total_amount'    => $itemTotal,
                    ]);

                    // 更新退货明细的已处理数量
                    $ReturnOrderItem->processed_quantity = bcadd((string)$ReturnOrderItem->processed_quantity, (string)$itemData['quantity'], 0);
                    $ReturnOrderItem->save();
                }

                // 更新总金额
                $stock->total_amount = $totalAmount;
                $stock->save();

                return $this->success(['id' => $stock->id], '退货出入库单创建成功');
            });
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 更新退货出入库单（优化数据类型处理）
     */
    public function update($id)
    {
        $stock = ReturnStock::where('deleted_at', null)->findOrEmpty();
        if ($stock->isEmpty()) return $this->error('退货出入库单不存在');
        if ($stock->status != ReturnStock::STATUS_PENDING_AUDIT) {
            return $this->error('只有待审核状态的单据可以修改');
        }

        $post = input('post.');

        try {
            return Db::transaction(function () use ($stock, $post) {
                // 更新主表信息（使用数组赋值，确保类型正确）
                $updateData = [];
                if (isset($post['warehouse_id'])) {
                    $updateData['warehouse_id'] = (int)$post['warehouse_id'];
                }
                if (isset($post['remark'])) {
                    $updateData['remark'] = (string)$post['remark'];
                }
                if (!empty($updateData)) {
                    $stock->save($updateData);
                }

                // 如果有新的明细，先删除旧的再添加新的
                if (!empty($post['items']) && is_array($post['items'])) {
                    // 先恢复退货明细的已处理数量
                    $oldItems = ReturnStockItem::where('return_stock_id', $stock->id)->select();
                    foreach ($oldItems as $oldItem) {
                        $ReturnOrderItem = ReturnOrderItem::find($oldItem->return_item_id);
                        if ($ReturnOrderItem) {
                            $ReturnOrderItem->processed_quantity = bcsub(
                                (string)$ReturnOrderItem->processed_quantity,
                                (string)$oldItem->quantity,
                                0
                            );
                            $ReturnOrderItem->save();
                        }
                        $oldItem->delete();
                    }

                    // 添加新的明细
                    $totalAmount = 0.00;
                    foreach ($post['items'] as $itemData) {
                        $itemValidate = new Validate([
                            'return_item_id' => 'require|integer',
                            'quantity'       => 'require|integer|gt:0',
                            'price'          => 'require|float|egt:0'
                        ]);

                        if (!$itemValidate->check($itemData)) {
                            // 变量名一致：使用 $itemValidate（之前错误使用 $itemValidate）
                            $errorMsg = is_array($itemValidate->getError())
                                ? implode('，', $itemValidate->getError())
                                : (string)$itemValidate->getError();
                            throw new \Exception($errorMsg);
                        }

                        $ReturnOrderItem = ReturnOrderItem::where([
                            'id' => $itemData['return_item_id'],
                            'return_id' => $stock->return_id
                        ])->findOrFail();

                        $availableQuantity = $ReturnOrderItem->return_quantity - $ReturnOrderItem->processed_quantity;
                        if ($itemData['quantity'] > $availableQuantity) {
                            throw new \Exception("退货数量超过可处理数量，最多可处理{$availableQuantity}");
                        }

                        $itemTotal = bcmul((string)$itemData['quantity'], (string)$itemData['price'], 2);
                        $totalAmount = bcadd((string)$totalAmount, $itemTotal, 2);

                        ReturnStockItem::create([
                            'return_stock_id' => $stock->id,
                            'return_item_id'  => $itemData['return_item_id'],
                            'product_id'      => $ReturnOrderItem->product_id,
                            'sku_id'          => $ReturnOrderItem->sku_id,
                            'quantity'        => $itemData['quantity'],
                            'price'           => $itemData['price'],
                            'total_amount'    => $itemTotal,
                        ]);

                        $ReturnOrderItem->processed_quantity = bcadd(
                            (string)$ReturnOrderItem->processed_quantity,
                            (string)$itemData['quantity'],
                            0
                        );
                        $ReturnOrderItem->save();
                    }

                    $stock->total_amount = $totalAmount;
                    $stock->save();
                }

                return $this->success([], '退货出入库单更新成功');
            });
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除退货出入库单（保持逻辑不变，优化异常处理）
     */
    public function delete($id)
    {
        $stock = ReturnStock::where('deleted_at', null)->findOrEmpty();
        if ($stock->isEmpty()) return $this->error('退货出入库单不存在');
        if ($stock->status != ReturnStock::STATUS_PENDING_AUDIT) {
            return $this->error('只有待审核状态的单据可以删除');
        }

        try {
            Db::transaction(function () use ($stock) {
                // 恢复退货明细的已处理数量
                $items = ReturnStockItem::where('return_stock_id', $stock->id)->select();
                foreach ($items as $item) {
                    $ReturnOrderItem = ReturnOrderItem::find($item->return_item_id);
                    if ($ReturnOrderItem) {
                        $ReturnOrderItem->processed_quantity = bcsub(
                            (string)$ReturnOrderItem->processed_quantity,
                            (string)$item->quantity,
                            0
                        );
                        $ReturnOrderItem->save();
                    }
                    $item->delete();
                }

                $stock->delete();
            });

            return $this->success([], '退货出入库单删除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 审核退货出入库单（优化库存计算精度）
     */
    public function audit($id)
    {
        $stock = ReturnStock::where('deleted_at', null)->findOrEmpty();
        if ($stock->isEmpty()) return $this->error('退货出入库单不存在');
        if ($stock->status != ReturnStock::STATUS_PENDING_AUDIT) {
            return $this->error('只有待审核状态的单据可以审核');
        }

        try {
            return Db::transaction(function () use ($stock) {
                // 获取退货单信息，判断是销售退货还是采购退货
                $return = ReturnOrder::findOrFail($stock->return_id);

                // 更新主表状态
                $stock->save([
                    'status'     => ReturnStock::STATUS_AUDITED,
                    'audit_by'   => $this->getUserId(),
                    'audit_time' => date('Y-m-d H:i:s'),
                ]);

                // 处理库存：销售退货入库，采购退货出库
                foreach ($stock->items as $item) {
                    $stockRecord = Stock::where([
                        'sku_id' => $item->sku_id,
                        'warehouse_id' => $stock->warehouse_id
                    ])->find();

                    if (!$stockRecord) {
                        // 如果库存记录不存在，创建新记录
                        $sku = ProductSku::findOrFail($item->sku_id);

                        $stockRecord = new Stock();
                        $stockRecord->sku_id = $item->sku_id;
                        $stockRecord->product_id = $item->product_id;
                        $stockRecord->warehouse_id = $stock->warehouse_id;
                        $stockRecord->cost_price = $sku->cost_price;
                        $stockRecord->quantity = 0;
                    }

                    // 根据退货类型更新库存（使用 bc 函数确保整数精度）
                    if ($return->type == ReturnOrder::TYPE_SALE) {
                        // 销售退货：入库，增加库存
                        $stockRecord->quantity = bcadd(
                            (string)$stockRecord->quantity,
                            (string)$item->quantity,
                            0
                        );
                    } else {
                        // 采购退货：出库，减少库存
                        if ($stockRecord->quantity < $item->quantity) {
                            throw new \Exception("库存不足，无法完成退货出库");
                        }
                        $stockRecord->quantity = bcsub(
                            (string)$stockRecord->quantity,
                            (string)$item->quantity,
                            0
                        );
                    }

                    // 更新总金额（确保金额精度）
                    $stockRecord->total_amount = bcmul(
                        (string)$stockRecord->quantity,
                        (string)$stockRecord->cost_price,
                        2
                    );
                    $stockRecord->save();
                }

                return $this->success([], '审核成功');
            });
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 取消退货出入库单（优化数量计算）
     */
    public function cancel($id)
    {
        $stock = ReturnStock::where('deleted_at', null)->findOrEmpty();
        if ($stock->isEmpty()) return $this->error('退货出入库单不存在');
        if ($stock->status != ReturnStock::STATUS_PENDING_AUDIT) {
            return $this->error('只有待审核状态的单据可以取消');
        }

        try {
            Db::transaction(function () use ($stock) {
                // 恢复退货明细的已处理数量
                $items = ReturnStockItem::where('return_stock_id', $stock->id)->select();
                foreach ($items as $item) {
                    $ReturnOrderItem = ReturnOrderItem::find($item->return_item_id);
                    if ($ReturnOrderItem) {
                        $ReturnOrderItem->processed_quantity = bcsub(
                            (string)$ReturnOrderItem->processed_quantity,
                            (string)$item->quantity,
                            0
                        );
                        $ReturnOrderItem->save();
                    }
                }

                // 更新状态为已取消
                $stock->status = ReturnStock::STATUS_CANCELLED;
                $stock->save();
            });

            return $this->success([], '已取消退货出入库单');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取出入库明细（修复 SKU spec 字段）
     */
    public function items($id)
    {
        $items = ReturnStockItem::with([
            'product' => function ($q) {
                $q->field('id, name, product_no, sale_price');
            },
            'sku' => function ($q) {
                $q->field('id, sku_code, spec, sale_price');
            },
            'ReturnOrderItem' => function ($q) {
                $q->field('id, return_quantity, processed_quantity');
            }
        ])->where('return_stock_id', $id)
            ->whereNull('deleted_at')
            ->select()
            ->each(function ($item) {
                // 处理 SKU spec 字段
                if (!empty($item->sku->spec)) {
                    $specArr = json_decode($item->sku->spec, true);
                    $item->sku->spec_text = is_array($specArr)
                        ? implode('，', array_map(function ($k, $v) {
                            return "{$k}:{$v}";
                        }, array_keys($specArr), $specArr))
                        : (string)$item->sku->spec;
                } else {
                    $item->sku->spec_text = '';
                }
                return $item;
            });

        return $this->success($items);
    }

    /**
     * 添加出入库明细（保持逻辑不变）
     */
    public function addItem($id)
    {
        $stock = ReturnStock::where('deleted_at', null)->findOrEmpty();
        if ($stock->isEmpty()) return $this->error('退货出入库单不存在');
        if ($stock->status != ReturnStock::STATUS_PENDING_AUDIT) {
            return $this->error('只有待审核状态的单据可以添加明细');
        }

        $post = input('post.');
        $validate = new Validate([
            'return_item_id' => 'require|integer',
            'quantity'       => 'require|integer|gt:0',
            'price'          => 'require|float|egt:0'
        ]);

        if (!$validate->check($post)) return $this->error($validate->getError());

        try {
            return Db::transaction(function () use ($stock, $post) {
                // 验证退货明细是否存在
                $ReturnOrderItem = ReturnOrderItem::where([
                    'id' => $post['return_item_id'],
                    'return_id' => $stock->return_id
                ])->findOrFail();

                // 检查是否已添加该明细
                $exists = ReturnStockItem::where([
                    'return_stock_id' => $stock->id,
                    'return_item_id' => $post['return_item_id']
                ])->exists();

                if ($exists) {
                    throw new \Exception('该退货明细已添加到出入库单中');
                }

                // 检查数量是否超过可处理数量
                $availableQuantity = $ReturnOrderItem->return_quantity - $ReturnOrderItem->processed_quantity;
                if ($post['quantity'] > $availableQuantity) {
                    throw new \Exception("退货数量超过可处理数量，最多可处理{$availableQuantity}");
                }

                // 计算金额
                $itemTotal = bcmul((string)$post['quantity'], (string)$post['price'], 2);

                // 创建明细记录
                ReturnStockItem::create([
                    'return_stock_id' => $stock->id,
                    'return_item_id'  => $post['return_item_id'],
                    'product_id'      => $ReturnOrderItem->product_id,
                    'sku_id'          => $ReturnOrderItem->sku_id,
                    'quantity'        => $post['quantity'],
                    'price'           => $post['price'],
                    'total_amount'    => $itemTotal,
                ]);

                // 更新退货明细的已处理数量
                $ReturnOrderItem->processed_quantity = bcadd(
                    (string)$ReturnOrderItem->processed_quantity,
                    (string)$post['quantity'],
                    0
                );
                $ReturnOrderItem->save();

                // 更新总金额
                $stock->total_amount = bcadd((string)$stock->total_amount, $itemTotal, 2);
                $stock->save();

                return $this->success([], '明细添加成功');
            });
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除出入库明细（保持逻辑不变）
     */
    public function deleteItem($id, $item_id)
    {
        $stock = ReturnStock::where('deleted_at', null)->findOrEmpty();
        if ($stock->isEmpty()) return $this->error('退货出入库单不存在');
        if ($stock->status != ReturnStock::STATUS_PENDING_AUDIT) {
            return $this->error('只有待审核状态的单据可以删除明细');
        }

        $item = ReturnStockItem::where([
            'return_stock_id' => $id,
            'id' => $item_id
        ])->findOrEmpty();

        if ($item->isEmpty()) return $this->error('明细不存在');

        try {
            Db::transaction(function () use ($stock, $item) {
                // 恢复退货明细的已处理数量
                $ReturnOrderItem = ReturnOrderItem::find($item->return_item_id);
                if ($ReturnOrderItem) {
                    $ReturnOrderItem->processed_quantity = bcsub(
                        (string)$ReturnOrderItem->processed_quantity,
                        (string)$item->quantity,
                        0
                    );
                    $ReturnOrderItem->save();
                }

                // 更新总金额
                $stock->total_amount = bcsub(
                    (string)$stock->total_amount,
                    (string)$item->total_amount,
                    2
                );
                $stock->save();

                // 删除明细
                $item->delete();
            });

            return $this->success([], '明细删除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
