<template>
  <div class="account-receivable-container">
    <!-- 导航栏 -->
    <van-nav-bar title="应收账款管理" left-text="返回" left-arrow @click-left="$router.back()" />

    <!-- 筛选区域 -->
    <div class="filter-wrapper">
      <!-- 状态标签筛选 -->
      <van-tabs v-model="activeStatus" @change="handleStatusChange" swipeable>
        <van-tab title="全部" name="" />
        <van-tab title="未结算" name="0" />
        <van-tab title="已结算" name="1" />
      </van-tabs>

      <!-- 搜索与高级筛选 -->
      <div class="search-filter">
        <van-search v-model="searchParams.keyword" placeholder="搜索客户名称、订单号" @search="handleSearch"
          @clear="handleClearSearch" />
        <van-dropdown-menu>
          <!-- 客户筛选 -->
          <van-dropdown-item v-model="searchParams.customer_id" :options="customerOptions" placeholder="选择客户"
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

    <!-- 统计信息 -->
    <div class="summary-section">
      <van-row gutter="12">
        <van-col span="8">
          <div class="summary-item">
            <div class="summary-label">应收总额</div>
            <div class="summary-value total">¥{{ formatPrice(summary.receivable_total || 0) }}</div>
          </div>
        </van-col>
        <van-col span="8">
          <div class="summary-item">
            <div class="summary-label">已收金额</div>
            <div class="summary-value received">¥{{ formatPrice(summary.paid_total || 0) }}</div>
          </div>
        </van-col>
        <van-col span="8">
          <div class="summary-item">
            <div class="summary-label">未收金额</div>
            <div class="summary-value unpaid">¥{{ formatPrice(summary.unpaid_total || 0) }}</div>
          </div>
        </van-col>
      </van-row>
    </div>

    <!-- 列表区域 -->
    <div class="list-section">
      <van-pull-refresh v-model="refreshing" @refresh="onRefresh">
        <van-list v-model:loading="loading" :finished="finished" :finished-text="receivableList.length > 0 ? '没有更多了' : ''"
          @load="onLoad">
          <!-- 空状态 -->
          <div v-if="receivableList.length === 0 && !loading" class="empty-state">
            <van-empty image="search" description="暂无应收账款数据" />
          </div>

          <!-- 应收账款列表 -->
          <div v-for="item in receivableList" :key="item.id" class="receivable-item">
            <div class="receivable-header">
              <span class="order-no">{{ item.order_no || `AR${item.id}` }}</span>
              <van-tag :type="getStatusTagType(item.status)" size="small">
                {{ getStatusText(item.status) }}
              </van-tag>
            </div>

            <div class="receivable-content">
              <div class="receivable-info">
                <div class="info-item">
                  <span class="label">客户：</span>
                  <span class="value">{{ item.customer_name || item.customer?.name || '未知' }}</span>
                </div>

                <div class="info-item">
                  <span class="label">应收金额：</span>
                  <span class="value amount">¥{{ formatPrice(item.total_amount) }}</span>
                  <span class="label">已收金额：</span>
                  <span class="value received">¥{{ formatPrice(item.paid_amount || 0) }}</span>
                </div>

                <div class="info-item">
                  <span class="label">未收金额：</span>
                  <span class="value unpaid">¥{{ formatPrice(item.unpaid_amount || (item.total_amount - (item.paid_amount || 0))) }}</span>
                </div>

                <div class="info-item">
                  <span class="label">创建时间：</span>
                  <span class="value time">{{ formatDate(item.created_at) }}</span>
                  <van-button v-if="item.status === 0" size="mini" type="primary" @click.stop="handleSettle(item)">
                    结算
                  </van-button>
                  <van-button v-if="item.status === 1" size="mini" type="default" @click.stop="handleViewDetail(item)">
                    查看详情
                  </van-button>
                </div>
              </div>
            </div>
          </div>
        </van-list>
      </van-pull-refresh>
    </div>

    <!-- 结算对话框 -->
    <van-dialog v-model:show="showSettleDialog" title="结算" show-cancel-button @confirm="handleSettleConfirm">
      <div class="settle-dialog-content">
        <van-cell-group inset>
          <van-field v-model="settleForm.amount" label="结算金额" placeholder="请输入结算金额" type="number">
            <template #extra>
              <span class="settle-hint">最大可结：¥{{ formatPrice(maxSettleAmount) }}</span>
            </template>
          </van-field>
          <van-field v-model="settleForm.remark" label="备注" placeholder="请输入备注信息" type="textarea" rows="2" />
        </van-cell-group>
      </div>
    </van-dialog>

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
import { useAccountStore } from '@/store/modules/account'
import { useCustomerStore } from '@/store/modules/customer'
import { showConfirmDialog, showToast, showDialog } from 'vant'
import { useRouter } from 'vue-router'

const accountStore = useAccountStore()
const customerStore = useCustomerStore()
const router = useRouter()

// 激活的状态标签
const activeStatus = ref('')

// 搜索参数
const searchParams = ref({
  keyword: '',
  customer_id: '',
  status: '',
  start_date: '',
  end_date: ''
})

// 日期选择相关
const showStartDatePicker = ref(false)
const showEndDatePicker = ref(false)
const startDate = ref([])
const endDate = ref([])
const dateDropdown = ref(null)

// 客户选项
const customerOptions = ref([
  { text: '全部客户', value: '' },
])

// 列表相关状态
const loading = ref(false)
const finished = ref(false)
const refreshing = ref(false)
const receivableList = computed(() => accountStore.receivableList || [])

// 统计信息
const summary = computed(() => accountStore.summary || {})

// 分页参数
const pagination = ref({
  page: 1,
  page_size: 20
})

