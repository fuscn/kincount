<template>
  <div class="purchase-return-stock-index">
    <!-- 导航栏 -->
    <van-nav-bar title="采购退货出库单管理" fixed placeholder left-text="返回" left-arrow @click-left="$router.back()" />

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
        <van-search v-model="searchParams.keyword" placeholder="搜索出库单号、供应商名称" @search="handleSearch"
          @clear="handleClearSearch" />
        
        <!-- 筛选行：供应商、仓库、时间在同一行 -->
        <div class="filter-row">
          <!-- 供应商筛选 -->
          <div class="supplier-filter">
            <SupplierSelect
              v-model="searchParams.supplier_id"
              :placeholder="getSupplierPlaceholder()"
              :show-all-option="true"
              :show-confirm-button="false"
              :trigger-button-type="'default'"
              :trigger-button-size="'normal'"
              :trigger-button-block="true"
              @change="handleFilterChange"
            />
          </div>
          
          <!-- 仓库筛选 -->
          <div class="warehouse-filter">
            <WarehouseSelect
              v-model="searchParams.warehouse_id"
              :placeholder="getWarehousePlaceholder()"
              :show-all-option="true"
              :show-confirm-button="false"
              :trigger-button-type="'default'"
              :trigger-button-size="'normal'"
              :trigger-button-block="true"
              @change="handleFilterChange"
            />
          </div>
          
          <!-- 时间筛选 -->
          <div class="date-filter">
            <van-dropdown-menu class="date-dropdown-menu">
              <van-dropdown-item 
                ref="dateDropdown"
                :title="getDateTitle()"
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
      </div>
    </div>

    <!-- 退货出库单列表 -->
    <van-pull-refresh v-model="refreshing" @refresh="handleRefresh">
      <van-list v-model:loading="loading" :finished="finished" finished-text="没有更多退货出库单了" @load="handleLoadMore" :immediate-check="false">
        <!-- 退货出库单项 -->
        <van-cell-group class="stock-list">
          <van-cell v-for="item in returnStockList" :key="item.id" :title="`出库单号：${item.stock_no || '未生成'}`" :label="getStockLabel(item)" @click="handleViewDetail(item)" is-link>
            <template #extra>
              <div class="stock-extra">
                <van-tag :type="getStatusTagType(item.status)" size="small">
                  {{ getStatusText(item.status) }}
                </van-tag>
                <div class="amount">¥{{ formatPrice(item.total_amount) }}</div>
              </div>
            </template>
          </van-cell>
        </van-cell-group>

        <!-- 空状态 -->
        <van-empty v-if="!loading && !refreshing && returnStockList.length === 0" image="search" description="暂无退货出库单数据" />
      </van-list>
    </van-pull-refresh>

    <!-- 初始加载状态 -->
    <van-loading v-if="initialLoading" class="initial-loading" />

    <!-- 日期选择器 -->
    <van-popup v-model:show="showStartDatePicker" position="bottom">
      <van-date-picker v-model="startDate" @confirm="onStartDateConfirm" @cancel="showStartDatePicker = false" />
    </van-popup>
    <van-popup v-model:show="showEndDatePicker" position="bottom">
      <van-date-picker v-model="endDate" @confirm="onEndDateConfirm" @cancel="showEndDatePicker = false" />
    </van-popup>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useStockStore } from '@/store/modules/stock'
import { showConfirmDialog, showToast, showLoadingToast, closeToast } from 'vant'
import { useRouter } from 'vue-router'
import SupplierSelect from '@/components/business/SupplierSelect.vue'
import WarehouseSelect from '@/components/business/WarehouseSelect.vue'

const stockStore = useStockStore()
const router = useRouter()

// 响应式数据
const initialLoading = ref(false)
const refreshing = ref(false)
const loading = ref(false)
const finished = ref(false)
const isLoading = ref(false)

// 筛选参数
const activeStatus = ref('')
const searchParams = ref({
  keyword: '',
  supplier_id: '',
  warehouse_id: '',
  status: '',
  start_date: '',
  end_date: '',
  type: 1, // 固定为采购退货出库（数据库定义：0-销售退货 1-采购退货）
})

// 日期选择相关
const showStartDatePicker = ref(false)
const showEndDatePicker = ref(false)
const startDate = ref([])
const endDate = ref([])
const dateDropdown = ref(null)

// 分页参数
const pagination = ref({
  page: 1,
  page_size: 10
})

// 计算属性获取退货出库单列表
const returnStockList = computed(() => stockStore.returnStockList || [])

// 获取供应商占位符文本
const getSupplierPlaceholder = () => {
  if (searchParams.value.supplier_id === 0) return '全部供应商'
  if (searchParams.value.supplier_id) {
    return `供应商${searchParams.value.supplier_id}`
  }
  return '选择供应商'
}

