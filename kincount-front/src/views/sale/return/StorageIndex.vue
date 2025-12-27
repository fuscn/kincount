<template>
  <div class="sale-return-storage-page">
    <!-- 导航栏 -->
    <van-nav-bar title="销售退货入库" fixed placeholder>
      <template #right>
        <van-button 
          size="small" 
          type="primary" 
          @click="handleAddReturnStock"
        >
          新建入库
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 筛选区域 -->
    <div class="filter-wrapper">
      <!-- 状态标签筛选 -->
      <van-tabs v-model="activeStatus" @change="handleStatusChange">
        <van-tab title="全部" name="" />
        <van-tab title="待审核" name="0" />
        <van-tab title="已审核" name="1" />
        <van-tab title="已取消" name="2" />
      </van-tabs>

      <!-- 搜索与高级筛选 -->
      <div class="search-filter">
        <van-search 
          v-model="keyword" 
          placeholder="搜索入库单号/客户名称" 
          @search="handleSearch" 
          @clear="handleClearSearch" 
        />
        
        <!-- 筛选行：客户、仓库、时间在同一行 -->
        <div class="filter-row">
          <!-- 客户筛选 -->
          <div class="customer-filter">
            <CustomerSelect
              ref="customerSelectRef"
              v-model="selectedCustomer"
              :placeholder="getCustomerPlaceholder()"
              :show-all-option="true"
              :show-confirm-button="false"
              :trigger-button-type="'default'"
              :trigger-button-size="'normal'"
              :trigger-button-block="true"
              @change="handleCustomerChange"
            />
          </div>
          
          <!-- 仓库筛选 -->
          <div class="warehouse-filter">
            <WarehouseSelect
              ref="warehouseSelectRef"
              v-model="selectedWarehouse"
              :placeholder="getWarehousePlaceholder()"
              :show-all-option="true"
              :show-confirm-button="false"
              :trigger-button-type="'default'"
              :trigger-button-size="'normal'"
              :trigger-button-block="true"
              @change="handleWarehouseChange"
            />
          </div>
          
          <!-- 时间筛选 -->
          <div class="date-filter">
            <van-dropdown-menu style="width: 100%; height: 100%;">
              <van-dropdown-item 
                v-model="dateRange" 
                :options="dateOptions" 
                :title="getDateTitle()"
                @change="handleFilterChange" 
              />
            </van-dropdown-menu>
          </div>
        </div>
      </div>
    </div>

    <!-- 入库列表 -->
    <van-pull-refresh v-model="refreshing" @refresh="handleRefresh">
      <van-list 
        v-model:loading="loading" 
        :finished="finished" 
        finished-text="没有更多入库单了" 
        @load="handleLoadMore" 
        :immediate-check="false"
      >
        <!-- 入库项 -->
        <van-cell-group class="storage-list">
          <van-cell 
            v-for="stockItem in stockList" 
            :key="stockItem.id" 
            :title="`入库单号：${stockItem.stock_no || '未生成'}`" 
            :label="getStorageLabel(stockItem)" 
            @click="handleViewDetail(stockItem)" 
            is-link
          >
            <template #extra>
              <div class="storage-extra">
                <van-tag :type="getStatusTagType(stockItem.status)">
                  {{ getStatusText(stockItem.status) }}
                </van-tag>
                <div class="amount">¥{{ formatPrice(stockItem.total_amount) }}</div>
                <div class="action-buttons" v-if="stockItem.status === 2">
                  <van-button 
                    v-if="stockItem.status === 2" 
                    size="mini" 
                    type="danger" 
                    @click.stop="handleCancel(stockItem)"
                  >
                    取消
                  </van-button>
                </div>
              </div>
            </template>
          </van-cell>
        </van-cell-group>

        <!-- 空状态 -->
        <van-empty 
          v-if="!loading && !refreshing && stockList.length === 0" 
          image="search" 
          :description="getEmptyDescription()" 
        />
      </van-list>
    </van-pull-refresh>

    <!-- 初始加载状态 -->
    <van-loading v-if="initialLoading" class="initial-loading" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { showToast, showFailToast, showConfirmDialog } from 'vant'
import { useStockStore } from '@/store/modules/stock'
import CustomerSelect from '@/components/business/CustomerSelect.vue'
import WarehouseSelect from '@/components/business/WarehouseSelect.vue'

const router = useRouter()
const stockStore = useStockStore()

// 响应式数据
const initialLoading = ref(true)
const refreshing = ref(false)
const loading = ref(false)
const finished = ref(false)

// 筛选参数
const activeStatus = ref('')
const keyword = ref('')
const selectedCustomer = ref('')
const selectedWarehouse = ref('')
const dateRange = ref('')
const isLoading = ref(false)

// 分页参数
const pagination = reactive({
  page: 1,
  pageSize: 10
})

// 日期选项
const dateOptions = ref([
  { text: '全部时间', value: '' },
  { text: '今日', value: 'today' },
  { text: '本周', value: 'week' },
  { text: '本月', value: 'month' },
  { text: '近3个月', value: 'quarter' }
])

