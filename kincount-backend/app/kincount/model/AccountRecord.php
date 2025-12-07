<?php

namespace app\kincount\model;

class AccountRecord extends BaseModel
{

    protected $type = [
        'type'            => 'integer',
        'target_id'       => 'integer',
        'related_id'      => 'integer',
        'amount'          => 'float',
        'paid_amount'     => 'float',
        'balance_amount'  => 'float',
        'status'          => 'integer',
    ];

    // 关联创建人
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // 关联客户或供应商
    public function target()
    {
        return $this->morphTo('target', 'type', 'target_id');
    }

    // 账款类型文本
    public function getTypeTextAttr($value, $data)
    {
        return $data['type'] == 1 ? '应收' : '应付';
    }

    // 状态文本
    public function getStatusTextAttr($value, $data)
    {
        return $data['status'] == 1 ? '未结清' : '已结清';
    }
}
