<template>
  <div class="settlement-detail-container">
    <!-- 导航栏 -->
    <van-nav-bar :title="pageTitle" left-text="返回" left-arrow @click-left="handleBack" fixed safe-area-inset-top>
      <template #right>
        <van-icon v-if="settlementData && settlementData.cancelable" name="delete-o" size="18" @click="handleCancel"
          v-perm="PERM.SETTLEMENT_CANCEL" />
      </template>
    </van-nav-bar>

    <!-- 主要内容区域 -->
    <div class="content-wrapper" :style="{ paddingTop: '46px' }">
      <!-- 加载状态 -->
      <div v-if="loading" class="loading-state">
        <van-loading size="24px" vertical>加载中...</van-loading>
      </div>

      <!-- 错误状态 -->
      <div v-else-if="error" class="error-state">
        <van-empty image="error" :description="error" />
        <div class="retry-button">
          <van-button type="primary" @click="loadSettlementDetail">重试</van-button>
        </div>
      </div>

      <!-- 详情内容 -->
      <div v-else-if="settlementData" class="detail-content">
        <!-- 基本信息卡片 -->
        <div class="info-card">
          <div class="card-header">
            <h3 class="card-title">核销信息</h3>
            <van-tag :type="getTypeTagType(settlementData.account_type)" size="medium">
              {{ getTypeText(settlementData.account_type) }}
            </van-tag>
          </div>

          <div class="card-body">
            <div class="info-row">
              <span class="label">核销单号：</span>
              <span class="value">{{ settlementData.settlement_no }}</span>
            </div>

            <div class="info-row">
              <span class="label">核销金额：</span>
              <span class="value amount" :class="getAmountClass(settlementData.account_type)">
                {{ settlementData.account_type === 1 ? '+' : '-' }}¥{{ formatPrice(settlementData.settlement_amount) }}
              </span>
            </div>

            <div class="info-row">
              <span class="label">核销日期：</span>
              <span class="value">{{ formatDate(settlementData.settlement_date) }}</span>
            </div>

            <div class="info-row">
              <span class="label">创建时间：</span>
              <span class="value">{{ formatDateTime(settlementData.created_at) }}</span>
            </div>

            <div v-if="settlementData.remark" class="info-row">
              <span class="label">备注：</span>
              <span class="value remark">{{ settlementData.remark }}</span>
            </div>
          </div>
        </div>

        <!-- 对方信息卡片 -->
        <div class="info-card">
          <div class="card-header">
            <h3 class="card-title">{{ getTargetTypeText(settlementData) }}</h3>
          </div>

          <div class="card-body">
            <div class="info-row">
              <span class="label">对方名称：</span>
              <span class="value">{{ getTargetName(settlementData) }}</span>
            </div>

            <div class="info-row">
              <span class="label">联系人：</span>
              <span class="value">{{ getContactPerson(settlementData) }}</span>
            </div>

            <div class="info-row">
              <span class="label">联系电话：</span>
              <span class="value">{{ getContactPhone(settlementData) }}</span>
            </div>

            <!-- 显示对方类型 -->
            <div class="info-row" v-if="settlementData.target_type">
              <span class="label">对方类型：</span>
              <van-tag type="primary" size="small">
                {{ settlementData.target_type === 'customer' ? '客户' : '供应商' }}
              </van-tag>
            </div>
          </div>
        </div>

        <!-- 账款信息卡片 -->
        <div class="info-card" v-if="settlementData.account">
          <div class="card-header">
            <h3 class="card-title">账款信息</h3>
            <van-tag :type="getAccountStatusTagType(settlementData.account.status)" size="small">
              {{ settlementData.account.status_text || getAccountStatusText(settlementData.account.status) }}
            </van-tag>
          </div>

          <div class="card-body">
            <div class="info-row">
              <span class="label">账款类型：</span>
              <span class="value">{{ settlementData.account.type_text || getAccountTypeText(settlementData.account.type) }}</span>
            </div>

            <div class="info-row clickable" @click="goToRelatedOrder">
              <span class="label">关联业务：</span>
              <div class="value-with-icon">
                <span class="value">
                  {{ settlementData.related_type_text || getRelatedTypeText(settlementData.account.related_type) }} 
                  #{{ settlementData.account.related_id }}
                </span>
                <van-icon name="arrow" class="arrow-icon" />
              </div>
            </div>

            <!-- 使用后端返回的related_info -->
            <div class="info-row" v-if="settlementData.related_info">
              <span class="label">业务详情：</span>
              <span class="value">{{ settlementData.related_info }}</span>
            </div>

            <div class="info-row">
              <span class="label">账款金额：</span>
              <span class="value">¥{{ formatPrice(settlementData.account.amount) }}</span>
            </div>

            <div class="info-row">
              <span class="label">已收/已付：</span>
              <span class="value">¥{{ formatPrice(settlementData.account.paid_amount) }}</span>
            </div>

            <div class="info-row">
              <span class="label">账款余额：</span>
              <span class="value">¥{{ formatPrice(settlementData.account.balance_amount) }}</span>
            </div>

            <div v-if="settlementData.account.due_date" class="info-row">
              <span class="label">到期日期：</span>
              <span class="value">{{ formatDate(settlementData.account.due_date) }}</span>
            </div>
          </div>
        </div>

        <!-- 财务信息卡片 -->
        <div class="info-card" v-if="settlementData.financial">
          <div class="card-header">
            <h3 class="card-title">财务收支信息</h3>
          </div>

          <div class="card-body">
            <div class="info-row">
              <span class="label">收支单号：</span>
              <span class="value">{{ settlementData.financial.record_no }}</span>
            </div>

            <div class="info-row">
              <span class="label">收支类型：</span>
              <van-tag :type="settlementData.financial.type === 1 ? 'success' : 'warning'" size="small">
                {{ settlementData.financial.type === 1 ? '收入' : '支出' }}
              </van-tag>
            </div>

            <div class="info-row">
              <span class="label">收支类别：</span>
              <span class="value">{{ settlementData.financial.category }}</span>
            </div>

            <div class="info-row">
              <span class="label">收支金额：</span>
              <span class="value amount" :class="settlementData.financial.type === 1 ? 'income' : 'expense'">
                {{ settlementData.financial.type === 1 ? '+' : '-' }}¥{{ formatPrice(settlementData.financial.amount) }}
              </span>
            </div>

            <div class="info-row">
              <span class="label">支付方式：</span>
              <span class="value">{{ settlementData.financial.payment_method || '--' }}</span>
            </div>

            <div class="info-row">
              <span class="label">收支日期：</span>
              <span class="value">{{ formatDate(settlementData.financial.record_date) }}</span>
            </div>

            <div v-if="settlementData.financial.remark" class="info-row">
              <span class="label">备注：</span>
              <span class="value remark">{{ settlementData.financial.remark }}</span>
            </div>
          </div>
        </div>

        <!-- 操作历史卡片 -->
        <div class="info-card" v-if="settlementData.operation_logs && settlementData.operation_logs.length > 0">
          <div class="card-header">
            <h3 class="card-title">操作历史</h3>
          </div>

          <div class="card-body">
            <div class="timeline">
              <div v-for="(log, index) in settlementData.operation_logs" :key="index" class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                  <div class="timeline-title">{{ log.action }}</div>
                  <div class="timeline-info">
                    <span class="operator">{{ log.operator_name || '系统' }}</span>
                    <span class="time">{{ formatDateTime(log.created_at) }}</span>
                  </div>
                  <div v-if="log.remark" class="timeline-remark">{{ log.remark }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 创建人信息 -->
        <div class="creator-info" v-if="settlementData.creator_name">
          <span class="label">创建人：</span>
          <span class="value">{{ settlementData.creator_name }}</span>
        </div>
      </div>
    </div>

    <!-- 取消核销确认对话框 -->
    <van-dialog v-model:show="showCancelDialog" title="确认取消核销" show-cancel-button @confirm="confirmCancel">
      <div class="cancel-dialog-content">
        <p>确定要取消核销单 <strong>{{ settlementData?.settlement_no }}</strong> 吗？</p>
        <p style="color: #ee0a24; margin-top: 8px;">此操作将恢复原始账款余额，且不可恢复。</p>
        <van-field v-model="cancelRemark" label="取消原因" type="textarea" placeholder="请输入取消原因" rows="2" autosize
          class="remark-field" />
      </div>
    </van-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSettlementStore } from '@/store/modules/settlement'
