<?php
declare (strict_types = 1);

namespace app\kincount\controller;

use app\kincount\model\Customer;
use app\kincount\model\SaleOrder;
use app\kincount\model\AccountRecord;
use think\facade\Db;

class CustomerController extends BaseController
{
    public function index()
    {
        $page  = (int)input('page', 1);
        $limit = (int)input('limit', 15);
        $kw    = input('keyword', '');
        $level = input('level', '');
        $status= input('status', '');

        $query = Customer::where('deleted_at', null);
        if ($kw) $query->whereLike('name|contact_person|phone', "%{$kw}%");
        if ($level !== '') $query->where('level', $level);
        if ($status !== '') $query->where('status', $status);

        return $this->paginate($query->order('id', 'desc')
                                   ->paginate(['list_rows' => $limit, 'page' => $page]));
    }

    public function read($id)
    {
        $customer = Customer::where('deleted_at', null)->find($id);
        if (!$customer) return $this->error('客户不存在');
        return $this->success($customer);
    }

    public function save()
    {
        $post = input('post.');
        $validate = new \think\Validate(['name' => 'require', 'type' => 'in:1,2', 'level' => 'in:1,2,3']);
        if (!$validate->check($post)) return $this->error($validate->getError());

        $id = Customer::create($post)->id;
        return $this->success(['id' => $id], '客户添加成功');
    }

    public function update($id)
    {
        $customer = Customer::where('deleted_at', null)->find($id);
        if (!$customer) return $this->error('客户不存在');

        $post = input('post.');
        $validate = new \think\Validate(['type' => 'in:1,2', 'level' => 'in:1,2,3']);
        if (!$validate->check($post)) return $this->error($validate->getError());

        $customer->save($post);
        return $this->success([], '客户更新成功');
    }

    public function delete($id)
    {
        $customer = Customer::where('deleted_at', null)->find($id);
        if (!$customer) return $this->error('客户不存在');
        if (SaleOrder::where('customer_id', $id)->find()) return $this->error('客户已关联销售订单，无法删除');

        $customer->delete();
        return $this->success([], '客户删除成功');
    }

    public function selectSearch()
    {
        $kw = input('keyword', '');
        return $this->success(
            Customer::where('status', 1)->where('deleted_at', null)
                   ->whereLike('name|contact_person|phone', "%{$kw}%")
                   ->field('id, name, contact_person, phone, level, discount')
                   ->order('id', 'desc')->limit((int)input('limit', 10))->select()
        );
    }

    public function arrears($id)
    {
        $customer = Customer::where('deleted_at', null)->find($id);
        if (!$customer) return $this->error('客户不存在');
        $receivable = AccountRecord::where('type', 1)
                                   ->where('target_id', $id)
                                   ->where('status', 1)
                                   ->sum('balance_amount');
        return $this->success(['customer' => $customer, 'receivables' => $receivable ?: 0]);
    }

    public function updateArrears($id)
    {
        $customer = Customer::where('deleted_at', null)->find($id);
        if (!$customer) return $this->error('客户不存在');

        $amount = (float)input('amount', 0);
        $type   = input('type', 'add');

        Db::transaction(function () use ($customer, $amount, $type) {
            $current = $customer->arrears_amount;
            $new     = $type === 'add' ? $current + $amount : max(0, $current - $amount);
            $customer->save(['arrears_amount' => $new]);
        });

        return $this->success([], '欠款更新成功');
    }

    public function statistics()
    {
        return $this->success([
            'level_stats'    => Customer::where('status', 1)->where('deleted_at', null)->field('level, count(*) count')->group('level')->select(),
            'type_stats'     => Customer::where('status', 1)->where('deleted_at', null)->field('type, count(*) count')->group('type')->select(),
            'total_customers'=> Customer::where('status', 1)->where('deleted_at', null)->count(),
            'total_arrears'  => Customer::where('status', 1)->where('deleted_at', null)->sum('arrears_amount') ?: 0,
        ]);
    }
}