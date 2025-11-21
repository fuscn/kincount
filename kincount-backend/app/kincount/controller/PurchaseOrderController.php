<?php
declare (strict_types = 1);

namespace app\kincount\controller;

use app\kincount\model\PurchaseOrder;
use app\kincount\model\PurchaseOrderItem;
use app\kincount\model\Product;
use app\kincount\model\Supplier;
use app\kincount\model\Warehouse;
use think\facade\Db;

class PurchaseOrderController extends BaseController
{
    public function index()
    {
        $page  = (int)input('page', 1);
        $limit = (int)input('limit', 15);
        $kw    = input('keyword', '');
        $supId = (int)input('supplier_id', 0);
        $status= input('status', '');
        $sDate = input('start_date', '');
        $eDate = input('end_date', '');

        $query = PurchaseOrder::with(['supplier', 'warehouse', 'creator'])
                              ->where('deleted_at', null);

        if ($kw) $query->whereLike('order_no', "%{$kw}%");
        if ($supId) $query->where('supplier_id', $supId);
        if ($status !== '') $query->where('status', $status);
        if ($sDate) $query->where('created_at', '>=', $sDate);
        if ($eDate) $query->where('created_at', '<=', $eDate . ' 23:59:59');

        return $this->paginate($query->order('id', 'desc')
                                   ->paginate(['list_rows' => $limit, 'page' => $page]));
    }

    public function read($id)
    {
        $order = PurchaseOrder::with(['supplier', 'warehouse', 'creator', 'auditor'])
                              ->where('deleted_at', null)->find($id);
        if (!$order) return $this->error('采购订单不存在');

        $order['items'] = PurchaseOrderItem::with(['product.category'])
                                           ->where('purchase_order_id', $id)->select();
        return $this->success($order);
    }

