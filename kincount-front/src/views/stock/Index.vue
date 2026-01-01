<template>
  <div class="stock-index">
    <!-- 导航栏 -->
    <van-nav-bar title="库存查询" fixed placeholder>
      <template #right>
        <van-button 
          size="small" 
          type="primary" 
          @click="handleStockTransfer"
        >
          库存调拨
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 筛选区域 -->
    <div class="filter-wrapper">
      <!-- 库存状态标签筛选 -->
      <van-tabs v-model="activeStatus" @change="handleStatusChange">
        <van-tab title="全部" name="" />
        <van-tab title="正常" name="normal" />
        <van-tab title="预警" name="warning" />
        <van-tab title="缺货" name="danger" />
      </van-tabs>

      <!-- 搜索与高级筛选 -->
      <div class="search-filter">
        <van-search 
          v-model="keyword" 
          placeholder="搜索商品名称/编号/SKU编码" 
          @search="handleSearch" 
          @clear="handleClearSearch" 
        />
        
        <!-- 筛选行：仓库、分类、时间在同一行 -->
        <div class="filter-row">
          <!-- 仓库筛选 -->
          <div class="warehouse-filter">
            <WarehouseSelect
              ref="warehouseSelectRef"
              v-model="selectedWarehouse"
              :placeholder="getWarehousePlaceholder()"
              :show-all-option="true"
              :show-confirm-button="false"
              :trigger-button-type="'default'"
              :trigger-button-size="'small'"
              :trigger-button-block="true"
              @change="handleWarehouseChange"
            />
          </div>
          
          <!-- 分类筛选 -->
          <div class="category-filter">
            <CategorySelect
              v-model="selectedCategory"
              :placeholder="getCategoryPlaceholder()"
              :show-confirm-button="false"
              :button-type="'default'"
              :button-size="'small'"
              :button-block="true"
              class="category-select-trigger"
              @change="handleCategoryChange"
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

    <!-- 库存统计 -->
    <div class="stats-cards">
      <van-row gutter="12">
        <van-col span="6">
          <div class="stat-card">
            <div class="stat-value">{{ statistics.skuCount || 0 }}</div>
            <div class="stat-label">SKU种类</div>
          </div>
        </van-col>
        <van-col span="6">
          <div class="stat-card">
            <div class="stat-value">{{ statistics.totalQuantity || 0 }}</div>
            <div class="stat-label">总库存量</div>
          </div>
        </van-col>
        <van-col span="6">
          <div class="stat-card">
            <div class="stat-value">¥{{ statistics.totalValue || 0 }}</div>
            <div class="stat-label">库存价值</div>
          </div>
        </van-col>
        <van-col span="6">
          <div class="stat-card warning">
            <div class="stat-value">{{ statistics.warningCount || 0 }}</div>
            <div class="stat-label">预警SKU</div>
          </div>
        </van-col>
      </van-row>
    </div>

    <!-- 库存列表 -->
    <van-pull-refresh v-model="refreshing" @refresh="handleRefresh">
      <van-list 
        v-model:loading="loading" 
        :finished="finished" 
        finished-text="没有更多库存数据了" 
        @load="handleLoadMore" 
        :immediate-check="false"
      >
        <!-- 库存项 -->
        <van-cell-group class="sku-list">
          <van-swipe-cell
            v-for="stockItem in stockList"
            :key="`${stockItem.sku_id}_${stockItem.warehouse_id}`"
            class="sku-item"
          >
            <van-cell class="sku-cell">
              <div class="product-grid">
                  <!-- 第一行：商品名,规格文本     库存状态 -->
                  <div class="grid-row first-row">
                    <div class="left-column">
                      <span class="product-name">{{ stockItem.sku?.product?.name || '未知商品' }}</span>
                      <span class="spec-text-inline" v-if="getItemSpecText(stockItem)">规格: {{ getItemSpecText(stockItem) }}</span>
                    </div>
                    <div class="right-column">
                      <van-tag :type="getStockTagType(stockItem)">{{ getStockStatusText(stockItem) }}</van-tag>
                    </div>
                  </div>
                  
                  <!-- 第二行：SKU编码  单位        库存数量 -->
                  <div class="grid-row second-row">
                    <div class="left-column">
                      <span class="sku-code" v-if="stockItem.sku?.sku_code">{{ stockItem.sku.sku_code }}</span>
                      <span class="unit-text">单位: {{ stockItem.sku?.unit || '个' }}</span>
                    </div>
                    <div class="right-column">
                      <div class="quantity-info">{{ stockItem.quantity || 0 }} {{ stockItem.sku?.unit || '个' }}</div>
                    </div>
                  </div>
                  
                  <!-- 第三行：位置(仓库)信息       库存金额 -->
                  <div class="grid-row third-row">
                    <div class="left-column">
                      <span class="warehouse-info">仓库: {{ stockItem.warehouse?.name || '未知仓库' }}</span>
                    </div>
                    <div class="right-column">
                      <div class="total-amount">¥{{ formatPrice(Number(stockItem.quantity || 0) * Number(stockItem.sku?.price || 0)) }}</div>
                    </div>
                  </div>
                </div>
            </van-cell>
            <template #right>
              <van-button size="small" type="warning" block @click.stop="handleStockTake(stockItem)">
                盘点
              </van-button>
              <van-button size="small" type="primary" block @click.stop="handleStockTransfer(stockItem)">
                调拨
              </van-button>
            </template>
          </van-swipe-cell>
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
import WarehouseSelect from '@/components/business/WarehouseSelect.vue'
import CategorySelect from '@/components/business/CategorySelect.vue'


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
const selectedWarehouse = ref('')
const selectedCategory = ref('')
const dateRange = ref('')
const isLoading = ref(false)

