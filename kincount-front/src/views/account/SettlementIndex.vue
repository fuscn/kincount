<template>
  <div class="settlement-container">
    <!-- 导航栏 -->
    <van-nav-bar title="账款核销记录" left-text="返回" left-arrow @click-left="$router.back()" />

    <!-- 筛选区域 -->
    <div class="filter-wrapper">
      <!-- 核销类型标签筛选 -->
      <van-tabs v-model="activeType" @change="handleTypeChange" swipeable>
        <van-tab title="全部" name="" />
        <van-tab title="应收核销" name="1" />
        <van-tab title="应付核销" name="2" />
      </van-tabs>

      <!-- 搜索与高级筛选 -->
      <div class="search-filter">
        <van-search v-model="searchParams.keyword" placeholder="搜索核销单号、对方名称" @search="handleSearch"
          @clear="handleClearSearch" />
        <van-dropdown-menu>
          <!-- 对方类型筛选 -->
          <van-dropdown-item v-model="searchParams.target_type" :options="targetTypeOptions" placeholder="对方类型"
            @change="handleFilterChange" />
          <!-- 时间筛选 -->
          <van-dropdown-item ref="dateDropdown" title="选择时间">
            <div class="date-filter-content">
              <van-cell title="开始日期" :value="searchParams.start_date || '请选择'" @click="showStartDatePicker = true" />
              <van-cell title="结束日期" :value="searchParams.end_date || '请选择'" @click="showEndDatePicker = true" />
              <div class="date-filter-actions">
                <van-button size="small" type="default" @click="handleDateReset">重置</van-button>
                <van-button size="small" type="primary" @click="handleDateConfirm">确认</van-button>
              </div>
            </div>
          </van-dropdown-item>
        </van-dropdown-menu>
      </div>
    </div>

    <!-- 统计卡片 -->
    <div v-if="summaryData.total_amount > 0" class="summary-cards">
      <van-row gutter="8">
        <van-col span="12">
          <div class="summary-card income">
            <div class="card-label">应收核销总额</div>
            <div class="card-value">¥{{ formatPrice(summaryData.receivable_amount || 0) }}</div>
            <div class="card-count">{{ summaryData.receivable_count || 0 }}笔</div>
          </div>
        </van-col>
        <van-col span="12">
          <div class="summary-card expense">
            <div class="card-label">应付核销总额</div>
            <div class="card-value">¥{{ formatPrice(summaryData.payable_amount || 0) }}</div>
            <div class="card-count">{{ summaryData.payable_count || 0 }}笔</div>
          </div>
        </van-col>
      </van-row>
    </div>

    <!-- 列表区域 -->
    <div class="list-section">
      <van-pull-refresh v-model="refreshing" @refresh="onRefresh">
        <van-list v-model:loading="loading" :finished="finished" :finished-text="settlementList.length > 0 ? '没有更多了' : ''"
          @load="onLoad">
          <!-- 空状态 -->
          <div v-if="settlementList.length === 0 && !loading" class="empty-state">
            <van-empty image="search" description="暂无核销记录" />
          </div>

          <!-- 核销记录列表 -->
          <div v-for="item in settlementList" :key="item.id" class="settlement-item" @click="handleViewDetail(item)">
            <div class="settlement-header">
              <div class="header-left">
                <span class="settlement-no">{{ item.settlement_no }}</span>
                <van-tag :type="getTypeTagType(item.account_type)" size="small">
                  {{ getTypeText(item.account_type) }}
                </van-tag>
              </div>
              <span class="settlement-amount" :class="getAmountClass(item.account_type)">
                {{ item.account_type === 1 ? '+' : '-' }}¥{{ formatPrice(item.settlement_amount) }}
              </span>
            </div>

            <div class="settlement-content">
              <div class="settlement-info">
                <div class="info-row">
                  <span class="label">对方：</span>
                  <span class="value">{{ item.target_name || getTargetName(item) }}</span>
                </div>

                <div class="info-row">
                  <span class="label">关联账款：</span>
                  <span class="value">{{ getRelatedInfo(item) }}</span>
                </div>

                <div class="info-row">
                  <span class="label">关联财务：</span>
                  <span class="value">{{ item.financial_no || `FR${item.financial_id}` }}</span>
                </div>

                <div class="info-row">
                  <span class="label">核销日期：</span>
                  <span class="value">{{ formatDate(item.settlement_date || item.created_at) }}</span>
                  <span class="label" style="margin-left: 16px;">操作人：</span>
                  <span class="value">{{ item.creator_name || '系统' }}</span>
                </div>

                <div v-if="item.remark" class="info-row">
                  <span class="label">备注：</span>
                  <span class="value remark">{{ item.remark }}</span>
                </div>
              </div>

              <!-- 操作按钮 -->
              <div v-if="item.cancelable" class="settlement-actions">
                <van-button size="mini" type="danger" @click.stop="handleCancelSettlement(item)"
                  v-perm="PERM.SETTLEMENT_CANCEL">
                  取消核销
                </van-button>
              </div>
            </div>
          </div>
        </van-list>
      </van-pull-refresh>
    </div>

    <!-- 日期选择器 -->
    <van-popup v-model:show="showStartDatePicker" position="bottom">
      <van-date-picker v-model="startDate" @confirm="onStartDateConfirm" @cancel="showStartDatePicker = false" />
    </van-popup>
    <van-popup v-model:show="showEndDatePicker" position="bottom">
      <van-date-picker v-model="endDate" @confirm="onEndDateConfirm" @cancel="showEndDatePicker = false" />
    </van-popup>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useSettlementStore } from '@/store/modules/settlement'
