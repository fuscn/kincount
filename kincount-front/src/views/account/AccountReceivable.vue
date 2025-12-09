<template>
  <div class="account-receivable-container">
    <!-- 导航栏 -->
    <van-nav-bar title="应收账款管理" left-text="返回" left-arrow @click-left="$router.back()" />

    <!-- 筛选区域 -->
    <div class="filter-wrapper">
      <!-- 状态标签筛选 -->
      <van-tabs v-model="activeStatus" @change="handleStatusChange" swipeable>
        <van-tab title="全部" name="" />
        <van-tab title="未结清" name="1" />
        <van-tab title="已结清" name="2" />
      </van-tabs>

      <!-- 搜索与高级筛选 -->
      <div class="search-filter">
        <van-search v-model="searchParams.keyword" placeholder="搜索客户名称、相关单号" @search="handleSearch"
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

    <!-- 统计卡片 -->
    <div class="statistics-cards" v-if="statistics.total_amount > 0">
      <van-row gutter="8">
        <van-col span="12">
          <div class="stat-card primary">
            <div class="stat-title">应收总额</div>
            <div class="stat-value">¥{{ formatPrice(statistics.total_amount) }}</div>
          </div>
        </van-col>
        <van-col span="12">
          <div class="stat-card warning">
            <div class="stat-title">未结清</div>
            <div class="stat-value">¥{{ formatPrice(statistics.unpaid_amount) }}</div>
          </div>
        </van-col>
      </van-row>
    </div>

    <!-- 列表区域 -->
    <div class="list-section">
      <van-pull-refresh v-model="refreshing" @refresh="onRefresh">
        <van-list 
          v-model:loading="loading" 
          :finished="finished" 
          :finished-text="accountList.length > 0 ? '没有更多了' : ''"
          :immediate-check="false" 
          @load="onLoad"
        >
          <!-- 空状态 -->
          <div v-if="accountList.length === 0 && !loading && !refreshing" class="empty-state">
            <van-empty image="search" description="暂无应收账款数据" />
          </div>

          <!-- 应收账款列表 -->
          <div v-for="item in accountList" :key="item.id" class="account-item">
            <div class="account-header">
              <div class="target-info">
                <div class="target-name">{{ item.target_name || '未知' }}</div>
                <!-- 根据 target_type 显示不同标签 -->
                <van-tag 
                  size="mini" 
                  :type="item.target_type === 'customer' ? 'primary' : 'warning'"
                >
                  {{ getTargetTypeText(item.target_type) }}
                </van-tag>
              </div>
              <van-tag :type="getStatusTagType(item.status)" size="small">
                {{ getStatusText(item.status) }}
              </van-tag>
            </div>

            <div class="account-content">
              <div class="info-line">
                <span class="label">业务类型：</span>
                <span class="value">{{ getRelatedTypeText(item.related_type) }}</span>
              </div>

              <div class="info-line">
                <span class="label">应收金额：</span>
                <span class="value amount">¥{{ formatPrice(item.amount) }}</span>
                <span class="label">已收：</span>
                <span class="value paid">¥{{ formatPrice(item.paid_amount) }}</span>
              </div>

              <div class="info-line">
                <span class="label">余额：</span>
                <span :class="['value balance', getBalanceClass(item.balance_amount)]">
                  ¥{{ formatPrice(item.balance_amount) }}
                </span>
                <span class="label">到期日：</span>
                <span :class="['value due-date', getDueDateClass(item.due_date)]">
                  {{ formatDate(item.due_date) || '--' }}
                </span>
              </div>


              <div class="info-line">
                <span class="label">创建时间：</span>
                <span class="value time">{{ formatDateTime(item.created_at) }}</span>
              </div>

              <!-- 操作按钮 - 只在未结清且余额大于0时显示收款按钮 -->
              <div class="action-buttons" v-if="showReceiveButton(item)">
                <van-button size="small" type="primary"
                  @click="handleReceive(item)" :loading="receiveLoading[item.id]">
                  收款
                </van-button>
              </div>
            </div>
          </div>
        </van-list>
      </van-pull-refresh>
    </div>

    <!-- 收款弹窗 -->
    <van-dialog v-model:show="showReceiveDialog" :title="getReceiveDialogTitle(currentReceiveItem)" show-cancel-button @confirm="handleReceiveConfirm">
      <div class="receive-dialog-content" v-if="currentReceiveItem">
        <van-cell-group>
          <!-- 根据 target_type 显示不同的标题 -->
          <van-cell 
            :title="getTargetTypeText(currentReceiveItem.target_type)" 
            :value="currentReceiveItem.target_name" 
          />
          <van-cell title="应收金额" :value="'¥' + formatPrice(currentReceiveItem.amount)" />
          <van-cell title="已收金额" :value="'¥' + formatPrice(currentReceiveItem.paid_amount)" />
          <van-cell title="应收余额" :value="'¥' + formatPrice(currentReceiveItem.balance_amount)" />
        </van-cell-group>

        <div class="form-section">
          <van-field v-model="receiveData.amount" label="本次收款金额" type="number" placeholder="请输入金额"
            :rules="[{ required: true, message: '请输入金额' }]" />
          
          <van-field :model-value="paymentMethodText" is-link readonly label="收款方式" placeholder="请选择收款方式"
            @click="showPaymentMethodPicker = true" />
          
          <van-field v-model="receiveData.remark" label="备注" type="textarea" placeholder="请输入备注信息" rows="2" />
        </div>
      </div>
    </van-dialog>

    <!-- 收款方式选择器 -->
    <van-popup v-model:show="showPaymentMethodPicker" position="bottom" round>
      <van-picker :columns="paymentMethodOptions" :default-index="defaultPaymentMethodIndex" 
        @confirm="onPaymentMethodConfirm" @cancel="showPaymentMethodPicker = false" />
    </van-popup>

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
import { ref, onMounted, computed } from 'vue'
import { useAccountStore } from '@/store/modules/account'
import { useCustomerStore } from '@/store/modules/customer'
import { showConfirmDialog, showToast, showDialog } from 'vant'
import { useRouter } from 'vue-router'
import dayjs from 'dayjs'

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
const accountList = ref([])
const statistics = ref({
  total_amount: 0,
  unpaid_amount: 0
})

