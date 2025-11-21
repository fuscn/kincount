<?php
declare (strict_types = 1);

namespace app\kincount\controller;

use app\kincount\model\FinancialRecord;
use think\facade\Db;

class FinancialRecordController extends BaseController
{
    public function index()
    {
        $page  = (int)input('page', 1);
        $limit = (int)input('limit', 15);
        $type  = input('type', '');
        $cate  = input('category', '');
        $payM  = input('payment_method', '');
        $sDate = input('start_date', '');
        $eDate = input('end_date', '');
        $kw    = input('keyword', '');

        $query = FinancialRecord::with(['creator'])->where('deleted_at', null);

        if ($type !== '') $query->where('type', $type);
        if ($cate) $query->where('category', $cate);
        if ($payM) $query->where('payment_method', $payM);
        if ($sDate) $query->where('record_date', '>=', $sDate);
        if ($eDate) $query->where('record_date', '<=', $eDate);
        if ($kw) $query->whereLike('record_no|description', "%{$kw}%");

        return $this->paginate($query->order('record_date', 'desc')->order('id', 'desc')
                                   ->paginate(['list_rows' => $limit, 'page' => $page]));
    }

    public function read($id)
    {
        $record = FinancialRecord::with(['creator'])->where('deleted_at', null)->find($id);
        if (!$record) return $this->error('财务记录不存在');
        return $this->success($record);
    }

    public function save()
    {
        $post = input('post.');
        $validate = new \think\Validate([
            'type'           => 'require|in:1,2',
            'category'       => 'require',
            'amount'         => 'require|float|gt:0',
            'record_date'    => 'require|date',
            'payment_method' => 'require'
        ]);
        if (!$validate->check($post)) return $this->error($validate->getError());

        $record = Db::transaction(function () use ($post) {
            $recordNo = $this->generateRecordNo($post['type']);

            return FinancialRecord::create([
                'record_no'      => $recordNo,
                'type'           => $post['type'],
                'category'       => $post['category'],
                'amount'         => $post['amount'],
                'record_date'    => $post['record_date'],
                'payment_method' => $post['payment_method'],
                'description'    => $post['description'] ?? '',
                'created_by'     => $this->getUserId(),
            ]);
        });

        return $this->success(['id' => $record->id], '财务记录添加成功');
    }

    public function update($id)
    {
        $record = FinancialRecord::where('deleted_at', null)->find($id);
        if (!$record) return $this->error('财务记录不存在');

        $post = input('post.');
        $validate = new \think\Validate([
            'type'   => 'in:1,2',
            'amount' => 'float|gt:0',
            'record_date' => 'date'
        ]);
        if (!$validate->check($post)) return $this->error($validate->getError());

        $record->save($post);
        return $this->success([], '财务记录更新成功');
    }

    public function delete($id)
    {
        $record = FinancialRecord::where('deleted_at', null)->find($id);
        if (!$record) return $this->error('财务记录不存在');

        $record->delete();
        return $this->success([], '财务记录删除成功');
    }

    public function categories()
    {
        $type = input('type', '');
        $list = [
            'income' => [
                '销售收入' => '销售收入',
                '其他收入' => '其他收入',
                '退款收入' => '退款收入',
                '投资收益' => '投资收益'
            ],
            'expense' => [
                '采购支出' => '采购支出',
                '工资支出' => '工资支出',
                '租金支出' => '租金支出',
                '水电费'   => '水电费',
                '运输费'   => '运输费',
                '营销费用' => '营销费用',
                '维修费用' => '维修费用',
                '其他支出' => '其他支出'
            ]
        ];
        if ($type === 'income')  return $this->success($list['income']);
        if ($type === 'expense') return $this->success($list['expense']);
        return $this->success($list);
    }

    public function statistics()
    {
        $sDate = input('start_date', date('Y-m-01'));
        $eDate = input('end_date', date('Y-m-d'));

        /* 收入 */
        $incomeStats = FinancialRecord::where('type', 1)
                                      ->whereBetween('record_date', [$sDate, $eDate])
                                      ->field('category, sum(amount) total_amount')
                                      ->group('category')->select();
        $totalIncome = FinancialRecord::where('type', 1)
                                      ->whereBetween('record_date', [$sDate, $eDate])
                                      ->sum('amount');

        /* 支出 */
        $expenseStats = FinancialRecord::where('type', 2)
                                       ->whereBetween('record_date', [$sDate, $eDate])
                                       ->field('category, sum(amount) total_amount')
                                       ->group('category')->select();
        $totalExpense = FinancialRecord::where('type', 2)
                                       ->whereBetween('record_date', [$sDate, $eDate])
                                       ->sum('amount');

        /* 月度趋势 */
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $in  = FinancialRecord::where('type', 1)->whereRaw("DATE_FORMAT(record_date,'%Y-%m')=?", [$month])->sum('amount');
            $out = FinancialRecord::where('type', 2)->whereRaw("DATE_FORMAT(record_date,'%Y-%m')=?", [$month])->sum('amount');
            $trend[] = [
                'month'  => $month,
                'income' => $in ?: 0,
                'expense'=> $out ?: 0,
                'profit' => $in - $out,
            ];
        }

        return $this->success([
            'income_stats'  => $incomeStats,
            'expense_stats' => $expenseStats,
            'total_income'  => $totalIncome ?: 0,
            'total_expense' => $totalExpense ?: 0,
            'net_profit'    => ($totalIncome ?: 0) - ($totalExpense ?: 0),
            'monthly_trend' => $trend,
        ]);
    }

    private function generateRecordNo($type)
    {
        $prefix = $type == 1 ? 'IN' : 'EX';
        $date   = date('Ymd');
        $num    = FinancialRecord::whereLike('record_no', $prefix . $date . '%')->count() + 1;
        return $prefix . $date . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
    }
}