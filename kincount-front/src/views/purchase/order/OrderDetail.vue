<template>
  <div class="purchase-order-detail">
    <van-nav-bar 
      title="采购订单详情" 
      left-arrow 
      left-text="返回"
      @click-left="$router.back()"
      fixed
      placeholder
    >
      <template #right>
        <van-button 
          v-if="hasActions" 
          type="primary" 
          size="small"
          @click="showActionSheet = true"
        >
          操作
        </van-button>
      </template>
    </van-nav-bar>
    
    <div class="content">
      <van-loading v-if="loading" class="loading">加载中...</van-loading>
      
      <template v-else-if="orderData">
        <!-- 订单基本信息 -->
        <van-card class="info-card">
          <template #header>
            <div class="card-header">
              <div class="order-no">{{ orderData.order_no }}</div>
              <van-tag :type="getStatusType(orderData.status)">
                {{ getStatusText(orderData.status) }}
              </van-tag>
            </div>
          </template>
          
          <template #default>
            <div class="info-item">
              <span class="label">供应商：</span>
              <span class="value">{{ orderData.supplier?.name }}</span>
            </div>
            <div class="info-item">
              <span class="label">仓库：</span>
              <span class="value">{{ orderData.warehouse?.name }}</span>
            </div>
            <div class="info-item">
              <span class="label">预计到货：</span>
              <span class="value">{{ formatDate(orderData.expected_date) }}</span>
            </div>
            <div class="info-item">
              <span class="label">总金额：</span>
              <span class="value amount">¥{{ formatPrice(orderData.total_amount) }}</span>
            </div>
            <div class="info-item">
              <span class="label">创建人：</span>
              <span class="value">{{ orderData.creator?.real_name }}</span>
            </div>
            <div class="info-item">
              <span class="label">创建时间：</span>
              <span class="value">{{ formatDateTime(orderData.created_at) }}</span>
            </div>
            <div v-if="orderData.remark" class="info-item">
              <span class="label">备注：</span>
              <span class="value">{{ orderData.remark }}</span>
            </div>
          </template>
        </van-card>

        <!-- 商品明细 -->
        <div class="section">
          <h3 class="section-title">商品明细</h3>
          <van-empty v-if="!orderData.items || orderData.items.length === 0" description="暂无商品" />
          <div v-else class="items-list">
            <div v-for="(item, index) in orderData.items" :key="item.id || index" class="item-card">
              <div class="item-info">
                <div class="item-name">{{ getProductName(item) }}</div>
                <div class="item-spec">{{ getSpecText(item) }}</div>
                <div class="item-code">{{ item.product?.product_no || '无编码' }}</div>
              </div>
              <div class="item-quantity">
                <div class="quantity">{{ item.quantity }} {{ item.product?.unit || '个' }}</div>
                <div class="price">¥{{ formatPrice(item.price) }}</div>
                <div v-if="item.received_quantity !== undefined" class="received">
                  已入库: {{ item.received_quantity }}
                </div>
              </div>
              <div class="item-total">
                ¥{{ formatPrice(item.total_amount) }}
              </div>
            </div>
            
            <!-- 合计 -->
            <div class="total-row">
              <div class="total-label">合计：</div>
              <div class="total-amount">¥{{ formatPrice(orderData.total_amount) }}</div>
            </div>
          </div>
        </div>
      </template>
      
      <van-empty v-else description="订单不存在" />
    </div>

    <!-- 操作面板 -->
    <van-action-sheet
      v-model:show="showActionSheet"
      :actions="actions"
      cancel-text="取消"
      close-on-click-action
      @select="onActionSelect"
    />
    
    <!-- 确认对话框 -->
    <van-dialog
      v-model:show="showConfirmDialog"
      :title="confirmTitle"
      show-cancel-button
      @confirm="confirmAction"
    >
      <div class="confirm-content">{{ confirmMessage }}</div>
    </van-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { 
  showToast, 
  showSuccessToast,
  showFailToast
} from 'vant'
import { usePurchaseStore } from '@/store/modules/purchase'

const route = useRoute()
const router = useRouter()
const purchaseStore = usePurchaseStore()

const loading = ref(true)
const orderData = ref(null)
const showActionSheet = ref(false)
const showConfirmDialog = ref(false)

// 确认操作相关
const currentAction = ref('')
const confirmTitle = ref('')
const confirmMessage = ref('')

