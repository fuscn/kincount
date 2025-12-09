<?php

declare(strict_types=1);

namespace app\kincount\controller;

use app\kincount\model\AccountRecord;
use app\kincount\model\Customer;
use app\kincount\model\Supplier;
use app\kincount\model\AccountSettlement;
use app\kincount\model\FinancialRecord;
use think\facade\Db;

class AccountRecordController extends BaseController
{
    public function index()
    {
        $page  = (int)input('page', 1);
        $limit = (int)input('limit', 15);
        $type  = input('type', '');
        $status = input('status', '');
        $tId   = (int)input('target_id', 0);
        $sDate = input('start_date', '');
        $eDate = input('end_date', '');

        $query = AccountRecord::with(['creator'])
            ->where('deleted_at', null);

        if ($type !== '') $query->where('type', $type);
        if ($status !== '') $query->where('status', $status);
        if ($tId) $query->where('target_id', $tId);
        if ($sDate) $query->where('created_at', '>=', $sDate);
        if ($eDate) $query->where('created_at', '<=', $eDate . ' 23:59:59');

        return $this->paginate($query->order('id', 'desc')
            ->paginate(['list_rows' => $limit, 'page' => $page]));
    }

    public function read($id)
    {
        $record = AccountRecord::with(['creator'])->where('deleted_at', null)->find($id);
        if (!$record) return $this->error('账款记录不存在');

        /* 目标名称 */
        if ($record->type == 1) {
            $record['target_name'] = Customer::where('id', $record->target_id)->value('name') ?? '';
        } else {
            $record['target_name'] = Supplier::where('id', $record->target_id)->value('name') ?? '';
        }

        return $this->success($record);
    }

    public function save()
    {
        $post = input('post.');

        // 扩展验证规则
        $validate = new \think\Validate([
            'type'           => 'require|in:1,2',
            'target_id'      => 'require|integer',
            'amount'         => 'require|float|gt:0',
            'balance_amount' => 'require|float|gt:0',
            'record_date'    => 'require|date',
            'related_type'   => 'in:,normal,return,purchase,other' // 新增：关联类型
        ]);

        if (!$validate->check($post)) {
            return $this->error($validate->getError());
        }

        // 验证目标对象是否存在
        if ($post['type'] == 1) {
            // 应收账款：目标必须是客户
            $target = Customer::find($post['target_id']);
            if (!$target) {
                return $this->error('客户不存在');
            }
            $targetType = 'customer';
        } else {
            // 应付账款：根据 related_type 确定目标类型
            if (isset($post['related_type']) && $post['related_type'] == 'return') {
                // 销售退货应付：目标必须是客户
                $target = Customer::find($post['target_id']);
                if (!$target) {
                    return $this->error('客户不存在');
                }
                $targetType = 'customer';
            } else {
                // 正常应付：目标必须是供应商
                $target = Supplier::find($post['target_id']);
                if (!$target) {
                    return $this->error('供应商不存在');
                }
                $targetType = 'supplier';
            }
        }

        $record = Db::transaction(function () use ($post, $targetType) {
            // 生成账款编号
            $recordNo = $this->generateRecordNo($post['type'], $post['related_type'] ?? '');

            // 创建记录
            $recordData = [
                'record_no'      => $recordNo,
                'type'           => $post['type'],
                'target_id'      => $post['target_id'],
                'target_type'    => $targetType, // 新增：明确目标类型
                'amount'         => $post['amount'],
                'balance_amount' => $post['balance_amount'],
                'record_date'    => $post['record_date'],
                'due_date'       => $post['due_date'] ?? null,
                'description'    => $post['description'] ?? '',
                'related_type'   => $post['related_type'] ?? 'normal', // 新增：关联类型
                'status'         => 1,
                'created_by'     => $this->getUserId(),
            ];

            $record = AccountRecord::create($recordData);

            // 更新客户/供应商欠款（根据目标类型）
            $this->updateTargetArrears($targetType, $post['target_id'], $post['balance_amount'], 'add');

            return $record;
        });

        return $this->success(['id' => $record->id], '账款记录添加成功');
    }

