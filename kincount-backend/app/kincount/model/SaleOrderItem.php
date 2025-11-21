<?php
namespace app\kincount\model;


class SaleOrderItem extends BaseModel
{

    protected $type = [
        'sale_order_id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'integer',
        'delivered_quantity' => 'integer',
        'price' => 'float',
        'total_amount' => 'float'
    ];
    
    // 关联销售订单
    public function saleOrder()
    {
        return $this->belongsTo(SaleOrder::class);
    }
    
    // 关联商品
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    
    // 获取未出库数量
    public function getUndeliveredQuantityAttr()
    {
        return $this->quantity - $this->delivered_quantity;
    }
}