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
                  <span class="value amount">¥{{ item.total_amount }}</span>
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

    <!-- 详情弹窗 -->
    <van-popup
      v-model:show="showDetailPopup"
      position="bottom"
      closeable
      :style="{ height: '85%' }"
      @close="handleCloseDetail"
    >
      <div class="detail-popup" v-if="currentStockDetail">
        <h3 class="detail-title">入库单详情 - {{ currentStockDetail.stock_no }}</h3>
        <div class="detail-content">
          <van-cell-group inset>
            <van-cell title="入库单号" :value="currentStockDetail.stock_no" />
            
            <!-- 关联采购单信息 - 简化显示 -->
            <van-cell 
              title="关联采购单" 
              :value="currentStockDetail.purchaseOrder ? currentStockDetail.purchaseOrder.order_no : (currentStockDetail.purchase_order_id ? `PO${currentStockDetail.purchase_order_id}` : '无')" 
            />
            
            <van-cell title="供应商" :value="currentStockDetail.supplier?.name" />
            <van-cell title="联系人" :value="currentStockDetail.supplier?.contact_person || '无'" />
            <van-cell title="联系电话" :value="currentStockDetail.supplier?.phone || '无'" />
            <van-cell title="仓库" :value="currentStockDetail.warehouse?.name" />
            <van-cell title="仓库地址" :value="currentStockDetail.warehouse?.address || '无'" />
            <van-cell title="总金额" :value="`¥${currentStockDetail.total_amount}`" />
            <van-cell title="状态" :value="getStatusText(currentStockDetail.status)" />
            <van-cell title="创建人" :value="currentStockDetail.creator?.real_name" />
            <van-cell title="审核人" :value="currentStockDetail.auditor?.real_name || '未审核'" />
            <van-cell title="审核时间" :value="currentStockDetail.audit_time || '未审核'" />
            <van-cell title="创建时间" :value="formatDate(currentStockDetail.created_at)" />
            <van-cell title="备注" :value="currentStockDetail.remark || '无'" />
          </van-cell-group>

          <!-- SKU明细 -->
          <div class="sku-section">
            <h4>SKU明细</h4>
            <div
              v-for="(item, index) in currentStockDetail.items"
              :key="index"
              class="sku-item"
            >
              <van-cell
                :title="item.product?.name || '未知商品'"
                :label="`商品编号: ${item.product?.product_no || '无'}`"
              />
              <van-cell
                title="规格"
                :value="item.product?.spec || '无规格'"
              />
              <van-cell
                title="数量"
                :value="`${item.quantity} ${item.product?.unit || '个'}`"
              />
              <van-cell title="单价" :value="`¥${item.price}`" />
              <van-cell title="金额" :value="`¥${item.total_amount}`" />
            </div>
            <div v-if="!currentStockDetail.items || currentStockDetail.items.length === 0" class="no-data">
              暂无明细数据
            </div>
          </div>
        </div>
      </div>
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

// 弹窗控制
const showDetailPopup = ref(false)

// 当前选中的入库单详情
const currentStockDetail = ref(null)

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

// 格式化日期
const formatDate = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toLocaleDateString() + ' ' + date.toLocaleTimeString().slice(0, 5)
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
    console.error('加载供应商选项失败:', error)
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
    console.error('加载仓库选项失败:', error)
    showToast('加载仓库列表失败')
  }
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

// 加载数据 - 修复重复加载问题
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
    console.error('加载入库单列表失败:', error)
    if (isRefresh) {
      refreshing.value = false
    } else {
      loading.value = false
    }
    finished.value = true
  }
}

// 查看详情 - 优化：只请求一次
const handleViewDetail = async (item) => {
  try {
    showDetailPopup.value = true
    // 先显示加载状态
    currentStockDetail.value = null
    
    // 只请求一次详情接口，该接口已经包含items数据
    const detail = await purchaseStore.loadStockDetail(item.id)
    currentStockDetail.value = detail
  } catch (error) {
    console.error('加载入库单详情失败:', error)
    showToast('加载详情失败')
    showDetailPopup.value = false
  }
}

// 关闭详情弹窗
const handleCloseDetail = () => {
  currentStockDetail.value = null
  showDetailPopup.value = false
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
      console.error('审核失败:', error)
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
      console.error('取消审核失败:', error)
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
      console.error('取消失败:', error)
      showToast('取消失败: ' + (error.message || '未知错误'))
    }
  }).catch(() => {
    // 用户取消
  })
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

<style scoped>
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

/* 详情弹窗样式 */
.detail-popup {
  height: 100%;
  display: flex;
  flex-direction: column;
}

.detail-title {
  text-align: center;
  margin: 0;
  padding: 16px;
  background: white;
  border-bottom: 1px solid #ebedf0;
  color: #323233;
  font-size: 16px;
  font-weight: bold;
}

.detail-content {
  flex: 1;
  overflow-y: auto;
  padding-bottom: 20px;
}

.sku-section {
  margin: 20px 16px 0;
}

.sku-section h4 {
  margin-bottom: 12px;
  color: #323233;
  font-size: 14px;
  font-weight: bold;
}

.sku-item {
  border: 1px solid #ebedf0;
  border-radius: 6px;
  margin-bottom: 10px;
  overflow: hidden;
  background: white;
}

.no-data {
  text-align: center;
  padding: 20px;
  color: #969799;
  font-size: 14px;
}

:deep(.van-cell) {
  padding: 12px 16px;
}

:deep(.van-cell__title) {
  flex: 2;
  font-size: 14px;
}

:deep(.van-cell__value) {
  flex: 3;
  text-align: right;
  font-size: 14px;
  color: #323233;
}

:deep(.van-cell__label) {
  font-size: 12px;
  color: #969799;
  margin-top: 2px;
}

:deep(.van-popup) {
  border-radius: 8px 8px 0 0;
}
</style>