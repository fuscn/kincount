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
          @click="handleEdit"
          v-perm="PERM.STOCK_TAKE"
          v-if="canEdit"
        >
          编辑
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
                  <div class="sku-code">{{ item.sku?.sku_code || item.product?.product_no }}</div>
                  <div class="sku-spec">{{ formatSpec(item.sku?.spec) || item.product?.spec || '无规格' }}</div>
                </div>
              </div>
              
              <!-- 第三行：库存对比 -->
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
        <van-cell-group title="盘点统计">
          <van-cell title="盘点商品数" :value="`${detail.items.length}项`" />
          <van-cell title="盘盈总数" :value="`+${totalProfit}`" value-class="positive-text" />
          <van-cell title="盘亏总数" :value="`${totalLoss}`" value-class="negative-text" />
          <van-cell title="总差异数" :value="`${totalDifference > 0 ? '+' : ''}${totalDifference}`" 
                    :value-class="totalDifference > 0 ? 'positive-text' : totalDifference < 0 ? 'negative-text' : ''" />
        </van-cell-group>
      </div>

      <!-- 操作按钮 -->
      <div class="action-section" v-if="showActions">
        <van-button 
          v-if="canAudit" 
          type="primary" 
          block 
          @click="handleAudit"
          class="action-btn"
        >
          审核通过
        </van-button>
        <van-button 
          v-if="canComplete" 
          type="success" 
          block 
          @click="handleComplete"
          class="action-btn"
        >
          完成盘点
        </van-button>
        <van-button 
          v-if="canCancel" 
          type="danger" 
          block 
          @click="handleCancel"
          class="action-btn"
        >
          取消盘点
        </van-button>
      </div>
    </div>

    <!-- 加载状态 -->
    <van-loading v-if="loading" class="page-loading" />
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

// 权限控制
const canEdit = computed(() => {
  return [0, 1].includes(detail.value.status) // 待盘点、盘点中可编辑
})

const canAudit = computed(() => {
  return detail.value.status === 1 // 盘点中可审核
})

const canComplete = computed(() => {
  return detail.value.status === 2 // 已审核可完成
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
    2: '已审核',
    3: '已完成',
    4: '已取消'
  }
  return statusMap[status] || '未知状态'
}

// 获取状态标签类型
const getStatusTagType = (status) => {
  const typeMap = {
    0: 'warning',
    1: 'primary',
    2: 'success', 
    3: 'success',
    4: 'danger'
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

const handleEdit = () => {
  router.push(`/stock/take/edit/${detail.value.id}`)
}

const handleAudit = async () => {
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

const handleComplete = async () => {
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

const handleCancel = async () => {
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
  padding-bottom: 80px;
}

.detail-container {
  padding: 16px;
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
  .item-card {
    background: white;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
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
      font-size: 15px;
      font-weight: 600;
      color: #323233;
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
    .stock-comparison {
      display: flex;
      align-items: center;
      background: #fafafa;
      border-radius: 6px;
      padding: 8px 12px;
      
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
}

.positive-text {
  color: #52c41a !important;
  font-weight: 600;
}

.negative-text {
  color: #ff4d4f !important;
  font-weight: 600;
}

.action-section {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background: white;
  padding: 12px 16px;
  box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.1);
  
  .action-btn {
    margin-bottom: 8px;
    
    &:last-child {
      margin-bottom: 0;
    }
  }
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