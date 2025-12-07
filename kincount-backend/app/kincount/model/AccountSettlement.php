<?php

namespace app\kincount\model;

use think\model\relation\BelongsTo;
use think\facade\Validate;
use think\exception\ValidateException;

class AccountSettlement extends BaseModel
{
    // 表名（自动生成，无需指定）
    // protected $table = 'account_settlements';
    
    // 主键
    protected $pk = 'id';
    
    // 自动写入时间戳字段
    // 已在BaseModel中定义，这里可以省略
    
    // 定义字段自动完成
    protected $auto = [];
    protected $insert = [];
    protected $update = [];
    
    // 字段类型转换
    protected $type = [
        'id'                => 'integer',
        'settlement_no'     => 'string',
        'account_type'      => 'integer',
        'account_id'        => 'integer',
        'financial_id'      => 'integer',
        'settlement_amount' => 'float',
        'settlement_date'   => 'datetime',
        'remark'            => 'string',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime'
    ];
    
    /**
     * 获取账款类型选项
     */
    public function getAccountTypeOptions()
    {
        return [
            1 => '应收账款',
            2 => '应付账款'
        ];
    }
    
    /**
     * 获取账款类型文本
     */
    public function getAccountTypeTextAttr($value, $data)
    {
        $type = $data['account_type'] ?? 0;
        $options = $this->getAccountTypeOptions();
        return $options[$type] ?? '未知';
    }
    
    /**
     * 获取核销单号前缀
     */
    public function getSettlementNoPrefix()
    {
        return 'SE';
    }
    
    /**
     * 生成核销单号
     */
    public function generateSettlementNo()
    {
        return $this->generateUniqueNo($this->getSettlementNoPrefix(), 'settlement_no');
    }
    
    /**
     * 自动生成核销单号
     */
    public static function onBeforeInsert($model)
    {
        if (empty($model->settlement_no)) {
            $model->settlement_no = $model->generateSettlementNo();
        }
        
        if (empty($model->settlement_date)) {
            $model->settlement_date = date('Y-m-d');
        }
    }
    
    /**
     * 关联账款记录
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountRecord::class, 'account_id', 'id');
    }
    
    /**
     * 关联财务收支记录
     */
    public function financial(): BelongsTo
    {
        return $this->belongsTo(FinancialRecord::class, 'financial_id', 'id');
    }
    
    /**
     * 搜索核销记录
     */
    public function scopeSearch($query, $params)
    {
        // 按核销单号搜索
        if (!empty($params['settlement_no'])) {
            $query->where('settlement_no', 'like', "%{$params['settlement_no']}%");
        }
        
        // 按账款类型搜索
        if (isset($params['account_type']) && $params['account_type'] !== '') {
            $query->where('account_type', $params['account_type']);
        }
        
        // 按账款ID搜索
        if (!empty($params['account_id'])) {
            $query->where('account_id', $params['account_id']);
        }
        
        // 按财务收支ID搜索
        if (!empty($params['financial_id'])) {
            $query->where('financial_id', $params['financial_id']);
        }
        
        // 按核销日期范围搜索
        if (!empty($params['start_date'])) {
            $query->where('settlement_date', '>=', $params['start_date']);
        }
        if (!empty($params['end_date'])) {
            $query->where('settlement_date', '<=', $params['end_date']);
        }
        
        // 按创建时间范围搜索
        if (!empty($params['created_start'])) {
            $query->where('created_at', '>=', $params['created_start']);
        }
        if (!empty($params['created_end'])) {
            $query->where('created_at', '<=', $params['created_end']);
        }
        
        // 排序
        $orderField = $params['order_field'] ?? 'id';
        $orderType = $params['order_type'] ?? 'desc';
        $query->order($orderField, $orderType);
        
        return $query;
    }
    
    /**
     * 获取核销详情（带关联信息）
     */
    public static function getDetail($id)
    {
        return self::with([
                'account' => function($q) {
                    $q->field('id, type, target_id,target_type, related_id, related_type, amount, paid_amount, balance_amount, status, due_date, remark');
                },
                'financial' => function($q) {
                    $q->field('id, record_no, type, category, amount, payment_method, remark, record_date, customer_id, supplier_id');
                }
            ])
            ->findOrEmpty($id);
    }
    
    /**
     * 验证核销数据
     */
    protected static function validateSettlementData($data)
    {
        try {
            // 使用验证器的完整类名
            validate(\app\kincount\validate\AccountSettlementValidate::class)
                ->scene('create')
                ->check($data);
        } catch (ValidateException $e) {
            throw new \Exception($e->getError());
        }
    }
    
