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

    <!-- 退货单列表：修复immediate-check属性，确保初始加载 -->
    <van-pull-refresh v-model="refreshing" @refresh="loadReturnList(true)">
      <van-list
        v-model:loading="listLoading"
        :finished="finished"
        :immediate-check="true"
        :finished-text="returnList.length === 0 ? '暂无退货记录' : '没有更多了'"
        @load="loadReturnList"
      >
        <div class="return-list">
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
                <span class="value">{{ returnOrder.order_no || '--' }}</span>
              </div>
              <div class="info-row">
                <span class="label">客户：</span>
                <span class="value">{{ returnOrder.customer_name || '--' }}</span>
              </div>
              <div class="info-row">
                <span class="label">退货类型：</span>
                <span class="value">{{ getReturnTypeText(returnOrder.return_type) }}</span>
              </div>
              <div class="info-row">
                <span class="label">退货日期：</span>
                <span class="value">{{ returnOrder.return_date || formatDate(returnOrder.created_at) }}</span>
              </div>
              <div class="info-row">
                <span class="label">退货金额：</span>
                <span class="value amount">-¥{{ formatPrice(returnOrder.total_amount) }}</span>
              </div>
              <div class="info-row">
                <span class="label">仓库：</span>
                <span class="value">{{ returnOrder.warehouse_name || '--' }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- 空状态：优化条件，避免加载中时显示空状态 -->
        <van-empty
          v-if="!listLoading && !refreshing && returnList.length === 0"
          description="暂无退货记录"
          image="search"
        />
      </van-list>
    </van-pull-refresh>

    <!-- 初始加载状态：确保只在首次加载时显示 -->
    <van-loading v-if="initialLoading && returnList.length === 0" class="page-loading" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
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
const initialLoading = ref(true)  // 首次加载标记
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

// 加载退货列表：优化逻辑，确保请求触发且状态正确
const loadReturnList = async (isRefresh = false) => {
  // 防止重复请求
  if ((isRefresh && refreshing.value) || (!isRefresh && listLoading.value)) {
    return
  }

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

    // 移除空值参数（避免后端接口报错）
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] == null) delete params[key]
    })

    console.log('请求参数：', params)  // 调试日志，查看是否正确传参
    // 调用store接口获取数据（确保store中的方法正确发送请求）
    const response = await saleStore.loadReturnList(params)
    
    // 假设store返回的数据格式符合后端规范：{ code: 200, msg: '', data: { list: [], total: 0 } }
    const { data } = response || {}
    const listData = data?.list || []
    const totalCount = data?.total || 0

    if (isRefresh) {
      returnList.value = listData  // 下拉刷新：覆盖数据
    } else {
      // 上拉加载：去重并追加数据
      const existingIds = new Set(returnList.value.map(item => item.id))
      const newItems = listData.filter(item => !existingIds.has(item.id))
      returnList.value = [...returnList.value, ...newItems]
    }
    
    pagination.total = totalCount

    // 检查是否加载完成（当前页数据不足一页，说明没有更多）
    if (listData.length < pagination.pageSize) {
      finished.value = true
    }

  } catch (error) {
    console.error('加载退货记录失败:', error)
    showFailToast('加载退货记录失败，请重试')
    finished.value = false  // 加载失败时允许重新加载
  } finally {
    // 重置所有加载状态
    refreshing.value = false
    listLoading.value = false
    initialLoading.value = false  // 首次加载完成，隐藏初始加载动画
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
  loadReturnList(true)  // 清空搜索后重新加载
}

// 可选：手动触发初始加载（双重保障，避免van-list自动加载失效）
onMounted(() => {
  // 延迟100ms，确保van-list初始化完成后再触发
  setTimeout(() => {
    if (returnList.value.length === 0 && initialLoading.value) {
      loadReturnList(true)
    }
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
      color: #f53f3f;  // 调整为红色，更符合退款金额的视觉习惯
      font-weight: 500;
    }
  }
}

.page-loading {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 200px;
}

/* 优化下拉刷新和上拉加载的样式兼容性 */
:deep(.van-pull-refresh) {
  background-color: #f7f8fa;
}

:deep(.van-list__loading) {
  padding: 15px 0;
}
</style>