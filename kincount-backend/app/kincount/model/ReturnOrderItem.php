<?php

namespace app\kincount\model;

use think\model\relation\BelongsTo;
use think\model\relation\HasMany;

class ReturnOrderItem extends BaseModel
{
    
    // 字段类型转换
    protected $type = [
        'return_quantity' => 'integer',
        'processed_quantity' => 'integer',
        'price' => 'float',
        'total_amount' => 'float'
    ];
    
    /**
     * 关联退货单
     */
    public function return(): BelongsTo
    {
        return $this->belongsTo(ReturnOrder::class, 'return_id');
    }
    
    /**
     * 关联商品（商品级）
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
    
    /**
     * 关联SKU（SKU级）
     */
    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class, 'sku_id');
    }
    
    /**
     * 关联源订单明细（销售或采购）
     */
    public function sourceOrderItem()
    {
        if ($this->return && $this->return->type == ReturnOrder::TYPE_SALE) {
            return $this->belongsTo(SaleOrderItem::class, 'source_order_item_id');
        } else {
            return $this->belongsTo(PurchaseOrderItem::class, 'source_order_item_id');
        }
    }
    
    /**
     * 关联源出入库明细
     */
    public function sourceStockItem()
    {
        if ($this->return && $this->return->type == ReturnOrder::TYPE_SALE) {
            return $this->belongsTo(SaleStockItem::class, 'source_stock_item_id');
        } else {
            return $this->belongsTo(PurchaseStockItem::class, 'source_stock_item_id');
        }
    }
    
    /**
     * 关联退货出入库明细
     */
    public function stockItems(): HasMany
    {
        return $this->hasMany(ReturnStockItem::class, 'return_item_id');
    }
    
    /**
     * 获取待处理数量
     */
    public function getPendingQuantity(): int
    {
        return max(0, $this->return_quantity - $this->processed_quantity);
    }
    
    /**
     * 获取处理进度
     */
    public function getProcessProgress(): array
    {
        return [
            'return_quantity' => $this->return_quantity,
            'processed_quantity' => $this->processed_quantity,
            'pending_quantity' => $this->getPendingQuantity(),
            'progress_rate' => $this->return_quantity > 0 ? 
                round($this->processed_quantity / $this->return_quantity * 100, 2) : 0
        ];
    }
    
    /**
     * 获取完整的商品信息（包含SKU规格）
     */
    public function getFullProductInfo(): array
    {
        $info = [
            'product_id' => $this->product_id,
            'sku_id' => $this->sku_id,
            'product_name' => '',
            'sku_spec' => '',
            'unit' => '',
            'barcode' => '',
            'image' => ''
        ];
        
        if ($this->product) {
            $info['product_name'] = $this->product->name;
            $info['unit'] = $this->product->unit;
            $info['image'] = $this->product->images ? json_decode($this->product->images, true)[0] ?? '' : '';
        }
        
        if ($this->sku) {
            $info['sku_spec'] = $this->sku->spec;
            $info['barcode'] = $this->sku->barcode;
        }
        
        return $info;
    }
    
    /**
     * 获取源单信息
     */
    public function getSourceInfo(): array
    {
        $info = [
            'source_order_item_id' => $this->source_order_item_id,
            'source_stock_item_id' => $this->source_stock_item_id,
            'original_quantity' => 0,
            'original_price' => 0
        ];
        
        if ($this->sourceOrderItem) {
            $info['original_quantity'] = $this->sourceOrderItem->quantity;
            $info['original_price'] = $this->sourceOrderItem->price;
        }
        
        return $info;
    }
}