import { showConfirmDialog, showToast, showDialog } from 'vant'
import { PERM } from '@/constants/permissions'

const route = useRoute()
const router = useRouter()
const settlementStore = useSettlementStore()

// 页面标题
const pageTitle = computed(() => {
  return route.meta?.title || '核销详情'
})

// 状态
const loading = ref(true)
const error = ref('')
const settlementData = ref(null)
const showCancelDialog = ref(false)
const cancelRemark = ref('')

// 加载核销详情
const loadSettlementDetail = async () => {
  const id = route.params.id
  if (!id) {
    error.value = '核销单ID不存在'
    loading.value = false
    return
  }

  try {
    loading.value = true
    error.value = ''

    // 调用store的方法获取详情
    const response = await settlementStore.fetchSettlementDetail(id)

    // 注意：response是完整的API响应对象，包括code, msg, data
    if (response && response.code === 200) {
      // response.data才是实际的详情数据
      settlementData.value = response.data
    } else {
      error.value = response?.msg || '核销记录不存在或已被删除'
    }
  } catch (err) {
    console.error('加载核销详情失败:', err)
    error.value = '加载核销详情失败: ' + (err.message || '未知错误')
  } finally {
    loading.value = false
  }
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
  if (dateString.includes('-')) {
    return dateString.split(' ')[0]
  }
  const date = new Date(dateString)
  return date.toLocaleDateString()
}

