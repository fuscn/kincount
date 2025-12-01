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
        <div class="order-list">
          <div v-for="order in orderList" :key="order.id" class="order-card" @click="handleViewOrder(order)">
            <div class="order-header">
              <div class="order-no">{{ order.order_no }}</div>
              <van-tag :type="getStatusTagType(order.status)" size="medium">
                {{ getStatusText(order.status) }}
              </van-tag>
            </div>
            
            <div class="order-info">
              <div class="info-row">
                <span class="label">客户：</span>
                <span class="value">{{ order.customer?.name || '--' }}</span>
              </div>
              <div v-if="order.customer?.contact_person" class="info-row">
                <span class="label">联系人：</span>
                <span class="value">{{ order.customer.contact_person }}</span>
              </div>
              <div v-if="order.customer?.phone" class="info-row">
                <span class="label">电话：</span>
                <span class="value">{{ order.customer.phone }}</span>
              </div>
              <div class="info-row">
                <span class="label">总金额：</span>
                <span class="value amount">¥{{ formatPrice(order.total_amount) }}</span>
              </div>
              <div class="info-row">
                <span class="label">创建时间：</span>
                <span class="value">{{ formatDateTime(order.created_at) }}</span>
              </div>
              <div v-if="order.order_date" class="info-row">
                <span class="label">订单日期：</span>
                <span class="value">{{ formatDate(order.order_date) }}</span>
              </div>
              <div v-if="order.delivery_date" class="info-row">
                <span class="label">交货日期：</span>
                <span class="value">{{ formatDate(order.delivery_date) }}</span>
              </div>
              <div v-if="order.remark" class="info-row">
                <span class="label">备注：</span>
                <span class="value remark">{{ order.remark }}</span>
              </div>
            </div>
          </div>
        </div>

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

// 格式化价格
const formatPrice = (price) => {
  if (price === null || price === undefined || price === '') return '0.00'
  const num = Number(price)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

// 格式化日期
const formatDate = (date) => {
  if (!date) return '--'
  try {
    const d = new Date(date)
    if (isNaN(d.getTime())) return '--'
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  } catch (error) {
    return '--'
  }
}

// 格式化日期时间
const formatDateTime = (dateTime) => {
  if (!dateTime) return '--'
  try {
    const d = new Date(dateTime)
    if (isNaN(d.getTime())) return '--'
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    const hours = String(d.getHours()).padStart(2, '0')
    const minutes = String(d.getMinutes()).padStart(2, '0')
    return `${year}-${month}-${day} ${hours}:${minutes}`
  } catch (error) {
    return '--'
  }
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
    3: 'info',
    4: 'success',
    5: 'danger'
  }
  return typeMap[status] || 'default'
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
    const response = await getCustomerList()
    console.log('客户列表响应:', response)
    
    // 根据实际返回的数据结构提取客户列表
    let customerList = []
    
    if (response?.code === 200 && response.data?.list) {
      // 结构为: { code: 200, msg: "获取成功", data: { list: [...], total: ... } }
      customerList = response.data.list
    } else if (response?.list) {
      // 结构为: { list: [...], total: ... }
      customerList = response.list
    } else if (Array.isArray(response)) {
      // 直接是数组
      customerList = response
    } else if (response?.data && Array.isArray(response.data)) {
      // 结构为: { data: [...] }
      customerList = response.data
    }

    console.log('提取的客户列表:', customerList)

    customerOptions.value = [
      { text: '全部客户', value: '' },
      ...customerList.map(item => ({
        text: item.name,
        value: item.id
      })).filter(item => item.text && item.value)
    ]

  } catch (error) {
    console.error('加载客户列表失败:', error)
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

onMounted(() => {
  loadCustomerOptions()
  loadOrderList(true)
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

.order-list {
  padding: 8px;
}

.order-card {
  background: white;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  
  .order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f5f5f5;
    
    .order-no {
      font-size: 16px;
      font-weight: 600;
      color: #323233;
    }
  }
  
  .order-info {
    .info-row {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 8px;
      font-size: 14px;
      line-height: 1.4;
      
      &:last-child {
        margin-bottom: 0;
      }
      
      .label {
        color: #646566;
        white-space: nowrap;
        margin-right: 8px;
      }
      
      .value {
        color: #323233;
        text-align: right;
        flex: 1;
        word-break: break-word;
        
        &.amount {
          font-weight: 600;
          color: #ee0a24;
        }
        
        &.remark {
          color: #969799;
          font-style: italic;
        }
      }
    }
  }
}

.page-loading {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 200px;
}
</style>