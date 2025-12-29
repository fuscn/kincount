<template>
  <div class="account-receivable-page">
    <van-nav-bar title="应收款项" fixed placeholder />

    <van-loading v-if="loading" class="page-loading" />

    <div v-else class="content">
      <van-cell-group title="应收汇总" class="statistics-card">
        <van-cell title="总应收金额" :value="`¥${formatAmount(statistics.total_receivable)}`" />
        <van-cell title="已收金额" :value="`¥${formatAmount(statistics.received)}`" value-class="received-amount" />
        <van-cell title="待收金额" :value="`¥${formatAmount(statistics.pending)}`" value-class="pending-amount" />
        <van-cell title="客户数量" :value="`${statistics.customer_count || receivableList.length}家`" />
      </van-cell-group>

      <div class="filter-section">
        <van-search v-model="filters.keyword" placeholder="搜索客户名称/联系人" show-action
          @search="loadReceivableList(true)" @clear="handleClearSearch">
          <template #action><div @click="loadReceivableList(true)">搜索</div></template>
        </van-search>
        
        <van-dropdown-menu>
          <van-dropdown-item v-model="filters.payment_status" :options="paymentStatusOptions" 
            @change="loadReceivableList(true)" />
          <van-dropdown-item v-model="filters.sort_by" :options="sortOptions" 
            @change="loadReceivableList(true)" />
        </van-dropdown-menu>
      </div>

      <van-pull-refresh v-model="refreshing" @refresh="loadReceivableList(true)">
        <van-list v-model:loading="listLoading" :finished="finished"
          :finished-text="receivableList.length === 0 ? '暂无应收款项' : '没有更多了'" @load="loadReceivableList">
          <van-cell-group>
            <van-cell v-for="customer in receivableList" :key="customer.id" :title="customer.name"
              :label="getCustomerLabel(customer)" @click="handleCustomerDetail(customer)">
              <template #value>
                <div class="amount-info">
                  <div class="receivable-amount">¥{{ formatAmount(customer.receivable_amount) }}</div>
                  <div class="payment-status" :class="getPaymentStatusClass(customer)">
                    {{ getPaymentStatusText(customer) }}
                  </div>
                </div>
              </template>
              <template #extra>
                <van-button size="small" type="primary" @click.stop="handleReceive(customer)"
                  :disabled="!customer.receivable_amount || customer.receivable_amount <= 0">
                  收款
                </van-button>
              </template>
            </van-cell>
          </van-cell-group>

          <van-empty v-if="!listLoading && !refreshing && receivableList.length === 0"
            description="暂无应收款项" image="search" />
        </van-list>
      </van-pull-refresh>

      <div class="quick-actions" v-if="hasAddPermission">
        <van-button type="primary" block @click="handleAddReceivable" class="add-button">
          新增应收记录
        </van-button>
      </div>
    </div>

    <van-popup v-model:show="showReceiveDialog" position="bottom" round :close-on-popstate="true">
      <div class="receive-dialog">
        <van-nav-bar title="收款操作" left-text="取消" right-text="确认"
          @click-left="showReceiveDialog = false" @click-right="handleReceiveConfirm" />
        
        <div class="receive-content">
          <van-cell-group>
            <van-cell title="客户" :value="selectedCustomer?.name" />
            <van-cell title="应收金额" :value="`¥${formatAmount(selectedCustomer?.receivable_amount)}`" />
            <van-field v-model="receiveForm.amount" label="收款金额" type="number" placeholder="请输入收款金额" required
              :rules="[{ validator: validateReceiveAmount, message: '收款金额不能超过应收金额' }]">
              <template #extra>元</template>
            </van-field>
            <van-field v-model="receiveForm.payment_method" label="收款方式" placeholder="请选择收款方式" readonly is-link required
              @click="showPaymentMethodPicker = true" />
            <van-field v-model="receiveForm.remark" label="备注" type="textarea" placeholder="请输入收款备注（可选）" rows="2"
              autosize maxlength="200" show-word-limit />
            <van-field v-model="receiveForm.receive_date" label="收款日期" placeholder="请选择收款日期" readonly is-link required
              @click="showReceiveDatePicker = true" />
          </van-cell-group>
        </div>
      </div>
    </van-popup>

    <van-popup v-model:show="showPaymentMethodPicker" position="bottom">
      <van-picker :columns="paymentMethodOptions" @confirm="onPaymentMethodConfirm" 
        @cancel="showPaymentMethodPicker = false" />
    </van-popup>

    <van-popup v-model:show="showReceiveDatePicker" position="bottom">
      <van-date-picker :min-date="minDate" :max-date="maxDate" @confirm="onReceiveDateConfirm" 
        @cancel="showReceiveDatePicker = false" />
    </van-popup>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { showToast, showSuccessToast, showFailToast, showConfirmDialog } from 'vant'
