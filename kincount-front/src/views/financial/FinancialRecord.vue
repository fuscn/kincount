<template>
  <div class="financial-record-page">
    <van-nav-bar title="收支记录" fixed placeholder>
      <template #right>
        <van-button size="small" type="primary" @click="handleCreateRecord" v-perm="PERM.FINANCE_ADD">
          新建记录
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 筛选区域 - 重新设计为卡片式布局 -->
    <div class="filter-section">
      <div class="filter-card">
        <!-- 搜索框 -->
        <div class="search-row">
          <van-search 
            v-model="filters.keyword" 
            placeholder="搜索备注/金额/分类" 
            shape="round"
            background="#f7f8fa"
            @search="loadRecordList(true)" 
            @clear="handleClearSearch"
          >
            <template #action>
              <div class="search-action" @click="loadRecordList(true)">搜索</div>
            </template>
          </van-search>
        </div>

        <!-- 筛选条件 -->
        <div class="filter-row">
          <van-dropdown-menu class="filter-menu">
            <van-dropdown-item 
              v-model="filters.type" 
              :options="typeOptions" 
              @change="loadRecordList(true)"
            >
              <template #title>
                <div class="filter-title">
                  <van-icon name="filter-o" />
                  <span>{{ getSelectedTypeText() }}</span>
                </div>
              </template>
            </van-dropdown-item>
            
            <van-dropdown-item 
              v-model="filters.category_id" 
              :options="categoryOptions" 
              @change="loadRecordList(true)"
            >
              <template #title>
                <div class="filter-title">
                  <van-icon name="label-o" />
                  <span>{{ getSelectedCategoryText() }}</span>
                </div>
              </template>
            </van-dropdown-item>
          </van-dropdown-menu>
        </div>

        <!-- 日期筛选 -->
        <div class="date-filter-row">
          <van-field 
            v-model="filters.start_date" 
            label="开始日期" 
            placeholder="选择开始日期" 
            readonly 
            is-link 
            @click="showStartDatePicker = true"
            class="date-field"
          />
          <div class="date-separator">至</div>
          <van-field 
            v-model="filters.end_date" 
            label="结束日期" 
            placeholder="选择结束日期" 
            readonly 
            is-link 
            @click="showEndDatePicker = true"
            class="date-field"
          />
        </div>

        <!-- 快捷日期按钮 -->
        <div class="quick-date-buttons">
          <van-button 
            size="small" 
            :type="activeQuickDate === 'today' ? 'primary' : 'default'"
            @click="setQuickDate('today')"
          >
            今天
          </van-button>
          <van-button 
            size="small" 
            :type="activeQuickDate === 'week' ? 'primary' : 'default'"
            @click="setQuickDate('week')"
          >
            本周
          </van-button>
          <van-button 
            size="small" 
            :type="activeQuickDate === 'month' ? 'primary' : 'default'"
            @click="setQuickDate('month')"
          >
            本月
          </van-button>
          <van-button 
            size="small" 
            :type="activeQuickDate === 'all' ? 'primary' : 'default'"
            @click="setQuickDate('all')"
          >
            全部
          </van-button>
        </div>
      </div>
    </div>

    <!-- 统计信息卡片 -->
    <div class="statistics-section" v-if="statistics.total_income > 0 || statistics.total_expense > 0">
      <div class="stats-card">
        <div class="stats-item">
          <div class="stats-label">总收入</div>
          <div class="stats-value income-amount">+¥{{ formatAmount(statistics.total_income) }}</div>
        </div>
        <div class="stats-item">
          <div class="stats-label">总支出</div>
          <div class="stats-value expense-amount">-¥{{ formatAmount(statistics.total_expense) }}</div>
        </div>
        <div class="stats-item">
          <div class="stats-label">净收入</div>
          <div class="stats-value" :class="statistics.net_income >= 0 ? 'net-income-positive' : 'net-income-negative'">
            ¥{{ formatAmount(statistics.net_income) }}
          </div>
        </div>
      </div>
    </div>

    <!-- 记录列表区域 - 明显的分割 -->
    <div class="list-section">
      <div class="list-header">
        <h3 class="list-title">收支记录</h3>
        <div class="list-count" v-if="recordList.length > 0">
          共 {{ pagination.total }} 条记录
        </div>
      </div>

      <van-pull-refresh v-model="refreshing" @refresh="loadRecordList(true)">
        <van-list 
          v-model:loading="listLoading" 
          :finished="finished" 
          :finished-text="recordList.length === 0 ? '暂无收支记录' : '没有更多了'" 
          @load="loadRecordList"
        >
          <!-- 记录列表 -->
          <div class="record-list">
            <div 
              v-for="record in recordList" 
              :key="record.id" 
              class="record-item"
              @click="handleViewRecord(record)"
            >
              <div class="record-main">
                <div class="record-header">
                  <div class="record-title">{{ getRecordTitle(record) }}</div>
                  <div :class="getAmountClass(record.type)">
                    {{ getAmountDisplay(record.type, record.amount) }}
                  </div>
                </div>
                <div class="record-info">
                  <van-tag :type="getTypeTagType(record.type)" size="small">
                    {{ getTypeText(record.type) }}
                  </van-tag>
                  <span class="record-category">{{ record.category_name || record.category }}</span>
                  <span class="record-date">{{ formatRecordDate(record.record_date || record.created_at) }}</span>
                </div>
                <div class="record-remark" v-if="record.remark">
                  {{ record.remark }}
                </div>
                <div class="record-footer">
                  <span class="record-payment">{{ record.payment_method || '无支付方式' }}</span>
                  <span class="record-creator" v-if="record.created_by_name">
                    {{ record.created_by_name }}
                  </span>
                </div>
              </div>
              <van-icon name="arrow" class="record-arrow" />
            </div>
          </div>

          <!-- 空状态 -->
          <van-empty 
            v-if="!listLoading && !refreshing && recordList.length === 0" 
            description="暂无收支记录" 
            image="search"
          >
            <van-button type="primary" @click="handleCreateRecord">新建记录</van-button>
          </van-empty>
        </van-list>
      </van-pull-refresh>
    </div>

    <!-- 日期选择器 -->
    <van-popup v-model:show="showStartDatePicker" position="bottom">
      <van-date-picker 
        :min-date="minDate" 
        :max-date="maxDate" 
        @confirm="onStartDateConfirm" 
        @cancel="showStartDatePicker = false"
      />
    </van-popup>
    <van-popup v-model:show="showEndDatePicker" position="bottom">
      <van-date-picker 
        :min-date="minDate" 
        :max-date="maxDate" 
        @confirm="onEndDateConfirm" 
        @cancel="showEndDatePicker = false"
      />
    </van-popup>

    <van-loading v-if="initialLoading" class="page-loading"/>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import { PERM } from '@/constants/permissions'
