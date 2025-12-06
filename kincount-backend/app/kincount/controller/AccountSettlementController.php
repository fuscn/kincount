<?php

namespace app\kincount\controller;

use app\kincount\model\AccountSettlement;
use app\kincount\model\AccountRecord;
use app\kincount\model\FinancialRecord;
use app\kincount\model\Customer;
use app\kincount\model\Supplier;
use think\facade\Db;
use think\facade\Validate;

class AccountSettlementController extends BaseController
{
    /**
     * 核销记录列表
     */
    public function index()
    {
        try {
            $params = request()->param;
            
            // 验证分页参数
            $validate = Validate::rule([
                'page' => 'integer|min:1',
                'limit' => 'integer|min:1|max:100'
            ]);
            
            if (!$validate->check($params)) {
                return $this->error($validate->getError());
            }
            
            $page = $params['page'] ?? 1;
            $limit = $params['limit'] ?? 20;
            
            // 构建查询
            $query = AccountSettlement::with([
                'account' => function($q) {
                    $q->field('id, type, target_id, related_id, related_type, amount, paid_amount, balance_amount, status');
                },
                'financial' => function($q) {
                    $q->field('id, record_no, type, category, amount, payment_method, record_date');
                }
            ]);
            
            // 搜索条件
            if (!empty($params['settlement_no'])) {
                $query->where('settlement_no', 'like', "%{$params['settlement_no']}%");
            }
            
            if (!empty($params['account_type']) && $params['account_type'] !== '') {
                $query->where('account_type', $params['account_type']);
            }
            
            if (!empty($params['account_id'])) {
                $query->where('account_id', $params['account_id']);
            }
            
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
            
            // 排序
            $orderField = $params['order_field'] ?? 'id';
            $orderType = $params['order_type'] ?? 'desc';
            $query->order($orderField, $orderType);
            
            // 分页查询
            $list = $query->paginate([
                'list_rows' => $limit,
                'page' => $page
            ]);
            
            // 处理返回数据
            $data = $list->toArray();
            
            // 添加目标信息（客户/供应商）
            foreach ($data['data'] as &$item) {
                $item['account_type_text'] = $item['account_type'] == 1 ? '应收账款' : '应付账款';
                
                // 获取目标信息
                if (!empty($item['account'])) {
                    if ($item['account_type'] == 1) {
                        // 应收 - 客户
                        $customer = Customer::find($item['account']['target_id']);
                        $item['target_info'] = [
                            'id' => $customer->id ?? 0,
                            'name' => $customer->name ?? '',
                            'type' => 'customer'
                        ];
                    } else {
                        // 应付 - 供应商
                        $supplier = Supplier::find($item['account']['target_id']);
                        $item['target_info'] = [
                            'id' => $supplier->id ?? 0,
                            'name' => $supplier->name ?? '',
                            'type' => 'supplier'
                        ];
                    }
                }
            }
            
            return $this->success([
                'list' => $data['data'],
                'total' => $data['total'],
                'page' => $page,
                'limit' => $limit,
                'pages' => $data['last_page']
            ]);
            
        } catch (\Exception $e) {
            return $this->error('查询失败: ' . $e->getMessage());
        }
    }

    /**
     * 核销记录详情
     */
    public function read($id)
    {
        try {
            $settlement = AccountSettlement::getDetail($id);
            
            if ($settlement->isEmpty()) {
                return $this->error('核销记录不存在');
            }
            
            $data = $settlement->toArray();
            
            // 获取目标信息
            if ($data['account_type'] == 1) {
                // 应收 - 客户
                if (!empty($data['account']['target_id'])) {
                    $customer = Customer::find($data['account']['target_id']);
                    $data['target_info'] = [
                        'id' => $customer->id ?? 0,
                        'name' => $customer->name ?? '',
                        'contact_person' => $customer->contact_person ?? '',
                        'phone' => $customer->phone ?? '',
                        'type' => 'customer'
                    ];
                }
            } else {
                // 应付 - 供应商
                if (!empty($data['account']['target_id'])) {
                    $supplier = Supplier::find($data['account']['target_id']);
                    $data['target_info'] = [
                        'id' => $supplier->id ?? 0,
                        'name' => $supplier->name ?? '',
                        'contact_person' => $supplier->contact_person ?? '',
                        'phone' => $supplier->phone ?? '',
                        'type' => 'supplier'
                    ];
                }
            }
            
            return $this->success($data);
            
        } catch (\Exception $e) {
            return $this->error('查询失败: ' . $e->getMessage());
        }
    }

