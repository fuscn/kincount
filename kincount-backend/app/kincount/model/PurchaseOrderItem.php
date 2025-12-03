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
        return $this->belongsTo(ProductSku::class, 'sku_id');
    }
    // 获取未入库数量
    public function getUnreceivedQuantityAttr()
    {
        return $this->quantity - $this->received_quantity;
    }
    /**
     * 获取完整商品信息
     */
    public function getFullProductInfo(): array
    {
        if (!$this->product || !$this->sku) {
            return [
                'product_id' => $this->product_id,
                'sku_id' => $this->sku_id,
                'product_name' => '',
                'sku_spec' => '',
                'unit' => '',
                'barcode' => ''
            ];
        }

        return [
            'product_id' => $this->product_id,
            'sku_id' => $this->sku_id,
            'product_name' => $this->product->name,
            'product_no' => $this->product->product_no,
            'sku_spec' => $this->sku->spec,
            'sku_code' => $this->sku->sku_code,
            'barcode' => $this->sku->barcode,
            'unit' => $this->product->unit
        ];
    }

    /**
     * 获取可退货数量
     */
    public function getReturnableQuantity(): int
    {
        return max(0, $this->received_quantity - $this->returned_quantity);
    }
}
