<template>
  <div class="stock-warning-page">
    <van-nav-bar 
      title="库存预警" 
      fixed 
      placeholder
      left-text="返回"
      left-arrow
      @click-left="$router.back()"
    />

    <!-- 预警统计 -->
    <van-grid :column-num="3" :border="false" class="warning-stats">
      <van-grid-item>
        <div class="stat-item">
          <div class="stat-value warning">{{ warningData.total || 0 }}</div>
          <div class="stat-label">预警总数</div>
        </div>
      </van-grid-item>
      <van-grid-item>
        <div class="stat-item">
          <div class="stat-value danger">{{ warningData.out_of_stock || 0 }}</div>
          <div class="stat-label">缺货商品</div>
        </div>
      </van-grid-item>
      <van-grid-item>
        <div class="stat-item">
          <div class="stat-value primary">{{ warningData.excess || 0 }}</div>
          <div class="stat-label">积压商品</div>
        </div>
      </van-grid-item>
    </van-grid>

    <!-- 预警列表 -->
    <van-pull-refresh v-model="refreshing" @refresh="loadWarningList(true)">
      <van-list
        v-model:loading="listLoading"
        :finished="finished"
        :finished-text="warningList.length === 0 ? '暂无预警数据' : '没有更多了'"
        @load="loadWarningList"
      >
        <van-cell-group>
          <van-cell
            v-for="item in warningList"
            :key="item.id"
            :title="item.product_name"
            :label="`编号: ${item.product_no} | 当前库存: ${item.quantity}${item.unit}`"
            :value="getWarningTypeText(item)"
            @click="handleViewDetail(item)"
          >
            <template #extra>
              <van-tag :type="getWarningTagType(item)">
                {{ getWarningTypeText(item) }}
              </van-tag>
            </template>
          </van-cell>
        </van-cell-group>

        <!-- 空状态 -->
        <van-empty
          v-if="!listLoading && !refreshing && warningList.length === 0"
          description="暂无库存预警"
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
  NavBar, 
  Grid, 
  GridItem, 
  Cell, 
  CellGroup, 
  List, 
  PullRefresh,
  Tag,
  Empty,
  Loading,
  Toast
} from 'vant'
import { useStockStore } from '@/store/modules/stock'

const router = useRouter()
const stockStore = useStockStore()

// 响应式数据
const warningList = ref([])
const warningData = ref({})
const refreshing = ref(false)
const listLoading = ref(false)
const initialLoading = ref(true)
const finished = ref(false)

const pagination = reactive({
  page: 1,
  pageSize: 15,
  total: 0
})

// 加载预警列表
const loadWarningList = async (isRefresh = false) => {
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
      limit: pagination.pageSize
    }

    await stockStore.loadWarning(params)
    
    // 处理响应数据
    let listData = []
    let totalCount = 0

    if (stockStore.warningList && Array.isArray(stockStore.warningList)) {
      listData = stockStore.warningList
      totalCount = stockStore.warningTotal || 0
    }

    if (isRefresh) {
      warningList.value = listData
    } else {
      warningList.value = [...warningList.value, ...listData]
    }
    
    pagination.total = totalCount

    // 检查是否加载完成
    if (warningList.value.length >= pagination.total) {
      finished.value = true
    }

    // 如果是第一次加载，获取统计信息
    if (isRefresh) {
      await loadWarningStatistics()
    }

  } catch (error) {
    console.error('加载预警列表失败:', error)
    Toast.fail('加载预警列表失败')
  } finally {
    refreshing.value = false
    listLoading.value = false
    initialLoading.value = false
  }
}

// 加载预警统计
const loadWarningStatistics = async () => {
  try {
    // 这里可以根据实际API调整
    // 暂时从列表数据中统计
    const list = warningList.value
    warningData.value = {
      total: list.length,
      out_of_stock: list.filter(item => (item.quantity || 0) <= 0).length,
      excess: list.filter(item => {
        const quantity = item.quantity || 0
        const maxStock = item.max_stock || 0
        return maxStock > 0 && quantity >= maxStock
      }).length
    }
  } catch (error) {
    console.error('加载预警统计失败:', error)
    warningData.value = {}
  }
}

// 获取预警类型文本
const getWarningTypeText = (item) => {
  const quantity = item.quantity || 0
  const minStock = item.min_stock || 0
  const maxStock = item.max_stock || 0

  if (quantity <= 0) return '缺货'
  if (quantity <= minStock) return '库存不足'
  if (quantity >= maxStock && maxStock > 0) return '库存积压'
  return '库存预警'
}

// 获取预警标签类型
const getWarningTagType = (item) => {
  const quantity = item.quantity || 0
  const minStock = item.min_stock || 0
  const maxStock = item.max_stock || 0

  if (quantity <= 0) return 'danger'
  if (quantity <= minStock) return 'warning'
  if (quantity >= maxStock && maxStock > 0) return 'primary'
  return 'warning'
}

// 查看详情
const handleViewDetail = (item) => {
  router.push(`/stock/detail/${item.id}`)
}

onMounted(() => {
  loadWarningList(true)
})
</script>

<style scoped lang="scss">
.stock-warning-page {
  background: #f7f8fa;
  min-height: 100vh;
}

.warning-stats {
  background: white;
  margin-bottom: 8px;
  padding: 16px 0;
}

.stat-item {
  text-align: center;
  
  .stat-value {
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 4px;
    
    &.warning {
      color: #ff976a;
    }
    
    &.danger {
      color: #ee0a24;
    }
    
    &.primary {
      color: #1989fa;
    }
  }
  
  .stat-label {
    font-size: 12px;
    color: #969799;
  }
}

.page-loading {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 200px;
}
</style>