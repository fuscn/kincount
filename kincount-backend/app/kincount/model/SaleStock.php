<?php

namespace app\kincount\model;

use think\model\relation\HasMany;

class SaleStock extends BaseModel
{
    // 销售出库状态常量
    const STATUS_PENDING = 1;      // 待审核
    const STATUS_AUDITED = 2;      // 已审核
    const STATUS_COMPLETED = 3;    // 已完成
    const STATUS_CANCELLED = 4;    // 已取消

    protected $type = [
        'sale_order_id' => 'integer',
        'customer_id' => 'integer',
        'warehouse_id' => 'integer',
        'total_amount' => 'float',
        'status' => 'integer',
        'created_by' => 'integer',
        'audit_by' => 'integer'
    ];

    // 关联销售订单
    public function saleOrder()
    {
        return $this->belongsTo(SaleOrder::class);
    }

    // 关联客户
    public function customer()
    {
        return $this->belongsTo(Customer::class);
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

    // 关联出库明细
    public function items()
    {
        return $this->hasMany(SaleStockItem::class, 'sale_stock_id');
    }

    // 销售出库状态选项
    public function getStatusOptions()
    {
        return [
            self::STATUS_PENDING => '待审核',
            self::STATUS_AUDITED => '已审核',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_CANCELLED => '已取消'
        ];
    }

    // 生成出库单号
    public function generateStockNo()
    {
        return $this->generateUniqueNo('SS');
    }
    /**
     * 关联销售退货单
     */
    public function returns(): HasMany
    {
        return $this->hasMany(ReturnOrder::class, 'source_stock_id')
            ->where('type', ReturnOrder::TYPE_SALE)
            ->whereNull('deleted_at');
    }
    /**
     * 获取可退货明细
     */
    public function getReturnableItems(): array
    {
        $returnableItems = [];

        foreach ($this->items as $item) {
            $returnableQty = $item->getReturnableQuantity();
            if ($returnableQty > 0) {
                $returnableItems[] = [
                    'stock_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'sku_id' => $item->sku_id,
                    'product_info' => $item->getFullProductInfo(),
                    'quantity' => $item->quantity,
                    'returned_quantity' => $item->returned_quantity,
                    'returnable_quantity' => $returnableQty,
                    'price' => $item->price
                ];
            }
        }

        return $returnableItems;
    }
}
