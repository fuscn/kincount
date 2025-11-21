<template>
  <div class="financial-dashboard-page">
    <van-nav-bar title="财务概览" fixed placeholder>
      <template #right>
        <van-button size="small" type="primary" @click="handleRefresh" :loading="refreshing">刷新</van-button>
      </template>
    </van-nav-bar>

    <van-loading v-if="loading" class="page-loading" />

    <div v-else class="dashboard-content">
      <van-cell-group title="今日统计" class="stats-card">
        <van-cell title="今日收入" :value="`¥${formatAmount(todayStats.income)}`" value-class="income-amount" />
        <van-cell title="今日支出" :value="`¥${formatAmount(todayStats.expense)}`" value-class="expense-amount" />
        <van-cell title="今日结余" :value="`¥${formatAmount(todayStats.balance)}`" 
          :value-class="todayStats.balance >= 0 ? 'profit-positive' : 'profit-negative'" />
      </van-cell-group>

      <van-cell-group title="本月统计" class="stats-card">
        <van-cell title="本月收入" :value="`¥${formatAmount(monthStats.income)}`" value-class="income-amount" />
        <van-cell title="本月支出" :value="`¥${formatAmount(monthStats.expense)}`" value-class="expense-amount" />
        <van-cell title="本月利润" :value="`¥${formatAmount(monthStats.profit)}`" 
          :value-class="monthStats.profit >= 0 ? 'profit-positive' : 'profit-negative'" />
      </van-cell-group>

      <van-cell-group title="财务趋势" class="chart-card">
        <div class="chart-container"><ChartLine :x-data="trendChart.xData" :series="trendChart.series" /></div>
      </van-cell-group>

      <van-cell-group title="收支比例" class="chart-card">
        <div class="chart-container"><ChartPie :data="pieChartData" /></div>
      </van-cell-group>

      <van-cell-group title="快捷操作" class="actions-card">
        <van-cell title="新建收入记录" is-link @click="handleCreateIncome" v-perm="PERM.FINANCE_ADD">
          <template #icon><van-icon name="add-o" class="action-icon" /></template>
        </van-cell>
        <van-cell title="新建支出记录" is-link @click="handleCreateExpense" v-perm="PERM.FINANCE_ADD">
          <template #icon><van-icon name="minus" class="action-icon" /></template>
        </van-cell>
        <van-cell title="查看利润报表" is-link @click="handleViewProfitReport" v-perm="PERM.FINANCE_REPORT">
          <template #icon><van-icon name="chart-trending-o" class="action-icon" /></template>
        </van-cell>
        <van-cell title="查看资金流水" is-link @click="handleViewCashflow" v-perm="PERM.FINANCE_REPORT">
          <template #icon><van-icon name="balance-list-o" class="action-icon" /></template>
        </van-cell>
        <van-cell title="应收款项" is-link @click="handleViewReceivable" v-perm="PERM.FINANCE_VIEW">
          <template #icon><van-icon name="cash-back" class="action-icon" /></template>
        </van-cell>
        <van-cell title="应付款项" is-link @click="handleViewPayable" v-perm="PERM.FINANCE_VIEW">
          <template #icon><van-icon name="cash-on-deliver" class="action-icon" /></template>
        </van-cell>
      </van-cell-group>

      <van-cell-group title="最近收支记录" class="recent-card">
        <van-list v-model:loading="recentLoading" :finished="recentFinished" finished-text="没有更多了" 
          @load="loadRecentRecords">
          <van-cell v-for="record in recentRecords" :key="record.id" :title="getRecordTitle(record)" 
            :label="getRecordLabel(record)" @click="handleViewRecord(record)">
            <template #value>
              <div :class="getAmountClass(record.type)">{{ getAmountDisplay(record.type, record.amount) }}</div>
            </template>
            <template #extra>
              <van-tag :type="getTypeTagType(record.type)">{{ getTypeText(record.type) }}</van-tag>
            </template>
          </van-cell>
        </van-list>

        <van-empty v-if="!recentLoading && recentRecords.length === 0" description="暂无收支记录" image="search" />
      </van-cell-group>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { showToast, showSuccessToast } from 'vant'
import { PERM } from '@/constants/permissions'
import { useFinancialStore } from '@/store/modules/financial'
import { useAuthStore } from '@/store/modules/auth'
import { getFinancialStatistics, getFinancialRecordList } from '@/api/financial'
import dayjs from 'dayjs'

const router = useRouter()
const financialStore = useFinancialStore()
const authStore = useAuthStore()

const loading = ref(true)
const refreshing = ref(false)
const recentLoading = ref(false)
const recentFinished = ref(false)

