<template>
  <div class="sale-stock-detail">
    <!-- 导航栏 -->
    <van-nav-bar 
      title="销售出库详情"
      left-text="返回"
      left-arrow
      @click-left="$router.back()"
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

    <!-- 加载状态 -->
    <van-loading v-if="loading" class="page-loading" />

    <!-- 内容区域 -->
    <div v-if="!loading" class="detail-content">
      <!-- 出库基本信息 -->
      <van-cell-group title="出库信息">
        <van-cell title="出库单号" :value="stockData.stock_no" />
        <van-cell title="关联销售订单" :value="stockData.saleOrder?.order_no || '--'" />
        <van-cell title="客户名称" :value="stockData.customer?.name || '--'" />
        <van-cell title="出库日期" :value="formatDate(stockData.created_at)" />
        <van-cell title="出库状态">
          <template #value>
            <van-tag :type="getStatusTagType(stockData.status)">
              {{ getStatusText(stockData.status) }}
            </van-tag>
          </template>
        </van-cell>
        <van-cell title="出库金额" :value="`¥${formatPrice(stockData.total_amount)}`" />
        <van-cell title="仓库" :value="stockData.warehouse?.name || '--'" />
        <van-cell title="备注信息" :value="stockData.remark || '无'" />
        <van-cell title="创建人" :value="stockData.creator?.real_name || stockData.creator?.username || '--'" />
        <van-cell v-if="stockData.auditor" title="审核人" :value="stockData.auditor?.real_name || stockData.auditor?.username || '--'" />
        <van-cell v-if="stockData.audit_time" title="审核时间" :value="formatDate(stockData.audit_time)" />
      </van-cell-group>

        <!-- SKU明细 -->
        <div class="section">
          <h3 class="section-title">SKU明细</h3>
          <van-empty v-if="!stockData.items || stockData.items.length === 0" description="暂无明细数据" />
          <div v-else class="items-list">
            <template v-for="(item, index) in stockData.items" :key="item.id || index">
              <van-swipe-cell class="product-item">
                <van-cell class="product-cell">
                  <!-- 商品信息三行显示 -->
                  <template #title>
                    <div class="product-info">
                      <!-- 第一行：商品名称、规格文本、数量 -->
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
                        <div class="product-total">¥{{ formatPrice(item.total_amount) }}</div>
                      </div>
                    </div>
                  </template>
                </van-cell>
              </van-swipe-cell>
              <!-- 手动添加分割线 -->
              <div v-if="index < stockData.items.length - 1" class="product-divider"></div>
            </template>

            <!-- 合计 -->
            <div class="total-row">
              <div class="total-label">合计：</div>
              <div class="total-amount">¥{{ formatPrice(stockData.total_amount) }}</div>
            </div>
          </div>
        </div>

        <!-- 操作记录 -->
        <div class="section">
          <h3 class="section-title">操作记录</h3>
          <div class="action-records">
            <div class="action-record">
              <div class="action-info">
                <div class="action-title">创建出库单</div>
                <div class="action-detail">{{ stockData.creator?.real_name || '系统' }} | {{ formatDateTime(stockData.created_at) }}</div>
              </div>
              <div class="action-status">已创建</div>
            </div>
            
            <div v-if="stockData.auditor" class="action-record">
              <div class="action-info">
                <div class="action-title">审核出库单</div>
                <div class="action-detail">{{ stockData.auditor.real_name }} | {{ formatDateTime(stockData.audit_time) }}</div>
              </div>
              <div class="action-status">已审核</div>
            </div>
            
            <div v-if="stockData.status === 2" class="action-record">
              <div class="action-info">
                <div class="action-title">完成出库</div>
                <div class="action-detail">系统 | {{ formatDateTime(stockData.updated_at) }}</div>
              </div>
              <div class="action-status">已完成</div>
            </div>
            
            <div v-if="stockData.status === 3" class="action-record">
              <div class="action-info">
                <div class="action-title">取消出库单</div>
                <div class="action-detail">系统 | {{ formatDateTime(stockData.updated_at) }}</div>
              </div>
              <div class="action-status">已取消</div>
            </div>
          </div>
        </div>
    </div>
      
    <van-empty v-else description="出库单不存在" />

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
import { PERM } from '@/constants/permissions'
import { useSaleStore } from '@/store/modules/sale'
import { auditSaleStock, cancelSaleStock, completeSaleStock } from '@/api/sale'
import dayjs from 'dayjs'

const route = useRoute()
const router = useRouter()
const saleStore = useSaleStore()

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

// 销售出库状态文本映射 - 更新为匹配数据库
const getStatusText = (status) => {
  const statusMap = {
    0: '待审核',  // 数据库中0-待审核
    1: '已审核',  // 数据库中1-已审核
    2: '已取消'   // 数据库中2-已取消
  }
  return statusMap[status] || '未知'
}

// 销售出库状态标签类型 - 更新为匹配数据库
const getStatusTagType = (status) => {
  const typeMap = {
    0: 'warning',   // 待审核 - 警告色
    1: 'primary',   // 已审核 - 蓝色
    2: 'danger'     // 已取消 - 红色
  }
  return typeMap[status] || 'default'
}

