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

    <div class="detail-content">
      <!-- 退货基本信息 -->
      <van-cell-group title="退货信息">
        <van-cell title="退货单号" :value="returnOrder.return_no" />
        <van-cell title="销售订单" :value="returnOrder.order_no" />
        <van-cell title="客户名称" :value="returnOrder.customer_name" />
        <van-cell title="退货日期" :value="returnOrder.return_date" />
        <van-cell title="退货原因" :value="getReturnTypeText(returnOrder.return_type)" />
        <van-cell title="退货状态">
          <template #value>
            <van-tag :type="getStatusTagType(returnOrder.status)">
              {{ getStatusText(returnOrder.status) }}
            </van-tag>
          </template>
        </van-cell>
        <van-cell title="退货金额" :value="`-¥${returnOrder.total_amount}`" />
        <van-cell title="入库仓库" :value="returnOrder.warehouse_name" />
        <van-cell title="备注信息" :value="returnOrder.remark || '无'" />
      </van-cell-group>

      <!-- 退货商品明细 -->
      <van-cell-group title="退货商品明细">
        <div class="product-items">
          <div 
            v-for="(item, index) in returnOrder.items" 
            :key="index"
            class="product-item"
          >
            <div class="product-header">
              <span class="product-name">{{ item.product_name }}</span>
              <span class="product-price">-¥{{ item.unit_price }}</span>
            </div>
            <div class="product-info">
              <span>编号: {{ item.product_no }}</span>
              <span>规格: {{ item.spec || '无' }}</span>
            </div>
            <div class="product-quantity">
              <span>退货数量: {{ item.return_quantity }}{{ item.unit }}</span>
              <span class="amount">金额: -¥{{ item.total_amount }}</span>
            </div>
            <div class="sale-info">
              原销售数量: {{ item.sale_quantity }}{{ item.unit }}
            </div>
          </div>
        </div>
      </van-cell-group>

      <!-- 操作记录 -->
      <van-cell-group title="操作记录">
        <van-cell
          v-for="(log, index) in operationLogs"
          :key="index"
          :title="log.action"
          :label="log.operator + ' | ' + log.created_at"
          :value="log.remark"
        />
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
  showSuccessToast
} from 'vant'
import { useSaleStore } from '@/store/modules/sale'
import { auditSaleReturn, cancelSaleReturn, completeSaleReturn } from '@/api/sale'

const route = useRoute()
const router = useRouter()
const saleStore = useSaleStore()

const returnOrder = ref({
  id: '',
  return_no: '',
  order_no: '',
  customer_name: '',
  return_date: '',
  return_type: '',
  status: '',
  total_amount: 0,
  warehouse_name: '',
  remark: '',
  items: []
})

const operationLogs = ref([])

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

// 获取退货类型文本
const getReturnTypeText = (type) => {
  const typeMap = {
    quality: '质量问题',
    customer: '客户原因',
    wrong_delivery: '发错货',
    other: '其他'
  }
  return typeMap[type] || '未知类型'
}

// 加载退货详情
const loadReturnDetail = async () => {
  try {
    const returnId = route.params.id
    await saleStore.loadReturnDetail(returnId)
    returnOrder.value = { ...saleStore.currentReturn }
    
    // 加载操作记录
    await loadOperationLogs(returnId)
  } catch (error) {
    showToast('加载退货详情失败')
    console.error('加载退货详情失败:', error)
  }
}

// 加载操作记录
const loadOperationLogs = async (returnId) => {
  try {
    // 这里需要根据实际情况调用API获取操作记录
    // 暂时模拟数据
    operationLogs.value = [
      {
        action: '创建退货单',
        operator: '系统管理员',
        created_at: new Date().toLocaleString(),
        remark: '创建销售退货单'
      }
    ]
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
    
    await auditSaleReturn(returnOrder.value.id)
    showSuccessToast('审核成功')
    loadReturnDetail()
  } catch (error) {
    if (error !== 'cancel') {
      showToast('审核失败')
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
    
    await completeSaleReturn(returnOrder.value.id)
    showSuccessToast('已完成')
    loadReturnDetail()
  } catch (error) {
    if (error !== 'cancel') {
      showToast('操作失败')
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
    
    await cancelSaleReturn(returnOrder.value.id)
    showSuccessToast('取消成功')
    loadReturnDetail()
  } catch (error) {
    if (error !== 'cancel') {
      showToast('取消失败')
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
        color: #07c160;
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
        color: #07c160;
        font-weight: 500;
      }
    }
    
    .sale-info {
      margin-top: 4px;
      font-size: 12px;
      color: #969799;
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
</style>