<?php

declare(strict_types=1);

namespace app\kincount\controller;

use app\kincount\model\SaleOrder;
use app\kincount\model\SaleOrderItem;
use app\kincount\model\Customer;
use app\kincount\model\Product;
use app\kincount\model\AccountRecord;
use app\kincount\model\Stock;
use app\kincount\model\Warehouse;
use think\facade\Db;

class SaleOrderController extends BaseController
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

        $query = SaleOrder::with(['customer', 'warehouse', 'creator'])
            ->where('deleted_at', null);

        if ($kw) $query->whereLike('order_no', "%{$kw}%");
        if ($cusId) $query->where('customer_id', $cusId);

        // 修改：支持逗号分隔的多个状态
        if ($status !== '') {
            $statusArr = explode(',', $status);
            if (count($statusArr) > 1) {
                $query->whereIn('status', $statusArr);
            } else {
                $query->where('status', $status);
            }
        }

        if ($sDate) $query->where('created_at', '>=', $sDate);
        if ($eDate) $query->where('created_at', '<=', $eDate . ' 23:59:59');

        return $this->paginate($query->order('id', 'desc')
            ->paginate(['list_rows' => $limit, 'page' => $page]));
    }

    public function read($id)
    {
        $order = SaleOrder::with(['customer', 'warehouse', 'creator', 'auditor'])
            ->where('deleted_at', null)->find($id);
        if (!$order) return $this->error('销售订单不存在');

        // 加载明细，同时加载商品分类、品牌和SKU信息
        $order['items'] = SaleOrderItem::with(['product.category', 'product.brand', 'sku'])
            ->where('sale_order_id', $id)->select();

        // 格式化SKU规格信息（如果SKU存在spec字段）
        if (!empty($order['items'])) {
            foreach ($order['items'] as &$item) {
                if (isset($item['sku']) && !empty($item['sku']['spec'])) {
                    // 如果spec是JSON字符串，解析为数组
                    if (is_string($item['sku']['spec'])) {
                        $item['sku']['spec'] = json_decode($item['sku']['spec'], true);
                    }
                    // 生成规格显示文本
                    if (is_array($item['sku']['spec']) && !empty($item['sku']['spec'])) {
                        $specText = [];
                        foreach ($item['sku']['spec'] as $key => $value) {
                            $specText[] = "{$key}:{$value}";
                        }
                        $item['sku']['spec_text'] = implode(' ', $specText);
                    } else {
                        $item['sku']['spec_text'] = '';
                    }
                } else {
                    // 如果没有SKU信息，设置默认值
                    $item['sku'] = $item['sku'] ?? [
                        'id' => 0,
                        'sku_code' => '',
                        'spec' => [],
                        'spec_text' => '',
                        'barcode' => ''
                    ];
                }

                // 计算未出库数量
                $item['undelivered_quantity'] = $item['quantity'] - $item['delivered_quantity'];
            }
            unset($item); // 解除引用
        }

        // 获取客户信息
        if ($order->customer) {
            $order['customer']['level_text'] = match ($order->customer->level) {
                1 => '普通',
                2 => '银牌',
                3 => '金牌',
                default => '未知'
            };
        }

        // 获取仓库信息
        if ($order->warehouse) {
            $order['warehouse_info'] = [
                'name' => $order->warehouse->name,
                'code' => $order->warehouse->code,
                'address' => $order->warehouse->address
            ];
        }

        // 获取订单状态文本
        $statusOptions = $order->getStatusOptions();
        $order['status_text'] = $statusOptions[$order->status] ?? '未知状态';

        // 计算付款进度
        $order['payment_progress'] = $order->final_amount > 0
            ? round(($order->paid_amount / $order->final_amount) * 100, 2)
            : 0;

        // 计算未付金额
        $order['unpaid_amount'] = $order->final_amount - $order->paid_amount;

        return $this->success($order);
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
            $order = Db::transaction(function () use ($post) {
                $orderNo = $this->generateOrderNo('SO');
                $customer = Customer::where('deleted_at', null)->find($post['customer_id']);
                $discount = $customer ? $customer->discount : 1.0;

                /* 主表 */
                $order = SaleOrder::create([
                    'order_no'      => $orderNo,
                    'customer_id'   => $post['customer_id'],
                    'warehouse_id'  => $post['warehouse_id'],
                    'status'        => 1,
                    'remark'        => $post['remark'] ?? '',
                    'created_by'    => $this->getUserId(),
                    'expected_date' => $post['expected_date'] ?? null,
                ]);

                /* 明细 + 库存校验（修改为SKU维度） */
                $total = 0;
                foreach ($post['items'] as $v) {
                    if (empty($v['sku_id']) || empty($v['quantity']) || empty($v['price'])) {
                        throw new \Exception('商品明细不完整');
                    }

                    // 通过sku_id获取product_id
                    $sku = \app\kincount\model\ProductSku::find($v['sku_id']);
                    if (!$sku) {
                        throw new \Exception('SKU不存在');
                    }

                    // 库存检查（SKU维度）
                    $stock = Stock::where('sku_id', $v['sku_id'])
                        ->where('warehouse_id', $post['warehouse_id'])->find();
                    if (!$stock || $stock->quantity < $v['quantity']) {
                        $prod = Product::find($sku->product_id);
                        $stockMsg = $stock ? "当前库存：{$stock->quantity}" : "无库存";
                        throw new \Exception("商品 {$prod->name} (SKU: {$sku->sku_code}) 库存不足，{$stockMsg}");
                    }

                    $rowTotal = $v['quantity'] * $v['price'];
                    SaleOrderItem::create([
                        'sale_order_id'     => $order->id,
                        'product_id'        => $sku->product_id,
                        'sku_id'            => $v['sku_id'],
                        'quantity'          => $v['quantity'],
                        'delivered_quantity' => 0,
                        'price'            => $v['price'],
                        'total_amount'     => $rowTotal,
                    ]);
                    $total += $rowTotal;
                }

                /* 折扣/最终金额 */
                $discountAmount = $total * (1 - $discount);
                $order->save([
                    'total_amount'   => $total,
                    'discount_amount' => $discountAmount,
                    'final_amount'   => $total - $discountAmount,
                ]);

                return $order;
            });

            return $this->success(['id' => $order->id], '销售订单创建成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function update($id)
    {
        $order = SaleOrder::where('deleted_at', null)->find($id);
        if (!$order) return $this->error('销售订单不存在');
        if ($order->status != 1) return $this->error('只有待审核可修改');

        $post = input('post.');
        Db::transaction(function () use ($order, $post) {
            /* 基础信息 */
            $order->save([
                'customer_id'   => $post['customer_id'] ?? $order->customer_id,
                'warehouse_id'  => $post['warehouse_id'] ?? $order->warehouse_id,
                'remark'        => $post['remark'] ?? $order->remark,
                'expected_date' => $post['expected_date'] ?? $order->expected_date,
            ]);

            /* 若传了 items 则整体替换 */
            if (!empty($post['items']) && is_array($post['items'])) {
                SaleOrderItem::where('sale_order_id', $order->id)->delete();
                $customer = Customer::find($post['customer_id'] ?? $order->customer_id);
                $discount = $customer ? $customer->discount : 1.0;

                $total = 0;
                foreach ($post['items'] as $v) {
                    $stock = Stock::where('product_id', $v['product_id'])
                        ->where('warehouse_id', $post['warehouse_id'] ?? $order->warehouse_id)->find();
                    if (!$stock || $stock->quantity < $v['quantity']) {
                        $prod = Product::find($v['product_id']);
                        throw new \Exception("商品 {$prod->name} 库存不足");
                    }
                    $rowTotal = $v['quantity'] * $v['price'];
                    SaleOrderItem::create([
                        'sale_order_id'     => $order->id,
                        'product_id'        => $v['product_id'],
                        'quantity'          => $v['quantity'],
                        'delivered_quantity' => 0,
                        'price'             => $v['price'],
                        'total_amount'      => $rowTotal,
                    ]);
                    $total += $rowTotal;
                }
                $discountAmount = $total * (1 - $discount);
                $order->save([
                    'total_amount'    => $total,
                    'discount_amount' => $discountAmount,
                    'final_amount'    => $total - $discountAmount,
                ]);
            }
        });

        return $this->success([], '销售订单更新成功');
    }

    public function delete($id)
    {
        $order = SaleOrder::where('deleted_at', null)->find($id);
        if (!$order) return $this->error('销售订单不存在');
        if ($order->status != 1) return $this->error('只有待审核可删除');

        Db::transaction(function () use ($order) {
            SaleOrderItem::where('sale_order_id', $order->id)->delete();
            $order->delete();
        });
        return $this->success([], '销售订单删除成功');
    }

 public function audit($id)
    {
        // 启动事务确保数据一致性
        Db::startTrans();
        try {
            // 1. 查找订单
            $order = SaleOrder::where('deleted_at', null)->find($id);
            if (!$order) {
                throw new \Exception('销售订单不存在');
            }
            if ($order->status != 1) {
                throw new \Exception('当前状态无法审核');
            }

            // 2. 更新订单状态
            $order->save([
                'status' => 2, 
                'audit_by' => $this->getUserId(), 
                'audit_time' => date('Y-m-d H:i:s')
            ]);

            // 3. 生成应收账款记录
            $accountRecord = AccountRecord::create([
                'type' => 1, // 应收账款
                'target_id' => $order->customer_id,
                'target_type' => 'customer',
                'related_id' => $order->id,
                'related_type' => 'sale',
                'amount' => $order->final_amount,
                'paid_amount' => 0.00,
                'balance_amount' => $order->final_amount,
                'status' => 1, // 未结清
                'due_date' => $order->expected_date ?: date('Y-m-d', strtotime('+30 days')),
                'remark' => "销售订单 {$order->order_no}"
            ]);

            // 4. 更新客户应收账款余额
            $customer = Customer::find($order->customer_id);
            if ($customer) {
                $customer->receivable_balance += $order->final_amount;
                $customer->save();
            }

            // 5. 提交事务
            Db::commit();
            
            // 6. 记录操作日志（可选）
            // $this->logAudit($order, '审核销售订单');
            
            return $this->success(['account_record_id' => $accountRecord->id], '审核成功，已生成应收账款');
            
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return $this->error($e->getMessage());
        }
    }

    public function cancel($id)
    {
        $order = SaleOrder::where('deleted_at', null)->find($id);
        if (!$order) return $this->error('销售订单不存在');
        if (!in_array($order->status, [1, 2])) return $this->error('当前状态不能取消');

        $order->save(['status' => 5]);
        return $this->success([], '已取消');
    }

    public function complete($id)
    {
        $order = SaleOrder::where('deleted_at', null)->find($id);
        if (!$order) return $this->error('销售订单不存在');
        if ($order->status != 2) return $this->error('只有已审核可完成');

        $order->save(['status' => 4]);
        return $this->success([], '订单已完成');
    }

    public function items($id)
    {
        return $this->success(
            SaleOrderItem::with(['product.category'])->where('sale_order_id', $id)->select()
        );
    }

    public function addItem($id)
    {
        $order = SaleOrder::where('deleted_at', null)->find($id);
        if (!$order) return $this->error('销售订单不存在');
        if ($order->status != 1) return $this->error('只有待审核可添加明细');

        $post = input('post.');
        $validate = new \think\Validate([
            'product_id' => 'require|integer',
            'quantity'   => 'require|integer|gt:0',
            'price'      => 'require|float|gt:0'
        ]);
        if (!$validate->check($post)) return $this->error($validate->getError());

        Db::transaction(function () use ($order, $post) {
            $stock = Stock::where('product_id', $post['product_id'])
                ->where('warehouse_id', $order->warehouse_id)->find();
            if (!$stock || $stock->quantity < $post['quantity']) {
                $prod = Product::find($post['product_id']);
                throw new \Exception("商品 {$prod->name} 库存不足");
            }
            $rowTotal = $post['quantity'] * $post['price'];
            SaleOrderItem::create([
                'sale_order_id'     => $order->id,
                'product_id'        => $post['product_id'],
                'quantity'          => $post['quantity'],
                'delivered_quantity' => 0,
                'price'             => $post['price'],
                'total_amount'      => $rowTotal,
            ]);
            $total = $order->total_amount + $rowTotal;
            $discountAmount = $total * (1 - ($order->discount_amount / $order->total_amount));
            $order->save([
                'total_amount'    => $total,
                'discount_amount' => $discountAmount,
                'final_amount'    => $total - $discountAmount,
            ]);
        });

        return $this->success([], '明细添加成功');
    }

    public function updateItem($id, $item_id)
    {
        $order = SaleOrder::where('deleted_at', null)->find($id);
        if (!$order) return $this->error('销售订单不存在');
        if ($order->status != 1) return $this->error('只有待审核可修改明细');

        $item = SaleOrderItem::where('sale_order_id', $id)->find($item_id);
        if (!$item) return $this->error('明细不存在');

        $post = input('post.');
        $oldTotal = $item->total_amount;
        $newQty   = $post['quantity'] ?? $item->quantity;
        $newPrice = $post['price'] ?? $item->price;
        $newTotal = $newQty * $newPrice;

        // ========== 新增：检查是否超过已出库数量 ==========
        if ($newQty < $item->delivered_quantity) {
            throw new \Exception("新数量 {$newQty} 小于已出库数量 {$item->delivered_quantity}");
        }
        // ========== 新增结束 ==========

        Db::transaction(function () use ($item, $order, $oldTotal, $newTotal, $newQty, $newPrice) {
            if ($newQty != $item->quantity) {
                $stock = Stock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $order->warehouse_id)->find();
                if (!$stock || $stock->quantity < $newQty) {
                    $prod = Product::find($item->product_id);
                    throw new \Exception("商品 {$prod->name} 库存不足");
                }
            }
            $item->save(['quantity' => $newQty, 'price' => $newPrice, 'total_amount' => $newTotal]);
            $total = $order->total_amount - $oldTotal + $newTotal;
            $discountAmount = $total * (1 - ($order->discount_amount / $order->total_amount));
            $order->save([
                'total_amount'    => $total,
                'discount_amount' => $discountAmount,
                'final_amount'    => $total - $discountAmount,
            ]);
        });

        return $this->success([], '明细更新成功');
    }

    public function deleteItem($id, $item_id)
    {
        $order = SaleOrder::where('deleted_at', null)->find($id);
        if (!$order) return $this->error('销售订单不存在');
        if ($order->status != 1) return $this->error('只有待审核可删除明细');

        $item = SaleOrderItem::where('sale_order_id', $id)->find($item_id);
        if (!$item) return $this->error('明细不存在');

        // ========== 新增：检查是否已有出库记录 ==========
        if ($item->delivered_quantity > 0) {
            return $this->error('该商品已有出库记录，无法删除');
        }
        // ========== 新增结束 ==========

        Db::transaction(function () use ($item, $order) {
            $total = $order->total_amount - $item->total_amount;
            $discountAmount = $total * (1 - ($order->discount_amount / $order->total_amount));
            $order->save([
                'total_amount'    => $total,
                'discount_amount' => $discountAmount,
                'final_amount'    => $total - $discountAmount,
            ]);
            $item->delete();
        });

        return $this->success([], '明细删除成功');
    }

    private function generateOrderNo($prefix = 'SO')
    {
        $date = date('Ymd');
        $num  = SaleOrder::whereLike('order_no', $prefix . $date . '%')->count() + 1;
        return $prefix . $date . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
    }
}
