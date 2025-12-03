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
                'unit' => ''
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
        return max(0, $this->quantity - $this->returned_quantity);
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
