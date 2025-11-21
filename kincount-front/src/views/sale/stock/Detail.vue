<template>
  <div class="sale-outbound-detail-page">
    <van-nav-bar :title="`销售出库详情`" left-text="返回" left-arrow @click-left="$router.back()">
      <template #right>
        <van-button v-if="stock.status === 1" size="small" type="primary" @click="handleAudit" v-perm="PERM.SALE_AUDIT">
          审核
        </van-button>
        <van-button v-if="stock.status === 2" size="small" type="success" @click="handleComplete">
          完成
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 加载状态 -->
    <van-loading v-if="loading" class="page-loading" />

    <!-- 错误状态 -->
    <div v-else-if="error" class="error-state">
      <van-empty description="加载出库详情失败">
        <van-button type="primary" @click="loadStockDetail">重试</van-button>
      </van-empty>
    </div>

    <!-- 正常内容 -->
    <div v-else class="detail-content">
      <!-- 出库基本信息 -->
      <van-cell-group title="出库信息">
        <van-cell title="出库单号" :value="stock.stock_no" />
        <van-cell title="销售订单" :value="stock.sale_order_id ? `订单${stock.sale_order_id}` : '无关联订单'" />
        <van-cell title="客户名称" :value="stock.customer?.name || '未知客户'" />
        <van-cell title="出库仓库" :value="stock.warehouse?.name || '未知仓库'" />
        <van-cell title="出库日期" :value="formatDate(stock.created_at)" />
        <van-cell title="出库状态">
          <template #value>
            <van-tag :type="getStatusTagType(stock.status)">
              {{ getStatusText(stock.status) }}
            </van-tag>
          </template>
        </van-cell>
        <van-cell title="出库金额" :value="`¥${stock.total_amount || 0}`" />
        <van-cell title="备注信息" :value="stock.remark || '无'" />
      </van-cell-group>

      <!-- 出库商品明细 -->
      <van-cell-group title="商品明细">
        <div class="product-items">
          <div v-for="(item, index) in stock.items" :key="index" class="product-item">
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
              <span>出库数量: {{ item.quantity }}{{ item.product?.unit || '个' }}</span>
              <span class="amount">金额: ¥{{ item.total_amount }}</span>
            </div>
          </div>
        </div>

        <!-- 空状态 -->
        <van-empty v-if="!stock.items || stock.items.length === 0" description="暂无商品明细" image="search" />
      </van-cell-group>

      <!-- 操作记录 -->
      <van-cell-group title="操作记录">
        <van-cell title="创建出库单" :label="`${stock.creator?.real_name || '系统'} | ${formatDate(stock.created_at)}`"
          value="创建销售出库单" />
        <van-cell v-if="stock.auditor" title="审核出库单"
          :label="`${stock.auditor?.real_name || '系统'} | ${formatDate(stock.audit_time)}`" value="审核通过" />
        <van-cell v-if="stock.status === 4" title="取消出库单"
          :label="`${stock.creator?.real_name || '系统'} | ${formatDate(stock.updated_at)}`" value="出库单已取消" />
      </van-cell-group>
    </div>

    <!-- 底部操作按钮 -->
    <div class="action-bar" v-if="stock.status === 1 && !loading && !error">
      <van-button type="danger" block @click="handleCancel" v-perm="PERM.SALE_DELETE">
        取消出库
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
import { auditSaleStock, cancelSaleStock, completeSaleStock } from '@/api/sale'
import dayjs from 'dayjs'

const route = useRoute()
const router = useRouter()
const saleStore = useSaleStore()

const stock = ref({
  id: '',
  stock_no: '',
  sale_order_id: '',
  customer: {},
  warehouse: {},
  total_amount: 0,
  status: 1,
  remark: '',
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
    4: '已取消',
    5: '已完成'
  }
  return statusMap[status] || '未知状态'
}

// 获取状态标签类型
const getStatusTagType = (status) => {
  const typeMap = {
    1: 'warning',
    2: 'primary',
    3: 'primary',
    4: 'danger',
    5: 'success'
  }
  return typeMap[status] || 'default'
}

// 加载出库详情
const loadStockDetail = async () => {
  loading.value = true
  error.value = false

  try {
    const stockId = route.params.id
    console.log('开始加载出库详情，ID:', stockId)

    await saleStore.loadStockDetail(stockId)

    if (saleStore.currentStock) {
      // 直接使用后端返回的数据结构
      stock.value = { ...saleStore.currentStock }
      console.log('设置到页面的出库数据:', stock.value)

      // 确保items是数组
      if (!stock.value.items) {
        stock.value.items = []
      }
    } else {
      throw new Error('出库数据为空')
    }
  } catch (err) {
    console.error('加载出库详情失败:', err)
    error.value = true
    showToast('加载出库详情失败: ' + (err.message || '未知错误'))
  } finally {
    loading.value = false
  }
}

// 审核出库单
const handleAudit = async () => {
  try {
    await showConfirmDialog({
      title: '确认审核',
      message: '确定要审核通过这个销售出库单吗？'
    })

    await auditSaleStock(stock.value.id)
    showSuccessToast('审核成功')
    // 重新加载出库详情
    await loadStockDetail()
  } catch (error) {
    if (error !== 'cancel') {
      showToast('审核失败: ' + (error.message || '未知错误'))
    }
  }
}

// 完成出库单
const handleComplete = async () => {
  try {
    await showConfirmDialog({
      title: '确认完成',
      message: '确定要标记这个出库单为已完成吗？'
    })

    await completeSaleStock(stock.value.id)
    showSuccessToast('已完成')
    await loadStockDetail()
  } catch (error) {
    if (error !== 'cancel') {
      showToast('操作失败: ' + (error.message || '未知错误'))
    }
  }
}

// 取消出库单
const handleCancel = async () => {
  try {
    await showConfirmDialog({
      title: '确认取消',
      message: '确定要取消这个销售出库单吗？此操作不可恢复。'
    })

    await cancelSaleStock(stock.value.id)
    showSuccessToast('取消成功')
    await loadStockDetail()
  } catch (error) {
    if (error !== 'cancel') {
      showToast('取消失败: ' + (error.message || '未知错误'))
    }
  }
}

onMounted(() => {
  loadStockDetail()
})
</script>

<style scoped lang="scss">
.sale-outbound-detail-page {
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