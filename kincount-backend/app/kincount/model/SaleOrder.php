<?php
namespace app\kincount\model;

class SaleOrder extends BaseModel
{
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
    
    // 销售订单状态选项
    public function getStatusOptions()
    {
        return [
            1 => '待审核',
            2 => '已审核',
            3 => '部分出库',
            4 => '已完成',
            5 => '已取消'
        ];
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
}