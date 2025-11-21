<?php
declare (strict_types = 1);

namespace app\kincount\controller;

use app\kincount\model\Warehouse;
use app\kincount\model\Stock;
use app\kincount\model\PurchaseOrder;
use app\kincount\model\SaleOrder;

class WarehouseController extends BaseController
{
    public function index()
    {
        $page    = (int)input('page', 1);
        $limit   = (int)input('limit', 15);
        $keyword = input('keyword', '');
        $status  = input('status', '');

        $query = Warehouse::where('deleted_at', null);
        if ($keyword !== '') {
            $query->whereLike('name|code|address', "%{$keyword}%");
        }
        if ($status !== '') {
            $query->where('status', $status);
        }

        $list = $query->order('id', 'desc')
                      ->paginate(['list_rows' => $limit, 'page' => $page]);

        return $this->paginate($list);
    }

    public function read($id)
    {
        $warehouse = Warehouse::where('deleted_at', null)->find($id);
        if (!$warehouse) return $this->error('仓库不存在');
        return $this->success($warehouse);
    }

    public function save()
    {
        $data = input('post.');
        $validate = new \think\Validate([
            'name' => 'require',
            'code' => 'require|unique:warehouses',
        ]);
        if (!$validate->check($data)) return $this->error($validate->getError());

        $warehouse = Warehouse::create($data);
        return $this->success(['id' => $warehouse->id], '仓库新增成功');
    }

    public function update($id)
    {
        $warehouse = Warehouse::where('deleted_at', null)->find($id);
        if (!$warehouse) return $this->error('仓库不存在');

        $data = input('post.');
        if (isset($data['code']) && $data['code'] != $warehouse->code) {
            $exists = Warehouse::where('code', $data['code'])->where('id', '<>', $id)->find();
            if ($exists) return $this->error('仓库编码已存在');
        }

        $warehouse->save($data);
        return $this->success([], '仓库更新成功');
    }

    public function delete($id)
    {
        $warehouse = Warehouse::where('deleted_at', null)->find($id);
        if (!$warehouse) return $this->error('仓库不存在');

        if (Stock::where('warehouse_id', $id)->where('deleted_at', null)->find()) {
            return $this->error('该仓库存在库存记录，无法删除');
        }
        if (PurchaseOrder::where('warehouse_id', $id)->where('deleted_at', null)->find()) {
            return $this->error('该仓库存在采购订单，无法删除');
        }
        if (SaleOrder::where('warehouse_id', $id)->where('deleted_at', null)->find()) {
            return $this->error('该仓库存在销售订单，无法删除');
        }

        $warehouse->delete();
        return $this->success([], '仓库删除成功');
    }

    public function options()
    {
        $list = Warehouse::where('status', 1)
                         ->where('deleted_at', null)
                         ->field('id, name, code')
                         ->order('id', 'asc')
                         ->select();
        return $this->success($list);
    }

    public function statistics($id)
    {
        $warehouse = Warehouse::where('deleted_at', null)->find($id);
        if (!$warehouse) return $this->error('仓库不存在');

        $stockStats = Stock::where('warehouse_id', $id)
                           ->where('deleted_at', null)
                           ->field('SUM(quantity) as total_quantity, SUM(total_amount) as total_amount, COUNT(*) as product_count')
                           ->find();

        $lowStockCount = Stock::where('warehouse_id', $id)
                              ->where('quantity <= min_stock')
                              ->where('deleted_at', null)
                              ->count();

        return $this->success([
            'warehouse'    => $warehouse,
            'stock_stats'  => [
                'total_quantity' => $stockStats['total_quantity'] ?: 0,
                'total_amount'   => $stockStats['total_amount'] ?: 0,
                'product_count'  => $stockStats['product_count'] ?: 0,
            ],
            'low_stock_count' => $lowStockCount,
        ]);
    }
}