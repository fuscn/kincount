<!-- kincount\kincount-front\src\views\stock\Take.vue -->
<template>
  <div class="take-page">
    <van-nav-bar 
      :title="navTitle"
      left-text="返回"
      left-arrow
      @click-left="handleBack"
    >
      <template #right>
        <van-button 
          size="small" 
          type="primary" 
          @click="handleCreateTake"
          v-perm="PERM.STOCK_TAKE"
        >
          新建盘点
        </van-button>
      </template>
    </van-nav-bar>

    <div class="page-content">
      <!-- 搜索栏 -->
      <van-search
        v-model="searchKeyword"
        placeholder="搜索盘点单号"
        show-action
        @search="handleSearch"
        @clear="handleClearSearch"
      >
        <template #action>
          <div @click="handleSearch">搜索</div>
        </template>
      </van-search>

      <!-- 状态筛选 -->
      <van-tabs v-model="activeStatus" @change="handleStatusChange">
        <van-tab 
          v-for="tab in statusTabs" 
          :key="tab.value" 
          :name="tab.value" 
          :title="tab.title" 
        />
      </van-tabs>

      <!-- 盘点单列表 -->
      <van-pull-refresh v-model="refreshing" @refresh="loadTakeList(true)">
        <van-list
          v-model:loading="listLoading"
          :finished="finished"
          :finished-text="takeList.length === 0 ? '暂无盘点数据' : '没有更多了'"
          @load="loadTakeList"
        >
          <van-cell-group>
            <van-cell
              v-for="take in takeList"
              :key="take.id"
              :title="`盘点单号: ${take.take_no || take.order_no}`"
              :label="getTakeLabel(take)"
              @click="handleViewTake(take)"
            >
              <template #value>
                <van-tag :type="getStatusTagType(take.status)">
                  {{ getStatusText(take.status) }}
                </van-tag>
              </template>
              <template #extra>
                <div class="cell-actions">
                  <van-button 
                    size="mini" 
                    type="primary" 
                    plain
                    @click.stop="handleEditTake(take)"
                    v-perm="PERM.STOCK_TAKE"
                  >
                    编辑
                  </van-button>
                </div>
              </template>
            </van-cell>
          </van-cell-group>

          <!-- 空状态 -->
          <van-empty
            v-if="!listLoading && !refreshing && takeList.length === 0"
            description="暂无盘点单数据"
            image="search"
          />
        </van-list>
      </van-pull-refresh>
    </div>

    <!-- 加载状态 -->
    <van-loading v-if="initialLoading" class="page-loading" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { 
  showToast,
  showConfirmDialog
} from 'vant'
import { PERM } from '@/constants/permissions'
import { useStockStore } from '@/store/modules/stock'

const router = useRouter()
const stockStore = useStockStore()

// 响应式数据
const navTitle = ref('库存盘点')
const searchKeyword = ref('')
const activeStatus = ref('')
const takeList = ref([])
const refreshing = ref(false)
const listLoading = ref(false)
const initialLoading = ref(true)
const finished = ref(false)

const pagination = reactive({
  page: 1,
  pageSize: 15,
  total: 0
})

const statusTabs = ref([
  { title: '全部', value: '' },
  { title: '待盘点', value: '1' },
  { title: '盘点中', value: '2' },
  { title: '已完成', value: '3' },
  { title: '已取消', value: '4' }
])

// 获取状态文本
const getStatusText = (status) => {
  const statusMap = {
    1: '待盘点',
    2: '盘点中', 
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

// 获取盘点单标签信息
const getTakeLabel = (take) => {
  const parts = []
  if (take.warehouse_name) {
    parts.push(`仓库: ${take.warehouse_name}`)
  }
  if (take.created_at) {
    parts.push(`创建: ${take.created_at}`)
  }
  if (take.total_items) {
    parts.push(`明细: ${take.total_items}项`)
  }
  return parts.join(' | ')
}

// 加载盘点列表
const loadTakeList = async (isRefresh = false) => {
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
      keyword: searchKeyword.value,
      status: activeStatus.value
    }

    // 移除空值参数
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] == null) delete params[key]
    })

    await stockStore.loadTakeList(params)
    
    let listData = []
    let totalCount = 0

    if (stockStore.takeList && Array.isArray(stockStore.takeList)) {
      listData = stockStore.takeList
      totalCount = stockStore.takeTotal || 0
    }

    if (isRefresh) {
      takeList.value = listData
    } else {
      takeList.value = [...takeList.value, ...listData]
    }
    
    pagination.total = totalCount

    // 检查是否加载完成
    if (takeList.value.length >= pagination.total) {
      finished.value = true
    }

  } catch (error) {
    console.error('加载盘点列表失败:', error)
    showToast('加载盘点列表失败')
    finished.value = true
  } finally {
    refreshing.value = false
    listLoading.value = false
    initialLoading.value = false
  }
}

// 事件处理
const handleBack = () => {
  router.back()
}

const handleCreateTake = () => {
  router.push('/stock/take/create')
}

const handleSearch = () => {
  loadTakeList(true)
}

const handleClearSearch = () => {
  searchKeyword.value = ''
  loadTakeList(true)
}

const handleStatusChange = () => {
  loadTakeList(true)
}

const handleViewTake = (take) => {
  router.push(`/stock/take/detail/${take.id}`)
}

const handleEditTake = (take) => {
  router.push(`/stock/take/edit/${take.id}`)
}

onMounted(() => {
  loadTakeList(true)
})
</script>

<style scoped lang="scss">
.take-page {
  background: #f7f8fa;
  min-height: 100vh;
}

.page-content {
  padding: 16px;
}

.cell-actions {
  display: flex;
  gap: 8px;
}

.page-loading {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 200px;
}

.van-cell {
  padding: 12px 16px;
  
  :deep(.van-cell__value) {
    display: flex;
    align-items: center;
    justify-content: flex-end;
  }
}
</style>