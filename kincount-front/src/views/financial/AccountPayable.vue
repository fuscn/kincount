<template>
  <div class="account-payable-page">
    <van-nav-bar title="应付款项" fixed placeholder />

    <van-loading v-if="loading" class="page-loading" />

    <div v-else class="content">
      <van-cell-group title="应付汇总" class="statistics-card">
        <van-cell title="总应付金额" :value="`¥${formatAmount(statistics.total_payable)}`" />
        <van-cell title="已付金额" :value="`¥${formatAmount(statistics.paid)}`" value-class="paid-amount" />
        <van-cell title="待付金额" :value="`¥${formatAmount(statistics.pending)}`" value-class="pending-amount" />
        <van-cell title="供应商数量" :value="`${statistics.supplier_count || payableList.length}家`" />
      </van-cell-group>

      <div class="filter-section">
        <van-search v-model="filters.keyword" placeholder="搜索供应商名称/联系人" show-action
          @search="loadPayableList(true)" @clear="handleClearSearch">
          <template #action><div @click="loadPayableList(true)">搜索</div></template>
        </van-search>
        
        <van-dropdown-menu>
          <van-dropdown-item v-model="filters.payment_status" :options="paymentStatusOptions" 
            @change="loadPayableList(true)" />
          <van-dropdown-item v-model="filters.sort_by" :options="sortOptions" 
            @change="loadPayableList(true)" />
        </van-dropdown-menu>
      </div>

      <van-pull-refresh v-model="refreshing" @refresh="loadPayableList(true)">
        <van-list v-model:loading="listLoading" :finished="finished"
          :finished-text="payableList.length === 0 ? '暂无应付款项' : '没有更多了'" @load="loadPayableList">
          <van-cell-group>
            <van-cell v-for="supplier in payableList" :key="supplier.id" :title="supplier.name"
              :label="getSupplierLabel(supplier)" @click="handleSupplierDetail(supplier)">
              <template #value>
                <div class="amount-info">
                  <div class="payable-amount">¥{{ formatAmount(supplier.payable_amount) }}</div>
                  <div class="payment-status" :class="getPaymentStatusClass(supplier)">
                    {{ getPaymentStatusText(supplier) }}
                  </div>
                </div>
              </template>
              <template #extra>
                <van-button size="small" type="primary" @click.stop="handlePay(supplier)"
                  :disabled="!supplier.payable_amount || supplier.payable_amount <= 0">
                  付款
                </van-button>
              </template>
            </van-cell>
          </van-cell-group>

          <van-empty v-if="!listLoading && !refreshing && payableList.length === 0"
            description="暂无应付款项" image="search" />
        </van-list>
      </van-pull-refresh>

      <div class="quick-actions" v-if="hasAddPermission">
        <van-button type="primary" block @click="handleAddPayable" class="add-button">
          新增应付记录
        </van-button>
      </div>
    </div>

    <van-popup v-model:show="showPaymentDialog" position="bottom" round :close-on-popstate="true">
      <div class="payment-dialog">
        <van-nav-bar title="付款操作" left-text="取消" right-text="确认"
          @click-left="showPaymentDialog = false" @click-right="handlePaymentConfirm" />
        
        <div class="payment-content">
          <van-cell-group>
            <van-cell title="供应商" :value="selectedSupplier?.name" />
            <van-cell title="应付金额" :value="`¥${formatAmount(selectedSupplier?.payable_amount)}`" />
            <van-field v-model="paymentForm.amount" label="付款金额" type="number" placeholder="请输入付款金额" required
              :rules="[{ validator: validatePaymentAmount, message: '付款金额不能超过应付金额' }]">
              <template #extra>元</template>
            </van-field>
            <van-field v-model="paymentForm.payment_method" label="支付方式" placeholder="请选择支付方式" readonly is-link required
              @click="showPaymentMethodPicker = true" />
            <van-field v-model="paymentForm.remark" label="备注" type="textarea" placeholder="请输入付款备注（可选）" rows="2"
              autosize maxlength="200" show-word-limit />
            <van-field v-model="paymentForm.payment_date" label="付款日期" placeholder="请选择付款日期" readonly is-link required
              @click="showPaymentDatePicker = true" />
          </van-cell-group>
        </div>
      </div>
    </van-popup>

    <van-popup v-model:show="showPaymentMethodPicker" position="bottom">
      <van-picker :columns="paymentMethodOptions" @confirm="onPaymentMethodConfirm" 
        @cancel="showPaymentMethodPicker = false" />
    </van-popup>

    <van-popup v-model:show="showPaymentDatePicker" position="bottom">
      <van-date-picker :min-date="minDate" :max-date="maxDate" @confirm="onPaymentDateConfirm" 
        @cancel="showPaymentDatePicker = false" />
    </van-popup>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { showToast, showSuccessToast, showFailToast, showConfirmDialog } from 'vant'