import { useFinancialStore } from '@/store/modules/financial'
import dayjs from 'dayjs'

const router = useRouter()
const financialStore = useFinancialStore()

// 响应式数据
const filters = reactive({
  keyword: '',
  type: '',
  category_id: '',
  start_date: dayjs().startOf('month').format('YYYY-MM-DD'),
  end_date: dayjs().format('YYYY-MM-DD')
})

const recordList = ref([])
const refreshing = ref(false)
const listLoading = ref(false)
const initialLoading = ref(true)
const finished = ref(false)
const showStartDatePicker = ref(false)
const showEndDatePicker = ref(false)
const activeQuickDate = ref('month') // 默认选中本月

const pagination = reactive({ page: 1, pageSize: 15, total: 0 })
const statistics = reactive({ total_income: 0, total_expense: 0, net_income: 0 })
const minDate = new Date(2020, 0, 1)
const maxDate = new Date(2030, 11, 31)

const typeOptions = ref([
  { text: '全部类型', value: '' },
  { text: '收入', value: 'income' },
  { text: '支出', value: 'expense' }
])

const categoryOptions = ref([{ text: '全部分类', value: '' }])

// 计算属性
const getSelectedTypeText = () => {
  const selected = typeOptions.value.find(option => option.value === filters.type)
  return selected ? selected.text : '收支类型'
}

const getSelectedCategoryText = () => {
  const selected = categoryOptions.value.find(option => option.value === filters.category_id)
  return selected ? selected.text : '收支分类'
}

