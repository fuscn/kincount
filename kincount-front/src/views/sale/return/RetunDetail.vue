<template>
  <div class="sale-return-detail-page">
    <van-nav-bar 
      title="销售退货详情"
      left-arrow
      left-text="返回"
      @click-left="$router.back()"
      fixed
      placeholder
    >
      <template #right>
        <van-button size="small" type="primary" @click="showActionSheet = true">
          操作
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 加载状态 -->
    <van-loading v-if="loading" class="page-loading" />

    <div v-else class="detail-content">
      <!-- 退货基本信息 -->
      <van-cell-group title="退货信息">
        <van-cell title="退货单号" :value="returnOrder.return_no" />
        <van-cell title="关联出库单" :value="returnOrder.sourceStock?.stock_no || '--'" />
        <van-cell title="客户名称" :value="returnOrder.customer?.name || '--'" />
        <van-cell title="退货日期" :value="formatDate(returnOrder.created_at)" />
        <van-cell title="退货原因" :value="returnOrder.return_reason || '--'" />
        <van-cell title="退货状态">
          <template #value>
            <van-tag :type="getStatusTagType(returnOrder.status)">
              {{ getStatusText(returnOrder.status) }}
            </van-tag>
          </template>
        </van-cell>
        <!-- 添加入库状态 -->
        <van-cell title="入库状态">
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
        <!-- 添加入库单信息 -->
        <van-cell 
          v-if="returnOrder.stock_id" 
          title="入库单号" 
          is-link
          @click="viewStockOrder"
          :value="returnStock?.stock_no || '点击查看'"
        />
      </van-cell-group>

      <!-- 退货商品明细 -->
      <van-cell-group title="退货商品明细" v-if="returnOrder.items && returnOrder.items.length > 0">
        <div class="product-items">
          <template v-for="(item, index) in returnOrder.items" :key="index">
            <van-swipe-cell class="product-item">
              <van-cell class="product-cell">
                <!-- 商品信息三行显示 -->
                <template #title>
                  <div class="product-info">
                    <!-- 第一行：商品名称和规格文本、数量 -->
                    <div class="product-row-first">
                      <div class="product-name-specs">
                        <span class="product-name">{{ item.product?.name || '产品' + item.id }}</span>
                        <span class="product-specs" v-if="formatSpec(item.sku?.spec) !== '无'">{{ formatSpec(item.sku?.spec) }}</span>
                      </div>
                      <div class="product-quantity">{{ item.return_quantity }}{{ item.sku?.unit || item.product?.unit || '个' }}</div>
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
                      <div class="product-cost">编号: {{ item.product?.product_no || '--' }}</div>
                      <!-- 已入库数量 -->
                      <div class="processed-quantity" v-if="item.processed_quantity !== undefined">
                        已入库: {{ item.processed_quantity }}{{ item.sku?.unit || item.product?.unit || '个' }}
                      </div>
                      <div class="product-total">¥{{ formatPrice(item.total_amount) }}</div>
                    </div>
                  </div>
                </template>
              </van-cell>
              <!-- 入库数量信息 -->
              <template #right v-if="item.processed_quantity !== undefined && item.processed_quantity < item.return_quantity">
                <div class="stock-info">
                  <span class="remaining-quantity">待入库: {{ item.return_quantity - item.processed_quantity }}{{ item.product?.unit || item.unit || '个' }}</span>
                </div>
              </template>
            </van-swipe-cell>
            <!-- 手动添加分割线 -->
            <div v-show="index < returnOrder.items.length - 1" class="product-divider"></div>
          </template>
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

      <!-- 操作面板 -->
      <van-action-sheet
        v-model:show="showActionSheet"
        :actions="actionSheetActions"
        @select="onActionSelect"
        cancel-text="取消"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { 
  showConfirmDialog,
  showSuccessToast,
  showFailToast,
  showLoadingToast,
  showNotify,
  closeToast
} from 'vant'
import { useSaleStore } from '@/store/modules/sale'
import { auditSaleReturn, cancelSaleReturn } from '@/api/sale'
import { createReturnStock } from '@/api/stock'

const route = useRoute()
const router = useRouter()
const saleStore = useSaleStore()

const returnOrder = ref({
  return_no: '',
  sourceStock: {},
  customer: {},
  warehouse: {},
  items: [],
  creator: {},
  auditor: null,
  stock_status: 0
})

const returnStock = ref(null) // 退货入库单详情
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

