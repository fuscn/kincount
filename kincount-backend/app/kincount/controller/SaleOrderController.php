<?php
declare (strict_types = 1);

namespace app\kincount\controller;

use app\kincount\model\SaleOrder;
use app\kincount\model\SaleOrderItem;
use app\kincount\model\Customer;
use app\kincount\model\Product;
use app\kincount\model\Stock;
use app\kincount\model\Warehouse;
use think\facade\Db;

class SaleOrderController extends BaseController
{
    public function index()
    {
        $page  = (int)input('page', 1);
        $limit = (int)input('limit', 15);
        $kw    = input('keyword', '');
        $cusId = (int)input('customer_id', 0);
        $status= input('status', '');
        $sDate = input('start_date', '');
        $eDate = input('end_date', '');

        $query = SaleOrder::with(['customer', 'warehouse', 'creator'])
                          ->where('deleted_at', null);

        if ($kw) $query->whereLike('order_no', "%{$kw}%");
        if ($cusId) $query->where('customer_id', $cusId);
        if ($status !== '') $query->where('status', $status);
        if ($sDate) $query->where('created_at', '>=', $sDate);
        if ($eDate) $query->where('created_at', '<=', $eDate . ' 23:59:59');

        return $this->paginate($query->order('id', 'desc')
                                   ->paginate(['list_rows' => $limit, 'page' => $page]));
    }

    public function read($id)
    {
        $order = SaleOrder::with(['customer', 'warehouse', 'creator', 'auditor'])
                          ->where('deleted_at', null)->find($id);
        if (!$order) return $this->error('销售订单不存在');

        $order['items'] = SaleOrderItem::with(['product.category'])
                                       ->where('sale_order_id', $id)->select();
        return $this->success($order);
    }

    public function save()
    {
        $post = input('post.');
        $validate = new \think\Validate([
            'customer_id'  => 'require|integer',
            'warehouse_id' => 'require|integer',
            'items'        => 'require|array|min:1'
        ]);
        if (!$validate->check($post)) return $this->error($validate->getError());

        $order = Db::transaction(function () use ($post) {
            $orderNo = $this->generateOrderNo('SO');
            $customer = Customer::where('deleted_at', null)->find($post['customer_id']);
            $discount = $customer ? $customer->discount : 1.0;

            /* 主表 */
            $order = SaleOrder::create([
                'order_no'      => $orderNo,
                'customer_id'   => $post['customer_id'],
                'warehouse_id'  => $post['warehouse_id'],
                'status'        => 1,
                'remark'        => $post['remark'] ?? '',
                'created_by'    => $this->getUserId(),
                'expected_date' => $post['expected_date'] ?? null,
            ]);

            /* 明细 + 库存校验 */
            $total = 0;
            foreach ($post['items'] as $v) {
                if (empty($v['product_id']) || empty($v['quantity']) || empty($v['price'])) {
                    throw new \Exception('商品明细不完整');
                }
                $stock = Stock::where('product_id', $v['product_id'])
                              ->where('warehouse_id', $post['warehouse_id'])->find();
                if (!$stock || $stock->quantity < $v['quantity']) {
                    $prod = Product::find($v['product_id']);
                    throw new \Exception("商品 {$prod->name} 库存不足");
                }
                $rowTotal = $v['quantity'] * $v['price'];
                SaleOrderItem::create([
                    'sale_order_id'    => $order->id,
                    'product_id'       => $v['product_id'],
                    'quantity'         => $v['quantity'],
                    'delivered_quantity'=> 0,
                    'price'            => $v['price'],
                    'total_amount'     => $rowTotal,
                ]);
                $total += $rowTotal;
            }

            /* 折扣/最终金额 */
            $discountAmount = $total * (1 - $discount);
            $order->save([
                'total_amount'   => $total,
                'discount_amount'=> $discountAmount,
                'final_amount'   => $total - $discountAmount,
            ]);

            return $order;
        });

        return $this->success(['id' => $order->id], '销售订单创建成功');
    }