import { useAccountStore } from '@/store/modules/account'
import { getAccountPayable, payAccountRecord } from '@/api/account'
import { PERM } from '@/constants/permissions'
import { useAuthStore } from '@/store/modules/auth'
import dayjs from 'dayjs'

const router = useRouter()
const accountStore = useAccountStore()
const authStore = useAuthStore()

// 响应式数据
const loading = ref(true)
const listLoading = ref(false)
const refreshing = ref(false)
const finished = ref(false)
const showPaymentDialog = ref(false)
const showPaymentMethodPicker = ref(false)
const showPaymentDatePicker = ref(false)

// 筛选条件
const filters = reactive({
  keyword: '',
  payment_status: '',
  sort_by: 'amount_desc'
})

// 分页参数
const pagination = reactive({ page: 1, pageSize: 15, total: 0 })

// 统计信息
const statistics = reactive({
  total_payable: 0, paid: 0, pending: 0, supplier_count: 0
})

// 应付款项列表
const payableList = ref([])
const selectedSupplier = ref(null)

// 付款表单
const paymentForm = reactive({
  amount: '', payment_method: '', remark: '', payment_date: dayjs().format('YYYY-MM-DD')
})

// 日期范围
const minDate = new Date(2020, 0, 1)
const maxDate = new Date(2030, 11, 31)

// 选项数据
const paymentStatusOptions = [
  { text: '全部状态', value: '' },
  { text: '未付款', value: 'pending' },
  { text: '部分付款', value: 'partial' },
  { text: '已付款', value: 'paid' }
]

const sortOptions = [
  { text: '金额降序', value: 'amount_desc' },
  { text: '金额升序', value: 'amount_asc' },
  { text: '最近更新', value: 'updated_desc' }
]

const paymentMethodOptions = [
  { text: '现金', value: '现金' },
  { text: '银行卡', value: '银行卡' },
  { text: '微信支付', value: '微信支付' },
  { text: '支付宝', value: '支付宝' },
  { text: '转账', value: '转账' },
  { text: '其他', value: '其他' }
]

// 权限检查
const hasAddPermission = computed(() => authStore.hasPerm(PERM.FINANCE_ADD))