    public function settle($id)
    {
        $record = AccountRecord::where('deleted_at', null)->find($id);
        if (!$record) return $this->error('账款记录不存在');
        if ($record->status == 2) return $this->error('已结清');

        Db::transaction(function () use ($record) {
            $record->save([
                'status'        => 2,
                'balance_amount' => 0,
                'settled_at'    => date('Y-m-d H:i:s'),
            ]);
            $this->updateTargetArrears($record->type, $record->target_id, $record->amount, 'subtract');
        });

        return $this->success([], '结清成功');
    }

    public function statistics()
    {
        /* 应收 */
        $totalReceivable = AccountRecord::where('type', 1)->where('status', 1)->sum('balance_amount');
        $overdueReceivable = AccountRecord::where('type', 1)->where('status', 1)
            ->where('due_date', '<', date('Y-m-d'))->sum('balance_amount');

        /* 应付 */
        $totalPayable = AccountRecord::where('type', 2)->where('status', 1)->sum('balance_amount');
        $overduePayable = AccountRecord::where('type', 2)->where('status', 1)
            ->where('due_date', '<', date('Y-m-d'))->sum('balance_amount');

        return $this->success([
            'receivable' => ['total' => $totalReceivable ?: 0, 'overdue' => $overdueReceivable ?: 0],
            'payable'    => ['total' => $totalPayable ?: 0, 'overdue' => $overduePayable ?: 0],
        ]);
    }

    /**
     * 生成账款编号
     * @param int $type 账款类型 1=应收，2=应付
     * @param string $relatedType 关联类型
     * @return string
     */
    private function generateRecordNo($type, $relatedType = '')
    {
        $prefix = '';

        if ($type == 1) {
            // 应收账款
            $prefix = 'AR'; // Accounts Receivable
        } else {
            // 应付账款
            if ($relatedType == 'return') {
                $prefix = 'PYR'; // Payable Return (销售退货应付)
            } else {
                $prefix = 'AP'; // Accounts Payable
            }
        }

        return $prefix . date('YmdHis') . rand(1000, 9999);
    }

