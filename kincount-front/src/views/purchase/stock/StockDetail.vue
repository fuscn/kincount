<template>
  <div class="purchase-stock-detail">
    <!-- 导航栏 -->
    <van-nav-bar
      :title="`入库单详情 - ${stockData?.stock_no || ''}`"
      left-text="返回"
      left-arrow
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

    <!-- 内容区域 -->
    <div class="content">
      <van-loading v-if="loading" class="loading">加载中...</van-loading>
      
      <template v-else-if="stockData">
        <!-- 基本信息卡片 -->
        <van-card class="info-card">
          <template #header>
            <div class="card-header">
              <div class="stock-no">{{ stockData.stock_no }}</div>
              <van-tag :type="getStatusTagType(stockData.status)" size="medium">
                {{ getStatusText(stockData.status) }}
              </van-tag>
            </div>
          </template>

          <template #desc>
            <div class="info-item">
              <span class="label">供应商：</span>
              <span class="value">{{ stockData.supplier?.name || '--' }}</span>
            </div>
            <div v-if="stockData.supplier?.contact_person" class="info-item">
              <span class="label">联系人：</span>
              <span class="value">{{ stockData.supplier.contact_person }}</span>
            </div>
            <div v-if="stockData.supplier?.phone" class="info-item">
              <span class="label">联系电话：</span>
              <span class="value">{{ stockData.supplier.phone }}</span>
            </div>
            <div class="info-item">
              <span class="label">仓库：</span>
              <span class="value">{{ stockData.warehouse?.name || '--' }}</span>
            </div>
            <div v-if="stockData.warehouse?.address" class="info-item">
              <span class="label">仓库地址：</span>
              <span class="value">{{ stockData.warehouse.address }}</span>
            </div>
            <div class="info-item">
              <span class="label">总金额：</span>
              <span class="value amount">¥{{ formatPrice(stockData.total_amount) }}</span>
            </div>
            
            <!-- 关联采购单 -->
            <div v-if="stockData.purchase_order_id" class="info-item clickable" @click="goToPurchaseOrder(stockData.purchase_order_id)">
              <span class="label">关联采购单：</span>
              <span class="value link">
                {{ stockData.purchaseOrder?.order_no || `PO${stockData.purchase_order_id}` }}
                <van-icon name="arrow" class="link-icon" />
              </span>
            </div>
            
            <div class="info-item">
              <span class="label">创建人：</span>
              <span class="value">{{ stockData.creator?.real_name || '--' }}</span>
            </div>
            <div v-if="stockData.auditor" class="info-item">
              <span class="label">审核人：</span>
              <span class="value">{{ stockData.auditor.real_name }}</span>
            </div>
            <div v-if="stockData.audit_time" class="info-item">
              <span class="label">审核时间：</span>
              <span class="value">{{ formatDateTime(stockData.audit_time) }}</span>
            </div>
            <div class="info-item">
              <span class="label">创建时间：</span>
              <span class="value">{{ formatDateTime(stockData.created_at) }}</span>
            </div>
            <div v-if="stockData.remark" class="info-item">
              <span class="label">备注：</span>
              <span class="value remark">{{ stockData.remark }}</span>
            </div>
          </template>
        </van-card>

        <!-- SKU明细 -->
        <div class="section">
          <h3 class="section-title">SKU明细</h3>
          <van-empty v-if="!stockData.items || stockData.items.length === 0" description="暂无明细数据" />
          <div v-else class="items-list">
            <div
              v-for="(item, index) in stockData.items"
              :key="item.id || index"
              class="item-card"
            >
              <div class="item-info">
                <div class="item-name">{{ item.product?.name || '未知商品' }}</div>
                
                <!-- 规格信息 -->
                <div class="item-specs" v-if="getSkuSpecs(item).length > 0">
                  <van-tag 
                    v-for="(spec, specIndex) in getSkuSpecs(item)" 
                    :key="specIndex"
                    size="small"
                    type="primary"
                    plain
                  >
                    {{ spec }}
                  </van-tag>
                </div>
                
                <div class="item-code">
                  <span>编码: {{ item.product?.product_no || item.sku?.sku_code || '无' }}</span>
                  <!-- 成本价和销售价放在编码后面 -->
                  <div class="price-tags">
                    <van-tag size="mini" type="primary" plain>成本: ¥{{ formatPrice(item.sku?.cost_price) }}</van-tag>
                    <van-tag size="mini" type="success" plain>售价: ¥{{ formatPrice(item.sku?.sale_price) }}</van-tag>
                  </div>
                </div>
              </div>
              
              <div class="item-quantity">
                <div class="quantity">{{ item.quantity }} {{ item.sku?.unit || item.product?.unit || '个' }}</div>
                <div class="price">采购价: ¥{{ formatPrice(item.price) }}</div>
                <div class="sub-total">小计: ¥{{ formatPrice(item.total_amount) }}</div>
              </div>
            </div>

            <!-- 合计 -->
            <div class="total-row">
              <div class="total-label">合计：</div>
              <div class="total-amount">¥{{ formatPrice(stockData.total_amount) }}</div>
            </div>
          </div>
        </div>

        <!-- 关联采购订单信息 -->
        <!-- <div v-if="stockData.purchaseOrder" class="section">
          <h3 class="section-title">关联采购订单</h3>
          <div class="purchase-order-info" @click="goToPurchaseOrder(stockData.purchase_order_id)">
            <div class="purchase-header">
              <div class="purchase-no">{{ stockData.purchaseOrder.order_no }}</div>
              <van-tag :type="getPurchaseStatusTagType(stockData.purchaseOrder.status)">
                {{ getPurchaseStatusText(stockData.purchaseOrder.status) }}
              </van-tag>
            </div>
            <div class="purchase-details">
              <div class="purchase-item">
                <span>供应商：</span>
                <span>{{ stockData.purchaseOrder.supplier?.name || '--' }}</span>
              </div>
              <div class="purchase-item">
                <span>总金额：</span>
                <span class="amount">¥{{ formatPrice(stockData.purchaseOrder.total_amount) }}</span>
              </div>
              <div class="purchase-item">
                <span>创建时间：</span>
                <span>{{ formatDateTime(stockData.purchaseOrder.created_at) }}</span>
              </div>
              <div class="purchase-item">
                <span>预计到货：</span>
                <span>{{ formatDate(stockData.purchaseOrder.expected_date) }}</span>
              </div>
            </div>
            <div class="purchase-arrow">
              <van-icon name="arrow" />
            </div>
          </div>
        </div> -->
      </template>
      
      <van-empty v-else description="入库单不存在" />
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
  showFailToast,
  showConfirmDialog as showConfirmDialogVant
} from 'vant'
import { usePurchaseStore } from '@/store/modules/purchase'