    /**
     * 创建核销记录（单个）
     */
    public function create()
    {
        try {
            $data = request()->param;
            
            // 验证必填字段
            $validate = Validate::rule([
                'account_type' => 'require|in:1,2',
                'account_id' => 'require|integer|gt:0',
                'financial_id' => 'require|integer|gt:0',
                'settlement_amount' => 'require|float|gt:0',
                'settlement_date' => 'require|date'
            ]);
            
            if (!$validate->check($data)) {
                return $this->error($validate->getError());
            }
            
            // 创建核销记录
            $settlement = AccountSettlement::createSettlement($data);
            
            return $this->success([
                'id' => $settlement->id,
                'settlement_no' => $settlement->settlement_no
            ], '核销成功');
            
        } catch (\Exception $e) {
            return $this->error('核销失败: ' . $e->getMessage());
        }
    }

    /**
     * 批量核销（一笔财务收支核销多笔账款）
     */
    public function batchCreate()
    {
        try {
            $params = request()->param;
            
            // 验证必填字段
            $validate = Validate::rule([
                'financial_id' => 'require|integer|gt:0',
                'settlements' => 'require|array|min:1'
            ]);
            
            if (!$validate->check($params)) {
                return $this->error($validate->getError());
            }
            
            $financialId = $params['financial_id'];
            $settlements = $params['settlements'];
            
            // 验证每笔核销记录
            foreach ($settlements as $index => $item) {
                $validateItem = Validate::rule([
                    'account_type' => 'require|in:1,2',
                    'account_id' => 'require|integer|gt:0',
                    'settlement_amount' => 'require|float|gt:0'
                ]);
                
                if (!$validateItem->check($item)) {
                    return $this->error("第" . ($index + 1) . "笔核销记录验证失败: " . $validateItem->getError());
                }
            }
            
            // 执行批量核销
            $totalSettled = AccountSettlement::batchSettle($financialId, $settlements);
            
            return $this->success([
                'financial_id' => $financialId,
                'total_settled' => $totalSettled,
                'count' => count($settlements)
            ], '批量核销成功');
            
        } catch (\Exception $e) {
            return $this->error('批量核销失败: ' . $e->getMessage());
        }
    }

    /**
     * 根据账款ID获取核销记录
     */
    public function getByAccountId($accountId)
    {
        try {
            // 验证账款记录是否存在
            $account = AccountRecord::find($accountId);
            if (!$account) {
                return $this->error('账款记录不存在');
            }
            
            $settlements = AccountSettlement::getSettlementsByAccountId($accountId);
            
            return $this->success([
                'account_info' => $account,
                'settlements' => $settlements,
                'total_count' => count($settlements),
                'total_settled' => $settlements->sum('settlement_amount')
            ]);
            
        } catch (\Exception $e) {
            return $this->error('查询失败: ' . $e->getMessage());
        }
    }

