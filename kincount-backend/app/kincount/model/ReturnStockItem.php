<?php

namespace app\kincount\model;

use think\model\relation\BelongsTo;

class ReturnStockItem extends BaseModel
{
    protected $table = 'return_stock_items';
    
    // 字段类型转换
    protected $type = [
        'quantity' => 'integer',
        'price' => 'float',
        'total_amount' => 'float'
    ];
    
    /**
     * 关联退货出入库单
     */
    public function returnStock(): BelongsTo
    {
        return $this->belongsTo(ReturnStock::class, 'return_stock_id');
    }
    
    /**
     * 关联退货明细
     */
    public function ReturnOrderItem(): BelongsTo
    {
        return $this->belongsTo(ReturnOrderItem::class, 'return_item_id');
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
     * 获取完整的商品信息
     */
    public function getFullProductInfo(): array
    {
        $info = [
            'product_id' => $this->product_id,
            'sku_id' => $this->sku_id,
            'product_name' => '',
            'sku_spec' => '',
            'unit' => '',
            'barcode' => ''
        ];
        
        if ($this->product) {
            $info['product_name'] = $this->product->name;
            $info['unit'] = $this->product->unit;
        }
        
        if ($this->sku) {
            $info['sku_spec'] = $this->sku->spec;
            $info['barcode'] = $this->sku->barcode;
        }
        
        return $info;
    }
    
    /**
     * 获取退货单信息
     */
    public function getReturnInfo(): array
    {
        $info = [
            'return_no' => '',
            'return_type' => 0
        ];
        
        if ($this->ReturnOrderItem && $this->ReturnOrderItem->return) {
            $info['return_no'] = $this->ReturnOrderItem->return->return_no;
            $info['return_type'] = $this->ReturnOrderItem->return->type;
        }
        
        return $info;
    }
    
    /**
     * 获取出入库方向
     */
    public function getStockDirection(): string
    {
        if ($this->ReturnOrderItem && $this->ReturnOrderItem->return) {
            return $this->ReturnOrderItem->return->type == ReturnOrder::TYPE_SALE ? '入库' : '出库';
        }
        return '';
    }
}