<?php

namespace app\kincount\model;

use think\model\relation\HasMany;

class SaleOrder extends BaseModel
{
    // 销售订单状态常量
    const STATUS_PENDING = 1;      // 待审核
    const STATUS_AUDITED = 2;      // 已审核
    const STATUS_PARTIAL = 3;      // 部分出库
    const STATUS_COMPLETED = 4;    // 已完成
    const STATUS_CANCELLED = 5;    // 已取消

    // 销售订单状态选项
    public function getStatusOptions()
    {
        return [
            self::STATUS_PENDING => '待审核',
            self::STATUS_AUDITED => '已审核',
            self::STATUS_PARTIAL => '部分出库',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_CANCELLED => '已取消'
        ];
    }

    protected $type = [
        'customer_id' => 'integer',
        'warehouse_id' => 'integer',
        'total_amount' => 'float',
        'discount_amount' => 'float',
        'final_amount' => 'float',
        'paid_amount' => 'float',
        'status' => 'integer',
        'created_by' => 'integer',
        'audit_by' => 'integer'
    ];

    // 关联客户
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    // 关联销售退货单

    public function returns(): HasMany
    {
        return $this->hasMany(ReturnModel::class, 'source_order_id')
            ->where('type', ReturnModel::TYPE_SALE)
            ->whereNull('deleted_at');
    }
    // 关联仓库
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    // 关联创建人
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // 关联审核人
    public function auditor()
    {
        return $this->belongsTo(User::class, 'audit_by');
    }

    // 关联订单明细
    public function items()
    {
        return $this->hasMany(SaleOrderItem::class, 'sale_order_id');
    }

    // 关联销售出库
    public function saleStocks()
    {
        return $this->hasMany(SaleStock::class);
    }



    // 获取未收金额
    public function getUnpaidAmountAttr()
    {
        return $this->final_amount - $this->paid_amount;
    }

    // 获取出库进度
    public function getDeliveryProgressAttr()
    {
        $totalQuantity = $this->items()->sum('quantity');
        $deliveredQuantity = $this->items()->sum('delivered_quantity');

        if ($totalQuantity == 0) return 0;
        return round(($deliveredQuantity / $totalQuantity) * 100, 2);
    }
    /**
     * 获取可退货商品明细（需要预加载items.product, items.sku）
     */
    public function getReturnableItems(): array
    {
        $returnableItems = [];

        foreach ($this->items as $item) {
            $returnableQty = $item->getReturnableQuantity();
            if ($returnableQty > 0) {
                $returnableItems[] = [
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'sku_id' => $item->sku_id,
                    'product_info' => $item->getFullProductInfo(),
                    'quantity' => $item->quantity,
                    'delivered_quantity' => $item->delivered_quantity,
                    'returned_quantity' => $item->returned_quantity,
                    'returnable_quantity' => $returnableQty,
                    'price' => $item->price,
                    'total_amount' => $item->total_amount
                ];
            }
        }

        return $returnableItems;
    }


    /**
     * 获取已退货总金额
     */
    public function getTotalReturnedAmount(): float
    {
        $total = 0;
        $returns = $this->returns()
            ->where('status', '>=', ReturnModel::STATUS_AUDITED)
            ->select();

        foreach ($returns as $return) {
            $total += $return->total_amount;
        }

        return $total;
    }

    /**
     * 获取退货统计
     */
    public function getReturnStatistics(): array
    {
        $returns = $this->returns()->select();

        $statistics = [
            'total_count' => $returns->count(),
            'pending_count' => 0,
            'completed_count' => 0,
            'total_amount' => 0,
            'returned_amount' => 0
        ];

        foreach ($returns as $return) {
            $statistics['total_amount'] += $return->total_amount;
            $statistics['returned_amount'] += $return->refunded_amount;

            if ($return->status == ReturnModel::STATUS_COMPLETED) {
                $statistics['completed_count']++;
            } elseif (in_array($return->status, [
                ReturnModel::STATUS_PENDING_AUDIT,
                ReturnModel::STATUS_AUDITED,
                ReturnModel::STATUS_PART_STOCK,
                ReturnModel::STATUS_STOCK_COMPLETE,
                ReturnModel::STATUS_REFUND_COMPLETE
            ])) {
                $statistics['pending_count']++;
            }
        }

        return $statistics;
    }
}
