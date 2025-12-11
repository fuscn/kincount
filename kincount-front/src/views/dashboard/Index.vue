<template>
  <div class="dashboard">
    <van-nav-bar title="仪表盘" fixed placeholder />

    <!-- 数据概览 -->
    <div class="overview-cards">
      <van-row gutter="12">
        <van-col span="8">
          <div class="stat-card">
            <div class="stat-value">¥{{ overviewData.today_sales || 0 }}</div>
            <div class="stat-label">今日销售额</div>
          </div>
        </van-col>
        <van-col span="8">
          <div class="stat-card">
            <div class="stat-value">¥{{ overviewData.month_sales || 0 }}</div>
            <div class="stat-label">本月销售额</div>
          </div>
        </van-col>
        <van-col span="8">
          <div class="stat-card warning">
            <div class="stat-value">{{ overviewData.warning_stock_count || 0 }}</div>
            <div class="stat-label">预警库存</div>
          </div>
        </van-col>
      </van-row>
    </div>

    <!-- 销售趋势图 -->
    <van-cell-group title="销售趋势" class="chart-section">
      <ChartLine :x-data="chartData.xData" :series="chartData.series" />
      <div v-if="isZeroSalesData" class="zero-data-tip">
        <van-empty image="search" description="暂无销售数据" />
      </div>
    </van-cell-group>


    <!-- 库存预警 -->
    <StockWarning />

    <!-- 热销商品部分 -->
    <div class="hot-skus-section">
      <van-cell-group title="热销 SKU">
        <template v-if="hotSkus.length > 0">
          <SkuStockList :warehouse-id="0" :keyword="hotKeyword" warning-type="" @click-card="handleViewSku" />
        </template>
        <template v-else-if="!loading">
          <van-empty description="暂无热销商品数据" />
        </template>
      </van-cell-group>
    </div>

    <!-- 加载状态 -->
    <van-loading v-if="loading" class="loading" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  NavBar, Col, Row, CellGroup, Grid, GridItem, Loading, Empty
} from 'vant'
import { getOverview } from '@/api/dashboard'
import { getSkuList } from '@/api/product'
import StockWarning from '@/components/business/StockWarning.vue'
import SkuStockList from '@/components/business/SkuStockList.vue'
import ChartLine from '@/components/business/ChartLine.vue'

const router = useRouter()
const hotKeyword = ref('')
const hotSkus = ref([])
const overviewData = ref({})
const salesTrend = ref([])
const loading = ref(true)

// 计算是否有销售数据
const hasSalesData = computed(() => {
  return salesTrend.value && salesTrend.value.length > 0
})

// 计算是否为零销售数据
const isZeroSalesData = computed(() => {
  return hasSalesData.value && salesTrend.value.every(item => item.amount === 0)
})

// 计算图表数据
const chartData = computed(() => {
  if (!hasSalesData.value) {
    return {
      xData: [],
      series: [{ name: '销售额', data: [] }]
    }
  }

  const dates = salesTrend.value.map(item => {
    try {
      const date = new Date(item.date)
      return `${(date.getMonth() + 1).toString().padStart(2, '0')}-${date.getDate().toString().padStart(2, '0')}`
    } catch (error) {
      return item.date
    }
  })

  const amounts = salesTrend.value.map(item => item.amount)

  return {
    xData: dates,
    series: [{
      name: '销售额',
      data: amounts,
      type: 'line'
    }]
  }
})

// 修复：点击卡片跳转
function handleViewSku(item) {

  // // 获取商品ID - 从多个可能的路径获取
  // const productId = item.product_id || item.sku?.product_id || item.id

  // if (!productId) {
  //   console.error('无法获取商品ID:', item)
  //   return
  // }

  // // 获取SKU ID
  // const skuId = item.sku_id || item.id

  // // 根据你的路由配置选择合适的跳转方式
  // // 方式1：使用命名路由
  // try {
  //   router.push({
  //     name: 'ProductSkus',
  //     params: { id: productId },
  //     query: { skuId: skuId }
  //   })
  // } catch (error) {
  //   console.error('路由跳转失败:', error)
  //   // 方式2：使用路径路由（备用方案）
  //   router.push(`/product/skus/${productId}?skuId=${skuId}`)
  // }
}

// 加载热销 SKU
const loadHotSkus = async () => {
  try {
    const res = await getSkuList({
      page: 1,
      limit: 5,
      sort: 'sale_num',
      order: 'desc'
    })

    // 处理不同的响应结构
    if (res && res.code === 200) {
      hotSkus.value = res.data?.list || res.data || []
    } else {
      hotSkus.value = res?.list || res || []
    }

  } catch (error) {
    hotSkus.value = []
  }
}

// 加载仪表盘数据
const loadDashboardData = async () => {
  try {
    loading.value = true
    await loadOverviewData()
  } catch (error) {
  } finally {
    loading.value = false
  }
}

// 加载概览数据
const loadOverviewData = async () => {
  try {
    const response = await getOverview()
    let data = {}

    if (response && response.code === 200) {
      data = response.data || {}
    } else {
      data = response || {}
    }

    overviewData.value = data
    salesTrend.value = data.sales_trend || []
  } catch (error) {
    overviewData.value = {}
    salesTrend.value = []
  }
}

onMounted(() => {
  loadDashboardData()
  loadHotSkus()
})
</script>

<style scoped lang="scss">
.dashboard {
  padding: 16px;
  background: #f7f8fa;
  min-height: 100vh;
}

.overview-cards {
  margin-bottom: 16px;
}

.stat-card {
  background: white;
  border-radius: 8px;
  padding: 16px;
  text-align: center;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);

  &.warning {
    background: #fff2f0;

    .stat-value {
      color: #ff4d4f;
    }
  }

  .stat-value {
    font-size: 18px;
    font-weight: bold;
    color: #1989fa;
    margin-bottom: 4px;
  }

  .stat-label {
    font-size: 12px;
    color: #969799;
  }
}

.quick-actions {
  margin-bottom: 16px;
}

.chart-section,
.hot-skus-section {
  margin-bottom: 16px;
  position: relative;
}

.zero-data-tip {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 10;
  background: rgba(255, 255, 255, 0.9);
  padding: 20px;
  border-radius: 8px;
}

.loading {
  display: flex;
  justify-content: center;
  padding: 20px;
}
</style>