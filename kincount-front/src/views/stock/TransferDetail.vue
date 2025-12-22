<!-- src/views/stock/TransferDetail.vue -->
<template>
  <div class="stock-transfer-detail">
    <van-nav-bar 
      title="调拨详情" 
      fixed 
      placeholder 
      left-text="返回" 
      @click-left="handleBack" 
    >
      <template #right>
        <van-button 
          size="small" 
          type="primary" 
          @click="handleEdit"
          v-perm="PERM.STOCK_TRANSFER"
          v-if="canEdit"
        >
          编辑
        </van-button>
      </template>
    </van-nav-bar>

    <div class="detail-container" v-if="!loading">
      <!-- 调拨基本信息 -->
      <van-cell-group title="基本信息">
        <van-cell title="调拨单号" :value="detail.transfer_no" />
        <van-cell title="调出仓库" :value="detail.fromWarehouse?.name" />
        <van-cell title="调入仓库" :value="detail.toWarehouse?.name" />
        <van-cell title="调拨状态">
          <template #value>
            <van-tag :type="getStatusTagType(detail.status)">
              {{ getStatusText(detail.status) }}
            </van-tag>
          </template>
        </van-cell>
        <van-cell title="创建人" :value="detail.creator?.real_name || detail.created_by_name || '未知'" />
        <van-cell title="创建时间" :value="detail.created_at" />
        <van-cell title="审核人" :value="detail.auditor?.real_name || detail.audit_by_name || '未审核'" />
        <van-cell title="审核时间" :value="detail.audit_time || '未审核'" />
        <van-cell title="总金额" :value="`¥${detail.total_amount || 0}`" />
        <van-cell title="备注说明" :value="detail.remark || '无'" />
      </van-cell-group>

      <!-- 调拨商品明细 -->
      <div class="items-section">
        <div class="section-header">
          <span class="section-title">调拨商品明细</span>
          <span class="item-count">共{{ detail.items?.length || 0 }}项</span>
        </div>
        
        <div class="items-list">
          <div 
            v-for="(item, index) in detail.items" 
            :key="index" 
            class="item-card"
          >
            <!-- 商品信息区域 -->
            <div class="item-info">
              <!-- 第一行：商品名称 -->
              <div class="row product-row">
                <div class="product-name">{{ item.product_name }}</div>
              </div>
              
              <!-- 第二行：SKU编码 + 规格 -->
              <div class="row sku-row">
                <div class="sku-info">
                  <div class="sku-code">{{ item.product?.product_no }}</div>
                  <div class="sku-spec">{{ item.product?.spec || '无规格' }}</div>
                </div>
              </div>
              
              <!-- 第三行：调拨数量和金额 -->
              <div class="row quantity-row">
                <div class="quantity-info">
                  <div class="quantity-item">
                    <span class="quantity-label">调拨数量</span>
                    <span class="quantity-value">{{ item.quantity || 0 }}</span>
                  </div>
                  <div class="quantity-item">
                    <span class="quantity-label">单价</span>
                    <span class="quantity-value">¥{{ item.cost_price || 0 }}</span>
                  </div>
                  <div class="quantity-item">
                    <span class="quantity-label">小计</span>
                    <span class="quantity-value total">¥{{ item.total_amount || 0 }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- 空状态 -->
          <van-empty 
            v-if="!detail.items?.length" 
            description="暂无调拨商品" 
            image="search" 
          />
        </div>
      </div>

      <!-- 操作按钮区域 -->
              <div class="action-buttons" v-if="showActions">
                <van-button 
                  v-if="canAudit" 
                  type="primary" 
                  block 
                  @click="handleAudit"
                  class="action-button"
                >
                  审核通过
                </van-button>
                <van-button 
                  v-if="canTransfer" 
                  type="warning" 
                  block 
                  @click="handleTransfer"
                  class="action-button"
                >
                  执行调拨
                </van-button>
                <van-button 
                  v-if="canComplete" 
                  type="success" 
                  block 
                  @click="handleComplete"
                  class="action-button"
                >
                  确认完成
                </van-button>
                <van-button 
                  v-if="canCancel" 
                  type="danger" 
                  block 
                  @click="handleCancel"
                  class="action-button"
                >
                  取消调拨
                </van-button>
              </div>
    </div>

    <!-- 加载状态 -->
    <van-loading v-if="loading" class="page-loading" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { showToast, showConfirmDialog } from 'vant'
import { PERM } from '@/constants/permissions'
import { useStockStore } from '@/store/modules/stock'

const router = useRouter()
const route = useRoute()
const stockStore = useStockStore()

// 响应式数据
const loading = ref(true)
const detail = ref({})

// 计算属性
const canEdit = computed(() => {
  return detail.value.status === 0 // 待调拨可编辑
})

const canAudit = computed(() => {
  return detail.value.status === 0 // 待调拨可审核
})

const canTransfer = computed(() => {
  return detail.value.status === 1 // 调拨中可执行调拨
})

const canComplete = computed(() => {
  return detail.value.status === 1 // 调拨中可完成
})

const canCancel = computed(() => {
  return [1, 2, 3].includes(detail.value.status) // 待调拨、已审核、调拨中可取消
})

const showActions = computed(() => {
  return canAudit.value || canTransfer.value || canComplete.value || canCancel.value
})

// 获取状态文本
const getStatusText = (status) => {
  const statusMap = {
    0: '待调拨',
    1: '调拨中',
    2: '已完成',
    3: '已取消'
  }
  return statusMap[status] || '未知状态'
}

// 获取状态标签类型
const getStatusTagType = (status) => {
  const typeMap = {
    0: 'warning',
    1: 'primary',
    2: 'success',
    3: 'danger'
  }
  return typeMap[status] || 'default'
}

// 事件处理
const handleBack = () => {
  router.back()
}

const handleEdit = () => {
  router.push(`/stock/transfer/edit/${route.params.id}`)
}

const handleAudit = async () => {
  try {
    await showConfirmDialog({
      title: '确认审核',
      message: '确认审核通过此调拨单？'
    })
    
    await stockStore.auditTransfer(route.params.id)
    showToast('审核成功')
    await loadDetail()
  } catch (error) {
    if (error !== 'cancel') {
      showToast('审核失败')
    }
  }
}

const handleTransfer = async () => {
  try {
    await showConfirmDialog({
      title: '确认执行调拨',
      message: '确认执行此调拨单？此操作将立即改变库存数量。'
    })
    
    await stockStore.transferStockTransferData(route.params.id)
    showToast('调拨执行成功')
    await loadDetail()
  } catch (error) {
    if (error !== 'cancel') {
      showToast('调拨执行失败')
    }
  }
}

const handleComplete = async () => {
  try {
    await showConfirmDialog({
      title: '确认完成',
      message: '确认此调拨单已完成？'
    })
    
    await stockStore.completeStockTransferData(route.params.id)
    showToast('操作成功')
    await loadDetail()
  } catch (error) {
    if (error !== 'cancel') {
      showToast('操作失败')
    }
  }
}

const handleCancel = async () => {
  try {
    await showConfirmDialog({
      title: '确认取消',
      message: '确认取消此调拨单？'
    })
    
    await stockStore.cancelStockTransferData(route.params.id)
    showToast('取消成功')
    await loadDetail()
  } catch (error) {
    if (error !== 'cancel') {
      showToast('取消失败')
    }
  }
}

// 加载详情数据
const loadDetail = async () => {
  try {
    loading.value = true
    const data = await stockStore.loadTransferDetail(route.params.id)
    detail.value = data
    
    // API返回的详情数据已经包含了items，不需要单独请求
    detail.value.items = data.items || []
  } catch (error) {
    showToast('加载详情失败')
    console.error('加载调拨详情失败:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadDetail()
})
</script>

<style scoped lang="scss">
.stock-transfer-detail {
  background: #f7f8fa;
  min-height: 100vh;
  padding-bottom: 80px;
}

.detail-container {
  padding: 16px;
}

.items-section {
  margin-top: 16px;
  background: white;
  border-radius: 8px;
  overflow: hidden;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  background: #fafafa;
  border-bottom: 1px solid #ebedf0;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: #323233;
}

.item-count {
  font-size: 14px;
  color: #969799;
}

.items-list {
  padding: 0;
}

.item-card {
  padding: 16px;
  border-bottom: 1px solid #ebedf0;
  
  &:last-child {
    border-bottom: none;
  }
}

.item-info {
  .row {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    
    &:last-child {
      margin-bottom: 0;
    }
  }
  
  .product-row {
    .product-name {
      font-size: 16px;
      font-weight: 600;
      color: #323233;
      line-height: 1.4;
    }
  }
  
  .sku-row {
    .sku-info {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    .sku-code {
      font-size: 13px;
      color: #646566;
      background: #f7f8fa;
      padding: 2px 6px;
      border-radius: 4px;
    }
    
    .sku-spec {
      font-size: 13px;
      color: #969799;
    }
  }
  
  .quantity-row {
    .quantity-info {
      display: flex;
      justify-content: space-between;
      align-items: center;
      width: 100%;
    }
    
    .quantity-item {
      text-align: center;
      flex: 1;
    }
    
    .quantity-label {
      display: block;
      font-size: 12px;
      color: #969799;
      margin-bottom: 4px;
    }
    
    .quantity-value {
      display: block;
      font-size: 15px;
      font-weight: 600;
      color: #323233;
      
      &.total {
        color: #07c160;
      }
    }
  }
}

.action-buttons {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 16px;
  background: white;
  border-top: 1px solid #ebedf0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.action-button {
  height: 44px;
}

.page-loading {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 200px;
}

.van-cell-group {
  margin-bottom: 16px;
}
</style>