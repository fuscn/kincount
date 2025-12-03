<template>
  <div class="sale-order-detail">
    <van-nav-bar title="销售订单详情" left-arrow left-text="返回" @click-left="$router.back()" fixed placeholder>
      <template #right>
        <van-button v-if="hasActions" type="primary" size="small" @click="showActionSheet = true">
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
              <!-- 突出显示审核状态 -->
              <van-tag :type="getStatusType(orderData.status)" size="large" class="status-tag" round>
                {{ getStatusText(orderData.status) }}
              </van-tag>
            </div>
          </template>

          <template #desc>
            <!-- 审核信息单独突出显示 -->
            <div v-if="orderData.status === 1" class="audit-warning">
              <van-icon name="warning" color="#ff976a" />
              <span>此订单待审核，请及时处理</span>
            </div>
            <div v-if="orderData.status === 2" class="audit-success">
              <van-icon name="checked" color="#07c160" />
              <span>订单已审核，可进行出库操作</span>
            </div>

            <div class="info-item">
              <span class="label">客户：</span>
              <span class="value">{{ orderData.customer?.name || '--' }}</span>
            </div>
            <div class="info-item">
              <span class="label">仓库：</span>
              <span class="value">{{ orderData.warehouse?.name || '--' }}</span>
            </div>
            <div class="info-item">
              <span class="label">预计交货：</span>
              <span class="value">{{ formatDate(orderData.expected_date) }}</span>
            </div>
            <div class="info-item">
              <span class="label">总金额：</span>
              <span class="value amount">¥{{ formatPrice(orderData.total_amount) }}</span>
            </div>
            <div class="info-item">
              <span class="label">折扣金额：</span>
              <span class="value amount discount">-¥{{ formatPrice(orderData.discount_amount) }}</span>
            </div>
            <div class="info-item">
              <span class="label">实收金额：</span>
              <span class="value amount final">¥{{ formatPrice(orderData.final_amount) }}</span>
            </div>
            <div class="info-item">
              <span class="label">已收金额：</span>
              <span class="value amount">¥{{ formatPrice(orderData.paid_amount) }}</span>
            </div>
            <div class="info-item">
              <span class="label">未收金额：</span>
              <span class="value amount unpaid">
                ¥{{ formatPrice(orderData.final_amount - orderData.paid_amount) }}
              </span>
            </div>
            <div class="info-item">
              <span class="label">创建人：</span>
              <span class="value">{{ orderData.creator?.real_name || '--' }}</span>
            </div>
            <div class="info-item">
              <span class="label">创建时间：</span>
              <span class="value">{{ formatDateTime(orderData.created_at) }}</span>
            </div>
            <div v-if="orderData.audit_by" class="info-item">
              <span class="label">审核人：</span>
              <span class="value">{{ orderData.auditor?.real_name || '--' }}</span>
            </div>
            <div v-if="orderData.audit_time" class="info-item">
              <span class="label">审核时间：</span>
              <span class="value">{{ formatDateTime(orderData.audit_time) }}</span>
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
                <div class="item-name">{{ getProductName(item) }}&nbsp&nbsp&nbsp&nbsp#{{ item.product?.product_no || '无编码' }}</div>
                <div class="item-specs" v-if="getSkuSpecs(item).length > 0">
                  <van-tag v-for="(spec, specIndex) in getSkuSpecs(item)" :key="specIndex" size="small" type="primary"
                    plain>
                    {{ spec }}
                  </van-tag>
                </div>
                <!-- <div class="item-code">{{ item.product?.product_no || '无编码' }}</div> -->
                <div v-if="item.sku?.sku_code" class="item-sku">
                  SKU: {{ item.sku.sku_code }}
                </div>
              </div>
              <div class="item-quantity">
                <div class="quantity">{{ item.quantity }} {{ item.product?.unit || item.unit || '个' }}</div>
                <div class="price">¥{{ formatPrice(item.price) }}</div>
                <div v-if="item.delivered_quantity !== undefined" class="delivered">
                  已出库: {{ item.delivered_quantity }}/{{ item.quantity }}
                </div>
              </div>
              <div class="item-total">
                ¥{{ formatPrice(item.total_amount) }}
              </div>
            </div>

            <!-- 合计 -->
            <div class="total-row">
              <div class="total-label">商品金额：</div>
              <div class="total-amount">¥{{ formatPrice(orderData.total_amount) }}</div>
            </div>
            <div v-if="orderData.discount_amount > 0" class="total-row discount-row">
              <div class="total-label">折扣金额：</div>
              <div class="total-amount discount">-¥{{ formatPrice(orderData.discount_amount) }}</div>
            </div>
            <div class="total-row final-row">
              <div class="total-label">实收金额：</div>
              <div class="total-amount final">¥{{ formatPrice(orderData.final_amount) }}</div>
            </div>
          </div>
        </div>

        <!-- 关联出库单 -->
        <div v-if="relatedStocks && relatedStocks.length > 0" class="section">
          <h3 class="section-title">关联出库单</h3>
          <div class="stock-list">
            <div v-for="stock in relatedStocks" :key="stock.id" class="stock-card" @click="viewStockDetail(stock.id)">
              <div class="stock-header">
                <div class="stock-no">{{ stock.stock_no }}</div>
                <van-tag :type="getStockStatusType(stock.status)">
                  {{ getStockStatusText(stock.status) }}
                </van-tag>
              </div>
              <div class="stock-info">
                <div class="stock-item">
                  <span>总金额：</span>
                  <span class="amount">¥{{ formatPrice(stock.total_amount) }}</span>
                </div>
                <div class="stock-item">
                  <span>审核人：</span>
                  <span>{{ stock.audit_by ? (stock.auditor?.real_name || '--') : '未审核' }}</span>
                </div>
                <div class="stock-item">
                  <span>创建时间：</span>
                  <span>{{ formatDateTime(stock.created_at) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>

      <van-empty v-else description="订单不存在" />
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
                <span class="max-quantity">可出库: {{ getAvailableQuantity(item) }}</span>
                <van-stepper v-model="item.stockQuantity" :max="getAvailableQuantity(item)" :min="0" integer
                  @change="updateStockTotal" />
              </div>
            </div>
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
    1: '待审核',
    2: '已审核',
    3: '部分出库',
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

// 获取出库单状态文本
const getStockStatusText = (status) => {
  const statusMap = {
    1: '待审核',
    2: '已审核',
    3: '已完成',  // 修改：3从"已取消"改为"已完成"
    4: '已取消'   // 新增：4为已取消状态
  }
  return statusMap[status] || '未知状态'
}

// 获取出库单状态类型
const getStockStatusType = (status) => {
  const typeMap = {
    1: 'warning',
    2: 'primary',  // 已审核用蓝色
    3: 'success',  // 已完成用绿色
    4: 'danger'    // 已取消用红色
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
  return orderData.value?.status === 1 // 待审核
})

const canCancel = computed(() => {
  const status = orderData.value?.status
  return [1, 2, 3].includes(status) // 待审核、已审核、部分出库
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
  return [2, 3].includes(status) && availableItems.value.length > 0
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

  // 重置数量选择
  availableItems.value.forEach(item => {
    item.stockQuantity = 0
  })
  stockTotalAmount.value = 0

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
.sale-order-detail {
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
    padding: 16px;
    border-bottom: 1px solid #f5f5f5;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
  }

  :deep(.van-card__content) {
    padding: 16px;
  }
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;

  .order-no {
    font-size: 18px;
    font-weight: 700;
    color: #2c3e50;
    text-shadow: 0 1px 1px rgba(0, 0, 0, 0.1);
  }

  .status-tag {
    font-weight: 600;
    padding: 6px 12px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  }
}

// 审核状态突出显示
.audit-warning {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px;
  margin-bottom: 16px;
  background: linear-gradient(to right, #fff8e1, #fff3e0);
  border-radius: 8px;
  border: 1px solid #ffb74d;

  span {
    font-weight: 600;
    color: #ff9800;
  }

  .van-icon {
    font-size: 20px;
  }
}

.audit-success {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px;
  margin-bottom: 16px;
  background: linear-gradient(to right, #e8f5e9, #c8e6c9);
  border-radius: 8px;
  border: 1px solid #81c784;

  span {
    font-weight: 600;
    color: #2e7d32;
  }

  .van-icon {
    font-size: 20px;
  }
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
    font-weight: 500;
  }

  .value {
    color: #323233;
    text-align: right;
    flex: 1;
    word-break: break-word;

    &.amount {
      font-weight: 700;
      color: #ee0a24;

      &.discount {
        color: #07c160;
      }

      &.final {
        font-size: 16px;
        text-shadow: 0 1px 1px rgba(0, 0, 0, 0.1);
      }
    }

    &.unpaid {
      color: #ff5722;
      font-weight: 700;
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
  font-size: 18px;
  font-weight: 700;
  margin: 0 0 16px 0;
  color: #2c3e50;
  padding-bottom: 12px;
  border-bottom: 2px solid #3498db;
}

.items-list {
  .item-card {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 14px 0;
    border-bottom: 1px solid #f5f5f5;
    transition: background-color 0.2s;

    &:hover {
      background-color: #fafafa;
    }

    &:last-child {
      border-bottom: none;
    }
  }

  .item-info {
    flex: 1;
    margin-right: 12px;

    .item-name {
      font-size: 15px;
      font-weight: 600;
      margin-bottom: 6px;
      color: #2c3e50;
    }

    .item-specs {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-bottom: 6px;

      :deep(.van-tag) {
        margin-right: 4px;
        margin-bottom: 4px;
        font-size: 12px;
      }
    }

    .item-code {
      font-size: 13px;
      color: #7f8c8d;
      margin-bottom: 4px;
    }

    .item-sku {
      font-size: 12px;
      color: #95a5a6;
      background: #f8f9fa;
      padding: 2px 6px;
      border-radius: 3px;
      display: inline-block;
    }
  }

  .item-quantity {
    text-align: right;
    min-width: 110px;

    .quantity {
      font-size: 15px;
      font-weight: 600;
      margin-bottom: 6px;
      color: #2c3e50;
    }

    .price {
      font-size: 13px;
      color: #7f8c8d;
      margin-bottom: 4px;
    }

    .delivered {
      font-size: 12px;
      color: #27ae60;
      background: #d5f4e6;
      padding: 2px 6px;
      border-radius: 3px;
      display: inline-block;
      font-weight: 500;
    }
  }

  .item-total {
    font-weight: 700;
    color: #ee0a24;
    min-width: 90px;
    text-align: right;
    font-size: 16px;
  }

  .total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    font-size: 15px;

    &.discount-row {
      color: #07c160;
      padding: 6px 0;
    }

    &.final-row {
      border-top: 2px solid #f5f5f5;
      margin-top: 12px;
      padding-top: 16px;
      font-size: 18px;
      font-weight: 700;
      background: #f8f9fa;
      padding: 16px;
      border-radius: 6px;
      margin-left: -16px;
      margin-right: -16px;
      margin-bottom: -16px;
    }

    .total-label {
      color: #2c3e50;
      font-weight: 600;
    }

    .total-amount {
      font-weight: 700;
      color: #323233;

      &.discount {
        color: #07c160;
      }

      &.final {
        color: #ee0a24;
        font-size: 20px;
        text-shadow: 0 1px 1px rgba(0, 0, 0, 0.1);
      }
    }
  }
}

// 关联出库单样式
.stock-list {
  .stock-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 14px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid #e9ecef;

    &:last-child {
      margin-bottom: 0;
    }

    &:hover {
      background: #e9ecef;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
  }

  .stock-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
  }

  .stock-no {
    font-size: 15px;
    font-weight: 600;
    color: #2c3e50;
  }

  .stock-info {
    .stock-item {
      display: flex;
      justify-content: space-between;
      font-size: 13px;
      margin-bottom: 6px;

      &:last-child {
        margin-bottom: 0;
      }

      .amount {
        color: #ee0a24;
        font-weight: 600;
      }
    }
  }
}

// 生成出库单对话框样式
.generate-stock-content {
  padding: 16px;

  .dialog-section {
    margin-bottom: 20px;

    h4 {
      margin: 0 0 16px 0;
      font-size: 16px;
      color: #2c3e50;
      font-weight: 700;
      padding-bottom: 10px;
      border-bottom: 2px solid #3498db;
    }
  }

  .no-available-items {
    text-align: center;
    color: #95a5a6;
    font-size: 15px;
    padding: 30px 0;
    background: #f8f9fa;
    border-radius: 8px;
    margin: 10px 0;
  }

  .info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    font-size: 15px;
    padding: 8px 0;

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
    padding: 14px 0;
    border-bottom: 1px solid #f5f5f5;
    transition: background-color 0.2s;

    &:hover {
      background-color: #fafafa;
    }

    &:last-child {
      border-bottom: none;
    }
  }

  .item-info {
    flex: 1;
    margin-right: 12px;

    .item-name {
      font-size: 14px;
      margin-bottom: 6px;
      font-weight: 600;
    }

    .item-specs {
      display: flex;
      flex-wrap: wrap;
      gap: 4px;
      margin-bottom: 6px;

      :deep(.van-tag) {
        margin-right: 4px;
        margin-bottom: 4px;
        font-size: 11px;
      }
    }

    .item-sku {
      font-size: 11px;
      color: #95a5a6;
      background: #f8f9fa;
      padding: 2px 6px;
      border-radius: 3px;
      display: inline-block;
    }
  }

  .quantity-control {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 10px;
    min-width: 130px;

    .max-quantity {
      font-size: 12px;
      color: #7f8c8d;
      font-weight: 500;
    }

    :deep(.van-stepper) {

      .van-stepper__minus,
      .van-stepper__plus {
        width: 32px;
        height: 32px;
        border-radius: 4px;
      }

      .van-stepper__input {
        width: 40px;
        height: 32px;
      }
    }
  }

  .total-section {
    border-top: 2px solid #f5f5f5;
    padding-top: 16px;
    margin-top: 20px;
    background: #f8f9fa;
    padding: 16px;
    border-radius: 8px;
    margin-left: -16px;
    margin-right: -16px;
    margin-bottom: -16px;

    .total-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 18px;
      font-weight: 700;

      .total-amount {
        color: #ee0a24;
        font-size: 20px;
      }
    }
  }

  .no-items-hint {
    text-align: center;
    color: #95a5a6;
    font-size: 15px;
    padding: 30px 0;
    background: #f8f9fa;
    border-radius: 8px;
    margin: 10px 0;
  }
}

.confirm-content {
  padding: 30px;
  text-align: center;
  font-size: 17px;
  color: #2c3e50;
  font-weight: 500;
  line-height: 1.6;
}

// 操作面板样式调整
:deep(.van-action-sheet) {
  .van-action-sheet__item {
    font-size: 17px;
    font-weight: 500;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;

    &[style*="color: #1989fa"] {
      color: #1989fa !important;
      background: linear-gradient(to right, #e8f4fe, #d9ecff);
    }

    &[style*="color: #07c160"] {
      color: #07c160 !important;
      background: linear-gradient(to right, #e8f7f0, #d9f2e6);
    }

    &[style*="color: #7232dd"] {
      color: #7232dd !important;
      background: linear-gradient(to right, #f1ebff, #e8e0ff);
    }

    &[style*="color: #ff976a"] {
      color: #ff976a !important;
      background: linear-gradient(to right, #fff0eb, #ffe6e0);
    }

    &[style*="color: #ee0a24"] {
      color: #ee0a24 !important;
      background: linear-gradient(to right, #ffeaea, #ffe0e0);
    }

    &:active {
      opacity: 0.8;
    }
  }

  .van-action-sheet__cancel {
    font-size: 16px;
    font-weight: 500;
    height: 56px;
    background: #f8f9fa;
    color: #7f8c8d;
  }
}

// 响应式调整
@media (max-width: 375px) {
  .card-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;

    .order-no {
      font-size: 16px;
    }

    .status-tag {
      align-self: flex-start;
    }
  }

  .info-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;

    .value {
      text-align: left;
    }
  }
}
</style>