// 分页参数
const pagination = reactive({
  page: 1,
  pageSize: 20
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
const stockList = computed(() => stockStore.list || [])
const statistics = computed(() => {
  // 如果API返回了正确的统计数据，使用API数据
  const apiStats = stockStore.statistics || {}
  
  // 如果API返回的是列表数据（说明统计API未实现），则从列表数据计算统计
  if (apiStats.list && Array.isArray(apiStats.list)) {
    console.log('从列表数据计算统计信息')
    const list = apiStats.list
    const stats = {
      skuCount: new Set(list.map(item => item.sku_id)).size,
      totalQuantity: list.reduce((sum, item) => sum + (Number(item.quantity) || 0), 0),
      totalValue: list.reduce((sum, item) => sum + (Number(item.total_amount) || 0), 0),
      warningCount: 0 // 需要结合min_stock计算，暂时设为0
    }
    console.log('计算出的统计信息:', stats)
    return stats
  }
  
  // 使用API返回的正确统计数据
  const result = {
    skuCount: apiStats.sku_count || apiStats.skuCount || 0,
    totalQuantity: apiStats.total_quantity || apiStats.totalQuantity || 0,
    totalValue: apiStats.total_value || apiStats.totalValue || 0,
    warningCount: apiStats.warning_count || apiStats.warningCount || 0
  }
  console.log('使用API统计数据:', result)
  return result
})

// 获取仓库占位符文本
const getWarehousePlaceholder = () => {
  if (selectedWarehouse.value === 0) return '全部仓库'
  if (selectedWarehouse.value) {
    return '选择仓库'
  }
  return '选择仓库'
}

// 获取分类占位符文本
const getCategoryPlaceholder = () => {
  if (selectedCategory.value === 0) return '全部分类'
  if (selectedCategory.value) {
    return '选择分类'
  }
  return '选择分类'
}

// 获取日期标题
const getDateTitle = () => {
  const option = dateOptions.value.find(opt => opt.value === dateRange.value)
  return option ? option.text : '选择时间'
}

// 获取空状态描述
const getEmptyDescription = () => {
  if (keyword.value) return `未找到"${keyword.value}"相关库存`
  if (selectedWarehouse.value && selectedWarehouse.value !== 0) return '该仓库暂无库存数据'
  if (selectedCategory.value) return '该分类暂无库存数据'
  if (activeStatus.value) return '该状态下暂无库存数据'
  if (dateRange.value) return '该时间段内暂无库存数据'
  return '暂无库存数据'
}

// 格式化金额
const formatPrice = (price) => {
  if (price === null || price === undefined || price === '') return '0.00'
  const num = Number(price)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

// 获取商品规格文本
const getItemSpecText = (stockItem) => {
  if (stockItem.sku?.spec_text) {
    return stockItem.sku.spec_text
  }
  if (stockItem.sku?.spec && typeof stockItem.sku.spec === 'object') {
    return Object.entries(stockItem.sku.spec)
      .map(([key, value]) => `${key}:${value}`)
      .join(' ')
  }
  return ''
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

// 获取库存标题（商品名称 + 规格）
const getStockTitle = (stockItem) => {
  let title = stockItem.sku?.product?.name || '未知产品'
  if (stockItem.sku?.spec) {
    const specText = Object.entries(stockItem.sku.spec).map(([key, value]) => `${key}:${value}`).join(' ')
    if (specText) {
      title += ` (${specText})`
    }
  }
  return title
}

// 获取库存标签信息
const getStockLabel = (stockItem) => {
  const labels = []
  if (stockItem.sku?.sku_code) labels.push(`SKU：${stockItem.sku.sku_code}`)
  if (stockItem.warehouse?.name) labels.push(`仓库：${stockItem.warehouse.name}`)
  return labels.join(' | ')
}

// 获取库存状态标签类型
const getStockTagType = (stockItem) => {
  const quantity = Number(stockItem.quantity) || 0
  const minStock = Number(stockItem.min_stock) || 0
  
  if (quantity <= 0) return 'danger'
  if (quantity <= minStock) return 'warning'
  return 'success'
}

// 获取库存状态文本
const getStockStatusText = (stockItem) => {
  const quantity = Number(stockItem.quantity) || 0
  const minStock = Number(stockItem.min_stock) || 0
  
  if (quantity <= 0) return '缺货'
  if (quantity <= minStock) return '预警'
  return '正常'
}

// 仓库选择变更事件
const handleWarehouseChange = (value, name) => {
  console.log('仓库变更:', value, name)
  selectedWarehouse.value = value
  handleFilterChange()
}

// 分类选择变更事件
const handleCategoryChange = (value, name) => {
  console.log('分类变更:', value, name)
  selectedCategory.value = value
  handleFilterChange()
}

// 状态标签变更事件
const handleStatusChange = (name) => {
  activeStatus.value = name
  loadStockList(true)
}

// 搜索相关方法
const handleSearch = () => {
  loadStockList(true)
}

const handleClearSearch = () => {
  keyword.value = ''
  loadStockList(true)
}

// 筛选条件变化
const handleFilterChange = () => {
  loadStockList(true)
}

// 下拉刷新
const handleRefresh = () => {
  loadStockList(true)
}

// 加载更多
const handleLoadMore = () => {
  loadStockList(false)
}

// 加载库存列表
const loadStockList = async (isRefresh = false) => {
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

    const params = {
      page: pagination.page,
      pageSize: pagination.pageSize,
      keyword: keyword.value,
      status: activeStatus.value,
      warehouse_id: selectedWarehouse.value,
      category_id: selectedCategory.value,
      date_range: dateRange.value
    }
    
    // 移除空值参数
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] == null || (Array.isArray(params[key]) && params[key].length === 0)) {
        delete params[key]
      }
    })

    await stockStore.loadList(params)
    
    const currentList = stockList.value || []
    const total = stockStore.total || 0
    
    // 判断是否加载完成
    if (currentList.length >= total || currentList.length === 0) {
      finished.value = true
    } else {
      if (!isRefresh) {
        pagination.page++
      }
    }
  } catch (error) {
    showFailToast('加载库存数据失败')
    console.error('loadStockList error:', error)
    finished.value = false
  } finally {
    initialLoading.value = false
    refreshing.value = false
    loading.value = false
    isLoading.value = false
  }
}