// 计算属性
const stockList = computed(() => stockStore.returnStockList || [])

// 获取客户占位符文本
const getCustomerPlaceholder = () => {
  if (selectedCustomer.value === 0) return '全部客户'
  if (selectedCustomer.value) {
    return '选择客户'  // 选择后显示按钮文本，而不是数字ID
  }
  return '选择客户'
}

// 获取仓库占位符文本
const getWarehousePlaceholder = () => {
  if (selectedWarehouse.value === 0) return '全部仓库'
  if (selectedWarehouse.value) {
    return '选择仓库'  // 选择后显示按钮文本，而不是数字ID
  }
  return '选择仓库'
}

// 获取日期标题
const getDateTitle = () => {
  const option = dateOptions.value.find(opt => opt.value === dateRange.value)
  return option ? option.text : '选择时间'
}

// 获取空状态描述
const getEmptyDescription = () => {
  if (keyword.value) return `未找到"${keyword.value}"相关入库单`
  if (selectedCustomer.value && selectedCustomer.value !== 0) return '该客户暂无入库单'
  if (selectedWarehouse.value && selectedWarehouse.value !== 0) return '该仓库暂无入库单'
  if (dateRange.value) return '该时间段内暂无入库单'
  return '暂无销售退货入库数据'
}

// 格式化金额
const formatPrice = (price) => {
  if (price === null || price === undefined || price === '') return '0.00'
  const num = Number(price)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

// 格式化时间
const formatTime = (time) => {
  if (!time) return ''
  try {
    const d = new Date(time)
    if (isNaN(d.getTime())) return ''
    return d.toLocaleDateString()
  } catch (error) {
    return ''
  }
}

// 获取状态文本
const getStatusText = (status) => {
  const statusMap = {
    0: '待审核',
    1: '已审核',
    2: '已取消'
  }
  return statusMap[status] || '未知状态'
}

// 获取状态标签类型
const getStatusTagType = (status) => {
  const typeMap = {
    0: 'warning',
    1: 'success',
    2: 'danger'
  }
  return typeMap[status] || 'default'
}

// 获取入库单标签信息
const getStorageLabel = (stockItem) => {
  const labels = []
  if (stockItem.target_name) labels.push(`客户：${stockItem.target_name}`)
  if (stockItem.warehouse?.name) labels.push(`仓库：${stockItem.warehouse.name}`)
  if (stockItem.return?.return_no) labels.push(`关联退货单：${stockItem.return.return_no}`)
  if (stockItem.created_at) labels.push(`创建时间：${formatTime(stockItem.created_at)}`)
  if (stockItem.creator?.real_name) labels.push(`创建人：${stockItem.creator.real_name}`)
  return labels.join(' | ')
}

// 客户选择变更事件
const handleCustomerChange = (value, name) => {
  console.log('客户变更:', value, name)
  selectedCustomer.value = value
  handleFilterChange()
}

// 仓库选择变更事件
const handleWarehouseChange = (value, name) => {
  console.log('仓库变更:', value, name)
  selectedWarehouse.value = value
  handleFilterChange()
}

// 状态标签变更事件
const handleStatusChange = (name) => {
  activeStatus.value = name
  loadStorageList(true)
}

// 搜索相关方法
const handleSearch = () => {
  loadStorageList(true)
}

const handleClearSearch = () => {
  keyword.value = ''
  loadStorageList(true)
}

// 筛选条件变化
const handleFilterChange = () => {
  loadStorageList(true)
}

// 下拉刷新
const handleRefresh = () => {
  loadStorageList(true)
}

// 加载更多
const handleLoadMore = () => {
  loadStorageList(false)
}

// 加载入库列表
const loadStorageList = async (isRefresh = false) => {
  if (isLoading.value) return
  if (loading.value && !isRefresh) return
  if (refreshing.value && isRefresh) return

  isLoading.value = true

  try {
    if (isRefresh) {
      pagination.page = 1
      finished.value = false
      refreshing.value = true
    } else {
      if (finished.value) return
      loading.value = true
    }

    // 处理时间范围参数
    let dateRangeParam = ''
    if (dateRange.value) {
      dateRangeParam = dateRange.value
    }

    const params = {
      page: pagination.page,
      pageSize: pagination.pageSize,
      keyword: keyword.value,
      status: activeStatus.value,
      type: 0, // 销售退货入库单固定为type=0
      date_range: dateRangeParam
    }
    
    // 只有当target_id存在且不为0时才加入参数
    if (selectedCustomer.value && selectedCustomer.value !== 0) {
      params.target_id = selectedCustomer.value
    }
    
    // 只有当warehouse_id存在且不为0时才加入参数
    if (selectedWarehouse.value && selectedWarehouse.value !== 0) {
      params.warehouse_id = selectedWarehouse.value
    }
    
    // 移除空值参数
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] == null || (Array.isArray(params[key]) && params[key].length === 0)) {
        delete params[key]
      }
    })

    await stockStore.loadReturnStockList(params)
    
    const currentList = stockList.value || []
    const total = stockStore.returnStockTotal || 0
    
    // 判断是否加载完成
    if (currentList.length >= total || currentList.length === 0) {
      finished.value = true
    } else {
      if (!isRefresh) {
        pagination.page++
      }
    }
  } catch (error) {
    showFailToast('加载销售退货入库失败')
    console.error('loadStorageList error:', error)
    finished.value = false
  } finally {
    initialLoading.value = false
    refreshing.value = false
    loading.value = false
    isLoading.value = false
  }
}

