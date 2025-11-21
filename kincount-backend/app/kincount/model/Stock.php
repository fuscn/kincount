<?php

namespace app\kincount\model;

class Stock extends BaseModel
{

    // 修复：移除不存在的 product_id 字段
    protected $type = [
        'warehouse_id' => 'integer',
        'quantity'     => 'integer',
        'cost_price'   => 'float',
        'total_amount' => 'float'
    ];

    // 自动完成字段（可选）
    protected $auto = [
        'total_amount'
    ];

    public function sku()
    {
        return $this->belongsTo(ProductSku::class, 'sku_id');
    }

    // 修复：移除有问题的 product 关联方法
    // 改为通过 sku 关联间接获取产品信息

    // 关联仓库
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }

    // 库存预警状态（改进版本）
    public function getWarningStatusAttr($value, $data)
    {
        // 通过 sku 关联获取产品信息
        if (isset($this->sku) && $this->sku && $this->sku->product) {
            $product = $this->sku->product;
            if ($data['quantity'] <= $product->min_stock) {
                return 'low';
            } elseif ($data['quantity'] >= $product->max_stock) {
                return 'high';
            }
            return 'normal';
        }

        // 如果没有预加载 sku 和 product，返回未知状态
        return 'unknown';
    }

    // 获取预警文本
    public function getWarningTextAttr()
    {
        $status = $this->warning_status;
        $texts = [
            'low'    => '库存过低',
            'high'   => '库存过高',
            'normal' => '库存正常',
            'unknown' => '状态未知'
        ];
        return $texts[$status] ?? '未知';
    }

    // 获取预警等级（用于颜色显示）
    public function getWarningLevelAttr()
    {
        $status = $this->warning_status;
        $levels = [
            'low'    => 'danger',
            'high'   => 'warning',
            'normal' => 'success',
            'unknown' => 'default'
        ];
        return $levels[$status] ?? 'default';
    }

    // 自动计算总金额
    protected function setTotalAmountAttr()
    {
        return bcmul($this->quantity, $this->cost_price, 2);
    }

    /**
     * 更新库存数量
     */
    public function updateQuantity($quantity, $type = 'adjust')
    {
        $oldQuantity = $this->quantity;

        switch ($type) {
            case 'in':  // 入库
                $this->quantity += $quantity;
                break;
            case 'out': // 出库
                if ($this->quantity < $quantity) {
                    throw new \Exception('库存不足，当前库存：' . $this->quantity);
                }
                $this->quantity -= $quantity;
                break;
            default:    // 直接调整
                $this->quantity = $quantity;
        }

        // 自动更新总金额
        $this->total_amount = $this->setTotalAmountAttr();

        return $this->save();
    }

    /**
     * 检查库存是否充足
     */
    public function checkStock($requiredQuantity)
    {
        return $this->quantity >= $requiredQuantity;
    }

    /**
     * 获取安全库存比例 (0-1)
     */
    public function getSafetyRatioAttr()
    {
        // 通过 sku 关联获取产品信息
        if (isset($this->sku) && $this->sku && $this->sku->product) {
            $product = $this->sku->product;
            $minStock = $product->min_stock;
            $maxStock = $product->max_stock;

            if ($maxStock <= $minStock) {
                return $this->quantity >= $minStock ? 1 : 0;
            }

            if ($this->quantity <= $minStock) {
                return 0;
            }

            if ($this->quantity >= $maxStock) {
                return 1;
            }

            return ($this->quantity - $minStock) / ($maxStock - $minStock);
        }

        return 0;
    }

    /**
     * 范围查询：低库存
     */
    public function scopeLowStock($query)
    {
        return $query->alias('s')
            ->join('product_sku ps', 'ps.id = s.sku_id')  // 修复：使用 sku_id 关联
            ->join('products p', 'p.id = ps.product_id')  // 通过 sku 关联产品
            ->where('s.quantity', '<=', \think\facade\Db::raw('p.min_stock'))
            ->where('s.deleted_at', null)
            ->where('p.deleted_at', null);
    }

    /**
     * 范围查询：高库存
     */
    public function scopeHighStock($query)
    {
        return $query->alias('s')
            ->join('product_sku ps', 'ps.id = s.sku_id')  // 修复：使用 sku_id 关联
            ->join('products p', 'p.id = ps.product_id')  // 通过 sku 关联产品
            ->where('s.quantity', '>=', \think\facade\Db::raw('p.max_stock'))
            ->where('s.deleted_at', null)
            ->where('p.deleted_at', null);
    }

    /**
     * 范围查询：正常库存
     */
    public function scopeNormalStock($query)
    {
        return $query->alias('s')
            ->join('product_sku ps', 'ps.id = s.sku_id')  // 修复：使用 sku_id 关联
            ->join('products p', 'p.id = ps.product_id')  // 通过 sku 关联产品
            ->where('s.quantity', '>', \think\facade\Db::raw('p.min_stock'))
            ->where('s.quantity', '<', \think\facade\Db::raw('p.max_stock'))
            ->where('s.deleted_at', null)
            ->where('p.deleted_at', null);
    }

    /**
     * 获取格式化数量（带单位）
     */
    public function getFormattedQuantityAttr()
    {
        // 通过 sku 关联获取产品信息
        if (isset($this->sku) && $this->sku && $this->sku->product && $this->sku->product->unit) {
            return $this->quantity . $this->sku->product->unit;
        }
        return (string)$this->quantity;
    }
}