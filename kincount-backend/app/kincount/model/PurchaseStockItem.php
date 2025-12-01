<?php

namespace app\kincount\model;


class PurchaseStockItem extends BaseModel
{


    protected $type = [
        'purchase_stock_id' => 'integer',
        'product_id' => 'integer',
        'sku_id' => 'integer', // 确保有这个字段定义
        'quantity' => 'integer',
        'price' => 'float',
        'total_amount' => 'float'
    ];

    // 关联采购入库
    public function purchaseStock()
    {
        return $this->belongsTo(PurchaseStock::class);
    }

    // 关联商品
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    // 添加 SKU 关联
    public function sku()
    {
        return $this->belongsTo(ProductSku::class, 'sku_id');
    }
}