// 加载筛选选项
const loadFilterOptions = async () => {
  try {
    // 仓库和分类数据由组件自行加载，这里无需处理
  } catch (error) {
    console.error('加载筛选选项失败:', error)
    showToast('加载筛选选项失败')
  }
}

// 加载统计数据
const loadStatistics = async () => {
  try {
    console.log('开始加载统计数据...')
    await stockStore.loadStatistics()
    console.log('统计数据加载完成:', stockStore.statistics)
  } catch (error) {
    console.error('加载统计数据失败:', error)
    // 如果API调用失败，statistics会保持为空对象，计算属性会使用stockList来计算
  }
}

// 库存调拨
const handleStockTransfer = (stockItem) => {
  showConfirmDialog({
    title: '库存调拨',
    message: `是否要对「${stockItem.sku?.product?.name || '未知产品'}」进行库存调拨？`
  }).then(() => {
    router.push({
      path: '/stock/transfer',
      query: { 
        sku_id: stockItem.sku_id,
        sku_name: stockItem.sku?.product?.name || '未知产品',
        warehouse_id: stockItem.warehouse_id
      }
    })
  }).catch(() => {
    // 取消操作
  })
}

// 库存盘点
const handleStockTake = (stockItem) => {
  showConfirmDialog({
    title: '库存盘点',
    message: `是否要对「${stockItem.sku?.product?.name || '未知产品'}」进行库存盘点？`
  }).then(() => {
    router.push({
      path: '/stock/take',
      query: { 
        sku_id: stockItem.sku_id,
        sku_name: stockItem.sku?.product?.name || '未知产品',
        warehouse_id: stockItem.warehouse_id
      }
    })
  }).catch(() => {
    // 取消操作
  })
}