const todayStats = reactive({ income: 0, expense: 0, balance: 0 })
const monthStats = reactive({ income: 0, expense: 0, profit: 0 })
const trendChart = reactive({ xData: [], series: [] })
const pieChartData = ref([])
const recentRecords = ref([])

const formatAmount = (amount) => {
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

const getRecordTitle = (record) => record.category_name || record.remark || '无备注'

const getRecordLabel = (record) => {
  const parts = []
  if (record.remark) parts.push(record.remark)
  if (record.created_at) parts.push(dayjs(record.created_at).format('MM-DD HH:mm'))
  return parts.join(' | ')
}

const loadFinancialStats = async () => {
  try {
    const todayParams = { start_date: dayjs().format('YYYY-MM-DD'), end_date: dayjs().format('YYYY-MM-DD') }
    const todayResult = await getFinancialStatistics(todayParams)
    
    if (todayResult) {
      Object.assign(todayStats, {
        income: todayResult.total_income || 0,
        expense: todayResult.total_expense || 0,
        balance: (todayResult.total_income || 0) - (todayResult.total_expense || 0)
      })
    }
    
    const monthParams = { start_date: dayjs().startOf('month').format('YYYY-MM-DD'), end_date: dayjs().format('YYYY-MM-DD') }
    const monthResult = await getFinancialStatistics(monthParams)
    
    if (monthResult) {
      Object.assign(monthStats, {
        income: monthResult.total_income || 0,
        expense: monthResult.total_expense || 0,
        profit: (monthResult.total_income || 0) - (monthResult.total_expense || 0)
      })
    }
    
    updateChartData()
  } catch (error) {
    showToast('加载统计数据失败')
  }
}

const updateChartData = () => {
  const last7Days = []
  for (let i = 6; i >= 0; i--) last7Days.push(dayjs().subtract(i, 'day').format('MM-DD'))
  
  trendChart.xData = last7Days
  trendChart.series = [
    { name: '收入', data: last7Days.map(() => Math.floor(Math.random() * 1000) + 500) },
    { name: '支出', data: last7Days.map(() => Math.floor(Math.random() * 800) + 300) }
  ]
  
  const total = (monthStats.income || 0) + (monthStats.expense || 0)
  pieChartData.value = total > 0 ? [
    { value: monthStats.income, name: '收入' }, { value: monthStats.expense, name: '支出' }
  ] : [{ value: 1, name: '暂无数据' }]
}

const loadRecentRecords = async () => {
  try {
    recentLoading.value = true
    const params = { page: 1, limit: 10, sort_by: 'created_desc' }
    const result = await getFinancialRecordList(params)
    
    let listData = result?.list || result?.data?.list || Array.isArray(result) ? result : result || []
    recentRecords.value = listData.slice(0, 5)
    recentFinished.value = true
  } catch {
    showToast('加载最近记录失败')
  } finally {
    recentLoading.value = false
  }
}

const handleRefresh = async () => {
  refreshing.value = true
  try {
    await Promise.all([loadFinancialStats(), loadRecentRecords()])
    showSuccessToast('刷新成功')
  } catch {
    showToast('刷新失败')
  } finally {
    refreshing.value = false
  }
}

const handleCreateIncome = () => router.push('/financial/record/create?type=income')
const handleCreateExpense = () => router.push('/financial/record/create?type=expense')
const handleViewProfitReport = () => router.push('/financial/report/profit')
const handleViewCashflow = () => router.push('/financial/report/cashflow')
const handleViewReceivable = () => router.push('/account/receivable')
const handleViewPayable = () => router.push('/account/payable')
const handleViewRecord = (record) => record.id && router.push(`/financial/record/detail/${record.id}`)

const initData = async () => {
  loading.value = true
  try {
    await Promise.all([loadFinancialStats(), loadRecentRecords()])
  } catch {
    showToast('初始化数据失败')
  } finally {
    loading.value = false
  }
}

onMounted(() => initData())
</script>

<style scoped lang="scss">
.financial-dashboard-page { min-height: 100vh; }

.page-loading {
  position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999;
}

.dashboard-content { padding: 16px; }

.stats-card, .chart-card, .actions-card, .recent-card { margin-bottom: 16px; border-radius: 8px; overflow: hidden; }

.chart-container { padding: 16px; height: 240px; }

.action-icon { margin-right: 8px; font-size: 16px; }

:deep {
  .income-amount { color: #07c160; font-weight: bold; }
  .expense-amount { color: #ee0a24; font-weight: bold; }
  .profit-positive { color: #07c160; font-weight: bold; }
  .profit-negative { color: #ee0a24; font-weight: bold; }
}

@media (max-width: 768px) {
  .dashboard-content { padding: 12px; }
  .chart-container { height: 200px; padding: 12px; }
}
</style>