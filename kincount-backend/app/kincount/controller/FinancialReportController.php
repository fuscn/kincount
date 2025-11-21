<?php
declare (strict_types = 1);

namespace app\kincount\controller;

use think\facade\Db;

class FinancialReportController extends BaseController
{
    /* 利润报表 */
    public function profit()
    {
        $sDate = input('start_date', date('Y-m-01'));
        $eDate = input('end_date', date('Y-m-d'));

        /* 销售收入 */
        $saleIncome = Db::name('sale_orders')
                        ->where('status', 4)
                        ->whereBetween('created_at', [$sDate, $eDate . ' 23:59:59'])
                        ->where('deleted_at', null)
                        ->sum('final_amount');

        /* 其他收入 */
        $otherIncome = Db::name('financial_records')
                         ->where('type', 1)
                         ->whereBetween('record_date', [$sDate, $eDate])
                         ->where('deleted_at', null)
                         ->sum('amount');

        /* 采购支出 */
        $purchaseExpense = Db::name('purchase_orders')
                             ->where('status', 4)
                             ->whereBetween('created_at', [$sDate, $eDate . ' 23:59:59'])
                             ->where('deleted_at', null)
                             ->sum('total_amount');

        /* 其他支出 */
        $otherExpense = Db::name('financial_records')
                          ->where('type', 2)
                          ->whereBetween('record_date', [$sDate, $eDate])
                          ->where('deleted_at', null)
                          ->sum('amount');

        $grossProfit = $saleIncome - $purchaseExpense;
        $netProfit   = $grossProfit + $otherIncome - $otherExpense;

        return $this->success([
            'sale_income'      => $saleIncome ?: 0,
            'other_income'     => $otherIncome ?: 0,
            'purchase_expense' => $purchaseExpense ?: 0,
            'other_expense'    => $otherExpense ?: 0,
            'gross_profit'     => $grossProfit,
            'net_profit'       => $netProfit,
        ]);
    }

    /* 资金流水 */
    public function cashflow()
    {
        $sDate = input('start_date', date('Y-m-01'));
        $eDate = input('end_date', date('Y-m-d'));

        $list = Db::name('financial_records')
                  ->whereBetween('record_date', [$sDate, $eDate])
                  ->where('deleted_at', null)
                  ->order('record_date', 'desc')
                  ->select();

        return $this->success($list);
    }

    /* 销售报表 */
    public function sales()
    {
        $sDate = input('start_date', date('Y-m-01'));
        $eDate = input('end_date', date('Y-m-d'));

        /* 汇总 */
        $totalSales = Db::name('sale_orders')
                         ->where('status', 4)
                         ->whereBetween('created_at', [$sDate, $eDate . ' 23:59:59'])
                         ->where('deleted_at', null)
                         ->sum('final_amount');

        $orderCnt = Db::name('sale_orders')
                      ->where('status', 4)
                      ->whereBetween('created_at', [$sDate, $eDate . ' 23:59:59'])
                      ->where('deleted_at', null)
                      ->count();

        $productCnt = Db::name('sale_order_items')
                        ->alias('oi')
                        ->join('sale_orders o', 'oi.sale_order_id = o.id')
                        ->where('o.status', 4)
                        ->whereBetween('o.created_at', [$sDate, $eDate . ' 23:59:59'])
                        ->where('o.deleted_at', null)
                        ->sum('oi.quantity');

        $customerCnt = Db::name('sale_orders')
                         ->where('status', 4)
                         ->whereBetween('created_at', [$sDate, $eDate . ' 23:59:59'])
                         ->where('deleted_at', null)
                         ->distinct(true)
                         ->count('customer_id');

        /* 每日趋势 */
        $daily = [];
        $curr  = $sDate;
        while ($curr <= $eDate) {
            $amt = Db::name('sale_orders')
                     ->where('status', 4)
                     ->whereRaw("DATE(created_at)=?", [$curr])
                     ->where('deleted_at', null)
                     ->sum('final_amount');
            $daily[] = ['date' => $curr, 'amount' => $amt ?: 0];
            $curr = date('Y-m-d', strtotime($curr . ' +1 day'));
        }

        return $this->success([
            'total_sales'   => $totalSales ?: 0,
            'order_count'   => $orderCnt,
            'product_count' => $productCnt ?: 0,
            'customer_count'=> $customerCnt,
            'daily_trend'   => $daily,
        ]);
    }

