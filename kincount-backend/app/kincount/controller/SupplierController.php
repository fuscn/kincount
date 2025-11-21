<?php
declare (strict_types = 1);

namespace app\kincount\controller;

use app\kincount\model\Supplier;
use app\kincount\model\PurchaseOrder;
use app\kincount\model\AccountRecord;
use think\facade\Db;

class SupplierController extends BaseController
{
    public function index()
    {
        $page  = (int)input('page', 1);
        $limit = (int)input('limit', 15);
        $kw    = input('keyword', '');
        $status= input('status', '');

        $query = Supplier::where('deleted_at', null);
        if ($kw) $query->whereLike('name|contact_person|phone', "%{$kw}%");
        if ($status !== '') $query->where('status', $status);

        return $this->paginate($query->order('id', 'desc')
                                   ->paginate(['list_rows' => $limit, 'page' => $page]));
    }

    public function read($id)
    {
        $supplier = Supplier::where('deleted_at', null)->find($id);
        if (!$supplier) return $this->error('供应商不存在');
        return $this->success($supplier);
    }

    public function save()
    {
        $post = input('post.');
        $validate = new \think\Validate(['name' => 'require']);
        if (!$validate->check($post)) return $this->error($validate->getError());

        $id = Supplier::create($post)->id;
        return $this->success(['id' => $id], '供应商添加成功');
    }

    public function update($id)
    {
        $supplier = Supplier::where('deleted_at', null)->find($id);
        if (!$supplier) return $this->error('供应商不存在');

        $supplier->save(input('post.'));
        return $this->success([], '供应商更新成功');
    }

    public function delete($id)
    {
        $supplier = Supplier::where('deleted_at', null)->find($id);
        if (!$supplier) return $this->error('供应商不存在');
        if (PurchaseOrder::where('supplier_id', $id)->find()) return $this->error('供应商已关联采购订单，无法删除');

        $supplier->delete();
        return $this->success([], '供应商删除成功');
    }

    public function selectSearch()
    {
        $kw = input('keyword', '');
        return $this->success(
            Supplier::where('status', 1)->where('deleted_at', null)
                   ->whereLike('name|contact_person|phone', "%{$kw}%")
                   ->field('id, name, contact_person, phone')
                   ->order('id', 'desc')->limit((int)input('limit', 10))->select()
        );
    }

    public function arrears($id)
    {
        $supplier = Supplier::where('deleted_at', null)->find($id);
        if (!$supplier) return $this->error('供应商不存在');
        $payable = AccountRecord::where('type', 2)
                                ->where('target_id', $id)
                                ->where('status', 1)
                                ->sum('balance_amount');
        return $this->success(['supplier' => $supplier, 'payables' => $payable ?: 0]);
    }

    public function updateArrears($id)
    {
        $supplier = Supplier::where('deleted_at', null)->find($id);
        if (!$supplier) return $this->error('供应商不存在');

        $amount = (float)input('amount', 0);
        $type   = input('type', 'add');

        Db::transaction(function () use ($supplier, $amount, $type) {
            $current = $supplier->arrears_amount;
            $new     = $type === 'add' ? $current + $amount : max(0, $current - $amount);
            $supplier->save(['arrears_amount' => $new]);
        });

        return $this->success([], '供应商欠款更新成功');
    }
}