// 方法
const formatAmount = (amount) => {
  const num = parseFloat(amount)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

// 修复类型显示函数 - 兼容数字和字符串类型值
const getTypeText = (type) => {
  if (type === 1 || type === '1' || type === 'income') return '收入'
  if (type === 2 || type === '2' || type === 'expense') return '支出'
  console.warn('未知类型值:', type, typeof type)
  return '未知类型'
}

const getTypeTagType = (type) => {
  if (type === 1 || type === '1' || type === 'income') return 'success'
  if (type === 2 || type === '2' || type === 'expense') return 'danger'
  return 'default'
}

const getAmountDisplay = (type, amount) => {
  const sign = (type === 1 || type === '1' || type === 'income') ? '+' : '-'
  return `${sign}¥${formatAmount(amount)}`
}

const getAmountClass = (type) => (type === 1 || type === '1' || type === 'income') ? 'income-amount' : 'expense-amount'

const getRecordTitle = (record) => {
  return record.category_name || record.category || record.remark || '无备注'
}

const formatRecordDate = (date) => {
  if (!date) return ''
  return dayjs(date).format('MM-DD HH:mm')
}

// 快捷日期设置
const setQuickDate = (type) => {
  activeQuickDate.value = type
  const today = dayjs()
  
  switch (type) {
    case 'today':
      filters.start_date = today.format('YYYY-MM-DD')
      filters.end_date = today.format('YYYY-MM-DD')
      break
    case 'week':
      filters.start_date = today.startOf('week').format('YYYY-MM-DD')
      filters.end_date = today.endOf('week').format('YYYY-MM-DD')
      break
    case 'month':
      filters.start_date = today.startOf('month').format('YYYY-MM-DD')
      filters.end_date = today.endOf('month').format('YYYY-MM-DD')
      break
    case 'all':
      filters.start_date = '2020-01-01'
      filters.end_date = today.format('YYYY-MM-DD')
      break
  }
  
  loadRecordList(true)
}

// 加载记录列表
const loadRecordList = async (isRefresh = false) => {
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
    
    await financialStore.loadRecordList(params)
    
    let listData = []
    let totalCount = 0

    if (financialStore.recordList && Array.isArray(financialStore.recordList)) {
      listData = financialStore.recordList
      totalCount = financialStore.recordTotal || 0
    }

    if (isRefresh) {
      recordList.value = listData
    } else {
      const existingIds = new Set(recordList.value.map(item => item.id))
      const newItems = listData.filter(item => !existingIds.has(item.id))
      recordList.value = [...recordList.value, ...newItems]
    }
    
    pagination.total = totalCount

    if (recordList.value.length >= pagination.total) {
      finished.value = true
    } else {
      pagination.page++
    }

    if (isRefresh) await loadStatistics()

  } catch (error) {
    console.error('加载收支记录失败:', error)
    showToast('加载收支记录失败')
    finished.value = true
  } finally {
    refreshing.value = false
    listLoading.value = false
    initialLoading.value = false
  }
}

// 加载统计信息
const loadStatistics = async () => {
  try {
    const params = { start_date: filters.start_date, end_date: filters.end_date }
    await financialStore.loadStatistics(params)
    if (financialStore.statistics) {
      Object.assign(statistics, financialStore.statistics)
      statistics.net_income = (statistics.total_income || 0) - (statistics.total_expense || 0)
    }
  } catch (error) {
    console.error('加载统计信息失败:', error)
  }
}

// 加载分类选项
const loadCategoryOptions = async () => {
  try {
    await financialStore.loadCategories()
    const incomeCategories = financialStore.categories.income || {}
    const expenseCategories = financialStore.categories.expense || {}
    const allCategories = [...Object.values(incomeCategories), ...Object.values(expenseCategories)]
    categoryOptions.value = [
      { text: '全部分类', value: '' },
      ...allCategories.map(category => ({ text: category.name, value: category.id }))
    ]
  } catch (error) {
    console.error('加载分类选项失败:', error)
    showToast('加载分类列表失败')
  }
}

// 日期选择处理
const onStartDateConfirm = (value) => {
  filters.start_date = dayjs(value.selectedValues.join('-')).format('YYYY-MM-DD')
  showStartDatePicker.value = false
  activeQuickDate.value = '' // 清除快捷日期选中状态
  loadRecordList(true)
}

const onEndDateConfirm = (value) => {
  filters.end_date = dayjs(value.selectedValues.join('-')).format('YYYY-MM-DD')
  showEndDatePicker.value = false
  activeQuickDate.value = '' // 清除快捷日期选中状态
  loadRecordList(true)
}