// 获取库存状态文本
const getStockStatusText = (status) => {
  const statusMap = {
    0: '待处理',
    1: '部分处理',
    2: '已完成'
  }
  return statusMap[status] || '未知'
}

// 获取库存状态标签类型
const getStockStatusTagType = (status) => {
  const typeMap = {
    0: 'danger',
    1: 'warning',
    2: 'success'
  }
  return typeMap[status] || 'default'
}

// 加载退货详情
const loadReturnDetail = async () => {
  if (isLoading.value) return // 如果正在加载，直接返回
  
  try {
    isLoading.value = true
    loading.value = true
    const returnId = route.params.id
    
    // 检查是否有loadReturnDetail方法，如果没有直接调用API
    if (saleStore.loadReturnDetail) {
      await saleStore.loadReturnDetail(returnId)
      returnOrder.value = { ...saleStore.currentReturn }
    } else {
      // 模拟数据用于测试
      console.error('saleStore.loadReturnDetail方法不存在')
      showFailToast('加载详情功能未实现')
    }
    
    console.log('退货详情数据:', returnOrder.value)
    
    // 如果已有入库单，加载入库单详情
    if (returnOrder.value.stock_id) {
      await loadReturnStockDetail(returnOrder.value.stock_id)
    }
    
    // 加载操作记录
    await loadOperationLogs(returnId)
  } catch (error) {
    console.error('加载退货详情失败:', error)
    showFailToast('加载退货详情失败')
  } finally {
    loading.value = false
    isLoading.value = false
  }
}

// 加载退货入库单详情
const loadReturnStockDetail = async (stockId) => {
  try {
    // 这里需要根据您的API实现来获取入库单详情
    // 暂时用模拟数据
    returnStock.value = {
      id: stockId,
      stock_no: `RS${new Date().getTime()}`,
      status: 0,
      created_at: new Date().toISOString()
    }
  } catch (error) {
    console.error('加载入库单详情失败:', error)
  }
}

// 加载操作记录
const loadOperationLogs = async (returnId) => {
  try {
    operationLogs.value = [
      {
        action: '创建退货单',
        operator: returnOrder.value.creator?.real_name || returnOrder.value.creator?.username || '系统管理员',
        created_at: formatDate(returnOrder.value.created_at),
        remark: '创建销售退货单'
      }
    ]
    
    // 如果有审核人，添加审核记录
    if (returnOrder.value.auditor) {
      operationLogs.value.push({
        action: '审核退货单',
        operator: returnOrder.value.auditor?.real_name || returnOrder.value.auditor?.username || '审核员',
        created_at: formatDate(returnOrder.value.audit_time),
        remark: '审核通过'
      })
    }
    
    // 如果有入库记录，添加入库操作记录
    if (returnOrder.value.stock_status === 2) {
      operationLogs.value.push({
        action: '商品入库',
        operator: '仓库管理员',
        created_at: formatDate(returnOrder.value.updated_at),
        remark: '退货商品已入库'
      })
    }
    
    // 如果有完成时间，添加完成记录
    if (returnOrder.value.status === 5) {
      operationLogs.value.push({
        action: '退货完成',
        operator: '系统',
        created_at: formatDate(returnOrder.value.updated_at),
        remark: '退货单已完成'
      })
    }
  } catch (error) {
    console.error('加载操作记录失败:', error)
  }
}