// 工具函数
const formatAmount = (amount) => {
  if (!amount) return '0.00'
  const num = parseFloat(amount)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

const getSupplierLabel = (supplier) => {
  const parts = []
  if (supplier.contact_person) parts.push(`联系人: ${supplier.contact_person}`)
  if (supplier.phone) parts.push(`电话: ${supplier.phone}`)
  if (supplier.last_payment_date) parts.push(`最后付款: ${dayjs(supplier.last_payment_date).format('MM-DD')}`)
  return parts.join(' | ')
}

const getPaymentStatusText = (supplier) => {
  const payable = parseFloat(supplier.payable_amount) || 0
  const paid = parseFloat(supplier.paid_amount) || 0
  if (paid <= 0) return '未付款'
  if (paid >= payable) return '已付款'
  return '部分付款'
}

const getPaymentStatusClass = (supplier) => {
  const status = getPaymentStatusText(supplier)
  const classMap = { '未付款': 'status-pending', '部分付款': 'status-partial', '已付款': 'status-paid' }
  return classMap[status] || 'status-pending'
}

const validatePaymentAmount = (value) => {
  const amount = parseFloat(value)
  const payable = parseFloat(selectedSupplier.value?.payable_amount) || 0
  return !isNaN(amount) && amount > 0 && amount <= payable
}

// 数据加载
const loadPayableList = async (isRefresh = false) => {
  if (isRefresh) {
    pagination.page = 1
    finished.value = false
    refreshing.value = true
  } else {
    listLoading.value = true
  }

  try {
    const params = { page: pagination.page, limit: pagination.pageSize, ...filters }
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] == null) delete params[key]
    })

    const result = await getAccountPayable(params)
    let listData = [], totalCount = 0

    if (result?.list) {
      listData = result.list
      totalCount = result.total || 0
    } else if (result?.data?.list) {
      listData = result.data.list
      totalCount = result.data.total || 0
    } else if (Array.isArray(result)) {
      listData = result
      totalCount = result.length
    } else {
      listData = result || []
      totalCount = result?.total || 0
    }

    if (result?.statistics) Object.assign(statistics, result.statistics)
    else if (result?.data?.statistics) Object.assign(statistics, result.data.statistics)

    if (isRefresh) {
      payableList.value = listData
    } else {
      const existingIds = new Set(payableList.value.map(item => item.id))
      const newItems = listData.filter(item => !existingIds.has(item.id))
      payableList.value = [...payableList.value, ...newItems]
    }
    
    pagination.total = totalCount
    finished.value = payableList.value.length >= pagination.total
    if (!finished.value) pagination.page++

  } catch (error) {
    console.error('加载应付款项列表失败:', error)
    showFailToast('加载应付款项列表失败')
    finished.value = true
  } finally {
    refreshing.value = false
    listLoading.value = false
    loading.value = false
  }
}

const loadStatistics = async () => {
  try {
    await accountStore.loadPayable()
    if (accountStore.payableList && Array.isArray(accountStore.payableList)) {
      const totalPayable = accountStore.payableList.reduce((sum, item) => sum + (parseFloat(item.payable_amount) || 0), 0)
      const totalPaid = accountStore.payableList.reduce((sum, item) => sum + (parseFloat(item.paid_amount) || 0), 0)
      statistics.total_payable = totalPayable
      statistics.paid = totalPaid
      statistics.pending = totalPayable - totalPaid
      statistics.supplier_count = accountStore.payableList.length
    }
  } catch (error) {
    console.error('加载统计数据失败:', error)
  }
}

// 事件处理
const onPaymentMethodConfirm = (value) => {
  paymentForm.payment_method = value.selectedOptions[0].value
  showPaymentMethodPicker.value = false
}

const onPaymentDateConfirm = (value) => {
  const selectedDate = new Date(value.selectedValues.year, value.selectedValues.month - 1, value.selectedValues.day)
  paymentForm.payment_date = dayjs(selectedDate).format('YYYY-MM-DD')
  showPaymentDatePicker.value = false
}

const handleClearSearch = () => {
  filters.keyword = ''
  loadPayableList(true)
}

const handleSupplierDetail = (supplier) => {
  router.push(`/supplier/detail/${supplier.id}`)
}

const handlePay = (supplier) => {
  if (!supplier.payable_amount || supplier.payable_amount <= 0) {
    showToast('该供应商暂无应付金额')
    return
  }
  
  selectedSupplier.value = supplier
  paymentForm.amount = formatAmount(supplier.payable_amount)
  paymentForm.payment_method = ''
  paymentForm.remark = ''
  paymentForm.payment_date = dayjs().format('YYYY-MM-DD')
  showPaymentDialog.value = true
}