    public function save()
    {
        $post = input('post.');
        $validate = new \think\Validate([
            'supplier_id'  => 'require|integer',
            'warehouse_id' => 'require|integer',
            'items'        => 'require|array|min:1'
        ]);
        if (!$validate->check($post)) return $this->error($validate->getError());

        $order = Db::transaction(function () use ($post) {
            $orderNo = $this->generateOrderNo('PO');
            $total   = 0;

            /* 主表 */
            $order = PurchaseOrder::create([
                'order_no'     => $orderNo,
                'supplier_id'  => $post['supplier_id'],
                'warehouse_id' => $post['warehouse_id'],
                'status'       => 1,
                'remark'       => $post['remark'] ?? '',
                'created_by'   => $this->getUserId(),
                'expected_date'=> $post['expected_date'] ?? null,
            ]);

            /* 明细 */
            foreach ($post['items'] as $v) {
                if (empty($v['product_id']) || empty($v['quantity']) || empty($v['price'])) {
                    throw new \Exception('商品明细不完整');
                }
                $rowTotal = $v['quantity'] * $v['price'];
                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'product_id'        => $v['product_id'],
                    'quantity'          => $v['quantity'],
                    'received_quantity' => 0,
                    'price'             => $v['price'],
                    'total_amount'      => $rowTotal,
                ]);
                $total += $rowTotal;
            }

            $order->save(['total_amount' => $total]);
            return $order;
        });

        return $this->success(['id' => $order->id], '采购订单创建成功');
    }

    public function update($id)
    {
        $order = PurchaseOrder::where('deleted_at', null)->find($id);
        if (!$order) return $this->error('采购订单不存在');
        if ($order->status != 1) return $this->error('只有待审核状态可修改');

        $post = input('post.');
        Db::transaction(function () use ($order, $post) {
            /* 基础信息 */
            $order->save([
                'supplier_id'  => $post['supplier_id'] ?? $order->supplier_id,
                'warehouse_id' => $post['warehouse_id'] ?? $order->warehouse_id,
                'remark'       => $post['remark'] ?? $order->remark,
                'expected_date'=> $post['expected_date'] ?? $order->expected_date,
            ]);

            /* 若传了 items 则整体替换 */
            if (!empty($post['items']) && is_array($post['items'])) {
                PurchaseOrderItem::where('purchase_order_id', $order->id)->delete();
                $total = 0;
                foreach ($post['items'] as $v) {
                    $rowTotal = $v['quantity'] * $v['price'];
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $order->id,
                        'product_id'        => $v['product_id'],
                        'quantity'          => $v['quantity'],
                        'received_quantity' => 0,
                        'price'             => $v['price'],
                        'total_amount'      => $rowTotal,
                    ]);
                    $total += $rowTotal;
                }
                $order->save(['total_amount' => $total]);
            }
        });

        return $this->success([], '采购订单更新成功');
    }

    public function delete($id)
    {
        $order = PurchaseOrder::where('deleted_at', null)->find($id);
        if (!$order) return $this->error('采购订单不存在');
        if ($order->status != 1) return $this->error('只有待审核状态可删除');

        Db::transaction(function () use ($order) {
            PurchaseOrderItem::where('purchase_order_id', $order->id)->delete();
            $order->delete();
        });
        return $this->success([], '采购订单删除成功');
    }

    public function audit($id)
    {
        $order = PurchaseOrder::where('deleted_at', null)->find($id);
        if (!$order) return $this->error('采购订单不存在');
        if ($order->status != 1) return $this->error('当前状态无法审核');

        $order->save(['status' => 2, 'audit_by' => $this->getUserId(), 'audit_time' => date('Y-m-d H:i:s')]);
        return $this->success([], '审核成功');
    }

    public function cancel($id)
    {
        $order = PurchaseOrder::where('deleted_at', null)->find($id);
        if (!$order) return $this->error('采购订单不存在');
        if (!in_array($order->status, [1, 2])) return $this->error('当前状态不能取消');

        $order->save(['status' => 5]);
        return $this->success([], '已取消');
    }

    public function complete($id)
    {
        $order = PurchaseOrder::where('deleted_at', null)->find($id);
        if (!$order) return $this->error('采购订单不存在');
        if ($order->status != 2) return $this->error('只有已审核状态可完成');

        $order->save(['status' => 4]);
        return $this->success([], '订单已完成');
    }

    public function items($id)
    {
        return $this->success(
            PurchaseOrderItem::with(['product.category'])
                             ->where('purchase_order_id', $id)->select()
        );
    }

    public function addItem($id)
    {
        $order = PurchaseOrder::where('deleted_at', null)->find($id);
        if (!$order) return $this->error('采购订单不存在');
        if ($order->status != 1) return $this->error('只有待审核可添加明细');

        $post = input('post.');
        $validate = new \think\Validate([
            'product_id' => 'require|integer',
            'quantity'   => 'require|integer|gt:0',
            'price'      => 'require|float|gt:0'
        ]);
        if (!$validate->check($post)) return $this->error($validate->getError());

        Db::transaction(function () use ($order, $post) {
            $rowTotal = $post['quantity'] * $post['price'];
            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id,
                'product_id'        => $post['product_id'],
                'quantity'          => $post['quantity'],
                'received_quantity' => 0,
                'price'             => $post['price'],
                'total_amount'      => $rowTotal,
            ]);
            $order->inc('total_amount', $rowTotal)->save();
        });

        return $this->success([], '明细添加成功');
    }

    public function updateItem($id, $item_id)
    {
        $order = PurchaseOrder::where('deleted_at', null)->find($id);
        if (!$order) return $this->error('采购订单不存在');
        if ($order->status != 1) return $this->error('只有待审核可修改明细');

        $item = PurchaseOrderItem::where('purchase_order_id', $id)->find($item_id);
        if (!$item) return $this->error('明细不存在');

        $post = input('post.');
        $oldTotal = $item->total_amount;
        $newQty   = $post['quantity'] ?? $item->quantity;
        $newPrice = $post['price'] ?? $item->price;
        $newTotal = $newQty * $newPrice;

        Db::transaction(function () use ($item, $order, $oldTotal, $newTotal, $newQty, $newPrice) {
            $item->save(['quantity' => $newQty, 'price' => $newPrice, 'total_amount' => $newTotal]);
            $order->inc('total_amount', $newTotal - $oldTotal)->save();
        });

        return $this->success([], '明细更新成功');
    }

    public function deleteItem($id, $item_id)
    {
        $order = PurchaseOrder::where('deleted_at', null)->find($id);
        if (!$order) return $this->error('采购订单不存在');
        if ($order->status != 1) return $this->error('只有待审核可删除明细');

        $item = PurchaseOrderItem::where('purchase_order_id', $id)->find($item_id);
        if (!$item) return $this->error('明细不存在');

        Db::transaction(function () use ($item, $order) {
            $order->dec('total_amount', $item->total_amount)->save();
            $item->delete();
        });

        return $this->success([], '明细删除成功');
    }

    /* ---------- 工具 ---------- */
    private function generateOrderNo($prefix = 'PO')
    {
        $date = date('Ymd');
        $num  = PurchaseOrder::whereLike('order_no', $prefix . $date . '%')->count() + 1;
        return $prefix . $date . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
    }
}