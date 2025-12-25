<?php

namespace app\kincount\model;

use think\model\relation\BelongsTo;
use think\model\relation\HasMany;

class ReturnStock extends BaseModel
{
    // 状态
    const STATUS_PENDING_AUDIT = 0;  // 待审核
    const STATUS_AUDITED = 1;        // 已审核
    const STATUS_CANCELLED = 2;      // 已取消

    protected $table = 'return_stocks';

    // 自动写入字段
    protected $auto = [];
    protected $insert = ['stock_no'];
    protected $update = [];

    // 字段类型转换
    protected $type = [
        'total_amount' => 'float',
        'status' => 'integer'
    ];

    /**
     * 自动生成出入库单号
     */
    protected function setStockNoAttr()
    {
        $prefix = 'RTS'; // Return Stock
        return $this->generateUniqueNo($prefix, 'stock_no');
    }

    /**
     * 关联退货单
     */
    public function return(): BelongsTo
    {
        return $this->belongsTo(ReturnOrder::class, 'return_id');
    }

    /**
     * 关联出入库明细
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReturnStockItem::class, 'return_stock_id')
            ->whereNull('deleted_at');
    }


    /**
     * 关联客户/供应商
     */
    public function target()
    {
        // 使用闭包来动态决定关联类型
        return $this->belongsTo('', 'target_id')
            ->bind(['target_name' => 'name', 'target_contact' => 'contact_person', 'target_phone' => 'phone'])
            ->query(function ($query) {
                // 根据当前模型的 type 字段决定查询哪个表
                if ($this->type == 1) {
                    $query->table('customer');
                } elseif ($this->type == 2) {
                    $query->table('supplier');
                }
            });
    }
    /**
     * 获取目标类型字段
     */
    protected function getTargetTypeAttr($value, $data)
    {
        if (isset($data['type'])) {
            return $data['type'] == 1 ? 'customer' : 'supplier';
        }
        return 'customer';
    }
    /**
     * 关联仓库
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * 创建人
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 审核人
     */
    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'audit_by');
    }

    /**
     * 获取状态选项
     */
    public function getStatusOptions(): array
    {
        return [
            self::STATUS_PENDING_AUDIT => '待审核',
            self::STATUS_AUDITED => '已审核',
            self::STATUS_CANCELLED => '已取消'
        ];
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data): string
    {
        $options = $this->getStatusOptions();
        return $options[$data['status'] ?? 0] ?? '未知';
    }

    /**
     * 获取操作类型
     */
    public function getOperationType(): string
    {
        if ($this->return) {
            return $this->return->type == ReturnOrder::TYPE_SALE ? '销售退货入库' : '采购退货出库';
        }
        return '退货出入库';
    }

    /**
     * 获取对方名称
     */
    public function getTargetName(): string
    {
        if ($this->target) {
            return $this->target->name;
        }
        return '';
    }

    /**
     * 计算明细数量总和
     */
    public function getTotalQuantity(): int
    {
        return $this->items()->sum('quantity') ?? 0;
    }

    /**
     * 获取出入库方向
     */
    public function getStockDirection(): string
    {
        if ($this->return) {
            return $this->return->type == ReturnOrder::TYPE_SALE ? '入库' : '出库';
        }
        return '';
    }
}