// 创建新入库单
const handleAddReturnStock = () => {
  router.push('/stock/return/create')
}

// 查看详情
const handleViewDetail = (stockItem) => {
  router.push(`/sale/return/storage/detail/${stockItem.id}`)
}

// 取消操作
const handleCancel = (stockItem) => {
  showConfirmDialog({
    title: '确认取消',
    message: `确定要取消入库单 ${stockItem.stock_no} 吗？此操作不可恢复。`
  }).then(async () => {
    try {
      await stockStore.cancelReturnStockData(stockItem.id)
      showToast('取消成功')
      handleRefresh() // 刷新列表
    } catch (error) {
      showToast('取消失败: ' + (error.message || '未知错误'))
    }
  }).catch(() => {
    // 用户取消
  })
}

// 页面挂载时加载数据
onMounted(() => {
  loadStorageList(true)
})
</script>

<style scoped lang="scss">
.sale-return-storage-page {
  min-height: 100vh;
  background-color: #f5f5f5;
  padding-top: 46px; // 适配fixed导航栏

  // 筛选区域样式
  .filter-wrapper {
    background-color: #fff;
    margin-bottom: 10px;

    .search-filter {
      padding: 0 10px 10px;

      .filter-row {
        display: flex;
        align-items: stretch; // 确保子元素高度一致
        gap: 10px;
        margin-top: 10px;
        height: 30px; // 设置固定高度，确保按钮高度一致
        
        .customer-filter,
        .warehouse-filter,
        .date-filter {
          flex: 1; // 平分宽度
          display: flex;
        }
        
        // 客户选择器样式
        .customer-filter {
          :deep(.customer-select) {
            width: 100%;
            height: 100%;
            
            .default-trigger {
              width: 100%;
              height: 100%;
              
              .van-button {
                width: 100%;
                height: 100%;
                border-radius: 6px;
                background-color: #f7f8fa;
                border: 1px solid #ebedf0;
                color: #323233;
                font-size: 14px;
                
                // 文本省略样式
                .van-button__text {
                  overflow: hidden;
                  text-overflow: ellipsis;
                  white-space: nowrap;
                  max-width: 100%;
                  display: inline-block;
                }
                
                &:active {
                  background-color: #f2f3f5;
                }
              }
            }
          }
        }
        
        // 仓库选择器样式
        .warehouse-filter {
          :deep(.warehouse-select-trigger) {
            width: 100%;
            height: 100%;
            
            .default-trigger {
              width: 100%;
              height: 100%;
              
              .van-button {
                width: 100%;
                height: 100%;
                border-radius: 6px;
                background-color: #f7f8fa;
                border: 1px solid #ebedf0;
                color: #323233;
                font-size: 14px;
                
                // 文本省略样式
                .van-button__text {
                  overflow: hidden;
                  text-overflow: ellipsis;
                  white-space: nowrap;
                  max-width: 100%;
                  display: inline-block;
                }
                
                &:active {
                  background-color: #f2f3f5;
                }
              }
            }
          }
        }
        
        // 日期筛选样式
        .date-filter {
          :deep(.van-dropdown-menu) {
            width: 100%;
            height: 100%;
            
            .van-dropdown-menu__bar {
              height: 100%;
              box-shadow: none;
              border-radius: 6px;
              background-color: #f7f8fa;
              border: 1px solid #ebedf0;
              
              .van-dropdown-menu__item {
                flex: 1;
                
                &:first-child {
                  border-radius: 6px;
                }
                
                .van-dropdown-menu__title {
                  font-size: 14px;
                  color: #323233;
                  padding: 0 12px;
                  line-height: 30px;
                  
                  &::after {
                    border-color: #969799;
                  }
                }
              }
            }
          }
        }
      }

      :deep(.van-dropdown-menu) {
        margin-top: 0;
      }
    }
  }

  // 入库列表样式
  .storage-list {
    .van-cell {
      padding: 15px 10px;
      margin-bottom: 10px;
      background-color: #fff;
      border-radius: 8px;

      .storage-extra {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 5px;

        .amount {
          font-size: 14px;
          font-weight: 600;
          color: #ee0a24;
        }

        .action-buttons {
          display: flex;
          gap: 5px;
        }
      }
    }
  }

  // 初始加载样式
  .initial-loading {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
  }
}
</style>