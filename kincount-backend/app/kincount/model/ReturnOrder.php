<?php

namespace app\kincount\model;

use think\model\relation\HasMany;

class ReturnOrder extends BaseModel
{
    // 退货单类型
    const TYPE_SALE = 1;      // 销售退货
    const TYPE_PURCHASE = 2;  // 采购退货
    
    // 状态
    const STATUS_PENDING_AUDIT = 1;  // 待审核
    const STATUS_AUDITED = 2;        // 已审核
    const STATUS_PART_STOCK = 3;     // 部分入库/出库
    const STATUS_STOCK_COMPLETE = 4; // 已入库/出库
    const STATUS_REFUND_COMPLETE = 5; // 已退款/收款
    const STATUS_COMPLETED = 6;      // 已完成
    const STATUS_CANCELLED = 7;      // 已取消
    
    // 退货原因类型
    const REASON_QUALITY = 1;        // 质量问题
    const REASON_QUANTITY = 2;       // 数量问题
    const REASON_CANCELLED = 3;      // 客户/供应商取消
    const REASON_OTHER = 4;          // 其他
    
    // 出入库状态
    const STOCK_PENDING = 1;         // 待处理
    const STOCK_PART = 2;            // 部分处理
    const STOCK_COMPLETE = 3;        // 已完成
    
    // 款项状态
    const REFUND_PENDING = 1;        // 待处理
    const REFUND_PART = 2;           // 部分处理
    const REFUND_COMPLETE = 3;       // 已完成

    protected $table = 'returns';
    
    // 自动写入字段
    protected $auto = [];
    protected $insert = ['return_no'];
    protected $update = [];
    
    // 字段类型转换
    protected $type = [
        'type' => 'integer',
        'total_amount' => 'float',
        'refund_amount' => 'float',
        'refunded_amount' => 'float',
        'return_type' => 'integer',
        'status' => 'integer',
        'stock_status' => 'integer',
        'refund_status' => 'integer'
    ];
    
    /**
     * 自动生成退货单号
     */
    protected function setReturnNoAttr()
    {
        $prefix = $this->getNoPrefix();
        return $this->generateUniqueNo($prefix, 'return_no');
    }
    
    /**
     * 根据类型获取单号前缀
     */
    private function getNoPrefix()
    {
        switch ($this->type) {
            case self::TYPE_SALE:
                return 'SR';
            case self::TYPE_PURCHASE:
                return 'PR';
            default:
                return 'RT';
        }
    }
    
    /**
     * 关联退货明细
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id')
            ->whereNull('deleted_at');
    }
    
    /**
     * 关联退货出入库单
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(ReturnStock::class, 'return_id')
            ->whereNull('deleted_at');
    }
    
    /**
     * 关联客户（销售退货）
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'target_id')
            ->where('type', self::TYPE_SALE);
    }
    
    /**
     * 关联供应商（采购退货）
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'target_id')
            ->where('type', self::TYPE_PURCHASE);
    }
    
    /**
     * 关联仓库
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
    
    /**
     * 关联源订单（销售或采购）
     */
    public function sourceOrder()
    {
        if ($this->type == self::TYPE_SALE) {
            return $this->belongsTo(SaleOrder::class, 'source_order_id');
        } else {
            return $this->belongsTo(PurchaseOrder::class, 'source_order_id');
        }
    }
    
    /**
     * 关联源出入库单
     */
    public function sourceStock()
    {
        if ($this->type == self::TYPE_SALE) {
            return $this->belongsTo(SaleStock::class, 'source_stock_id');
        } else {
            return $this->belongsTo(PurchaseStock::class, 'source_stock_id');
        }
    }
    
    /**
     * 创建人
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    /**
     * 审核人
     */
    public function auditor()
    {
        return $this->belongsTo(User::class, 'audit_by');
    }
    
    /**
     * 获取类型选项
     */
    public function getTypeOptions(): array
    {
        return [
            self::TYPE_SALE => '销售退货',
            self::TYPE_PURCHASE => '采购退货'
        ];
    }
    
    /**
     * 获取状态选项
     */
    public function getStatusOptions(): array
    {
        return [
            self::STATUS_PENDING_AUDIT => '待审核',
            self::STATUS_AUDITED => '已审核',
            self::STATUS_PART_STOCK => '部分出入库',
            self::STATUS_STOCK_COMPLETE => '已出入库',
            self::STATUS_REFUND_COMPLETE => '已退款/收款',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_CANCELLED => '已取消'
        ];
    }
    
    /**
     * 获取退货原因选项
     */
    public function getReturnTypeOptions(): array
    {
        return [
            self::REASON_QUALITY => '质量问题',
            self::REASON_QUANTITY => '数量问题',
            self::REASON_CANCELLED => '客户/供应商取消',
            self::REASON_OTHER => '其他'
        ];
    }
    
    /**
     * 获取出入库状态选项
     */
    public function getStockStatusOptions(): array
    {
        return [
            self::STOCK_PENDING => '待处理',
            self::STOCK_PART => '部分处理',
            self::STOCK_COMPLETE => '已完成'
        ];
    }
    
    /**
     * 获取退款状态选项
     */
    public function getRefundStatusOptions(): array
    {
        return [
            self::REFUND_PENDING => '待处理',
            self::REFUND_PART => '部分处理',
            self::REFUND_COMPLETE => '已完成'
        ];
    }
    
    /**
     * 获取类型文本
     */
    public function getTypeTextAttr($value, $data): string
    {
        $options = $this->getTypeOptions();
        return $options[$data['type'] ?? 1] ?? '未知';
    }
    
    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data): string
    {
        $options = $this->getStatusOptions();
        return $options[$data['status'] ?? 1] ?? '未知';
    }
    
    /**
     * 获取退货原因文本
     */
    public function getReturnTypeTextAttr($value, $data): string
    {
        $options = $this->getReturnTypeOptions();
        return $options[$data['return_type'] ?? 1] ?? '未知';
    }
    
    /**
     * 获取出入库状态文本
     */
    public function getStockStatusTextAttr($value, $data): string
    {
        $options = $this->getStockStatusOptions();
        return $options[$data['stock_status'] ?? 1] ?? '未知';
    }
    
    /**
     * 获取退款状态文本
     */
    public function getRefundStatusTextAttr($value, $data): string
    {
        $options = $this->getRefundStatusOptions();
        return $options[$data['refund_status'] ?? 1] ?? '未知';
    }
    
    /**
     * 计算可退货金额
     */
    public function getRefundableAmount(): float
    {
        return bcsub($this->refund_amount, $this->refunded_amount, 2);
    }
    
    /**
     * 获取对方名称（客户/供应商）
     */
    public function getTargetName(): string
    {
        if ($this->type == self::TYPE_SALE && $this->customer) {
            return $this->customer->name;
        } elseif ($this->type == self::TYPE_PURCHASE && $this->supplier) {
            return $this->supplier->name;
        }
        return '';
    }
    
    /**
     * 获取源单号
     */
    public function getSourceOrderNo(): string
    {
        if ($this->source_order_id && $this->sourceOrder) {
            return $this->sourceOrder->order_no;
        }
        return '';
    }
}