<?php

namespace app\kincount\model;


class PurchaseOrderItem extends BaseModel
{

    protected $type = [
        'sku_id' => 'integer', // 添加这一行
        'purchase_order_id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'integer',
        'received_quantity' => 'integer',
        'price' => 'float',
        'total_amount' => 'float'
    ];

    // 关联采购订单
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    // 关联商品
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // 关联SKU
    public function sku()
    {
        return $this->belongsTo(ProductSku::class);
    }
    // 获取未入库数量
    public function getUnreceivedQuantityAttr()
    {
        return $this->quantity - $this->received_quantity;
    }
}
