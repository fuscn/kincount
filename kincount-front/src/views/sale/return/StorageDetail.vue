<template>
  <div class="return-stock-detail-container">
    <!-- 导航栏 -->
    <van-nav-bar 
      title="销售退货入库单" 
      left-text="返回" 
      left-arrow 
      @click-left="$router.back()" 
      fixed
      placeholder
    >
      <template #right>
        <van-button v-if="hasActions" type="primary" size="small" @click="showActionSheet = true">
          操作
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 加载状态 -->
    <div v-if="loading" class="loading-state">
      <van-loading size="24px" vertical>加载中...</van-loading>
    </div>

    <!-- 详情内容 -->
    <div v-else class="detail-content">
      <!-- 基本信息卡片 -->
      <van-cell-group title="入库信息">
        <van-cell title="出入库单号" :value="returnStockDetail.stock_no || '--'" class="no-wrap-value" />
        <van-cell title="客户" :value="returnStockDetail.target_info?.name || '--'" />
        <van-cell title="仓库" :value="returnStockDetail.warehouse?.name || '--'" />
        <van-cell title="状态">
          <template #value>
            <van-tag :type="getStatusTagType(returnStockDetail.status)">
              {{ getStatusText(returnStockDetail.status) }}
            </van-tag>
          </template>
        </van-cell>
        <van-cell title="总金额" :value="`¥${formatPrice(returnStockDetail.total_amount)}`" />
        <van-cell title="创建人" :value="returnStockDetail.creator?.real_name || '--'" />
        <van-cell title="创建时间" :value="formatDateTime(returnStockDetail.created_at)" />
        
        <template v-if="returnStockDetail.status === 1">
          <van-cell title="审核人" :value="returnStockDetail.auditor?.real_name || '--'" />
          <van-cell title="审核时间" :value="formatDateTime(returnStockDetail.audit_time)" />
        </template>
        
        <van-cell 
          v-if="returnStockDetail.remark" 
          title="备注" 
          :value="returnStockDetail.remark" 
        />
      </van-cell-group>

        <!-- 商品明细 -->
        <van-cell-group title="商品明细" v-if="returnStockItems.length > 0">
          <div class="product-items">
            <template v-for="(item, index) in returnStockItems" :key="index">
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
                        <div class="product-quantity">{{ item.quantity || 0 }}{{ item.sku?.unit || item.product?.unit || '个' }}</div>
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
                        <!-- 退货单关联信息 -->
                        <div class="return-order-info" v-if="item.ReturnOrderItem">
                          关联退货数量: {{ item.ReturnOrderItem.return_quantity || 0 }}
                        </div>
                        <div class="product-total">¥{{ formatPrice(item.total_amount) }}</div>
                      </div>
                    </div>
                  </template>
                </van-cell>
              </van-swipe-cell>
              <!-- 手动添加分割线 -->
              <div v-show="index < returnStockItems.length - 1" class="product-divider"></div>
            </template>
          </div>
          <div class="total-amount">
            <span>合计: {{ returnStockItems.length }} 种商品</span>
            <span class="total-price">总金额: ¥{{ formatPrice(returnStockDetail.total_amount) }}</span>
          </div>
        </van-cell-group>

        <!-- 商品明细空状态 -->
        <div v-else class="empty-items">
          <van-empty image="search" description="暂无商品明细" />
        </div>

        <!-- 关联退货单 -->
        <van-cell-group title="关联退货单" v-if="returnStockDetail.return">
          <van-cell 
            :title="returnStockDetail.return.return_no" 
            is-link
            :value="`¥${formatPrice(returnStockDetail.return.total_amount)}`"
            @click="handleViewReturnDetail"
          >
            <template #right-icon>
              <van-tag :type="getStatusTagType(returnStockDetail.return.status)">
                {{ getStatusText(returnStockDetail.return.status) }}
              </van-tag>
            </template>
          </van-cell>
        </van-cell-group>
    </div>

    <!-- 操作面板 -->
    <van-action-sheet v-model:show="showActionSheet" :actions="actions" cancel-text="取消" close-on-click-action
      @select="onActionSelect" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useStockStore } from '@/store/modules/stock'
import { showConfirmDialog, showToast } from 'vant'
import { PERM } from '@/constants/permissions'

const route = useRoute()
const router = useRouter()
const stockStore = useStockStore()

// 退货入库单ID
const id = route.params.id

// 加载状态
const loading = ref(true)
const refreshing = ref(false)

// 操作面板
const showActionSheet = ref(false)

// 详情数据
const returnStockDetail = computed(() => stockStore.currentReturnStock || {})
const returnStockItems = computed(() => returnStockDetail.value.items || [])

// 计算合计数量
const totalQuantity = computed(() => {
  return returnStockItems.value.reduce((sum, item) => sum + (item.quantity || 0), 0)
})

// 操作权限判断
const canAudit = computed(() => {
  return returnStockDetail.value.status === 0 // 待审核
})

