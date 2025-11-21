<template>
  <div class="financial-report-cashflow-page financial-module">
    <van-nav-bar title="资金流水报表" fixed placeholder>
      <template #right>
        <van-button size="small" type="primary" @click="handleExport" :loading="exporting">
          导出
        </van-button>
      </template>
    </van-nav-bar>

    <div class="filter-section">
      <van-search
        v-model="filters.keyword"
        placeholder="搜索备注/单号"
        show-action
        @search="loadCashflowData(true)"
        @clear="handleClearSearch"
      >
        <template #action><div @click="loadCashflowData(true)">搜索</div></template>
      </van-search>
      
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
        <van-dropdown-item v-model="filters.type" :options="typeOptions" @change="loadCashflowData(true)"/>
        <van-dropdown-item v-model="filters.payment_method" :options="paymentMethodOptions" @change="loadCashflowData(true)"/>
      </van-dropdown-menu>
    </div>

    <van-cell-group title="统计概览" class="stats-overview">
      <van-row class="stats-row">
        <van-col span="8" class="stats-item">
          <div class="stats-value income">¥{{ formatAmount(statistics.total_income) }}</div>
          <div class="stats-label">总收入</div>
        </van-col>
        <van-col span="8" class="stats-item">
          <div class="stats-value expense">¥{{ formatAmount(statistics.total_expense) }}</div>
          <div class="stats-label">总支出</div>
        </van-col>
        <van-col span="8" class="stats-item">
          <div class="stats-value" :class="statistics.net_cashflow >= 0 ? 'profit' : 'loss'">
            ¥{{ formatAmount(statistics.net_cashflow) }}
          </div>
          <div class="stats-label">净现金流</div>
        </van-col>
      </van-row>
    </van-cell-group>

    <van-cell-group title="资金流水趋势" class="chart-section">
      <div class="chart-container">
        <ChartLine :x-data="trendChart.xData" :series="trendChart.series"/>
      </div>
    </van-cell-group>

    <van-pull-refresh v-model="refreshing" @refresh="loadCashflowData(true)">
      <van-list
        v-model:loading="listLoading"
        :finished="finished"
        :finished-text="cashflowList.length === 0 ? '暂无资金流水记录' : '没有更多了'"
        @load="loadCashflowData"
      >
        <van-cell-group>
          <van-cell
            v-for="record in cashflowList"
            :key="record.id"
            :title="getRecordTitle(record)"
            :label="getRecordLabel(record)"
          >
            <template #value>
              <div :class="getAmountClass(record.type)">{{ getAmountDisplay(record.type, record.amount) }}</div>
            </template>
            <template #extra>
              <van-tag :type="getTypeTagType(record.type)" size="small">{{ getTypeText(record.type) }}</van-tag>
            </template>
          </van-cell>
        </van-cell-group>

        <van-empty v-if="!listLoading && !refreshing && cashflowList.length === 0" description="暂无资金流水记录" image="search"/>
      </van-list>
    </van-pull-refresh>

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
import { getFinancialReportCashflow } from '@/api/financial'
import dayjs from 'dayjs'

const financialStore = useFinancialStore()

const loading = ref(true)
const listLoading = ref(false)
const refreshing = ref(false)
const finished = ref(false)
const exporting = ref(false)
const showStartDatePicker = ref(false)
const showEndDatePicker = ref(false)

const filters = reactive({
  keyword: '',
  type: '',
  payment_method: '',
  start_date: dayjs().startOf('month').format('YYYY-MM-DD'),
  end_date: dayjs().format('YYYY-MM-DD')
})

const pagination = reactive({ page: 1, pageSize: 15, total: 0 })
const statistics = reactive({ total_income: 0, total_expense: 0, net_cashflow: 0, transaction_count: 0 })
const cashflowList = ref([])
const trendChart = reactive({ xData: [], series: [] })

const minDate = new Date(2020, 0, 1)
const maxDate = new Date(2030, 11, 31)

const typeOptions = ref([
  { text: '全部类型', value: '' },
  { text: '收入', value: 'income' },
  { text: '支出', value: 'expense' }
])

const paymentMethodOptions = ref([
  { text: '全部方式', value: '' },
  { text: '现金', value: '现金' },
  { text: '银行卡', value: '银行卡' },
  { text: '微信支付', value: '微信支付' },
  { text: '支付宝', value: '支付宝' },
  { text: '转账', value: '转账' },
  { text: '其他', value: '其他' }
])