import { useAccountStore } from '@/store/modules/account'
import { showConfirmDialog, showToast, showDialog } from 'vant'
import { useRouter } from 'vue-router'
import { PERM } from '@/constants/permissions'

const settlementStore = useSettlementStore()
const accountStore = useAccountStore()
const router = useRouter()

// 激活的类型标签
const activeType = ref('')

// 搜索参数
const searchParams = ref({
  keyword: '',
  account_type: '',
  target_type: '',
  start_date: '',
  end_date: ''
})

// 日期选择相关
const showStartDatePicker = ref(false)
const showEndDatePicker = ref(false)
const startDate = ref([])
const endDate = ref([])
const dateDropdown = ref(null)

// 对方类型选项
const targetTypeOptions = ref([
  { text: '全部类型', value: '' },
  { text: '客户', value: 'customer' },
  { text: '供应商', value: 'supplier' }
])

// 列表相关状态
const loading = ref(false)
const finished = ref(false)
const refreshing = ref(false)
const settlementList = computed(() => settlementStore.settlementList || [])
const summaryData = computed(() => settlementStore.statistics || {})

// 分页参数
const pagination = ref({
  page: 1,
  page_size: 20
})

// 格式化金额
const formatPrice = (price) => {
  if (price === null || price === undefined || price === '') return '0.00'
  const num = Number(price)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

// 格式化日期
const formatDate = (dateString) => {
  if (!dateString) return ''
  if (dateString.includes('-')) {
    return dateString.split(' ')[0]
  }
  const date = new Date(dateString)
  return date.toLocaleDateString()
}

// 获取类型文本
const getTypeText = (type) => {
  const typeMap = {
    1: '应收核销',
    2: '应付核销'
  }
  return typeMap[type] || '未知'
}

// 获取类型标签样式
const getTypeTagType = (type) => {
  return type === 1 ? 'success' : 'warning'
}

// 获取金额样式
const getAmountClass = (type) => {
  return type === 1 ? 'income' : 'expense'
}

// 获取对方名称
const getTargetName = (item) => {
  if (item.customer) return item.customer.name
  if (item.supplier) return item.supplier.name
  if (item.account) return item.account.target_name
  return '未知'
}

// 获取关联信息
const getRelatedInfo = (item) => {
  if (item.account) {
    const account = item.account
    return `${getRelatedTypeText(account.related_type)}#${account.related_id} (余额:¥${formatPrice(account.balance_amount)})`
  }
  return `账款#${item.account_id}`
}

// 获取关联类型文本
const getRelatedTypeText = (relatedType) => {
  const typeMap = {
    'sale': '销售单',
    'purchase': '采购单',
    'sale_return': '销售退货',
    'purchase_return': '采购退货'
  }
  return typeMap[relatedType] || relatedType
}

// 查看详情
const handleViewDetail = (item) => {
  router.push(`/account/settlement/${item.id}`)
}

// 取消核销
const handleCancelSettlement = (item) => {
  showConfirmDialog({
    title: '确认取消核销',
    message: `确定要取消核销记录 ${item.settlement_no} 吗？此操作将恢复原始账款余额，且不可恢复。`
  }).then(async () => {
    try {
      await settlementStore.cancelSettlement(item.id)
      showToast('取消核销成功')
      onRefresh() // 刷新列表
    } catch (error) {
      showToast('取消核销失败: ' + (error.message || '未知错误'))
    }
  }).catch(() => {
    // 用户取消
  })
}

// 类型标签变化
const handleTypeChange = (name) => {
  searchParams.value.account_type = name
  handleSearch()
}

// 搜索处理
const handleSearch = () => {
  pagination.value.page = 1
  finished.value = false
  onLoad(true)
}

// 清除搜索
const handleClearSearch = () => {
  searchParams.value.keyword = ''
  handleSearch()
}

// 筛选条件变化
const handleFilterChange = () => {
  handleSearch()
}

// 日期确认
const handleDateConfirm = () => {
  if (dateDropdown.value) {
    dateDropdown.value.toggle()
  }
  handleSearch()
}

// 日期重置
const handleDateReset = () => {
  searchParams.value.start_date = ''
  searchParams.value.end_date = ''
  startDate.value = []
  endDate.value = []
  if (dateDropdown.value) {
    dateDropdown.value.toggle()
  }
  handleSearch()
}

// 下拉刷新
const onRefresh = () => {
  pagination.value.page = 1
  finished.value = false
  onLoad(true)
}

// 加载数据
const onLoad = async (isRefresh = false) => {
  // 防止重复请求
  if (loading.value && !isRefresh) return

  if (isRefresh) {
    refreshing.value = true
  } else {
    loading.value = true
  }

  try {
    const params = {
      ...pagination.value,
      ...searchParams.value
    }

    // 清理空参数
    Object.keys(params).forEach(key => {
      if (params[key] === '') {
        delete params[key]
      }
    })

    await settlementStore.fetchSettlementList(params)

    if (isRefresh) {
      refreshing.value = false
    } else {
      loading.value = false
    }

    // 检查是否加载完毕
    const currentLength = settlementList.value.length
    const total = settlementStore.settlementListTotal || summaryData.value.total_count || 0

    if (currentLength >= total || currentLength === 0) {
      finished.value = true
    } else {
      pagination.value.page++
    }
  } catch (error) {
    console.error('加载核销记录列表失败:', error)
    if (isRefresh) {
      refreshing.value = false
    } else {
      loading.value = false
    }
    finished.value = true
  }
}

// 开始日期选择确认
const onStartDateConfirm = (value) => {
  const year = value.selectedValues[0]
  const month = value.selectedValues[1].toString().padStart(2, '0')
  const day = value.selectedValues[2].toString().padStart(2, '0')
  searchParams.value.start_date = `${year}-${month}-${day}`
  showStartDatePicker.value = false
}

// 结束日期选择确认
const onEndDateConfirm = (value) => {
  const year = value.selectedValues[0]
  const month = value.selectedValues[1].toString().padStart(2, '0')
  const day = value.selectedValues[2].toString().padStart(2, '0')
  searchParams.value.end_date = `${year}-${month}-${day}`
  showEndDatePicker.value = false
}

// 初始化加载
onMounted(async () => {
  // 加载统计数据
  try {
    // 可以根据需要添加统计API调用
    // await settlementStore.loadStatistics()
  } catch (error) {
    console.error('加载统计数据失败:', error)
  }
})
</script>

<style scoped lang="scss">
.settlement-container {
  padding: 0;
  background-color: #f7f8fa;
  min-height: 100vh;
}

/* 导航栏样式 */
:deep(.van-nav-bar) {
  background: #fff;
  position: sticky;
  top: 0;
  z-index: 1000;
}

/* 筛选区域样式 */
.filter-wrapper {
  background: white;
  position: sticky;
  top: 46px;
  /* 导航栏高度 */
  z-index: 100;
}

.search-filter {
  padding: 0 12px;
}

:deep(.van-tabs__wrap) {
  border-bottom: 1px solid #f0f0f0;
}

:deep(.van-dropdown-menu) {
  box-shadow: none;
  background: transparent;
}

.date-filter-content {
  padding: 16px;
}

.date-filter-actions {
  display: flex;
  justify-content: space-between;
  margin-top: 16px;
  padding: 0 8px;
}

/* 统计卡片 */
.summary-cards {
  padding: 12px;
  background: white;
  margin-bottom: 8px;
}

.summary-card {
  padding: 16px;
  border-radius: 8px;
  text-align: center;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);

  &.income {
    background: linear-gradient(135deg, #34d399, #059669);
    color: white;
  }

  &.expense {
    background: linear-gradient(135deg, #fbbf24, #d97706);
    color: white;
  }
}

.card-label {
  font-size: 12px;
  opacity: 0.9;
  margin-bottom: 4px;
}

.card-value {
  font-size: 20px;
  font-weight: bold;
  margin-bottom: 4px;
}

.card-count {
  font-size: 11px;
  opacity: 0.8;
}

/* 列表区域样式 */
.list-section {
  background: white;
  min-height: 60vh;
}

.empty-state {
  padding: 40px 20px;
}

.settlement-item {
  margin: 12px;
  border: 1px solid #ebedf0;
  border-radius: 8px;
  overflow: hidden;
  background: white;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  cursor: pointer;
}

.settlement-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: #fafafa;
  border-bottom: 1px solid #f0f0f0;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 8px;
}

.settlement-no {
  font-weight: bold;
  color: #323233;
  font-size: 14px;
}

.settlement-amount {
  font-weight: bold;
  font-size: 16px;

  &.income {
    color: #059669;
  }

  &.expense {
    color: #d97706;
  }
}

.settlement-content {
  padding: 16px;
}

.settlement-info {
  margin-bottom: 12px;
}

.info-row {
  display: flex;
  margin-bottom: 6px;
  font-size: 13px;
  align-items: flex-start;

  &:last-child {
    margin-bottom: 0;
  }
}

.info-row .label {
  color: #969799;
  min-width: 70px;
  flex-shrink: 0;
}

.info-row .value {
  color: #323233;
  flex: 1;
  word-break: break-word;
}

.info-row .remark {
  color: #646566;
  font-size: 12px;
}

.settlement-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px dashed #f0f0f0;
}

:deep(.van-button--mini) {
  height: 24px;
  padding: 0 8px;
  font-size: 11px;
}
</style>