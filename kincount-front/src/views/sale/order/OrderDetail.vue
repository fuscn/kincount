<template>
  <div class="sale-order-detail-page">
    <van-nav-bar 
      title="销售订单详情" 
      left-text="返回"
      left-arrow
      @click-left="$router.back()"
    >
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
        <van-cell title="客户名称" :value="orderData.customer?.name || '--'" />
        <van-cell title="仓库名称" :value="orderData.warehouse?.name || '--'" />
        <van-cell title="预计交货" :value="formatDate(orderData.expected_date)" />
        <van-cell title="订单状态">
          <template #value>
            <van-tag :type="getStatusType(orderData.status)">
              {{ getStatusText(orderData.status) }}
            </van-tag>
          </template>
        </van-cell>
        <van-cell title="总金额" :value="`¥${formatPrice(orderData.total_amount)}`" />
        <van-cell title="折扣金额" :value="`-¥${formatPrice(orderData.discount_amount)}`" />
        <van-cell title="实收金额" :value="`¥${formatPrice(orderData.final_amount)}`" />
        <van-cell title="已收金额" :value="`¥${formatPrice(orderData.paid_amount)}`" />
        <van-cell title="未收金额" :value="`¥${formatPrice(orderData.final_amount - orderData.paid_amount)}`" />
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
                    <!-- 第一行：商品名称、规格文本、数量 -->
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
                      <!-- 已出库数量 -->
                      <div class="processed-quantity" v-if="item.delivered_quantity !== undefined">
                        已出库: {{ item.delivered_quantity }}{{ item.sku?.unit || item.product?.unit || '个' }}
                      </div>
                      <div class="product-total">¥{{ formatPrice(item.total_amount) }}</div>
                    </div>
                  </div>
                </template>
              </van-cell>
              <!-- 出库数量信息 -->
              <template #right v-if="item.delivered_quantity !== undefined && item.delivered_quantity < item.quantity">
                <div class="stock-info">
                  <span class="remaining-quantity">待出库: {{ item.quantity - item.delivered_quantity }}{{ item.product?.unit || item.unit || '个' }}</span>
                </div>
              </template>
            </van-swipe-cell>
            <!-- 手动添加分割线 -->
            <div v-if="index < orderData.items.length - 1" class="product-divider"></div>
          </template>
        </div>
        <div class="total-amount">
          <span>合计: {{ orderData.items.length }} 种商品</span>
          <span class="total-price">总金额: ¥{{ formatPrice(orderData.total_amount) }}</span>
        </div>
      </van-cell-group>
      <van-cell-group title="商品明细" v-else>
        <van-empty description="暂无商品明细" image="search" />
      </van-cell-group>

      <!-- 关联出库单 -->
      <van-cell-group title="关联出库单" v-if="relatedStocks && relatedStocks.length > 0">
        <div v-for="stock in relatedStocks" :key="stock.id" class="stock-card" @click="viewStockDetail(stock.id)">
          <van-cell :title="stock.stock_no" is-link>
            <template #right-icon>
              <van-tag :type="getStockStatusType(stock.status)">
                {{ getStockStatusText(stock.status) }}
              </van-tag>
            </template>
          </van-cell>
          <van-cell title="总金额" :value="`¥${formatPrice(stock.total_amount)}`" />
          <van-cell title="审核人" :value="stock.audit_by ? (stock.auditor?.real_name || '--') : '未审核'" />
          <van-cell title="创建时间" :value="formatDateTime(stock.created_at)" />
        </div>
      </van-cell-group>
    </div>

    <!-- 操作面板 -->
    <van-action-sheet v-model:show="showActionSheet" :actions="actions" cancel-text="取消" close-on-click-action
      @select="onActionSelect" />

    <!-- 确认对话框 -->
    <van-dialog v-model:show="showConfirmDialog" :title="confirmTitle" show-cancel-button @confirm="confirmAction">
      <div class="confirm-content">{{ confirmMessage }}</div>
    </van-dialog>

    <!-- 生成出库单对话框 -->
    <van-dialog v-model:show="showGenerateStockDialog" title="生成出库单" show-cancel-button @confirm="generateSaleStock"
      @cancel="showGenerateStockDialog = false">
      <div class="generate-stock-content">
        <div class="dialog-section">
          <h4>出库信息</h4>
          <div class="info-row">
            <span>客户：</span>
            <span>{{ orderData?.customer?.name || '--' }}</span>
          </div>
          <div class="info-row">
            <span>仓库：</span>
            <span>{{ orderData?.warehouse?.name || '--' }}</span>
          </div>
        </div>

        <div class="dialog-section">
          <h4>商品明细</h4>
          <div v-if="availableItems.length === 0" class="no-available-items">
            没有可出库的商品
          </div>
          <div v-else class="stock-items">
            <template v-for="(item, index) in availableItems" :key="item.id">
              <van-swipe-cell class="product-item">
                <van-cell class="product-cell">
                  <template #title>
                    <div class="product-title">
                      <span class="product-name">{{ getProductName(item) }}</span>
                      <span class="sku-code" v-if="item.sku?.sku_code">{{ item.sku?.sku_code }}</span>
                    </div>
                  </template>
                  <template #label>
                    <div class="product-label">
                      <div class="spec-text" v-if="getSkuSpecs(item).length > 0">规格: {{ getSkuSpecs(item).join(' ') }}</div>
                      <div class="stock-text">
                        商品编号: {{ item.product?.product_no || '--' }}
                        <span class="barcode-info" v-if="item.sku?.barcode">条形码: {{ item.sku?.barcode }}</span>
                      </div>
                    </div>
                  </template>
                  <template #default>
                    <div class="item-details">
                      <div class="quantity-total-column">
                        <!-- 总金额 -->
                        <div class="item-total">
                          <div class="total-amount">¥{{ formatPrice(item.price * item.stockQuantity) }}</div>
                        </div>
                        <!-- 数量 -->
                        <div class="quantity-display">
                          <span class="quantity-text">可出库: {{ getAvailableQuantity(item) }}{{ item.product?.unit || item.unit || '个' }}</span>
                        </div>
                        <!-- 数量选择器 -->
                        <div class="quantity-display">
                          <van-stepper v-model="item.stockQuantity" :max="getAvailableQuantity(item)" :min="0" integer
                            @change="updateStockTotal" />
                        </div>
                      </div>
                    </div>
                  </template>
                </van-cell>
              </van-swipe-cell>
            </template>
          </div>
        </div>

        <div v-if="stockTotalAmount > 0" class="total-section">
          <div class="total-row">
            <span>出库总金额：</span>
            <span class="total-amount">¥{{ formatPrice(stockTotalAmount) }}</span>
          </div>
        </div>

        <div v-else class="no-items-hint">
          请选择要出库的商品数量
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
import { useSaleStore } from '@/store/modules/sale'