const handlePaymentConfirm = async () => {
  try {
    if (!paymentForm.amount || parseFloat(paymentForm.amount) <= 0) {
      showToast('请输入有效的付款金额')
      return
    }
    
    if (!paymentForm.payment_method) {
      showToast('请选择支付方式')
      return
    }

    const paymentAmount = parseFloat(paymentForm.amount)
    const payableAmount = parseFloat(selectedSupplier.value.payable_amount)
    
    if (paymentAmount > payableAmount) {
      showToast('付款金额不能超过应付金额')
      return
    }

    await showConfirmDialog({
      title: '确认付款',
      message: `确定要向 ${selectedSupplier.value.name} 支付 ¥${formatAmount(paymentForm.amount)} 吗？`
    })

    const paymentData = {
      amount: paymentAmount,
      payment_method: paymentForm.payment_method,
      remark: paymentForm.remark,
      payment_date: paymentForm.payment_date
    }

    await payAccountRecord(selectedSupplier.value.id, paymentData)
    showSuccessToast('付款成功')
    showPaymentDialog.value = false
    loadPayableList(true)
    loadStatistics()
    
  } catch (error) {
    if (error !== 'cancel') {
      console.error('付款失败:', error)
      showFailToast('付款失败')
    }
  }
}

const handleAddPayable = () => {
  router.push('/account/payable/create')
}

onMounted(() => {
  loadPayableList(true)
  loadStatistics()
})
</script>

<style scoped lang="scss">
// account-payable.scss
.account-payable-page {
  background: #f7f8fa;
  min-height: 100vh;

  .page-loading {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 9999;
  }

  .content {
    padding: 16px;
  }

  .statistics-card {
    margin-bottom: 16px;
    border-radius: 8px;
    overflow: hidden;
  }

  .filter-section {
    background: white;
    margin-bottom: 12px;
    border-radius: 8px;
    overflow: hidden;
  }

  .amount-info {
    text-align: right;
    
    .payable-amount {
      font-size: 16px;
      font-weight: bold;
      color: #ee0a24;
      margin-bottom: 4px;
    }
    
    .payment-status {
      font-size: 12px;
      padding: 2px 6px;
      border-radius: 10px;
      display: inline-block;
      
      &.status-pending {
        background: #fff2e8;
        color: #ed6a0c;
      }
      
      &.status-partial {
        background: #e8f4ff;
        color: #1989fa;
      }
      
      &.status-paid {
        background: #e8f8e8;
        color: #07c160;
      }
    }
  }

  .paid-amount {
    color: #07c160;
    font-weight: bold;
  }

  .pending-amount {
    color: #ee0a24;
    font-weight: bold;
  }

  .quick-actions {
    padding: 16px;
    background: white;
    border-radius: 8px;
    margin-top: 16px;
    
    .add-button {
      border-radius: 6px;
    }
  }

  .payment-dialog {
    background: #fff;
    border-radius: 16px 16px 0 0;
    overflow: hidden;
    
    .payment-content {
      padding: 16px;
      max-height: 60vh;
      overflow-y: auto;
    }
  }

  // 组件样式覆盖
:deep(.van-nav-bar) {
  background: #fff;
  
  &__text, .van-icon {
    color: #1989fa;
  }
}

:deep(.van-cell-group__title) {
  font-size: 14px;
  color: #323233;
  font-weight: 500;
  padding: 16px 16px 8px;
  background: #f7f8fa;
}

:deep(.van-cell) {
  padding: 12px 16px;
  
  &__title {
    flex: 2;
    color: #646566;
  }
  
  &__value {
    flex: 3;
    text-align: right;
    color: #323233;
  }
  
  &__label {
    font-size: 12px;
    color: #969799;
    margin-top: 2px;
  }
}

:deep(.van-button) {
  border-radius: 6px;
}

:deep(.van-button--small) {
  height: 32px;
  font-size: 14px;
  padding: 0 12px;
}

:deep(.van-empty) {
  padding: 60px 0;
}

:deep(.van-dropdown-menu) {
  background: white;
}

:deep(.van-search) {
  padding: 8px 16px;
}

  // 响应式设计
  @media (max-width: 768px) {
    .content {
      padding: 12px;
    }
    
    :deep(.van-cell) {
      padding: 10px 12px;
    }
  }
}
</style>