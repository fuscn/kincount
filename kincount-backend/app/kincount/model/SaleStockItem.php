<?php
namespace app\kincount\model;

class SaleStockItem extends BaseModel
{
    protected $type = [
        'sale_stock_id' => 'integer',
        'product_id' => 'integer',
        'sku_id' => 'integer',
        'quantity' => 'integer',
        'price' => 'float',
        'total_amount' => 'float'
    ];
    
    // 关联销售出库
    public function saleStock()
    {
        return $this->belongsTo(SaleStock::class);
    }
    
    // 关联商品
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}