<template>
  <div class="sale-order-index">
    <!-- 导航栏 -->
    <van-nav-bar title="销售订单" fixed placeholder>
      <template #right>
        <van-button size="small" type="primary" @click="handleCreateOrder" v-perm="PERM.SALE_ADD">
          新建订单
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 筛选区域 -->
    <div class="filter-wrapper">
      <!-- 状态标签筛选 -->
      <van-tabs v-model="activeStatus" @change="handleStatusChange">
        <van-tab title="全部" name="" />
        <van-tab title="待审核" name="1" />
        <van-tab title="已审核" name="2" />
        <van-tab title="部分出库" name="3" />
        <van-tab title="已完成" name="4" />
        <van-tab title="已取消" name="5" />
      </van-tabs>

      <!-- 搜索与高级筛选 -->
      <div class="search-filter">
        <van-search 
          v-model="keyword" 
          placeholder="搜索订单号/客户名称" 
          @search="handleSearch" 
          @clear="handleClearSearch" 
        />
        
        <!-- 筛选行：客户、时间在同一行 -->
        <div class="filter-row">
          <!-- 客户筛选 -->
          <div class="customer-filter">
            <CustomerSelect
              ref="customerSelectRef"
              v-model="selectedCustomer"
              :placeholder="getCustomerPlaceholder()"
              :show-all-option="true"
              :show-confirm-button="false"
              :trigger-button-type="'default'"
              :trigger-button-size="'normal'"
              :trigger-button-block="true"
              @change="handleCustomerChange"
            />
          </div>
          
          <!-- 时间筛选 -->
          <div class="date-filter">
            <van-dropdown-menu style="width: 100%; height: 100%;">
              <van-dropdown-item 
                v-model="dateRange" 
                :options="dateOptions" 
                :title="getDateTitle()"
                @change="handleFilterChange" 
              />
            </van-dropdown-menu>
          </div>
        </div>
      </div>
    </div>

    <!-- 订单列表 -->
    <van-pull-refresh v-model="refreshing" @refresh="handleRefresh">
      <van-list 
        v-model:loading="loading" 
        :finished="finished" 
        finished-text="没有更多订单了" 
        @load="handleLoadMore" 
        :immediate-check="false"
      >
        <!-- 订单项 -->
        <van-cell-group class="order-list">
          <van-cell 
            v-for="order in orderList" 
            :key="order.id" 
            :title="`订单号：${order.order_no || '未生成'}`" 
            :label="getOrderLabel(order)" 
            @click="handleViewOrder(order.id)" 
            is-link
          >
            <template #extra>
              <div class="order-extra">
                <van-tag :type="getStatusTagType(order.status)">
                  {{ getStatusText(order.status) }}
                </van-tag>
                <div class="amount">¥{{ formatPrice(order.final_amount || order.total_amount) }}</div>
              </div>
            </template>
          </van-cell>
        </van-cell-group>

        <!-- 空状态 -->
        <van-empty 
          v-if="!loading && !refreshing && orderList.length === 0" 
          image="search" 
          :description="getEmptyDescription()" 
        />
      </van-list>
    </van-pull-refresh>

    <!-- 初始加载状态 -->
    <van-loading v-if="initialLoading" class="initial-loading" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { showToast, showFailToast } from 'vant'
import { PERM } from '@/constants/permissions'
import { useSaleStore } from '@/store/modules/sale'
import CustomerSelect from '@/components/business/CustomerSelect.vue'

const router = useRouter()
const saleStore = useSaleStore()

// 响应式数据
const initialLoading = ref(true)
const refreshing = ref(false)
const loading = ref(false)
const finished = ref(false)

// 筛选参数
const activeStatus = ref('')
const keyword = ref('')
const selectedCustomer = ref('')
const dateRange = ref('')
const isLoading = ref(false)

// 分页参数
const pagination = reactive({
  page: 1,
  pageSize: 10
})

// 日期选项
const dateOptions = ref([
  { text: '全部时间', value: '' },
  { text: '今日', value: 'today' },
  { text: '本周', value: 'week' },
  { text: '本月', value: 'month' },
  { text: '近3个月', value: 'quarter' }
])

// 计算属性
const orderList = computed(() => saleStore.orderList || [])

// 获取客户占位符文本
const getCustomerPlaceholder = () => {
  if (selectedCustomer.value === 0) return '全部客户'
  if (selectedCustomer.value) {
    // 可以尝试从列表中查找客户名称，如果没有则显示ID
    return selectedCustomer.value
  }
  return '选择客户'
}

// 获取日期标题
const getDateTitle = () => {
  const option = dateOptions.value.find(opt => opt.value === dateRange.value)
  return option ? option.text : '选择时间'
}

// 获取空状态描述
const getEmptyDescription = () => {
  if (keyword.value) return `未找到"${keyword.value}"相关订单`
  if (selectedCustomer.value && selectedCustomer.value !== 0) return '该客户暂无订单'
  if (dateRange.value) return '该时间段内暂无订单'
  return '暂无销售订单数据'
}

