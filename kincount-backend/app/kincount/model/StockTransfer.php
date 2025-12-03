<?php

namespace app\kincount\model;

class StockTransfer extends BaseModel
{
    protected $type = [
        'from_warehouse_id' => 'integer',
        'to_warehouse_id' => 'integer',
        'total_amount' => 'float',
        'status' => 'integer',
        'created_by' => 'integer',
        'audit_by' => 'integer'
    ];

    // 关联调出仓库
    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    // 关联调入仓库
    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    // 关联创建人
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // 关联审核人
    public function auditor()
    {
        return $this->belongsTo(User::class, 'audit_by');
    }

    // 关联调拨明细
    public function items()
    {
        return $this->hasMany(StockTransferItem::class, 'stock_transfer_id');
    }

    // 库存调拨状态常量
    const STATUS_PENDING = 1;      // 待调拨
    const STATUS_TRANSFERRING = 2; // 调拨中
    const STATUS_COMPLETED = 3;    // 已完成
    const STATUS_CANCELLED = 4;    // 已取消

    // 状态文本映射方法
    public function getStatusOptions()
    {
        return [
            self::STATUS_PENDING => '待调拨',
            self::STATUS_TRANSFERRING => '调拨中',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_CANCELLED => '已取消'
        ];
    }

    // 生成调拨单号
    public function generateTransferNo()
    {
        return $this->generateUniqueNo('TR');
    }

    // 计算调拨总金额
    public function calculateTotalAmount()
    {
        $total = $this->items()->sum('total_amount');
        $this->total_amount = $total;
        return $this->save();
    }
}
