<?php

declare(strict_types=1);

namespace app\kincount\controller;

use think\facade\Db;

class DashboardController extends BaseController
{
    /* 数据概览 */
    public function overview()
    {
        $today = date('Y-m-d');
        $month = date('Y-m');

        /* 今日销售 */
        $todaySales = Db::name('sale_orders')
            ->where('status', 4)
            ->whereRaw("DATE(created_at)=?", [$today])
            ->sum('final_amount');

        /* 本月销售 */
        $monthSales = Db::name('sale_orders')
            ->where('status', 4)
            ->whereRaw("DATE_FORMAT(created_at,'%Y-%m')=?", [$month])
            ->sum('final_amount');

        /* 今日采购 */
        $todayPurchase = Db::name('purchase_orders')
            ->where('status', 4)
            ->whereRaw("DATE(created_at)=?", [$today])
            ->sum('total_amount');

        /* 库存预警 */
        $warningStockCnt = Db::name('stocks')
            ->alias('s')
            ->join('product_skus sku', 's.sku_id = sku.id')
            ->join('products p', 'sku.product_id = p.id')
            ->whereRaw('s.quantity <= p.min_stock')
            ->count();

        /* 待审核采购订单 */
        $pendingPurchaseCnt = Db::name('purchase_orders')
            ->where('status', 1)
            ->where('deleted_at', null)
            ->count();

        /* 待审核销售订单 */
        $pendingSaleCnt = Db::name('sale_orders')
            ->where('status', 1)
            ->where('deleted_at', null)
            ->count();

        /* 总客户数 */
        $customerCnt = Db::name('customers')
            ->where('status', 1)
            ->where('deleted_at', null)
            ->count();

        /* 总供应商数 */
        $supplierCnt = Db::name('suppliers')
            ->where('status', 1)
            ->where('deleted_at', null)
            ->count();

        /* 近7天销售趋势 */
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $amt  = Db::name('sale_orders')
                ->where('status', 4)
                ->whereRaw("DATE(created_at)=?", [$date])
                ->sum('final_amount');
            $trend[] = ['date' => $date, 'amount' => $amt ?: 0];
        }

        return $this->success([
            'today_sales'          => $todaySales ?: 0,
            'month_sales'          => $monthSales ?: 0,
            'today_purchase'       => $todayPurchase ?: 0,
            'warning_stock_count'  => $warningStockCnt,
            'pending_purchase_orders' => $pendingPurchaseCnt,
            'pending_sale_orders'  => $pendingSaleCnt,
            'total_customers'      => $customerCnt,
            'total_suppliers'      => $supplierCnt,
            'sales_trend'          => $trend,
        ]);
    }

    /* 统计信息 */
    public function statistics()
    {
        /* 商品统计 */
        $onSale  = Db::name('products')->where('status', 1)->where('deleted_at', null)->count();
        $offSale = Db::name('products')->where('status', 0)->where('deleted_at', null)->count();

        /* 库存统计 */
        $stock = Db::name('stocks')
            ->alias('s')
            ->join('products p', 's.product_id = p.id')
            ->where('p.deleted_at', null)
            ->where('s.deleted_at', null)
            ->fieldRaw('sum(s.quantity) total_quantity, sum(s.total_amount) total_amount')
            ->find();

        /* 账款统计 */
        $receivable = Db::name('account_records')
            ->where('type', 1)
            ->where('status', 1)
            ->where('deleted_at', null)
            ->sum('balance_amount');

        $payable = Db::name('account_records')
            ->where('type', 2)
            ->where('status', 1)
            ->where('deleted_at', null)
            ->sum('balance_amount');

        return $this->success([
            'products' => [
                'total'   => $onSale + $offSale,
                'on_sale' => $onSale,
                'off_sale' => $offSale,
            ],
            'stocks'   => [
                'total_quantity' => $stock['total_quantity'] ?: 0,
                'total_amount'   => $stock['total_amount'] ?: 0,
            ],
            'finance'  => [
                'receivable_amount' => $receivable ?: 0,
                'payable_amount'    => $payable ?: 0,
            ],
        ]);
    }

    /* 预警信息 */
    public function alerts()
    {
        $alerts = [];

        /* 低库存 */
        $lowList = Db::name('stocks')
            ->alias('s')
            ->join('products p', 's.product_id = p.id')
            ->join('warehouses w', 's.warehouse_id = w.id')
            ->whereRaw('s.quantity <= p.min_stock')
            ->where('p.deleted_at', null)
            ->field('p.name product_name, w.name warehouse_name, s.quantity, p.min_stock')
            ->select();
        foreach ($lowList as $v) {
            $alerts[] = [
                'type'    => 'low_stock',
                'title'   => '低库存预警',
                'message' => "商品 {$v['product_name']} 在 {$v['warehouse_name']} 库存过低 ({$v['quantity']}/{$v['min_stock']})",
                'level'   => 'warning',
                'time'    => date('Y-m-d H:i:s'),
            ];
        }

        /* 超期未审核订单（24h） */
        $overdueHours = 24;
        $overduePurchase = Db::name('purchase_orders')
            ->where('status', 1)
            ->where('created_at', '<', date('Y-m-d H:i:s', strtotime("-$overdueHours hours")))
            ->where('deleted_at', null)
            ->count();

        $overdueSale = Db::name('sale_orders')
            ->where('status', 1)
            ->where('created_at', '<', date('Y-m-d H:i:s', strtotime("-$overdueHours hours")))
            ->where('deleted_at', null)
            ->count();

        if ($overduePurchase > 0) {
            $alerts[] = [
                'type'    => 'overdue_purchase',
                'title'   => '超期采购订单',
                'message' => "有 {$overduePurchase} 个采购订单超过{$overdueHours}小时未审核",
                'level'   => 'danger',
                'time'    => date('Y-m-d H:i:s'),
            ];
        }

        if ($overdueSale > 0) {
            $alerts[] = [
                'type'    => 'overdue_sale',
                'title'   => '超期销售订单',
                'message' => "有 {$overdueSale} 个销售订单超过{$overdueHours}小时未审核",
                'level'   => 'danger',
                'time'    => date('Y-m-d H:i:s'),
            ];
        }

        return $this->success([
            'alerts' => $alerts,
            'total'  => count($alerts),
        ]);
    }

    /* 快捷操作 */
    public function quickActions()
    {
        $actions = [
            ['name' => '新增商品', 'icon' => 'add-o', 'path' => '/product/form', 'color' => '#1989fa'],
            ['name' => '采购入库', 'icon' => 'cart-o', 'path' => '/purchase/stock/form', 'color' => '#07c160'],
            ['name' => '销售出库', 'icon' => 'balance-o', 'path' => '/sale/stock/form', 'color' => '#ff976a'],
            ['name' => '库存盘点', 'icon' => 'notes-o', 'path' => '/stock/take/form', 'color' => '#ee0a24'],
            ['name' => '新增客户', 'icon' => 'friends-o', 'path' => '/customer/form', 'color' => '#1989fa'],
            ['name' => '财务记账', 'icon' => 'balance-list-o', 'path' => '/financial/record/form', 'color' => '#07c160'],
        ];

        return $this->success($actions);
    }
}
