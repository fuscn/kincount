<template>
  <div class="sale-return-page">
    <van-nav-bar 
      title="销售退货"
      fixed
      placeholder
    >
      <template #right>
        <van-button 
          size="small" 
          type="primary" 
          @click="handleCreateReturn"
        >
          新建退货
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 搜索和筛选 -->
    <div class="filter-section">
      <van-search
        v-model="filters.keyword"
        placeholder="搜索退货单号/订单号"
        show-action
        @search="loadReturnList(true)"
        @clear="handleClearSearch"
      >
        <template #action>
          <div @click="loadReturnList(true)">搜索</div>
        </template>
      </van-search>
      
      <van-dropdown-menu>
        <van-dropdown-item 
          v-model="filters.status" 
          :options="statusOptions" 
          @change="loadReturnList(true)"
        />
        <van-dropdown-item 
          v-model="filters.return_type" 
          :options="returnTypeOptions" 
          @change="loadReturnList(true)"
        />
      </van-dropdown-menu>
    </div>

    <!-- 退货单列表 -->
    <van-pull-refresh v-model="refreshing" @refresh="loadReturnList(true)">
      <van-list
        v-model:loading="listLoading"
        :finished="finished"
        :finished-text="returnList.length === 0 ? '暂无退货记录' : '没有更多了'"
        @load="loadReturnList"
      >
        <van-cell-group>
          <van-cell
            v-for="returnOrder in returnList"
            :key="returnOrder.id"
            :title="`退货单号: ${returnOrder.return_no}`"
            :label="getReturnLabel(returnOrder)"
            @click="handleViewReturn(returnOrder)"
          >
            <template #value>
              <div class="return-amount">-¥{{ returnOrder.total_amount }}</div>
            </template>
            <template #extra>
              <van-tag :type="getStatusTagType(returnOrder.status)">
                {{ getStatusText(returnOrder.status) }}
              </van-tag>
            </template>
          </van-cell>
        </van-cell-group>

        <!-- 空状态 -->
        <van-empty
          v-if="!listLoading && !refreshing && returnList.length === 0"
          description="暂无退货记录"
          image="search"
        />
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
import { useSaleStore } from '@/store/modules/sale'

const router = useRouter()
const saleStore = useSaleStore()

// 响应式数据
const filters = reactive({
  keyword: '',
  status: '',
  return_type: ''
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
  { text: '已完成', value: '3' },
  { text: '已取消', value: '4' }
])

const returnTypeOptions = ref([
  { text: '全部类型', value: '' },
  { text: '质量问题', value: 'quality' },
  { text: '客户原因', value: 'customer' },
  { text: '发错货', value: 'wrong_delivery' },
  { text: '其他', value: 'other' }
])

const returnList = ref([])
const refreshing = ref(false)
const listLoading = ref(false)
const initialLoading = ref(true)
const finished = ref(false)

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

// 获取退货单标签信息
const getReturnLabel = (returnOrder) => {
  const parts = []
  if (returnOrder.order_no) {
    parts.push(`订单: ${returnOrder.order_no}`)
  }
  if (returnOrder.customer_name) {
    parts.push(`客户: ${returnOrder.customer_name}`)
  }
  if (returnOrder.created_at) {
    parts.push(`创建: ${returnOrder.created_at}`)
  }
  if (returnOrder.return_type) {
    parts.push(`类型: ${getReturnTypeText(returnOrder.return_type)}`)
  }
  return parts.join(' | ')
}

// 加载退货列表
const loadReturnList = async (isRefresh = false) => {
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

    await saleStore.loadReturnList(params)
    
    let listData = []
    let totalCount = 0

    if (saleStore.returnList && Array.isArray(saleStore.returnList)) {
      listData = saleStore.returnList
      totalCount = saleStore.returnTotal || 0
    }

    if (isRefresh) {
      returnList.value = listData
    } else {
      returnList.value = [...returnList.value, ...listData]
    }
    
    pagination.total = totalCount

    // 检查是否加载完成
    if (returnList.value.length >= pagination.total) {
      finished.value = true
    }

  } catch (error) {
    console.error('加载退货记录失败:', error)
    showToast('加载退货记录失败')
    finished.value = true
  } finally {
    refreshing.value = false
    listLoading.value = false
    initialLoading.value = false
  }
}

// 事件处理
const handleCreateReturn = () => {
  router.push('/sale/return/create')
}

const handleViewReturn = (returnOrder) => {
  router.push(`/sale/return/detail/${returnOrder.id}`)
}

const handleClearSearch = () => {
  filters.keyword = ''
  loadReturnList(true)
}

onMounted(() => {
  loadReturnList(true)
})
</script>

<style scoped lang="scss">
.sale-return-page {
  background: #f7f8fa;
  min-height: 100vh;
}

.filter-section {
  background: white;
  margin-bottom: 12px;
  border-radius: 8px;
  overflow: hidden;
}

.return-amount {
  font-weight: bold;
  color: #07c160;
  font-size: 14px;
}

.page-loading {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 200px;
}
</style>