<?php
// app/kincount/validate/AccountSettlementValidate.php

namespace app\kincount\validate;

use think\Validate;

class AccountSettlementValidate extends Validate
{
    protected $rule = [
        'account_type'      => 'require|in:1,2',
        'account_id'        => 'require|integer|gt:0',
        'financial_id'      => 'require|integer|gt:0',
        'settlement_amount' => 'require|float|gt:0',
        'settlement_date'   => 'require|date',
        'remark'            => 'max:500'
    ];
    
    protected $message = [
        'account_type.require'      => '账款类型不能为空',
        'account_type.in'           => '账款类型只能是1(应收)或2(应付)',
        'account_id.require'        => '账款ID不能为空',
        'account_id.integer'        => '账款ID必须为整数',
        'account_id.gt'             => '账款ID必须大于0',
        'financial_id.require'      => '财务收支ID不能为空',
        'financial_id.integer'      => '财务收支ID必须为整数',
        'financial_id.gt'           => '财务收支ID必须大于0',
        'settlement_amount.require' => '核销金额不能为空',
        'settlement_amount.float'   => '核销金额必须是数字',
        'settlement_amount.gt'      => '核销金额必须大于0',
        'settlement_date.require'   => '核销日期不能为空',
        'settlement_date.date'      => '核销日期格式不正确',
        'remark.max'                => '备注不能超过500个字符'
    ];
    
    protected $scene = [
        'create' => ['account_type', 'account_id', 'financial_id', 'settlement_amount', 'settlement_date', 'remark']
    ];
}