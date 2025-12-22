<template>
  <div class="purchase-return-detail-page">
    <van-nav-bar 
      title="采购退货详情"
      left-text="返回"
      left-arrow
      @click-left="$router.back()"
    >
      <template #right>
        <van-button 
          v-if="returnOrder.status === 0" 
          size="small" 
          type="primary" 
          @click="handleAudit"
        >
          审核
        </van-button>
        <van-button 
          v-if="returnOrder.status === 1 && returnOrder.stock_status !== 2" 
          size="small" 
          type="success" 
          @click="handleCreateStock"
        >
          创建出库单
        </van-button>
        <van-button 
          v-if="returnOrder.status === 1 && returnOrder.stock_status === 2" 
          size="small" 
          type="default"
          @click="viewStockOrder"
        >
          查看出库单
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 加载状态 -->
    <van-loading v-if="loading" class="page-loading" />

    <div v-else class="detail-content">
      <!-- 退货基本信息 -->
      <van-cell-group title="退货信息">
        <van-cell title="退货单号" :value="returnOrder.return_no" />
        <van-cell title="关联入库单" :value="returnOrder.sourceStock?.stock_no || '--'" />
        <van-cell title="供应商名称" :value="returnOrder.supplier?.name || '--'" />
        <van-cell title="退货日期" :value="formatDate(returnOrder.created_at)" />
        <van-cell title="退货类型" :value="getReturnTypeText(returnOrder.return_type)" />
        <van-cell title="退货状态">
          <template #value>
            <van-tag :type="getStatusTagType(returnOrder.status)">
              {{ getStatusText(returnOrder.status) }}
            </van-tag>
          </template>
        </van-cell>
        <!-- 出库状态 -->
        <van-cell title="出库状态">
          <template #value>
            <van-tag :type="getStockStatusTagType(returnOrder.stock_status)">
              {{ getStockStatusText(returnOrder.stock_status) }}
            </van-tag>
          </template>
        </van-cell>
        <van-cell title="退货金额" :value="`¥${formatPrice(returnOrder.total_amount)}`" />
        <van-cell title="应退金额" :value="`¥${formatPrice(returnOrder.refund_amount)}`" />
        <van-cell title="已退金额" :value="`¥${formatPrice(returnOrder.refunded_amount)}`" />
        <van-cell title="仓库" :value="returnOrder.warehouse?.name || '--'" />
        <van-cell title="备注信息" :value="returnOrder.remark || '无'" />
        <van-cell title="创建人" :value="returnOrder.creator?.real_name || returnOrder.creator?.username || '--'" />
        <van-cell v-if="returnOrder.auditor" title="审核人" :value="returnOrder.auditor?.real_name || returnOrder.auditor?.username || '--'" />
        <van-cell v-if="returnOrder.audit_time" title="审核时间" :value="formatDate(returnOrder.audit_time)" />
        <!-- 出库单信息 -->
        <van-cell 
          v-if="returnOrder.stock_id" 
          title="出库单号" 
          is-link
          @click="viewStockOrder"
          :value="returnStock?.stock_no || '点击查看'"
        />
      </van-cell-group>

      <!-- 退货商品明细 -->
      <van-cell-group title="退货商品明细" v-if="returnOrder.items && returnOrder.items.length > 0">
        <div class="product-items">
          <van-swipe-cell 
            v-for="(item, index) in returnOrder.items" 
            :key="index"
            class="product-item"
          >
            <van-cell class="product-cell">
              <template #title>
                <div class="product-title">
                  <span class="product-name">{{ item.product?.name || '产品' + item.id }}</span>
                  <span class="sku-code" v-if="item.sku?.sku_code">{{ item.sku?.sku_code }}</span>
                </div>
              </template>
              <template #label>
                <div class="product-label">
                  <div class="spec-text" v-if="formatSpec(item.sku?.spec)">规格: {{ formatSpec(item.sku?.spec) }}</div>
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
                      <div class="total-amount">¥{{ formatPrice(item.total_amount) }}</div>
                    </div>
                    <!-- 退货数量 -->
                    <div class="quantity-display">
                      <span class="quantity-text">{{ item.return_quantity }}{{ item.sku?.unit || item.product?.unit || '个' }}</span>
                    </div>
                  </div>
                </div>
              </template>
            </van-cell>
            <!-- 出库数量信息 -->
            <template #right v-if="item.processed_quantity !== undefined">
              <div class="stock-info">
                <span class="processed-quantity">已出库: {{ item.processed_quantity }}{{ item.sku?.unit || item.product?.unit || '个' }}</span>
                <span class="remaining-quantity">待出库: {{ item.return_quantity - item.processed_quantity }}{{ item.sku?.unit || item.product?.unit || '个' }}</span>
              </div>
            </template>
          </van-swipe-cell>
        </div>
        <div class="total-amount">
          <span>合计: {{ returnOrder.items.length }} 种商品</span>
          <span class="total-price">总金额: ¥{{ formatPrice(returnOrder.total_amount) }}</span>
        </div>
      </van-cell-group>
      <van-cell-group title="退货商品明细" v-else>
        <van-empty description="暂无商品明细" image="search" />
      </van-cell-group>

      <!-- 操作记录 -->
      <van-cell-group title="操作记录" v-if="operationLogs.length > 0">
        <van-cell
          v-for="(log, index) in operationLogs"
          :key="index"
          :title="log.action"
          :label="log.operator + ' | ' + log.created_at"
          :value="log.remark"
        />
      </van-cell-group>
      <van-cell-group title="操作记录" v-else>
        <van-empty description="暂无操作记录" image="search" />
      </van-cell-group>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { 
  showConfirmDialog,
  showSuccessToast,
  showFailToast,
  showLoadingToast,
  showNotify,
  closeToast
} from 'vant'
import { usePurchaseStore } from '@/store/modules/purchase'
import { auditPurchaseReturn, cancelPurchaseReturn, getPurchaseReturnDetail } from '@/api/purchase'
import { createReturnStock } from '@/api/stock'

