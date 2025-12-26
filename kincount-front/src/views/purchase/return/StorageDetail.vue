<template>
  <div class="return-stock-detail-container">
    <!-- 导航栏 -->
    <van-nav-bar 
      title="退货出库单详情" 
      left-text="返回" 
      left-arrow 
      @click-left="$router.back()" 
    >
      <template #right>
        <van-button 
          v-if="returnStockDetail.status === 0" 
          size="small" 
          type="primary" 
          @click="handleAudit"
        >
          审核
        </van-button>
        <van-button 
          v-if="returnStockDetail.status === 0" 
          size="small" 
          type="danger" 
          @click="handleCancel"
        >
          取消
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 加载状态 -->
    <div v-if="loading" class="loading-state">
      <van-loading size="24px" vertical>加载中...</van-loading>
    </div>

    <!-- 详情内容 -->
    <div v-else class="detail-content">
      <van-pull-refresh v-model="refreshing" @refresh="onRefresh">
        <!-- 基本信息卡片 -->
        <div class="info-card">
          <div class="card-header">
            <div class="header-title">基本信息</div>
          </div>
          
          <div class="info-list">
            <van-cell title="出库单号" :value="returnStockDetail.stock_no || '--'" />
            <van-cell 
              title="关联退货单" 
              :value="returnStockDetail.return?.return_no || '--'" 
              @click="handleViewReturnDetail"
              is-link
            />
            <van-cell 
              title="供应商" 
              :value="returnStockDetail.target_info?.name || '--'" 
            />
            <van-cell title="仓库" :value="returnStockDetail.warehouse?.name || '--'" />
            <van-cell title="出库总金额" :value="`¥${formatPrice(returnStockDetail.total_amount)}`" />
            <van-cell title="创建人" :value="returnStockDetail.creator?.real_name || '--'" />
            <van-cell title="创建时间" :value="formatDateTime(returnStockDetail.created_at)" />
            
            <!-- 业务状态 -->
            <template v-if="returnStockDetail.return">
              <van-cell title="退货类型" :value="returnStockDetail.return.type === 0 ? '销售退货' : (returnStockDetail.return.type === 1 ? '采购退货' : '未知类型')" />
              <van-cell title="退货单状态">
                <template #value>
                  <van-tag :type="getReturnStatusTagType(returnStockDetail.return.status)" size="small">
                    {{ getReturnStatusText(returnStockDetail.return.status) }}
                  </van-tag>
                </template>
              </van-cell>
              <van-cell 
                v-if="returnStockDetail.return.type === 1" 
                title="出库说明" 
                value="采购退货，商品出库退回供应商" 
              />
            </template>
            
            <template v-if="returnStockDetail.status === 1">
              <van-cell title="审核人" :value="returnStockDetail.auditor?.real_name || '--'" />
              <van-cell title="审核时间" :value="formatDateTime(returnStockDetail.audit_time)" />
            </template>
            
            <van-cell 
              v-if="returnStockDetail.remark" 
              title="备注" 
              :value="returnStockDetail.remark" 
              class="remark-cell"
            />
          </div>
        </div>

        <!-- 商品明细卡片 -->
        <div class="items-card" v-if="returnStockItems.length > 0">
          <div class="card-header">
            <div class="header-title">出库商品明细</div>
            <div class="header-count">共 {{ returnStockItems.length }} 项</div>
          </div>
          
          <div class="product-items">
            <template v-for="(item, index) in returnStockItems" :key="item.id">
              <van-swipe-cell class="product-item">
                <van-cell class="product-cell">
                  <!-- 商品信息三行显示 -->
                  <template #title>
                    <div class="product-info">
                      <!-- 第一行：商品名称和规格文本、数量 -->
                      <div class="product-row-first">
                        <div class="product-name-specs">
                          <span class="product-name">{{ item.product?.name || `商品${item.product_id}` }}</span>
                          <span class="product-specs" v-if="item.sku?.spec_text">{{ item.sku.spec_text }}</span>
                        </div>
                        <div class="product-quantity">{{ item.quantity || 0 }}{{ getUnit(item) }}</div>
                      </div>
                      
                      <!-- 第二行：sku编号、单位、单价 -->
                      <div class="product-row-second">
                        <div class="product-sku">SKU: {{ item.sku?.sku_code || '--' }}</div>
                        <div class="product-unit-price">
                          <span class="product-unit">单位: {{ getUnit(item) }} </span>
                          <span class="product-price">¥{{ formatPrice(item.price) }}</span>
                        </div>
                      </div>
                      
                      <!-- 第三行：其他信息、金额小计 -->
                      <div class="product-row-third">
                        <div class="product-cost">成本: ¥{{ formatPrice(item.sku?.cost_price) }}</div>
                        <!-- 退货单关联信息 -->
                        <div class="return-quantity" v-if="item.ReturnOrderItem">
                          退货: {{ item.ReturnOrderItem.return_quantity || 0 }}{{ getUnit(item) }}
                        </div>
                        <div class="product-total">¥{{ formatPrice(item.total_amount) }}</div>
                      </div>
                    </div>
                  </template>
                </van-cell>
                <!-- 退货单关联信息 -->
                <template #right v-if="item.ReturnOrderItem && item.ReturnOrderItem.processed_quantity < item.ReturnOrderItem.return_quantity">
                  <div class="stock-info">
                    <span class="remaining-quantity">待出库: {{ item.ReturnOrderItem.return_quantity - item.ReturnOrderItem.processed_quantity || 0 }}{{ getUnit(item) }}</span>
                  </div>
                </template>
              </van-swipe-cell>
              <!-- 手动添加分割线 -->
              <div v-show="index < returnStockItems.length - 1" class="product-divider"></div>
            </template>
          </div>
          
          <!-- 汇总信息 -->
          <div class="items-summary">
            <div class="summary-row">
              <span class="label">合计数量：</span>
              <span class="value">{{ totalQuantity }}{{ returnStockItems.length > 0 ? getUnit(returnStockItems[0]) : '' }}</span>
            </div>
            <div class="summary-row total">
              <span class="label">合计金额：</span>
              <span class="value amount">¥{{ formatPrice(returnStockDetail.total_amount) }}</span>
            </div>
          </div>
        </div>

        <!-- 空状态 -->
        <div v-else class="empty-items">
          <van-empty image="search" description="暂无商品明细" />
        </div>
      </van-pull-refresh>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useStockStore } from '@/store/modules/stock'
