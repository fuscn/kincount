<template>
  <div class="sale-order-detail-page">
    <van-nav-bar title="采购订单详情" left-arrow left-text="返回" @click-left="$router.back()" fixed placeholder>
      <template #right>
        <van-button v-if="hasActions" type="primary" size="small" @click="showActionSheet = true">
          操作
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 加载状态 -->
    <van-loading v-if="loading" class="page-loading" />

    <div v-else class="detail-content">
      <!-- 订单基本信息 -->
      <van-cell-group title="订单信息">
        <van-cell title="订单号" :value="orderData.order_no" />
        <van-cell title="供应商名称" :value="orderData.supplier?.name || '--'" />
        <van-cell title="仓库名称" :value="orderData.warehouse?.name || '--'" />
        <van-cell title="预计到货" :value="formatDate(orderData.expected_date)" />
        <van-cell title="订单状态">
          <template #value>
            <van-tag :type="getStatusType(orderData.status)">
              {{ getStatusText(orderData.status) }}
            </van-tag>
          </template>
        </van-cell>
        <van-cell title="总金额" :value="`¥${formatPrice(orderData.total_amount)}`" />
        <van-cell title="已付金额" :value="`¥${formatPrice(orderData.paid_amount)}`" />
        <van-cell title="未付金额" :value="`¥${formatPrice(orderData.total_amount - orderData.paid_amount)}`" />
        <van-cell title="创建人" :value="orderData.creator?.real_name || orderData.creator?.username || '--'" />
        <van-cell title="创建时间" :value="formatDateTime(orderData.created_at)" />
        <van-cell v-if="orderData.auditor" title="审核人" :value="orderData.auditor?.real_name || orderData.auditor?.username || '--'" />
        <van-cell v-if="orderData.audit_time" title="审核时间" :value="formatDateTime(orderData.audit_time)" />
        <van-cell title="备注信息" :value="orderData.remark || '无'" />
      </van-cell-group>

        <!-- 商品明细 -->
        <van-cell-group title="商品明细" v-if="orderData.items && orderData.items.length > 0">
          <div class="product-items">
            <template v-for="(item, index) in orderData.items" :key="index">
              <van-swipe-cell class="product-item">
                <van-cell class="product-cell">
                  <!-- 商品信息三行显示 -->
                  <template #title>
                    <div class="product-info">
                      <!-- 第一行：商品名称和规格文本、数量 -->
                      <div class="product-row-first">
                        <div class="product-name-specs">
                          <span class="product-name">{{ getProductName(item) }}</span>
                          <span class="product-specs" v-if="getSkuSpecs(item).length > 0">{{ getSkuSpecs(item).join(' ') }}</span>
                        </div>
                        <div class="product-quantity">{{ item.quantity }}{{ item.sku?.unit || item.product?.unit || '个' }}</div>
                      </div>
                      
                      <!-- 第二行：sku编号、单位、单价 -->
                      <div class="product-row-second">
                        <div class="product-sku">SKU: {{ item.sku?.sku_code || '--' }}</div>
                        <div class="product-unit-price">
                          <span class="product-unit">单位: {{ item.sku?.unit || item.product?.unit || '个' }} </span>
                          <span class="product-price">¥{{ formatPrice(item.price) }}</span>
                        </div>
                      </div>
                      
                      <!-- 第三行：其他信息、金额小计 -->
                      <div class="product-row-third">
                        <div class="product-cost">成本: ¥{{ formatPrice(item.sku?.cost_price) }}</div>
                        <!-- 已入库数量 -->
                        <div class="processed-quantity" v-if="item.received_quantity !== undefined">
                          已入库: {{ item.received_quantity }}{{ item.sku?.unit || item.product?.unit || '个' }}
                        </div>
                        <div class="product-total">¥{{ formatPrice(item.total_amount) }}</div>
                      </div>
                    </div>
                  </template>
                </van-cell>
                <!-- 入库数量信息 -->
                <template #right v-if="item.received_quantity !== undefined && item.received_quantity < item.quantity">
                  <div class="stock-info">
                    <span class="remaining-quantity">待入库: {{ item.quantity - item.received_quantity }}{{ item.product?.unit || item.unit || '个' }}</span>
                  </div>
                </template>
              </van-swipe-cell>
              <!-- 手动添加分割线 -->
              <div v-show="index < orderData.items.length - 1" class="product-divider"></div>
            </template>
          </div>
          <div class="total-amount">
            <span>合计: {{ orderData.items.length }} 种商品</span>
            <span class="total-price">总金额: ¥{{ formatPrice(orderData.total_amount) }}</span>
          </div>
        </van-cell-group>

      <!-- 关联入库单 -->
      <van-cell-group title="关联入库单" v-if="relatedStocks && relatedStocks.length > 0">
        <van-cell 
          v-for="stock in relatedStocks" 
          :key="stock.id" 
          :title="stock.stock_no" 
          is-link
          :value="`¥${formatPrice(stock.total_amount)}`"
          @click="viewStockDetail(stock.id)"
        >
          <template #right-icon>
            <van-tag :type="getStockStatusType(stock.status)">
              {{ getStockStatusText(stock.status) }}
            </van-tag>
          </template>
        </van-cell>
      </van-cell-group>
    </div>

    <!-- 操作面板 -->
    <van-action-sheet v-model:show="showActionSheet" :actions="actions" cancel-text="取消" close-on-click-action
      @select="onActionSelect" />

    <!-- 确认对话框 -->
    <van-dialog v-model:show="showConfirmDialog" :title="confirmTitle" show-cancel-button @confirm="confirmAction">
      <div class="confirm-content">{{ confirmMessage }}</div>
    </van-dialog>

    <!-- 生成入库单对话框 -->
    <van-dialog v-model:show="showGenerateStockDialog" title="生成入库单" show-cancel-button @confirm="generatePurchaseStock"
      @cancel="showGenerateStockDialog = false">
      <div class="generate-stock-content">
        <div class="dialog-section">
          <h4>入库信息</h4>
          <div class="info-row">
            <span>供应商：</span>
            <span>{{ orderData?.supplier?.name || '--' }}</span>
          </div>
          <div class="info-row">
            <span>仓库：</span>
            <span>{{ orderData?.warehouse?.name || '--' }}</span>
          </div>
        </div>

        <div class="dialog-section">
          <h4>商品明细</h4>
          <div v-if="availableItems.length === 0" class="no-available-items">
            没有可入库的商品
          </div>
          <div v-else class="stock-items">
            <div v-for="item in availableItems" :key="item.id" class="stock-item-row">
              <div class="item-info">
                <div class="item-name">{{ getProductName(item) }}</div>
                <div class="item-specs" v-if="getSkuSpecs(item).length > 0">
                  <van-tag v-for="(spec, specIndex) in getSkuSpecs(item)" :key="specIndex" size="mini" type="primary"
                    plain>
                    {{ spec }}
                  </van-tag>
                </div>
                <div v-if="item.sku?.sku_code" class="item-sku">
                  SKU: {{ item.sku.sku_code }}
                </div>
              </div>
              <div class="quantity-control">
                <span class="max-quantity">可入库: {{ getAvailableQuantity(item) }}</span>
                <van-stepper v-model="item.stockQuantity" :max="getAvailableQuantity(item)" :min="0" integer
                  @change="updateStockTotal" />
              </div>
            </div>
          </div>
        </div>

        <div v-if="stockTotalAmount > 0" class="total-section">
          <div class="total-row">
            <span>入库总金额：</span>
            <span class="total-amount">¥{{ formatPrice(stockTotalAmount) }}</span>
          </div>
        </div>

        <div v-else class="no-items-hint">
          请选择要入库的商品数量
        </div>
      </div>
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
const showGenerateStockDialog = ref(false)

