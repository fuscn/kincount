<?php
namespace app\kincount\model;
use think\model\relation\HasMany;

class PurchaseOrder extends BaseModel
{
    // 采购订单状态常量
    const STATUS_PENDING = 0;      // 待审核
    const STATUS_AUDITED = 1;      // 已审核
    const STATUS_PARTIAL = 2;      // 部分入库
    const STATUS_COMPLETED = 3;    // 已完成
    const STATUS_CANCELLED = 4;    // 已取消
    
    protected $type = [
        'supplier_id' => 'integer',
        'warehouse_id' => 'integer',
        'total_amount' => 'float',
        'paid_amount' => 'float',
        'status' => 'integer',
        'created_by' => 'integer',
        'audit_by' => 'integer'
    ];
    
    // 关联供应商
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
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
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }
    
    // 关联采购入库
    public function purchaseStocks()
    {
        return $this->hasMany(PurchaseStock::class);
    }
    
    // 采购订单状态选项
    public function getStatusOptions()
    {
        return [
            self::STATUS_PENDING => '待审核',
            self::STATUS_AUDITED => '已审核',
            self::STATUS_PARTIAL => '部分入库',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_CANCELLED => '已取消'
        ];
    }
    
    // 获取未付金额
    public function getUnpaidAmountAttr()
    {
        return $this->total_amount - $this->paid_amount;
    }
    
    // 获取入库进度
    public function getReceiptProgressAttr()
    {
        $totalQuantity = $this->items()->sum('quantity');
        $receivedQuantity = $this->items()->sum('received_quantity');
        
        if ($totalQuantity == 0) return 0;
        return round(($receivedQuantity / $totalQuantity) * 100, 2);
    }
     /**
     * 关联采购退货单
     */
    public function returns(): HasMany
    {
        return $this->hasMany(ReturnOrder::class, 'source_order_id')
            ->where('type', ReturnOrder::TYPE_PURCHASE)
            ->whereNull('deleted_at');
    }
    
    /**
     * 获取可退货商品明细
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
                    'received_quantity' => $item->received_quantity,
                    'returned_quantity' => $item->returned_quantity,
                    'returnable_quantity' => $returnableQty,
                    'price' => $item->price,
                    'total_amount' => $item->total_amount
                ];
            }
        }
        
        return $returnableItems;
    }
}