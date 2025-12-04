<template>
  <div class="sale-return-detail-page">
    <van-nav-bar 
      :title="`销售退货详情`"
      left-text="返回"
      left-arrow
      @click-left="$router.back()"
    >
      <template #right>
        <van-button 
          v-if="returnOrder.status === 1" 
          size="small" 
          type="primary" 
          @click="handleAudit"
        >
          审核
        </van-button>
        <van-button 
          v-if="returnOrder.status === 2" 
          size="small" 
          type="success" 
          @click="handleComplete"
        >
          完成
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 加载状态 -->
    <van-loading v-if="loading" class="page-loading" />

    <div v-else class="detail-content">
      <!-- 退货基本信息 -->
      <van-cell-group title="退货信息">
        <van-cell title="退货单号" :value="returnOrder.return_no" />
        <van-cell title="关联订单" :value="returnOrder.source_order_id || '--'" />
        <van-cell title="客户名称" :value="returnOrder.target?.name || '--'" />
        <van-cell title="退货日期" :value="formatDate(returnOrder.created_at)" />
        <van-cell title="退货类型" :value="getReturnTypeText(returnOrder.return_type)" />
        <van-cell title="退货原因" :value="returnOrder.return_reason || '--'" />
        <van-cell title="退货状态">
          <template #value>
            <van-tag :type="getStatusTagType(returnOrder.status)">
              {{ getStatusText(returnOrder.status) }}
            </van-tag>
          </template>
        </van-cell>
        <van-cell title="退货金额" :value="`-¥${formatPrice(returnOrder.total_amount)}`" />
        <van-cell title="退款金额" :value="`¥${formatPrice(returnOrder.refund_amount)}`" />
        <van-cell title="已退金额" :value="`¥${formatPrice(returnOrder.refunded_amount)}`" />
        <van-cell title="仓库" :value="returnOrder.warehouse?.name || '--'" />
        <van-cell title="备注信息" :value="returnOrder.remark || '无'" />
      </van-cell-group>

      <!-- 退货商品明细 -->
      <van-cell-group title="退货商品明细" v-if="returnOrder.items && returnOrder.items.length > 0">
        <div class="product-items">
          <div 
            v-for="(item, index) in returnOrder.items" 
            :key="index"
            class="product-item"
          >
            <div class="product-header">
              <span class="product-name">{{ item.product?.name || '产品' + item.id }}</span>
              <span class="product-price">-¥{{ formatPrice(item.unit_price) }}</span>
            </div>
            <div class="product-info">
              <span>编号: {{ item.product_no || '--' }}</span>
              <span>规格: {{ item.sku?.spec || '无' }}</span>
            </div>
            <div class="product-quantity">
              <span>退货数量: {{ item.return_quantity }}{{ item.unit || '个' }}</span>
              <span class="amount">金额: -¥{{ formatPrice(item.total_amount) }}</span>
            </div>
          </div>
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

    <!-- 底部操作按钮 -->
    <div class="action-bar" v-if="returnOrder.status === 1">
      <van-button 
        type="danger" 
        block
        @click="handleCancel"
      >
        取消退货
      </van-button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { 
  showToast,
  showConfirmDialog,
  showSuccessToast,
  showFailToast
} from 'vant'
import { useSaleStore } from '@/store/modules/sale'
// 暂时注释掉可能不存在的API调用
// import { auditSaleReturn, cancelSaleReturn, completeSaleReturn } from '@/api/sale'

const route = useRoute()
const router = useRouter()
const saleStore = useSaleStore()

const returnOrder = ref({
  return_no: '',
  target: {},
  warehouse: {},
  items: []
})

const operationLogs = ref([])
const loading = ref(true)

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

// 获取状态文本
const getStatusText = (status) => {
  const statusMap = {
    1: '待审核',
    2: '已审核',
    3: '已完成',
    4: '已取消'
  }
  return statusMap[status] || '未知状态'
}

