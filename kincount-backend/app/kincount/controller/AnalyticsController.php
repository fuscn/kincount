<?php
declare (strict_types = 1);

namespace app\kincount\controller;

use think\facade\Db;

class AnalyticsController extends BaseController
{
    /* 销售分析 */
    public function sales()
    {
        $sDate = input('start_date', date('Y-m-01'));
        $eDate = input('end_date', date('Y-m-d'));

        /* 销售趋势 */
        $trend = [];
        $curr  = $sDate;
        while ($curr <= $eDate) {
            $amt = Db::name('sale_orders')
                     ->where('status', 4)
                     ->whereRaw("DATE(created_at)=?", [$curr])
                     ->where('deleted_at', null)
                     ->sum('final_amount');
            $trend[] = ['date' => $curr, 'amount' => $amt ?: 0];
            $curr = date('Y-m-d', strtotime($curr . ' +1 day'));
        }

        /* TOP10 热销商品 */
        $hotProducts = Db::name('sale_order_items')
                          ->alias('oi')
                          ->join('sale_orders o', 'oi.sale_order_id = o.id')
                          ->join('products p', 'oi.product_id = p.id')
                          ->where('o.status', 4)
                          ->whereBetween('o.created_at', [$sDate, $eDate . ' 23:59:59'])
                          ->where('o.deleted_at', null)
                          ->field('p.name, sum(oi.quantity) total_quantity, sum(oi.total_amount) total_amount')
                          ->group('oi.product_id')
                          ->order('total_quantity', 'desc')
                          ->limit(10)
                          ->select();

        /* 客户消费排行 */
        $customerRank = Db::name('sale_orders')
                           ->alias('so')
                           ->join('customers c', 'so.customer_id = c.id')
                           ->where('so.status', 4)
                           ->whereBetween('so.created_at', [$sDate, $eDate . ' 23:59:59'])
                           ->where('so.deleted_at', null)
                           ->field('c.name, count(*) order_count, sum(so.final_amount) total_amount')
                           ->group('so.customer_id')
                           ->order('total_amount', 'desc')
                           ->limit(10)
                           ->select();

        return $this->success([
            'sales_trend'  => $trend,
            'hot_products' => $hotProducts,
            'customer_rank'=> $customerRank,
        ]);
    }

    /* 采购分析 */
    public function purchase()
    {
        $sDate = input('start_date', date('Y-m-01'));
        $eDate = input('end_date', date('Y-m-d'));

        /* 采购趋势 */
        $trend = [];
        $curr  = $sDate;
        while ($curr <= $eDate) {
            $amt = Db::name('purchase_orders')
                     ->where('status', 4)
                     ->whereRaw("DATE(created_at)=?", [$curr])
                     ->where('deleted_at', null)
                     ->sum('total_amount');
            $trend[] = ['date' => $curr, 'amount' => $amt ?: 0];
            $curr = date('Y-m-d', strtotime($curr . ' +1 day'));
        }

        /* TOP10 采购商品 */
        $purchaseProducts = Db::name('purchase_order_items')
                               ->alias('oi')
                               ->join('purchase_orders o', 'oi.purchase_order_id = o.id')
                               ->join('products p', 'oi.product_id = p.id')
                               ->where('o.status', 4)
                               ->whereBetween('o.created_at', [$sDate, $eDate . ' 23:59:59'])
                               ->where('o.deleted_at', null)
                               ->field('p.name, sum(oi.quantity) total_quantity, sum(oi.total_amount) total_amount')
                               ->group('oi.product_id')
                               ->order('total_quantity', 'desc')
                               ->limit(10)
                               ->select();

        /* 供应商采购排行 */
        $supplierRank = Db::name('purchase_orders')
                           ->alias('po')
                           ->join('suppliers s', 'po.supplier_id = s.id')
                           ->where('po.status', 4)
                           ->whereBetween('po.created_at', [$sDate, $eDate . ' 23:59:59'])
                           ->where('po.deleted_at', null)
                           ->field('s.name, count(*) order_count, sum(po.total_amount) total_amount')
                           ->group('po.supplier_id')
                           ->order('total_amount', 'desc')
                           ->limit(10)
                           ->select();

        return $this->success([
            'purchase_trend'   => $trend,
            'purchase_products'=> $purchaseProducts,
            'supplier_rank'    => $supplierRank,
        ]);
    }

