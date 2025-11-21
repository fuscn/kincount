<!-- kincount\kincount-front\src\views\stock\Transfer.vue -->
<template>
  <div class="transfer-page">
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
          @click="handleCreateTransfer"
          v-perm="PERM.STOCK_TRANSFER"
        >
          新建调拨
        </van-button>
      </template>
    </van-nav-bar>

    <div class="page-content">
      <!-- 搜索栏 -->
      <van-search
        v-model="searchKeyword"
        placeholder="搜索调拨单号"
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

      <!-- 调拨单列表 -->
      <van-pull-refresh v-model="refreshing" @refresh="loadTransferList(true)">
        <van-list
          v-model:loading="listLoading"
          :finished="finished"
          :finished-text="transferList.length === 0 ? '暂无调拨数据' : '没有更多了'"
          @load="loadTransferList"
        >
          <van-cell-group>
            <van-cell
              v-for="transfer in transferList"
              :key="transfer.id"
              :title="`调拨单号: ${transfer.transfer_no || transfer.order_no}`"
              :label="getTransferLabel(transfer)"
              @click="handleViewTransfer(transfer)"
            >
              <template #value>
                <van-tag :type="getStatusTagType(transfer.status)">
                  {{ getStatusText(transfer.status) }}
                </van-tag>
              </template>
              <template #extra>
                <div class="cell-actions">
                  <van-button 
                    size="mini" 
                    type="primary" 
                    plain
                    @click.stop="handleEditTransfer(transfer)"
                    v-perm="PERM.STOCK_TRANSFER"
                  >
                    编辑
                  </van-button>
                </div>
              </template>
            </van-cell>
          </van-cell-group>

          <!-- 空状态 -->
          <van-empty
            v-if="!listLoading && !refreshing && transferList.length === 0"
            description="暂无调拨单数据"
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
const navTitle = ref('库存调拨')
const searchKeyword = ref('')
const activeStatus = ref('')
const transferList = ref([])
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
  { title: '待审核', value: '1' },
  { title: '已审核', value: '2' },
  { title: '调拨中', value: '3' },
  { title: '已完成', value: '4' },
  { title: '已取消', value: '5' }
])

// 获取状态文本
const getStatusText = (status) => {
  const statusMap = {
    1: '待审核',
    2: '已审核',
    3: '调拨中',
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

// 获取调拨单标签信息
const getTransferLabel = (transfer) => {
  const parts = []
  if (transfer.from_warehouse_name && transfer.to_warehouse_name) {
    parts.push(`${transfer.from_warehouse_name} → ${transfer.to_warehouse_name}`)
  }
  if (transfer.created_at) {
    parts.push(`创建: ${transfer.created_at}`)
  }
  if (transfer.total_items) {
    parts.push(`明细: ${transfer.total_items}项`)
  }
  return parts.join(' | ')
}

// 加载调拨列表
const loadTransferList = async (isRefresh = false) => {
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

    await stockStore.loadTransferList(params)
    
    let listData = []
    let totalCount = 0

    if (stockStore.transferList && Array.isArray(stockStore.transferList)) {
      listData = stockStore.transferList
      totalCount = stockStore.transferTotal || 0
    }

    if (isRefresh) {
      transferList.value = listData
    } else {
      transferList.value = [...transferList.value, ...listData]
    }
    
    pagination.total = totalCount

    // 检查是否加载完成
    if (transferList.value.length >= pagination.total) {
      finished.value = true
    }

  } catch (error) {
    console.error('加载调拨列表失败:', error)
    showToast('加载调拨列表失败')
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

const handleCreateTransfer = () => {
  router.push('/stock/transfer/create')
}

const handleSearch = () => {
  loadTransferList(true)
}

const handleClearSearch = () => {
  searchKeyword.value = ''
  loadTransferList(true)
}

const handleStatusChange = () => {
  loadTransferList(true)
}

const handleViewTransfer = (transfer) => {
  router.push(`/stock/transfer/detail/${transfer.id}`)
}

const handleEditTransfer = (transfer) => {
  router.push(`/stock/transfer/edit/${transfer.id}`)
}

onMounted(() => {
  loadTransferList(true)
})
</script>

<style scoped lang="scss">
.transfer-page {
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