    /* 采购报表 */
    public function purchase()
    {
        $sDate = input('start_date', date('Y-m-01'));
        $eDate = input('end_date', date('Y-m-d'));

        $totalPurchase = Db::name('purchase_orders')
                             ->where('status', 4)
                             ->whereBetween('created_at', [$sDate, $eDate . ' 23:59:59'])
                             ->where('deleted_at', null)
                             ->sum('total_amount');

        $orderCnt = Db::name('purchase_orders')
                      ->where('status', 4)
                      ->whereBetween('created_at', [$sDate, $eDate . ' 23:59:59'])
                      ->where('deleted_at', null)
                      ->count();

        $productCnt = Db::name('purchase_order_items')
                        ->alias('oi')
                        ->join('purchase_orders o', 'oi.purchase_order_id = o.id')
                        ->where('o.status', 4)
                        ->whereBetween('o.created_at', [$sDate, $eDate . ' 23:59:59'])
                        ->where('o.deleted_at', null)
                        ->sum('oi.quantity');

        $supplierCnt = Db::name('purchase_orders')
                          ->where('status', 4)
                          ->whereBetween('created_at', [$sDate, $eDate . ' 23:59:59'])
                          ->where('deleted_at', null)
                          ->distinct(true)
                          ->count('supplier_id');

        /* 每日趋势 */
        $daily = [];
        $curr  = $sDate;
        while ($curr <= $eDate) {
            $amt = Db::name('purchase_orders')
                     ->where('status', 4)
                     ->whereRaw("DATE(created_at)=?", [$curr])
                     ->where('deleted_at', null)
                     ->sum('total_amount');
            $daily[] = ['date' => $curr, 'amount' => $amt ?: 0];
            $curr = date('Y-m-d', strtotime($curr . ' +1 day'));
        }

        return $this->success([
            'total_purchase' => $totalPurchase ?: 0,
            'order_count'    => $orderCnt,
            'product_count'  => $productCnt ?: 0,
            'supplier_count' => $supplierCnt,
            'daily_trend'    => $daily,
        ]);
    }

    /* 库存报表 */
    public function inventory()
    {
        /* 库存总值 */
        $totalAmt = Db::name('stocks')
                      ->alias('s')
                      ->join('products p', 's.product_id = p.id')
                      ->where('p.deleted_at', null)
                      ->where('s.deleted_at', null)
                      ->sum('s.total_amount');

        /* SKU 数 */
        $skuCnt = Db::name('stocks')
                    ->alias('s')
                    ->join('products p', 's.product_id = p.id')
                    ->where('p.deleted_at', null)
                    ->where('s.deleted_at', null)
                    ->distinct(true)
                    ->count('s.product_id');

        /* 低库存 */
        $lowCnt = Db::name('stocks')
                    ->alias('s')
                    ->join('products p', 's.product_id = p.id')
                    ->whereRaw('s.quantity <= p.min_stock')
                    ->where('p.deleted_at', null)
                    ->where('s.deleted_at', null)
                    ->count();

        /* 高库存 */
        $highCnt = Db::name('stocks')
                     ->alias('s')
                     ->join('products p', 's.product_id = p.id')
                     ->whereRaw('s.quantity >= p.max_stock')
                     ->where('p.deleted_at', null)
                     ->where('s.deleted_at', null)
                     ->count();

        /* 仓库分布 */
        $whStats = Db::name('stocks')
                     ->alias('s')
                     ->join('warehouses w', 's.warehouse_id = w.id')
                     ->where('w.deleted_at', null)
                     ->where('s.deleted_at', null)
                     ->field('w.name, sum(s.total_amount) amount')
                     ->group('s.warehouse_id')
                     ->select();

        /* 周转率（简化） */
        $saleCost = Db::name('sale_order_items')
                      ->alias('oi')
                      ->join('sale_orders o', 'oi.sale_order_id = o.id')
                      ->join('products p', 'oi.product_id = p.id')
                      ->where('o.status', 4)
                      ->whereBetween('o.created_at', [date('Y-m-01'), date('Y-m-d') . ' 23:59:59'])
                      ->where('o.deleted_at', null)
                      ->sum('oi.quantity * p.cost_price');

        $avgInventory = $totalAmt ?: 1;
        $turnoverRate = $saleCost / $avgInventory;

        return $this->success([
            'total_amount'    => $totalAmt ?: 0,
            'product_count'   => $skuCnt,
            'low_stock_count' => $lowCnt,
            'high_stock_count'=> $highCnt,
            'warehouse_stats' => $whStats,
            'turnover_rate'   => round($turnoverRate, 2),
        ]);
    }
}