import { useAccountStore } from '@/store/modules/account'
import { getAccountReceivable, payAccountRecord } from '@/api/account'
import { PERM } from '@/constants/permissions'
import { useAuthStore } from '@/store/modules/auth'
import dayjs from 'dayjs'

const router = useRouter()
const accountStore = useAccountStore()
const authStore = useAuthStore()

const loading = ref(true)
const listLoading = ref(false)
const refreshing = ref(false)
const finished = ref(false)
const showReceiveDialog = ref(false)
const showPaymentMethodPicker = ref(false)
const showReceiveDatePicker = ref(false)

const filters = reactive({ keyword: '', payment_status: '', sort_by: 'amount_desc' })
const pagination = reactive({ page: 1, pageSize: 15, total: 0 })
const statistics = reactive({ total_receivable: 0, received: 0, pending: 0, customer_count: 0 })
const receivableList = ref([])
const selectedCustomer = ref(null)
const receiveForm = reactive({ amount: '', payment_method: '', remark: '', receive_date: dayjs().format('YYYY-MM-DD') })

const minDate = new Date(2020, 0, 1)
const maxDate = new Date(2030, 11, 31)

const paymentStatusOptions = [
  { text: '全部状态', value: '' },
  { text: '未收款', value: 'pending' },
  { text: '部分收款', value: 'partial' },
  { text: '已收款', value: 'received' }
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

const hasAddPermission = computed(() => authStore.hasPerm(PERM.FINANCE_ADD))

const formatAmount = (amount) => {
  const num = parseFloat(amount)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

const getCustomerLabel = (customer) => {
  const parts = []
  if (customer.contact_person) parts.push(`联系人: ${customer.contact_person}`)
  if (customer.phone) parts.push(`电话: ${customer.phone}`)
  if (customer.last_receive_date) parts.push(`最后收款: ${dayjs(customer.last_receive_date).format('MM-DD')}`)
  return parts.join(' | ')
}

const getPaymentStatusText = (customer) => {
  const receivable = parseFloat(customer.receivable_amount) || 0
  const received = parseFloat(customer.received_amount) || 0
  if (received <= 0) return '未收款'
  if (received >= receivable) return '已收款'
  return '部分收款'
}

const getPaymentStatusClass = (customer) => {
  const status = getPaymentStatusText(customer)
  const classMap = { '未收款': 'status-pending', '部分收款': 'status-partial', '已收款': 'status-received' }
  return classMap[status] || 'status-pending'
}

const validateReceiveAmount = (value) => {
  const amount = parseFloat(value)
  const receivable = parseFloat(selectedCustomer.value?.receivable_amount) || 0
  return !isNaN(amount) && amount > 0 && amount <= receivable
}

const loadReceivableList = async (isRefresh = false) => {
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

    const result = await getAccountReceivable(params)
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
      receivableList.value = listData
    } else {
      const existingIds = new Set(receivableList.value.map(item => item.id))
      const newItems = listData.filter(item => !existingIds.has(item.id))
      receivableList.value = [...receivableList.value, ...newItems]
    }
    
    pagination.total = totalCount
    finished.value = receivableList.value.length >= pagination.total
    if (!finished.value) pagination.page++

  } catch (error) {
    console.error('加载应收款项列表失败:', error)
    showFailToast('加载应收款项列表失败')
    finished.value = true
  } finally {
    refreshing.value = false
    listLoading.value = false
    loading.value = false
  }
}

const loadStatistics = async () => {
  try {
    await accountStore.loadReceivable()
    if (accountStore.receivableList && Array.isArray(accountStore.receivableList)) {
      const totalReceivable = accountStore.receivableList.reduce((sum, item) => sum + (parseFloat(item.receivable_amount) || 0), 0)
      const totalReceived = accountStore.receivableList.reduce((sum, item) => sum + (parseFloat(item.received_amount) || 0), 0)
      statistics.total_receivable = totalReceivable
      statistics.received = totalReceived
      statistics.pending = totalReceivable - totalReceived
      statistics.customer_count = accountStore.receivableList.length
    }
  } catch (error) {
    console.error('加载统计数据失败:', error)
  }
}

const onPaymentMethodConfirm = (value) => {
  receiveForm.payment_method = value.selectedOptions[0].value
  showPaymentMethodPicker.value = false
}

