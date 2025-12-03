<?php

namespace app\kincount\model;

use think\model\relation\HasMany;

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
    // 关联SKU - 新增
    public function sku()
    {
        return $this->belongsTo(ProductSku::class, 'sku_id');
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
}