    /* 库存分析 */
    public function inventory()
    {
        /* 库存金额分布（按分类） */
        $amountDist = Db::name('stocks')
                         ->alias('s')
                         ->join('products p', 's.product_id = p.id')
                         ->join('categories c', 'p.category_id = c.id')
                         ->where('p.deleted_at', null)
                         ->where('s.deleted_at', null)
                         ->field('c.name category_name, sum(s.total_amount) total_amount')
                         ->group('p.category_id')
                         ->select();

        /* 库存周转率（按分类简化版） */
        $turnover = [];
        $categories = Db::name('categories')->where('deleted_at', null)->select();
        foreach ($categories as $cate) {
            /* 销售成本 = 销售数量 * 成本价 */
            $saleCost = Db::name('sale_order_items')
                           ->alias('oi')
                           ->join('sale_orders o', 'oi.sale_order_id = o.id')
                           ->join('products p', 'oi.product_id = p.id')
                           ->where('p.category_id', $cate['id'])
                           ->where('o.status', 4)
                           ->whereBetween('o.created_at', [date('Y-m-01'), date('Y-m-d') . ' 23:59:59'])
                           ->where('o.deleted_at', null)
                           ->sum('oi.quantity * p.cost_price');

            /* 平均库存金额 */
            $avgInv = Db::name('stocks')
                        ->alias('s')
                        ->join('products p', 's.product_id = p.id')
                        ->where('p.category_id', $cate['id'])
                        ->where('p.deleted_at', null)
                        ->where('s.deleted_at', null)
                        ->avg('s.total_amount');

            $turnoverRate = $avgInv > 0 ? $saleCost / $avgInv : 0;
            $turnover[] = [
                'category_name' => $cate['name'],
                'sale_cost'     => $saleCost ?: 0,
                'avg_inventory' => $avgInv ?: 0,
                'turnover_rate' => round($turnoverRate, 2),
            ];
        }

        return $this->success([
            'amount_distribution' => $amountDist,
            'turnover_analysis'   => $turnover,
        ]);
    }

    /* 财务分析 */
    public function financial()
    {
        $sDate = input('start_date', date('Y-m-01'));
        $eDate = input('end_date', date('Y-m-d'));

        /* 收入构成 */
        $incomeComposition = Db::name('financial_records')
                                ->where('type', 1)
                                ->whereBetween('record_date', [$sDate, $eDate])
                                ->where('deleted_at', null)
                                ->field('category, sum(amount) total_amount')
                                ->group('category')
                                ->select();

        /* 支出构成 */
        $expenseComposition = Db::name('financial_records')
                                 ->where('type', 2)
                                 ->whereBetween('record_date', [$sDate, $eDate])
                                 ->where('deleted_at', null)
                                 ->field('category, sum(amount) total_amount')
                                 ->group('category')
                                 ->select();

        /* 利润率趋势（近6月） */
        $profitTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $in  = Db::name('financial_records')->where('type', 1)->whereRaw("DATE_FORMAT(record_date,'%Y-%m')=?", [$month])->where('deleted_at', null)->sum('amount');
            $out = Db::name('financial_records')->where('type', 2)->whereRaw("DATE_FORMAT(record_date,'%Y-%m')=?", [$month])->where('deleted_at', null)->sum('amount');
            $profit = $in - $out;
            $rate   = $in > 0 ? ($profit / $in) * 100 : 0;
            $profitTrend[] = [
                'month'      => $month,
                'income'     => $in ?: 0,
                'expense'    => $out ?: 0,
                'profit'     => $profit,
                'profit_rate'=> round($rate, 2),
            ];
        }

        return $this->success([
            'income_composition'  => $incomeComposition,
            'expense_composition' => $expenseComposition,
            'profit_trend'        => $profitTrend,
        ]);
    }

    /* 客户分析 */
    public function customer()
    {
        $sDate = input('start_date', date('Y-m-01'));
        $eDate = input('end_date', date('Y-m-d'));

        /* 增长趋势 */
        $growth = [];
        $curr   = $sDate;
        while ($curr <= $eDate) {
            $cnt = Db::name('customers')
                     ->whereRaw("DATE(created_at)=?", [$curr])
                     ->where('deleted_at', null)
                     ->count();
            $growth[] = ['date' => $curr, 'count' => $cnt];
            $curr = date('Y-m-d', strtotime($curr . ' +1 day'));
        }

        /* 等级分布 */
        $levelDist = Db::name('customers')
                        ->where('deleted_at', null)
                        ->field('level, count(*) count')
                        ->group('level')
                        ->select();

        /* 类型分布 */
        $typeDist = Db::name('customers')
                       ->where('deleted_at', null)
                       ->field('type, count(*) count')
                       ->group('type')
                       ->select();

        return $this->success([
            'growth_trend'    => $growth,
            'level_distribution'=> $levelDist,
            'type_distribution' => $typeDist,
        ]);
    }

    /* 供应商分析 */
    public function supplier()
    {
        /* 地区分布 */
        $regionDist = Db::name('suppliers')
                         ->where('deleted_at', null)
                         ->field('region, count(*) count')
                         ->group('region')
                         ->select();

        /* 合作时长 */
        $cooperation = Db::name('suppliers')
                          ->where('deleted_at', null)
                          ->field('name, DATEDIFF(NOW(), created_at) days')
                          ->order('days', 'desc')
                          ->limit(10)
                          ->select();

        /* 采购金额排名 */
        $purchaseRank = Db::name('purchase_orders')
                           ->alias('po')
                           ->join('suppliers s', 'po.supplier_id = s.id')
                           ->where('po.status', 4)
                           ->where('po.deleted_at', null)
                           ->field('s.name, count(*) order_count, sum(po.total_amount) total_amount')
                           ->group('po.supplier_id')
                           ->order('total_amount', 'desc')
                           ->limit(10)
                           ->select();

        return $this->success([
            'region_distribution' => $regionDist,
            'cooperation_period'  => $cooperation,
            'purchase_rank'       => $purchaseRank,
        ]);
    }
}