// 分页参数
const pagination = ref({
  page: 1,
  page_size: 20
})

// 防止重复请求的锁
const isLoadingLocked = ref(false)

// 收款相关状态
const showReceiveDialog = ref(false)
const currentReceiveItem = ref(null)
const receiveData = ref({
  amount: '',
  payment_method: '',
  remark: ''
})

// 收款方式
const showPaymentMethodPicker = ref(false)
const paymentMethodOptions = [
  { text: '现金', value: 'cash' },
  { text: '银行转账', value: 'bank_transfer' },
  { text: '支付宝', value: 'alipay' },
  { text: '微信支付', value: 'wechat_pay' },
  { text: '其他', value: 'other' },
]

// 默认收款方式索引 - 设置为微信支付（索引3）
const defaultPaymentMethodIndex = ref(3)

// 计算属性：收款方式显示文本
const paymentMethodText = computed(() => {
  const method = receiveData.value.payment_method
  const map = {
    'cash': '现金',
    'bank_transfer': '银行转账',
    'alipay': '支付宝',
    'wechat_pay': '微信支付',
    'other': '其他'
  }
  return map[method] || '请选择'
})

// 加载状态记录
const receiveLoading = ref({})

// 关联类型映射
const relatedTypeMap = {
  'sale': '销售订单',
  'sale_return': '销售退货',
  'purchase_return': '采购退货'
}

// 目标类型文本映射
const targetTypeMap = {
  'customer': '客户',
  'supplier': '供应商'
}

// 检查是否显示收款按钮
const showReceiveButton = (item) => {
  return item.status === 1 && item.balance_amount > 0
}