import { 
  showConfirmDialog, 
  showSuccessToast, 
  showFailToast,
  showLoadingToast,
  closeToast
} from 'vant'

const route = useRoute()
const router = useRouter()
const stockStore = useStockStore()

// 退货出库单ID
const id = route.params.id

// 加载状态
const loading = ref(true)
const refreshing = ref(false)

// 详情数据
const returnStockDetail = computed(() => stockStore.currentReturnStock || {})
const returnStockItems = computed(() => returnStockDetail.value.items || [])

// 计算合计数量
const totalQuantity = computed(() => {
  return returnStockItems.value.reduce((sum, item) => sum + (item.quantity || 0), 0)
})

// 获取单位
const getUnit = (item) => {
  if (item && item.sku && item.sku.unit) return item.sku.unit
  if (item && item.product && item.product.unit) return item.product.unit
  return '个'
}

// 格式化金额
const formatPrice = (price) => {
  if (price === null || price === undefined || price === '') return '0.00'
  const num = Number(price)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

// 格式化日期时间
const formatDateTime = (dateString) => {
  if (!dateString) return '--'
  try {
    const date = new Date(dateString)
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    const hour = String(date.getHours()).padStart(2, '0')
    const minute = String(date.getMinutes()).padStart(2, '0')
    return `${year}-${month}-${day} ${hour}:${minute}`
  } catch (error) {
    return '--'
  }
}

// 获取状态文本映射
const getStatusText = (status) => {
  const statusMap = {
    0: '待审核',
    1: '已审核',
    2: '已取消'
  }
  return statusMap[status] || '未知'
}

// 获取状态标签类型
const getStatusTagType = (status) => {
  const typeMap = {
    0: 'warning',  // 待审核 - 警告色
    1: 'success',  // 已审核 - 成功色
    2: 'danger'    // 已取消 - 危险色
  }
  return typeMap[status] || 'default'
}

// 获取退货单状态文本
const getReturnStatusText = (status) => {
  const statusMap = {
    0: '待审核',
    1: '已审核',
    2: '部分入库/出库',
    3: '已入库/出库',
    4: '已退款/收款',
    5: '已完成',
    6: '已取消'
  }
  return statusMap[status] || '未知'
}

// 获取退货单状态标签类型
const getReturnStatusTagType = (status) => {
  const typeMap = {
    0: 'warning',
    1: 'primary',
    2: 'warning',
    3: 'success',
    4: 'primary',
    5: 'success',
    6: 'danger'
  }
  return typeMap[status] || 'default'
}

// 加载详情数据
const loadDetailData = async () => {
  try {
    loading.value = true
    await stockStore.loadReturnStockDetail(id)
  } catch (error) {
    console.error('加载退货出库单详情失败:', error)
    showFailToast('加载详情失败')
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

// 下拉刷新
const onRefresh = () => {
  loadDetailData()
}

// 审核操作
const handleAudit = async () => {
  try {
    await showConfirmDialog({
      title: '确认审核',
      message: `确定要审核退货出库单 ${returnStockDetail.value.stock_no} 吗？审核后商品将出库退回供应商。`
    })
    
    // 显示加载提示
    const loadingToast = showLoadingToast({
      message: '审核中...',
      forbidClick: true,
    })
    
    try {
      // 检查当前状态，避免重复审核
      if (returnStockDetail.value.status !== 0) {
        closeToast()
        showFailToast('当前状态无法审核')
        loadDetailData() // 重新加载数据
        return
      }
      
      await stockStore.auditReturnStockData(id)
      closeToast()
      showSuccessToast('审核成功，商品已出库')
      
      // 重新加载数据
      loadDetailData()
    } catch (error) {
      closeToast()
      console.error('审核失败:', error)
      
      // 根据错误类型显示不同的提示信息
      if (error.response) {
        const data = error.response.data
        if (data && data.msg) {
          showFailToast(data.msg)
        } else {
          showFailToast(`审核失败: ${error.response.status}`)
        }
      } else if (error.message && error.message.includes('库存不足')) {
        showFailToast('库存不足，无法完成退货出库')
      } else {
        showFailToast('审核失败，请重试')
      }
      
      // 重新加载数据
      loadDetailData()
    }
  } catch (error) {
    // 对话框取消
    console.log('用户取消了审核操作')
  }
}

// 取消操作
const handleCancel = async () => {
  try {
    await showConfirmDialog({
      title: '确认取消',
      message: `确定要取消退货出库单 ${returnStockDetail.value.stock_no} 吗？此操作不可恢复。`
    })
    
    // 显示加载提示
    const loadingToast = showLoadingToast({
      message: '取消中...',
      forbidClick: true,
    })
    
    try {
      // 检查当前状态，避免重复取消
      if (returnStockDetail.value.status !== 0) {
        closeToast()
        showFailToast('当前状态无法取消')
        loadDetailData() // 重新加载数据
        return
      }
      
      await stockStore.cancelReturnStockData(id)
      closeToast()
      showSuccessToast('取消成功')
      
      // 重新加载数据
      loadDetailData()
    } catch (error) {
      closeToast()
      console.error('取消失败:', error)
      showFailToast('取消失败')
      
      // 重新加载数据
      loadDetailData()
    }
  } catch (error) {
    // 对话框取消
    console.log('用户取消了取消操作')
  }
}

// 查看采购退货单详情
const handleViewReturnDetail = () => {
  if (returnStockDetail.value.return_id) {
    // 根据返回数据中的type字段判断是销售退货还是采购退货
    const returnType = returnStockDetail.value.return?.type
    if (returnType === 1) {
      // 销售退货
      router.push(`/sale/return/detail/${returnStockDetail.value.return_id}`)
    } else if (returnType === 2) {
      // 采购退货
      router.push(`/purchase/return/detail/${returnStockDetail.value.return_id}`)
    } else {
      showFailToast('未知的退货单类型')
    }
  } else {
    showFailToast('未找到关联的退货单')
  }
}

// 初始化加载
onMounted(() => {
  loadDetailData()
})
</script>

<style scoped lang="scss">
.return-stock-detail-container {
  padding: 0;
  background-color: #f7f8fa;
  min-height: 100vh;
}

/* 导航栏样式 */
:deep(.van-nav-bar) {
  background: #fff;
  position: sticky;
  top: 0;
  z-index: 1000;
}

:deep(.van-nav-bar .van-button) {
  height: 28px;
  padding: 0 10px;
  margin-left: 8px;
  font-size: 12px;
}

/* 加载状态 */
.loading-state {
  padding: 40px 20px;
  text-align: center;
}

/* 详情内容 */
.detail-content {
  padding: 12px;
}

/* 卡片样式 */
.info-card,
.status-card,
.items-card {
  background: white;
  border-radius: 8px;
  margin-bottom: 12px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: #fafafa;
  border-bottom: 1px solid #f0f0f0;
}

.header-title {
  font-weight: bold;
  color: #323233;
  font-size: 15px;
}

.header-count {
  color: #969799;
  font-size: 13px;
}

/* 信息列表 */
.info-list {
  :deep(.van-cell) {
    padding: 12px 16px;
  }
  
  :deep(.van-cell__title) {
    flex: 0 0 80px;
    color: #969799;
    font-size: 13px;
  }
  
  :deep(.van-cell__value) {
    color: #323233;
    font-size: 13px;
    text-align: right;
  }
}

.remark-cell {
  :deep(.van-cell__value) {
    text-align: left;
    padding-top: 4px;
    color: #646566;
  }
}

/* 状态卡片 */
.status-info {
  padding: 16px;
}

.status-item {
  display: flex;
  align-items: center;
  margin-bottom: 12px;
  font-size: 14px;
  
  &:last-child {
    margin-bottom: 0;
  }
  
  .label {
    color: #969799;
    min-width: 80px;
  }
  
  .value {
    color: #323233;
    flex: 1;
  }
  
  :deep(.van-tag) {
    margin-left: 0;
  }
}

/* 商品明细 */
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
        
        .return-quantity {
          flex: 1;
          color: #1989fa;
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

.remaining-quantity {
  color: #ff976a;
}

/* 汇总信息 */
.items-summary {
  padding: 16px;
  background: #fafafa;
  border-top: 1px solid #f0f0f0;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  font-size: 14px;
  
  &:last-child {
    margin-bottom: 0;
  }
  
  &.total {
    font-size: 16px;
    font-weight: bold;
    margin-top: 8px;
    padding-top: 12px;
    border-top: 1px solid #e8e8e8;
  }
}

.summary-row .label {
  color: #323233;
}

.summary-row .value {
  color: #323233;
  font-weight: 500;
}

/* 空状态 */
.empty-items {
  background: white;
  border-radius: 8px;
  padding: 40px 20px;
  margin-bottom: 12px;
}
</style>