    /**
     * 更新目标对象欠款
     * @param string $targetType 目标类型：customer, supplier
     * @param int $targetId 目标ID
     * @param float $amount 金额
     * @param string $operation 操作：add(增加), sub(减少)
     * @return bool
     */
    private function updateTargetArrears($targetType, $targetId, $amount, $operation = 'add')
    {
        try {
            // 将金额转换为字符串格式，确保两位小数
            $amountStr = number_format((float)$amount, 2, '.', '');

            if ($targetType == 'customer') {
                $model = Customer::find($targetId);
                $field = 'arrears_amount';
            } elseif ($targetType == 'supplier') {
                $model = Supplier::find($targetId);
                $field = 'payable_amount';
            } else {
                throw new \Exception('未知的目标类型');
            }

            if (!$model) {
                throw new \Exception('目标对象不存在');
            }

            // 确保当前值也是字符串格式
            $currentValue = number_format((float)$model->$field, 2, '.', '');

            if ($operation == 'add') {
                $model->$field = bcadd($currentValue, $amountStr, 2);
            } elseif ($operation == 'sub') {
                $model->$field = bcsub($currentValue, $amountStr, 2);

                // 防止负数，使用 bc 函数比较
                if (bccomp($model->$field, '0', 2) < 0) {
                    $model->$field = '0.00';
                }
            }

            return $model->save();
        } catch (\Exception $e) {
            // 记录日志
            \think\facade\Log::error('更新欠款失败：' . $e->getMessage());
            throw $e;
        }
    }
    /** 支付账款 */
    public function pay($id)
    {
        try {
            // 1. 获取账款记录 - 使用TP8的助手函数或模型方法
            $record = AccountRecord::find($id);
            if (!$record) {
                return $this->error('账款记录不存在');
            }

            if ($record->deleted_at) {
                return $this->error('账款记录已被删除');
            }

            if ($record->status == 2) {
                return $this->error('已结清，无需重复支付/收款');
            }

            // 2. 验证参数 - 使用TP8的验证器或助手函数
            $amount = (float)input('amount', 0);
            $paymentMethod = input('payment_method', '');
            $remark = input('remark', '');
            $settlementDate = input('settlement_date', date('Y-m-d'));

            // 验证日期格式
            if (!strtotime($settlementDate)) {
                return $this->error('核销日期格式不正确');
            }

            if ($amount <= 0) {
                return $this->error('支付金额必须大于 0');
            }

            // 3. 检查支付金额是否大于应付余额
            if ($amount > $record->balance_amount) {
                return $this->error(sprintf(
                    '支付金额(¥%.2f)不能大于应付余额(¥%.2f)',
                    $amount,
                    $record->balance_amount
                ));
            }

            // 4. 开始事务
            Db::startTrans();

            try {
                // 5. 更新账款记录
                $record->paid_amount = bcadd((string)$record->paid_amount, (string)$amount, 2);
                $record->balance_amount = bcsub((string)$record->balance_amount, (string)$amount, 2);
                $record->updated_at = date('Y-m-d H:i:s');

                // 如果余额为0，则标记为已结清
                if ($record->balance_amount == 0) {
                    $record->status = 2; // 已结清
                }

                $record->save();

                // 6. 生成财务收支单号
                $financialNo = 'FR' . date('YmdHis') . mt_rand(1000, 9999);

                // 7. 根据账款类型确定财务类型
                // 1=应收(别人欠我们，收款)，2=应付(我们欠别人，付款)
                $paymentType = ($record->type == 1) ? 1 : 2; // 应收->收入，应付->支出
                $paymentCategory = ($record->type == 1) ? '账款收款' : '账款支付';

                // 8. 获取创建人ID
                $userId = $this->getUserId();

                // 9. 创建财务收支记录
                $financialData = [
                    'record_no' => $financialNo,
                    'type' => $paymentType,
                    'category' => $paymentCategory,
                    'amount' => $amount,
                    'payment_method' => $paymentMethod,
                    'remark' => $remark ?: ($record->type == 1 ? '应收款收款' : '应付款支付'),
                    'record_date' => $settlementDate,
                    'created_by' => $userId,
                    'account_id' => $record->id,
                    'customer_id' => $record->type == 1 ? $record->target_id : null,
                    'supplier_id' => $record->type == 2 ? $record->target_id : null,
                    'order_id' => $record->related_id,
                    'order_type' => $record->related_type,
                ];

                $financialRecord = FinancialRecord::create($financialData);

                // 10. 生成核销单号
                $settlementNo = 'ST' . date('YmdHis') . mt_rand(1000, 9999);

                // 11. 创建核销记录
                $settlementData = [
                    'settlement_no' => $settlementNo,
                    'account_type' => $record->type,
                    'account_id' => $record->id,
                    'financial_id' => $financialRecord->id,
                    'settlement_amount' => $amount,
                    'settlement_date' => $settlementDate,
                    'remark' => $remark ?: ($record->type == 1 ? '应收款核销' : '应付款核销'),
                ];

                $settlementRecord = AccountSettlement::create($settlementData);

                // 12. 根据账款类型更新对方余额
                if ($record->type == 1) { // 应收账款
                    $customer = Customer::find($record->target_id);
                    if ($customer) {
                        $customer->receivable_balance = bcsub(
                            (string)$customer->receivable_balance,
                            (string)$amount,
                            2
                        );
                        if ($customer->receivable_balance < 0) {
                            $customer->receivable_balance = 0;
                        }
                        $customer->save();
                    }
                } else { // 应付账款
                    $supplier = Supplier::find($record->target_id);
                    if ($supplier) {
                        $supplier->payable_balance = bcsub(
                            (string)$supplier->payable_balance,
                            (string)$amount,
                            2
                        );
                        if ($supplier->payable_balance < 0) {
                            $supplier->payable_balance = 0;
                        }
                        $supplier->save();
                    }
                }

                // 13. 提交事务
                Db::commit();

                // 14. 记录日志
                \think\facade\Log::info('账款核销成功', [
                    'record_id' => $id,
                    'amount' => $amount,
                    'type' => $record->type == 1 ? '应收' : '应付',
                    'user_id' => $userId,
                ]);

                // 15. 返回完整结果
                return $this->success([
                    'record' => $record,
                    'financial_record' => $financialRecord,
                    'settlement' => $settlementRecord,
                    'balance_amount' => $record->balance_amount,
                    'is_settled' => $record->status == 2,
                    'settlement_no' => $settlementNo,
                    'financial_no' => $financialNo,
                ], '操作成功');
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();

                // 记录错误日志
                \think\facade\Log::error('账款核销失败: ' . $e->getMessage(), [
                    'record_id' => $id,
                    'amount' => $amount,
                    'error' => $e->getTraceAsString()
                ]);

                return $this->error('操作失败: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            \think\facade\Log::error('账款核销异常: ' . $e->getMessage());
            return $this->error('系统异常: ' . $e->getMessage());
        }
    }

    /** 账款汇总 */
    public function summary()
    {
        $totalReceivable = AccountRecord::where('type', 1)
            ->where('status', 1)
            ->sum('balance_amount') ?: 0;
        $totalPayable = AccountRecord::where('type', 2)
            ->where('status', 1)
            ->sum('balance_amount') ?: 0;

        return $this->success([
            'total_receivable' => $totalReceivable,
            'total_payable'    => $totalPayable,
        ]);
    }


    /** 应收账款列表 */
    public function receivable()
    {
        $page  = (int)input('page', 1);
        $limit = (int)input('limit', 15);
        $kw    = input('keyword', '');
        $status = input('status', '');
        $targetId = input('target_id', '');
        $targetType = input('target_type', '');
        $startTime = input('start_time', '');
        $endTime = input('end_time', '');
        $minAmount = input('min_amount', '');
        $maxAmount = input('max_amount', '');

        // 使用CASE WHEN和LEFT JOIN分别关联客户和供应商表
        $query = AccountRecord::alias('ar')
            ->with(['creator'])
            // 左联客户表（当target_type为customer时）
            ->leftJoin('customers c', "ar.target_type = 'customer' AND ar.target_id = c.id")
            // 左联供应商表（当target_type为supplier时）
            ->leftJoin('suppliers s', "ar.target_type = 'supplier' AND ar.target_id = s.id")
            // 使用COALESCE或CASE获取对应的名称
            ->field('ar.*, 
                CASE 
                    WHEN ar.target_type = "customer" THEN c.name
                    WHEN ar.target_type = "supplier" THEN s.name
                    ELSE "未知"
                END as target_name,
                ar.target_type')
            ->where('ar.type', 1) // type=1 表示应收账款
            ->where('ar.deleted_at', null);

        // 关键词搜索
        if ($kw) {
            $query->whereLike('ar.remark|c.name|s.name', "%{$kw}%");
        }

        // 状态筛选
        if ($status !== '') {
            $query->where('ar.status', $status);
        }

        // 目标筛选
        if ($targetId !== '') {
            $query->where('ar.target_id', $targetId);
        }

        // 目标类型筛选
        if ($targetType !== '') {
            $query->where('ar.target_type', $targetType);
        }

        // 时间范围筛选
        if ($startTime) {
            $query->where('ar.created_at', '>=', $startTime);
        }
        if ($endTime) {
            $query->where('ar.created_at', '<=', $endTime);
        }

        // 金额范围筛选
        if ($minAmount !== '') {
            $query->where('ar.amount', '>=', $minAmount);
        }
        if ($maxAmount !== '') {
            $query->where('ar.amount', '<=', $maxAmount);
        }

        // 排序
        return $this->paginate($query->order('ar.created_at', 'desc')
            ->paginate(['list_rows' => $limit, 'page' => $page]));
    }


    /** 应付账款列表 */
    public function payable()
    {
        $page  = (int)input('page', 1);
        $limit = (int)input('limit', 15);
        $kw    = input('keyword', '');
        $status = input('status', '');
        $targetId = input('target_id', '');
        $targetType = input('target_type', '');
        $startTime = input('start_time', '');
        $endTime = input('end_time', '');
        $minAmount = input('min_amount', '');
        $maxAmount = input('max_amount', '');

        // 使用CASE WHEN和LEFT JOIN分别关联客户和供应商表
        $query = AccountRecord::alias('ar')
            ->with(['creator'])
            // 左联客户表（当target_type为customer时）
            ->leftJoin('customers c', "ar.target_type = 'customer' AND ar.target_id = c.id")
            // 左联供应商表（当target_type为supplier时）
            ->leftJoin('suppliers s', "ar.target_type = 'supplier' AND ar.target_id = s.id")
            // 使用COALESCE或CASE获取对应的名称
            ->field('ar.*, 
                CASE 
                    WHEN ar.target_type = "customer" THEN c.name
                    WHEN ar.target_type = "supplier" THEN s.name
                    ELSE "未知"
                END as target_name,
                ar.target_type')
            ->where('ar.type', 2) // type=2 表示应付账款
            ->where('ar.deleted_at', null);

        // 关键词搜索
        if ($kw) {
            $query->whereLike('ar.remark|c.name|s.name', "%{$kw}%");
        }

        // 状态筛选
        if ($status !== '') {
            $query->where('ar.status', $status);
        }

        // 目标筛选
        if ($targetId !== '') {
            $query->where('ar.target_id', $targetId);
        }

        // 目标类型筛选
        if ($targetType !== '') {
            $query->where('ar.target_type', $targetType);
        }

        // 时间范围筛选
        if ($startTime) {
            $query->where('ar.created_at', '>=', $startTime);
        }
        if ($endTime) {
            $query->where('ar.created_at', '<=', $endTime);
        }

        // 金额范围筛选
        if ($minAmount !== '') {
            $query->where('ar.amount', '>=', $minAmount);
        }
        if ($maxAmount !== '') {
            $query->where('ar.amount', '<=', $maxAmount);
        }

        // 排序
        return $this->paginate($query->order('ar.created_at', 'desc')
            ->paginate(['list_rows' => $limit, 'page' => $page]));
    }



    /**
     * 应付账款汇总统计（按供应商分组）
     * 用于应付账款首页展示
     */
    public function payableSummary()
    {
        $kw = input('keyword', '');
        $status = input('status', '');
        $relatedType = input('related_type', '');

        // 统计采购应付（供应商）
        $purchaseQuery = AccountRecord::alias('ar')
            ->leftJoin('suppliers s', 'ar.target_id = s.id')
            ->field([
                'ar.target_id as target_id',
                's.name as target_name',
                's.contact_person',
                's.phone',
                '"supplier" as target_type',
                '"purchase" as related_type',
                'COUNT(ar.id) as record_count',
                'SUM(ar.amount) as total_amount',
                'SUM(IF(ar.status = 1, ar.amount, 0)) as unsettled_amount',
                'MAX(ar.status) as status'
            ])
            ->where('ar.type', 2)
            ->where('ar.related_type', 'purchase')
            ->where('ar.deleted_at', null)
            ->group('ar.target_id');

        // 统计销售退货应付（客户）
        $saleReturnQuery = AccountRecord::alias('ar')
            ->leftJoin('customers c', 'ar.target_id = c.id')
            ->field([
                'ar.target_id as target_id',
                'c.name as target_name',
                'c.contact_person',
                'c.phone',
                '"customer" as target_type',
                '"sale_return" as related_type',
                'COUNT(ar.id) as record_count',
                'SUM(ar.amount) as total_amount',
                'SUM(IF(ar.status = 1, ar.amount, 0)) as unsettled_amount',
                'MAX(ar.status) as status'
            ])
            ->where('ar.type', 2)
            ->where('ar.related_type', 'sale_return')
            ->where('ar.deleted_at', null)
            ->group('ar.target_id');

        // 应用筛选条件
        if ($kw) {
            $purchaseQuery->whereLike('s.name|s.contact_person', "%{$kw}%");
            $saleReturnQuery->whereLike('c.name|c.contact_person', "%{$kw}%");
        }

        if ($status !== '') {
            $purchaseQuery->where('ar.status', $status);
            $saleReturnQuery->where('ar.status', $status);
        }

        // 根据 related_type 参数决定查询哪种
        if ($relatedType === 'purchase') {
            $list = $purchaseQuery->select()->toArray();
        } elseif ($relatedType === 'sale_return') {
            $list = $saleReturnQuery->select()->toArray();
        } else {
            // 查询两种类型，合并结果
            $purchaseList = $purchaseQuery->select()->toArray();
            $saleReturnList = $saleReturnQuery->select()->toArray();
            $list = array_merge($purchaseList, $saleReturnList);
        }

        // 转换格式
        foreach ($list as &$item) {
            $item['balance_amount'] = $item['unsettled_amount'];
            $item['status_text'] = $item['status'] == 1 ? '未结清' : '已结清';

            // 根据 related_type 设置不同的标签
            if ($item['related_type'] === 'sale_return') {
                $item['related_type_label'] = '销售退货应付';
                $item['supplier_name'] = $item['target_name']; // 前端可能期望 supplier_name
            } else {
                $item['related_type_label'] = '采购应付';
                $item['supplier_name'] = $item['target_name'];
            }
        }

        // 计算统计信息
        $totalAmount = array_sum(array_column($list, 'total_amount'));
        $unsettledAmount = array_sum(array_column($list, 'unsettled_amount'));
        $purchaseCount = count(array_filter($list, function ($item) {
            return $item['related_type'] === 'purchase';
        }));
        $saleReturnCount = count(array_filter($list, function ($item) {
            return $item['related_type'] === 'sale_return';
        }));
        $arrearsCount = count(array_filter($list, function ($item) {
            return $item['unsettled_amount'] > 0;
        }));

        return $this->success('获取成功', [
            'list' => $list,
            'total' => count($list),
            'statistics' => [
                'total_amount' => $totalAmount,
                'unsettled_amount' => $unsettledAmount,
                'purchase_count' => $purchaseCount,
                'sale_return_count' => $saleReturnCount,
                'arrears_count' => $arrearsCount
            ]
        ]);
    }

    /**
     * 应付账款统计信息
     */
    public function payableStatistics()
    {
        // 总应付金额
        $totalAmount = AccountRecord::where('type', 2)
            ->where('deleted_at', null)
            ->sum('amount');

        // 未结清金额
        $unsettledAmount = AccountRecord::where('type', 2)
            ->where('status', 1) // 状态1为未结清
            ->where('deleted_at', null)
            ->sum('amount');

        // 供应商数量（有应付账款的供应商）
        $supplierCount = AccountRecord::where('type', 2)
            ->where('deleted_at', null)
            ->group('target_id')
            ->count();

        // 欠款供应商数量
        $arrearsCount = AccountRecord::where('type', 2)
            ->where('status', 1)
            ->where('deleted_at', null)
            ->group('target_id')
            ->count();

        return $this->success('获取成功', [
            'total_amount' => $totalAmount ?: 0,
            'unsettled_amount' => $unsettledAmount ?: 0,
            'supplier_count' => $supplierCount,
            'arrears_count' => $arrearsCount
        ]);
    }
}