// 事件处理
const handleCreateRecord = () => router.push('/financial/record/create')
const handleViewRecord = (record) => router.push(`/financial/record/detail/${record.id}`)
const handleClearSearch = () => { 
  filters.keyword = ''
  loadRecordList(true) 
}

onMounted(() => {
  loadCategoryOptions()
  loadRecordList(true)
})
</script>

<style scoped lang="scss">
.financial-record-page {
  background: #f7f8fa;
  min-height: 100vh;
}

/* 筛选区域样式 */
.filter-section {
  padding: 12px;
  background: #f7f8fa;
}

.filter-card {
  background: white;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.search-row {
  margin-bottom: 12px;
}

.search-action {
  color: #1989fa;
  font-weight: 500;
  padding: 0 8px;
}

.filter-row {
  margin-bottom: 12px;
}

.filter-menu {
  background: transparent;
}

.filter-title {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 14px;
  color: #323233;
  
  .van-icon {
    font-size: 14px;
    color: #969799;
  }
}

.date-filter-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.date-field {
  flex: 1;
  padding: 0;
  
  :deep(.van-field__body) {
    padding: 8px 12px;
    background: #f7f8fa;
    border-radius: 6px;
  }
}

.date-separator {
  color: #969799;
  font-size: 14px;
  min-width: 20px;
  text-align: center;
}

.quick-date-buttons {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  
  .van-button {
    flex: 1;
    min-width: 60px;
    border-radius: 16px;
  }
}

/* 统计信息样式 */
.statistics-section {
  padding: 0 12px 12px;
}

.stats-card {
  background: white;
  border-radius: 12px;
  padding: 16px;
  display: flex;
  justify-content: space-between;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.stats-item {
  text-align: center;
  flex: 1;
}

.stats-label {
  font-size: 12px;
  color: #969799;
  margin-bottom: 4px;
}

.stats-value {
  font-size: 16px;
  font-weight: 600;
}

.income-amount {
  color: #07c160;
}

.expense-amount {
  color: #ee0a24;
}

.net-income-positive {
  color: #07c160;
}

.net-income-negative {
  color: #ee0a24;
}

/* 列表区域样式 */
.list-section {
  background: white;
  border-radius: 12px 12px 0 0;
  min-height: 60vh;
}

.list-header {
  padding: 16px 16px 12px;
  border-bottom: 1px solid #f0f0f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.list-title {
  font-size: 16px;
  font-weight: 600;
  color: #323233;
  margin: 0;
}

.list-count {
  font-size: 12px;
  color: #969799;
}

.record-list {
  padding: 0 16px;
}

.record-item {
  display: flex;
  align-items: center;
  padding: 16px 0;
  border-bottom: 1px solid #f7f8fa;
  cursor: pointer;
  
  &:last-child {
    border-bottom: none;
  }
  
  &:active {
    background: #fafafa;
  }
}

.record-main {
  flex: 1;
}

.record-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 8px;
}

.record-title {
  font-size: 16px;
  font-weight: 500;
  color: #323233;
  flex: 1;
  margin-right: 12px;
}

.record-info {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
  flex-wrap: wrap;
}

.record-category {
  font-size: 12px;
  color: #969799;
}

.record-date {
  font-size: 12px;
  color: #969799;
}

.record-remark {
  font-size: 14px;
  color: #646566;
  line-height: 1.4;
  margin-bottom: 6px;
  word-break: break-all;
}

.record-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.record-payment {
  font-size: 12px;
  color: #1989fa;
}

.record-creator {
  font-size: 12px;
  color: #969799;
}

.record-arrow {
  color: #c8c9cc;
  font-size: 16px;
  margin-left: 8px;
}

/* 加载状态 */
.page-loading {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 9999;
}

/* 响应式设计 */
@media (max-width: 768px) {
  .filter-section,
  .statistics-section {
    padding: 8px;
  }
  
  .filter-card {
    padding: 12px;
  }
  
  .stats-card {
    padding: 12px;
  }
  
  .record-list {
    padding: 0 12px;
  }
  
  .record-item {
    padding: 12px 0;
  }
}

/* 下拉菜单样式优化 */
:deep(.van-dropdown-menu__bar) {
  background: transparent;
  box-shadow: none;
}

:deep(.van-dropdown-menu__title) {
  padding: 8px 12px;
  background: #f7f8fa;
  border-radius: 6px;
}

:deep(.van-dropdown-menu__title::after) {
  display: none;
}
</style>