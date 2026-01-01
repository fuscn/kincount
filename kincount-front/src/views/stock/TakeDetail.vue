<!-- src/views/stock/TakeDetail.vue -->
<template>
  <div class="stock-take-detail">
    <van-nav-bar 
      title="盘点详情" 
      fixed 
      placeholder 
      left-text="返回" 
      @click-left="handleBack" 
    >
      <template #right>
        <van-button 
          size="small" 
          type="primary"
          @click="showActionSheet = true"
          v-if="showActions || canEdit"
        >
          操作
        </van-button>
      </template>
    </van-nav-bar>

    <div class="detail-container" v-if="!loading">
      <!-- 盘点基本信息 -->
      <van-cell-group title="基本信息">
        <van-cell title="盘点单号" :value="detail.take_no || detail.order_no" />
        <van-cell title="盘点仓库" :value="detail.warehouse?.name || detail.warehouse_name" />
        <van-cell title="盘点日期" :value="detail.take_date || detail.created_at?.split(' ')[0]" />
        <van-cell title="盘点状态">
          <template #value>
            <van-tag :type="getStatusTagType(detail.status)">
              {{ getStatusText(detail.status) }}
            </van-tag>
          </template>
        </van-cell>
        <van-cell title="创建时间" :value="detail.created_at" />
        <van-cell title="备注说明" :value="detail.remark || '无'" />
      </van-cell-group>

      <!-- 盘点商品明细 -->
      <div class="items-section">
        <div class="section-header">
          <span class="section-title">盘点商品明细</span>
          <span class="item-count">共{{ detail.items?.length || 0 }}项</span>
        </div>
        
        <div class="items-list">
          <div class="list-container">
            <div 
              v-for="(item, index) in detail.items" 
              :key="index" 
              class="list-item"
            >
              <!-- 商品信息区域 -->
              <div class="item-info">
                <!-- 第一行：商品名称 + 规格 -->
                <div class="row product-row">
                  <div class="product-name">{{ item.product?.name || item.product_name }}</div>
                  <div class="sku-spec">{{ formatSpec(item.sku?.spec) || item.product?.spec || '无规格' }}</div>
                </div>
                
                <!-- 第二行：库存对比 -->
                <div class="row stock-row">
                  <div class="stock-comparison">
                    <div class="stock-item">
                      <span class="stock-label">系统库存</span>
                      <span class="stock-value system">{{ item.system_quantity }}</span>
                    </div>
                    <div class="stock-arrow">→</div>
                    <div class="stock-item">
                      <span class="stock-label">实际库存</span>
                      <span class="stock-value actual">{{ item.actual_quantity }}</span>
                    </div>
                    <div class="stock-arrow">→</div>
                    <div class="stock-item difference-item">
                      <span class="stock-label">差异数</span>
                      <div class="difference-badge" :class="{ 
                        'positive': item.difference_quantity > 0, 
                        'negative': item.difference_quantity < 0,
                        'zero': item.difference_quantity === 0
                      }">
                        {{ item.difference_quantity > 0 ? '+' : '' }}{{ item.difference_quantity || 0 }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- 空状态 -->
          <van-empty 
            v-if="!detail.items?.length" 
            description="暂无盘点商品明细" 
            image="default"
          />
        </div>
      </div>

      <!-- 盘点统计 -->
      <div class="statistics-section" v-if="detail.items?.length > 0">
        <div class="section-header">
          <span class="section-title">盘点统计</span>
        </div>
        <div class="statistics-row">
          <div class="stat-item">
            <span class="stat-label">盘点商品数</span>
            <span class="stat-value">{{ detail.items.length }}项</span>
          </div>
          <div class="stat-item">
            <span class="stat-label">盘盈总数</span>
            <span class="stat-value positive-text">+{{ totalProfit }}</span>
          </div>
          <div class="stat-item">
            <span class="stat-label">盘亏总数</span>
            <span class="stat-value negative-text">{{ totalLoss }}</span>
          </div>
          <div class="stat-item">
            <span class="stat-label">总差异数</span>
            <span class="stat-value" :class="totalDifference > 0 ? 'positive-text' : totalDifference < 0 ? 'negative-text' : ''">
              {{ totalDifference > 0 ? '+' : '' }}{{ totalDifference }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- 加载状态 -->
    <van-loading v-if="loading" class="page-loading" />

    <!-- 操作面板 -->
    <van-action-sheet
      v-model:show="showActionSheet"
      cancel-text="取消"
      title="操作"
    >
      <van-button 
        type="primary" 
        block 
        @click="handleEditAction"
        v-perm="PERM.STOCK_TAKE"
        v-if="canEdit"
      >
        编辑
      </van-button>
      <van-button 
        type="primary" 
        block 
        @click="handleAuditAction"
        v-if="canAudit"
      >
        审核通过
      </van-button>
      <van-button 
        type="success" 
        block 
        @click="handleCompleteAction"
        v-if="canComplete"
      >
        完成盘点
      </van-button>
      <van-button 
        type="danger" 
        block 
        @click="handleCancelAction"
        v-if="canCancel"
      >
        取消盘点
      </van-button>
    </van-action-sheet>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { 
  showToast, 
  showConfirmDialog 
} from 'vant'
import { PERM } from '@/constants/permissions'
import { useStockStore } from '@/store/modules/stock'

const route = useRoute()
const router = useRouter()
const stockStore = useStockStore()

// 响应式数据
const loading = ref(true)
const detail = ref({})
const showActionSheet = ref(false)

// 计算属性
const totalProfit = computed(() => {
  if (!detail.value.items) return 0
  return detail.value.items.reduce((sum, item) => {
    return sum + (item.difference_quantity > 0 ? item.difference_quantity : 0)
  }, 0)
})

const totalLoss = computed(() => {
  if (!detail.value.items) return 0
  return Math.abs(detail.value.items.reduce((sum, item) => {
    return sum + (item.difference_quantity < 0 ? item.difference_quantity : 0)
  }, 0))
})

const totalDifference = computed(() => {
  if (!detail.value.items) return 0
  return detail.value.items.reduce((sum, item) => {
    return sum + (item.difference_quantity || 0)
  }, 0)
})

// 权限控制（以数据库为准：0-待盘点,1-盘点中,2-已完成,3-已取消）
const canEdit = computed(() => {
  return [0, 1].includes(detail.value.status) // 待盘点、盘点中可编辑
})

const canAudit = computed(() => {
  return [0, 1].includes(detail.value.status) // 待盘点、盘点中可审核
})

const canComplete = computed(() => {
  return [0, 1].includes(detail.value.status) // 待盘点、盘点中可完成
})

const canCancel = computed(() => {
  return [0, 1].includes(detail.value.status) // 待盘点、盘点中可取消
})

const showActions = computed(() => {
  return canAudit.value || canComplete.value || canCancel.value
})

// 获取状态文本
const getStatusText = (status) => {
  const statusMap = {
    0: '待盘点',
    1: '盘点中',
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

// 格式化规格显示
const formatSpec = (spec) => {
  if (!spec) return ''
  if (typeof spec === 'string') return spec
  if (typeof spec === 'object') {
    return Object.entries(spec).map(([key, value]) => `${key}:${value}`).join(' | ')
  }
  return ''
}

// 加载盘点详情
const loadDetail = async () => {
  try {
    loading.value = true
    const id = route.params.id
    await stockStore.loadTakeDetail(id)
    detail.value = stockStore.currentTake
    
    // items已经在详情数据中，不需要单独请求
  } catch (error) {
    console.error('加载盘点详情失败:', error)
    showToast('加载盘点详情失败')
  } finally {
    loading.value = false
  }
}

// 事件处理
const handleBack = () => {
  router.back()
}

const handleEditAction = () => {
  showActionSheet.value = false
  router.push(`/stock/take/edit/${detail.value.id}`)
}

const handleAuditAction = async () => {
  showActionSheet.value = false
  try {
    await showConfirmDialog({
      title: '确认审核',
      message: '确认审核通过此盘点单？'
    })
    
    await stockStore.auditTake(detail.value.id)
    showToast('审核成功')
    await loadDetail() // 重新加载详情
  } catch (error) {
    if (error !== 'cancel') {
      console.error('审核失败:', error)
      showToast('审核失败')
    }
  }
}

const handleCompleteAction = async () => {
  showActionSheet.value = false
  try {
    await showConfirmDialog({
      title: '确认完成',
      message: '确认完成此盘点单？'
    })
    
    await stockStore.completeStockTakeData(detail.value.id)
    showToast('盘点完成')
    await loadDetail() // 重新加载详情
  } catch (error) {
    if (error !== 'cancel') {
      console.error('完成盘点失败:', error)
      showToast('完成盘点失败')
    }
  }
}

const handleCancelAction = async () => {
  showActionSheet.value = false
  try {
    await showConfirmDialog({
      title: '确认取消',
      message: '确认取消此盘点单？取消后将无法恢复。'
    })
    
    await stockStore.cancelStockTakeData(detail.value.id)
    showToast('盘点已取消')
    await loadDetail() // 重新加载详情
  } catch (error) {
    if (error !== 'cancel') {
      console.error('取消盘点失败:', error)
      showToast('取消盘点失败')
    }
  }
}

onMounted(() => {
  loadDetail()
})
</script>

<style scoped lang="scss">
.stock-take-detail {
  background: #f7f8fa;
  min-height: 100vh;
  padding-bottom: 20px;
}

.detail-container {
  padding: 0;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  padding: 0 4px;
  
  .section-title {
    font-size: 16px;
    font-weight: 600;
    color: #323233;
  }
  
  .item-count {
    font-size: 14px;
    color: #969799;
  }
}

.items-section {
  margin-bottom: 16px;
}

.items-list {
  .list-container {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    overflow: hidden;
  }
  
  .list-item {
    padding: 12px 16px;
    border-bottom: 1px solid #f0f0f0;
    
    
    &:last-child {
      border-bottom: none;
    }
    
    .item-info {
      width: 100%;
    }
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
    display: flex;
    width: 100%;
    
    .product-name {
      font-size: 15px;
      font-weight: 600;
      color: #323233;
    }
    
    .sku-spec {
      font-size: 13px;
      color: #999;
      margin-left: 8px;
    }
  }
  
  .sku-row {
    justify-content: space-between;
    align-items: center;
    
    .sku-info {
      display: flex;
      align-items: center;
      gap: 12px;
      
      .sku-code {
        font-size: 13px;
        color: #666;
      }
      
      .sku-spec {
        font-size: 13px;
        color: #999;
      }
    }
    
    .difference-badge {
      padding: 2px 8px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: 600;
      min-width: 40px;
      text-align: center;
      
      &.positive {
        background: #f0f9ff;
        color: #1890ff;
      }
      
      &.negative {
        background: #fff2f0;
        color: #ff4d4f;
      }
      
      &.zero {
        background: #f6f6f6;
        color: #999;
      }
    }
  }
  
  .stock-row {
    width: 100%;
    
    .stock-comparison {
      display: flex;
      align-items: center;
      background: #fafafa;
      border-radius: 6px;
      padding: 8px 12px;
      width: 100%;
      
      .stock-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
        
        .stock-label {
          font-size: 12px;
          color: #999;
          margin-bottom: 2px;
        }
        
        .stock-value {
          font-size: 16px;
          font-weight: 600;
          
          &.system {
            color: #666;
          }
          
          &.actual {
            color: #1890ff;
          }
        }
      }
      
      .stock-arrow {
        font-size: 16px;
        color: #1890ff;
        margin: 0 16px;
        font-weight: bold;
      }
    }
  }
}

.statistics-section {
  margin-bottom: 16px;
  
  .statistics-row {
    display: flex;
    justify-content: space-around;
    background: white;
    border-radius: 8px;
    padding: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    
    .stat-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      flex: 1;
      
      .stat-label {
        font-size: 12px;
        color: #999;
        margin-bottom: 4px;
      }
      
      .stat-value {
        font-size: 16px;
        font-weight: 600;
        color: #323233;
      }
    }
  }
}

.positive-text {
  color: #52c41a !important;
  font-weight: 600;
}

.negative-text {
  color: #ff4d4f !important;
  font-weight: 600;
}



.page-loading {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 200px;
}

:deep(.van-cell__value) {
  color: #323233;
}
</style>