// 格式化日期时间
const formatDateTime = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toLocaleDateString() + ' ' + date.toLocaleTimeString().slice(0, 5)
}

// 获取类型文本 - 优先使用后端返回的文本
const getTypeText = (type) => {
  // 如果后端返回了account_type_text，直接使用
  if (settlementData.value?.account_type_text) {
    return settlementData.value.account_type_text
  }
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

// 获取对方名称 - 修正版本
const getTargetName = (item) => {
  // 优先使用后端返回的target_info
  if (item.target_info?.name) {
    return item.target_info.name
  }
  // 根据target_type判断
  if (item.target_type === 'customer' && item.customer) {
    return item.customer.name
  }
  if (item.target_type === 'supplier' && item.supplier) {
    return item.supplier.name
  }
  return item.target_name || '未知'
}

// 获取对方类型文本 - 新增方法
const getTargetTypeText = (item) => {
  if (item.account_type === 1) {
    return '客户信息';  // 应收对应客户
  } else if (item.account_type === 2) {
    // 应付可能对应供应商或客户（如销售退货）
    return item.target_type === 'customer' ? '客户信息' : '供应商信息';
  }
  return '对方信息';
}

// 获取联系人信息 - 新增方法
const getContactPerson = (item) => {
  if (item.target_info?.contact_person) {
    return item.target_info.contact_person;
  }
  if (item.target_type === 'customer' && item.customer?.contact_person) {
    return item.customer.contact_person;
  }
  if (item.target_type === 'supplier' && item.supplier?.contact_person) {
    return item.supplier.contact_person;
  }
  return '--';
}

// 获取联系电话 - 新增方法
const getContactPhone = (item) => {
  if (item.target_info?.phone) {
    return item.target_info.phone;
  }
  if (item.target_type === 'customer' && item.customer?.phone) {
    return item.customer.phone;
  }
  if (item.target_type === 'supplier' && item.supplier?.phone) {
    return item.supplier.phone;
  }
  return '--';
}

// 获取账款类型文本
const getAccountTypeText = (type) => {
  const typeMap = {
    1: '应收账款',
    2: '应付账款'
  }
  return typeMap[type] || '未知'
}

// 获取账款状态文本
const getAccountStatusText = (status) => {
  const statusMap = {
    1: '未结清',
    2: '已结清'
  }
  return statusMap[status] || '未知'
}

// 获取账款状态标签样式
const getAccountStatusTagType = (status) => {
  return status === 2 ? 'success' : 'warning'
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

// 跳转到关联业务详情
const goToRelatedOrder = () => {
  if (!settlementData.value?.account) {
    return
  }
  
  const { related_type, related_id } = settlementData.value.account
  let routeName = ''
  
  // 根据关联类型确定跳转的路由
  switch (related_type) {
    case 'sale':
      routeName = 'SaleOrderDetail'
      break
    case 'purchase':
      routeName = 'PurchaseOrderDetail'
      break
    case 'sale_return':
      routeName = 'SaleReturnDetail'
      break
    case 'purchase_return':
      // 退货单统一跳转到退货详情页面
      routeName = 'ReturnOrderDetail'
      break
    default:
      showToast('未知的业务类型')
      return
  }
  
  // 跳转到对应的详情页面
  router.push({
    name: routeName,
    params: { id: related_id }
  })
}

// 返回上一页
const handleBack = () => {
  router.back()
}

// 取消核销
const handleCancel = () => {
  if (!settlementData.value?.cancelable) {
    showToast('该核销记录不可取消')
    return
  }

  showCancelDialog.value = true
}

// 确认取消核销
const confirmCancel = async () => {
  if (!settlementData.value) return

  try {
    // 传递参数给取消核销方法
    const response = await settlementStore.cancelSettlement(settlementData.value.id)

    if (response.code === 200) {
      showToast('取消核销成功')
      cancelRemark.value = ''
      showCancelDialog.value = false

      // 重新加载详情
      await loadSettlementDetail()
    } else {
      showToast(response.msg || '取消核销失败')
    }
  } catch (err) {
    console.error('取消核销失败:', err)
    showToast('取消核销失败: ' + (err.message || '未知错误'))
  }
}

// 初始化加载
onMounted(() => {
  loadSettlementDetail()
})
</script>

<style scoped lang="scss">
.settlement-detail-container {
  min-height: 100vh;
  background-color: #f7f8fa;
}

.content-wrapper {
  padding: 0 12px 20px;
}

.loading-state,
.error-state {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  min-height: 60vh;
}

.retry-button {
  margin-top: 20px;
}

.info-card {
  background: white;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid #f0f0f0;
}

.card-title {
  font-size: 16px;
  font-weight: bold;
  color: #323233;
  margin: 0;
}

.card-body {
  padding: 0;
}

.info-row {
  display: flex;
  margin-bottom: 12px;
  font-size: 14px;

  &:last-child {
    margin-bottom: 0;
  }
}

.info-row .label {
  color: #969799;
  min-width: 80px;
  flex-shrink: 0;
}

.info-row .value {
  color: #323233;
  flex: 1;
  word-break: break-word;
}

// 可点击行样式
.info-row.clickable {
  cursor: pointer;
  transition: background-color 0.2s;
  padding: 8px;
  margin-left: -8px;
  margin-right: -8px;
  border-radius: 4px;
  
  &:active {
    background-color: #f5f5f5;
  }
}

.value-with-icon {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex: 1;
}

.arrow-icon {
  color: #969799;
  margin-left: 8px;
}

.info-row .amount {
  font-weight: bold;
  font-size: 16px;

  &.income {
    color: #07c160;
  }

  &.expense {
    color: #ee0a24;
  }
}

.info-row .remark {
  color: #646566;
  font-size: 13px;
  line-height: 1.4;
}

.creator-info {
  background: white;
  border-radius: 8px;
  padding: 12px 16px;
  font-size: 13px;
  color: #969799;
  display: flex;
  align-items: center;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.creator-info .label {
  margin-right: 4px;
}

.creator-info .value {
  color: #323233;
}

/* 时间线样式 */
.timeline {
  position: relative;
  padding-left: 16px;
}

.timeline-item {
  position: relative;
  margin-bottom: 16px;

  &:last-child {
    margin-bottom: 0;
  }
}

.timeline-dot {
  position: absolute;
  left: -24px;
  top: 6px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: #07c160;
}

.timeline-content {
  padding-left: 4px;
}

.timeline-title {
  font-size: 14px;
  font-weight: 500;
  color: #323233;
  margin-bottom: 4px;
}

.timeline-info {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #969799;
  margin-bottom: 4px;
}

.timeline-remark {
  font-size: 12px;
  color: #646566;
  line-height: 1.4;
  background: #f7f8fa;
  padding: 6px 8px;
  border-radius: 4px;
  margin-top: 4px;
}

/* 对话框样式 */
.cancel-dialog-content {
  padding: 20px 24px;
}

.remark-field {
  margin-top: 12px;
  padding: 0;
  background: #f7f8fa;
  border-radius: 4px;

  :deep(.van-field__label) {
    width: 60px;
  }

  :deep(.van-field__control) {
    min-height: 60px;
  }
}
</style>