const route = useRoute()
const router = useRouter()
const purchaseStore = usePurchaseStore()

const loading = ref(true)
const stockData = ref(null)
const showActionSheet = ref(false)
const showConfirmDialog = ref(false)

// 确认操作相关
const currentAction = ref('')
const confirmTitle = ref('')
const confirmMessage = ref('')

// 格式化金额
const formatPrice = (price) => {
  if (price === null || price === undefined || price === '') return '0.00'
  const num = Number(price)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

// 格式化日期
const formatDate = (date) => {
  if (!date) return '--'
  try {
    const d = new Date(date)
    if (isNaN(d.getTime())) return '--'
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  } catch (error) {
    return '--'
  }
}

// 格式化日期时间
const formatDateTime = (dateTime) => {
  if (!dateTime) return '--'
  try {
    const d = new Date(dateTime)
    if (isNaN(d.getTime())) return '--'
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    const hours = String(d.getHours()).padStart(2, '0')
    const minutes = String(d.getMinutes()).padStart(2, '0')
    return `${year}-${month}-${day} ${hours}:${minutes}`
  } catch (error) {
    return '--'
  }
}

// 获取SKU规格信息
const getSkuSpecs = (item) => {
  const specs = []
  
  // 优先从sku.spec获取规格信息
  if (item.sku?.spec) {
    if (typeof item.sku.spec === 'object') {
      Object.entries(item.sku.spec).forEach(([key, value]) => {
        specs.push(`${key}:${value}`)
      })
    } else {
      specs.push(String(item.sku.spec))
    }
  }
  // 如果没有sku.spec，再尝试从product.spec获取
  else if (item.product?.spec) {
    if (typeof item.product.spec === 'object') {
      Object.entries(item.product.spec).forEach(([key, value]) => {
        specs.push(`${key}:${value}`)
      })
    } else {
      specs.push(String(item.product.spec))
    }
  }
  
  return specs
}

// 入库单状态文本映射
const getStatusText = (status) => {
  const statusMap = {
    1: '待审核',
    2: '已审核',
    3: '已取消'
  }
  return statusMap[status] || '未知'
}

// 入库单状态标签类型
const getStatusTagType = (status) => {
  const typeMap = {
    1: 'warning',  // 待审核 - 警告色
    2: 'success',  // 已审核 - 成功色
    3: 'danger'    // 已取消 - 危险色
  }
  return typeMap[status] || 'default'
}

// 采购订单状态文本映射
const getPurchaseStatusText = (status) => {
  const statusMap = {
    1: '待审核',
    2: '已审核',
    3: '部分入库',
    4: '已完成',
    5: '已取消'
  }
  return statusMap[status] || '未知'
}

// 采购订单状态标签类型
const getPurchaseStatusTagType = (status) => {
  const typeMap = {
    1: 'warning',
    2: 'primary',
    3: 'info',
    4: 'success',
    5: 'danger'
  }
  return typeMap[status] || 'default'
}

// 跳转到采购订单
const goToPurchaseOrder = (orderId) => {
  if (orderId) {
    router.push(`/purchase/order/detail/${orderId}`)
  }
}

// 操作权限判断
const canAudit = computed(() => {
  return stockData.value?.status === 1 // 待审核
})

const canCancelAudit = computed(() => {
  return stockData.value?.status === 2 // 已审核
})

const canCancel = computed(() => {
  const status = stockData.value?.status
  return [1, 2].includes(status) // 待审核、已审核
})

// 是否有可用操作
const hasActions = computed(() => {
  return canAudit.value || canCancelAudit.value || canCancel.value
})

// 操作面板选项
const actions = computed(() => {
  const actionList = []
  
  if (canAudit.value) {
    actionList.push({
      name: '审核通过',
      action: 'audit',
      color: '#07c160'
    })
  }
  
  if (canCancelAudit.value) {
    actionList.push({
      name: '取消审核',
      action: 'cancelAudit',
      color: '#ff976a'
    })
  }
  
  if (canCancel.value) {
    actionList.push({
      name: '取消入库单',
      action: 'cancel',
      color: '#ee0a24'
    })
  }
  
  return actionList
})

// 加载入库单详情
const loadStockDetail = async () => {
  const stockId = route.params.id
  if (!stockId) {
    showFailToast('入库单ID不存在')
    return
  }

  try {
    loading.value = true
    const detail = await purchaseStore.loadStockDetail(stockId)
    stockData.value = detail
    console.log('入库单详情数据:', stockData.value)
  } catch (error) {
    console.error('加载入库单详情失败:', error)
    showFailToast('加载入库单详情失败')
  } finally {
    loading.value = false
  }
}

// 操作面板选择
const onActionSelect = (action) => {
  showActionSheet.value = false
  
  switch (action.action) {
    case 'audit':
      showConfirm('审核入库单', '确定要审核通过此入库单吗？审核后将更新库存数量。', 'audit')
      break
    case 'cancelAudit':
      showConfirm('取消审核', '确定要取消审核此入库单吗？取消审核将回退库存数量。', 'cancelAudit')
      break
    case 'cancel':
      showConfirm('取消入库单', '确定要取消此入库单吗？此操作不可恢复。', 'cancel')
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
        result = await purchaseStore.auditStock(stockData.value.id)
        if (result) showSuccessToast('审核成功')
        break
      case 'cancelAudit':
        result = await purchaseStore.cancelAuditStock(stockData.value.id)
        if (result) showSuccessToast('取消审核成功')
        break
      case 'cancel':
        result = await purchaseStore.cancelStock(stockData.value.id)
        if (result) {
          showSuccessToast('取消成功')
          router.push('/purchase/stock')
          return
        }
        break
    }
    
    // 重新加载详情（取消操作除外）
    if (currentAction.value !== 'cancel') {
      await loadStockDetail()
    }
  } catch (error) {
    showFailToast(error.message || '操作失败')
  } finally {
    showConfirmDialog.value = false
    currentAction.value = ''
  }
}

