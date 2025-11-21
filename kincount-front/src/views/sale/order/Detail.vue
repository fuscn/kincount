<template>
  <div class="sale-order-detail-page">
    <van-nav-bar 
      :title="`销售订单详情`"
      left-text="返回"
      left-arrow
      @click-left="$router.back()"
    >
      <template #right>
        <van-button 
          v-if="order.status === 1" 
          size="small" 
          type="primary" 
          @click="handleAudit"
          v-perm="PERM.SALE_AUDIT"
        >
          审核
        </van-button>
        <van-button 
          v-if="order.status === 2" 
          size="small" 
          type="warning" 
          @click="handleOutbound"
        >
          出库
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 加载状态 -->
    <van-loading v-if="loading" class="page-loading" />

    <!-- 错误状态 -->
    <div v-else-if="error" class="error-state">
      <van-empty description="加载订单详情失败">
        <van-button type="primary" @click="loadOrderDetail">重试</van-button>
      </van-empty>
    </div>

    <!-- 正常内容 -->
    <div v-else class="detail-content">
      <!-- 订单基本信息 -->
      <van-cell-group title="订单信息">
        <van-cell title="订单编号" :value="order.order_no" />
        <van-cell title="客户名称" :value="order.customer?.name || '未知客户'" />
        <van-cell title="发货仓库" :value="order.warehouse?.name || '未知仓库'" />
        <van-cell title="订单日期" :value="formatDate(order.created_at)" />
        <van-cell title="期望交货日期" :value="order.expected_date || '未设置'" />
        <van-cell title="订单状态">
          <template #value>
            <van-tag :type="getStatusTagType(order.status)">
              {{ getStatusText(order.status) }}
            </van-tag>
          </template>
        </van-cell>
        <van-cell title="订单金额" :value="`¥${order.total_amount || 0}`" />
        <van-cell title="优惠金额" :value="`¥${order.discount_amount || 0}`" />
        <van-cell title="实付金额" :value="`¥${order.final_amount || 0}`" />
        <van-cell title="已付金额" :value="`¥${order.paid_amount || 0}`" />
        <van-cell title="备注信息" :value="order.remark || '无'" />
      </van-cell-group>

      <!-- 订单商品明细 -->
      <van-cell-group title="商品明细">
        <div class="product-items">
          <div 
            v-for="(item, index) in order.items" 
            :key="index"
            class="product-item"
          >
            <div class="product-header">
              <span class="product-name">{{ item.product?.name || `商品${item.product_id}` }}</span>
              <span class="product-price">¥{{ item.price }}</span>
            </div>
            <div class="product-info">
              <span>编号: {{ item.product?.product_no || item.product_id }}</span>
              <span>规格: {{ item.product?.spec || '无' }}</span>
              <span>单位: {{ item.product?.unit || '个' }}</span>
            </div>
            <div class="product-quantity">
              <span>销售数量: {{ item.quantity }}{{ item.product?.unit || '个' }}</span>
              <span class="amount">金额: ¥{{ item.total_amount }}</span>
            </div>
            <div v-if="item.delivered_quantity > 0" class="outbound-info">
              已出库: {{ item.delivered_quantity }}{{ item.product?.unit || '个' }}
            </div>
          </div>
        </div>

        <!-- 空状态 -->
        <van-empty
          v-if="!order.items || order.items.length === 0"
          description="暂无商品明细"
          image="search"
        />
      </van-cell-group>

      <!-- 操作记录 -->
      <van-cell-group title="操作记录">
        <van-cell
          title="创建订单"
          :label="`${order.creator?.real_name || '系统'} | ${formatDate(order.created_at)}`"
          value="创建销售订单"
        />
        <van-cell
          v-if="order.auditor"
          title="审核订单"
          :label="`${order.auditor?.real_name || '系统'} | ${formatDate(order.audit_time)}`"
          value="审核通过"
        />
        <van-cell
          v-if="order.status === 5"
          title="取消订单"
          :label="`${order.creator?.real_name || '系统'} | ${formatDate(order.updated_at)}`"
          value="订单已取消"
        />
      </van-cell-group>
    </div>

    <!-- 底部操作按钮 -->
    <div class="action-bar" v-if="order.status === 1 && !loading && !error">
      <van-button 
        type="danger" 
        block
        @click="handleCancel"
        v-perm="PERM.SALE_DELETE"
      >
        取消订单
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
import { PERM } from '@/constants/permissions'
import { useSaleStore } from '@/store/modules/sale'
import { auditSaleOrder, cancelSaleOrder } from '@/api/sale'
import dayjs from 'dayjs'

