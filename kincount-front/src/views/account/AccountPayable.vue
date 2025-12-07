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
        <van-search v-model="searchParams.keyword" placeholder="搜索客户/供应商名称、联系人、备注" @search="handleSearch"
          @clear="handleClearSearch" />
        <van-dropdown-menu>
          <!-- 账款对象筛选 -->
          <van-dropdown-item v-model="searchParams.target_type" :options="targetTypeOptions" placeholder="选择对象类型"
            @change="handleTargetTypeChange" />
          <!-- 关联类型筛选 -->
          <van-dropdown-item v-model="searchParams.related_type" :options="relatedTypeOptions" placeholder="关联类型"
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
        <van-list v-model:loading="loading" :finished="finished" :finished-text="accountList.length > 0 ? '没有更多了' : ''"
          @load="onLoad">
          <!-- 空状态 -->
          <div v-if="accountList.length === 0 && !loading" class="empty-state">
            <van-empty image="search" description="暂无应付账款数据" />
          </div>

          <!-- 应付账款列表 -->
          <div v-for="item in accountList" :key="item.id" class="account-item">
            <div class="account-header">
              <span class="target-name">{{ item.target_name || getTargetTypeLabel(item.target_type) }}</span>
              <van-tag :type="getStatusTagType(item.status)" size="small">
                {{ getStatusText(item.status) }}
              </van-tag>
            </div>
            
            <!-- 对象类型标签 -->
            <div class="target-type-tag">
              <van-tag size="mini" :type="item.target_type === 'customer' ? 'primary' : 'warning'">
                {{ getTargetTypeLabel(item.target_type) }}
              </van-tag>
              <span class="related-type">{{ item.related_type_label || getRelatedTypeText(item.related_type) }}</span>
            </div>

            <div class="account-content">
              <div class="account-info">
                <div class="info-item">
                  <span class="label">联系人：</span>
                  <span class="value">{{ item.contact_person || '--' }}</span>
                  <span class="label">电话：</span>
                  <span class="value">{{ item.phone || '--' }}</span>
                </div>

                <div class="info-item">
                  <span class="label">应付金额：</span>
                  <span class="value amount">¥{{ formatPrice(item.amount) }}</span>
                  <span class="label">已付金额：</span>
                  <span class="value paid">¥{{ formatPrice(item.paid_amount) }}</span>
                </div>

                <div class="info-item">
                  <span class="label">余额：</span>
                  <span :class="['value balance', getBalanceClass(item.balance_amount)]">
                    ¥{{ formatPrice(Math.abs(item.balance_amount)) }}
                  </span>
                  <span class="label">到期日：</span>
                  <span :class="['value due-date', getDueDateClass(item.due_date)]">
                    {{ formatDate(item.due_date) || '--' }}
                  </span>
                </div>

                <div class="info-item">
                  <span class="label">创建时间：</span>
                  <span class="value time">{{ formatDateTime(item.created_at) }}</span>
                </div>

                <!-- 操作按钮 -->
                <div class="action-buttons" v-if="item.status === 1">
                  <van-button v-if="item.balance_amount > 0" size="mini" type="primary"
                    @click="handlePay(item)" :loading="payLoading[item.id]">
                    {{ getPayButtonText(item) }}
                  </van-button>
                  <van-button v-if="item.balance_amount < 0" size="mini" type="warning"
                    @click="handleRefund(item)" :loading="refundLoading[item.id]">
                    {{ getRefundButtonText(item) }}
                  </van-button>
                  <van-button size="mini" type="default" @click="handleViewSettlement(item)">
                    核销记录
                  </van-button>
                  <van-button size="mini" type="default" @click="handleViewDetail(item)">
                    查看详情
                  </van-button>
                </div>

                <div class="info-item remark" v-if="item.remark">
                  <span class="label">备注：</span>
                  <span class="value">{{ item.remark }}</span>
                </div>
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

    <!-- 付款弹窗 -->
    <van-dialog v-model:show="showPayDialog" :title="getPayDialogTitle(currentPayItem)" show-cancel-button :before-close="handlePayConfirm">
      <div class="pay-dialog-content" v-if="currentPayItem">
        <van-cell-group>
          <van-cell :title="getTargetTypeLabel(currentPayItem.target_type)" :value="currentPayItem.target_name" />
          <van-cell title="应付金额" :value="'¥' + formatPrice(currentPayItem.amount)" />
          <van-cell title="已付金额" :value="'¥' + formatPrice(currentPayItem.paid_amount)" />
          <van-cell title="应付余额" :value="'¥' + formatPrice(Math.abs(currentPayItem.balance_amount))" />
          <van-cell title="关联类型" :value="currentPayItem.related_type_label || getRelatedTypeText(currentPayItem.related_type)" />
        </van-cell-group>

        <div class="form-section">
          <van-field v-model="payData.amount" label="本次付款" type="number" placeholder="请输入付款金额"
            :rules="[{ required: true, message: '请输入付款金额' }]" 
            @input="validatePayAmount"
            :error-message="payAmountError" />
          
          <van-field v-model="payData.payment_method" is-link readonly label="支付方式" placeholder="请选择支付方式"
            @click="showPaymentMethodPicker = true" />
          
          <van-field v-model="payData.settlement_date" is-link readonly label="付款日期" placeholder="请选择付款日期"
            @click="showPayDatePicker = true" />
          
          <van-field v-model="payData.remark" label="备注" type="textarea" placeholder="请输入备注信息" rows="2" show-word-limit />
          
          <!-- 核销预览 -->
          <div class="settlement-preview" v-if="payData.amount">
            <van-divider content-position="left">核销预览</van-divider>
            <div class="preview-info">
              <div class="preview-item">
                <span>本次核销：</span>
                <span class="highlight">¥{{ formatPrice(payData.amount) }}</span>
              </div>
              <div class="preview-item">
                <span>支付方式：</span>
                <span>{{ getPaymentMethodText(payData.payment_method) || '--' }}</span>
              </div>
              <div class="preview-item">
                <span>核销后余额：</span>
                <span :class="['highlight', getBalanceClass(currentPayItem.balance_amount - payData.amount)]">
                  ¥{{ formatPrice(Math.abs(currentPayItem.balance_amount - payData.amount)) }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </van-dialog>

    <!-- 支付方式选择器 -->
    <van-popup v-model:show="showPaymentMethodPicker" position="bottom" round>
      <van-picker :columns="paymentMethodOptions" @confirm="onPaymentMethodConfirm" @cancel="showPaymentMethodPicker = false" />
    </van-popup>

    <!-- 付款日期选择器 -->
    <van-popup v-model:show="showPayDatePicker" position="bottom">
      <van-date-picker v-model="payDateValue" @confirm="onPayDateConfirm" @cancel="showPayDatePicker = false" />
    </van-popup>

    <!-- 核销成功提示 -->
    <van-dialog v-model:show="showSuccessDialog" title="支付成功" show-confirm-button :show-cancel-button="false"
      @confirm="handleSuccessConfirm">
      <div class="success-dialog-content">
        <div class="success-icon">
          <van-icon name="checked" color="#07c160" size="60" />
        </div>
        <div class="success-message">
          <p>已成功支付 ¥{{ formatPrice(payResult?.settlement_amount) }}</p>
          <p v-if="payResult?.is_settled">该账款已结清！</p>
          <p v-else>剩余应付余额 ¥{{ formatPrice(payResult?.balance_amount) }}</p>
        </div>
        <div class="success-details">
          <p>核销单号：{{ payResult?.settlement_no || '--' }}</p>
          <p>支付方式：{{ getPaymentMethodText(payResult?.payment_method) || '--' }}</p>
        </div>
      </div>
    </van-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
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
  target_type: '',      // 对象类型: customer, supplier
  target_id: '',        // 对象ID
  related_type: '',
  status: '',
  start_date: '',
  end_date: '',
  due_date: ''
})

