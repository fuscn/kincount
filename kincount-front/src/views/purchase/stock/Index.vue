<template>
  <div class="purchase-stock-container">
    <!-- 导航栏 -->
    <van-nav-bar
      title="采购入库单管理"
      left-text="返回"
      left-arrow
      @click-left="$router.back()"
    />

    <!-- 筛选区域 -->
    <div class="filter-wrapper">
      <!-- 状态标签筛选 -->
      <van-tabs v-model="activeStatus" @change="handleStatusChange" swipeable>
        <van-tab title="全部" name="" />
        <van-tab title="待审核" name="1" />
        <van-tab title="已审核" name="2" />
        <van-tab title="已取消" name="3" />
      </van-tabs>

      <!-- 搜索与高级筛选 -->
      <div class="search-filter">
        <van-search 
          v-model="searchParams.stock_no" 
          placeholder="搜索入库单号" 
          @search="handleSearch" 
          @clear="handleClearSearch" 
        />
        <van-dropdown-menu>
          <!-- 供应商筛选 -->
          <van-dropdown-item 
            v-model="searchParams.supplier_id" 
            :options="supplierOptions" 
            placeholder="选择供应商"
            @change="handleFilterChange" 
          />
          <!-- 仓库筛选 -->
          <van-dropdown-item 
            v-model="searchParams.warehouse_id" 
            :options="warehouseOptions" 
            placeholder="选择仓库"
            @change="handleFilterChange" 
          />
          <!-- 时间筛选 -->
          <van-dropdown-item 
            ref="dateDropdown" 
            title="选择时间"
          >
            <div class="date-filter-content">
              <van-cell title="开始日期" :value="searchParams.start_date || '请选择'" @click="showStartDatePicker = true" />
              <van-cell title="结束日期" :value="searchParams.end_date || '请选择'" @click="showEndDatePicker = true" />
              <div class="date-filter-actions">
                <van-button size="small" type="default" @click="handleDateReset">重置</van-button>
                <van-button size="small" type="primary" @click="handleDateConfirm">确认</van-button>
              </div>
            </div>
          </van-dropdown-item>
        </van-dropdown-menu>
      </div>
    </div>

    <!-- 列表区域 -->
    <div class="list-section">
      <van-pull-refresh v-model="refreshing" @refresh="onRefresh">
        <van-list
          v-model:loading="loading"
          :finished="finished"
          :finished-text="stockList.length > 0 ? '没有更多了' : ''"
          @load="onLoad"
        >
          <!-- 空状态 -->
          <div v-if="stockList.length === 0 && !loading" class="empty-state">
            <van-empty image="search" description="暂无入库单数据" />
          </div>

          <!-- 入库单列表 -->
          <div
            v-for="item in stockList"
            :key="item.id"
            class="stock-item"
            @click="handleViewDetail(item)"
          >
            <div class="stock-header">
              <span class="stock-no">{{ item.stock_no }}</span>
              <van-tag :type="getStatusTagType(item.status)" size="small">
                {{ getStatusText(item.status) }}
              </van-tag>
            </div>
            
            <div class="stock-content">
              <div class="stock-info">
                <div class="info-item">
                  <span class="label">供应商：</span>
                  <span class="value">{{ item.supplier?.name || '未知' }}</span>
                </div>
                <div class="info-item">
                  <span class="label">仓库：</span>
                  <span class="value">{{ item.warehouse?.name || '未知' }}</span>
                </div>
                <div class="info-item">
                  <span class="label">关联采购单：</span>
                  <span class="value">{{ item.purchase_order_id ? `PO${item.purchase_order_id}` : '无' }}</span>
                </div>
                <div class="info-item">
                  <span class="label">金额：</span>
                  <span class="value amount">¥{{ formatPrice(item.total_amount) }}</span>
                </div>
                <div class="info-item">
                  <span class="label">创建时间：</span>
                  <span class="value time">{{ formatDate(item.created_at) }}</span>
                </div>
              </div>
              
              <div class="stock-actions">
                <van-button
                  v-if="item.status === 1"
                  size="mini"
                  type="primary"
                  @click.stop="handleAudit(item)"
                >
                  审核
                </van-button>
                <van-button
                  v-if="item.status === 2"
                  size="mini"
                  type="warning"
                  @click.stop="handleCancelAudit(item)"
                >
                  取消审核
                </van-button>
                <van-button
                  v-if="item.status !== 3"
                  size="mini"
                  type="danger"
                  @click.stop="handleCancel(item)"
                >
                  取消
                </van-button>
                <van-button
                  size="mini"
                  plain
                  @click.stop="handleViewDetail(item)"
                >
                  详情
                </van-button>
              </div>
            </div>
          </div>
        </van-list>
      </van-pull-refresh>
    </div>

    <!-- 日期选择器 -->
    <van-popup v-model:show="showStartDatePicker" position="bottom">
      <van-date-picker
        v-model="startDate"
        @confirm="onStartDateConfirm"
        @cancel="showStartDatePicker = false"
      />
    </van-popup>
    <van-popup v-model:show="showEndDatePicker" position="bottom">
      <van-date-picker
        v-model="endDate"
        @confirm="onEndDateConfirm"
        @cancel="showEndDatePicker = false"
      />
    </van-popup>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePurchaseStore } from '@/store/modules/purchase'
