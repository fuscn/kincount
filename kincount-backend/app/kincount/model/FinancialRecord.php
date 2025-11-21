<?php
namespace app\kincount\model;

class FinancialRecord extends BaseModel
{
    
    protected $type = [
        'type' => 'integer',
        'amount' => 'float',
        'created_by' => 'integer'
    ];
    
    // 关联创建人
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    // 财务收支类型选项
    public function getTypeOptions()
    {
        return [
            1 => '收入',
            2 => '支出'
        ];
    }
    
    // 收支类别选项
    public function getCategoryOptions()
    {
        return [
            '销售收入' => '销售收入',
            '采购支出' => '采购支出',
            '工资支出' => '工资支出',
            '租金支出' => '租金支出',
            '水电费' => '水电费',
            '运输费' => '运输费',
            '其他收入' => '其他收入',
            '其他支出' => '其他支出'
        ];
    }
    
    // 支付方式选项
    public function getPaymentMethodOptions()
    {
        return [
            '现金' => '现金',
            '银行卡' => '银行卡',
            '微信支付' => '微信支付',
            '支付宝' => '支付宝',
            '转账' => '转账',
            '其他' => '其他'
        ];
    }
    
    // 生成收支单号
    public function generateRecordNo()
    {
        return $this->generateUniqueNo('FR');
    }
    
    // 获取金额显示（带符号）
    public function getAmountDisplayAttr($value, $data)
    {
        $type = $data['type'] ?? 1;
        $amount = $data['amount'] ?? 0;
        $symbol = $type == 1 ? '+' : '-';
        return $symbol . number_format($amount, 2);
    }
}