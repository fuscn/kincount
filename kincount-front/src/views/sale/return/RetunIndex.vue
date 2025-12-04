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
        <van-dropdown-item 
          v-model="filters.return_type" 
          :options="returnTypeOptions" 
          @change="handleFilterChange"
        />
      </van-dropdown-menu>
    </div>

    <!-- 优化后的列表加载逻辑 -->
    <van-pull-refresh 
      v-model="refreshing" 
      @refresh="onRefresh"
      :disabled="initialLoading"
    >
      <van-list
        v-model:loading="listLoading"
        :finished="finished"
        finished-text="没有更多了"
        @load="onLoad"
        :immediate-check="false"
      >
        <!-- 列表内容 -->
        <div class="return-list" v-if="!initialLoading">
          <div 
            v-for="returnOrder in returnList" 
            :key="returnOrder.id" 
            class="return-card" 
            @click="handleViewReturn(returnOrder)"
          >
            <div class="return-header">
              <div class="return-no">{{ returnOrder.return_no }}</div>
              <van-tag :type="getStatusTagType(returnOrder.status)" size="medium">
                {{ getStatusText(returnOrder.status) }}
              </van-tag>
            </div>

            <div class="return-info">
              <div class="info-row">
                <span class="label">关联订单：</span>
                <span class="value">{{ returnOrder.source_order_id || '--' }}</span>
              </div>
              <div class="info-row">
                <span class="label">客户：</span>
                <span class="value">{{ returnOrder.target?.name || '--' }}</span>
              </div>
              <div class="info-row">
                <span class="label">退货类型：</span>
                <span class="value">{{ getReturnTypeText(returnOrder.return_type) }}</span>
              </div>
              <div class="info-row">
                <span class="label">退货日期：</span>
                <span class="value">{{ formatDate(returnOrder.created_at) }}</span>
              </div>
              <div class="info-row">
                <span class="label">退货金额：</span>
                <span class="value amount">-¥{{ formatPrice(returnOrder.total_amount) }}</span>
              </div>
              <div class="info-row">
                <span class="label">退货原因：</span>
                <span class="value">{{ returnOrder.return_reason || '--' }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- 空状态显示 -->
        <van-empty
          v-if="!initialLoading && returnList.length === 0"
          :description="emptyDescription"
          image="search"
        />
      </van-list>
    </van-pull-refresh>

    <!-- 初始加载 -->
    <van-loading v-if="initialLoading" class="page-loading" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { 
  showToast,
  showFailToast
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

// 根据API响应调整退货类型选项 - 现在是数字类型
const returnTypeOptions = ref([
  { text: '全部类型', value: '' },
  { text: '质量问题', value: '1' },
  { text: '客户原因', value: '2' },
  { text: '发错货', value: '3' },
  { text: '其他', value: '4' }
])

const returnList = ref([])
const refreshing = ref(false)
const listLoading = ref(false)
const initialLoading = ref(true)
const finished = ref(false)

// 计算属性
const emptyDescription = computed(() => {
  if (filters.keyword || filters.status || filters.return_type) {
    return '未找到相关退货记录'
  }
  return '暂无退货记录'
})

// 格式化函数
const formatPrice = (price) => {
  if (price === null || price === undefined || price === '') return '0.00'
  const num = Number(price)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

const formatDate = (date) => {
  if (!date) return '--'
  try {
    const d = new Date(date)
    if (isNaN(d.getTime())) return '--'
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    const hour = String(d.getHours()).padStart(2, '0')
    const minute = String(d.getMinutes()).padStart(2, '0')
    return `${year}-${month}-${day} ${hour}:${minute}`
  } catch (error) {
    return '--'
  }
}

// 状态处理函数 - 根据API响应调整
const getStatusText = (status) => {
  const statusMap = {
    1: '待审核',
    2: '已审核',
    3: '已完成',
    4: '已取消'
  }
  return statusMap[status] || '未知状态'
}

const getStatusTagType = (status) => {
  const typeMap = {
    1: 'warning',
    2: 'primary',
    3: 'success',
    4: 'danger'
  }
  return typeMap[status] || 'default'
}

// 退货类型处理函数 - 根据API响应调整为数字映射
const getReturnTypeText = (type) => {
  // 根据您的API响应，return_type是数字
  const typeMap = {
    1: '质量问题',
    2: '客户原因',
    3: '发错货',
    4: '其他'
  }
  return typeMap[type] || '未知类型'
}

// 数据加载函数 - 修复数据结构问题
const fetchReturnList = async (isRefresh = false) => {
  try {
    const params = {
      page: isRefresh ? 1 : pagination.page,
      limit: pagination.pageSize,
      ...filters
    }

    // 清理空参数
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] == null) delete params[key]
    })

    console.log('请求参数:', params) // 调试
    
    // 调用store接口获取数据
    const response = await saleStore.loadReturnList(params)
    console.log('API响应:', response) // 调试
    
    // 根据日志，response 直接是 {list: Array(4), total: 4}，而不是 {code: 200, data: {...}}
    // 所以直接使用 response 而不是 response.data
    const listData = response?.list || []
    const totalCount = response?.total || 0

    console.log('列表数据:', listData) // 调试
    
    if (listData.length > 0) {
      console.log('第一条数据详情:', listData[0])
      console.log('客户名称:', listData[0].target?.name)
      console.log('退货类型:', listData[0].return_type)
      console.log('状态:', listData[0].status)
    }

    if (isRefresh) {
      returnList.value = listData
    } else {
      // 去重合并
      const existingIds = new Set(returnList.value.map(item => item.id))
      const newItems = listData.filter(item => !existingIds.has(item.id))
      returnList.value = [...returnList.value, ...newItems]
    }

    // 更新分页信息
    if (isRefresh) {
      pagination.page = 1
    } else {
      pagination.page += 1
    }
    
    pagination.total = totalCount

    // 判断是否已加载完所有数据
    if (listData.length < pagination.pageSize || returnList.value.length >= totalCount) {
      finished.value = true
    } else {
      finished.value = false
    }

    return true
  } catch (error) {
    console.error('加载退货记录失败:', error)
    showFailToast('加载退货记录失败，请重试')
    return false
  }
}

