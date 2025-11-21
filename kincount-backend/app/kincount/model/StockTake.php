<?php
namespace app\kincount\model;

class StockTake extends BaseModel
{
    protected $type = [
        'warehouse_id' => 'integer',
        'total_difference' => 'float',
        'status' => 'integer',
        'created_by' => 'integer',
        'audit_by' => 'integer'
    ];
    
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
    
    // 关联盘点明细
    public function items()
    {
        return $this->hasMany(StockTakeItem::class, 'stock_take_id');
    }
    
    // 库存盘点状态选项
    public function getStatusOptions()
    {
        return [
            1 => '盘点中',
            2 => '已完成',
            3 => '已取消'
        ];
    }
    
    // 生成盘点单号
    public function generateTakeNo()
    {
        return $this->generateUniqueNo('ST');
    }
    
    // 计算总差异金额
    public function calculateTotalDifference()
    {
        $total = $this->items()->sum('difference_amount');
        $this->total_difference = $total;
        return $this->save();
    }
}