// 审核退货
const handleAudit = async () => {
  try {
    await showConfirmDialog({
      title: '确认审核',
      message: '确定要审核通过这个销售退货单吗？'
    })
    
    // 显示加载提示（使用Vant 4 API）
    const loadingToast = showLoadingToast({
      message: '审核中...',
      forbidClick: true,
    })
    
    try {
      const response = await auditSaleReturn(returnOrder.value.id)
      
      // 关闭加载提示（使用Vant 4 API）
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

// 创建退货入库单
const handleCreateStock = async () => {
  try {
    // 检查是否已有入库单
    if (returnOrder.value.stock_id) {
      showNotify({
        type: 'warning',
        message: '已存在入库单，请查看入库单详情',
        duration: 3000
      })
      viewStockOrder()
      return
    }
    
    // 检查是否有需要入库的商品
    if (!returnOrder.value.items || returnOrder.value.items.length === 0) {
      showNotify({
        type: 'warning',
        message: '退货单没有商品明细，无法创建入库单',
        duration: 3000
      })
      return
    }
    
    // 计算总待入库数量
    const totalRemainingQuantity = returnOrder.value.items.reduce((sum, item) => {
      const processed = item.processed_quantity || 0
      return sum + (item.return_quantity - processed)
    }, 0)
    
    if (totalRemainingQuantity <= 0) {
      showNotify({
        type: 'warning',
        message: '所有商品已入库，无需创建入库单',
        duration: 3000
      })
      return
    }
    
    await showConfirmDialog({
      title: '创建入库单',
      message: `确定要创建入库单吗？共${returnOrder.value.items.length}个商品，待入库数量：${totalRemainingQuantity}`
    })
    
    // 显示加载提示
    const loadingToast = showLoadingToast({
      message: '创建入库单中...',
      forbidClick: true,
    })
    
    try {
      // 构建入库单数据，包含return_item_id字段
      const stockData = {
        return_id: returnOrder.value.id,
        warehouse_id: returnOrder.value.warehouse_id || returnOrder.value.warehouse?.id,
        remark: `退货单 ${returnOrder.value.return_no} 的入库单`,
        items: returnOrder.value.items.map(item => {
          const processed = item.processed_quantity || 0
          const remaining = item.return_quantity - processed
          
          return {
            return_item_id: item.id, // 退货明细ID，这个是必须的
            product_id: item.product_id,
            sku_id: item.sku_id,
            quantity: remaining, // 本次入库数量
            price: item.price, // 入库价格
            unit: item.sku?.unit || item.product?.unit || '个',
            product_no: item.product?.product_no,
            product_name: item.product?.name,
            spec: item.sku?.spec ? JSON.stringify(item.sku.spec) : null
          }
        }).filter(item => item.quantity > 0) // 只包含需要入库的商品
      }
      
      console.log('创建入库单请求数据:', stockData)
      
      const response = await createReturnStock(stockData)
      console.log('创建入库单响应:', response)
      
      // 关闭加载提示
      closeToast()
      
      if (response.code === 200) {
        showSuccessToast('入库单创建成功')
        
        // 直接跳转到入库单详情页面
        router.push(`/sale/return/storage/detail/${response.data.id}`)
      } else {
        showFailToast(response.msg || '创建入库单失败')
      }
    } catch (error) {
      closeToast()
      console.error('创建入库单失败:', error)
      
      // 显示详细的错误信息
      let errorMsg = '创建入库单失败'
      
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

// 查看入库单
const viewStockOrder = () => {
  if (returnOrder.value.stock_id) {
    router.push(`/stock/return-stock/detail/${returnOrder.value.stock_id}`)
  } else {
    showNotify({
      type: 'warning',
      message: '暂无入库单信息',
      duration: 2000
    })
  }
}

// 操作面板显示状态
const showActionSheet = ref(false)

// 操作面板选项
const actionSheetActions = computed(() => {
  const actions = []
  
  // 根据订单状态添加不同的操作选项
  if (returnOrder.value.status === 0) {
    actions.push({
      name: '审核',
      value: 'audit',
      color: '#1989fa'
    })
    actions.push({
      name: '取消退货',
      value: 'cancel',
      color: '#ee0a24'
    })
  }
  
  if (returnOrder.value.status === 1) {
    if (returnOrder.value.stock_status !== 2) {
      actions.push({
        name: '创建入库单',
        value: 'createStock',
        color: '#07c160'
      })
    } else {
      actions.push({
        name: '查看入库单',
        value: 'viewStock',
        color: '#646566'
      })
    }
  }
  
  return actions
})

// 操作面板选择处理
const onActionSelect = (action, index) => {
  showActionSheet.value = false
  
  switch (action.value) {
    case 'audit':
      handleAudit()
      break
    case 'cancel':
      handleCancel()
      break
    case 'createStock':
      handleCreateStock()
      break
    case 'viewStock':
      viewStockOrder()
      break
  }
}

// 取消退货
const handleCancel = async () => {
  try {
    await showConfirmDialog({
      title: '确认取消',
      message: '确定要取消这个销售退货单吗？此操作不可恢复。'
    })
    
    // 显示加载提示（使用Vant 4 API）
    const loadingToast = showLoadingToast({
      message: '取消中...',
      forbidClick: true,
    })
    
    try {
      const response = await cancelSaleReturn(returnOrder.value.id)
      
      // 关闭加载提示（使用Vant 4 API）
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
.sale-return-detail-page {
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

.action-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 16px;
  background: white;
  border-top: 1px solid #ebedf0;
}
</style>