const canCancel = computed(() => {
  return returnStockDetail.value.status === 0 // 待审核
})

const hasActions = computed(() => {
  return canAudit.value || canCancel.value
})

// 操作面板选项
const actions = computed(() => {
  const actionList = []

  if (canAudit.value) {
    actionList.push({
      name: '审核',
      action: 'audit',
      color: '#07c160'
    })
  }

  if (canCancel.value) {
    actionList.push({
      name: '取消',
      action: 'cancel',
      color: '#ee0a24'
    })
  }

  return actionList
})

// 格式化金额
const formatPrice = (price) => {
  if (price === null || price === undefined || price === '') return '0.00'
  const num = Number(price)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

// 格式化日期时间
const formatDateTime = (dateString) => {
  if (!dateString) return '--'
  const date = new Date(dateString)
  return date.toLocaleDateString() + ' ' + date.toLocaleTimeString().slice(0, 5)
}

// 状态文本映射
const getStatusText = (status) => {
  // 退货入库单状态
  const stockStatusMap = {
    0: '待审核',
    1: '已审核',
    2: '已取消'
  }
  
  // 退货单状态
  const returnStatusMap = {
    0: '待审核',
    1: '已审核',
    2: '部分入库/出库',
    3: '已入库/出库',
    4: '已退款/收款',
    5: '已完成',
    6: '已取消'
  }
  
  // 默认使用退货入库单状态映射
  return stockStatusMap[status] || returnStatusMap[status] || '未知'
}

// 状态标签类型
const getStatusTagType = (status) => {
  // 退货入库单状态标签类型
  const stockTypeMap = {
    0: 'warning',  // 待审核 - 警告色
    1: 'success',  // 已审核 - 成功色
    2: 'danger'    // 已取消 - 危险色
  }
  
  // 退货单状态标签类型
  const returnTypeMap = {
    0: 'warning',  // 待审核 - 警告色
    1: 'primary',  // 已审核 - 主要色
    2: 'warning',  // 部分入库/出库 - 警告色
    3: 'primary',  // 已入库/出库 - 主要色
    4: 'success',  // 已退款/收款 - 成功色
    5: 'success',  // 已完成 - 成功色
    6: 'danger'    // 已取消 - 危险色
  }
  
  // 默认使用退货入库单状态标签类型
  return stockTypeMap[status] || returnTypeMap[status] || 'default'
}

// 加载详情数据
const loadDetailData = async () => {
  try {
    loading.value = true
    // 只加载一次，后端已经返回了items数据
    await stockStore.loadReturnStockDetail(id)
  } catch (error) {
    console.error('加载退货入库单详情失败:', error)
    showToast('加载详情失败: ' + (error.message || '未知错误'))
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

// 操作面板选择
const onActionSelect = (action) => {
  showActionSheet.value = false

  switch (action.action) {
    case 'audit':
      handleAudit()
      break
    case 'cancel':
      handleCancel()
      break
  }
}

// 审核操作
const handleAudit = () => {
  showConfirmDialog({
    title: '确认审核',
    message: `确定要审核退货入库单 ${returnStockDetail.value.stock_no} 吗？审核后商品将入库。`
  }).then(async () => {
    try {
      await stockStore.auditReturnStockData(id)
      showToast('审核成功')
      loadDetailData() // 重新加载数据
    } catch (error) {
      showToast('审核失败: ' + (error.message || '未知错误'))
    }
  }).catch(() => {
    // 用户取消
  })
}

// 取消操作
const handleCancel = () => {
  showConfirmDialog({
    title: '确认取消',
    message: `确定要取消退货入库单 ${returnStockDetail.value.stock_no} 吗？此操作不可恢复。`
  }).then(async () => {
    try {
      await stockStore.cancelReturnStockData(id)
      showToast('取消成功')
      loadDetailData() // 重新加载数据
    } catch (error) {
      showToast('取消失败: ' + (error.message || '未知错误'))
    }
  }).catch(() => {
    // 用户取消
  })
}

// 查看退货单详情
const handleViewReturnDetail = () => {
  if (returnStockDetail.value.return_id) {
    router.push(`/sale/return/detail/${returnStockDetail.value.return_id}`)
  } else {
    showToast('未找到关联的退货单')
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
  // 移除底部的padding，因为删除了底部按钮
  padding-bottom: 0;
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
        
        .return-order-info {
          flex: 1;
          color: #07c160;
          font-size: 12px;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
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

/* 空状态 */
.empty-items {
  background: white;
  border-radius: 8px;
  padding: 40px 20px;
  margin-bottom: 12px;
}

/* 防止单号换行 */
.no-wrap-value {
  :deep(.van-cell__value) {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
    padding-left: 8px;
  }
  
  :deep(.van-cell__title) {
    flex: 0 0 auto;
    min-width: auto;
  }
}

/* 删除底部的操作按钮区域 */
</style>