// 跳转到销售订单
const goToSaleOrder = (orderId) => {
  if (orderId) {
    router.push(`/sale/order/detail/${orderId}`)
  }
}

// 操作权限判断
const canAudit = computed(() => {
  return stockData.value?.status === 0 // 待审核（数据库中0-待审核）
})

const canComplete = computed(() => {
  return stockData.value?.status === 1 // 已审核（数据库中1-已审核）
})

const canCancel = computed(() => {
  const status = stockData.value?.status
  return [0, 1].includes(status) // 待审核、已审核（数据库中0-待审核，1-已审核）
})

// 是否有可用操作
const hasActions = computed(() => {
  return canAudit.value || canComplete.value || canCancel.value
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
  
  if (canComplete.value) {
    actionList.push({
      name: '完成出库',
      action: 'complete',
      color: '#1989fa'
    })
  }
  
  if (canCancel.value) {
    actionList.push({
      name: '取消出库单',
      action: 'cancel',
      color: '#ee0a24'
    })
  }
  
  return actionList
})

// 加载出库详情
const loadStockDetail = async () => {
  const stockId = route.params.id
  if (!stockId) {
    showFailToast('出库单ID不存在')
    return
  }

  try {
    loading.value = true
    await saleStore.loadStockDetail(stockId)
    stockData.value = saleStore.currentStock
    console.log('销售出库详情数据:', stockData.value)
  } catch (error) {
    console.error('加载出库详情失败:', error)
    showFailToast('加载出库详情失败: ' + (error.message || '未知错误'))
  } finally {
    loading.value = false
  }
}

// 操作面板选择
const onActionSelect = (action) => {
  showActionSheet.value = false
  
  switch (action.action) {
    case 'audit':
      showConfirm('审核出库单', '确定要审核通过此出库单吗？审核通过后，出库单将标记为已审核状态。', 'audit')
      break
    case 'complete':
      showConfirm('完成出库', '确定要完成此出库单吗？完成操作将实际扣减库存并更新销售订单状态。', 'complete')
      break
    case 'cancel':
        const message = stockData.value.status === 1 
          ? '此出库单已审核，确定要取消吗？这会影响库存扣减和销售订单状态。'
          : '确定要取消这个销售出库单吗？此操作不可恢复。'
        showConfirm('取消出库单', message, 'cancel')
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
        result = await auditSaleStock(stockData.value.id)
        if (result) showSuccessToast('审核成功')
        break
      case 'complete':
        result = await completeSaleStock(stockData.value.id)
        if (result) showSuccessToast('完成成功')
        break
      case 'cancel':
        result = await cancelSaleStock(stockData.value.id)
        if (result) {
          showSuccessToast('取消成功')
          router.push('/sale/stock')
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
.sale-stock-detail {
  background: #f7f8fa;
  min-height: 100vh;
}

.detail-content {
  padding: 16px;
}

.page-loading {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 999;
}

.section {
  margin-bottom: 16px;
  background-color: #fff;
  border-radius: 8px;
  overflow: hidden;
}

.section-title {
  padding: 12px 16px;
  font-size: 16px;
  font-weight: 600;
  color: #323233;
  background-color: #f7f8fa;
  border-bottom: 1px solid #ebedf0;
}

.items-list {
  .product-item {
    .product-cell {
      .product-info {
        width: 100%;
      }
      
      .product-row-first {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 6px;
        
        .product-name-specs {
          flex: 2;
          display: flex;
          align-items: center;
          
          .product-name {
            font-weight: bold;
            color: #323233;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 70%;
          }
          
          .product-specs {
            color: #969799;
            font-size: 12px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-left: 6px;
          }
        }
        
        .product-quantity {
          flex: 1;
          text-align: right;
          color: #323233;
          font-size: 14px;
        }
      }
      
      .product-row-second {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 6px;
        
        .product-sku {
          flex: 1;
          color: #646566;
          font-size: 12px;
          background: #f5f5f5;
          padding: 2px 4px;
          border-radius: 3px;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }
        
        .product-unit {
          flex: 1;
          color: #969799;
          font-size: 12px;
          text-align: center;
        }
        
        .product-price {
          flex: 1;
          color: #323233;
          font-size: 13px;
          text-align: right;
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
        
        .product-total {
          flex: 1;
          color: #f53f3f;
          font-weight: bold;
          font-size: 14px;
          text-align: right;
        }
      }
    }
  }
  
  .product-divider {
    height: 1px;
    background-color: #ebedf0;
    margin-left: 16px;
    margin-right: 16px;
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

// 操作记录样式
.action-records {
  .action-record {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f5f5f5;

    &:last-child {
      border-bottom: none;
    }
  }

  .action-info {
    flex: 1;
  }

  .action-title {
    font-size: 14px;
    font-weight: 500;
    color: #323233;
    margin-bottom: 4px;
  }

  .action-detail {
    font-size: 12px;
    color: #969799;
  }

  .action-status {
    font-size: 12px;
    color: #07c160;
    background: #f0f9f4;
    padding: 4px 8px;
    border-radius: 4px;
    white-space: nowrap;
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
    
    &[style*="color: #1989fa"] {
      color: #1989fa !important;
    }
    
    &[style*="color: #ee0a24"] {
      color: #ee0a24 !important;
    }
  }
}
</style>