<?php
namespace app\kincount\model;

class StockTake extends BaseModel
{
    // 指定表名
    protected $table = 'stock_takes';
    
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
    
    // 获取总差异金额（实时计算）
    public function getTotalDifferenceAttr($value, $data)
    {
        // 如果有关联的items数据已加载，则使用已加载的数据计算
        if (isset($this->items) && !empty($this->items)) {
            $total = 0;
            foreach ($this->items as $item) {
                $total += $item->difference_amount;
            }
            return $total;
        }
        // 否则通过数据库查询实时计算
        if (isset($this->id)) {
            return $this->items()->sum('difference_amount');
        }
        // 最后返回数据库中的原始值
        return $value ?? 0;
    }
    
    // 获取总差异数量（实时计算）
    public function getTotalDifferenceQuantityAttr($value, $data)
    {
        // 如果有关联的items数据已加载，则使用已加载的数据计算
        if (isset($this->items) && !empty($this->items)) {
            $total = 0;
            foreach ($this->items as $item) {
                $total += $item->difference_quantity;
            }
            return $total;
        }
        // 否则通过数据库查询实时计算
        if (isset($this->id)) {
            return $this->items()->sum('difference_quantity');
        }
        // 最后返回数据库中的原始值
        return $value ?? 0;
    }
    
    // 库存盘点状态选项
    public function getStatusOptions()
    {
        return [
            0 => '待盘点',
            1 => '盘点中',
            2 => '已审核',
            3 => '已完成',
            4 => '已取消'
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