    /**
     * 创建核销记录
     */
    public static function createSettlement($data)
    {
        // 验证数据
        self::validateSettlementData($data);
        
        // 检查账款记录是否存在
        $account = AccountRecord::find($data['account_id']);
        if (!$account) {
            throw new \Exception('账款记录不存在');
        }
        
        // 检查财务收支记录是否存在
        $financial = FinancialRecord::find($data['financial_id']);
        if (!$financial) {
            throw new \Exception('财务收支记录不存在');
        }
        
        // 检查核销金额是否超过账款余额
        if ($data['settlement_amount'] > $account->balance_amount) {
            throw new \Exception('核销金额不能超过账款余额');
        }
        
        // 检查账款类型是否匹配
        if ($data['account_type'] != $account->type) {
            throw new \Exception('账款类型不匹配');
        }
        
        // 使用事务创建核销记录
        return self::transaction(function() use ($data, $account, $financial) {
            // 创建核销记录
            $settlement = self::create($data);
            
            // 更新账款记录的已收/已付金额和余额
            $account->paid_amount += $data['settlement_amount'];
            $account->balance_amount -= $data['settlement_amount'];
            
            // 更新账款状态
            if (abs($account->balance_amount) < 0.01) { // 考虑浮点数精度
                $account->status = 2; // 已结清
            } else {
                $account->status = 1; // 未结清
            }
            $account->save();
            
            // 更新客户或供应商的账款余额
            if ($account->type == 1) {
                // 应收 - 更新客户
                $customer = Customer::find($account->target_id);
                if ($customer) {
                    $customer->receivable_balance -= $data['settlement_amount'];
                    $customer->save();
                }
            } else {
                // 应付 - 更新供应商
                $supplier = Supplier::find($account->target_id);
                if ($supplier) {
                    $supplier->payable_balance -= $data['settlement_amount'];
                    $supplier->save();
                }
            }
            
            // 更新相关订单的已收/已付金额
            self::updateRelatedOrderAmount($account, $data['settlement_amount']);
            
            return $settlement;
        });
    }
    
    /**
     * 更新相关订单的已收/已付金额
     */
    protected static function updateRelatedOrderAmount($account, $settlementAmount)
    {
        if ($account->related_type == 'sale_order') {
            // 销售订单
            $order = SaleOrder::find($account->related_id);
            if ($order) {
                $order->paid_amount += $settlementAmount;
                $order->save();
            }
        } elseif ($account->related_type == 'purchase_order') {
            // 采购订单
            $order = PurchaseOrder::find($account->related_id);
            if ($order) {
                $order->paid_amount += $settlementAmount;
                $order->save();
            }
        } elseif ($account->related_type == 'sale_return') {
            // 销售退货
            $return = ReturnOrder::find($account->related_id);
            if ($return) {
                $return->refunded_amount += $settlementAmount;
                $return->save();
            }
        } elseif ($account->related_type == 'purchase_return') {
            // 采购退货
            $return = ReturnOrder::find($account->related_id);
            if ($return) {
                $return->refunded_amount += $settlementAmount;
                $return->save();
            }
        }
    }
    
    /**
     * 批量核销
     */
    public static function batchSettle($financialId, $settlements)
    {
        return self::transaction(function() use ($financialId, $settlements) {
            $totalSettled = 0;
            
            foreach ($settlements as $settlementData) {
                // 为每个核销记录设置财务收支ID
                $settlementData['financial_id'] = $financialId;
                
                // 创建核销记录
                $settlement = self::createSettlement($settlementData);
                
                $totalSettled += $settlementData['settlement_amount'];
            }
            
            return $totalSettled;
        });
    }
    
    /**
     * 根据财务收支ID获取核销记录列表
     */
    public static function getSettlementsByFinancialId($financialId)
    {
        return self::with([
                'account' => function($q) {
                    $q->field('id, type, target_id, related_id, related_type, amount, paid_amount, balance_amount, status');
                }
            ])
            ->where('financial_id', $financialId)
            ->select();
    }
    
    /**
     * 根据账款ID获取核销记录列表
     */
    public static function getSettlementsByAccountId($accountId)
    {
        return self::with([
                'financial' => function($q) {
                    $q->field('id, record_no, type, category, amount, payment_method, record_date');
                }
            ])
            ->where('account_id', $accountId)
            ->order('settlement_date', 'desc')
            ->select();
    }
    
    /**
     * 获取核销统计
     */
    public static function getSettlementStatistics($params = [])
    {
        $query = self::alias('as')
            ->join('account_records ar', 'as.account_id = ar.id')
            ->field([
                'as.account_type',
                'COUNT(as.id) as settlement_count',
                'SUM(as.settlement_amount) as settlement_total',
                'MIN(as.settlement_date) as earliest_date',
                'MAX(as.settlement_date) as latest_date'
            ])
            ->group('as.account_type');
        
        // 按日期范围统计
        if (!empty($params['start_date'])) {
            $query->where('as.settlement_date', '>=', $params['start_date']);
        }
        if (!empty($params['end_date'])) {
            $query->where('as.settlement_date', '<=', $params['end_date']);
        }
        
        return $query->select();
    }
}