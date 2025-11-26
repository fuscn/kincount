<?php

namespace app\kincount\model;



class Product extends BaseModel

{
    protected $table = 'products';
    protected static function onBeforeInsert($model)
    {
        if (empty($model->product_no)) {
            $model->product_no = static::generateProductNo($model->category_id);
        }
    }
    // 自动类型转换
    protected $type = [
        'category_id'     => 'integer',
        'brand_id'        => 'integer',
        'cost_price'      => 'float',
        'sale_price'      => 'float',
        'wholesale_price' => 'float',
        'min_stock'       => 'integer',
        'max_stock'       => 'integer',
        'status'          => 'integer'
    ];

    // JSON 字段
    protected $json = ['images'];

    public function skus()
    {
        return $this->hasMany(ProductSku::class);
    }
    public function specDefinitions()
    {
        return $this->hasMany(ProductSpecDefinition::class);
    }
    // 关联分类
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    // 关联品牌
    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id', 'id');
    }

    // 关联库存
    public function stocks()
    {
        return $this->hasMany(Stock::class, 'sku_id', 'id');
    }

    // 状态选项 - 如果 BaseModel 中已定义，可以删除或重命名
    public function getProductStatusOptions()
    {
        return [
            0 => '下架',
            1 => '上架'
        ];
    }

    // 获取状态文本 - 重命名以避免冲突
    public function getProductStatusTextAttr()
    {
        $options = $this->getProductStatusOptions();
        return $options[$this->status] ?? '未知';
    }

    // 获取总库存
    public function getTotalStockAttr()
    {
        // 如果已经预加载了 stocks 关联，直接计算
        if (isset($this->stocks) && count($this->stocks) > 0) {
            return array_sum(array_column($this->stocks->toArray(), 'quantity'));
        }

        // 否则查询数据库
        return $this->stocks()->sum('quantity');
    }

    // 获取库存总价值
    public function getTotalValueAttr()
    {
        // 如果已经预加载了 stocks 关联，直接计算
        if (isset($this->stocks) && count($this->stocks) > 0) {
            return array_sum(array_column($this->stocks->toArray(), 'total_amount'));
        }

        // 否则查询数据库
        return $this->stocks()->sum('total_amount');
    }

    // 获取第一张图片
    public function getFirstImageAttr()
    {
        if (empty($this->images)) {
            return '/assets/images/default-product.png';
        }

        $images = $this->images;
        return is_array($images) && count($images) > 0 ? $images[0] : $images;
    }

    // 获取所有仓库的库存详情
    public function getWarehouseStocksAttr()
    {
        return $this->stocks()->with('warehouse')->select();
    }

    // 检查库存是否充足
    public function checkStock($requiredQuantity, $warehouseId = null)
    {
        if ($warehouseId) {
            $stock = $this->stocks()->where('warehouse_id', $warehouseId)->find();
            return $stock ? $stock->quantity >= $requiredQuantity : false;
        } else {
            return $this->total_stock >= $requiredQuantity;
        }
    }

    // 获取库存状态
    public function getStockStatusAttr()
    {
        $totalStock = $this->total_stock;

        if ($totalStock <= $this->min_stock) {
            return 'low';
        } elseif ($totalStock >= $this->max_stock) {
            return 'high';
        }

        return 'normal';
    }

    // 获取库存状态文本
    public function getStockStatusTextAttr()
    {
        $status = $this->stock_status;
        $texts = [
            'low'    => '库存过低',
            'high'   => '库存过高',
            'normal' => '库存正常'
        ];
        return $texts[$status] ?? '未知';
    }

    // 获取毛利润
    public function getGrossProfitAttr()
    {
        return $this->sale_price - $this->cost_price;
    }

    // 获取毛利率
    public function getGrossProfitRateAttr()
    {
        if ($this->sale_price <= 0) {
            return 0;
        }

        return round(($this->gross_profit / $this->sale_price) * 100, 2);
    }

    // 获取格式化价格
    public function getFormattedCostPriceAttr()
    {
        return '¥' . number_format($this->cost_price, 2);
    }

    public function getFormattedSalePriceAttr()
    {
        return '¥' . number_format($this->sale_price, 2);
    }

    public function getFormattedWholesalePriceAttr()
    {
        return $this->wholesale_price ? '¥' . number_format($this->wholesale_price, 2) : '-';
    }

    // 范围查询：上架商品
    public function scopeOnSale($query)
    {
        return $query->where('status', 1);
    }

    // 范围查询：下架商品
    public function scopeOffSale($query)
    {
        return $query->where('status', 0);
    }

    // 范围查询：低库存商品
    public function scopeLowStock($query)
    {
        return $query->alias('p')
            ->join('stocks s', 's.product_id = p.id')
            ->where('s.deleted_at', null)
            ->where('p.deleted_at', null)
            ->group('p.id')
            ->having('SUM(s.quantity) <= p.min_stock');
    }

    // 范围查询：根据分类
    public function scopeCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    // 范围查询：根据品牌
    public function scopeBrand($query, $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    // 搜索商品名称或编号
    public function scopeSearch($query, $keyword)
    {
        return $query->where(function ($q) use ($keyword) {
            $q->whereLike('name', "%{$keyword}%")
                ->whereOrLike('product_no', "%{$keyword}%");
        });
    }

    /**
     * 生成商品编号
     */
    public static function generateProductNo($categoryId = null)
    {
        // 1. 计算 2+2 分类段
        $lvl1 = '00';
        $lvl2 = '00';

        if ($categoryId) {
            $cat = Category::find($categoryId);
            if ($cat) {
                if ($cat->parent_id > 0) {          // 二级分类
                    $lvl2 = str_pad($cat->id, 2, '0', STR_PAD_LEFT);
                    $lvl1 = str_pad($cat->parent_id, 2, '0', STR_PAD_LEFT);
                } else {                            // 一级分类
                    $lvl1 = str_pad($cat->id, 2, '0', STR_PAD_LEFT);
                }
            }
        }

        $core = $lvl1 . $lvl2;          // 4 位数字

        // 2. 顺序号
        $last = self::where('product_no', 'like', 'P' . $core . '%')
            ->order('product_no', 'desc')
            ->find();

        if ($last) {
            $seq = intval(substr($last->product_no, 5)) + 1; // 去掉前 5 位 → P+4位
        } else {
            $seq = 1;
        }
        $seq = str_pad($seq, 4, '0', STR_PAD_LEFT);

        // 3. 返回 P + 4 位分类 + 4 位序号
        return 'P' . $core . $seq;      // 9 位
    }

    /**
     * 更新商品状态
     */
    public function updateStatus($status)
    {
        $this->status = $status;
        return $this->save();
    }

    /**
     * 获取商品完整名称（包含规格）
     */
    public function getFullNameAttr()
    {
        $name = $this->name;
        if ($this->spec) {
            $name .= " ({$this->spec})";
        }
        return $name;
    }

    /**
     * 商品上架
     */
    public function putOnSale()
    {
        return $this->updateStatus(1);
    }

    /**
     * 商品下架
     */
    public function putOffSale()
    {
        return $this->updateStatus(0);
    }
}
