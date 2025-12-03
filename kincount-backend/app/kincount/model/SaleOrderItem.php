<?php

namespace app\kincount\model;

use think\model\relation\HasMany;


class SaleOrderItem extends BaseModel
{
    protected $type = [
        'sale_order_id' => 'integer',
        'product_id' => 'integer',
        'sku_id' => 'integer',
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
    // 关联SKU
    public function sku()
    {
        return $this->belongsTo(ProductSku::class, 'sku_id');
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
    /**
     * 关联销售退货明细
     */
    public function returnItems(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'source_order_item_id')
            ->whereHas('return', function ($query) {
                $query->where('type', ReturnModel::TYPE_SALE);
            });
    }
    /**
     * 获取可退货数量
     */
    public function getReturnableQuantity(): int
    {
        // 已出库数量 - 已退货数量
        return max(0, $this->delivered_quantity - $this->returned_quantity);
    }

    /**
     * 获取已退货数量
     */
    public function getReturnedQuantity(): int
    {
        return $this->returned_quantity;
    }

    /**
     * 获取已退货金额
     */
    public function getReturnedAmount(): float
    {
        return $this->returned_amount;
    }

    /**
     * 获取退货明细
     */
    public function getReturnDetails(): array
    {
        $returnItems = $this->returnItems()->select();
        $details = [];

        foreach ($returnItems as $returnItem) {
            $return = $returnItem->return;
            $details[] = [
                'return_id' => $return->id,
                'return_no' => $return->return_no,
                'return_quantity' => $returnItem->return_quantity,
                'return_amount' => $returnItem->total_amount,
                'processed_quantity' => $returnItem->processed_quantity,
                'return_type' => $return->return_type,
                'status' => $return->status,
                'return_date' => $return->created_at
            ];
        }

        return $details;
    }

 /**
     * 获取完整商品信息 - 简化为通过关联获取
     */
    public function getFullProductInfo(): array
    {
        // 预加载product和sku关联后使用
        if (!$this->product || !$this->sku) {
            // 如果没有加载关联，返回基本信息
            return [
                'product_id' => $this->product_id,
                'sku_id' => $this->sku_id,
                'product_name' => '',
                'sku_spec' => '',
                'unit' => '',
                'barcode' => '',
                'cost_price' => 0,
                'sale_price' => 0
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
            'unit' => $this->product->unit,
            'cost_price' => $this->sku->cost_price ?? $this->product->cost_price,
            'sale_price' => $this->sku->sale_price ?? $this->product->sale_price,
            'image' => $this->product->images ? json_decode($this->product->images, true)[0] ?? '' : ''
        ];
    }
}
