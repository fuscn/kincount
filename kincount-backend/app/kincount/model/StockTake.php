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
    
    // 动态属性：总差异绝对值
    protected function getTotalAbsDifferenceAttr()
    {
        $items = StockTakeItem::where('stock_take_id', $this->id)
            ->where('deleted_at', null)
            ->select();

        $total = 0;
        foreach ($items as $v) {
            $total += abs($v->difference_amount); // 使用绝对值
        }
        return $total;
    }
    // 动态属性：总差异数量绝对值
    protected function getTotalAbsDifferenceQuantityAttr()
    {
        $items = StockTakeItem::where('stock_take_id', $this->id)
            ->where('deleted_at', null)
            ->select();

        $total = 0;
        foreach ($items as $v) {
            $total += abs($v->difference_quantity); // 使用绝对值
        }
        return $total;
    }
    
    /**
     * 状态选项
     */
    const STATUS_OPTIONS = [
        ['value' => 0, 'label' => '待盘点'],
        ['value' => 1, 'label' => '盘点中'],
        ['value' => 2, 'label' => '已审核'],
        ['value' => 3, 'label' => '已完成'],
        ['value' => 4, 'label' => '已取消']
    ];
    // 状态选项获取方法
    public static function getStatusOptions()
    {
        return self::STATUS_OPTIONS;
    }
    
    // 生成盘点单号
    public function generateTakeNo()
    {
        return $this->generateUniqueNo('ST');
    }
    
    // 计算总差异（保留原方法，但确保使用绝对值累加）
    public function calculateTotalDifference()
    {
        $items = $this->items;
        $totalDifference = 0;
        $totalDifferenceQuantity = 0;

        foreach ($items as $item) {
            $totalDifference += abs($item->difference_amount);
            $totalDifferenceQuantity += abs($item->difference_quantity);
        }

        return [
            'total_difference' => $totalDifference,
            'total_difference_quantity' => $totalDifferenceQuantity
        ];
    }
}