<?php
namespace app\kincount\model;

class SaleStock extends BaseModel
{
    
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
            1 => '待审核',
            2 => '已审核',
            3 => '已取消'
        ];
    }
    
    // 生成出库单号
    public function generateStockNo()
    {
        return $this->generateUniqueNo('SS');
    }
}