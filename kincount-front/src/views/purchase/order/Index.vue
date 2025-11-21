<!-- src/views/purchase/order/Index.vue -->
<template>
  <div class="purchase-order-page">
    <van-nav-bar title="采购订单" fixed placeholder>
      <template #right>
        <van-button 
          size="small" 
          type="primary" 
          @click="handleCreateOrder"
          v-perm="PERM.PURCHASE_ADD"
        >
          新建订单
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 状态筛选 -->
    <van-tabs v-model="filters.status" @change="loadOrderList(true)">
      <van-tab 
        v-for="tab in statusTabs" 
        :key="tab.value" 
        :name="tab.value" 
        :title="tab.title" 
      />
    </van-tabs>

    <!-- 订单列表 -->
    <van-pull-refresh v-model="loading" @refresh="loadOrderList(true)">
      <van-list
        v-model:loading="listLoading"
        :finished="finished"
        finished-text="没有更多了"
        @load="loadOrderList"
      >
        <van-cell-group>
          <van-cell
            v-for="order in orderList"
            :key="order.id"
            :title="`订单号: ${order.orderNo}`"
            :label="`供应商: ${order.supplierName} | 金额: ¥${order.totalAmount}`"
            :value="getStatusText(order.status)"
            @click="handleViewDetail(order)"
          >
            <template #extra>
              <van-tag :type="getStatusTagType(order.status)">
                {{ getStatusText(order.status) }}
              </van-tag>
            </template>
          </van-cell>
        </van-cell-group>
      </van-list>
    </van-pull-refresh>

    <!-- 分页 -->
    <Pagination
      v-model:page="pagination.page"
      :total="pagination.total"
      :page-size="pagination.pageSize"
      @change="handlePageChange"
    />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { 
  NavBar, 
  Button, 
  Tabs, 
  Tab, 
  Cell, 
  CellGroup, 
  List, 
  PullRefresh,
  Tag 
} from 'vant'
import { PERM } from '@/constants/permissions'
import { usePurchaseStore } from '@/store/modules/purchase'
import { ORDER_STATUS } from '@/constants'

const router = useRouter()
const purchaseStore = usePurchaseStore()

// 响应式数据
const filters = reactive({
  status: ''
})

const pagination = reactive({
  page: 1,
  pageSize: 15,
  total: 0
})

const statusTabs = ref([
  { title: '全部', value: '' },
  { title: '待审核', value: '1' },
  { title: '已审核', value: '2' },
  { title: '部分入库', value: '3' },
  { title: '已完成', value: '4' },
  { title: '已取消', value: '5' }
])

const orderList = ref([])
const loading = ref(false)
const listLoading = ref(false)
const finished = ref(false)

// 加载订单列表
const loadOrderList = async (isRefresh = false) => {
  if (isRefresh) {
    pagination.page = 1
    finished.value = false
  }

  try {
    const params = {
      page: pagination.page,
      limit: pagination.pageSize,
      status: filters.status
    }

    await purchaseStore.loadOrderList(params)
    orderList.value = purchaseStore.orderList
    pagination.total = purchaseStore.orderTotal
    
    // 检查是否加载完成
    if (orderList.value.length >= pagination.total) {
      finished.value = true
    }
  } catch (error) {
    console.error('加载订单列表失败:', error)
  } finally {
    loading.value = false
    listLoading.value = false
  }
}

// 获取状态文本
const getStatusText = (status) => {
  return ORDER_STATUS[status] || '未知状态'
}

// 获取状态标签类型
const getStatusTagType = (status) => {
  const typeMap = {
    1: 'warning',   // 待审核
    2: 'primary',   // 已审核
    3: 'success',   // 部分入库
    4: 'success',   // 已完成
    5: 'danger'     // 已取消
  }
  return typeMap[status] || 'default'
}

// 事件处理
const handlePageChange = (page) => {
  pagination.page = page
  loadOrderList()
}

const handleCreateOrder = () => {
  router.push('/purchase/order/create')
}

const handleViewDetail = (order) => {
  router.push(`/purchase/order/detail/${order.id}`)
}

onMounted(() => {
  loadOrderList(true)
})
</script>

<style scoped lang="scss">
.purchase-order-page {
  background: #f7f8fa;
  min-height: 100vh;
}
</style>