const route = useRoute()
const router = useRouter()
const saleStore = useSaleStore()

const loading = ref(true)
const orderData = ref(null)
const showActionSheet = ref(false)
const showConfirmDialog = ref(false)
const showGenerateStockDialog = ref(false)

// 生成出库单相关
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
    2: '部分出库',
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

// 获取出库单状态文本
const getStockStatusText = (status) => {
  const statusMap = {
    0: '待审核',
    1: '已审核',
    2: '已取消'
  }
  return statusMap[status] || '未知状态'
}

// 获取出库单状态类型
const getStockStatusType = (status) => {
  const typeMap = {
    0: 'warning',
    1: 'primary',
    2: 'danger'
  }
  return typeMap[status] || 'default'
}

// 获取商品名称
const getProductName = (item) => {
  return item.product?.name || item.product_name || '未知商品'
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

// 获取可出库数量
const getAvailableQuantity = (item) => {
  const ordered = item.quantity || 0
  const delivered = item.delivered_quantity || 0
  return Math.max(0, ordered - delivered)
}

// 可生成出库单的商品列表
const availableItems = computed(() => {
  if (!orderData.value?.items) return []

  return orderData.value.items
    .filter(item => getAvailableQuantity(item) > 0)
    .map(item => ({
      ...item,
      stockQuantity: 0
    }))
})

// 操作权限判断
const canAudit = computed(() => {
  return orderData.value?.status === 0 // 待审核
})

const canCancel = computed(() => {
  const status = orderData.value?.status
  return [0, 1, 2].includes(status) // 待审核、已审核、部分出库
})

const canComplete = computed(() => {
  const status = orderData.value?.status
  return [2, 3].includes(status) // 已审核、部分出库
})

const canEdit = computed(() => {
  const status = orderData.value?.status
  return [1].includes(status) // 仅待审核状态可编辑
})

const canDelete = computed(() => {
  const status = orderData.value?.status
  return [1, 5].includes(status) // 待审核、已取消
})

const canGenerateStock = computed(() => {
  // 已审核状态且有待出库的商品
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
      name: '生成出库单',
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
    await saleStore.loadOrderDetail(orderId)
    orderData.value = saleStore.currentOrder

    // 加载关联的出库单
    await loadRelatedStocks(orderId)

  } catch (error) {
    showFailToast('加载订单详情失败')
  } finally {
    loading.value = false
  }
}

// 加载关联出库单 - 需要根据实际情况调整API
const loadRelatedStocks = async (orderId) => {
  try {
    // 这里需要根据实际情况调用API获取关联的出库单
    // 暂时先设置为空数组
    relatedStocks.value = []

    // 示例：如果有获取关联出库单的API
    // const result = await saleStore.loadStocksByOrderId(orderId)
    // if (result) {
    //   relatedStocks.value = result
    // }
  } catch (error) {
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
      showConfirm('审核订单', '确定要审核通过此销售订单吗？', 'audit')
      break
    case 'cancel':
      showConfirm('取消订单', '确定要取消此销售订单吗？', 'cancel')
      break
    case 'complete':
      showConfirm('标记完成', '确定要标记此销售订单为已完成吗？', 'complete')
      break
    case 'delete':
      showConfirm('删除订单', '确定要删除此销售订单吗？此操作不可恢复！', 'delete')
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
        try {
          // 先显示加载状态
          loading.value = true
          // 执行审核
          await saleStore.auditOrder(orderData.value.id)
          // 从store获取最新数据
          orderData.value = { ...saleStore.currentOrder }
          showSuccessToast('审核成功')
        } catch (error) {
          showFailToast(error.message || '审核失败')
        } finally {
          loading.value = false
        }
        break
      case 'cancel':
        result = await saleStore.cancelOrder(orderData.value.id)
        if (result) {
          showSuccessToast('取消成功')
          // 重新加载订单详情
          await loadOrderDetail()
        }
        break
      case 'complete':
        result = await saleStore.completeOrder(orderData.value.id)
        if (result) {
          showSuccessToast('标记完成成功')
          // 重新加载订单详情
          await loadOrderDetail()
        }
        break
      case 'delete':
        result = await saleStore.deleteOrder(orderData.value.id)
        if (result) {
          showSuccessToast('删除成功')
          router.push('/sale/order')
          return
        }
        break
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
  router.push(`/sale/order/edit/${orderData.value.id}`)
}

// 处理生成出库单
const handleGenerateStock = () => {
  if (availableItems.value.length === 0) {
    showFailToast('没有可出库的商品')
    return
  }

  // 重置数量选择 - 修改这里，将默认值从 0 改为可出库数量
  availableItems.value.forEach(item => {
    // item.stockQuantity = 0 // 原来的代码
    item.stockQuantity = getAvailableQuantity(item) // 修改后的代码
  })
  stockTotalAmount.value = 0

  // 添加这行，更新总金额
  updateStockTotal()

  showGenerateStockDialog.value = true
}

// 更新出库单总金额
const updateStockTotal = () => {
  let total = 0
  availableItems.value.forEach(item => {
    if (item.stockQuantity > 0) {
      total += (item.stockQuantity * (item.price || 0))
    }
  })
  stockTotalAmount.value = total
}

// 生成销售出库单 - 修改后跳转到出库单详情
const generateSaleStock = async () => {
  // 检查是否有选择商品
  const selectedItems = availableItems.value.filter(item => item.stockQuantity > 0)
  if (selectedItems.length === 0) {
    showFailToast('请选择要出库的商品')
    return
  }

  try {
    // 构建出库单数据
    const stockData = {
      sale_order_id: orderData.value.id,
      customer_id: orderData.value.customer_id,
      warehouse_id: orderData.value.warehouse_id,
      total_amount: stockTotalAmount.value,
      remark: `由销售订单 ${orderData.value.order_no} 生成`,
      items: selectedItems.map(item => ({
        product_id: item.product_id,
        sku_id: item.sku_id,
        quantity: item.stockQuantity,
        price: item.price,
        total_amount: item.stockQuantity * item.price
      }))
    }

    const result = await saleStore.addStock(stockData)
    if (result) {
      showSuccessToast('生成出库单成功')
      showGenerateStockDialog.value = false

      // 获取出库单ID并跳转到出库单详情页
      let stockId = null

      // 处理不同的响应结构
      if (result.id) {
        stockId = result.id
      } else if (result.data && result.data.id) {
        stockId = result.data.id
      } else if (result.code === 200 && result.data) {
        // 如果是标准响应结构
        if (result.data.id) {
          stockId = result.data.id
        } else if (result.data.stock_id) {
          stockId = result.data.stock_id
        }
      }

      if (stockId) {
        // 延迟跳转，让用户看到成功提示
        setTimeout(() => {
          router.push(`/sale/stock/detail/${stockId}`)
        }, 500)
      } else {
        // 如果没有获取到ID，重新加载订单详情
        await loadOrderDetail()
      }
    }
  } catch (error) {
    showFailToast(error.message || '生成出库单失败')
  }
}

// 查看出库单详情
const viewStockDetail = (stockId) => {
  router.push(`/sale/stock/detail/${stockId}`)
}

onMounted(() => {
  loadOrderDetail()
})
</script>

<style scoped lang="scss">
.sale-order-detail-page {
  background: #f7f8fa;
  min-height: 100vh;
}

.detail-content {
  padding: 16px;
}

.product-items {
  .product-item {
    margin-bottom: 1px;

    &:last-child {
      margin-bottom: 0;
    }
  }

  .product-cell {
    padding: 10px 16px;
    align-items: flex-start;

    &:after {
      border-bottom: none;
    }
  }
  
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
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    
    .product-unit {
      color: #646566;
      font-size: 12px;
      margin-right: 8px;
    }
    
    .product-price {
      color: #f53f3f;
      font-weight: 500;
      font-size: 13px;
    }
    
    .product-unit-price {
      display: flex;
      align-items: center;
      justify-content: flex-end;
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
    
    .processed-quantity {
      flex: 1;
      color: #07c160;
      font-size: 12px;
      text-align: right;
    }
  }
  
  .product-divider {
    height: 1px;
    background-color: #ebedf0;
    margin-left: 16px;
    margin-right: 16px;
  }
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

.action-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 16px;
  background: white;
  border-top: 1px solid #ebedf0;
}

.page-loading {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 999;
}
</style>