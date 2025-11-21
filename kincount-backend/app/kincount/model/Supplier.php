<?php
namespace app\kincount\model;

class Supplier extends BaseModel
{
    
    protected $type = [
        'arrears_amount' => 'float',
        'status' => 'integer'
    ];
    
    // 关联采购订单
    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
    
    // 关联采购入库
    public function purchaseStocks()
    {
        return $this->hasMany(PurchaseStock::class);
    }
    
    // 关联应付款项
    public function accountPayables()
    {
        return $this->hasMany(AccountRecord::class, 'target_id')
            ->where('type', 2);
    }
    
    // 获取总应付金额
    public function getTotalPayableAttr()
    {
        return $this->accountPayables()->sum('balance_amount');
    }
}