import { useSupplierStore } from '@/store/modules/supplier'
import { useWarehouseStore } from '@/store/modules/warehouse'
import { showConfirmDialog, showToast } from 'vant'
import { useRouter } from 'vue-router'

const purchaseStore = usePurchaseStore()
const supplierStore = useSupplierStore()
const warehouseStore = useWarehouseStore()
const router = useRouter()

// 激活的状态标签
const activeStatus = ref('')

// 搜索参数
const searchParams = ref({
  stock_no: '',
  supplier_id: '',
  warehouse_id: '',
  status: '',
  start_date: '',
  end_date: ''
})

// 日期选择相关
const showStartDatePicker = ref(false)
const showEndDatePicker = ref(false)
const startDate = ref([])
const endDate = ref([])
const dateDropdown = ref(null)

// 供应商选项
const supplierOptions = ref([
  { text: '全部供应商', value: '' },
])

// 仓库选项
const warehouseOptions = ref([
  { text: '全部仓库', value: '' },
])

// 列表相关状态
const loading = ref(false)
const finished = ref(false)
const refreshing = ref(false)
const stockList = computed(() => purchaseStore.stockList)

// 分页参数
const pagination = ref({
  page: 1,
  page_size: 20
})

// 格式化金额
const formatPrice = (price) => {
  if (price === null || price === undefined || price === '') return '0.00'
  const num = Number(price)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

// 格式化日期
const formatDate = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toLocaleDateString() + ' ' + date.toLocaleTimeString().slice(0, 5)
}

// 状态文本映射
const getStatusText = (status) => {
  const statusMap = {
    1: '待审核',
    2: '已审核',
    3: '已取消'
  }
  return statusMap[status] || '未知'
}

// 状态标签类型
const getStatusTagType = (status) => {
  const typeMap = {
    1: 'warning',  // 待审核 - 警告色
    2: 'success',  // 已审核 - 成功色
    3: 'danger'    // 已取消 - 危险色
  }
  return typeMap[status] || 'default'
}

// 查看详情 - 跳转到详情页面
const handleViewDetail = (item) => {
  router.push(`/purchase/stock/detail/${item.id}`)
}

// 审核操作
const handleAudit = (item) => {
  showConfirmDialog({
    title: '确认审核',
    message: `确定要审核入库单 ${item.stock_no} 吗？审核后将更新库存数量。`
  }).then(async () => {
    try {
      await purchaseStore.auditStock(item.id)
      showToast('审核成功')
      onRefresh() // 刷新列表
    } catch (error) {
      showToast('审核失败: ' + (error.message || '未知错误'))
    }
  }).catch(() => {
    // 用户取消
  })
}

// 取消审核操作
const handleCancelAudit = (item) => {
  showConfirmDialog({
    title: '确认取消审核',
    message: `确定要取消审核入库单 ${item.stock_no} 吗？取消审核将回退库存数量。`
  }).then(async () => {
    try {
      await purchaseStore.cancelAuditStock(item.id)
      showToast('取消审核成功')
      onRefresh() // 刷新列表
    } catch (error) {
      showToast('取消审核失败: ' + (error.message || '未知错误'))
    }
  }).catch(() => {
    // 用户取消
  })
}

// 取消操作
const handleCancel = (item) => {
  showConfirmDialog({
    title: '确认取消',
    message: `确定要取消入库单 ${item.stock_no} 吗？此操作不可恢复。`
  }).then(async () => {
    try {
      await purchaseStore.cancelStock(item.id)
      showToast('取消成功')
      onRefresh() // 刷新列表
    } catch (error) {
      showToast('取消失败: ' + (error.message || '未知错误'))
    }
  }).catch(() => {
    // 用户取消
  })
}

// 状态标签变化
const handleStatusChange = (name) => {
  searchParams.value.status = name
  handleSearch()
}

// 搜索处理
const handleSearch = () => {
  pagination.value.page = 1
  finished.value = false
  onLoad(true)
}

// 清除搜索
const handleClearSearch = () => {
  searchParams.value.stock_no = ''
  handleSearch()
}

// 筛选条件变化
const handleFilterChange = () => {
  handleSearch()
}

// 日期确认
const handleDateConfirm = () => {
  if (dateDropdown.value) {
    dateDropdown.value.toggle()
  }
  handleSearch()
}

// 日期重置
const handleDateReset = () => {
  searchParams.value.start_date = ''
  searchParams.value.end_date = ''
  startDate.value = []
  endDate.value = []
  if (dateDropdown.value) {
    dateDropdown.value.toggle()
  }
  handleSearch()
}

// 下拉刷新
const onRefresh = () => {
  pagination.value.page = 1
  finished.value = false
  onLoad(true)
}

