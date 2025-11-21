<template>
  <div class="sale-order-page">
    <van-nav-bar title="销售订单" fixed placeholder>
      <template #right>
        <van-button size="small" type="primary" @click="handleCreateOrder" v-perm="PERM.SALE_ADD">
          新建订单
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 搜索和筛选 -->
    <div class="filter-section">
      <van-search v-model="filters.keyword" placeholder="搜索订单号/客户名称" show-action @search="loadOrderList(true)"
        @clear="handleClearSearch">
        <template #action>
          <div @click="loadOrderList(true)">搜索</div>
        </template>
      </van-search>

      <van-dropdown-menu>
        <van-dropdown-item v-model="filters.status" :options="statusOptions" @change="loadOrderList(true)" />
        <van-dropdown-item v-model="filters.customer_id" :options="customerOptions" @change="loadOrderList(true)" />
      </van-dropdown-menu>
    </div>

    <!-- 订单列表 -->
    <van-pull-refresh v-model="refreshing" @refresh="loadOrderList(true)">
      <van-list v-model:loading="listLoading" :finished="finished" :immediate-check="false"
        :finished-text="orderList.length === 0 ? '暂无销售订单' : '没有更多了'" @load="loadOrderList">
        <van-cell-group>
          <van-cell v-for="order in orderList" :key="order.id" :title="`订单号: ${order.order_no}`"
            :label="getOrderLabel(order)" @click="handleViewOrder(order)">
            <template #value>
              <div class="order-amount">¥{{ order.total_amount }}</div>
            </template>
            <template #extra>
              <van-tag :type="getStatusTagType(order.status)">
                {{ getStatusText(order.status) }}
              </van-tag>
            </template>
          </van-cell>
        </van-cell-group>

        <!-- 空状态 -->
        <van-empty v-if="!listLoading && !refreshing && orderList.length === 0" description="暂无销售订单" image="search" />
      </van-list>
    </van-pull-refresh>

    <!-- 加载状态 -->
    <van-loading v-if="initialLoading" class="page-loading" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  showToast
} from 'vant'
import { PERM } from '@/constants/permissions'
import { useSaleStore } from '@/store/modules/sale'
import { getCustomerList } from '@/api/customer'

const router = useRouter()
const saleStore = useSaleStore()

// 响应式数据
const filters = reactive({
  keyword: '',
  status: '',
  customer_id: ''
})

const pagination = reactive({
  page: 1,
  pageSize: 15,
  total: 0
})

const statusOptions = ref([
  { text: '全部状态', value: '' },
  { text: '待审核', value: '1' },
  { text: '已审核', value: '2' },
  { text: '部分出库', value: '3' },
  { text: '已完成', value: '4' },
  { text: '已取消', value: '5' }
])

const customerOptions = ref([{ text: '全部客户', value: '' }])
const orderList = ref([])
const refreshing = ref(false)
const listLoading = ref(false)
const initialLoading = ref(true)
const finished = ref(false)

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

// 获取订单标签信息
const getOrderLabel = (order) => {
  const parts = []
  if (order.customer_name) {
    parts.push(`客户: ${order.customer_name}`)
  }
  if (order.created_at) {
    parts.push(`创建: ${order.created_at}`)
  }
  if (order.order_date) {
    parts.push(`日期: ${order.order_date}`)
  }
  return parts.join(' | ')
}

// 加载订单列表
const loadOrderList = async (isRefresh = false) => {
  console.log('📥 加载订单列表，模式:', isRefresh ? '刷新' : '加载更多')

  if (isRefresh) {
    pagination.page = 1
    finished.value = false
    refreshing.value = true
  } else {
    listLoading.value = true
  }

  try {
    const params = {
      page: pagination.page,
      limit: pagination.pageSize,
      ...filters
    }

    // 移除空值参数
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] == null) delete params[key]
    })

    await saleStore.loadOrderList(params)

    let listData = []
    let totalCount = 0

    if (saleStore.orderList && Array.isArray(saleStore.orderList)) {
      listData = saleStore.orderList
      totalCount = saleStore.orderTotal || 0
    }

    if (isRefresh) {
      orderList.value = listData
    } else {
      orderList.value = [...orderList.value, ...listData]
    }

    pagination.total = totalCount

    // 检查是否加载完成
    if (orderList.value.length >= pagination.total) {
      finished.value = true
    }

  } catch (error) {
    console.error('加载销售订单失败:', error)
    showToast('加载销售订单失败')
    finished.value = true
  } finally {
    refreshing.value = false
    listLoading.value = false
    initialLoading.value = false
  }
}

// 加载客户选项
const loadCustomerOptions = async () => {
  try {
    const customers = await getCustomerList()
    const customerData = customers?.data || customers || []

    customerOptions.value = [
      { text: '全部客户', value: '' },
      ...customerData.map(item => ({
        text: item.name,
        value: item.id
      }))
    ]
  } catch (error) {
    showToast('加载客户列表失败')
  }
}

// 事件处理
const handleCreateOrder = () => {
  router.push('/sale/order/create')
}

const handleViewOrder = (order) => {
  router.push(`/sale/order/detail/${order.id}`)
}

const handleClearSearch = () => {
  filters.keyword = ''
  loadOrderList(true)
}

// 添加下拉刷新处理
const handleRefresh = () => {
  loadOrderList(true)
}

onMounted(() => {
  loadCustomerOptions()
  loadOrderList(true)  // 这里触发一次
})
</script>

<style scoped lang="scss">
.sale-order-page {
  background: #f7f8fa;
  min-height: 100vh;
}

.filter-section {
  background: white;
  margin-bottom: 12px;
  border-radius: 8px;
  overflow: hidden;
}

.order-amount {
  font-weight: bold;
  color: #ee0a24;
  font-size: 14px;
}

.page-loading {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 200px;
}
</style>