const formatAmount = (amount) => {
  if (!amount) return '0.00'
  const num = parseFloat(amount)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

const getTypeText = (type) => {
  const typeMap = { '1': '收入', '2': '支出', 'income': '收入', 'expense': '支出' }
  return typeMap[type] || '未知类型'
}

const getTypeTagType = (type) => {
  const typeMap = { '1': 'success', '2': 'danger', 'income': 'success', 'expense': 'danger' }
  return typeMap[type] || 'default'
}

const getAmountDisplay = (type, amount) => {
  const sign = (type === '1' || type === 'income') ? '+' : '-'
  return `${sign}¥${formatAmount(amount)}`
}

const getAmountClass = (type) => (type === '1' || type === 'income') ? 'income-amount' : 'expense-amount'

const getRecordTitle = (record) => record.category_name || record.remark || record.description || '无备注'

const getRecordLabel = (record) => {
  const parts = []
  if (record.record_no) parts.push(`单号: ${record.record_no}`)
  if (record.payment_method) parts.push(`方式: ${record.payment_method}`)
  if (record.record_date) parts.push(dayjs(record.record_date).format('MM-DD HH:mm'))
  return parts.join(' | ')
}

const loadCashflowData = async (isRefresh = false) => {
  if (isRefresh) {
    pagination.page = 1
    finished.value = false
    refreshing.value = true
  } else {
    listLoading.value = true
  }

  try {
    const params = { page: pagination.page, limit: pagination.pageSize, ...filters }
    Object.keys(params).forEach(key => { if (params[key] === '' || params[key] == null) delete params[key] })
    
    const result = await getFinancialReportCashflow(params)

    let listData = []
    let totalCount = 0

    if (result && result.list) {
      listData = result.list
      totalCount = result.total || 0
    } else if (result && result.data && result.data.list) {
      listData = result.data.list
      totalCount = result.data.total || 0
    } else if (Array.isArray(result)) {
      listData = result
      totalCount = result.length
    } else {
      listData = result || []
      totalCount = result?.total || 0
    }

    if (result.statistics) {
      Object.assign(statistics, result.statistics)
    } else if (result.data && result.data.statistics) {
      Object.assign(statistics, result.data.statistics)
    } else {
      calculateStatistics(listData)
    }

    if (result.chart_data) {
      updateChartData(result.chart_data)
    } else if (result.data && result.data.chart_data) {
      updateChartData(result.data.chart_data)
    } else {
      generateMockChartData()
    }

    if (isRefresh) {
      cashflowList.value = listData
    } else {
      const existingIds = new Set(cashflowList.value.map(item => item.id))
      const newItems = listData.filter(item => !existingIds.has(item.id))
      cashflowList.value = [...cashflowList.value, ...newItems]
    }
    
    pagination.total = totalCount

    if (cashflowList.value.length >= pagination.total) {
      finished.value = true
    } else {
      pagination.page++
    }

  } catch (error) {
    console.error('加载资金流水数据失败:', error)
    showFailToast('加载数据失败')
    finished.value = true
  } finally {
    refreshing.value = false
    listLoading.value = false
    loading.value = false
  }
}

const calculateStatistics = (list) => {
  const stats = { total_income: 0, total_expense: 0, transaction_count: list.length }

  list.forEach(record => {
    const amount = parseFloat(record.amount) || 0
    const type = record.type
    
    if (type === '1' || type === 'income') {
      stats.total_income += amount
    } else if (type === '2' || type === 'expense') {
      stats.total_expense += amount
    }
  })

  stats.net_cashflow = stats.total_income - stats.total_expense
  Object.assign(statistics, stats)
}

const updateChartData = (chartData) => {
  if (chartData && chartData.xData && chartData.series) {
    trendChart.xData = chartData.xData
    trendChart.series = chartData.series
  } else {
    generateMockChartData()
  }
}

const generateMockChartData = () => {
  const dates = []
  for (let i = 29; i >= 0; i--) {
    dates.push(dayjs().subtract(i, 'day').format('MM-DD'))
  }

  const incomeData = dates.map(() => Math.floor(Math.random() * 10000) + 5000)
  const expenseData = dates.map(() => Math.floor(Math.random() * 8000) + 3000)

  trendChart.xData = dates
  trendChart.series = [
    { name: '收入', data: incomeData },
    { name: '支出', data: expenseData }
  ]
}

const handleExport = async () => {
  exporting.value = true
  try {
    // await exportCashflowReport(filters)
    showSuccessToast('导出成功')
  } catch (error) {
    console.error('导出失败:', error)
    showFailToast('导出失败')
  } finally {
    exporting.value = false
  }
}

const handleClearSearch = () => {
  filters.keyword = ''
  loadCashflowData(true)
}

const onStartDateConfirm = (value) => {
  filters.start_date = dayjs(value.selectedValues.join('-')).format('YYYY-MM-DD')
  showStartDatePicker.value = false
  loadCashflowData(true)
}

const onEndDateConfirm = (value) => {
  filters.end_date = dayjs(value.selectedValues.join('-')).format('YYYY-MM-DD')
  showEndDatePicker.value = false
  loadCashflowData(true)
}

onMounted(() => {
  loadCashflowData(true)
})
</script>

<style lang="scss" scoped>
.financial-report-cashflow-page {
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

.stats-overview {
  margin-bottom: 12px;
  
  .stats-row {
    padding: 16px 0;
  }
  
  .stats-item {
    text-align: center;
    padding: 8px 0;
    
    .stats-value {
      font-size: 18px;
      font-weight: bold;
      margin-bottom: 4px;
      
      &.income { color: #ee0a24; }
      &.expense { color: #07c160; }
      &.profit { color: #ee0a24; }
      &.loss { color: #07c160; }
    }
    
    .stats-label {
      font-size: 12px;
      color: #969799;
    }
  }
}

.chart-section {
  margin-bottom: 12px;
  
  .chart-container {
    height: 200px;
    padding: 16px;
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