// 格式化函数
const formatDate = (date) => {
  if (!date) return ''
  try {
    const d = new Date(date)
    if (isNaN(d.getTime())) return ''
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  } catch (error) {
    return ''
  }
}

const formatPrice = (price) => {
  if (price === null || price === undefined || price === '') return '0.00'
  const num = Number(price)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

const formatDateTime = (dateTime) => {
  if (!dateTime) return ''
  try {
    const d = new Date(dateTime)
    if (isNaN(d.getTime())) return ''
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    const hours = String(d.getHours()).padStart(2, '0')
    const minutes = String(d.getMinutes()).padStart(2, '0')
    return `${year}-${month}-${day} ${hours}:${minutes}`
  } catch (error) {
    return ''
  }
}

// 获取状态文本
const getStatusText = (status) => {
  const statusMap = {
    1: '待审核',
    2: '已审核',
    3: '部分入库',
    4: '已完成',
    5: '已取消'
  }
  return statusMap[status] || '未知状态'
}

// 获取状态类型
const getStatusType = (status) => {
  const typeMap = {
    1: 'warning',
    2: 'primary',
    3: 'info',
    4: 'success',
    5: 'danger'
  }
  return typeMap[status] || 'default'
}

// 获取商品名称
const getProductName = (item) => {
  return item.product?.name || '未知商品'
}

// 获取规格文本
const getSpecText = (item) => {
  if (item.product?.spec) {
    if (typeof item.product.spec === 'object') {
      return Object.entries(item.product.spec)
        .map(([key, value]) => `${key}:${value}`)
        .join(' ')
    }
    return String(item.product.spec)
  }
  return ''
}

// 操作权限判断
const canAudit = computed(() => {
  return orderData.value?.status === 1 // 待审核
})

const canCancel = computed(() => {
  const status = orderData.value?.status
  return [1, 2, 3].includes(status) // 待审核、已审核、部分入库
})

const canComplete = computed(() => {
  const status = orderData.value?.status
  return [2, 3].includes(status) // 已审核、部分入库
})

const canEdit = computed(() => {
  const status = orderData.value?.status
  return [1].includes(status) // 仅待审核状态可编辑
})

const canDelete = computed(() => {
  const status = orderData.value?.status
  return [1, 5].includes(status) // 待审核、已取消
})

// 是否有可用操作
const hasActions = computed(() => {
  return canAudit.value || canCancel.value || canComplete.value || canEdit.value || canDelete.value
})

// 操作面板选项
const actions = computed(() => {
  const actionList = []
  
  if (canEdit.value) {
    actionList.push({
      name: '编辑订单',
      action: 'edit',
      color: '#1989fa'
    })
  }
  
  if (canAudit.value) {
    actionList.push({
      name: '审核通过',
      action: 'audit',
      color: '#07c160'
    })
  }
  
  if (canCancel.value) {
    actionList.push({
      name: '取消订单',
      action: 'cancel',
      color: '#ff976a'
    })
  }
  
  if (canComplete.value) {
    actionList.push({
      name: '标记完成',
      action: 'complete',
      color: '#07c160'
    })
  }
  
  if (canDelete.value) {
    actionList.push({
      name: '删除订单',
      action: 'delete',
      color: '#ee0a24'
    })
  }
  
  return actionList
})

// 加载订单详情
const loadOrderDetail = async () => {
  const orderId = route.params.id
  if (!orderId) {
    showFailToast('订单ID不存在')
    return
  }

  try {
    loading.value = true
    await purchaseStore.loadOrderDetail(orderId)
    orderData.value = purchaseStore.currentOrder
    console.log('订单详情数据:', orderData.value)
  } catch (error) {
    console.error('加载订单详情失败:', error)
    showFailToast('加载订单详情失败')
  } finally {
    loading.value = false
  }
}

// 操作面板选择
const onActionSelect = (action) => {
  showActionSheet.value = false
  
  switch (action.action) {
    case 'edit':
      handleEdit()
      break
    case 'audit':
      showConfirm('审核订单', '确定要审核通过此采购订单吗？', 'audit')
      break
    case 'cancel':
      showConfirm('取消订单', '确定要取消此采购订单吗？', 'cancel')
      break
    case 'complete':
      showConfirm('标记完成', '确定要标记此采购订单为已完成吗？', 'complete')
      break
    case 'delete':
      showConfirm('删除订单', '确定要删除此采购订单吗？此操作不可恢复！', 'delete')
      break
  }
}

// 显示确认对话框
const showConfirm = (title, message, action) => {
  confirmTitle.value = title
  confirmMessage.value = message
  currentAction.value = action
  showConfirmDialog.value = true
}

// 确认执行操作
const confirmAction = async () => {
  try {
    let result
    switch (currentAction.value) {
      case 'audit':
        result = await purchaseStore.auditOrder(orderData.value.id)
        if (result) showSuccessToast('审核成功')
        break
      case 'cancel':
        result = await purchaseStore.cancelOrder(orderData.value.id)
        if (result) showSuccessToast('取消成功')
        break
      case 'complete':
        result = await purchaseStore.completeOrder(orderData.value.id)
        if (result) showSuccessToast('标记完成成功')
        break
      case 'delete':
        result = await purchaseStore.deleteOrder(orderData.value.id)
        if (result) {
          showSuccessToast('删除成功')
          router.push('/purchase/order')
          return
        }
        break
    }
    
    // 重新加载订单详情（删除操作除外）
    if (currentAction.value !== 'delete') {
      await loadOrderDetail()
    }
  } catch (error) {
    showFailToast(error.message || '操作失败')
  } finally {
    showConfirmDialog.value = false
    currentAction.value = ''
  }
}

// 直接编辑操作
const handleEdit = () => {
  router.push(`/purchase/order/edit/${orderData.value.id}`)
}

onMounted(() => {
  loadOrderDetail()
})
</script>

<style scoped lang="scss">
.purchase-order-detail {
  background-color: #f7f8fa;
  min-height: 100vh;
}

.content {
  padding: 16px;
}

.loading {
  display: flex;
  justify-content: center;
  padding: 40px 0;
}

.info-card {
  margin-bottom: 16px;
  border-radius: 8px;
  
  :deep(.van-card__header) {
    padding-bottom: 12px;
    border-bottom: 1px solid #f5f5f5;
  }
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.order-no {
  font-size: 16px;
  font-weight: 600;
  color: #323233;
}

.info-item {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
  
  &:last-child {
    margin-bottom: 0;
  }
  
  .label {
    color: #646566;
  }
  
  .value {
    color: #323233;
    
    &.amount {
      font-weight: 600;
      color: #ee0a24;
    }
  }
}

.section {
  background: white;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 16px;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  margin: 0 0 12px 0;
  color: #323233;
}

.items-list {
  .item-card {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 12px 0;
    border-bottom: 1px solid #f5f5f5;
    
    &:last-child {
      border-bottom: none;
    }
  }
  
  .item-info {
    flex: 1;
    
    .item-name {
      font-size: 14px;
      font-weight: 500;
      margin-bottom: 4px;
    }
    
    .item-spec {
      font-size: 12px;
      color: #646566;
      margin-bottom: 2px;
    }
    
    .item-code {
      font-size: 12px;
      color: #969799;
    }
  }
  
  .item-quantity {
    text-align: right;
    margin: 0 12px;
    
    .quantity {
      font-size: 14px;
      margin-bottom: 4px;
    }
    
    .price {
      font-size: 12px;
      color: #969799;
      margin-bottom: 2px;
    }
    
    .received {
      font-size: 11px;
      color: #07c160;
    }
  }
  
  .item-total {
    font-weight: 600;
    color: #ee0a24;
    min-width: 80px;
    text-align: right;
  }
  
  .total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-top: 1px solid #f5f5f5;
    margin-top: 8px;
    
    .total-label {
      font-weight: 600;
      color: #323233;
    }
    
    .total-amount {
      font-weight: 700;
      color: #ee0a24;
      font-size: 16px;
    }
  }
}

.confirm-content {
  padding: 20px;
  text-align: center;
  font-size: 16px;
  color: #323233;
}

// 操作面板样式调整
:deep(.van-action-sheet) {
  .van-action-sheet__item {
    font-size: 16px;
    
    // 为不同类型的操作设置不同颜色
    &[style*="color: #1989fa"] {
      color: #1989fa !important;
    }
    
    &[style*="color: #07c160"] {
      color: #07c160 !important;
    }
    
    &[style*="color: #ff976a"] {
      color: #ff976a !important;
    }
    
    &[style*="color: #ee0a24"] {
      color: #ee0a24 !important;
    }
  }
}
</style>