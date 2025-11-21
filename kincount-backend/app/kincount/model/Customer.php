<?php
namespace app\kincount\model;

class Customer extends BaseModel
{
    
    protected $type = [
        'type' => 'integer',
        'level' => 'integer',
        'discount' => 'float',
        'credit_amount' => 'float',
        'arrears_amount' => 'float',
        'status' => 'integer'
    ];
    
    // 关联销售订单
    public function saleOrders()
    {
        return $this->hasMany(SaleOrder::class);
    }
    
    // 关联销售出库
    public function saleStocks()
    {
        return $this->hasMany(SaleStock::class);
    }
    
    // 关联应收款项
    public function accountReceivables()
    {
        return $this->hasMany(AccountRecord::class, 'target_id')
            ->where('type', 1);
    }
    
    // 客户类型选项
    public function getTypeOptions()
    {
        return [
            1 => '个人',
            2 => '公司'
        ];
    }
    
    // 客户等级选项
    public function getLevelOptions()
    {
        return [
            1 => '普通',
            2 => '银牌', 
            3 => '金牌'
        ];
    }
    
    // 获取总应收金额
    public function getTotalReceivableAttr()
    {
        return $this->accountReceivables()->sum('balance_amount');
    }
}