// 获取状态标签类型
const getStatusTagType = (status) => {
  const typeMap = {
    1: 'warning',
    2: 'primary',
    3: 'success',
    4: 'danger'
  }
  return typeMap[status] || 'default'
}

// 获取退货类型文本 - 根据API返回的数字类型
const getReturnTypeText = (type) => {
  const typeMap = {
    1: '质量问题',
    2: '客户原因',
    3: '发错货',
    4: '其他'
  }
  return typeMap[type] || '未知类型'
}

// 加载退货详情
const loadReturnDetail = async () => {
  try {
    loading.value = true
    const returnId = route.params.id
    console.log('加载退货详情ID:', returnId)
    
    // 检查是否有loadReturnDetail方法，如果没有直接调用API
    if (saleStore.loadReturnDetail) {
      await saleStore.loadReturnDetail(returnId)
      returnOrder.value = { ...saleStore.currentReturn }
    } else {
      // 或者直接调用API
      console.error('saleStore.loadReturnDetail方法不存在')
      showFailToast('加载详情功能未实现')
    }
    
    console.log('退货详情数据:', returnOrder.value)
    
    // 加载操作记录
    await loadOperationLogs(returnId)
  } catch (error) {
    console.error('加载退货详情失败:', error)
    showFailToast('加载退货详情失败')
  } finally {
    loading.value = false
  }
}

// 加载操作记录
const loadOperationLogs = async (returnId) => {
  try {
    // 模拟操作记录数据
    operationLogs.value = [
      {
        action: '创建退货单',
        operator: returnOrder.value.creator?.name || '系统管理员',
        created_at: formatDate(returnOrder.value.created_at),
        remark: '创建销售退货单'
      }
    ]
    
    // 如果有审核人，添加审核记录
    if (returnOrder.value.auditor) {
      operationLogs.value.push({
        action: '审核退货单',
        operator: returnOrder.value.auditor?.name || '审核员',
        created_at: formatDate(returnOrder.value.audit_time),
        remark: '审核通过'
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
    
    // TODO: 调用审核API
    // await auditSaleReturn(returnOrder.value.id)
    showSuccessToast('审核成功（功能开发中）')
    // 重新加载数据
    loadReturnDetail()
  } catch (error) {
    if (error !== 'cancel') {
      showFailToast('审核失败')
    }
  }
}

// 完成退货
const handleComplete = async () => {
  try {
    await showConfirmDialog({
      title: '确认完成',
      message: '确定要标记这个退货单为已完成吗？'
    })
    
    // TODO: 调用完成API
    // await completeSaleReturn(returnOrder.value.id)
    showSuccessToast('已完成（功能开发中）')
    loadReturnDetail()
  } catch (error) {
    if (error !== 'cancel') {
      showFailToast('操作失败')
    }
  }
}

// 取消退货
const handleCancel = async () => {
  try {
    await showConfirmDialog({
      title: '确认取消',
      message: '确定要取消这个销售退货单吗？此操作不可恢复。'
    })
    
    // TODO: 调用取消API
    // await cancelSaleReturn(returnOrder.value.id)
    showSuccessToast('取消成功（功能开发中）')
    loadReturnDetail()
  } catch (error) {
    if (error !== 'cancel') {
      showFailToast('取消失败')
    }
  }
}

onMounted(() => {
  loadReturnDetail()
})
</script>

<style scoped lang="scss">
.sale-return-detail-page {
  background: #f7f8fa;
  min-height: 100vh;
}

.detail-content {
  padding: 16px;
}

.product-items {
  .product-item {
    background: white;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 8px;
    
    .product-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
      
      .product-name {
        font-weight: 500;
        font-size: 14px;
      }
      
      .product-price {
        color: #f53f3f;
        font-weight: bold;
      }
    }
    
    .product-info {
      display: flex;
      gap: 12px;
      font-size: 12px;
      color: #969799;
      margin-bottom: 8px;
    }
    
    .product-quantity {
      display: flex;
      justify-content: space-between;
      font-size: 13px;
      
      .amount {
        color: #f53f3f;
        font-weight: 500;
      }
    }
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