const onReceiveDateConfirm = (value) => {
  const selectedDate = new Date(value.selectedValues.year, value.selectedValues.month - 1, value.selectedValues.day)
  receiveForm.receive_date = dayjs(selectedDate).format('YYYY-MM-DD')
  showReceiveDatePicker.value = false
}

const handleClearSearch = () => {
  filters.keyword = ''
  loadReceivableList(true)
}

const handleCustomerDetail = (customer) => {
  router.push(`/customer/detail/${customer.id}`)
}

const handleReceive = (customer) => {
  if (!customer.receivable_amount || customer.receivable_amount <= 0) {
    showToast('该客户暂无应收金额')
    return
  }
  
  selectedCustomer.value = customer
  receiveForm.amount = formatAmount(customer.receivable_amount)
  receiveForm.payment_method = ''
  receiveForm.remark = ''
  receiveForm.receive_date = dayjs().format('YYYY-MM-DD')
  showReceiveDialog.value = true
}

const handleReceiveConfirm = async () => {
  try {
    if (!receiveForm.amount || parseFloat(receiveForm.amount) <= 0) {
      showToast('请输入有效的收款金额')
      return
    }
    
    if (!receiveForm.payment_method) {
      showToast('请选择收款方式')
      return
    }

    const receiveAmount = parseFloat(receiveForm.amount)
    const receivableAmount = parseFloat(selectedCustomer.value.receivable_amount)
    
    if (receiveAmount > receivableAmount) {
      showToast('收款金额不能超过应收金额')
      return
    }

    await showConfirmDialog({
      title: '确认收款',
      message: `确定要向 ${selectedCustomer.value.name} 收取 ¥${formatAmount(receiveForm.amount)} 吗？`
    })

    const receiveData = {
      amount: receiveAmount,
      payment_method: receiveForm.payment_method,
      remark: receiveForm.remark,
      receive_date: receiveForm.receive_date
    }

    await payAccountRecord(selectedCustomer.value.id, receiveData)
    showSuccessToast('收款成功')
    showReceiveDialog.value = false
    loadReceivableList(true)
    loadStatistics()
    
  } catch (error) {
    if (error !== 'cancel') {
      console.error('收款失败:', error)
      showFailToast('收款失败')
    }
  }
}

const handleAddReceivable = () => {
  router.push('/account/receivable/create')
}

onMounted(() => {
  loadReceivableList(true)
  loadStatistics()
})
</script>

<style scoped lang="scss">
.account-receivable-page { background: #f7f8fa; min-height: 100vh; }

.page-loading {
  position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999;
}

.content { padding: 16px; }

.statistics-card { margin-bottom: 16px; border-radius: 8px; overflow: hidden; }

.filter-section { background: white; margin-bottom: 12px; border-radius: 8px; overflow: hidden; }

.amount-info {
  text-align: right;
  .receivable-amount { font-size: 16px; font-weight: bold; color: #07c160; margin-bottom: 4px; }
  .payment-status {
    font-size: 12px; padding: 2px 6px; border-radius: 10px; display: inline-block;
    &.status-pending { background: #fff2e8; color: #ed6a0c; }
    &.status-partial { background: #e8f4ff; color: #1989fa; }
    &.status-received { background: #e8f8e8; color: #07c160; }
  }
}

.received-amount { color: #07c160; font-weight: bold; }
.pending-amount { color: #ee0a24; font-weight: bold; }

.quick-actions {
  padding: 16px; background: white; border-radius: 8px; margin-top: 16px;
  .add-button { border-radius: 6px; }
}

.receive-dialog {
  background: #fff; border-radius: 16px 16px 0 0; overflow: hidden;
  .receive-content { padding: 16px; max-height: 60vh; overflow-y: auto; }
}

:deep(.van-nav-bar) { background: #fff;
  &__text, .van-icon { color: #1989fa; }
}
:deep(.van-cell-group__title) { font-size: 14px; color: #323233; font-weight: 500; padding: 16px 16px 8px; background: #f7f8fa; }
:deep(.van-cell) { padding: 12px 16px;
  &__title { flex: 2; color: #646566; }
  &__value { flex: 3; text-align: right; color: #323233; }
  &__label { font-size: 12px; color: #969799; margin-top: 2px; }
}
:deep(.van-button) { border-radius: 6px; }
:deep(.van-button--small) { height: 32px; font-size: 14px; padding: 0 12px; }
:deep(.van-empty) { padding: 60px 0; }
:deep(.van-dropdown-menu) { background: white; }
:deep(.van-search) { padding: 8px 16px; }

@media (max-width: 768px) {
  .content { padding: 12px; }
  :deep(.van-cell) { padding: 10px 12px; }
}
</style>