// 日期选择相关
const showStartDatePicker = ref(false)
const showEndDatePicker = ref(false)
const startDate = ref([])
const endDate = ref([])
const dateDropdown = ref(null)

// 对象类型选项
const targetTypeOptions = ref([
  { text: '全部类型', value: '' },
  { text: '客户', value: 'customer' },
  { text: '供应商', value: 'supplier' },
])

// 关联类型选项
const relatedTypeOptions = ref([
  { text: '全部类型', value: '' },
  { text: '采购订单', value: 'purchase_order' },
  { text: '采购退货', value: 'purchase_return' },
  { text: '销售退货', value: 'sale_return' },
  { text: '其他应付', value: 'other_payable' },
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

// 付款相关状态
const showPayDialog = ref(false)
const currentPayItem = ref(null)
const payData = ref({
  amount: '',
  payment_method: '',
  settlement_date: '',
  remark: ''
})
const payAmountError = ref('')

// 支付方式
const showPaymentMethodPicker = ref(false)
const paymentMethodOptions = [
  { text: '现金', value: 'cash' },
  { text: '银行转账', value: 'bank_transfer' },
  { text: '支付宝', value: 'alipay' },
  { text: '微信支付', value: 'wechat_pay' },
  { text: '支票', value: 'check' },
  { text: '其他', value: 'other' },
]

// 付款日期选择
const showPayDatePicker = ref(false)
const payDateValue = ref([])

// 成功弹窗
const showSuccessDialog = ref(false)
const payResult = ref(null)

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
  'purchase_order': '采购订单',
  'purchase_return': '采购退货',
  'sale_return': '销售退货',
  'other_payable': '其他应付'
}

// 支付方式映射
const paymentMethodMap = {
  'cash': '现金',
  'bank_transfer': '银行转账',
  'alipay': '支付宝',
  'wechat_pay': '微信支付',
  'check': '支票',
  'other': '其他'
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
  const statusMap = {
    1: '未结清',
    2: '已结清'
  }
  return statusMap[status] || '未知'
}

// 状态标签类型
const getStatusTagType = (status) => {
  const typeMap = {
    1: 'danger',   // 未结清 - 危险色
    2: 'success'   // 已结清 - 成功色
  }
  return typeMap[status] || 'default'
}

// 对象类型文本
const getTargetTypeLabel = (type) => {
  return targetTypeMap[type] || type || '对方'
}

// 关联类型文本
const getRelatedTypeText = (type) => {
  return relatedTypeMap[type] || type || '未知'
}

// 支付方式文本
const getPaymentMethodText = (method) => {
  return paymentMethodMap[method] || method || '--'
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

// 获取付款按钮文本
const getPayButtonText = (item) => {
  if (item.balance_amount < 0) return '收取退款'
  return item.target_type === 'customer' ? '退还款项' : '付款'
}

// 获取退款按钮文本
const getRefundButtonText = (item) => {
  return item.target_type === 'customer' ? '收取退款' : '支付退款'
}

// 获取付款弹窗标题
const getPayDialogTitle = (item) => {
  if (!item) return '应付账款付款'
  
  if (item.balance_amount < 0) {
    // 余额为负表示对方欠我们钱，实际是收款
    return `${getTargetTypeLabel(item.target_type)}退款`
  }
  
  return `${getTargetTypeLabel(item.target_type)}付款`
}

// 查看详情
const handleViewDetail = (item) => {
  router.push(`/account/payable/detail/${item.id}`)
}

// 查看核销记录
const handleViewSettlement = (item) => {
  router.push({
    path: '/account/settlement',
    query: {
      account_id: item.id,
      account_type: 2,
      target_id: item.target_id,
      target_name: item.target_name,
      target_type: item.target_type
    }
  })
}

// 付款操作
const handlePay = (item) => {
  currentPayItem.value = item
  const balanceAmount = Math.abs(item.balance_amount)
  
  let defaultRemark = ''
  let buttonText = ''
  
  if (item.balance_amount > 0) {
    // 应付余额为正，我们需要付款
    buttonText = item.target_type === 'customer' ? '退还款项' : '付款'
    defaultRemark = `${getTargetTypeLabel(item.target_type)}${item.target_name}的应付账款`
  } else {
    // 应付余额为负，实际是对方退款给我们（我们收款）
    buttonText = '收取退款'
    defaultRemark = `收取${getTargetTypeLabel(item.target_type)}${item.target_name}的退款`
  }
  
  payData.value = {
    amount: balanceAmount.toFixed(2),
    payment_method: 'bank_transfer',
    settlement_date: dayjs().format('YYYY-MM-DD'),
    remark: defaultRemark
  }
  payAmountError.value = ''
  showPayDialog.value = true
}

// 校验付款金额
const validatePayAmount = () => {
  if (!currentPayItem.value) return
  
  const payAmount = Number(payData.value.amount)
  const balance = currentPayItem.value.balance_amount
  
  if (payAmount <= 0) {
    payAmountError.value = '金额必须大于0'
  } else if (balance > 0 && payAmount > balance) {
    // 应付余额为正，付款不能超过余额
    payAmountError.value = `金额不能大于应付余额¥${formatPrice(balance)}`
  } else if (balance < 0 && payAmount > Math.abs(balance)) {
    // 应付余额为负（实际是应收），收款不能超过应收金额的绝对值
    payAmountError.value = `金额不能超过应收金额¥${formatPrice(Math.abs(balance))}`
  } else {
    payAmountError.value = ''
  }
}

// 支付方式选择确认
const onPaymentMethodConfirm = ({ selectedOptions }) => {
  payData.value.payment_method = selectedOptions[0].value
  showPaymentMethodPicker.value = false
}

// 付款日期选择确认
const onPayDateConfirm = (value) => {
  if (!value || !value.selectedValues) {
    showToast('日期选择错误')
    return
  }
  
  const year = value.selectedValues[0]
  const month = value.selectedValues[1].toString().padStart(2, '0')
  const day = value.selectedValues[2].toString().padStart(2, '0')
  payData.value.settlement_date = `${year}-${month}-${day}`
  showPayDatePicker.value = false
}

// 付款确认
const handlePayConfirm = (action) => {
  if (action === 'cancel') {
    showPayDialog.value = false
    return
  }

  // 验证必填项
  if (!payData.value.amount || Number(payData.value.amount) <= 0) {
    showToast('请输入有效的金额')
    return false
  }
  
  if (!payData.value.payment_method) {
    showToast('请选择支付方式')
    return false
  }

  const payAmount = Number(payData.value.amount)
  const balance = currentPayItem.value.balance_amount
  
  if (balance > 0 && payAmount > balance) {
    showToast('金额不能大于应付余额')
    return false
  } else if (balance < 0 && payAmount > Math.abs(balance)) {
    showToast('金额不能超过应收金额')
    return false
  }

  // 开始支付
  performPayment()
  return false // 阻止弹窗关闭，等待支付完成
}

// 执行支付
const performPayment = async () => {
  if (!currentPayItem.value) return
  
  const itemId = currentPayItem.value.id
  payLoading.value[itemId] = true
  
  try {
    // 根据余额正负决定是付款还是收款
    const isPayment = currentPayItem.value.balance_amount > 0
    
    // 调用支付接口
    const response = await accountStore.payRecord(
      itemId, 
      Number(payData.value.amount),
      {
        payment_method: payData.value.payment_method,
        settlement_date: payData.value.settlement_date,
        remark: payData.value.remark,
        is_payment: isPayment // 告诉后端这是付款还是收款
      }
    )
    
    if (response.code === 200) {
      // 显示成功弹窗
      payResult.value = {
        settlement_amount: payData.value.amount,
        settlement_no: response.data?.settlement?.settlement_no,
        payment_method: payData.value.payment_method,
        balance_amount: response.data?.balance_amount,
        is_settled: response.data?.is_settled
      }
      
      showPayDialog.value = false
      showSuccessDialog.value = true
      
      // 刷新列表
      onRefresh()
    } else {
      showToast(response.msg || '操作失败')
    }
  } catch (error) {
    console.error('操作失败:', error)
    showToast('操作失败: ' + (error.message || '未知错误'))
  } finally {
    payLoading.value[itemId] = false
  }
}

// 成功确认
const handleSuccessConfirm = () => {
  showSuccessDialog.value = false
  // 可以跳转到核销记录页面
  if (payResult.value?.settlement_no) {
    handleViewSettlement(currentPayItem.value)
  }
}

// 退款操作
const handleRefund = (item) => {
  const targetLabel = getTargetTypeLabel(item.target_type)
  
  showConfirmDialog({
    title: '确认退款',
    message: `确定要对${targetLabel} ${item.target_name} 的退款进行处理吗？`
  }).then(async () => {
    refundLoading.value[item.id] = true
    try {
      // 余额为负，表示对方退款给我们，我们收款
      const refundAmount = Math.abs(item.balance_amount)
      
      const response = await accountStore.payRecord(
        item.id, 
        refundAmount,
        {
          payment_method: 'bank_transfer',
          remark: `收取${targetLabel}${item.target_name}的退款`,
          is_payment: false // 收款
        }
      )
      
      if (response.code === 200) {
        showToast('退款处理成功')
        onRefresh()
      } else {
        showToast(response.msg || '退款处理失败')
      }
    } catch (error) {
      console.error('退款处理失败:', error)
      showToast('退款处理失败: ' + (error.message || '未知错误'))
    } finally {
      refundLoading.value[item.id] = false
    }
  }).catch(() => {
    // 用户取消
  })
}

// 状态标签变化
const handleStatusChange = (name) => {
  searchParams.value.status = name
  handleSearch()
}

// 对象类型变化
const handleTargetTypeChange = () => {
  // 切换对象类型时，重置对象ID
  searchParams.value.target_id = ''
  handleSearch()
}

// 搜索处理
const handleSearch = () => {
  pagination.value.page = 1
  accountList.value = []
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
  accountList.value = []
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
      ...searchParams.value,
      type: 2 // 应付账款类型
    }

    // 清理空参数
    Object.keys(params).forEach(key => {
      if (params[key] === '') {
        delete params[key]
      }
    })

    // 调用API
    const response = await accountStore.loadPayable(params)
    
    if (response.code === 200) {
      // 统一处理：msg字段包含分页数据
      const responseData = response.msg || response.data
      let dataList = []
      
      if (responseData && responseData.data) {
        dataList = responseData.data
      } else if (Array.isArray(responseData)) {
        dataList = responseData
      }
      
      if (isRefresh) {
        accountList.value = dataList
      } else {
        // 去重合并
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
    console.error('加载应付账款列表失败:', error)
    showToast('加载失败: ' + (error.message || '未知错误'))
    finished.value = true
  } finally {
    if (isRefresh) {
      refreshing.value = false
    } else {
      loading.value = false
    }
  }
}

// 检查是否有更多数据
const checkHasMore = (responseData, currentLength) => {
  if (!responseData) return false
  
  const total = responseData.total || 0
  const perPage = responseData.per_page || pagination.value.page_size
  const lastPage = responseData.last_page || 1
  const currentPage = responseData.current_page || pagination.value.page
  
  return currentLength === perPage && currentPage < lastPage
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

// 开始日期选择确认
const onStartDateConfirm = (value) => {
  if (!value || !value.selectedValues) {
    showToast('日期选择错误')
    return
  }
  
  const year = value.selectedValues[0]
  const month = value.selectedValues[1].toString().padStart(2, '0')
  const day = value.selectedValues[2].toString().padStart(2, '0')
  searchParams.value.start_date = `${year}-${month}-${day}`
  showStartDatePicker.value = false
}

// 结束日期选择确认
const onEndDateConfirm = (value) => {
  if (!value || !value.selectedValues) {
    showToast('日期选择错误')
    return
  }
  
  const year = value.selectedValues[0]
  const month = value.selectedValues[1].toString().padStart(2, '0')
  const day = value.selectedValues[2].toString().padStart(2, '0')
  searchParams.value.end_date = `${year}-${month}-${day}`
  showEndDatePicker.value = false
}

// 初始化加载
onMounted(() => {
  // 初始加载数据
  onLoad()
})
</script>

<style scoped lang="scss">
.account-payable-container {
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

.target-name {
  font-weight: bold;
  color: #323233;
  font-size: 14px;
}

.target-type-tag {
  padding: 4px 16px;
  background: #f5f5f5;
  border-bottom: 1px solid #f0f0f0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.related-type {
  font-size: 12px;
  color: #646566;
}

.account-content {
  padding: 8px;
}

.account-info {
  margin-bottom: 12px;
}

.info-item {
  display: flex;
  margin-bottom: 6px;
  font-size: 13px;
  align-items: center;
  flex-wrap: wrap;
}

.info-item .label {
  color: #969799;
  min-width: 70px;
  flex-shrink: 0;
}

.info-item .value {
  color: #323233;
  margin-right: 16px;
  flex: 1;
  min-width: 60px;
}

.info-item .amount {
  color: #ee0a24;
  font-weight: bold;
}

.info-item .paid {
  color: #07c160;
  font-weight: bold;
}

.info-item .balance.positive {
  color: #ee0a24;
  font-weight: bold;
}

.info-item .balance.negative {
  color: #07c160;
  font-weight: bold;
}

.info-item .due-date.overdue {
  color: #ee0a24;
  font-weight: bold;
}

.info-item .due-date.warning {
  color: #ff976a;
  font-weight: bold;
}

.info-item .due-date.normal {
  color: #07c160;
}

.info-item .time {
  color: #646566;
}

.info-item.remark {
  background-color: #f9f9f9;
  padding: 8px;
  border-radius: 4px;
  margin-top: 8px;
}

.action-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 12px;
  padding-top: 8px;
  border-top: 1px dashed #f0f0f0;
}

:deep(.van-button--mini) {
  height: 24px;
  padding: 0 8px;
  font-size: 11px;
}

/* 付款弹窗样式 */
.pay-dialog-content {
  padding: 20px 16px;
}

.form-section {
  margin-top: 16px;
}

.preview-info {
  padding: 12px;
  background-color: #f5f5f5;
  border-radius: 6px;
  font-size: 14px;
}

.preview-item {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
}

.preview-item .highlight {
  font-weight: bold;
  font-size: 16px;
}

.preview-item .positive {
  color: #ee0a24;
}

.preview-item .negative {
  color: #07c160;
}

/* 成功弹窗样式 */
.success-dialog-content {
  padding: 20px;
  text-align: center;
}

.success-icon {
  margin-bottom: 20px;
}

.success-message {
  margin-bottom: 20px;
  font-size: 16px;
  color: #323233;
}

.success-message p {
  margin: 8px 0;
}

.success-details {
  padding: 12px;
  background-color: #f9f9f9;
  border-radius: 6px;
  font-size: 14px;
  color: #646566;
  text-align: left;
}

.success-details p {
  margin: 4px 0;
}
</style>