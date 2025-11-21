<template>
  <div class="sale-stock-page">
    <van-nav-bar 
      title="销售出库"
      fixed
      placeholder
    >
      <template #right>
        <van-button 
          size="small" 
          type="primary" 
          @click="handleCreateOutbound"
          v-perm="PERM.SALE_ADD"
        >
          新建出库
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 搜索和筛选 -->
    <div class="filter-section">
      <van-search
        v-model="filters.keyword"
        placeholder="搜索出库单号/客户名称"
        show-action
        @search="handleSearch"
        @clear="handleClearSearch"
      >
        <template #action>
          <div @click="handleSearch">搜索</div>
        </template>
      </van-search>
      
      <van-dropdown-menu>
        <van-dropdown-item 
          v-model="filters.status" 
          :options="statusOptions" 
          @change="handleFilterChange"
        />
      </van-dropdown-menu>
    </div>

    <!-- 出库单列表 -->
    <van-pull-refresh v-model="refreshing" @refresh="handleRefresh">
      <van-list
        v-model:loading="listLoading"
        :finished="finished"
        :immediate-check="false"
        :finished-text="list.length === 0 ? '暂无出库记录' : '没有更多了'"
        @load="loadList"
      >
        <van-cell-group>
          <van-cell
            v-for="item in list"
            :key="item.id"
            :title="`出库单号: ${item.stock_no}`"
            :label="getItemLabel(item)"
            @click="handleViewDetail(item)"
          >
            <template #value>
              <div class="item-amount">¥{{ item.total_amount }}</div>
            </template>
            <template #extra>
              <van-tag :type="getStatusTagType(item.status)">
                {{ getStatusText(item.status) }}
              </van-tag>
            </template>
          </van-cell>
        </van-cell-group>

        <!-- 空状态 -->
        <van-empty
          v-if="!listLoading && !refreshing && list.length === 0"
          description="暂无出库记录"
          image="search"
        />
      </van-list>
    </van-pull-refresh>

    <!-- 加载状态 -->
    <van-loading v-if="initialLoading" class="page-loading" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import { PERM } from '@/constants/permissions'
import { useSaleStore } from '@/store/modules/sale'

const router = useRouter()
const saleStore = useSaleStore()

// 响应式数据
const filters = reactive({
  keyword: '',
  status: ''
})

const list = ref([])
const refreshing = ref(false)
const listLoading = ref(false)
const initialLoading = ref(true)
const finished = ref(false)

// 分页参数
const pagination = reactive({
  page: 1,
  pageSize: 15,
  total: 0
})

const statusOptions = ref([
  { text: '全部状态', value: '' },
  { text: '待审核', value: '1' },
  { text: '已审核', value: '2' },
  { text: '已完成', value: '3' },
  { text: '已取消', value: '4' }
])

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

// 获取列表项标签信息
const getItemLabel = (item) => {
  const parts = []
  if (item.customer?.name) {
    parts.push(`客户: ${item.customer.name}`)
  }
  if (item.warehouse?.name) {
    parts.push(`仓库: ${item.warehouse.name}`)
  }
  if (item.created_at) {
    parts.push(`创建: ${formatDate(item.created_at)}`)
  }
  return parts.join(' | ')
}

// 格式化日期
const formatDate = (dateString) => {
  if (!dateString) return ''
  return new Date(dateString).toLocaleDateString()
}

// 加载列表 - 修复重复加载问题
const loadList = async (isRefresh = false) => {
  console.log('📥 加载列表，模式:', isRefresh ? '刷新' : '加载更多')
  
  if (isRefresh) {
    // 刷新模式：重置分页
    pagination.page = 1
    finished.value = false
    refreshing.value = true
  } else {
    // 加载更多模式
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

    console.log('🔍 请求参数:', params)
    
    // 调用 store 加载数据
    await saleStore.loadStockList(params)
    
    console.log('📦 Store 数据:', saleStore.stockList)
    
    let listData = []
    let totalCount = 0

    // 处理不同的数据结构
    if (saleStore.stockList && Array.isArray(saleStore.stockList)) {
      listData = saleStore.stockList
      totalCount = saleStore.stockTotal || 0
    } else {
      console.warn('⚠️ Store 返回的数据格式异常:', saleStore.stockList)
      listData = []
      totalCount = 0
    }

    console.log('📊 处理后的数据:', { listData, totalCount })

    if (isRefresh) {
      // 刷新：替换整个列表
      list.value = listData
    } else {
      // 加载更多：追加到现有列表
      // 去重：确保不添加重复数据
      const existingIds = new Set(list.value.map(item => item.id))
      const newItems = listData.filter(item => !existingIds.has(item.id))
      list.value = [...list.value, ...newItems]
    }
    
    pagination.total = totalCount

    // 检查是否加载完成
    if (list.value.length >= pagination.total) {
      finished.value = true
      console.log('✅ 列表加载完成')
    } else {
      // 还有更多数据，增加页码
      pagination.page++
      console.log('🔄 还有更多数据，下一页:', pagination.page)
    }

  } catch (error) {
    console.error('❌ 加载销售出库列表失败:', error)
    showToast('加载销售出库列表失败')
    finished.value = true
  } finally {
    refreshing.value = false
    listLoading.value = false
    initialLoading.value = false
  }
}

// 搜索
const handleSearch = () => {
  console.log('🔍 执行搜索')
  loadList(true)
}

// 清空搜索
const handleClearSearch = () => {
  filters.keyword = ''
  loadList(true)
}

// 筛选变更
const handleFilterChange = () => {
  loadList(true)
}

// 下拉刷新
const handleRefresh = () => {
  loadList(true)
}

// 查看详情
const handleViewDetail = (item) => {
  console.log('👀 查看详情:', item)
  router.push(`/sale/stock/detail/${item.id}`)
}

// 新建出库
const handleCreateOutbound = () => {
  router.push('/sale/stock/create')
}

onMounted(() => {
  console.log('🚀 页面挂载，开始加载数据')
  // 初始加载
  loadList(true)
})
</script>

<style scoped lang="scss">
.sale-stock-page {
  background: #f7f8fa;
  min-height: 100vh;
}

.filter-section {
  background: white;
  margin-bottom: 12px;
  border-radius: 8px;
  overflow: hidden;
}

.item-amount {
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