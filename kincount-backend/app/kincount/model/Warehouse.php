<?php
namespace app\kincount\model;

class Warehouse extends BaseModel
{
    
    protected $type = [
        'capacity' => 'float',
        'status' => 'integer'
    ];
    
    // 关联库存
    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }
    
    // 关联采购订单
    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
    
    // 关联销售订单
    public function saleOrders()
    {
        return $this->hasMany(SaleOrder::class);
    }
    
    // 获取库存总价值
    public function getTotalStockValueAttr()
    {
        return $this->stocks()->sum('total_amount');
    }
    
    // 获取商品种类数量
    public function getProductCountAttr()
    {
        return $this->stocks()->count();
    }
}