const route = useRoute()
const router = useRouter()
const saleStore = useSaleStore()

const order = ref({
  id: '',
  order_no: '',
  customer: {},
  warehouse: {},
  total_amount: 0,
  discount_amount: 0,
  final_amount: 0,
  paid_amount: 0,
  status: 1,
  remark: '',
  expected_date: '',
  created_at: '',
  updated_at: '',
  audit_time: '',
  creator: {},
  auditor: null,
  items: []
})

const loading = ref(false)
const error = ref(false)

// 格式化日期
const formatDate = (dateString) => {
  if (!dateString) return ''
  return dayjs(dateString).format('YYYY-MM-DD HH:mm:ss')
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

// 获取状态标签类型
const getStatusTagType = (status) => {
  const typeMap = {
    1: 'warning',
    2: 'primary',
    3: 'primary',
    4: 'success',
    5: 'danger'
  }
  return typeMap[status] || 'default'
}

// 加载订单详情
const loadOrderDetail = async () => {
  loading.value = true
  error.value = false
  
  try {
    const orderId = route.params.id
    console.log('开始加载订单详情，ID:', orderId)
    
    // 直接调用API，绕过store可能的问题
    const orderDetail = await saleStore.loadOrderDetail(orderId)
    console.log('store返回的订单详情:', orderDetail)
    
    if (saleStore.currentOrder) {
      order.value = { ...saleStore.currentOrder }
      console.log('设置到页面的订单数据:', order.value)
      
      // 确保items是数组
      if (!order.value.items) {
        order.value.items = []
      }
    } else {
      throw new Error('订单数据为空')
    }
  } catch (err) {
    console.error('加载订单详情失败:', err)
    error.value = true
    showToast('加载订单详情失败: ' + (err.message || '未知错误'))
  } finally {
    loading.value = false
  }
}

// 审核订单
const handleAudit = async () => {
  try {
    await showConfirmDialog({
      title: '确认审核',
      message: '确定要审核通过这个销售订单吗？'
    })
    
    await auditSaleOrder(order.value.id)
    showSuccessToast('审核成功')
    // 重新加载订单详情
    await loadOrderDetail()
  } catch (error) {
    if (error !== 'cancel') {
      showToast('审核失败: ' + (error.message || '未知错误'))
    }
  }
}

// 取消订单
const handleCancel = async () => {
  try {
    await showConfirmDialog({
      title: '确认取消',
      message: '确定要取消这个销售订单吗？此操作不可恢复。'
    })
    
    await cancelSaleOrder(order.value.id)
    showSuccessToast('取消成功')
    // 重新加载订单详情
    await loadOrderDetail()
  } catch (error) {
    if (error !== 'cancel') {
      showToast('取消失败: ' + (error.message || '未知错误'))
    }
  }
}

// 出库操作
const handleOutbound = () => {
  router.push(`/sale/stock/create?order_id=${order.value.id}`)
}

onMounted(() => {
  loadOrderDetail()
})
</script>

<style scoped lang="scss">
.sale-order-detail-page {
  background: #f7f8fa;
  min-height: 100vh;
}

.page-loading {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 200px;
}

.error-state {
  padding: 20px;
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
    border: 1px solid #ebedf0;
    
    .product-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
      
      .product-name {
        font-weight: 500;
        font-size: 14px;
        flex: 1;
      }
      
      .product-price {
        color: #ee0a24;
        font-weight: bold;
        font-size: 14px;
      }
    }
    
    .product-info {
      display: flex;
      flex-wrap: wrap;
      gap: 8px 12px;
      font-size: 12px;
      color: #969799;
      margin-bottom: 8px;
    }
    
    .product-quantity {
      display: flex;
      justify-content: space-between;
      font-size: 13px;
      
      .amount {
        color: #ee0a24;
        font-weight: 500;
      }
    }
    
    .outbound-info {
      margin-top: 4px;
      font-size: 12px;
      color: #07c160;
      text-align: right;
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
  box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.1);
}
</style>