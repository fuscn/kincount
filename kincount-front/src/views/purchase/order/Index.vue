<template>
  <div class="purchase-order-index">
    <!-- 导航栏 -->
    <van-nav-bar title="采购订单管理" fixed placeholder>
      <template #right>
        <van-button size="small" type="primary" @click="handleCreate">
          新增订单
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
        <van-tab title="已完成" name="4" />
        <van-tab title="已取消" name="5" />
      </van-tabs>

      <!-- 搜索与高级筛选 -->
      <div class="search-filter">
        <van-search
          v-model="keyword"
          placeholder="搜索订单号/供应商名称"
          @search="handleSearch"
          @clear="handleClearSearch"
        />
        <van-dropdown-menu>
          <!-- 供应商筛选 -->
          <van-dropdown-item
            v-model="selectedSupplier"
            :options="supplierOptions"
            placeholder="选择供应商"
            @change="handleFilterChange"
          />
          <!-- 时间筛选（可选） -->
          <van-dropdown-item
            v-model="dateRange"
            :options="dateOptions"
            placeholder="选择时间"
            @change="handleFilterChange"
          />
        </van-dropdown-menu>
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
            @click="handleDetail(order.id)"
            is-link
          >
            <template #extra>
              <div class="order-extra">
                <van-tag :type="getStatusTagType(order.status)">
                  {{ getStatusText(order.status) }}
                </van-tag>
                <div class="amount">¥{{ (order.total_amount || 0).toFixed(2) }}</div>
              </div>
            </template>
          </van-cell>
        </van-cell-group>

        <!-- 空状态 -->
        <van-empty
          v-if="!loading && !refreshing && orderList.length === 0"
          image="search"
          description="暂无采购订单数据"
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
import { showToast } from 'vant'
import { usePurchaseStore } from '@/store/modules/purchase'
// 引入供应商API
import { getSupplierList } from '@/api/supplier'

// 路由实例
const router = useRouter()
// 采购Store实例
const purchaseStore = usePurchaseStore()

// 响应式数据
const initialLoading = ref(true) // 初始加载状态
const refreshing = ref(false) // 下拉刷新状态
const loading = ref(false) // 上拉加载状态
const finished = ref(false) // 加载完成标记
const activeStatus = ref('') // 选中的状态标签
const keyword = ref('') // 搜索关键词
const selectedSupplier = ref('') // 选中的供应商ID
const dateRange = ref('') // 选中的时间范围

// 分页参数
const pagination = reactive({
  page: 1,
  pageSize: 10
})

// 供应商选项（初始化含"全部"）
const supplierOptions = ref([{ text: '全部供应商', value: '' }])
// 时间范围选项（可选）
const dateOptions = ref([
  { text: '全部时间', value: '' },
  { text: '今日', value: 'today' },
  { text: '本周', value: 'week' },
  { text: '本月', value: 'month' },
  { text: '近3个月', value: 'quarter' }
])

// 关键修改：直接使用store中的orderList，不重复处理数据
const orderList = computed(() => purchaseStore.orderList)

/**
 * 加载供应商列表（初始化调用）
 */
const loadSuppliers = async () => {
  try {
    const res = await getSupplierList()
    console.log('供应商API响应:', res)
    
    let list = []
    if (res && res.code === 200) {
      if (Array.isArray(res.data)) {
        list = res.data
      } else if (res.data && res.data.list && Array.isArray(res.data.list)) {
        list = res.data.list
      }
    }
    
    supplierOptions.value = [
      { text: '全部供应商', value: '' },
      ...list.map(item => ({ text: item.name, value: item.id }))
    ]
  } catch (error) {
    showToast('加载供应商列表失败')
    console.error('loadSuppliers error:', error)
  }
}

/**
 * 加载采购订单列表
 * @param {Boolean} isRefresh - 是否为刷新（重置分页）
 */