const route = useRoute()
const router = useRouter()
const purchaseStore = usePurchaseStore()

const returnOrder = ref({
  return_no: '',
  sourceStock: {},
  supplier: {},
  warehouse: {},
  items: [],
  creator: {},
  auditor: null,
  stock_status: 0
})

const returnStock = ref(null) // 退货出库单详情
const operationLogs = ref([])
const loading = ref(true)
const isLoading = ref(false) // 加载锁，防止重复请求

// 格式化函数
const formatPrice = (price) => {
  if (price === null || price === undefined || price === '') return '0.00'
  const num = Number(price)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

const formatDate = (date) => {
  if (!date) return '--'
  try {
    const d = new Date(date)
    if (isNaN(d.getTime())) return '--'
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    const hour = String(d.getHours()).padStart(2, '0')
    const minute = String(d.getMinutes()).padStart(2, '0')
    return `${year}-${month}-${day} ${hour}:${minute}`
  } catch (error) {
    return '--'
  }
}

// 添加格式化规格的函数
const formatSpec = (spec) => {
  if (!spec) return '无'
  if (typeof spec === 'string') return spec
  if (typeof spec === 'object') {
    return Object.entries(spec)
      .map(([key, value]) => `${key}:${value}`)
      .join(' ')
  }
  return '无'
}

// 获取状态文本
const getStatusText = (status) => {
  const statusMap = {
    0: '待审核',
    1: '已审核',
    2: '部分入库/出库',
    3: '已入库/出库',
    4: '已退款/收款',
    5: '已完成',
    6: '已取消'
  }
  return statusMap[status] || '未知状态'
}

// 获取状态标签类型
const getStatusTagType = (status) => {
  const typeMap = {
    0: 'warning',
    1: 'primary',
    2: 'warning',
    3: 'primary',
    4: 'success',
    5: 'success',
    6: 'danger'
  }
  return typeMap[status] || 'default'
}

// 获取出库状态文本
const getStockStatusText = (status) => {
  const statusMap = {
    0: '未出库',
    1: '部分出库',
    2: '已出库'
  }
  return statusMap[status] || '未知'
}

// 获取出库状态标签类型
const getStockStatusTagType = (status) => {
  const typeMap = {
    0: 'danger',
    1: 'warning',
    2: 'success'
  }
  return typeMap[status] || 'default'
}

// 获取退货类型文本
const getReturnTypeText = (type) => {
  const typeMap = {
    0: '质量问题',
    1: '数量问题',
    2: '供应商取消',
    3: '其他'
  }
  return typeMap[type] || '未知类型'
}

// 加载采购退货详情
const loadReturnDetail = async () => {
  if (isLoading.value) return // 如果正在加载，直接返回
  
  try {
    isLoading.value = true
    loading.value = true
    const returnId = route.params.id
    
    // 使用采购store加载退货详情
    if (purchaseStore.loadReturnDetail) {
      await purchaseStore.loadReturnDetail(returnId)
      returnOrder.value = { ...purchaseStore.currentReturn }
    } else {
      // 如果store没有相应方法，直接调用API
      const response = await getPurchaseReturnDetail(returnId)
      if (response.code === 200) {
        returnOrder.value = response.data
      } else {
        showFailToast(response.msg || '加载退货详情失败')
      }
    }
    
    console.log('采购退货详情数据:', returnOrder.value)
    
    // 如果已有出库单，加载出库单详情
    if (returnOrder.value.stock_id) {
      await loadReturnStockDetail(returnOrder.value.stock_id)
    }
    
    // 加载操作记录
    await loadOperationLogs(returnId)
  } catch (error) {
    console.error('加载采购退货详情失败:', error)
    showFailToast('加载采购退货详情失败')
  } finally {
    loading.value = false
    isLoading.value = false
  }
}

// 加载退货出库单详情
const loadReturnStockDetail = async (stockId) => {
  try {
    // 这里需要根据您的API实现来获取出库单详情
    // 暂时用模拟数据
    returnStock.value = {
      id: stockId,
      stock_no: `RS${new Date().getTime()}`,
      status: 0,
      created_at: new Date().toISOString()
    }
  } catch (error) {
    console.error('加载出库单详情失败:', error)
  }
}

// 加载操作记录
const loadOperationLogs = async (returnId) => {
  try {
    operationLogs.value = [
      {
        action: '创建采购退货单',
        operator: returnOrder.value.creator?.real_name || returnOrder.value.creator?.username || '系统管理员',
        created_at: formatDate(returnOrder.value.created_at),
        remark: '创建采购退货单'
      }
    ]
    
    // 如果有审核人，添加审核记录
    if (returnOrder.value.auditor) {
      operationLogs.value.push({
        action: '审核采购退货单',
        operator: returnOrder.value.auditor?.real_name || returnOrder.value.auditor?.username || '审核员',
        created_at: formatDate(returnOrder.value.audit_time),
        remark: '审核通过'
      })
    }
    
    // 如果有出库记录，添加出库操作记录
    if (returnOrder.value.stock_status === 2) {
      operationLogs.value.push({
        action: '商品出库',
        operator: '仓库管理员',
        created_at: formatDate(returnOrder.value.updated_at),
        remark: '退货商品已出库退回供应商'
      })
    }
    
    // 如果有完成时间，添加完成记录
    if (returnOrder.value.status === 5) {
      operationLogs.value.push({
        action: '退货完成',
        operator: '系统',
        created_at: formatDate(returnOrder.value.updated_at),
        remark: '采购退货单已完成'
      })
    }
  } catch (error) {
    console.error('加载操作记录失败:', error)
  }
}

// 审核采购退货
const handleAudit = async () => {
  try {
    await showConfirmDialog({
      title: '确认审核',
      message: '确定要审核通过这个采购退货单吗？'
    })
    
    // 显示加载提示
    const loadingToast = showLoadingToast({
      message: '审核中...',
      forbidClick: true,
    })
    
    try {
      const response = await auditPurchaseReturn(returnOrder.value.id)
      
      // 关闭加载提示
      closeToast()
      
      if (response.code === 200) {
        showSuccessToast('审核成功')
        // 重新加载数据
        loadReturnDetail()
      } else {
        showFailToast(response.msg || '审核失败')
      }
    } catch (error) {
      closeToast()
      console.error('审核失败:', error)
    }
    
  } catch (error) {
    // 这是对话框取消的错误
    if (error !== 'cancel') {
      console.error('对话框错误:', error)
    }
  }
}

// 创建退货出库单
const handleCreateStock = async () => {
  try {
    // 检查是否已有出库单
    if (returnOrder.value.stock_id) {
      showNotify({
        type: 'warning',
        message: '已存在出库单，请查看出库单详情',
        duration: 3000
      })
      viewStockOrder()
      return
    }
    
    // 检查是否有需要出库的商品
    if (!returnOrder.value.items || returnOrder.value.items.length === 0) {
      showNotify({
        type: 'warning',
        message: '退货单没有商品明细，无法创建出库单',
        duration: 3000
      })
      return
    }
    
    // 计算总待出库数量
    const totalRemainingQuantity = returnOrder.value.items.reduce((sum, item) => {
      const processed = item.processed_quantity || 0
      return sum + (item.return_quantity - processed)
    }, 0)
    
    if (totalRemainingQuantity <= 0) {
      showNotify({
        type: 'warning',
        message: '所有商品已出库，无需创建出库单',
        duration: 3000
      })
      return
    }
    
    await showConfirmDialog({
      title: '创建出库单',
      message: `确定要创建出库单吗？共${returnOrder.value.items.length}个商品，待出库数量：${totalRemainingQuantity}`
    })
    
    // 显示加载提示
    const loadingToast = showLoadingToast({
      message: '创建出库单中...',
      forbidClick: true,
    })
    
    try {
      // 构建出库单数据
      const stockData = {
        return_id: returnOrder.value.id,
        warehouse_id: returnOrder.value.warehouse_id || returnOrder.value.warehouse?.id,
        remark: `采购退货单 ${returnOrder.value.return_no} 的出库单`,
        items: returnOrder.value.items.map(item => {
          const processed = item.processed_quantity || 0
          const remaining = item.return_quantity - processed
          
          return {
            return_item_id: item.id, // 退货明细ID
            source_stock_item_id: item.source_stock_item_id, // 源入库明细ID，用于追溯
            product_id: item.product_id,
            sku_id: item.sku_id,
            quantity: remaining, // 本次出库数量
            price: item.price, // 出库价格
            unit: item.sku?.unit || item.product?.unit || '个',
            product_no: item.product?.product_no,
            product_name: item.product?.name,
            spec: item.sku?.spec ? JSON.stringify(item.sku.spec) : null
          }
        }).filter(item => item.quantity > 0) // 只包含需要出库的商品
      }
      
      console.log('创建出库单请求数据:', stockData)
      
      const response = await createReturnStock(stockData)
      console.log('创建出库单响应:', response)
      
      // 关闭加载提示
      closeToast()
      
      if (response.code === 200) {
        showSuccessToast('出库单创建成功')
        
        // 询问是否跳转到出库单详情页面
        await showConfirmDialog({
          title: '出库单创建成功',
          message: '是否立即查看并处理出库单？',
          showCancelButton: true,
          confirmButtonText: '查看出库单',
          cancelButtonText: '稍后处理'
        }).then(() => {
          // 跳转到出库单详情页面
          router.push(`/purchase/return/storage/detail/${response.data.id}`)
        }).catch(() => {
          // 用户选择稍后处理，重新加载退货详情
          loadReturnDetail()
        })
      } else {
        showFailToast(response.msg || '创建出库单失败')
      }
    } catch (error) {
      closeToast()
      console.error('创建出库单失败:', error)
      
      // 显示详细的错误信息
      let errorMsg = '创建出库单失败'
      
      if (error.response) {
        // 服务器响应了错误
        console.error('错误响应数据:', error.response.data)
        
        if (error.response.data && typeof error.response.data === 'object') {
          if (error.response.data.msg) {
            errorMsg = error.response.data.msg
          } else if (error.response.data.message) {
            errorMsg = error.response.data.message
          }
        } else if (error.response.status) {
          errorMsg = `服务器错误 (${error.response.status})`
        }
      } else if (error.request) {
        // 请求已发出但没有响应
        errorMsg = '网络连接失败，请检查网络'
      } else {
        // 请求配置错误
        errorMsg = error.message || '请求配置错误'
      }
      
      showFailToast(errorMsg)
    }
    
  } catch (error) {
    // 这是对话框取消的错误
    if (error !== 'cancel') {
      console.error('对话框错误:', error)
    }
  }
}

// 查看出库单
const viewStockOrder = () => {
  if (returnOrder.value.stock_id) {
    router.push(`/purchase/return/storage/detail/${returnOrder.value.stock_id}`)
  } else {
    showNotify({
      type: 'warning',
      message: '暂无出库单信息',
      duration: 2000
    })
  }
}

// 取消退货
const handleCancel = async () => {
  try {
    await showConfirmDialog({
      title: '确认取消',
      message: '确定要取消这个采购退货单吗？此操作不可恢复。'
    })
    
    // 显示加载提示
    const loadingToast = showLoadingToast({
      message: '取消中...',
      forbidClick: true,
    })
    
    try {
      const response = await cancelPurchaseReturn(returnOrder.value.id)
      
      // 关闭加载提示
      closeToast()
      
      if (response.code === 200) {
        showSuccessToast('取消成功')
        // 重新加载数据
        loadReturnDetail()
      } else {
        showFailToast(response.msg || '取消失败')
      }
    } catch (error) {
      closeToast()
      console.error('取消失败:', error)
    }
    
  } catch (error) {
    // 这是对话框取消的错误
    if (error !== 'cancel') {
      console.error('对话框错误:', error)
    }
  }
}

onMounted(() => {
  loadReturnDetail()
})

// 监听路由参数变化，避免重复加载
watch(
  () => route.params.id,
  (newId, oldId) => {
    if (newId && newId !== oldId) {
      loadReturnDetail()
    }
  }
)
</script>

<style scoped lang="scss">
.purchase-return-detail-page {
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
      border-bottom: 1px solid #f5f5f5;
    }
  }
}

.product-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  min-width: 0; /* 确保flex子元素可以收缩 */
}

.product-name {
  font-weight: bold;
  color: #323233;
  font-size: 14px;
  line-height: 1.4;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
  min-width: 0; /* 确保可以收缩 */
}

.sku-code {
  color: #646566;
  font-size: 12px;
  font-weight: normal;
  background: #f5f5f5;
  padding: 1px 4px;
  border-radius: 3px;
  white-space: nowrap;
  flex-shrink: 0; /* 防止SKU编码被压缩 */
}

.product-label {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.spec-text {
  color: #969799;
  font-size: 12px;
  margin-bottom: 2px;
}

.stock-text {
  color: #1989fa;
  line-height: 1.3;
  margin-bottom: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.barcode-info {
  color: #969799;
  font-size: 12px;
  margin-left: 8px;
}

.item-details {
  display: flex;
  justify-content: flex-end;
}

.quantity-total-column {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}

.item-total {
  margin-bottom: 2px;
}

.total-amount {
  color: #f53f3f;
  font-weight: bold;
  font-size: 14px;
}

.quantity-display {
  display: flex;
  align-items: center;
}

.quantity-text {
  color: #323233;
  font-size: 13px;
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