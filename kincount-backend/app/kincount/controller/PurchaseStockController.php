<?php

namespace app\kincount\controller;

use think\facade\Db;
use app\kincount\model\PurchaseStock;
use app\kincount\model\PurchaseStockItem;
use app\kincount\model\PurchaseOrder;
use app\kincount\model\PurchaseOrderItem;

class PurchaseStockController extends BaseController
{
    /**
     * 入库单列表
     */
    public function index()
    {
        try {
            $params = input('get.');
            $page = $params['page'] ?? 1;
            $pageSize = $params['page_size'] ?? 20;

            $query = PurchaseStock::with([
                'supplier' => function ($q) {
                    $q->field('id,name,contact_person,phone');
                },
                'warehouse' => function ($q) {
                    $q->field('id,name');
                },
                'creator' => function ($q) {
                    $q->field('id,real_name');
                },
                'auditor' => function ($q) {
                    $q->field('id,real_name');
                }
            ]);

            // 搜索条件
            if (!empty($params['stock_no'])) {
                $query->where('stock_no', 'like', "%{$params['stock_no']}%");
            }

            // 修复：添加供应商筛选
            if (!empty($params['supplier_id'])) {
                $query->where('supplier_id', $params['supplier_id']);
            }

            // 修复：添加仓库筛选
            if (!empty($params['warehouse_id'])) {
                $query->where('warehouse_id', $params['warehouse_id']);
            }

            if (!empty($params['status'])) {
                $query->where('status', $params['status']);
            }
            if (!empty($params['purchase_order_id'])) {
                $query->where('purchase_order_id', $params['purchase_order_id']);
            }
            if (!empty($params['start_date'])) {
                $query->where('created_at', '>=', $params['start_date']);
            }
            if (!empty($params['end_date'])) {
                $query->where('created_at', '<=', $params['end_date'] . ' 23:59:59');
            }

            $list = $query->order('id', 'desc')
                ->paginate([
                    'page' => $page,
                    'list_rows' => $pageSize
                ]);

            return $this->paginate($list, '获取成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 创建入库单
     */
    public function save()
    {
        $post = input('post.');
        $validate = new \think\Validate([
            'supplier_id'  => 'require|integer',
            'warehouse_id' => 'require|integer',
            'items'        => 'require|array|min:1'
        ]);

        if (!$validate->check($post)) {
            return $this->error($validate->getError());
        }

        // 手动管理事务
        Db::startTrans();
        try {
            // 如果有采购订单ID，检查采购订单状态和可入库数量
            if (!empty($post['purchase_order_id'])) {
                $this->validatePurchaseOrder($post['purchase_order_id'], $post['items']);
            }

            $stockNo = $this->generateStockNo('PS');
            $total   = 0;

            /* 主表 */
            $stock = PurchaseStock::create([
                'stock_no'          => $stockNo,
                'purchase_order_id' => $post['purchase_order_id'] ?? 0,
                'supplier_id'       => $post['supplier_id'],
                'warehouse_id'      => $post['warehouse_id'],
                'total_amount'      => 0,
                'status'            => 1,
                'remark'            => $post['remark'] ?? '',
                'created_by'        => $this->getUserId(),
            ]);

            /* 明细 - 确保包含 sku_id */
            foreach ($post['items'] as $v) {
                if (empty($v['product_id']) || empty($v['sku_id']) || empty($v['quantity']) || empty($v['price'])) {
                    throw new \Exception('商品明细不完整，请检查product_id、sku_id、quantity、price');
                }

                $rowTotal = $v['quantity'] * $v['price'];
                PurchaseStockItem::create([
                    'purchase_stock_id' => $stock->id,
                    'product_id'        => $v['product_id'],
                    'sku_id'           => $v['sku_id'], // 确保这一行存在
                    'quantity'          => $v['quantity'],
                    'price'             => $v['price'],
                    'total_amount'      => $rowTotal,
                ]);
                $total += $rowTotal;

                // 如果有采购订单ID，更新采购订单明细的已入库数量
                if (!empty($post['purchase_order_id'])) {
                    $this->updatePurchaseOrderReceivedQuantity(
                        $post['purchase_order_id'],
                        $v['product_id'],
                        $v['sku_id'],  // 新增 sku_id 参数
                        $v['quantity']
                    );
                }
            }

            // 更新入库单总金额
            $stock->save(['total_amount' => $total]);

            Db::commit();
            return $this->success(['id' => $stock->id], '采购入库单创建成功');
        } catch (\Exception $e) {
            Db::rollback();
            // 记录详细错误日志
            \think\facade\Log::error('入库单创建失败: ' . $e->getMessage(), [
                'post_data' => $post,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('入库单创建失败: ' . $e->getMessage());
        }
    }

    /**
     * 入库单详情
     */
    public function read($id)
    {
        try {
            $stock = PurchaseStock::with([
                'supplier' => function ($q) {
                    $q->field('id,name,contact_person,phone,address');
                },
                'warehouse' => function ($q) {
                    $q->field('id,name,address');
                },
                'creator' => function ($q) {
                    $q->field('id,real_name');
                },
                'auditor' => function ($q) {
                    $q->field('id,real_name');
                },
                'purchaseOrder' => function ($q) {
                    $q->field('id,order_no,status,total_amount,created_at')
                        ->with(['supplier' => function ($q) {
                            $q->field('id,name');
                        }]);
                },
                'items' => function ($q) {
                    $q->with([
                        'product' => function ($q) {
                            $q->field('id,name,product_no,unit,spec');
                        },
                        'sku' => function ($q) {
                            $q->field('id,sku_code,spec,cost_price,sale_price,barcode,unit,status');
                        }
                    ]);
                }
            ])->find($id);

            if (!$stock) {
                return $this->error('入库单不存在');
            }

            return $this->success($stock, '获取成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 更新入库单
     */
    public function update($id)
    {
        try {
            $post = input('post.');
            $stock = PurchaseStock::find($id);

            if (!$stock) {
                return $this->error('入库单不存在');
            }

            // 只有待审核状态的入库单可以更新
            if ($stock->status != 1) {
                return $this->error('只有待审核状态的入库单可以更新');
            }

            // 过滤允许更新的字段
            $allowFields = ['supplier_id', 'warehouse_id', 'remark'];
            $updateData = array_intersect_key($post, array_flip($allowFields));

            if (!empty($updateData)) {
                $stock->save($updateData);
            }

            return $this->success([], '更新成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除入库单
     */
    public function delete($id)
    {
        try {
            Db::transaction(function () use ($id) {
                $stock = PurchaseStock::find($id);
                if (!$stock) {
                    throw new \Exception('入库单不存在');
                }

                // 只有待审核状态的入库单可以删除
                if ($stock->status != 1) {
                    throw new \Exception('只有待审核状态的入库单可以删除');
                }

                // 如果有关联的采购订单，需要回退已入库数量
                if ($stock->purchase_order_id) {
                    $items = PurchaseStockItem::where('purchase_stock_id', $id)->select();
                    foreach ($items as $item) {
                        $result = PurchaseOrderItem::where('purchase_order_id', $stock->purchase_order_id)
                            ->where('product_id', $item->product_id)
                            ->dec('received_quantity', $item->quantity)
                            ->update();

                        if (!$result) {
                            throw new \Exception("回退采购订单已入库数量失败");
                        }
                    }

                    // 重新计算采购订单状态
                    $this->updatePurchaseOrderStatus($stock->purchase_order_id);
                }

                // 软删除入库单 - 直接更新 deleted_at 字段
                $stock->save(['deleted_at' => date('Y-m-d H:i:s')]);

                // 软删除入库单明细 - 直接更新 deleted_at 字段
                PurchaseStockItem::where('purchase_stock_id', $id)
                    ->update(['deleted_at' => date('Y-m-d H:i:s')]);
            });

            return $this->success([], '删除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 审核入库单
     */
    /**
     * 审核入库单
     */
    public function audit($id)
    {
        try {
            Db::transaction(function () use ($id) {
                $stock = PurchaseStock::with(['items'])->find($id);
                if (!$stock) {
                    throw new \Exception('入库单不存在');
                }

                if ($stock->status != 1) {
                    throw new \Exception('只有待审核状态的入库单可以审核');
                }

                // 更新库存
                foreach ($stock->items as $item) {
                    $this->updateStockQuantity(
                        $item->product_id,
                        $item->sku_id, // 注意：这里需要SKU ID，但你的入库单明细表可能没有sku_id字段
                        $stock->warehouse_id,
                        $item->quantity,
                        $item->price
                    );
                }

                // 更新入库单状态
                $stock->status = 2; // 已审核
                $stock->audit_by = $this->getUserId();
                $stock->audit_time = date('Y-m-d H:i:s');
                $stock->save();

                // 如果入库单有关联的采购订单，需要在审核时更新采购订单状态
                if ($stock->purchase_order_id) {
                    $this->updatePurchaseOrderStatus($stock->purchase_order_id);
                }
            });

            return $this->success([], '审核成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 更新库存数量
     */
    private function updateStockQuantity($productId, $skuId, $warehouseId, $quantity, $price)
    {
        // 首先检查库存记录是否存在
        $stock = \app\kincount\model\Stock::where('sku_id', $skuId)
            ->where('warehouse_id', $warehouseId)
            ->find();

        if ($stock) {
            // 更新现有库存
            $stock->quantity += $quantity;
            // 重新计算平均成本价
            $totalAmount = ($stock->quantity * $stock->cost_price) + ($quantity * $price);
            $stock->cost_price = $totalAmount / ($stock->quantity + $quantity);
            $stock->total_amount = $totalAmount;
            $stock->save();
        } else {
            // 创建新的库存记录
            \app\kincount\model\Stock::create([
                'sku_id' => $skuId,
                'warehouse_id' => $warehouseId,
                'quantity' => $quantity,
                'cost_price' => $price,
                'total_amount' => $quantity * $price
            ]);
        }
    }

    /**
     * 取消入库单（作废）
     */
    public function cancel($id)
    {
        try {
            Db::transaction(function () use ($id) {
                $stock = PurchaseStock::find($id);
                if (!$stock) {
                    throw new \Exception('入库单不存在');
                }

                // 只有待审核和已审核状态的入库单可以取消
                if (!in_array($stock->status, [1, 2])) {
                    throw new \Exception('当前状态的入库单不能取消');
                }

                // 如果有关联的采购订单，需要回退已入库数量
                if ($stock->purchase_order_id) {
                    $items = PurchaseStockItem::where('purchase_stock_id', $id)->select();
                    foreach ($items as $item) {
                        PurchaseOrderItem::where('purchase_order_id', $stock->purchase_order_id)
                            ->where('product_id', $item->product_id)
                            ->dec('received_quantity', $item->quantity)
                            ->update();
                    }

                    // 重新计算采购订单状态
                    $this->updatePurchaseOrderStatus($stock->purchase_order_id);
                }

                // 更新入库单状态为已取消
                $stock->status = 3; // 3-已取消
                $stock->save();
            });

            return $this->success([], '取消成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 取消审核（需要单独的路由）
     */
    public function cancelAudit($id)
    {
        try {
            Db::transaction(function () use ($id) {
                $stock = PurchaseStock::find($id);
                if (!$stock) {
                    throw new \Exception('入库单不存在');
                }

                if ($stock->status != 2) {
                    throw new \Exception('只有已审核状态的入库单可以取消审核');
                }

                // 如果入库单有关联的采购订单，需要回退已入库数量
                if ($stock->purchase_order_id) {
                    $items = PurchaseStockItem::where('purchase_stock_id', $id)->select();
                    foreach ($items as $item) {
                        // 使用原子操作减少已入库数量
                        $this->updatePurchaseOrderReceivedQuantity(
                            $stock->purchase_order_id,
                            $item->product_id,
                            $item->sku_id,  // 使用 item 的 sku_id
                            -$item->quantity  // 负数表示减少
                        );
                    }

                    // 重新计算采购订单状态
                    $this->updatePurchaseOrderStatus($stock->purchase_order_id);
                }

                // 更新入库单状态
                $stock->status = 1; // 待审核
                $stock->audit_by = null;
                $stock->audit_time = null;
                $stock->save();
            });

            return $this->success([], '取消审核成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取入库单明细
     */
    public function items($id)
    {
        try {
            $items = PurchaseStockItem::where('purchase_stock_id', $id)
                ->with(['product' => function ($q) {
                    $q->field('id,name,product_no,unit,spec');
                }])
                ->select();

            return $this->success($items, '获取成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 添加入库单明细
     */
    public function addItem($id)
    {
        try {
            $post = input('post.');
            $validate = new \think\Validate([
                'product_id' => 'require|integer',
                'sku_id'     => 'require|integer', // 新增 sku_id 验证
                'quantity'   => 'require|integer|gt:0',
                'price'      => 'require|float|gt:0'
            ]);

            if (!$validate->check($post)) {
                return $this->error($validate->getError());
            }

            $stock = PurchaseStock::find($id);
            if (!$stock) {
                return $this->error('入库单不存在');
            }

            // 只有待审核状态的入库单可以添加明细
            if ($stock->status != 1) {
                return $this->error('只有待审核状态的入库单可以添加明细');
            }

            Db::transaction(function () use ($stock, $post, $id) {
                $rowTotal = $post['quantity'] * $post['price'];

                // 添加明细
                PurchaseStockItem::create([
                    'purchase_stock_id' => $id,
                    'product_id'        => $post['product_id'],
                    'quantity'          => $post['quantity'],
                    'price'             => $post['price'],
                    'total_amount'      => $rowTotal,
                ]);

                // 更新入库单总金额
                $stock->inc('total_amount', $rowTotal)->save();

                // 如果有关联的采购订单，更新已入库数量
                if ($stock->purchase_order_id) {
                    $this->updatePurchaseOrderReceivedQuantity(
                        $stock->purchase_order_id,
                        $post['product_id'],
                        $post['sku_id'],  // 确保 $post 中包含 sku_id
                        $post['quantity']
                    );
                }
            });

            return $this->success([], '添加明细成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 更新入库单明细
     */
    public function updateItem($id, $itemId)
    {
        try {
            $post = input('post.');
            $stock = PurchaseStock::find($id);

            if (!$stock) {
                return $this->error('入库单不存在');
            }

            // 只有待审核状态的入库单可以更新明细
            if ($stock->status != 1) {
                return $this->error('只有待审核状态的入库单可以更新明细');
            }

            $item = PurchaseStockItem::where('purchase_stock_id', $id)
                ->where('id', $itemId)
                ->find();

            if (!$item) {
                return $this->error('明细不存在');
            }

            Db::transaction(function () use ($stock, $item, $post) {
                $oldQuantity = $item->quantity;
                $oldTotal = $item->total_amount;

                // 更新明细
                if (isset($post['quantity']) || isset($post['price'])) {
                    $quantity = $post['quantity'] ?? $item->quantity;
                    $price = $post['price'] ?? $item->price;
                    $newTotal = $quantity * $price;

                    $item->save([
                        'quantity' => $quantity,
                        'price' => $price,
                        'total_amount' => $newTotal
                    ]);

                    // 更新入库单总金额
                    $stock->dec('total_amount', $oldTotal)
                        ->inc('total_amount', $newTotal)
                        ->save();

                    // 如果有关联的采购订单，更新已入库数量
                    if ($stock->purchase_order_id) {
                        $quantityDiff = $quantity - $oldQuantity;
                        if ($quantityDiff != 0) {
                            $this->updatePurchaseOrderReceivedQuantity(
                                $stock->purchase_order_id,
                                $item->product_id,
                                $item->sku_id,  // 使用 item 的 sku_id
                                $quantityDiff
                            );
                        }
                    }
                }
            });

            return $this->success([], '更新明细成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除入库单明细
     */
    public function deleteItem($id, $itemId)
    {
        try {
            $stock = PurchaseStock::find($id);

            if (!$stock) {
                return $this->error('入库单不存在');
            }

            // 只有待审核状态的入库单可以删除明细
            if ($stock->status != 1) {
                return $this->error('只有待审核状态的入库单可以删除明细');
            }

            $item = PurchaseStockItem::where('purchase_stock_id', $id)
                ->where('id', $itemId)
                ->find();

            if (!$item) {
                return $this->error('明细不存在');
            }

            Db::transaction(function () use ($stock, $item) {
                // 更新入库单总金额
                $stock->dec('total_amount', $item->total_amount)->save();

                // 如果有关联的采购订单，回退已入库数量
                if ($stock->purchase_order_id) {
                    $this->updatePurchaseOrderReceivedQuantity(
                        $stock->purchase_order_id,
                        $item->product_id,
                        $item->sku_id,  // 使用 item 的 sku_id
                        -$item->quantity  // 负数表示减少
                    );
                }

                // 软删除明细 - 直接更新 deleted_at 字段
                $item->save(['deleted_at' => date('Y-m-d H:i:s')]);
            });

            return $this->success([], '删除明细成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /************************ 扩展方法 ************************/

    /**
     * 检查采购订单是否可以生成入库单
     */
    public function checkStockAvailability($id)
    {
        try {
            $purchaseOrder = PurchaseOrder::with('items')->find($id);

            if (!$purchaseOrder) {
                return $this->error('采购订单不存在');
            }

            // 检查状态
            if (!in_array($purchaseOrder->status, [2, 3])) {
                return $this->error('采购订单状态不允许生成入库单');
            }

            // 检查是否有可入库的商品
            $hasAvailableItems = false;
            $availableItems = [];

            foreach ($purchaseOrder->items as $item) {
                $availableQuantity = $item->quantity - $item->received_quantity;
                if ($availableQuantity > 0) {
                    $hasAvailableItems = true;
                    $availableItems[] = [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name ?? '未知商品',
                        'available_quantity' => $availableQuantity,
                        'ordered_quantity' => $item->quantity,
                        'received_quantity' => $item->received_quantity
                    ];
                }
            }

            if (!$hasAvailableItems) {
                return $this->error('没有可入库的商品');
            }

            return $this->success([
                'available' => true,
                'items' => $availableItems,
                'order_status' => $purchaseOrder->status
            ], '可以生成入库单');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 根据采购订单ID获取关联的入库单
     */

    /**
     * 根据采购订单ID获取关联的入库单
     */
    public function getStocksByOrderId($orderId)
    {
        try {
            $stocks = PurchaseStock::where('purchase_order_id', $orderId)
                ->with([
                    'auditor' => function ($query) {
                        $query->field('id,real_name');
                    },
                    // 添加入库单明细关联
                    'items' => function ($query) {
                        $query->field('id,purchase_stock_id,product_id,sku_id,quantity,price,total_amount')
                            ->with([
                                'product' => function ($q) {
                                    $q->field('id,name,product_no,unit,spec');
                                },
                                'sku' => function ($q) {
                                    // 修复：使用正确的字段，移除不存在的 sku_name
                                    $q->field('id,sku_code,spec,product_id'); // 添加 product_id 用于关联验证
                                }
                            ]);
                    },
                    // 可选：关联仓库信息
                    'warehouse' => function ($q) {
                        $q->field('id,name');
                    },
                    // 可选：关联创建者信息
                    'creator' => function ($q) {
                        $q->field('id,real_name');
                    }
                ])
                ->order('id', 'desc')
                ->select();

            return $this->success($stocks, '获取成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /************************ 私有方法 ************************/

    /**
     * 验证采购订单是否可以生成入库单（SKU级别）
     */
    private function validatePurchaseOrder($purchaseOrderId, $items)
    {
        // 检查采购订单是否存在且状态正确
        $purchaseOrder = PurchaseOrder::where('id', $purchaseOrderId)
            ->where('status', 'in', [2, 3]) // 2-已审核, 3-部分入库
            ->find();

        if (!$purchaseOrder) {
            throw new \Exception('采购订单不存在或状态不允许生成入库单');
        }

        // 检查每个商品的入库数量是否超过可入库数量（按SKU检查）
        foreach ($items as $item) {
            $availableQuantity = $this->getAvailableQuantity(
                $purchaseOrderId,
                $item['product_id'],
                $item['sku_id']  // 新增 sku_id 参数
            );

            if ($item['quantity'] > $availableQuantity) {
                $productName = $this->getProductName($item['product_id']);
                $skuInfo = $this->getSkuInfo($item['sku_id']);
                throw new \Exception("商品【{$productName}】SKU【{$skuInfo}】的入库数量超过可入库数量，可入库数量：{$availableQuantity}");
            }
        }
    }


    /**
     * 获取商品可入库数量（SKU级别）
     */
    private function getAvailableQuantity($purchaseOrderId, $productId, $skuId)
    {
        // 获取采购订单中该商品SKU的订购数量和已入库数量
        $orderItem = PurchaseOrderItem::where('purchase_order_id', $purchaseOrderId)
            ->where('product_id', $productId)
            ->where('sku_id', $skuId)  // 新增 SKU 条件
            ->find();

        if (!$orderItem) {
            throw new \Exception("采购订单中不存在该商品SKU，商品ID: {$productId}, SKU ID: {$skuId}");
        }

        $orderedQuantity = $orderItem->quantity;
        $receivedQuantity = $orderItem->received_quantity;

        return max(0, $orderedQuantity - $receivedQuantity);
    }

    /**
     * 更新采购订单的已入库数量
     */
    private function updatePurchaseOrderReceivedQuantity($purchaseOrderId, $productId, $skuId, $quantity)
    {
        // 使用原子操作更新已入库数量，同时匹配 product_id 和 sku_id
        $result = PurchaseOrderItem::where('purchase_order_id', $purchaseOrderId)
            ->where('product_id', $productId)
            ->where('sku_id', $skuId)  // 新增 SKU 条件
            ->inc('received_quantity', $quantity)
            ->update();

        if (!$result) {
            throw new \Exception("更新采购订单已入库数量失败，采购订单ID: {$purchaseOrderId}, 商品ID: {$productId}, SKU ID: {$skuId}");
        }

        // 检查并更新采购订单状态
        $this->updatePurchaseOrderStatus($purchaseOrderId);
    }

    /**
     * 更新采购订单状态
     */
    private function updatePurchaseOrderStatus($purchaseOrderId)
    {
        $order = PurchaseOrder::with('items')->find($purchaseOrderId);

        if (!$order) return;

        $allCompleted = true;
        $hasPartial = false;

        foreach ($order->items as $item) {
            $received = $item->received_quantity;
            $ordered = $item->quantity;

            if ($received < $ordered) {
                $allCompleted = false;
            }
            if ($received > 0 && $received < $ordered) {
                $hasPartial = true;
            }
        }

        $newStatus = $order->status;
        if ($allCompleted) {
            $newStatus = 4; // 已完成
        } elseif ($hasPartial) {
            $newStatus = 3; // 部分入库
        } else {
            $newStatus = 2; // 已审核（但未入库）
        }

        if ($order->status != $newStatus) {
            $order->status = $newStatus;
            $order->save();
        }
    }
    /**
     * 获取商品名称
     */
    private function getProductName($productId)
    {
        $product = \app\kincount\model\Product::where('id', $productId)->value('name');
        return $product ?: '未知商品';
    }

    /**
     * 获取SKU信息
     */
    private function getSkuInfo($skuId)
    {
        $sku = \app\kincount\model\ProductSku::where('id', $skuId)->find();
        if (!$sku) {
            return '未知SKU';
        }

        // 根据你的SKU表结构返回合适的描述
        return $sku->sku_code ?: 'SKU-' . $skuId;
    }
    /**
     * 生成单号 - 修复重复问题版本
     */
    private function generateStockNo($prefix = 'PS')
    {
        $date = date('Ymd');

        // 修复：使用更安全的方式查询当天记录数
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');

        // 获取当天最大的序号
        $maxNo = PurchaseStock::where('created_at', '>=', $todayStart)
            ->where('created_at', '<=', $todayEnd)
            ->order('id', 'desc')
            ->value('stock_no');

        $sequence = 1;

        if ($maxNo) {
            // 从最大单号中提取序号
            preg_match('/\d{4}$/', $maxNo, $matches);
            if (!empty($matches)) {
                $sequence = intval($matches[0]) + 1;
            }
        }

        $stockNo = $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);

        // 双重检查：确保单号唯一
        $exists = PurchaseStock::where('stock_no', $stockNo)->find();
        if ($exists) {
            // 如果存在，递归生成直到找到唯一的单号
            return $this->generateStockNoWithRetry($prefix, $sequence + 1);
        }

        return $stockNo;
    }

    /**
     * 重试生成单号（防止重复）
     */
    private function generateStockNoWithRetry($prefix = 'PS', $startSequence = 1, $maxRetry = 100)
    {
        $date = date('Ymd');

        for ($i = 0; $i < $maxRetry; $i++) {
            $sequence = $startSequence + $i;
            $stockNo = $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);

            // 检查单号是否已存在
            $exists = PurchaseStock::where('stock_no', $stockNo)->find();
            if (!$exists) {
                return $stockNo;
            }
        }

        // 如果尝试多次仍然冲突，使用时间戳作为后缀
        return $prefix . $date . substr(time(), -4);
    }
}
