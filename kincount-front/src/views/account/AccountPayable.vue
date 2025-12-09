<template>
  <div class="account-payable-container">
    <!-- 导航栏 -->
    <van-nav-bar title="应付账款管理" left-text="返回" left-arrow @click-left="$router.back()" />

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
        <van-search v-model="searchParams.keyword" placeholder="搜索供应商/客户名称" @search="handleSearch"
          @clear="handleClearSearch" />
        <van-dropdown-menu>
          <!-- 账款对象筛选 -->
          <van-dropdown-item v-model="searchParams.target_type" :options="targetTypeOptions" placeholder="选择对象类型"
            @change="handleFilterChange" />
          <!-- 供应商筛选 -->
          <van-dropdown-item v-model="searchParams.supplier_id" :options="supplierOptions" placeholder="选择供应商"
            @change="handleFilterChange" />
        </van-dropdown-menu>
      </div>
    </div>

    <!-- 统计卡片 -->
    <div class="statistics-cards" v-if="statistics.total_amount > 0">
      <van-row gutter="8">
        <van-col span="12">
          <div class="stat-card primary">
            <div class="stat-title">应付总额</div>
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
          @load="onLoad"
        >
          <!-- 空状态 -->
          <div v-if="accountList.length === 0 && !loading && !refreshing" class="empty-state">
            <van-empty image="search" description="暂无应付账款数据" />
          </div>

          <!-- 应付账款列表 -->
          <div v-for="item in accountList" :key="item.id" class="account-item">
            <div class="account-header">
              <div class="target-info">
                <div class="target-name">{{ getTargetName(item) }}</div>
                <van-tag size="mini" :type="item.target_type === 'customer' ? 'primary' : 'warning'">
                  {{ getTargetTypeLabel(item.target_type) }}
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
                <span class="label">应付金额：</span>
                <span class="value amount">¥{{ formatPrice(item.amount) }}</span>
                <span class="label">已付：</span>
                <span class="value paid">¥{{ formatPrice(item.paid_amount) }}</span>
              </div>

              <div class="info-line">
                <span class="label">余额：</span>
                <span :class="['value balance', getBalanceClass(item.balance_amount)]">
                  ¥{{ formatPrice(Math.abs(item.balance_amount)) }}
                </span>
                <span class="label">到期日：</span>
                <span :class="['value due-date', getDueDateClass(item.due_date)]">
                  {{ formatDate(item.due_date) || '--' }}
                </span>
              </div>

              <div class="info-line" v-if="item.remark">
                <span class="label">备注：</span>
                <span class="value remark">{{ item.remark }}</span>
              </div>

              <div class="info-line">
                <span class="label">创建时间：</span>
                <span class="value time">{{ formatDateTime(item.created_at) }}</span>
              </div>

              <!-- 操作按钮 -->
              <div class="action-buttons" v-if="item.status === 1">
                <van-button v-if="item.balance_amount > 0" size="small" type="primary"
                  @click="handlePay(item)" :loading="payLoading[item.id]">
                  付款
                </van-button>
                <van-button v-if="item.balance_amount < 0" size="small" type="warning"
                  @click="handleRefund(item)" :loading="refundLoading[item.id]">
                  收取退款
                </van-button>
                <van-button size="small" type="default" @click="handleViewDetail(item)">
                  详情
                </van-button>
              </div>
            </div>
          </div>
        </van-list>
      </van-pull-refresh>
    </div>

    <!-- 付款弹窗 -->
    <van-dialog v-model:show="showPayDialog" :title="currentPayItem?.balance_amount > 0 ? '付款' : '收取退款'" show-cancel-button @confirm="handlePayConfirm">
      <div class="pay-dialog-content" v-if="currentPayItem">
        <van-cell-group>
          <van-cell :title="getTargetTypeLabel(currentPayItem.target_type)" :value="getTargetName(currentPayItem)" />
          <van-cell title="应付金额" :value="'¥' + formatPrice(currentPayItem.amount)" />
          <van-cell title="已付金额" :value="'¥' + formatPrice(currentPayItem.paid_amount)" />
          <van-cell title="应付余额" :value="'¥' + formatPrice(Math.abs(currentPayItem.balance_amount))" />
        </van-cell-group>

        <div class="form-section">
          <van-field v-model="payData.amount" label="本次金额" type="number" placeholder="请输入金额"
            :rules="[{ required: true, message: '请输入金额' }]" />
          
          <van-field :model-value="paymentMethodText" is-link readonly label="支付方式" placeholder="请选择支付方式"
            @click="showPaymentMethodPicker = true" />
          
          <van-field v-model="payData.remark" label="备注" type="textarea" placeholder="请输入备注信息" rows="2" />
        </div>
      </div>
    </van-dialog>

    <!-- 支付方式选择器 -->
    <van-popup v-model:show="showPaymentMethodPicker" position="bottom" round>
      <van-picker :columns="paymentMethodOptions" :default-index="defaultPaymentMethodIndex" @confirm="onPaymentMethodConfirm" @cancel="showPaymentMethodPicker = false" />
    </van-popup>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useAccountStore } from '@/store/modules/account'