// 页面挂载时加载数据
onMounted(() => {
  loadFilterOptions()
  loadStockList(true)
  loadStatistics()
})
</script>

<style scoped lang="scss">
.stock-index {
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
        
        .warehouse-filter,
        .category-filter,
        .date-filter {
          flex: 1; // 平分宽度
          display: flex;
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
                
                &:active {
                  background-color: #f2f3f5;
                }
              }
            }
          }
        }
        
        // 分类筛选样式
        .category-filter {
          :deep(.category-select-trigger) {
            width: 100%;
            height: 100%;
            
            .category-select-wrapper {
              width: 100%;
              height: 100%;
              
              .van-button {
                width: 100% !important;
                height: 100% !important;
                border-radius: 6px !important;
                background-color: #f7f8fa !important;
                border: 1px solid #ebedf0 !important;
                color: #323233 !important;
                font-size: 14px !important;
                box-shadow: none !important;
                
                &:active {
                  background-color: #f2f3f5 !important;
                }
                
                &:before {
                  display: none !important;
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

  // 统计卡片样式
  .stats-cards {
    padding: 0 10px;
    margin-bottom: 10px;

    .stat-card {
      background: white;
      border-radius: 8px;
      padding: 12px;
      text-align: center;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
      
      &.warning {
        background: #fff2f0;
        
        .stat-value {
          color: #ff4d4f;
        }
      }
      
      .stat-value {
        font-size: 16px;
        font-weight: bold;
        color: #1989fa;
        margin-bottom: 4px;
      }
      
      .stat-label {
        font-size: 11px;
        color: #969799;
      }
    }
  }

  // 库存列表样式
  .sku-list {
    .sku-item {
      margin-bottom: 8px;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);

      &:last-child {
        margin-bottom: 0;
      }
    }

    .sku-cell {
      padding: 10px 16px;
      background-color: #fff;

      &:after {
        display: none;
      }
    }
  }

  .product-grid {
    display: flex;
    flex-direction: column;
    gap: 4px;
    width: 100%;
  }

  .grid-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;

    .left-column {
      flex: 1;
      display: flex;
      align-items: center;
      gap: 8px;
      min-width: 0;
    }

    .right-column {
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: flex-end;
      min-width: 100px;
    }
  }

  .first-row {
    .left-column {
      display: flex;
      align-items: center;
      gap: 8px;
      flex: 1;
      min-width: 0;

      .product-name {
        font-weight: bold;
        color: #323233;
        font-size: 14px;
        white-space: normal;
        word-wrap: break-word;
        line-height: 1.3;
      }

      .spec-text-inline {
        font-size: 12px;
        color: #969799;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        text-align: left;
      }
    }
  }

  .second-row {
      .left-column {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1;
        min-width: 0;

        .sku-code,
        .unit-text {
          font-size: 12px;
          color: #969799;
        }
      }
      
      .right-column {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        min-width: 100px;
      }
    }

    .third-row {
      .left-column {
        flex: 1;
        min-width: 0;
      }
      
      .right-column {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        min-width: 100px;
      }

      .quantity-info {
        font-size: 14px;
        color: #323233;
      }

      .value-info {
        font-size: 14px;
        font-weight: 600;
        color: #ee0a24;
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

// 文本省略样式
:deep(.van-button__text) {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 100%;
  display: inline-block;
}
</style>