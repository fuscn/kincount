<?php

namespace app\kincount\model;


class StockTakeItem extends BaseModel
{
    // 指定表名
    protected $table = 'stock_take_items';

    protected $type = [
        'stock_take_id' => 'integer',
        'product_id' => 'integer',
        'sku_id' => 'integer',
        'system_quantity' => 'integer',
        'actual_quantity' => 'integer',
        'difference_quantity' => 'integer',
        'cost_price' => 'float',
        'difference_amount' => 'float'
    ];

    // 关联盘点单
    public function stockTake()
    {
        return $this->belongsTo(StockTake::class);
    }

    // 关联商品
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    // 关联SKU
    public function sku()
    {
        return $this->belongsTo(ProductSku::class, 'sku_id');
    }
    // 差异类型
    public function getDifferenceTypeAttr()
    {
        if ($this->difference_quantity > 0) {
            return '盘盈';
        } elseif ($this->difference_quantity < 0) {
            return '盘亏';
        }
        return '正常';
    }
    // 获取绝对值的差异金额
    public function getAbsDifferenceAmountAttr()
    {
        return abs($this->difference_amount);
    }
    // 获取绝对值的差异数量
    public function getAbsDifferenceQuantityAttr()
    {
        return abs($this->difference_quantity);
    }
}