// 事件处理函数
const onRefresh = async () => {
  refreshing.value = true
  finished.value = false
  await fetchReturnList(true)
  refreshing.value = false
}

const onLoad = async () => {
  if (finished.value || listLoading.value) return
  
  listLoading.value = true
  const success = await fetchReturnList()
  listLoading.value = false
  
  if (!success) {
    finished.value = false
  }
}

const handleSearch = () => {
  initialLoading.value = true
  finished.value = false
  pagination.page = 1
  returnList.value = []
  
  setTimeout(async () => {
    await fetchReturnList(true)
    initialLoading.value = false
  }, 100)
}

const handleFilterChange = () => {
  handleSearch()
}

const handleClearSearch = () => {
  filters.keyword = ''
  handleSearch()
}

const handleCreateReturn = () => {
  router.push('/sale/return/create')
}

const handleViewReturn = (returnOrder) => {
  router.push(`/sale/return/detail/${returnOrder.id}`)
}

// 生命周期
onMounted(() => {
  // 初始加载
  setTimeout(async () => {
    await fetchReturnList(true)
    initialLoading.value = false
  }, 100)
})
</script>

<style scoped lang="scss">
.sale-return-page {
  background: #f7f8fa;
  min-height: 100vh;
  padding-top: 46px;
}

.filter-section {
  background: white;
  margin-bottom: 12px;
  border-radius: 8px;
  overflow: hidden;
}

.return-list {
  padding: 12px;
}

.return-card {
  background: white;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  cursor: pointer;
  transition: transform 0.2s ease;
  
  &:active {
    transform: scale(0.98);
  }
}

.return-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  padding-bottom: 8px;
  border-bottom: 1px solid #f5f5f5;
  
  .return-no {
    font-weight: 600;
    color: #323233;
    font-size: 15px;
  }
}

.return-info {
  .info-row {
    display: flex;
    margin-bottom: 6px;
    font-size: 13px;
    
    .label {
      color: #909399;
      width: 80px;
      flex-shrink: 0;
    }
    
    .value {
      color: #323233;
      flex: 1;
      word-break: break-all;
    }
    
    .amount {
      color: #f53f3f;
      font-weight: 500;
    }
  }
}

.page-loading {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}

/* 优化样式 */
:deep(.van-pull-refresh) {
  min-height: 100px;
}

:deep(.van-empty) {
  padding-top: 100px;
}
</style>