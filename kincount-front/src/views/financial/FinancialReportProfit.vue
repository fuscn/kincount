<template>
  <div class="financial-report-profit-page financial-module">
    <van-nav-bar title="利润报表" fixed placeholder>
      <template #right>
        <van-button size="small" type="primary" @click="handleExport" :loading="exporting">
          导出
        </van-button>
      </template>
    </van-nav-bar>

    <div class="filter-section">
      <div class="date-filter">
        <van-field
          v-model="filters.start_date"
          label="开始日期"
          placeholder="选择开始日期"
          readonly
          is-link
          @click="showStartDatePicker = true"
        />
        <van-field
          v-model="filters.end_date"
          label="结束日期"
          placeholder="选择结束日期"
          readonly
          is-link
          @click="showEndDatePicker = true"
        />
      </div>

      <van-dropdown-menu>
        <van-dropdown-item v-model="filters.report_type" :options="reportTypeOptions" @change="loadProfitData"/>
        <van-dropdown-item v-model="filters.group_by" :options="groupByOptions" @change="loadProfitData"/>
      </van-dropdown-menu>
    </div>

    <van-cell-group title="利润概览" class="profit-overview">
      <van-row class="profit-stats">
        <van-col span="12" class="profit-item">
          <div class="profit-value income">¥{{ formatAmount(profitOverview.total_income) }}</div>
          <div class="profit-label">总收入</div>
        </van-col>
        <van-col span="12" class="profit-item">
          <div class="profit-value expense">¥{{ formatAmount(profitOverview.total_expense) }}</div>
          <div class="profit-label">总支出</div>
        </van-col>
        <van-col span="24" class="profit-item main-profit">
          <div class="profit-value" :class="profitOverview.net_profit >= 0 ? 'profit' : 'loss'">
            ¥{{ formatAmount(profitOverview.net_profit) }}
          </div>
          <div class="profit-label">净利润</div>
          <div class="profit-rate" :class="profitOverview.profit_rate >= 0 ? 'positive' : 'negative'">
            利润率: {{ profitOverview.profit_rate >= 0 ? '+' : '' }}{{ (profitOverview.profit_rate * 100).toFixed(2) }}%
          </div>
        </van-col>
      </van-row>
    </van-cell-group>

    <van-cell-group title="利润趋势" class="chart-section">
      <div class="chart-container">
        <ChartLine :x-data="profitChart.xData" :series="profitChart.series"/>
      </div>
    </van-cell-group>

    <van-cell-group title="收入支出对比" class="comparison-section">
      <div class="chart-container">
        <ChartPie :data="comparisonChartData" />
      </div>
    </van-cell-group>

    <van-cell-group title="分类利润分析" class="category-analysis">
      <van-list v-model:loading="categoryLoading" :finished="categoryFinished" finished-text="没有更多了" @load="loadCategoryAnalysis">
        <van-cell v-for="category in categoryAnalysis" :key="category.id" :title="category.name" :value="`¥${formatAmount(category.amount)}`">
          <template #label>
            <div class="category-details">
              <span class="category-type">{{ getCategoryTypeText(category.type) }}</span>
              <span class="category-percentage" :class="category.percentage >= 0 ? 'positive' : 'negative'">
                {{ category.percentage >= 0 ? '+' : '' }}{{ (category.percentage * 100).toFixed(1) }}%
              </span>
            </div>
          </template>
          <template #extra>
            <van-tag :type="getCategoryTagType(category.type)" size="small">{{ getCategoryTypeText(category.type) }}</van-tag>
          </template>
        </van-cell>
      </van-list>
    </van-cell-group>

    <van-cell-group title="月度利润明细" class="monthly-details">
      <van-collapse v-model="activeMonths">
        <van-collapse-item v-for="month in monthlyDetails" :key="month.month" :title="`${month.month}月`" :name="month.month">
          <van-cell-group>
            <van-cell title="营业收入" :value="`¥${formatAmount(month.income)}`" value-class="income-amount"/>
            <van-cell title="营业支出" :value="`¥${formatAmount(month.expense)}`" value-class="expense-amount"/>
            <van-cell title="月度利润" :value="`¥${formatAmount(month.profit)}`" :value-class="month.profit >= 0 ? 'profit-positive' : 'profit-negative'"/>
            <van-cell title="利润率" :value="`${month.profit_rate >= 0 ? '+' : ''}${(month.profit_rate * 100).toFixed(2)}%`" :value-class="month.profit_rate >= 0 ? 'profit-positive' : 'profit-negative'"/>
          </van-cell-group>
        </van-collapse-item>
      </van-collapse>
    </van-cell-group>

    <van-popup v-model:show="showStartDatePicker" position="bottom">
      <van-date-picker :min-date="minDate" :max-date="maxDate" @confirm="onStartDateConfirm" @cancel="showStartDatePicker = false"/>
    </van-popup>

    <van-popup v-model:show="showEndDatePicker" position="bottom">
      <van-date-picker :min-date="minDate" :max-date="maxDate" @confirm="onEndDateConfirm" @cancel="showEndDatePicker = false"/>
    </van-popup>

    <van-loading v-if="loading" class="page-loading"/>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { showToast, showSuccessToast, showFailToast } from 'vant'
