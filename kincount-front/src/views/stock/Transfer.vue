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
      <van-list
        v-model:loading="listLoading"
        :finished="finished"
        :finished-text="transferList.length === 0 ? '暂无调拨数据' : '没有更多了'"
        @load="onLoad"
      >
          <van-cell-group>
            <van-cell
              v-for="transfer in transferList"
              :key="transfer.id"
              @click="handleViewTransfer(transfer)"
            >
              <template #title>
                <div class="transfer-number">
                  <span class="label">调拨单号:</span>
                  <span class="value">{{ transfer.transfer_no || transfer.order_no }}</span>
                </div>
              </template>
              <template #label>
                {{ getTransferLabel(transfer) }}
              </template>
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
                    v-if="transfer.status === 0"
                  >
                    编辑
                  </van-button>
                </div>
              </template>
            </van-cell>
          </van-cell-group>

          <!-- 空状态 -->
          <van-empty
            v-if="!listLoading && transferList.length === 0 && !initialLoading"
            description="暂无调拨单数据"
            image="search"
          />
        </van-list>
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
const listLoading = ref(false)
const initialLoading = ref(true)
const finished = ref(false)
const isFirstLoad = ref(true)

const pagination = reactive({
  page: 1,
  pageSize: 15,
  total: 0
})

const statusTabs = ref([
  { title: '全部', value: '' },
  { title: '待调拨', value: '0' },
  { title: '调拨中', value: '1' },
  { title: '已完成', value: '2' },
  { title: '已取消', value: '3' }
])

// 获取状态文本
const getStatusText = (status) => {
  const statusMap = {
    0: '待调拨',
    1: '调拨中',
    2: '已完成',
    3: '已取消'
  }
  return statusMap[status] || '未知状态'
}

// 获取状态标签类型
const getStatusTagType = (status) => {
  const typeMap = {
    0: 'warning',
    1: 'primary',
    2: 'success',
    3: 'danger'
  }
  return typeMap[status] || 'default'
}

// 获取调拨单标签信息
const getTransferLabel = (transfer) => {
  const parts = []
  if (transfer.fromWarehouse?.name && transfer.toWarehouse?.name) {
    parts.push(`${transfer.fromWarehouse.name} → ${transfer.toWarehouse.name}`)
  }
  if (transfer.created_at) {
    parts.push(`创建: ${transfer.created_at}`)
  }
  if (transfer.auditor?.real_name) {
    parts.push(`审核: ${transfer.auditor.real_name}`)
  }
  if (transfer.total_amount) {
    parts.push(`金额: ¥${transfer.total_amount}`)
  }
  return parts.join(' | ')
}

// 加载调拨列表
const loadTransferList = async (params) => {
  try {
    await stockStore.loadTransferList(params)
    
    const listData = stockStore.transferList || []
    const totalCount = stockStore.transferTotal || 0

    return { listData, totalCount }
  } catch (error) {
    console.error('加载调拨列表失败:', error)
    showToast('加载调拨列表失败')
    throw error
  }
}



// 列表加载
const onLoad = async () => {
  // 防止重复调用
  if (listLoading.value) {
    return
  }
  
  try {
    listLoading.value = true
    
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

    const { listData, totalCount } = await loadTransferList(params)
    
    if (pagination.page === 1) {
      transferList.value = listData
    } else {
      transferList.value = [...transferList.value, ...listData]
    }
    
    pagination.total = totalCount
    pagination.page++

    // 检查是否加载完成
    if (transferList.value.length >= pagination.total) {
      finished.value = true
    }

  } catch (error) {
    console.error('加载调拨列表失败:', error)
    showToast('加载调拨列表失败')
    finished.value = true
  } finally {
    listLoading.value = false
    initialLoading.value = false
    isFirstLoad.value = false
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
  pagination.page = 1
  finished.value = false
  transferList.value = []
  onLoad()
}

const handleClearSearch = () => {
  searchKeyword.value = ''
  pagination.page = 1
  finished.value = false
  transferList.value = []
  onLoad()
}

const handleStatusChange = () => {
  pagination.page = 1
  finished.value = false
  transferList.value = []
  onLoad()
}

const handleViewTransfer = (transfer) => {
  router.push(`/stock/transfer/detail/${transfer.id}`)
}

const handleEditTransfer = (transfer) => {
  router.push(`/stock/transfer/edit/${transfer.id}`)
}

onMounted(() => {
  // 组件挂载时手动触发首次加载
  onLoad()
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

.transfer-number {
  display: flex;
  align-items: center;
  white-space: nowrap;
}

.transfer-number .label {
  margin-right: 4px;
  color: #323233;
  font-weight: 500;
}

.transfer-number .value {
  flex-shrink: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  color: #646566;
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