// 格式化金额
const formatPrice = (price) => {
  if (price === null || price === undefined || price === '') return '0.00'
  const num = Number(price)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

// 格式化时间
const formatTime = (time) => {
  if (!time) return ''
  try {
    const d = new Date(time)
    if (isNaN(d.getTime())) return ''
    return d.toLocaleDateString()
  } catch (error) {
    return ''
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

// 获取订单标签信息
const getOrderLabel = (order) => {
  const labels = []
  if (order.customer?.name) labels.push(`客户：${order.customer.name}`)
  if (order.warehouse?.name) labels.push(`仓库：${order.warehouse.name}`)
  if (order.created_at) labels.push(`创建时间：${formatTime(order.created_at)}`)
  if (order.creator?.real_name) labels.push(`创建人：${order.creator.real_name}`)
  if (order.expected_date) labels.push(`交货日期：${formatTime(order.expected_date)}`)
  return labels.join(' | ')
}

// 客户选择变更事件
const handleCustomerChange = (value, name) => {
  console.log('客户变更:', value, name)
  selectedCustomer.value = value
  handleFilterChange()
}

// 状态标签变更事件
const handleStatusChange = (name) => {
  activeStatus.value = name
  loadOrderList(true)
}

// 搜索相关方法
const handleSearch = () => {
  loadOrderList(true)
}

const handleClearSearch = () => {
  keyword.value = ''
  loadOrderList(true)
}

// 筛选条件变化
const handleFilterChange = () => {
  loadOrderList(true)
}

// 下拉刷新
const handleRefresh = () => {
  loadOrderList(true)
}

// 加载更多
const handleLoadMore = () => {
  loadOrderList(false)
}

// 加载订单列表
const loadOrderList = async (isRefresh = false) => {
  if (isLoading.value) return
  if (loading.value && !isRefresh) return
  if (refreshing.value && isRefresh) return

  isLoading.value = true

  try {
    if (isRefresh) {
      pagination.page = 1
      finished.value = false
      refreshing.value = true
    } else {
      if (finished.value) return
      loading.value = true
    }

    // 处理时间范围参数
    let dateRangeParam = ''
    if (dateRange.value) {
      dateRangeParam = dateRange.value
    }

    const params = {
      page: pagination.page,
      pageSize: pagination.pageSize,
      keyword: keyword.value,
      status: activeStatus.value,
      date_range: dateRangeParam
    }
    
    // 只有当customer_id存在且不为0时才加入参数
    if (selectedCustomer.value && selectedCustomer.value !== 0) {
      params.customer_id = selectedCustomer.value
    }
    
    // 移除空值参数
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] == null || (Array.isArray(params[key]) && params[key].length === 0)) {
        delete params[key]
      }
    })

    await saleStore.loadOrderList(params)
    
    const currentList = orderList.value || []
    const total = saleStore.orderTotal || 0
    
    // 判断是否加载完成
    if (currentList.length >= total || currentList.length === 0) {
      finished.value = true
    } else {
      if (!isRefresh) {
        pagination.page++
      }
    }
  } catch (error) {
    showFailToast('加载销售订单失败')
    console.error('loadOrderList error:', error)
    finished.value = false
  } finally {
    initialLoading.value = false
    refreshing.value = false
    loading.value = false
    isLoading.value = false
  }
}

// 创建新订单
const handleCreateOrder = () => {
  router.push('/sale/order/create')
}

// 查看订单详情
const handleViewOrder = (id) => {
  router.push(`/sale/order/detail/${id}`)
}

// 页面挂载时加载数据
onMounted(() => {
  loadOrderList(true)
})
</script>

<style scoped lang="scss">
.sale-order-index {
  min-height: 100vh;
  background-color: #f5f5f5;
  padding-top: 46px; // 适配fixed导航栏

  // 筛选区域样式
  .filter-wrapper {
    background-color: #fff;
    margin-bottom: 10px;

    .search-filter {
      padding: 0 10px 10px;

      .filter-row {
        display: flex;
        align-items: stretch; // 确保子元素高度一致
        gap: 10px;
        margin-top: 10px;
        height: 30px; // 设置固定高度，确保两个按钮高度一致
        
        .customer-filter,
        .date-filter {
          flex: 1; // 平分宽度
          display: flex;
        }
        
        // 客户选择器样式
        .customer-filter {
          :deep(.customer-select) {
            width: 100%;
            height: 100%;
            
            .default-trigger {
              width: 100%;
              height: 100%;
              
              .van-button {
                width: 100%;
                height: 100%;
                border-radius: 6px;
                background-color: #f7f8fa;
                border: 1px solid #ebedf0;
                color: #323233;
                font-size: 14px;
                
                &:active {
                  background-color: #f2f3f5;
                }
              }
            }
          }
        }
        
        // 日期筛选样式
        .date-filter {
          :deep(.van-dropdown-menu) {
            width: 100%;
            height: 100%;
            
            .van-dropdown-menu__bar {
              height: 100%;
              box-shadow: none;
              border-radius: 6px;
              background-color: #f7f8fa;
              border: 1px solid #ebedf0;
              
              .van-dropdown-menu__item {
                flex: 1;
                
                &:first-child {
                  border-radius: 6px;
                }
                
                .van-dropdown-menu__title {
                  font-size: 14px;
                  color: #323233;
                  padding: 0 12px;
                  line-height: 30px;
                  
                  &::after {
                    border-color: #969799;
                  }
                }
              }
            }
          }
        }
      }

      :deep(.van-dropdown-menu) {
        margin-top: 0;
      }
    }
  }

  // 订单列表样式
  .order-list {
    .van-cell {
      padding: 15px 10px;
      margin-bottom: 10px;
      background-color: #fff;
      border-radius: 8px;

      .order-extra {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 5px;

        .amount {
          font-size: 14px;
          font-weight: 600;
          color: #ee0a24;
        }
      }
    }
  }

  // 初始加载样式
  .initial-loading {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
  }
}
</style>