// 获取仓库占位符文本
const getWarehousePlaceholder = () => {
  if (searchParams.value.warehouse_id === 0) return '全部仓库'
  if (searchParams.value.warehouse_id) {
    return `仓库${searchParams.value.warehouse_id}`
  }
  return '选择仓库'
}

// 获取日期标题
const getDateTitle = () => {
  if (searchParams.value.start_date && searchParams.value.end_date) {
    return `${searchParams.value.start_date} 至 ${searchParams.value.end_date}`
  }
  return '选择时间'
}

// 获取出库单标签信息
const getStockLabel = (item) => {
  const labels = []
  if (item.target_info?.name) labels.push(`供应商：${item.target_info.name}`)
  if (item.warehouse?.name) labels.push(`仓库：${item.warehouse.name}`)
  if (item.return?.return_no) labels.push(`关联退货单：${item.return.return_no}`)
  if (item.created_at) labels.push(`创建时间：${formatDate(item.created_at)}`)
  if (item.creator?.real_name) labels.push(`创建人：${item.creator.real_name}`)
  return labels.join(' | ')
}

// 格式化金额
const formatPrice = (price) => {
  if (price === null || price === undefined || price === '') return '0.00'
  const num = Number(price)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

// 格式化日期（简略版）
const formatDate = (dateString) => {
  if (!dateString) return ''
  return new Date(dateString).toLocaleDateString()
}

// 状态文本映射
const getStatusText = (status) => {
  const statusMap = {
    0: '待审核',
    1: '已审核',
    2: '已取消'
  }
  return statusMap[status] || '未知'
}

// 状态标签类型
const getStatusTagType = (status) => {
  const typeMap = {
    0: 'warning',  // 待审核 - 警告色
    1: 'primary',  // 已审核 - 主要色
    2: 'danger'    // 已取消 - 危险色
  }
  return typeMap[status] || 'default'
}

// 查看详情
const handleViewDetail = (item) => {
  router.push(`/purchase/return/storage/detail/${item.id}`)
}

// 状态标签变化
const handleStatusChange = (name) => {
  searchParams.value.status = name
  handleSearch()
}

// 搜索处理
const handleSearch = () => {
  loadStockList(true)
}

// 清除搜索
const handleClearSearch = () => {
  searchParams.value.keyword = ''
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
const handleRefresh = () => {
  loadStockList(true)
}

// 加载更多
const handleLoadMore = () => {
  loadStockList(false)
}

// 加载退货出库单列表
const loadStockList = async (isRefresh = false) => {
  if (isLoading.value) return
  if (loading.value && !isRefresh) return
  if (refreshing.value && isRefresh) return

  isLoading.value = true

  try {
    if (isRefresh) {
      pagination.value.page = 1
      finished.value = false
      refreshing.value = true
    } else {
      if (finished.value) return
      loading.value = true
    }

    const params = {
      page: pagination.value.page,
      page_size: pagination.value.page_size,
      ...searchParams.value
    }

    // 清理空参数
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] == null) {
        delete params[key]
      }
    })

    await stockStore.loadReturnStockList(params)
    
    const currentLength = returnStockList.value.length
    const total = stockStore.returnStockTotal || 0
    
    // 判断是否加载完成
    if (currentLength >= total || currentLength === 0) {
      finished.value = true
    } else {
      if (!isRefresh) {
        pagination.value.page++
      }
    }
  } catch (error) {
    showToast('加载退货出库单列表失败')
    console.error('loadStockList error:', error)
    finished.value = false
  } finally {
    initialLoading.value = false
    refreshing.value = false
    loading.value = false
    isLoading.value = false
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
</script>

<style scoped lang="scss">
.purchase-return-stock-index {
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
        
        .supplier-filter,
        .warehouse-filter,
        .date-filter {
          flex: 1; // 平分宽度
          display: flex;
        }
        
        // 供应商选择器样式
        .supplier-filter {
          :deep(.supplier-select-trigger) {
            width: 100%;
            height: 100%;
          }
          
          :deep(.default-trigger) {
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
              
              &:active {
                background-color: #f2f3f5;
              }
            }
          }
        }
        
        // 仓库选择器样式
        .warehouse-filter {
          :deep(.warehouse-select-trigger) {
            width: 100%;
            height: 100%;
          }
          
          :deep(.default-trigger) {
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
              
              &:active {
                background-color: #f2f3f5;
              }
            }
          }
        }
        
        // 时间筛选样式
        .date-filter {
          :deep(.date-dropdown-menu) {
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

  // 出库单列表样式
  .stock-list {
    .van-cell {
      padding: 15px 10px;
      margin-bottom: 10px;
      background-color: #fff;
      border-radius: 8px;

      .stock-extra {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 5px;

        .amount {
          font-size: 14px;
          font-weight: 600;
          color: #ee0a24;
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