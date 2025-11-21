<?php

namespace app\kincount\model;

class ProductSku extends BaseModel
{
    protected $json = ['spec'];

    public function product()
    {
        return $this->belongsTo(Product::class,'product_id');
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class, 'sku_id','id');
    }
    // 定义与仓库的关联（确保这个方法存在）
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
    // 生成唯一 SKU 编码
    public static function generateSkuCode($productId)
    {
        return 'SKU-' . $productId . '-' . substr(md5(uniqid()), 0, 6);
    }

    // 规格文本 颜色:红胡桃 | 厚度:18mm
    public function getSpecTextAttr()
    {
        return implode(' | ', array_map(fn($k, $v) => "$k:$v", array_keys($this->spec), $this->spec));
    }
}