    /**
     * 根据财务收支ID获取核销记录
     */
    public function getByFinancialId($financialId)
    {
        try {
            // 验证财务收支记录是否存在
            $financial = FinancialRecord::find($financialId);
            if (!$financial) {
                return $this->error('财务收支记录不存在');
            }
            
            $settlements = AccountSettlement::getSettlementsByFinancialId($financialId);
            
            // 获取目标信息
            $targetInfo = null;
            if ($financial->customer_id) {
                $customer = Customer::find($financial->customer_id);
                $targetInfo = [
                    'id' => $customer->id ?? 0,
                    'name' => $customer->name ?? '',
                    'type' => 'customer'
                ];
            } elseif ($financial->supplier_id) {
                $supplier = Supplier::find($financial->supplier_id);
                $targetInfo = [
                    'id' => $supplier->id ?? 0,
                    'name' => $supplier->name ?? '',
                    'type' => 'supplier'
                ];
            }
            
            return $this->success([
                'financial_info' => $financial,
                'target_info' => $targetInfo,
                'settlements' => $settlements,
                'total_count' => count($settlements),
                'total_settled' => $settlements->sum('settlement_amount')
            ]);
            
        } catch (\Exception $e) {
            return $this->error('查询失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取可核销的账款列表
     */
    public function getSettableAccounts()
    {
        try {
            $params = request()->param;
            
            $type = $params['type'] ?? null; // 1-应收 2-应付
            $targetId = $params['target_id'] ?? null; // 客户ID或供应商ID
            
            if (!$type) {
                return $this->error('请指定账款类型');
            }
            
            // 查询未结清的账款记录
            $query = AccountRecord::where('type', $type)
                ->where('status', 1) // 未结清
                ->where('balance_amount', '>', 0) // 余额大于0
                ->whereNull('deleted_at');
            
            if ($targetId) {
                $query->where('target_id', $targetId);
            }
            
            // 按相关业务类型筛选
            if (!empty($params['related_type'])) {
                $query->where('related_type', $params['related_type']);
            }
            
            // 按到期日筛选
            if (!empty($params['due_start'])) {
                $query->where('due_date', '>=', $params['due_start']);
            }
            if (!empty($params['due_end'])) {
                $query->where('due_date', '<=', $params['due_end']);
            }
            
            // 排序
            $query->order('due_date', 'asc')->order('id', 'asc');
            
            $accounts = $query->select();
            
            // 处理返回数据
            $list = [];
            foreach ($accounts as $account) {
                $item = $account->toArray();
                
                // 获取目标信息
                if ($type == 1) {
                    $customer = Customer::find($account->target_id);
                    $item['target_info'] = [
                        'id' => $customer->id ?? 0,
                        'name' => $customer->name ?? '',
                        'contact_person' => $customer->contact_person ?? '',
                        'phone' => $customer->phone ?? ''
                    ];
                } else {
                    $supplier = Supplier::find($account->target_id);
                    $item['target_info'] = [
                        'id' => $supplier->id ?? 0,
                        'name' => $supplier->name ?? '',
                        'contact_person' => $supplier->contact_person ?? '',
                        'phone' => $supplier->phone ?? ''
                    ];
                }
                
                // 获取相关业务信息
                $item['related_info'] = $this->getRelatedBusinessInfo($account->related_type, $account->related_id);
                
                $list[] = $item;
            }
            
            return $this->success([
                'list' => $list,
                'total' => count($list),
                'type' => $type,
                'type_text' => $type == 1 ? '应收账款' : '应付账款'
            ]);
            
        } catch (\Exception $e) {
            return $this->error('查询失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取核销统计
     */
    public function getStatistics()
    {
        try {
            $params = request()->param;
            
            $statistics = AccountSettlement::getSettlementStatistics($params);
            
            // 格式化统计数据
            $data = [
                'receivable' => [
                    'settlement_count' => 0,
                    'settlement_total' => 0,
                    'earliest_date' => null,
                    'latest_date' => null
                ],
                'payable' => [
                    'settlement_count' => 0,
                    'settlement_total' => 0,
                    'earliest_date' => null,
                    'latest_date' => null
                ]
            ];
            
            foreach ($statistics as $stat) {
                if ($stat['account_type'] == 1) {
                    $data['receivable'] = [
                        'settlement_count' => $stat['settlement_count'],
                        'settlement_total' => $stat['settlement_total'],
                        'earliest_date' => $stat['earliest_date'],
                        'latest_date' => $stat['latest_date']
                    ];
                } elseif ($stat['account_type'] == 2) {
                    $data['payable'] = [
                        'settlement_count' => $stat['settlement_count'],
                        'settlement_total' => $stat['settlement_total'],
                        'earliest_date' => $stat['earliest_date'],
                        'latest_date' => $stat['latest_date']
                    ];
                }
            }
            
            return $this->success($data);
            
        } catch (\Exception $e) {
            return $this->error('统计失败: ' . $e->getMessage());
        }
    }

    /**
     * 取消核销（需要高级权限）
     */
    public function cancel($id)
    {
        try {
            // 检查权限
            if (!$this->hasPermission('account_settlement_cancel')) {
                return $this->error('没有取消核销的权限');
            }
            
            $settlement = AccountSettlement::find($id);
            if (!$settlement) {
                return $this->error('核销记录不存在');
            }
            
            Db::transaction(function() use ($settlement) {
                // 1. 获取账款记录
                $account = AccountRecord::find($settlement->account_id);
                if (!$account) {
                    throw new \Exception('账款记录不存在');
                }
                
                // 2. 恢复账款记录金额
                $account->paid_amount -= $settlement->settlement_amount;
                $account->balance_amount += $settlement->settlement_amount;
                $account->status = ($account->balance_amount > 0) ? 1 : 2;
                $account->save();
                
                // 3. 恢复客户或供应商的账款余额
                if ($settlement->account_type == 1) {
                    // 应收 - 恢复客户余额
                    $customer = Customer::find($account->target_id);
                    if ($customer) {
                        $customer->receivable_balance += $settlement->settlement_amount;
                        $customer->save();
                    }
                } else {
                    // 应付 - 恢复供应商余额
                    $supplier = Supplier::find($account->target_id);
                    if ($supplier) {
                        $supplier->payable_balance += $settlement->settlement_amount;
                        $supplier->save();
                    }
                }
                
                // 4. 更新相关订单的已收/已付金额
                $this->updateRelatedOrderOnCancel($account, $settlement->settlement_amount);
                
                // 5. 删除核销记录（软删除）
                $settlement->delete();
                
                // 6. 记录操作日志
                $this->logCancelSettlement($settlement, $account);
            });
            
            return $this->success([], '取消核销成功');
            
        } catch (\Exception $e) {
            return $this->error('取消核销失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取相关业务信息
     */
    private function getRelatedBusinessInfo($relatedType, $relatedId)
    {
        $info = null;
        
        switch ($relatedType) {
            case 'sale_order':
                $order = \app\kincount\model\SaleOrder::find($relatedId);
                if ($order) {
                    $info = [
                        'type' => '销售订单',
                        'no' => $order->order_no,
                        'amount' => $order->final_amount,
                        'status' => $order->status
                    ];
                }
                break;
                
            case 'purchase_order':
                $order = \app\kincount\model\PurchaseOrder::find($relatedId);
                if ($order) {
                    $info = [
                        'type' => '采购订单',
                        'no' => $order->order_no,
                        'amount' => $order->total_amount,
                        'status' => $order->status
                    ];
                }
                break;
                
            case 'sale_return':
                $return = \app\kincount\model\ReturnOrder::find($relatedId);
                if ($return) {
                    $info = [
                        'type' => '销售退货',
                        'no' => $return->return_no,
                        'amount' => $return->refund_amount,
                        'status' => $return->status
                    ];
                }
                break;
                
            case 'purchase_return':
                $return = \app\kincount\model\ReturnOrder::find($relatedId);
                if ($return) {
                    $info = [
                        'type' => '采购退货',
                        'no' => $return->return_no,
                        'amount' => $return->refund_amount,
                        'status' => $return->status
                    ];
                }
                break;
                
            default:
                $info = [
                    'type' => '其他',
                    'no' => '--',
                    'amount' => 0,
                    'status' => 0
                ];
        }
        
        return $info;
    }

    /**
     * 取消核销时更新相关订单金额
     */
    private function updateRelatedOrderOnCancel($account, $amount)
    {
        if ($account->related_type == 'sale_order') {
            $order = \app\kincount\model\SaleOrder::find($account->related_id);
            if ($order) {
                $order->paid_amount -= $amount;
                $order->save();
            }
        } elseif ($account->related_type == 'purchase_order') {
            $order = \app\kincount\model\PurchaseOrder::find($account->related_id);
            if ($order) {
                $order->paid_amount -= $amount;
                $order->save();
            }
        } elseif ($account->related_type == 'sale_return') {
            $return = \app\kincount\model\ReturnOrder::find($account->related_id);
            if ($return) {
                $return->refunded_amount -= $amount;
                $return->save();
            }
        } elseif ($account->related_type == 'purchase_return') {
            $return = \app\kincount\model\ReturnOrder::find($account->related_id);
            if ($return) {
                $return->refunded_amount -= $amount;
                $return->save();
            }
        }
    }

    /**
     * 记录取消核销操作日志
     */
    private function logCancelSettlement($settlement, $account)
    {
        // 实现您的日志记录逻辑
        // 例如：写入操作日志表
        $logData = [
            'action' => 'cancel_settlement',
            'settlement_id' => $settlement->id,
            'settlement_no' => $settlement->settlement_no,
            'account_id' => $account->id,
            'account_type' => $account->type,
            'amount' => $settlement->settlement_amount,
            'operator_id' => $this->getUserId(),
            'operator_name' => '系统操作员', // 根据实际情况获取
            'operate_time' => date('Y-m-d H:i:s'),
            'remark' => '取消核销操作'
        ];
        
        // 保存到操作日志表
        // OperationLog::create($logData);
    }

    /**
     * 检查权限
     */
    private function hasPermission($permission)
    {
        // 根据您的权限系统实现
        // 示例：从JWT token或session中获取权限
        return true; // 暂时返回true，请根据实际情况修改
    }
}