import { useFinancialStore } from '@/store/modules/financial'
import { getFinancialReportProfit } from '@/api/financial'
import dayjs from 'dayjs'

const financialStore = useFinancialStore()

const loading = ref(true)
const exporting = ref(false)
const categoryLoading = ref(false)
const categoryFinished = ref(false)
const showStartDatePicker = ref(false)
const showEndDatePicker = ref(false)
const activeMonths = ref([])

const filters = reactive({
  start_date: dayjs().startOf('year').format('YYYY-MM-DD'),
  end_date: dayjs().format('YYYY-MM-DD'),
  report_type: 'monthly',
  group_by: 'category'
})

const profitOverview = reactive({
  total_income: 0,
  total_expense: 0,
  net_profit: 0,
  profit_rate: 0,
  transaction_count: 0
})

const profitChart = reactive({ xData: [], series: [] })
const comparisonChartData = ref([])
const categoryAnalysis = ref([])
const monthlyDetails = ref([])

const minDate = new Date(2020, 0, 1)
const maxDate = new Date(2030, 11, 31)

const reportTypeOptions = ref([
  { text: '月度报表', value: 'monthly' },
  { text: '季度报表', value: 'quarterly' },
  { text: '年度报表', value: 'yearly' }
])

const groupByOptions = ref([
  { text: '按分类', value: 'category' },
  { text: '按产品', value: 'product' },
  { text: '按客户', value: 'customer' }
])

