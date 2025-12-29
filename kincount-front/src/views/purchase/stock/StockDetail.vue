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
    <div class="detail-content">
      <van-loading v-if="loading" class="page-loading" />
      
      <template v-else-if="stockData">
        <!-- 入库单基本信息 -->
        <van-cell-group title="入库单信息">
          <van-cell title="入库单号" :value="stockData.stock_no" />
          <van-cell title="供应商名称" :value="stockData.supplier?.name || '--'" />
          <van-cell title="仓库名称" :value="stockData.warehouse?.name || '--'" />
          <van-cell title="总金额" :value="`¥${formatPrice(stockData.total_amount)}`" />
          <van-cell title="状态">
            <template #value>
              <van-tag :type="getStatusTagType(stockData.status)">
                {{ getStatusText(stockData.status) }}
              </van-tag>
            </template>
          </van-cell>
          <van-cell title="创建人" :value="stockData.creator?.real_name || '--'" />
          <van-cell v-if="stockData.auditor" title="审核人" :value="stockData.auditor.real_name" />
          <van-cell v-if="stockData.audit_time" title="审核时间" :value="formatDateTime(stockData.audit_time)" />
          <van-cell title="创建时间" :value="formatDateTime(stockData.created_at)" />
          <van-cell v-if="stockData.remark" title="备注信息" :value="stockData.remark || '无'" />
          
        </van-cell-group>

        <!-- SKU明细 -->
        <van-cell-group title="SKU明细" v-if="stockData.items && stockData.items.length > 0">
          <div class="product-items">
            <template v-for="(item, index) in stockData.items" :key="index">
              <van-swipe-cell class="product-item">
                <van-cell class="product-cell">
                  <!-- 商品信息三行显示 -->
                  <template #title>
                    <div class="product-info">
                      <!-- 第一行：商品名称和规格文本、数量 -->
                      <div class="product-row-first">
                        <div class="product-name-specs">
                          <span class="product-name">{{ item.product?.name || '未知商品' }}</span>
                          <span class="product-specs" v-if="getSkuSpecs(item).length > 0">{{ getSkuSpecs(item).join(' ') }}</span>
                        </div>
                        <div class="product-quantity">{{ item.quantity }}{{ item.sku?.unit || item.product?.unit || '个' }}</div>
                      </div>
                      
                      <!-- 第二行：sku编号、单位、单价 -->
                      <div class="product-row-second">
                        <div class="product-sku">SKU: {{ item.sku?.sku_code || '--' }}</div>
                        <div class="product-unit">单位: {{ item.sku?.unit || item.product?.unit || '个' }}</div>
                        <div class="product-price">¥{{ formatPrice(item.price) }}</div>
                      </div>
                      
                      <!-- 第三行：其他信息、金额小计 -->
                      <div class="product-row-third">
                        <div class="product-cost">成本: ¥{{ formatPrice(item.sku?.cost_price) }}</div>
                        <div class="product-sale-price">售价: ¥{{ formatPrice(item.sku?.sale_price) }}</div>
                        <div class="product-total">¥{{ formatPrice(item.total_amount) }}</div>
                      </div>
                    </div>
                  </template>
                </van-cell>
              </van-swipe-cell>
              <!-- 手动添加分割线 -->
              <div v-show="index < stockData.items.length - 1" class="product-divider"></div>
            </template>
          </div>
          <div class="total-amount">
            <span>合计: {{ stockData.items.length }} 种商品</span>
            <span class="total-price">总金额: ¥{{ formatPrice(stockData.total_amount) }}</span>
          </div>
        </van-cell-group>
        
        <van-empty v-else description="暂无明细数据" />

        <!-- 关联采购订单信息 -->
        <van-cell-group title="关联采购订单" v-if="stockData.purchaseOrder">
          <van-cell 
            :title="stockData.purchaseOrder.order_no"
            is-link
            :value="`¥${formatPrice(stockData.purchaseOrder.total_amount)}`"
            @click="goToPurchaseOrder(stockData.purchase_order_id)"
            class="purchase-order-cell"
          >
            <template #right-icon>
              <van-tag :type="getPurchaseStatusTagType(stockData.purchaseOrder.status)">
                {{ getPurchaseStatusText(stockData.purchaseOrder.status) }}
              </van-tag>
            </template>
          </van-cell>
        </van-cell-group>
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
    0: '待审核',
    1: '已审核',
    2: '已取消'
  }
  return statusMap[status] || '未知'
}

// 入库单状态标签类型
const getStatusTagType = (status) => {
  const typeMap = {
    0: 'warning',  // 待审核 - 警告色
    1: 'success',  // 已审核 - 成功色
    2: 'danger'    // 已取消 - 危险色
  }
  return typeMap[status] || 'default'
}

// 采购订单状态文本映射
const getPurchaseStatusText = (status) => {
  console.log('状态值:', status, typeof status)
  const statusMap = {
    0: '待审核',
    1: '已审核',
    2: '部分入库',
    3: '已完成',
    4: '已取消'
  }
  return statusMap[status] || `未知状态(${status})`
}

// 采购订单状态标签类型
const getPurchaseStatusTagType = (status) => {
  const typeMap = {
    0: 'warning',
    1: 'primary',
    2: 'info',
    3: 'success',
    4: 'danger'
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
  return stockData.value?.status === 0 // 待审核
})

const canCancelAudit = computed(() => {
  return stockData.value?.status === 1 // 已审核
})

const canCancel = computed(() => {
  const status = stockData.value?.status
  return status === 0 // 仅待审核状态可以取消
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
            font-weight: 600;
            color: #323233;
            line-height: 1.4;
          }
          
          .product-specs {
            font-size: 12px;
            color: #969799;
            line-height: 1.3;
          }
        }
        
        .product-quantity {
          font-size: 14px;
          font-weight: 600;
          color: #323233;
          white-space: nowrap;
        }
      }
      
      .product-row-second {
        display: flex;
        justify-content: space-between;
        align-items: center;
        
        .product-sku {
          font-size: 12px;
          color: #969799;
          flex: 1;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }
        
        .product-unit {
          font-size: 12px;
          color: #969799;
          margin: 0 8px;
          white-space: nowrap;
        }
        
        .product-price {
          font-size: 13px;
          font-weight: 500;
          color: #323233;
          white-space: nowrap;
        }
      }
      
      .product-row-third {
        display: flex;
        justify-content: space-between;
        align-items: center;
        
        .product-cost {
          font-size: 12px;
          color: #646566;
          flex: 1;
          text-align: left;
          white-space: nowrap;
        }
        
        .product-sale-price {
          font-size: 12px;
          color: #646566;
          flex: 1;
          text-align: center;
          white-space: nowrap;
        }
        
        .product-total {
          font-size: 14px;
          font-weight: 600;
          color: #ee0a24;
          flex: 1;
          text-align: right;
          white-space: nowrap;
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

// 关联采购订单单元格样式
.purchase-order-cell {
  :deep(.van-cell__right-icon) {
    display: flex;
    align-items: center;
    gap: 8px;
    
    .van-tag {
      flex-shrink: 0;
    }
    
    .van-icon {
      flex-shrink: 0;
    }
  }
}

// 关联采购订单信息样式
.purchase-order-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
  
  .order-no {
    font-size: 15px;
    font-weight: 500;
    color: #323233;
  }
}
</style>