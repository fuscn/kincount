<?php
namespace app\kincount\model;


class StockTransferItem extends BaseModel
{
    
    protected $type = [
        'stock_transfer_id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'integer',
        'cost_price' => 'float',
        'total_amount' => 'float'
    ];
    
    // 关联调拨单
    public function stockTransfer()
    {
        return $this->belongsTo(StockTransfer::class);
    }
    
    // 关联商品
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
        // 关联SKU
    public function sku()
    {
        return $this->belongsTo(ProductSku::class,'sku_id');
    }
}