// 加载数据
const onLoad = async (isRefresh = false) => {
  // 防止重复请求
  if (loading.value && !isRefresh) return
  
  if (isRefresh) {
    refreshing.value = true
  } else {
    loading.value = true
  }

  try {
    const params = {
      ...pagination.value,
      ...searchParams.value
    }

    // 清理空参数
    Object.keys(params).forEach(key => {
      if (params[key] === '') {
        delete params[key]
      }
    })

    await purchaseStore.loadStockList(params)

    if (isRefresh) {
      refreshing.value = false
    } else {
      loading.value = false
    }

    // 检查是否加载完毕
    const currentLength = purchaseStore.stockList.length
    const total = purchaseStore.stockTotal
    
    if (currentLength >= total || currentLength === 0) {
      finished.value = true
    } else {
      pagination.value.page++
    }
  } catch (error) {
    if (isRefresh) {
      refreshing.value = false
    } else {
      loading.value = false
    }
    finished.value = true
  }
}

// 开始日期选择确认
const onStartDateConfirm = (value) => {
  const year = value.selectedValues[0]
  const month = value.selectedValues[1].toString().padStart(2, '0')
  const day = value.selectedValues[2].toString().padStart(2, '0')
  searchParams.value.start_date = `${year}-${month}-${day}`
  showStartDatePicker.value = false
}

// 结束日期选择确认
const onEndDateConfirm = (value) => {
  const year = value.selectedValues[0]
  const month = value.selectedValues[1].toString().padStart(2, '0')
  const day = value.selectedValues[2].toString().padStart(2, '0')
  searchParams.value.end_date = `${year}-${month}-${day}`
  showEndDatePicker.value = false
}

// 加载供应商选项
const loadSupplierOptions = async () => {
  try {
    const response = await supplierStore.loadList({ page: 1, page_size: 1000 })
    
    let supplierList = []
    if (response && response.list) {
      supplierList = response.list
    } else if (response && response.data && response.data.list) {
      supplierList = response.data.list
    } else if (Array.isArray(response)) {
      supplierList = response
    }
    
    // 转换格式为 { text: name, value: id }
    supplierOptions.value = [
      { text: '全部供应商', value: '' },
      ...supplierList.map(item => ({
        text: item.name,
        value: item.id
      }))
    ]
  } catch (error) {
    showToast('加载供应商列表失败')
  }
}

// 加载仓库选项
const loadWarehouseOptions = async () => {
  try {
    await warehouseStore.loadOptions()
    warehouseOptions.value = [
      { text: '全部仓库', value: '' },
      ...warehouseStore.options
    ]
  } catch (error) {
    showToast('加载仓库列表失败')
  }
}

// 初始化加载
onMounted(async () => {
  // 加载供应商和仓库选项
  await Promise.all([
    loadSupplierOptions(),
    loadWarehouseOptions()
  ])
  
  // 让van-list自动触发第一次加载
})
</script>

<style scoped lang="scss">
.purchase-stock-container {
  padding: 0;
  background-color: #f7f8fa;
  min-height: 100vh;
}

/* 导航栏样式 */
:deep(.van-nav-bar) {
  background: #fff;
  position: sticky;
  top: 0;
  z-index: 1000;
}

/* 筛选区域样式 */
.filter-wrapper {
  background: white;
  position: sticky;
  top: 46px; /* 导航栏高度 */
  z-index: 100;
}

.search-filter {
  padding: 0 12px;
}

:deep(.van-tabs__wrap) {
  border-bottom: 1px solid #f0f0f0;
}

:deep(.van-dropdown-menu) {
  box-shadow: none;
  background: transparent;
}

.date-filter-content {
  padding: 16px;
}

.date-filter-actions {
  display: flex;
  justify-content: space-between;
  margin-top: 16px;
  padding: 0 8px;
}

/* 列表区域样式 */
.list-section {
  background: white;
  min-height: 60vh;
}

.empty-state {
  padding: 40px 20px;
}

.stock-item {
  margin: 12px;
  border: 1px solid #ebedf0;
  border-radius: 8px;
  overflow: hidden;
  background: white;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  cursor: pointer;
}

.stock-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: #fafafa;
  border-bottom: 1px solid #f0f0f0;
}

.stock-no {
  font-weight: bold;
  color: #323233;
  font-size: 14px;
}

.stock-content {
  padding: 16px;
}

.stock-info {
  margin-bottom: 12px;
}

.info-item {
  display: flex;
  margin-bottom: 6px;
  font-size: 13px;
}

.info-item .label {
  color: #969799;
  min-width: 70px;
}

.info-item .value {
  color: #323233;
  flex: 1;
}

.info-item .amount {
  color: #ee0a24;
  font-weight: bold;
}

.info-item .time {
  color: #646566;
}

.stock-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  flex-wrap: wrap;
}

:deep(.van-button--mini) {
  height: 24px;
  padding: 0 8px;
  font-size: 11px;
}
</style>