// 格式化金额
const formatPrice = (price) => {
  if (price === null || price === undefined || price === '') return '0.00'
  const num = Number(price)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

// 格式化日期
const formatDate = (dateString) => {
  if (!dateString) return ''
  try {
    return dayjs(dateString).format('YYYY-MM-DD')
  } catch (e) {
    return dateString
  }
}

// 格式化日期时间
const formatDateTime = (dateString) => {
  if (!dateString) return ''
  try {
    return dayjs(dateString).format('YYYY-MM-DD HH:mm')
  } catch (e) {
    return dateString
  }
}

// 状态文本映射
const getStatusText = (status) => {
  return status === 1 ? '未结清' : '已结清'
}

// 状态标签类型
const getStatusTagType = (status) => {
  return status === 1 ? 'danger' : 'success'
}

// 关联类型文本
const getRelatedTypeText = (type) => {
  return relatedTypeMap[type] || type || '未知'
}

// 获取目标类型文本
const getTargetTypeText = (type) => {
  return targetTypeMap[type] || type || '未知'
}

// 获取收款弹窗标题
const getReceiveDialogTitle = (item) => {
  if (!item) return '收款'
  const typeText = getTargetTypeText(item.target_type)
  return `${typeText}收款`
}

// 余额样式
const getBalanceClass = (balance) => {
  return balance > 0 ? 'positive' : 'neutral'
}

// 到期日样式
const getDueDateClass = (dueDate) => {
  if (!dueDate) return 'normal'
  try {
    const today = dayjs()
    const due = dayjs(dueDate)
    const diffDays = due.diff(today, 'day')
    
    if (diffDays < 0) return 'overdue'
    if (diffDays <= 7) return 'warning'
    return 'normal'
  } catch (e) {
    return 'normal'
  }
}

// 收款操作
const handleReceive = (item) => {
  currentReceiveItem.value = item
  receiveData.value = {
    amount: item.balance_amount > 0 ? item.balance_amount.toFixed(2) : '',
    payment_method: 'wechat_pay', // 改为微信支付作为默认
    remark: `${getTargetTypeText(item.target_type)}${item.target_name}收款`
  }
  showReceiveDialog.value = true
}

// 收款方式选择确认
const onPaymentMethodConfirm = ({ selectedOptions }) => {
  receiveData.value.payment_method = selectedOptions[0].value
  showPaymentMethodPicker.value = false
}

// 收款确认
const handleReceiveConfirm = async () => {
  if (!currentReceiveItem.value) return
  
  const amount = Number(receiveData.value.amount)
  if (!amount || amount <= 0) {
    showToast('请输入有效金额')
    return
  }
  
  if (amount > currentReceiveItem.value.balance_amount) {
    showToast('收款金额不能超过应收余额')
    return
  }
  
  if (!receiveData.value.payment_method) {
    showToast('请选择收款方式')
    return
  }

  const itemId = currentReceiveItem.value.id
  receiveLoading.value[itemId] = true
  
  try {
    // 调用支付接口进行收款
    const response = await accountStore.payRecord(
      itemId, 
      amount,
      {
        payment_method: receiveData.value.payment_method,
        remark: receiveData.value.remark,
        is_payment: false // 对于应收账款，收款是收入
      }
    )
    
    if (response.code === 200) {
      showToast('收款成功')
      showReceiveDialog.value = false
      // 刷新列表
      onRefresh()
    } else {
      showToast(response.msg || '收款失败')
    }
  } catch (error) {
    console.error('收款失败:', error)
    showToast('收款失败')
  } finally {
    receiveLoading.value[itemId] = false
  }
}

// 状态标签变化
const handleStatusChange = (name) => {
  searchParams.value.status = name
  handleSearch()
}

// 筛选条件变化
const handleFilterChange = () => {
  handleSearch()
}

// 搜索处理
const handleSearch = () => {
  pagination.value.page = 1
  finished.value = false
  accountList.value = []
  
  // 手动触发加载，而不是通过van-list
  loadData(true)
}

// 清除搜索
const handleClearSearch = () => {
  searchParams.value.keyword = ''
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

// 下拉刷新
const onRefresh = () => {
  pagination.value.page = 1
  finished.value = false
  loadData(true)
}

// van-list的加载事件
const onLoad = () => {
  // 只有在不是搜索操作时才触发滚动加载
  if (!isLoadingLocked.value) {
    loadData(false)
  }
}

// 统一的数据加载函数
const loadData = async (isRefresh = false) => {
  // 防止重复请求
  if (isLoadingLocked.value) return
  
  isLoadingLocked.value = true

  if (isRefresh) {
    refreshing.value = true
  } else {
    loading.value = true
  }

  try {
    const params = {
      ...pagination.value,
      ...searchParams.value,
    }

    // 清理空参数
    Object.keys(params).forEach(key => {
      if (params[key] === '') delete params[key]
    })

    // 调用专门的应收账款接口
    const response = await accountStore.loadReceivable(params)
    
    if (response.code === 200) {
      const responseData = response.data || {}
      let dataList = responseData.list || responseData.data || []
      
      if (isRefresh) {
        accountList.value = dataList
      } else {
        const existingIds = accountList.value.map(item => item.id)
        const newItems = dataList.filter(item => !existingIds.includes(item.id))
        accountList.value = [...accountList.value, ...newItems]
      }
      
      // 计算统计信息
      calculateStatistics(accountList.value)
      
      // 检查是否加载完毕
      const hasMore = checkHasMore(responseData, dataList.length)
      finished.value = !hasMore
      
      if (hasMore) {
        pagination.value.page++
      }
    } else {
      showToast(response.msg || '加载失败')
      finished.value = true
    }
  } catch (error) {
    console.error('加载失败:', error)
    showToast('加载失败')
    finished.value = true
  } finally {
    if (isRefresh) {
      refreshing.value = false
    } else {
      loading.value = false
    }
    isLoadingLocked.value = false
  }
}

// 检查是否有更多数据
const checkHasMore = (responseData, currentLength) => {
  if (!responseData) return false
  
  const perPage = responseData.page_size || pagination.value.page_size
  const total = responseData.total || 0
  const currentPage = responseData.page || pagination.value.page
  
  // 如果没有数据或已经加载完所有数据
  if (total === 0 || currentLength === 0) return false
  
  // 如果当前页面数据少于每页数量，说明没有更多数据了
  if (currentLength < perPage) return false
  
  // 如果已经加载的数量小于总数，说明还有更多数据
  return (currentPage * perPage) < total
}

// 计算统计信息
const calculateStatistics = (data) => {
  let total = 0
  let unpaid = 0
  
  data.forEach(item => {
    total += Math.abs(Number(item.amount) || 0)
    if (item.status === 1) {
      unpaid += Math.abs(Number(item.balance_amount) || 0)
    }
  })
  
  statistics.value = {
    total_amount: total,
    unpaid_amount: unpaid
  }
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
  // 页面初始化时加载数据
  loadData(true)
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

/* 统计卡片样式 */
.statistics-cards {
  padding: 12px;
}

.stat-card {
  background: white;
  border-radius: 8px;
  padding: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  text-align: center;

  &.primary {
    border-left: 4px solid #1989fa;
  }

  &.warning {
    border-left: 4px solid #ff976a;
  }
}

.stat-title {
  font-size: 13px;
  color: #969799;
  margin-bottom: 8px;
}

.stat-value {
  font-size: 20px;
  font-weight: bold;
  color: #323233;
}

/* 列表区域样式 */
.list-section {
  background: white;
  min-height: 60vh;
}

.empty-state {
  padding: 40px 20px;
}

.account-item {
  margin: 12px;
  border: 1px solid #ebedf0;
  border-radius: 8px;
  overflow: hidden;
  background: white;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.account-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: #fafafa;
  border-bottom: 1px solid #f0f0f0;
}

.target-info {
  display: flex;
  align-items: center;
  gap: 8px;
}

.target-name {
  font-weight: bold;
  color: #323233;
  font-size: 14px;
}

.account-content {
  padding: 12px;
}

.info-line {
  display: flex;
  align-items: center;
  margin-bottom: 8px;
  font-size: 13px;
  flex-wrap: wrap;
}

.info-line .label {
  color: #969799;
  min-width: 70px;
  flex-shrink: 0;
}

.info-line .value {
  color: #323233;
  margin-right: 16px;
  flex: 1;
  min-width: 60px;
}

.info-line .amount {
  color: #1989fa;
  font-weight: bold;
}

.info-line .paid {
  color: #07c160;
}

.info-line .balance.positive {
  color: #ee0a24;
  font-weight: bold;
}

.info-line .balance.neutral {
  color: #969799;
}

.info-line .due-date.overdue {
  color: #ee0a24;
  font-weight: bold;
}

.info-line .due-date.warning {
  color: #ff976a;
}

.info-line .due-date.normal {
  color: #07c160;
}

.info-line .remark {
  color: #646566;
  flex: 1;
  word-break: break-all;
}

.info-line .time {
  color: #969799;
}

.action-buttons {
  display: flex;
  gap: 8px;
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px dashed #f0f0f0;
}

:deep(.van-button--small) {
  height: 28px;
  font-size: 12px;
}

/* 收款弹窗样式 */
.receive-dialog-content {
  padding: 20px 16px;
}

.form-section {
  margin-top: 16px;
}

/* 移动端适配 */
@media (max-width: 375px) {
  .account-item {
    margin: 8px;
  }
  
  .info-line {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .info-line .label {
    margin-bottom: 2px;
  }
  
  .info-line .value {
    margin-right: 0;
    margin-bottom: 4px;
  }
  
  .action-buttons {
    flex-direction: column;
  }
  
  :deep(.van-button--small) {
    width: 100%;
    margin-bottom: 4px;
  }
}
</style>