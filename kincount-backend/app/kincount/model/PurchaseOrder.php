<?php
namespace app\kincount\model;

class PurchaseOrder extends BaseModel
{
    // 采购订单状态常量
    const STATUS_PENDING = 1;      // 待审核
    const STATUS_AUDITED = 2;      // 已审核
    const STATUS_PARTIAL = 3;      // 部分入库
    const STATUS_COMPLETED = 4;    // 已完成
    const STATUS_CANCELLED = 5;    // 已取消
    
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
}