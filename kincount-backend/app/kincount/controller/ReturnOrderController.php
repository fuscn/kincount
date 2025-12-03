<?php
declare (strict_types = 1);

namespace app\kincount\controller;

use app\kincount\model\ReturnModel;
use app\kincount\model\ReturnItem;
use app\kincount\model\ReturnStock;
use app\kincount\model\ReturnStockItem;
use app\kincount\model\Customer;
use app\kincount\model\Supplier;
use app\kincount\model\AccountRecord;
use app\kincount\model\FinancialRecord;
use app\kincount\model\ProductSku;
use think\facade\Db;
use think\Validate;

class ReturnController extends BaseController
{
    /**
     * 退货单列表（支持按类型筛选）
     */
    public function index()
    {
        $page = (int)input('page', 1);
        $limit = (int)input('limit', 15);
        $keyword = input('keyword', '');
        $type = input('type', '');
        $status = input('status', '');
        $startDate = input('start_date', '');
        $endDate = input('end_date', '');

        $query = ReturnModel::where('deleted_at', null)
            ->with(['creator', 'auditor', 'target', 'warehouse']);

        // 关键词搜索（单号/源单号/对方名称）
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('return_no', "%{$keyword}%")
                    ->whereOrLike('source_order_no', "%{$keyword}%")
                    ->whereOrHas('customer', function ($cq) use ($keyword) {
                        $cq->whereLike('name', "%{$keyword}%");
                    })
                    ->whereOrHas('supplier', function ($sq) use ($keyword) {
                        $sq->whereLike('name', "%{$keyword}%");
                    });
            });
        }

        // 类型筛选
        if ($type !== '') {
            $query->where('type', $type);
        }

        // 状态筛选
        if ($status !== '') {
            $query->where('status', $status);
        }

        // 日期范围筛选
        if ($startDate) {
            $query->where('created_at', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate . ' 23:59:59');
        }

        $list = $query->order('id', 'desc')
            ->paginate(['list_rows' => $limit, 'page' => $page]);

        return $this->paginate($list);
    }

    /**
     * 创建退货单
     */
    public function save()
    {
        $post = input('post.');
        $validate = new Validate([
            'type' => 'require|in:1,2',
            'target_id' => 'require|integer',
            'warehouse_id' => 'require|integer',
            'source_order_id' => 'integer',
            'source_stock_id' => 'integer',
            'return_type' => 'in:1,2,3,4',
            'items' => 'require|array',
            'items.*.product_id' => 'require|integer',
            'items.*.sku_id' => 'require|integer',
            'items.*.return_quantity' => 'require|integer|gt:0',
            'items.*.price' => 'require|float|gt:0',
        ]);

        if (!$validate->check($post)) {
            return $this->error($validate->getError());
        }

        // 验证目标对象存在性
        if ($post['type'] == ReturnModel::TYPE_SALE) {
            $customer = Customer::find($post['target_id']);
            if (!$customer) {
                return $this->error('客户不存在');
            }
        } else {
            $supplier = Supplier::find($post['target_id']);
            if (!$supplier) {
                return $this->error('供应商不存在');
            }
        }

        Db::transaction(function () use ($post) {
            // 计算总金额
            $totalAmount = '0';
            foreach ($post['items'] as &$item) {
                // 确保金额为字符串类型并保留2位小数
                $item['total_amount'] = number_format($item['return_quantity'] * $item['price'], 2, '.', '');
                $totalAmount = bcadd($totalAmount, $item['total_amount'], 2);
            }

            // 创建退货单
            $return = new ReturnModel();
            $return->type = (int)$post['type'];
            $return->target_id = (int)$post['target_id'];
            $return->warehouse_id = (int)$post['warehouse_id'];
            $return->source_order_id = $post['source_order_id'] ?? 0;
            $return->source_stock_id = $post['source_stock_id'] ?? 0;
            $return->total_amount = $totalAmount;
            $return->refund_amount = $totalAmount; // 默认为全额退款
            $return->return_type = $post['return_type'] ?? 1;
            $return->remark = $post['remark'] ?? '';
            $return->status = ReturnModel::STATUS_PENDING_AUDIT;
            $return->stock_status = ReturnModel::STOCK_PENDING;
            $return->refund_status = ReturnModel::REFUND_PENDING;
            $return->created_by = $this->getUserId();
            $return->save();

            // 创建退货明细
            foreach ($post['items'] as $item) {
                ReturnItem::create([
                    'return_id' => $return->id,
                    'product_id' => (int)$item['product_id'],
                    'sku_id' => (int)$item['sku_id'],
                    'source_order_item_id' => $item['source_order_item_id'] ?? 0,
                    'source_stock_item_id' => $item['source_stock_item_id'] ?? 0,
                    'return_quantity' => (int)$item['return_quantity'],
                    'price' => number_format($item['price'], 2, '.', ''),
                    'total_amount' => $item['total_amount'],
                ]);
            }
        });

        return $this->success([], '退货单创建成功');
    }

    /**
     * 退货单详情
     */
    public function read($id)
    {
        $return = ReturnModel::where('deleted_at', null)
            ->with([
                'items' => function ($q) {
                    $q->with('product,sku,sourceOrderItem,sourceStockItem');
                },
                'stocks' => function ($q) {
                    $q->with('items,warehouse,auditor');
                },
                'refunds',
                'customer',
                'supplier',
                'warehouse',
                'sourceOrder',
                'sourceStock',
                'creator',
                'auditor'
            ])->find($id);

        if (!$return) {
            return $this->error('退货单不存在');
        }

        return $this->success($return);
    }

    /**
     * 更新退货单
     */
    public function update($id)
    {
        $return = ReturnModel::where('deleted_at', null)->find($id);
        if (!$return) {
            return $this->error('退货单不存在');
        }

        // 只有待审核状态可更新
        if ($return->status != ReturnModel::STATUS_PENDING_AUDIT) {
            return $this->error('只有待审核的退货单可更新');
        }

        $post = input('post.');
        $validate = new Validate([
            'warehouse_id' => 'integer',
            'return_type' => 'in:1,2,3,4',
            'items' => 'array',
            'items.*.product_id' => 'integer',
            'items.*.sku_id' => 'integer',
            'items.*.return_quantity' => 'integer|gt:0',
            'items.*.price' => 'float|gt:0',
        ]);

        if (!$validate->check($post)) {
            return $this->error($validate->getError());
        }

        Db::transaction(function () use ($post, $return) {
            // 更新主表信息
            $updateData = [
                'warehouse_id' => $post['warehouse_id'] ?? $return->warehouse_id,
                'return_type' => $post['return_type'] ?? $return->return_type,
                'remark' => $post['remark'] ?? $return->remark,
            ];
            $return->save($updateData);

            // 处理明细（先删后增）
            if (!empty($post['items'])) {
                ReturnItem::where('return_id', $return->id)->delete();
                
                $totalAmount = '0';
                foreach ($post['items'] as $item) {
                    $itemTotal = number_format($item['return_quantity'] * $item['price'], 2, '.', '');
                    $totalAmount = bcadd($totalAmount, $itemTotal, 2);
                    
                    ReturnItem::create([
                        'return_id' => $return->id,
                        'product_id' => (int)$item['product_id'],
                        'sku_id' => (int)$item['sku_id'],
                        'source_order_item_id' => $item['source_order_item_id'] ?? 0,
                        'source_stock_item_id' => $item['source_stock_item_id'] ?? 0,
                        'return_quantity' => (int)$item['return_quantity'],
                        'price' => number_format($item['price'], 2, '.', ''),
                        'total_amount' => $itemTotal,
                    ]);
                }
                
                // 更新总金额
                $return->save([
                    'total_amount' => $totalAmount,
                    'refund_amount' => $totalAmount
                ]);
            }
        });

        return $this->success([], '退货单更新成功');
    }

    /**
     * 删除退货单
     */
    public function delete($id)
    {
        $return = ReturnModel::where('deleted_at', null)->find($id);
        if (!$return) {
            return $this->error('退货单不存在');
        }

        // 只有待审核状态可删除
        if ($return->status != ReturnModel::STATUS_PENDING_AUDIT) {
            return $this->error('只有待审核的退货单可删除');
        }

        Db::transaction(function () use ($return) {
            // 级联删除明细和出入库单
            ReturnItem::where('return_id', $return->id)->delete();
            $stocks = ReturnStock::where('return_id', $return->id)->select();
            foreach ($stocks as $stock) {
                ReturnStockItem::where('return_stock_id', $stock->id)->delete();
                $stock->delete();
            }
            $return->delete();
        });

        return $this->success([], '退货单删除成功');
    }

    /**
     * 审核退货单
     */
    public function audit($id)
    {
        $return = ReturnModel::where('deleted_at', null)->find($id);
        if (!$return) {
            return $this->error('退货单不存在');
        }

        if ($return->status != ReturnModel::STATUS_PENDING_AUDIT) {
            return $this->error('只有待审核的退货单可审核');
        }

        Db::transaction(function () use ($return) {
            // 更新退货单状态
            $return->status = ReturnModel::STATUS_AUDITED;
            $return->audit_by = $this->getUserId();
            $return->audit_time = date('Y-m-d H:i:s');
            $return->save();

            // 创建账款记录
            $accountType = $return->type == ReturnModel::TYPE_SALE ? 1 : 2; // 1-应收(客户) 2-应付(供应商)
            $balanceAmount = $return->type == ReturnModel::TYPE_SALE 
                ? '-' . $return->refund_amount  // 销售退货减少应收
                : '-' . $return->refund_amount; // 采购退货减少应付

            AccountRecord::create([
                'type' => $accountType,
                'target_id' => $return->target_id,
                'related_id' => $return->id,
                'related_type' => 'return',
                'amount' => $return->refund_amount,
                'paid_amount' => '0',
                'balance_amount' => $balanceAmount,
                'status' => 1, // 未结清
                'remark' => "退货单[{$return->return_no}]账款",
            ]);
        });

        return $this->success([], '审核成功');
    }

    /**
     * 取消退货单
     */
    public function cancel($id)
    {
        $return = ReturnModel::where('deleted_at', null)->find($id);
        if (!$return) {
            return $this->error('退货单不存在');
        }

        // 已完成和已取消状态不能再取消
        if (in_array($return->status, [
            ReturnModel::STATUS_COMPLETED,
            ReturnModel::STATUS_CANCELLED
        ])) {
            return $this->error('当前状态无法取消');
        }

        Db::transaction(function () use ($return) {
            // 更新退货单状态
            $return->status = ReturnModel::STATUS_CANCELLED;
            $return->save();

            // 取消关联的出入库单
            $stocks = ReturnStock::where('return_id', $return->id)->where('status', '!=', 3)->select();
            foreach ($stocks as $stock) {
                $stock->status = 3; // 已取消
                $stock->save();
            }
        });

        return $this->success([], '取消成功');
    }

    /**
     * 完成退货单
     */
    public function complete($id)
    {
        $return = ReturnModel::where('deleted_at', null)->find($id);
        if (!$return) {
            return $this->error('退货单不存在');
        }

        // 检查状态是否允许完成
        if ($return->status == ReturnModel::STATUS_COMPLETED || $return->status == ReturnModel::STATUS_CANCELLED) {
            return $this->error('当前状态无法完成');
        }

        // 检查出入库和退款是否完成
        if ($return->stock_status != ReturnModel::STOCK_COMPLETE) {
            return $this->error('请先完成所有出入库操作');
        }
        if ($return->refund_status != ReturnModel::REFUND_COMPLETE) {
            return $this->error('请先完成所有退款操作');
        }

        $return->status = ReturnModel::STATUS_COMPLETED;
        $return->save();

        return $this->success([], '标记完成成功');
    }

    /**
     * 获取退货明细列表
     */
    public function items($id)
    {
        $items = ReturnItem::where('return_id', $id)
            ->whereNull('deleted_at')
            ->with('product,sku,sourceOrderItem,sourceStockItem')
            ->select();

        return $this->success($items);
    }

    /**
     * 添加退货明细
     */
    public function addItem($id)
    {
        $return = ReturnModel::where('deleted_at', null)->find($id);
        if (!$return) {
            return $this->error('退货单不存在');
        }
        if ($return->status != ReturnModel::STATUS_PENDING_AUDIT) {
            return $this->error('只有待审核的退货单可添加明细');
        }

        $post = input('post.');
        $validate = new Validate([
            'product_id' => 'require|integer',
            'sku_id' => 'require|integer',
            'return_quantity' => 'require|integer|gt:0',
            'price' => 'require|float|gt:0',
            'source_order_item_id' => 'integer',
            'source_stock_item_id' => 'integer',
        ]);

        if (!$validate->check($post)) {
            return $this->error($validate->getError());
        }

        // 计算总金额
        $totalAmount = number_format($post['return_quantity'] * $post['price'], 2, '.', '');

        // 创建明细
        $item = ReturnItem::create([
            'return_id' => (int)$id,
            'product_id' => (int)$post['product_id'],
            'sku_id' => (int)$post['sku_id'],
            'source_order_item_id' => $post['source_order_item_id'] ?? 0,
            'source_stock_item_id' => $post['source_stock_item_id'] ?? 0,
            'return_quantity' => (int)$post['return_quantity'],
            'price' => number_format($post['price'], 2, '.', ''),
            'total_amount' => $totalAmount,
        ]);

        // 更新退货单总金额
        $return->total_amount = bcadd($return->total_amount, $totalAmount, 2);
        $return->refund_amount = $return->total_amount;
        $return->save();

        return $this->success($item, '明细添加成功');
    }

    /**
     * 更新退货明细
     */
    public function updateItem($id, $item_id)
    {
        $return = ReturnModel::where('deleted_at', null)->find($id);
        if (!$return) {
            return $this->error('退货单不存在');
        }
        if ($return->status != ReturnModel::STATUS_PENDING_AUDIT) {
            return $this->error('只有待审核的退货单可更新明细');
        }

        $item = ReturnItem::where('id', $item_id)->where('return_id', $id)->find();
        if (!$item) {
            return $this->error('明细不存在');
        }

        $post = input('post.');
        $validate = new Validate([
            'return_quantity' => 'integer|gt:0',
            'price' => 'float|gt:0',
        ]);

        if (!$validate->check($post)) {
            return $this->error($validate->getError());
        }

        // 计算金额变化
        $oldTotal = $item->total_amount;
        $newQuantity = $post['return_quantity'] ?? $item->return_quantity;
        $newPrice = $post['price'] ?? $item->price;
        $newTotal = number_format($newQuantity * $newPrice, 2, '.', '');

        // 更新明细
        $item->return_quantity = (int)$newQuantity;
        $item->price = number_format($newPrice, 2, '.', '');
        $item->total_amount = $newTotal;
        $item->save();

        // 更新退货单总金额
        $return->total_amount = bcadd($return->total_amount, bcsub($newTotal, $oldTotal, 2), 2);
        $return->refund_amount = $return->total_amount;
        $return->save();

        return $this->success([], '明细更新成功');
    }

    /**
     * 删除退货明细
     */
    public function deleteItem($id, $item_id)
    {
        $return = ReturnModel::where('deleted_at', null)->find($id);
        if (!$return) {
            return $this->error('退货单不存在');
        }
        if ($return->status != ReturnModel::STATUS_PENDING_AUDIT) {
            return $this->error('只有待审核的退货单可删除明细');
        }

        $item = ReturnItem::where('id', $item_id)->where('return_id', $id)->find();
        if (!$item) {
            return $this->error('明细不存在');
        }

        // 记录明细金额
        $itemTotal = $item->total_amount;

        // 删除明细
        $item->delete();

        // 更新退货单总金额
        $return->total_amount = bcsub($return->total_amount, $itemTotal, 2);
        $return->refund_amount = $return->total_amount;
        $return->save();

        return $this->success([], '明细删除成功');
    }

    /**
     * 退款/收款操作
     */
    public function refund($id)
    {
        $return = ReturnModel::where('deleted_at', null)->find($id);
        if (!$return) {
            return $this->error('退货单不存在');
        }

        // 检查状态
        if ($return->status == ReturnModel::STATUS_CANCELLED) {
            return $this->error('已取消的退货单无法操作退款');
        }
        if ($return->stock_status != ReturnModel::STOCK_COMPLETE) {
            return $this->error('请先完成出入库操作');
        }

        $post = input('post.');
        $validate = new Validate([
            'amount' => 'require|float|gt:0',
            'payment_method' => 'require',
            'remark' => 'max:500',
        ]);

        if (!$validate->check($post)) {
            return $this->error($validate->getError());
        }

        // 检查可退款金额
        $refundable = $return->getRefundableAmount();
        if ($post['amount'] > $refundable) {
            return $this->error("退款金额不能超过可退款金额（{$refundable}元）");
        }

        Db::transaction(function () use ($post, $return) {
            // 格式化金额
            $refundAmount = number_format($post['amount'], 2, '.', '');
            
            // 更新退货单退款金额
            $return->refunded_amount = bcadd($return->refunded_amount, $refundAmount, 2);
            
            // 更新退款状态
            if ($return->refunded_amount >= $return->refund_amount) {
                $return->refund_status = ReturnModel::REFUND_COMPLETE;
            } else {
                $return->refund_status = ReturnModel::REFUND_PART;
            }
            $return->save();

            // 创建财务记录
            $financeType = $return->type == ReturnModel::TYPE_SALE ? 2 : 1; // 1-收入(采购退货收款) 2-支出(销售退货退款)
            FinancialRecord::create([
                'record_no' => $this->generateFinanceNo($financeType),
                'type' => $financeType,
                'category' => $financeType == 1 ? '采购退货收款' : '销售退货退款',
                'amount' => $refundAmount,
                'payment_method' => $post['payment_method'],
                'remark' => $post['remark'] ?? "关联退货单:{$return->return_no}",
                'record_date' => date('Y-m-d'),
                'created_by' => $this->getUserId(),
            ]);

            // 更新账款记录
            $accountRecord = AccountRecord::where('related_type', 'return')
                ->where('related_id', $return->id)
                ->where('status', 1)
                ->find();
                
            if ($accountRecord) {
                $accountRecord->paid_amount = bcadd($accountRecord->paid_amount, $refundAmount, 2);
                if ($accountRecord->paid_amount >= abs($accountRecord->balance_amount)) {
                    $accountRecord->status = 2; // 已结清
                }
                $accountRecord->save();
            }
        });

        return $this->success([], '退款操作成功');
    }

    /**
     * 获取退款记录
     */
    public function refunds($id)
    {
        $return = ReturnModel::where('deleted_at', null)->find($id);
        if (!$return) {
            return $this->error('退货单不存在');
        }

        $records = FinancialRecord::where('remark', 'like', "%{$return->return_no}%")
            ->whereIn('category', ['采购退货收款', '销售退货退款'])
            ->order('created_at', 'desc')
            ->select();

        return $this->success($records);
    }

    /**
     * 获取关联出入库单列表
     */
    public function stocks($id)
    {
        $stocks = ReturnStock::where('return_id', $id)
            ->whereNull('deleted_at')
            ->with('warehouse,target,audiTor')
            ->order('id', 'desc')
            ->select();

        return $this->success($stocks);
    }

    /**
     * 创建退货出入库单
     */
    public function createStock($id)
    {
        $return = ReturnModel::where('deleted_at', null)->with('items')->find($id);
        if (!$return) {
            return $this->error('退货单不存在');
        }

        // 检查状态
        if ($return->status == ReturnModel::STATUS_CANCELLED) {
            return $this->error('已取消的退货单无法创建出入库单');
        }
        if ($return->status == ReturnModel::STATUS_PENDING_AUDIT) {
            return $this->error('请先审核退货单');
        }

        // 检查是否有未处理的明细
        $pendingItems = [];
        foreach ($return->items as $item) {
            $pending = $item->getPendingQuantity();
            if ($pending > 0) {
                $pendingItems[] = [
                    'item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'sku_id' => $item->sku_id,
                    'pending_quantity' => $pending,
                    'price' => $item->price,
                ];
            }
        }
        if (empty($pendingItems)) {
            return $this->error('所有明细已完成出入库处理');
        }

        Db::transaction(function () use ($return, $pendingItems) {
            // 创建出入库单
            $stock = new ReturnStock();
            $stock->return_id = $return->id;
            $stock->target_id = $return->target_id;
            $stock->warehouse_id = $return->warehouse_id;
            $stock->status = ReturnStock::STATUS_PENDING_AUDIT;
            $stock->remark = "关联退货单:{$return->return_no}";
            $stock->created_by = $this->getUserId();
            $stock->save();

            // 创建出入库明细
            $totalAmount = '0';
            foreach ($pendingItems as $item) {
                $itemAmount = number_format($item['pending_quantity'] * $item['price'], 2, '.', '');
                $totalAmount = bcadd($totalAmount, $itemAmount, 2);

                ReturnStockItem::create([
                    'return_stock_id' => $stock->id,
                    'return_item_id' => $item['item_id'],
                    'product_id' => $item['product_id'],
                    'sku_id' => $item['sku_id'],
                    'quantity' => $item['pending_quantity'],
                    'price' => $item['price'],
                    'total_amount' => $itemAmount,
                ]);
            }

            // 更新出入库单总金额
            $stock->total_amount = $totalAmount;
            $stock->save();
        });

        return $this->success([], '出入库单创建成功');
    }

    /**
     * 生成财务单号
     */
    private function generateFinanceNo(int $type): string
    {
        $prefix = $type == 1 ? 'IN' : 'OUT';
        return $prefix . date('Ymd') . rand(1000, 9999);
    }
}