// 结算相关
const showSettleDialog = ref(false)
const currentSettleItem = ref(null)
const settleForm = ref({
  amount: '',
  remark: ''
})
const maxSettleAmount = computed(() => {
  if (!currentSettleItem.value) return 0
  const item = currentSettleItem.value
  return item.unpaid_amount || (item.total_amount - (item.paid_amount || 0))
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
  const date = new Date(dateString)
  return date.toLocaleDateString() + ' ' + date.toLocaleTimeString().slice(0, 5)
}

// 状态文本映射
const getStatusText = (status) => {
  const statusMap = {
    0: '未结算',
    1: '已结算'
  }
  return statusMap[status] || '未知'
}

// 状态标签类型
const getStatusTagType = (status) => {
  const typeMap = {
    0: 'warning',  // 未结算 - 警告色
    1: 'success',  // 已结算 - 成功色
  }
  return typeMap[status] || 'default'
}

// 查看详情
const handleViewDetail = (item) => {
  // 根据实际路由调整
  router.push(`/account/receivable/detail/${item.id}`)
}

// 结算操作
const handleSettle = (item) => {
  currentSettleItem.value = item
  settleForm.value = {
    amount: maxSettleAmount.value.toString(),
    remark: ''
  }
  showSettleDialog.value = true
}

// 结算确认
const handleSettleConfirm = async () => {
  if (!currentSettleItem.value) return
  
  const amount = Number(settleForm.value.amount)
  if (isNaN(amount) || amount <= 0) {
    showToast('请输入有效的结算金额')
    return
  }
  
  if (amount > maxSettleAmount.value) {
    showToast('结算金额不能超过未收金额')
    return
  }
  
  try {
    await accountStore.payRecord(currentSettleItem.value.id, amount)
    showToast('结算成功')
    showSettleDialog.value = false
    
    // 刷新数据和统计
    await Promise.all([
      loadReceivableList(true),
      loadSummary()
    ])
  } catch (error) {
    showToast('结算失败: ' + (error.message || '未知错误'))
  }
}

// 状态标签变化
const handleStatusChange = (name) => {
  searchParams.value.status = name
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

// 加载应收账款列表
const loadReceivableList = async (isRefresh = false) => {
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

  return await accountStore.loadReceivable(params)
}

// 加载统计信息
const loadSummary = async () => {
  await accountStore.loadSummary()
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
    const response = await loadReceivableList(isRefresh)

    if (isRefresh) {
      refreshing.value = false
    } else {
      loading.value = false
    }

    // 检查是否加载完毕
    const currentLength = receivableList.value.length
    const total = response?.data?.total || 0

    if (currentLength >= total || currentLength === 0) {
      finished.value = true
    } else {
      pagination.value.page++
    }
  } catch (error) {
    console.error('加载应收账款列表失败:', error)
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

// 加载客户选项
const loadCustomerOptions = async () => {
  try {
    const response = await customerStore.loadList({ page: 1, page_size: 1000 })

    let customerList = []
    if (response && response.list) {
      customerList = response.list
    } else if (response && response.data && response.data.list) {
      customerList = response.data.list
    } else if (Array.isArray(response)) {
      customerList = response
    }

    // 转换格式为 { text: name, value: id }
    customerOptions.value = [
      { text: '全部客户', value: '' },
      ...customerList.map(item => ({
        text: item.name,
        value: item.id
      }))
    ]
  } catch (error) {
    console.error('加载客户列表失败:', error)
    showToast('加载客户列表失败')
  }
}

// 初始化加载
onMounted(async () => {
  // 加载客户选项
  await loadCustomerOptions()
  
  // 加载统计信息
  await loadSummary()
})
</script>

<style scoped lang="scss">
.account-receivable-container {
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

/* 统计区域样式 */
.summary-section {
  background: white;
  padding: 16px 12px;
  margin: 8px 0;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.summary-item {
  text-align: center;
  padding: 8px;
  border-right: 1px solid #f0f0f0;
}

.summary-item:last-child {
  border-right: none;
}

.summary-label {
  font-size: 12px;
  color: #969799;
  margin-bottom: 4px;
}

.summary-value {
  font-size: 16px;
  font-weight: bold;
}

.summary-value.total {
  color: #1989fa;
}

.summary-value.received {
  color: #07c160;
}

.summary-value.unpaid {
  color: #ee0a24;
}

/* 列表区域样式 */
.list-section {
  background: white;
  min-height: 60vh;
}

.empty-state {
  padding: 40px 20px;
}

.receivable-item {
  margin: 12px;
  border: 1px solid #ebedf0;
  border-radius: 8px;
  overflow: hidden;
  background: white;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.receivable-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: #fafafa;
  border-bottom: 1px solid #f0f0f0;
}

.order-no {
  font-weight: bold;
  color: #323233;
  font-size: 14px;
}

.receivable-content {
  padding: 8px;
}

.receivable-info {
  margin-bottom: 12px;
}

.info-item {
  display: flex;
  margin-bottom: 6px;
  font-size: 13px;
  align-items: center;
}

.info-item .label {
  color: #969799;
  min-width: 70px;
}

.info-item .value {
  color: #323233;
  flex: 1;
}

.info-item .amount {
  color: #1989fa;
  font-weight: bold;
}

.info-item .received {
  color: #07c160;
}

.info-item .unpaid {
  color: #ee0a24;
  font-weight: bold;
}

.info-item .time {
  color: #646566;
}

:deep(.van-button--mini) {
  height: 24px;
  padding: 0 8px;
  font-size: 11px;
  margin-left: 8px;
}

/* 结算对话框样式 */
.settle-dialog-content {
  padding: 16px;
}

.settle-hint {
  font-size: 12px;
  color: #969799;
}
</style>