// 生成入库单相关
const stockTotalAmount = ref(0)
const relatedStocks = ref([])

// 确认操作相关
const currentAction = ref('')
const confirmTitle = ref('')
const confirmMessage = ref('')

// 格式化函数
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

const formatPrice = (price) => {
  if (price === null || price === undefined || price === '') return '0.00'
  const num = Number(price)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

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

// 获取状态文本
const getStatusText = (status) => {
  const statusMap = {
    0: '待审核',
    1: '已审核',
    2: '部分入库',
    3: '已完成',
    4: '已取消'
  }
  return statusMap[status] || '未知状态'
}

// 获取状态类型
const getStatusType = (status) => {
  const typeMap = {
    0: 'warning',
    1: 'primary',
    2: 'info',
    3: 'success',
    4: 'danger'
  }
  return typeMap[status] || 'default'
}

// 获取入库单状态文本
const getStockStatusText = (status) => {
  const statusMap = {
    0: '待审核',
    1: '已审核',
    2: '已取消'
  }
  return statusMap[status] || '未知状态'
}

// 获取入库单状态类型
const getStockStatusType = (status) => {
  const typeMap = {
    0: 'warning',
    1: 'success',
    2: 'danger'
  }
  return typeMap[status] || 'default'
}

// 获取商品名称
const getProductName = (item) => {
  return item.product?.name || '未知商品'
}

// 获取SKU规格信息
const getSkuSpecs = (item) => {
  const specs = []

  // 优先从sku.spec获取规格信息
  if (item.sku?.spec) {
    if (typeof item.sku.spec === 'object') {
      // 处理对象形式的规格
      Object.entries(item.sku.spec).forEach(([key, value]) => {
        specs.push(`${key}:${value}`)
      })
    } else {
      // 处理字符串形式的规格
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

// 获取可入库数量
const getAvailableQuantity = (item) => {
  const ordered = item.quantity || 0
  const received = item.received_quantity || 0
  return Math.max(0, ordered - received)
}

// 可生成入库单的商品列表
const availableItems = computed(() => {
  if (!orderData.value?.items) return []

  return orderData.value.items
    .filter(item => getAvailableQuantity(item) > 0)
    .map(item => ({
      ...item,
      stockQuantity: getAvailableQuantity(item)
    }))
})

// 操作权限判断
const canAudit = computed(() => {
  return orderData.value?.status === 0 // 待审核
})

const canCancel = computed(() => {
  const status = orderData.value?.status
  return status === 0 // 仅待审核状态可以取消
})

const canComplete = computed(() => {
  const status = orderData.value?.status
  return [1, 2].includes(status) // 已审核、部分入库
})

const canEdit = computed(() => {
  const status = orderData.value?.status
  return [0].includes(status) // 仅待审核状态可编辑
})

const canDelete = computed(() => {
  const status = orderData.value?.status
  return [0, 4].includes(status) // 待审核、已取消
})

const canGenerateStock = computed(() => {
  // 已审核状态且有待入库的商品
  const status = orderData.value?.status
  return [1, 2].includes(status) && availableItems.value.length > 0
})

// 是否有可用操作
const hasActions = computed(() => {
  return canAudit.value || canCancel.value || canComplete.value || canEdit.value || canDelete.value || canGenerateStock.value
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

  if (canGenerateStock.value) {
    actionList.push({
      name: '生成入库单',
      action: 'generateStock',
      color: '#7232dd'
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

    // 加载关联的入库单
    await loadRelatedStocks(orderId)

    console.log('订单详情数据:', orderData.value)
  } catch (error) {
    console.error('加载订单详情失败:', error)
    showFailToast('加载订单详情失败')
  } finally {
    loading.value = false
  }
}

// 加载关联入库单
const loadRelatedStocks = async (orderId) => {
  try {
    const result = await purchaseStore.loadStocksByOrderId(orderId)
    if (result) {
      relatedStocks.value = result
    }
  } catch (error) {
    console.error('加载关联入库单失败:', error)
    relatedStocks.value = []
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
    case 'generateStock':
      handleGenerateStock()
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

// 处理生成入库单
const handleGenerateStock = () => {
  if (availableItems.value.length === 0) {
    showFailToast('没有可入库的商品')
    return
  }

  // 计算初始总金额（使用默认的最大可入数量）
  updateStockTotal()

  showGenerateStockDialog.value = true
}

// 更新入库单总金额
const updateStockTotal = () => {
  let total = 0
  availableItems.value.forEach(item => {
    if (item.stockQuantity > 0) {
      total += (item.stockQuantity * (item.price || 0))
    }
  })
  stockTotalAmount.value = total
}

// 生成采购入库单
const generatePurchaseStock = async () => {
  // 检查是否有选择商品
  const selectedItems = availableItems.value.filter(item => item.stockQuantity > 0)
  if (selectedItems.length === 0) {
    showFailToast('请选择要入库的商品')
    return
  }

  try {
    // 构建入库单数据
    const stockData = {
      purchase_order_id: orderData.value.id,
      supplier_id: orderData.value.supplier_id,
      warehouse_id: orderData.value.warehouse_id,
      total_amount: stockTotalAmount.value,
      remark: `由采购订单 ${orderData.value.order_no} 生成`,
      items: selectedItems.map(item => ({
        product_id: item.product_id,
        sku_id: item.sku_id,
        quantity: item.stockQuantity,
        price: item.price,
        total_amount: item.stockQuantity * item.price
      }))
    }

    console.log('生成的入库单数据:', stockData)

    const result = await purchaseStore.addStock(stockData)
    if (result) {
      showSuccessToast('生成入库单成功')
      showGenerateStockDialog.value = false

      // 重新加载页面数据
      await loadOrderDetail()
    }
  } catch (error) {
    console.error('生成入库单失败:', error)
    showFailToast(error.message || '生成入库单失败')
  }
}

// 查看入库单详情
const viewStockDetail = (stockId) => {
  router.push(`/purchase/stock/detail/${stockId}`)
}

onMounted(() => {
  loadOrderDetail()
})
</script>

<style scoped lang="scss">
.sale-order-detail-page {
  background-color: #f7f8fa;
  min-height: 100vh;
}

.detail-content {
  padding-bottom: 60px;
}

.page-loading {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 999;
}

.product-items {
  .product-item {
    .product-cell {
      padding: 12px 16px;
      
      :deep(.van-cell__title) {
        width: 100%;
      }
    }
    
    .product-info {
      display: flex;
      flex-direction: column;
      gap: 6px;
      width: 100%;
      
      .product-row-first {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        
        .product-name-specs {
          flex: 1;
          display: flex;
          flex-direction: row;
          align-items: center;
          gap: 8px;
          margin-right: 12px;
          
          .product-name {
            font-size: 15px;
            font-weight: 500;
            color: #323233;
            line-height: 1.4;
          }
          
          .product-specs {
            font-size: 12px;
            color: #969799;
            line-height: 1.4;
          }
        }
        
        .product-quantity {
          flex-shrink: 0;
          font-size: 15px;
          font-weight: bold;
          color: #323233;
        }
      }
      
      .product-row-second {
        display: flex;
        justify-content: space-between;
        align-items: center;
        
        .product-sku {
          flex: 1;
          font-size: 12px;
          color: #646566;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }
        
        .product-unit-price {
          display: flex;
          align-items: center;
          justify-content: flex-end;
          
          .product-unit {
            font-size: 12px;
            color: #646566;
            margin-right: 8px;
          }
          
          .product-price {
            color: #f53f3f;
            font-weight: 500;
            font-size: 13px;
          }
        }
      }
      
      .product-row-third {
        display: flex;
        justify-content: space-between;
        align-items: center;
        
        .product-cost {
          flex: 1;
          color: #07c160;
          font-size: 12px;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }
        
        .processed-quantity {
          flex: 1;
          color: #07c160;
          font-size: 12px;
          text-align: center;
        }
        
        .product-total {
          flex: 1;
          color: #ee0a24;
          font-weight: bold;
          font-size: 14px;
          text-align: right;
        }
      }
    }
  }
}

.product-divider {
  height: 1px;
  background-color: #ebedf0;
  margin: 8px 16px;
}

.stock-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 0 12px;
  font-size: 12px;
}

.processed-quantity {
  color: #07c160;
}

.remaining-quantity {
  color: #ff976a;
}

.total-amount {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background-color: #f7f8fa;
  font-size: 14px;
  
  .total-price {
    color: #f53f3f;
    font-weight: bold;
    font-size: 16px;
  }
}

.order-no {
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

    &.unpaid {
      color: #ff976a;
    }
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
      margin-bottom: 2px;
    }

    .item-sku {
      font-size: 11px;
      color: #646566;
      background: #f5f5f5;
      padding: 1px 4px;
      border-radius: 2px;
      display: inline-block;
    }
  }

  .item-quantity {
    text-align: right;
    min-width: 100px;

    .quantity {
      font-size: 14px;
      font-weight: 500;
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
      background: #e7f7f0;
      padding: 1px 4px;
      border-radius: 2px;
      display: inline-block;
    }
  }

  .item-total {
    font-weight: 600;
    color: #ee0a24;
    min-width: 80px;
    text-align: right;
    font-size: 14px;
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

// 关联入库单样式
.stock-list {
  .stock-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
  }

  .stock-no {
    font-size: 14px;
    font-weight: 500;
  }

  .stock-info {
    .stock-item {
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
}

// 生成入库单对话框样式
.generate-stock-content {
  padding: 16px;

  .dialog-section {
    margin-bottom: 16px;

    h4 {
      margin: 0 0 12px 0;
      font-size: 14px;
      color: #323233;
      font-weight: 600;
      padding-bottom: 8px;
      border-bottom: 1px solid #f5f5f5;
    }
  }

  .no-available-items {
    text-align: center;
    color: #969799;
    font-size: 14px;
    padding: 20px 0;
  }

  .info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 14px;

    &:last-child {
      margin-bottom: 0;
    }
  }

  .stock-items {
    max-height: 300px;
    overflow-y: auto;
    margin: 0 -16px;
    padding: 0 16px;
  }

  .stock-item-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
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
      margin-bottom: 4px;
      font-weight: 500;
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

    .item-sku {
      font-size: 11px;
      color: #646566;
      background: #f5f5f5;
      padding: 1px 4px;
      border-radius: 2px;
      display: inline-block;
    }
  }

  .quantity-control {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
    min-width: 120px;

    .max-quantity {
      font-size: 11px;
      color: #969799;
    }
  }

  .total-section {
    border-top: 1px solid #f5f5f5;
    padding-top: 12px;
    margin-top: 12px;

    .total-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 16px;
      font-weight: 600;

      .total-amount {
        color: #ee0a24;
      }
    }
  }

  .no-items-hint {
    text-align: center;
    color: #969799;
    font-size: 14px;
    padding: 20px 0;
  }
}

// 生成入库单对话框样式
.generate-stock-content {
  padding: 16px;
  max-height: 70vh;
  overflow-y: auto;

  .dialog-section {
    margin-bottom: 16px;

    h4 {
      margin: 0 0 12px 0;
      font-size: 16px;
      color: #323233;
    }

    .info-row {
      display: flex;
      margin-bottom: 8px;
      font-size: 14px;

      span:first-child {
        width: 80px;
        color: #646566;
      }

      span:last-child {
        flex: 1;
        color: #323233;
      }
    }
  }

  .stock-items {
    .stock-item-row {
      display: flex;
      align-items: center;
      padding: 12px 0;
      border-bottom: 1px solid #ebedf0;

      &:last-child {
        border-bottom: none;
      }

      .item-info {
        flex: 1;
        margin-right: 12px;

        .item-name {
          font-size: 14px;
          font-weight: 500;
          color: #323233;
          margin-bottom: 4px;
        }

        .item-specs {
          margin-bottom: 4px;

          .van-tag {
            margin-right: 4px;
            margin-bottom: 4px;
          }
        }

        .item-sku {
          font-size: 11px;
          color: #646566;
          background: #f5f5f5;
          padding: 1px 4px;
          border-radius: 2px;
          display: inline-block;
        }
      }

      .quantity-control {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
        min-width: 120px;

        .max-quantity {
          font-size: 11px;
          color: #969799;
        }
      }
    }
  }

  .total-section {
    border-top: 1px solid #f5f5f5;
    padding-top: 12px;
    margin-top: 12px;

    .total-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 16px;
      font-weight: 600;

      .total-amount {
        color: #ee0a24;
      }
    }
  }

  .no-items-hint {
    text-align: center;
    color: #969799;
    font-size: 14px;
    padding: 20px 0;
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

    &[style*="color: #1989fa"] {
      color: #1989fa !important;
    }

    &[style*="color: #07c160"] {
      color: #07c160 !important;
    }

    &[style*="color: #7232dd"] {
      color: #7232dd !important;
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