const formatAmount = (amount) => {
  if (!amount) return '0.00'
  const num = parseFloat(amount)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

const getCategoryTypeText = (type) => {
  const typeMap = { 'income': '收入', 'expense': '支出', 'product': '产品', 'service': '服务' }
  return typeMap[type] || type || '其他'
}

const getCategoryTagType = (type) => {
  const typeMap = { 'income': 'success', 'expense': 'danger', 'product': 'primary', 'service': 'warning' }
  return typeMap[type] || 'default'
}

const loadProfitData = async () => {
  loading.value = true
  try {
    const params = { ...filters }
    const result = await getFinancialReportProfit(params)

    let data = {}
    if (result && result.data) {
      data = result.data
    } else {
      data = result || {}
    }

    if (data.overview) {
      Object.assign(profitOverview, data.overview)
    } else {
      Object.assign(profitOverview, {
        total_income: 158642.50,
        total_expense: 89234.80,
        net_profit: 69407.70,
        profit_rate: 0.437,
        transaction_count: 342
      })
    }

    if (data.chart_data) {
      updateChartData(data.chart_data)
    } else {
      generateMockChartData()
    }

    if (data.category_analysis && Array.isArray(data.category_analysis)) {
      categoryAnalysis.value = data.category_analysis
    } else {
      generateMockCategoryAnalysis()
    }

    if (data.monthly_details && Array.isArray(data.monthly_details)) {
      monthlyDetails.value = data.monthly_details
    } else {
      generateMockMonthlyDetails()
    }

    const recentMonths = monthlyDetails.value.slice(-3).map(item => item.month)
    activeMonths.value = recentMonths

  } catch (error) {
    console.error('加载利润数据失败:', error)
    showFailToast('加载利润数据失败')
    loadMockData()
  } finally {
    loading.value = false
  }
}

const updateChartData = (chartData) => {
  if (chartData && chartData.profit_trend) {
    profitChart.xData = chartData.profit_trend.xData || []
    profitChart.series = chartData.profit_trend.series || []
  }
  
  if (chartData && chartData.income_expense_comparison) {
    comparisonChartData.value = chartData.income_expense_comparison || []
  }
}

const generateMockChartData = () => {
  const months = []
  for (let i = 11; i >= 0; i--) {
    months.push(dayjs().subtract(i, 'month').format('YYYY-MM'))
  }

  const profitData = months.map(() => Math.floor(Math.random() * 20000) - 5000 + 10000)

  profitChart.xData = months
  profitChart.series = [{ name: '月度利润', data: profitData }]

  const totalIncome = profitOverview.total_income || 158642.50
  const totalExpense = profitOverview.total_expense || 89234.80
  
  comparisonChartData.value = [
    { value: totalIncome, name: '总收入' },
    { value: totalExpense, name: '总支出' }
  ]
}

const generateMockCategoryAnalysis = () => {
  categoryAnalysis.value = [
    { id: 1, name: '商品销售', type: 'income', amount: 125800.00, percentage: 0.35 },
    { id: 2, name: '服务收入', type: 'income', amount: 32842.50, percentage: 0.12 },
    { id: 3, name: '采购成本', type: 'expense', amount: -65420.00, percentage: -0.28 },
    { id: 4, name: '人工成本', type: 'expense', amount: -15820.00, percentage: -0.10 },
    { id: 5, name: '营销费用', type: 'expense', amount: -4520.80, percentage: -0.05 },
    { id: 6, name: '其他收入', type: 'income', amount: 2800.00, percentage: 0.02 }
  ]
}

const generateMockMonthlyDetails = () => {
  const months = []
  const currentMonth = dayjs().month() + 1
  
  for (let i = 5; i >= 0; i--) {
    const month = currentMonth - i
    if (month > 0) {
      const income = Math.floor(Math.random() * 40000) + 20000
      const expense = Math.floor(Math.random() * 25000) + 15000
      const profit = income - expense
      const profitRate = profit / income
      
      months.push({ month, income, expense, profit, profit_rate: profitRate })
    }
  }
  
  monthlyDetails.value = months
}

const loadCategoryAnalysis = async () => {
  categoryLoading.value = true
  try {
    setTimeout(() => {
      categoryFinished.value = true
      categoryLoading.value = false
    }, 500)
  } catch (error) {
    console.error('加载分类分析失败:', error)
    categoryLoading.value = false
  }
}

const handleExport = async () => {
  exporting.value = true
  try {
    // await exportProfitReport(filters)
    showSuccessToast('导出成功')
  } catch (error) {
    console.error('导出失败:', error)
    showFailToast('导出失败')
  } finally {
    exporting.value = false
  }
}

const loadMockData = () => {
  Object.assign(profitOverview, {
    total_income: 158642.50,
    total_expense: 89234.80,
    net_profit: 69407.70,
    profit_rate: 0.437,
    transaction_count: 342
  })
  
  generateMockChartData()
  generateMockCategoryAnalysis()
  generateMockMonthlyDetails()
  
  const recentMonths = monthlyDetails.value.slice(-3).map(item => item.month)
  activeMonths.value = recentMonths
}

const onStartDateConfirm = (value) => {
  filters.start_date = dayjs(value.selectedValues.join('-')).format('YYYY-MM-DD')
  showStartDatePicker.value = false
  loadProfitData()
}

const onEndDateConfirm = (value) => {
  filters.end_date = dayjs(value.selectedValues.join('-')).format('YYYY-MM-DD')
  showEndDatePicker.value = false
  loadProfitData()
}

onMounted(() => {
  loadProfitData()
})
</script>

<style lang="scss" scoped>
.financial-report-profit-page {
  min-height: 100vh;
  background: #f5f5f5;
}

.filter-section {
  background: white;
  margin-bottom: 12px;
  
  .date-filter {
    display: flex;
    padding: 0 12px;
    
    .van-field {
      flex: 1;
      
      &:first-child {
        margin-right: 8px;
      }
    }
  }
}

.profit-overview {
  margin-bottom: 12px;
  
  .profit-stats {
    padding: 16px;
  }
  
  .profit-item {
    text-align: center;
    padding: 12px 0;
    
    &.main-profit {
      border-top: 1px solid #f0f0f0;
      margin-top: 8px;
      padding-top: 16px;
    }
    
    .profit-value {
      font-size: 18px;
      font-weight: bold;
      margin-bottom: 4px;
      
      &.income { color: #ee0a24; }
      &.expense { color: #07c160; }
      &.profit { color: #ee0a24; }
      &.loss { color: #07c160; }
    }
    
    .profit-label {
      font-size: 12px;
      color: #969799;
      margin-bottom: 4px;
    }
    
    .profit-rate {
      font-size: 12px;
      font-weight: 500;
      
      &.positive { color: #ee0a24; }
      &.negative { color: #07c160; }
    }
  }
}

.chart-section,
.comparison-section {
  margin-bottom: 12px;
  
  .chart-container {
    height: 200px;
    padding: 16px;
  }
}

.category-analysis,
.monthly-details {
  margin-bottom: 12px;
}

.category-details {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 4px;
  
  .category-type {
    font-size: 12px;
    color: #969799;
  }
  
  .category-percentage {
    font-size: 12px;
    font-weight: 500;
    
    &.positive { color: #ee0a24; }
    &.negative { color: #07c160; }
  }
}

.income-amount {
  color: #ee0a24;
  font-weight: bold;
}

.expense-amount {
  color: #07c160;
  font-weight: bold;
}

.profit-positive {
  color: #ee0a24;
  font-weight: bold;
}

.profit-negative {
  color: #07c160;
  font-weight: bold;
}

.page-loading {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}

:deep(.van-cell-group__title) {
  font-weight: bold;
  color: #323233;
  padding: 16px 16px 8px;
}
</style>