<?php

declare(strict_types=1);

namespace app\kincount\controller;

use app\kincount\model\AccountRecord;
use app\kincount\model\Customer;
use app\kincount\model\Supplier;
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
        $validate = new \think\Validate([
            'type'           => 'require|in:1,2',
            'target_id'      => 'require|integer',
            'amount'         => 'require|float|gt:0',
            'balance_amount' => 'require|float|gt:0',
            'record_date'    => 'require|date'
        ]);
        if (!$validate->check($post)) return $this->error($validate->getError());

        $record = Db::transaction(function () use ($post) {
            $recordNo = $this->generateRecordNo($post['type']);

            $record = AccountRecord::create([
                'record_no'      => $recordNo,
                'type'           => $post['type'],
                'target_id'      => $post['target_id'],
                'amount'         => $post['amount'],
                'balance_amount' => $post['balance_amount'],
                'record_date'    => $post['record_date'],
                'due_date'       => $post['due_date'] ?? null,
                'description'    => $post['description'] ?? '',
                'status'         => 1,
                'created_by'     => $this->getUserId(),
            ]);

            /* 更新客户/供应商欠款 */
            $this->updateTargetArrears($post['type'], $post['target_id'], $post['balance_amount'], 'add');

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

    /* ---------- 内部工具 ---------- */
    private function generateRecordNo($type)
    {
        $prefix = $type == 1 ? 'AR' : 'AP';
        $date   = date('Ymd');
        $num    = AccountRecord::whereLike('record_no', $prefix . $date . '%')->count() + 1;
        return $prefix . $date . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
    }

    private function updateTargetArrears($type, $targetId, $amount, $operation)
    {
        $table = $type == 1 ? 'customers' : 'suppliers';
        $field = 'arrears_amount';

        $target = \think\facade\Db::table($table)->where('id', $targetId)->find();
        if ($target) {
            $current = $target[$field] ?: 0;
            $new     = $operation === 'add' ? $current + $amount : max(0, $current - $amount);
            \think\facade\Db::table($table)->where('id', $targetId)->update([$field => $new]);
        }
    }
    /** 支付账款 */
    public function pay($id)
    {
        $record = AccountRecord::where('deleted_at', null)->find($id);
        if (!$record) return $this->error('账款记录不存在');
        if ($record->status == 2) return $this->error('已结清，无需重复支付');

        $amount = input('amount', 0);
        if ($amount <= 0) return $this->error('支付金额必须大于 0');

        Db::transaction(function () use ($record, $amount) {
            $record->save([
                'paid_amount' => $record->paid_amount + $amount,
                'balance_amount' => $record->balance_amount - $amount,
                'settled_at' => date('Y-m-d H:i:s'),
            ]);

            if ($record->balance_amount <= 0) {
                $record->save(['status' => 2]);
            }

            /* 更新客户/供应商欠款 */
            $this->updateTargetArrears($record->type, $record->target_id, $amount, 'subtract');
        });

        return $this->success([], '支付成功');
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

        $query = AccountRecord::with(['creator'])
            ->where('type', 1)
            ->where('deleted_at', null);

        if ($kw) $query->whereLike('record_no|description', "%{$kw}%");
        if ($status !== '') $query->where('status', $status);

        return $this->paginate($query->order('record_date', 'desc')
            ->paginate(['list_rows' => $limit, 'page' => $page]));
    }
}