import { useSupplierStore } from '@/store/modules/supplier'
import { useCustomerStore } from '@/store/modules/customer'
import { showConfirmDialog, showToast, showDialog } from 'vant'
import { useRouter } from 'vue-router'
import dayjs from 'dayjs'

const accountStore = useAccountStore()
const supplierStore = useSupplierStore()
const customerStore = useCustomerStore()
const router = useRouter()

// 激活的状态标签
const activeStatus = ref('')

// 搜索参数
const searchParams = ref({
  keyword: '',
  target_type: '',
  supplier_id: '',
  status: '',
})

// 对象类型选项
const targetTypeOptions = ref([
  { text: '全部类型', value: '' },
  { text: '供应商', value: 'supplier' },
  { text: '客户', value: 'customer' },
])

// 供应商选项
const supplierOptions = ref([
  { text: '全部供应商', value: '' },
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

// 付款相关状态
const showPayDialog = ref(false)
const currentPayItem = ref(null)
const payData = ref({
  amount: '',
  payment_method: '',
  remark: ''
})

// 支付方式
const showPaymentMethodPicker = ref(false)
const paymentMethodOptions = [
  { text: '现金', value: 'cash' },
  { text: '银行转账', value: 'bank_transfer' },
  { text: '支付宝', value: 'alipay' },
  { text: '微信支付', value: 'wechat_pay' },
  { text: '其他', value: 'other' },
]

// 默认支付方式索引
const defaultPaymentMethodIndex = ref(3)

// 计算属性：支付方式显示文本
const paymentMethodText = computed(() => {
  const method = payData.value.payment_method
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
const payLoading = ref({})
const refundLoading = ref({})

// 对象类型映射
const targetTypeMap = {
  'customer': '客户',
  'supplier': '供应商'
}

// 关联类型映射
const relatedTypeMap = {
  'purchase': '采购订单',
  'sale_return': '销售退货',
  'purchase_return': '采购退货'
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

// 对象类型文本
const getTargetTypeLabel = (type) => {
  return targetTypeMap[type] || type || '对方'
}

// 获取目标名称
const getTargetName = (item) => {
  if (item.supplier_name) return item.supplier_name
  if (item.customer_name) return item.customer_name
  if (item.target_name) return item.target_name
  return item.target_type === 'supplier' ? '未知供应商' : '未知客户'
}

// 关联类型文本
const getRelatedTypeText = (type) => {
  return relatedTypeMap[type] || type || '未知'
}

// 余额样式
const getBalanceClass = (balance) => {
  if (balance > 0) return 'positive'
  if (balance < 0) return 'negative'
  return 'neutral'
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

// 查看详情
const handleViewDetail = (item) => {
  router.push(`/account/payable/detail/${item.id}`)
}

// 付款操作
const handlePay = (item) => {
  currentPayItem.value = item
  payData.value = {
    amount: Math.abs(item.balance_amount).toFixed(2),
    payment_method: 'wechat_pay',
    remark: item.balance_amount > 0 
      ? `${getTargetTypeLabel(item.target_type)}付款` 
      : '收取退款'
  }
  showPayDialog.value = true
}

// 退款操作
const handleRefund = (item) => {
  handlePay(item)
}

// 支付方式选择确认
const onPaymentMethodConfirm = ({ selectedOptions }) => {
  payData.value.payment_method = selectedOptions[0].value
  showPaymentMethodPicker.value = false
}

// 付款确认
const handlePayConfirm = async () => {
  if (!currentPayItem.value) return
  
  const amount = Number(payData.value.amount)
  if (!amount || amount <= 0) {
    showToast('请输入有效金额')
    return
  }
  
  if (!payData.value.payment_method) {
    showToast('请选择支付方式')
    return
  }

  const itemId = currentPayItem.value.id
  const isPayment = currentPayItem.value.balance_amount > 0
  
  if (isPayment) {
    payLoading.value[itemId] = true
  } else {
    refundLoading.value[itemId] = true
  }
  
  try {
    const response = await accountStore.payRecord(
      itemId, 
      amount,
      {
        payment_method: payData.value.payment_method,
        remark: payData.value.remark,
        is_payment: isPayment
      }
    )
    
    if (response.code === 200) {
      showToast('操作成功')
      showPayDialog.value = false
      onRefresh()
    } else {
      showToast(response.msg || '操作失败')
    }
  } catch (error) {
    console.error('操作失败:', error)
    showToast('操作失败')
  } finally {
    if (isPayment) {
      payLoading.value[itemId] = false
    } else {
      refundLoading.value[itemId] = false
    }
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
  
  // 手动触发加载
  if (!isLoadingLocked.value) {
    loadData(true)
  }
}

// 清除搜索
const handleClearSearch = () => {
  searchParams.value.keyword = ''
  handleSearch()
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

    Object.keys(params).forEach(key => {
      if (params[key] === '') delete params[key]
    })

    const response = await accountStore.loadPayable(params)
    
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
      
      calculateStatistics(accountList.value)
      
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
  
  if (total === 0 || currentLength === 0) return false
  
  if (currentLength < perPage) return false
  
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

// 加载供应商选项
const loadSupplierOptions = async () => {
  try {
    const response = await supplierStore.loadList({ page: 1, page_size: 20 })

    let supplierList = []
    if (response && response.list) {
      supplierList = response.list
    } else if (response && response.data && response.data.list) {
      supplierList = response.data.list
    } else if (Array.isArray(response)) {
      supplierList = response
    }

    supplierOptions.value = [
      { text: '全部供应商', value: '' },
      ...supplierList.map(item => ({
        text: item.name,
        value: item.id
      }))
    ]
  } catch (error) {
    console.error('加载供应商列表失败:', error)
    showToast('加载供应商列表失败')
  }
}

// 初始化加载 - 只加载供应商选项，不加载数据
onMounted(async () => {
  // 加载供应商选项
  await loadSupplierOptions()
  
  // 注意：这里不再手动调用 loadData
  // van-list 组件会在初始化时自动触发一次 @load 事件
})
</script>

<style scoped lang="scss">
.account-payable-container {
  padding: 0;
  background-color: #f7f8fa;
  min-height: 100vh;
}

:deep(.van-nav-bar) {
  background: #fff;
  position: sticky;
  top: 0;
  z-index: 1000;
}

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
  color: #ee0a24;
  font-weight: bold;
}

.info-line .paid {
  color: #07c160;
}

.info-line .balance.positive {
  color: #ee0a24;
  font-weight: bold;
}

.info-line .balance.negative {
  color: #07c160;
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

.pay-dialog-content {
  padding: 20px 16px;
}

.form-section {
  margin-top: 16px;
}

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