onMounted(() => {
  loadStockDetail()
})
</script>

<style scoped lang="scss">
.purchase-stock-detail {
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
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);

  :deep(.van-card__header) {
    padding: 12px 16px;
    border-bottom: 1px solid #f5f5f5;
  }

  :deep(.van-card__content) {
    padding: 12px 16px;
  }
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.stock-no {
  font-size: 16px;
  font-weight: 600;
  color: #323233;
}

.info-item {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 8px;
  font-size: 14px;
  line-height: 1.4;

  &:last-child {
    margin-bottom: 0;
  }

  &.clickable {
    cursor: pointer;
    &:hover {
      background: #f5f5f5;
      margin: -4px -16px;
      padding: 4px 16px;
      border-radius: 4px;
    }
  }

  .label {
    color: #646566;
    white-space: nowrap;
    margin-right: 8px;
  }

  .value {
    color: #323233;
    text-align: right;
    flex: 1;
    word-break: break-word;

    &.amount {
      font-weight: 600;
      color: #ee0a24;
    }

    &.link {
      color: #1989fa;
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 4px;
    }

    &.remark {
      color: #969799;
      font-style: italic;
    }
  }

  .link-icon {
    font-size: 12px;
  }
}

.section {
  background: white;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  margin: 0 0 12px 0;
  color: #323233;
  padding-bottom: 8px;
  border-bottom: 1px solid #f5f5f5;
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
    margin-right: 12px;

    .item-name {
      font-size: 14px;
      font-weight: 500;
      margin-bottom: 4px;
      color: #323233;
    }

    .item-specs {
      display: flex;
      flex-wrap: wrap;
      gap: 4px;
      margin-bottom: 4px;

      :deep(.van-tag) {
        margin-right: 4px;
        margin-bottom: 2px;
      }
    }

    .item-code {
      font-size: 12px;
      color: #969799;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 8px;
      
      > span {
        margin-right: 8px;
      }
      
      .price-tags {
        display: flex;
        gap: 4px;
        
        :deep(.van-tag) {
          height: 18px;
          line-height: 16px;
          font-size: 10px;
          padding: 0 4px;
        }
      }
    }
  }

  .item-quantity {
    text-align: right;
    min-width: 110px;

    .quantity {
      font-size: 14px;
      font-weight: 500;
      margin-bottom: 4px;
      color: #323233;
    }

    .price {
      font-size: 12px;
      color: #969799;
      margin-bottom: 4px;
    }

    .sub-total {
      font-size: 13px;
      font-weight: 600;
      color: #ee0a24;
      background: #ffeaea;
      padding: 2px 6px;
      border-radius: 3px;
      display: inline-block;
    }
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
      font-size: 15px;
    }

    .total-amount {
      font-weight: 700;
      color: #ee0a24;
      font-size: 16px;
    }
  }
}

// 关联采购订单样式
.purchase-order-info {
  background: #fafafa;
  border-radius: 6px;
  padding: 12px;
  cursor: pointer;
  position: relative;
  transition: background-color 0.2s;

  &:hover {
    background: #f0f0f0;
  }

  .purchase-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
  }

  .purchase-no {
    font-size: 14px;
    font-weight: 500;
    color: #1989fa;
  }

  .purchase-details {
    .purchase-item {
      display: flex;
      justify-content: space-between;
      font-size: 12px;
      margin-bottom: 4px;

      &:last-child {
        margin-bottom: 0;
      }

      .amount {
        color: #ee0a24;
        font-weight: 500;
      }
    }
  }

  .purchase-arrow {
    position: absolute;
    right: 8px;
    bottom: 8px;
    color: #969799;
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