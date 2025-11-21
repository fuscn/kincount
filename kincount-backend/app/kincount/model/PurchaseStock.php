<?php
namespace app\kincount\model;

class PurchaseStock extends BaseModel
{
    
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
            1 => '待审核',
            2 => '已审核',
            3 => '已取消'
        ];
    }
    
    // 生成入库单号
    public function generateStockNo()
    {
        return $this->generateUniqueNo('PS');
    }
}