const loadOrderList = async (isRefresh = false) => {
  // 防止重复加载
  if (loading.value && !isRefresh) return
  if (refreshing.value && isRefresh) return

  console.log(`开始加载订单列表: isRefresh=${isRefresh}, page=${pagination.page}`)

  // 刷新时重置分页和加载状态
  if (isRefresh) {
    pagination.page = 1
    finished.value = false
    refreshing.value = true
  } else {
    // 加载更多时检查是否已完成
    if (finished.value) return
    loading.value = true
  }

  try {
    // 构造筛选参数
    const params = {
      page: pagination.page,
      pageSize: pagination.pageSize,
      status: activeStatus.value,
      keyword: keyword.value,
      supplierId: selectedSupplier.value,
      dateRange: dateRange.value
    }
    
    // 移除空值参数
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] == null) delete params[key]
    })

    console.log('请求参数:', params)

    // 关键修改：直接调用store方法，让store处理数据
    const res = await purchaseStore.loadOrderList(params)
    console.log('Store返回的数据:', res)
    
    // 关键修改：直接从store获取数据，不再重复处理
    const currentList = purchaseStore.orderList || []
    console.log('当前订单列表:', currentList)
    console.log('列表长度:', currentList.length)

    // 判断是否加载完成（无更多数据）
    if (currentList.length < pagination.pageSize) {
      finished.value = true
      console.log('加载完成，没有更多数据')
    } else {
      pagination.page++ // 只有加载更多时才递增页码
      console.log('继续加载下一页，当前页码:', pagination.page)
    }
  } catch (error) {
    showToast('加载采购订单失败')
    console.error('loadOrderList error:', error)
  } finally {
    // 重置加载状态
    initialLoading.value = false
    refreshing.value = false
    loading.value = false
  }
}

/**
 * 状态标签变更事件
 */
const handleStatusChange = () => {
  console.log('状态变更:', activeStatus.value)
  loadOrderList(true)
}

/**
 * 搜索事件
 */
const handleSearch = () => {
  console.log('搜索关键词:', keyword.value)
  loadOrderList(true)
}

/**
 * 清空搜索框
 */
const handleClearSearch = () => {
  console.log('清空搜索')
  keyword.value = ''
  loadOrderList(true)
}

/**
 * 筛选条件变更（供应商/时间）
 */
const handleFilterChange = () => {
  console.log('筛选条件变更 - 供应商:', selectedSupplier.value, '时间:', dateRange.value)
  loadOrderList(true)
}

/**
 * 下拉刷新
 */
const handleRefresh = () => {
  console.log('下拉刷新')
  loadOrderList(true)
}

/**
 * 上拉加载更多
 */
const handleLoadMore = () => {
  console.log('上拉加载更多')
  loadOrderList(false)
}

/**
 * 跳转到新增订单页
 */
const handleCreate = () => {
  router.push('/purchase/order/create')
}

/**
 * 跳转到订单详情页
 * @param {Number|String} id - 订单ID
 */
const handleDetail = (id) => {
  router.push(`/purchase/order/detail/${id}`)
}

/**
 * 获取订单状态文本
 * @param {String|Number} status - 状态码
 */
const getStatusText = (status) => {
  const statusMap = {
    1: '待审核',
    2: '已审核', 
    3: '部分入库',
    4: '已完成',
    5: '已取消'
  }
  return statusMap[status] || '未知状态'
}

/**
 * 获取状态标签类型（适配Vant Tag）
 * @param {String|Number} status - 状态码
 */
const getStatusTagType = (status) => {
  const typeMap = {
    1: 'warning', // 待审核
    2: 'primary', // 已审核
    3: 'info',    // 部分入库
    4: 'success', // 已完成
    5: 'danger'   // 已取消
  }
  return typeMap[status] || 'default'
}

/**
 * 构造订单列表的标签描述
 * @param {Object} order - 订单对象
 */
const getOrderLabel = (order) => {
  const labels = []
  if (order.supplier && order.supplier.name) labels.push(`供应商：${order.supplier.name}`)
  if (order.created_at) labels.push(`创建时间：${formatTime(order.created_at)}`)
  if (order.warehouse && order.warehouse.name) labels.push(`目标仓库：${order.warehouse.name}`)
  if (order.creator && order.creator.real_name) labels.push(`创建人：${order.creator.real_name}`)
  return labels.join(' | ')
}

/**
 * 简易时间格式化
 * @param {String} time - 时间字符串
 */
const formatTime = (time) => {
  if (!time) return ''
  return new Date(time).toLocaleDateString()
}

// 页面初始化：先加载供应商，再加载订单列表
onMounted(async () => {
  console.log('组件挂载，开始初始化...')
  await loadSuppliers()
  await loadOrderList(true)
  console.log('初始化完成')
})
</script>

<style scoped lang="scss">
.purchase-order-index {
  min-height: 100vh;
  background-color: #f5f5f5;
  padding-top: 46px; // 适配fixed导航栏

  // 筛选区域样式
  .filter-wrapper {
    background-color: #fff;
    margin-bottom: 10px;

    .search-filter {
      padding: 0 10px 10px;

      :deep(.van-dropdown-menu) {
        margin-top: 10px;
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