    public function update($id)
    {
        $order = SaleOrder::where('deleted_at', null)->find($id);
        if (!$order) return $this->error('销售订单不存在');
        if ($order->status != 1) return $this->error('只有待审核可修改');

        $post = input('post.');
        Db::transaction(function () use ($order, $post) {
            /* 基础信息 */
            $order->save([
                'customer_id'   => $post['customer_id'] ?? $order->customer_id,
                'warehouse_id'  => $post['warehouse_id'] ?? $order->warehouse_id,
                'remark'        => $post['remark'] ?? $order->remark,
                'expected_date' => $post['expected_date'] ?? $order->expected_date,
            ]);

            /* 若传了 items 则整体替换 */
            if (!empty($post['items']) && is_array($post['items'])) {
                SaleOrderItem::where('sale_order_id', $order->id)->delete();
                $customer = Customer::find($post['customer_id'] ?? $order->customer_id);
                $discount = $customer ? $customer->discount : 1.0;

                $total = 0;
                foreach ($post['items'] as $v) {
                    $stock = Stock::where('product_id', $v['product_id'])
                                  ->where('warehouse_id', $post['warehouse_id'] ?? $order->warehouse_id)->find();
                    if (!$stock || $stock->quantity < $v['quantity']) {
                        $prod = Product::find($v['product_id']);
                        throw new \Exception("商品 {$prod->name} 库存不足");
                    }
                    $rowTotal = $v['quantity'] * $v['price'];
                    SaleOrderItem::create([
                        'sale_order_id'     => $order->id,
                        'product_id'        => $v['product_id'],
                        'quantity'          => $v['quantity'],
                        'delivered_quantity'=> 0,
                        'price'             => $v['price'],
                        'total_amount'      => $rowTotal,
                    ]);
                    $total += $rowTotal;
                }
                $discountAmount = $total * (1 - $discount);
                $order->save([
                    'total_amount'    => $total,
                    'discount_amount' => $discountAmount,
                    'final_amount'    => $total - $discountAmount,
                ]);
            }
        });

        return $this->success([], '销售订单更新成功');
    }

    public function delete($id)
    {
        $order = SaleOrder::where('deleted_at', null)->find($id);
        if (!$order) return $this->error('销售订单不存在');
        if ($order->status != 1) return $this->error('只有待审核可删除');

        Db::transaction(function () use ($order) {
            SaleOrderItem::where('sale_order_id', $order->id)->delete();
            $order->delete();
        });
        return $this->success([], '销售订单删除成功');
    }

    public function audit($id)
    {
        $order = SaleOrder::where('deleted_at', null)->find($id);
        if (!$order) return $this->error('销售订单不存在');
        if ($order->status != 1) return $this->error('当前状态无法审核');

        $order->save(['status' => 2, 'audit_by' => $this->getUserId(), 'audit_time' => date('Y-m-d H:i:s')]);
        return $this->success([], '审核成功');
    }

    public function cancel($id)
    {
        $order = SaleOrder::where('deleted_at', null)->find($id);
        if (!$order) return $this->error('销售订单不存在');
        if (!in_array($order->status, [1, 2])) return $this->error('当前状态不能取消');

        $order->save(['status' => 5]);
        return $this->success([], '已取消');
    }

    public function complete($id)
    {
        $order = SaleOrder::where('deleted_at', null)->find($id);
        if (!$order) return $this->error('销售订单不存在');
        if ($order->status != 2) return $this->error('只有已审核可完成');

        $order->save(['status' => 4]);
        return $this->success([], '订单已完成');
    }

    public function items($id)
    {
        return $this->success(
            SaleOrderItem::with(['product.category'])->where('sale_order_id', $id)->select()
        );
    }

