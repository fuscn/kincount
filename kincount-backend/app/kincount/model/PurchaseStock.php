<?php
namespace app\kincount\model;
use think\model\relation\HasMany;
class PurchaseStock extends BaseModel
{
    // 采购入库状态常量
    const STATUS_PENDING = 0;      // 待审核
    const STATUS_AUDITED = 1;      // 已审核
    const STATUS_CANCELLED = 2;    // 已取消
    
    protected $type = [
        'purchase_order_id' => 'integer',
        'supplier_id' => 'integer',
        'warehouse_id' => 'integer',
        'total_amount' => 'float',
        'status' => 'integer',
        'created_by' => 'integer',
        'audit_by' => 'integer'
    ];
    
    // 关联采购订单
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
    
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
    
    // 关联入库明细
    public function items()
    {
        return $this->hasMany(PurchaseStockItem::class, 'purchase_stock_id');
    }
    
    // 采购入库状态选项
    public function getStatusOptions()
    {
        return [
            self::STATUS_PENDING => '待审核',
            self::STATUS_AUDITED => '已审核',
            self::STATUS_CANCELLED => '已取消'
        ];
    }
    
    // 生成入库单号
    public function generateStockNo()
    {
        return $this->generateUniqueNo('PS');
    }
    /**
     * 关联采购退货单
     */
    public function returns(): HasMany
    {
        return $this->hasMany(ReturnOrder::class, 'source_stock_id')
            ->where('type', ReturnOrder::TYPE_PURCHASE)
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