    public function addItem($id)
    {
        $order = SaleOrder::where('deleted_at', null)->find($id);
        if (!$order) return $this->error('销售订单不存在');
        if ($order->status != 1) return $this->error('只有待审核可添加明细');

        $post = input('post.');
        $validate = new \think\Validate([
            'product_id' => 'require|integer',
            'quantity'   => 'require|integer|gt:0',
            'price'      => 'require|float|gt:0'
        ]);
        if (!$validate->check($post)) return $this->error($validate->getError());

        Db::transaction(function () use ($order, $post) {
            $stock = Stock::where('product_id', $post['product_id'])
                          ->where('warehouse_id', $order->warehouse_id)->find();
            if (!$stock || $stock->quantity < $post['quantity']) {
                $prod = Product::find($post['product_id']);
                throw new \Exception("商品 {$prod->name} 库存不足");
            }
            $rowTotal = $post['quantity'] * $post['price'];
            SaleOrderItem::create([
                'sale_order_id'     => $order->id,
                'product_id'        => $post['product_id'],
                'quantity'          => $post['quantity'],
                'delivered_quantity'=> 0,
                'price'             => $post['price'],
                'total_amount'      => $rowTotal,
            ]);
            $total = $order->total_amount + $rowTotal;
            $discountAmount = $total * (1 - ($order->discount_amount / $order->total_amount));
            $order->save([
                'total_amount'    => $total,
                'discount_amount' => $discountAmount,
                'final_amount'    => $total - $discountAmount,
            ]);
        });

        return $this->success([], '明细添加成功');
    }

    public function updateItem($id, $item_id)
    {
        $order = SaleOrder::where('deleted_at', null)->find($id);
        if (!$order) return $this->error('销售订单不存在');
        if ($order->status != 1) return $this->error('只有待审核可修改明细');

        $item = SaleOrderItem::where('sale_order_id', $id)->find($item_id);
        if (!$item) return $this->error('明细不存在');

        $post = input('post.');
        $oldTotal = $item->total_amount;
        $newQty   = $post['quantity'] ?? $item->quantity;
        $newPrice = $post['price'] ?? $item->price;
        $newTotal = $newQty * $newPrice;

        Db::transaction(function () use ($item, $order, $oldTotal, $newTotal, $newQty, $newPrice) {
            if ($newQty != $item->quantity) {
                $stock = Stock::where('product_id', $item->product_id)
                              ->where('warehouse_id', $order->warehouse_id)->find();
                if (!$stock || $stock->quantity < $newQty) {
                    $prod = Product::find($item->product_id);
                    throw new \Exception("商品 {$prod->name} 库存不足");
                }
            }
            $item->save(['quantity' => $newQty, 'price' => $newPrice, 'total_amount' => $newTotal]);
            $total = $order->total_amount - $oldTotal + $newTotal;
            $discountAmount = $total * (1 - ($order->discount_amount / $order->total_amount));
            $order->save([
                'total_amount'    => $total,
                'discount_amount' => $discountAmount,
                'final_amount'    => $total - $discountAmount,
            ]);
        });

        return $this->success([], '明细更新成功');
    }

    public function deleteItem($id, $item_id)
    {
        $order = SaleOrder::where('deleted_at', null)->find($id);
        if (!$order) return $this->error('销售订单不存在');
        if ($order->status != 1) return $this->error('只有待审核可删除明细');

        $item = SaleOrderItem::where('sale_order_id', $id)->find($item_id);
        if (!$item) return $this->error('明细不存在');

        Db::transaction(function () use ($item, $order) {
            $total = $order->total_amount - $item->total_amount;
            $discountAmount = $total * (1 - ($order->discount_amount / $order->total_amount));
            $order->save([
                'total_amount'    => $total,
                'discount_amount' => $discountAmount,
                'final_amount'    => $total - $discountAmount,
            ]);
            $item->delete();
        });

        return $this->success([], '明细删除成功');
    }

    private function generateOrderNo($prefix = 'SO')
    {
        $date = date('Ymd');
        $num  = SaleOrder::whereLike('order_no', $prefix . $date . '%')->count() + 1;
        return $prefix . $date . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
    }
}