<template>
  <!-- 筛选区域 -->
  <div class="filter-section" v-if="showFilters">
    <div class="filter-row">
      <!-- 分类筛选 -->
      <div class="filter-item" v-if="enableCategoryFilter">
        <CategorySelect
          v-model="filters.category_id"
          placeholder="选择分类"
          :hide-trigger="false"
          :show-all-option="true"
          :button-size="'small'"
          :button-block="false"
          @change="onFilterChange"
        />
      </div>
      
      <!-- 品牌筛选 -->
      <div class="filter-item" v-if="enableBrandFilter">
        <BrandSelect
          v-model="filters.brand_id"
          placeholder="选择品牌"
          :hide-trigger="false"
          :show-all-option="true"
          :button-size="'small'"
          :button-block="false"
          @change="onFilterChange"
        />
      </div>
      

      
      <!-- 重置按钮 -->
      <div class="filter-item">
        <van-button size="small" @click="resetFilters">重置</van-button>
      </div>
    </div>
  </div>
  
  <!-- 列表模式 -->
  <van-pull-refresh v-model="refreshing" @refresh="onRefresh">
    <van-list
      v-model:loading="loading"
      :finished="finished"
      finished-text="没有更多"
      @load="onLoad"
    >
      <div class="sku-card-list" :key="listKey">
        <div 
          v-for="item in list" 
          :key="`${item.id}-${item.sku_id || ''}-${filters.category_id || 'all'}-${filters.brand_id || 'all'}-${listKey}-${renderKey}`" 
          class="sku-card"
          @click="handleCardClick(item)"
        >
          <!-- 选中状态勾号 -->
          <div v-show="selectable && selectedIds.includes(`${item.warehouse_id}-${item.sku_id}`)" class="selected-check">
            <van-icon name="success" size="16" color="#fff" />
          </div>
          
          <!-- 信息区域 -->
          <div class="sku-info">
            <!-- 第一行：商品名称 + 规格 + 售价 -->
            <div class="row product-row">
              <div class="product-info">
                <span class="product-name">{{ getProductName(item) }}</span>
                <span class="sku-spec">{{ getSpecText(item) }}</span>
              </div>
              <div class="sku-price">¥{{ getPrice(item) }}</div>
            </div>
            
            <!-- 第二行：SKU编码 + 库存 -->
            <div class="row sku-row">
              <div class="sku-code">{{ getSkuCode(item) }}</div>
              <div class="stock-info" :class="{ warning: isWarning(item) }">
                <van-icon name="bag-o" size="12" />
                库存: {{ item.quantity || 0 }}
              </div>
            </div>
            
            <!-- 第三行：仓库 -->
            <div class="row spec-row">
              <div></div>
              <div class="warehouse-info">
                <van-icon name="location-o" size="12" />
                {{ getWarehouseName(item) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </van-list>
  </van-pull-refresh>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue'
import { getStockList } from '@/api/stock'
import CategorySelect from '@/components/business/CategorySelect.vue'
import BrandSelect from '@/components/business/BrandSelect.vue'

const props = defineProps({
  // 列表模式参数
  warehouseId: [String, Number],
  keyword: String,
  warningType: String, // low | high
  
  // 选择模式参数
  selectable: {
    type: Boolean,
    default: false
  },
  selectedIds: {
    type: Array,
    default: () => []
  },
  
  // 筛选功能参数
  showFilters: {
    type: Boolean,
    default: false
  },
  enableCategoryFilter: {
    type: Boolean,
    default: true
  },
  enableBrandFilter: {
    type: Boolean,
    default: true
  },

})

const emit = defineEmits(['click-card', 'confirm', 'change']) // 添加新的 emit 事件

// 独立的完整选中商品数据存储
const allSelectedStockData = ref([])

// 筛选条件
const filters = ref({
  category_id: null,
  brand_id: null
})

const list = ref([])
const loading = ref(false)
const finished = ref(false)
const refreshing = ref(false)
const listKey = ref(0) // 用于强制刷新列表
const renderKey = ref(Date.now()) // 用于确保渲染唯一性

// 图片错误处理记录
const imageErrorMap = ref(new Map())

// 更新完整选中数据存储
const updateAllSelectedData = (item) => {
  const uniqueKey = `${item.warehouse_id}-${item.sku_id}`
  const existingIndex = allSelectedStockData.value.findIndex(selected => `${selected.warehouse_id}-${selected.sku_id}` === uniqueKey)
  if (existingIndex > -1) {
    allSelectedStockData.value[existingIndex] = item
  } else {
    allSelectedStockData.value.push(item)
  }
}

// 处理卡片点击事件
const handleCardClick = (item) => {
  // 更新完整数据存储
  updateAllSelectedData(item)
  emit('click-card', item)
}

// 获取完整的选中数据
const getSelectedStockData = () => {
  // 从完整存储中筛选出当前 selectedIds 中的商品
  return allSelectedStockData.value.filter(selected => 
    props.selectedIds.includes(`${selected.warehouse_id}-${selected.sku_id}`)
  )
}

// 确认选择
const confirmSelection = () => {
  const selectedData = getSelectedStockData()
  emit('confirm', {
    selectedIds: props.selectedIds,
    selectedData: selectedData
  })
}

// 获取商品名称（最重要的信息）
const getProductName = (item) => {
  // 优先从 product 对象获取商品名称
  if (item.sku?.product?.name) return item.sku.product.name
  if (item.product?.name) return item.product.name
  
  // 如果商品名称不存在，尝试从 SKU 编码推断（实际项目中可能需要额外API调用）
  if (item.sku?.sku_code) {
    // 假设 SKU 编码格式为 "商品名-SKU编号"，尝试提取商品名
    const skuCode = item.sku.sku_code
    const dashIndex = skuCode.lastIndexOf('-')
    if (dashIndex > 0) {
      return skuCode.substring(0, dashIndex)
    }
    return skuCode
  }
  
  // 最后回退方案
  return `商品-${item.sku_id || item.id}`
}

// 获取 SKU 编码
const getSkuCode = (item) => {
  if (item.sku && item.sku.sku_code) return item.sku.sku_code
  if (item.sku_code) return item.sku_code
  return `SKU-${item.sku_id || item.id}`
}

// 获取规格文本
const getSpecText = (item) => {
  const spec = item.sku?.spec || item.spec
  if (!spec) return '无规格'
  
  if (typeof spec === 'string') {
    try {
      const parsedSpec = JSON.parse(spec)
      return Object.values(parsedSpec).join(' / ')
    } catch {
      return spec
    }
  }
  
  if (typeof spec === 'object') {
    return Object.values(spec).join(' / ')
  }
  
  return '无规格'
}

// 获取价格
const getPrice = (item) => {
  if (item.sku?.sale_price) return item.sku.sale_price
  if (item.sale_price) return item.sale_price
  return '0.00'
}

// 获取产品图片 - 优化版本
const getProductImage = (item) => {
  // 检查是否已经记录过图片错误
  const itemId = item.id
  if (imageErrorMap.value.has(itemId)) {
    return defaultImage
  }
  
  // 尝试获取图片URL
  let imageUrl = ''
  if (item.sku?.product?.image) {
    imageUrl = item.sku.product.image
  } else if (item.product?.image) {
    imageUrl = item.product.image
  }
  
  // 验证图片URL有效性
  if (!imageUrl || imageUrl === 'null' || imageUrl === 'undefined') {
    imageErrorMap.value.set(itemId, true)
    return defaultImage
  }
  
  return imageUrl
}

// 图片加载失败处理 - 优化版本
const handleImageError = (event) => {
  const img = event.target
  const itemId = img.dataset.itemId
  
  // 记录图片加载失败
  if (itemId) {
    imageErrorMap.value.set(itemId, true)
  }
  
  // 立即替换为默认图片，避免反复重试
  img.src = defaultImage
  
  // 移除错误监听，防止后续再次触发
  img.onerror = null
}

// 获取仓库名称
const getWarehouseName = (item) => {
  if (item.warehouse?.name) return item.warehouse.name
  if (item.warehouse_name) return item.warehouse_name
  return '未知仓库'
}

// 获取预警标签
const getWarningTag = (item) => {
  // 根据业务逻辑设置预警标签
  const quantity = item.quantity || 0
  if (quantity === 0) return '缺货'
  if (quantity < 10) return '低库存'
  if (quantity > 500) return '高库存'
  return ''
}

// 判断是否预警
const isWarning = (item) => {
  const quantity = item.quantity || 0
  return quantity === 0 || quantity < 10 || quantity > 500
}

// 筛选条件变化处理
const onFilterChange = () => {
  page = 1
  list.value = []
  finished.value = false
  imageErrorMap.value.clear()
  // 强制刷新列表
  listKey.value += 1
  renderKey.value = Date.now()
  // 强制触发视图更新
  nextTick(() => {
    onLoad()
  })
}

// 重置筛选条件
const resetFilters = () => {
  filters.value = {
    category_id: null,
    brand_id: null
  }
  onFilterChange()
}

let page = 1
let isLoading = false // 加载锁，防止重复请求

const onLoad = async () => {
  if (isLoading) return
  
  try {
    isLoading = true
    loading.value = true
    
    // 构建请求参数
    const params = {
      page,
      limit: 10,
      warehouse_id: props.warehouseId,
      keyword: props.keyword,
      warning_type: props.warningType
    }
    
    // 添加筛选参数
    if (filters.value.category_id) {
      params.category_id = filters.value.category_id
    }
    if (filters.value.brand_id) {
      params.brand_id = filters.value.brand_id
    }
    
    const res = await getStockList(params)
    
    // 处理响应数据 - 注意拦截器已经过滤了数据，直接使用 res
    let dataList = []
    if (res && res.list) {
      dataList = res.list
    } else if (Array.isArray(res)) {
      dataList = res
    } else if (res && res.data && res.data.list) {
      dataList = res.data.list
    }
    
    if (refreshing.value) {
      list.value = []
      refreshing.value = false
      // 清空图片错误记录
      imageErrorMap.value.clear()
    }
    
    if (dataList && dataList.length > 0) {
      // 将加载的数据更新到完整存储中
      dataList.forEach(item => {
        const uniqueKey = `${item.warehouse_id}-${item.sku_id}`
        const existingIndex = allSelectedStockData.value.findIndex(selected => `${selected.warehouse_id}-${selected.sku_id}` === uniqueKey)
        if (existingIndex > -1) {
          allSelectedStockData.value[existingIndex] = item
        } else {
          // 只有当该商品在 selectedIds 中时才添加到完整存储
          if (props.selectedIds.includes(uniqueKey)) {
            allSelectedStockData.value.push(item)
          }
        }
      })
      
      list.value.push(...dataList)
      finished.value = dataList.length < 10
      page++
    } else {
      finished.value = true
    }
    
  } catch (error) {
    console.error('加载库存数据失败:', error)
    finished.value = true
  } finally {
    loading.value = false
    isLoading = false
  }
}

const onRefresh = () => {
  page = 1
  finished.value = false
  loading.value = true
  // 清空图片错误记录
  imageErrorMap.value.clear()
  onLoad()
}

// 监听 props 变化重新加载
watch(
  () => [props.warehouseId, props.keyword, props.warningType],
  () => {
    page = 1
    list.value = []
    finished.value = false
    // 清空图片错误记录
    imageErrorMap.value.clear()
    onLoad()
  }
)

// 监听筛选条件变化，确保选择状态正确更新
watch(
  () => [filters.value.category_id, filters.value.brand_id],
  () => {
    // 筛选条件变化时，不需要重置选择状态
    // 选择状态由父组件OrderForm.vue中的tempSelectedIds管理
  }
)

// 监听selectedIds变化，确保选择状态正确更新
watch(
  () => props.selectedIds,
  (newVal, oldVal) => {
    // 选择状态变化处理
  },
  { deep: true }
)

// 暴露方法给父组件
defineExpose({
  confirmSelection,
  getSelectedStockData
})
</script>

<style scoped lang="scss">
/* 筛选区域样式 */
.filter-section {
  padding: 12px;
  background: #fff;
  border-bottom: 1px solid #eee;
  
  .filter-row {
    display: flex;
    align-items: center;
    gap: 8px;
    // 移除 flex-wrap: wrap，禁止换行
    white-space: nowrap;
    overflow-x: auto;
    padding-bottom: 4px; // 为滚动条留出空间
    
    // 隐藏滚动条但保持滚动功能
    &::-webkit-scrollbar {
      display: none;
    }
    -ms-overflow-style: none;
    scrollbar-width: none;
  }
  
  .filter-item {
    // 分类和品牌筛选组件使用最小宽度，允许自适应
    &:not(:last-child) {
      min-width: 120px;
      flex: 1;
      max-width: 180px;
    }
    
    // 重置按钮特殊处理
    &:last-child {
      flex: 0 0 auto;
      min-width: 60px;
      max-width: 80px;
    }
    
    // 确保所有筛选按钮高度一致
    :deep(.van-button) {
      height: 32px;
      line-height: 30px;
    }
    
    // 确保筛选组件高度和宽度一致
    :deep(.van-field__control) {
      height: 32px;
      line-height: 30px;
    }
    
    // 确保CategorySelect和BrandSelect组件宽度一致
    :deep(.van-field) {
      width: 100%;
    }
    
    // 确保CategorySelect组件内部按钮宽度一致
    :deep(.category-select-wrapper .van-button) {
      width: 100%;
    }
    
    // 确保BrandSelect组件内部按钮宽度一致
    :deep(.brand-select .van-button) {
      width: 100%;
    }
  }
}

/* 列表模式样式（原有） */
.sku-card-list {
  padding: 8px;
}

.sku-card {
  display: flex;
  background: #fff;
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: all 0.2s ease;
  position: relative;
  
  &:active {
    background: #f5f5f5;
  }
  
  .selected-check {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 20px;
    height: 20px;
    background: #07c160;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
  }
  
  .sku-info {
    flex: 1;
    min-width: 0; /* 防止内容溢出 */
    
    .row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 6px;
      
      &:last-child {
        margin-bottom: 0;
      }
    }
    
    .product-row {
      .product-info {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-right: 8px;
      }
      
      .product-name {
        font-size: 15px;
        font-weight: 600;
        color: #333;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
      
      .sku-spec {
        font-size: 12px;
        color: #888;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
      
      .sku-price {
        font-size: 15px;
        font-weight: bold;
        color: #ff4444;
        flex-shrink: 0;
      }
    }
    
    .sku-row {
      .sku-code {
        font-size: 12px;
        color: #666;
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        margin-right: 8px;
      }
      
      .stock-info {
        display: flex;
        align-items: center;
        font-size: 12px;
        color: #999;
        flex-shrink: 0;
        
        :deep(.van-icon) {
          margin-right: 2px;
        }
        
        &.warning {
          color: #ff4444;
          font-weight: 500;
        }
      }
    }
    
    .spec-row {
      .sku-spec {
        font-size: 12px;
        color: #888;
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        margin-right: 8px;
      }
      
      .warehouse-info {
        display: flex;
        align-items: center;
        font-size: 12px;
        color: #999;
        flex-shrink: 0;
        
        :deep(.van-icon) {
          margin-right: 2px;
        }
      }
    }
  }
}

/* 响应式调整 */
@media (max-width: 320px) {
  .sku-card {
    padding: 8px;
    
    .sku-image {
      width: 50px;
      height: 50px;
      margin-right: 8px;
    }
    
    .sku-info {
      .product-row {
        .product-name {
          font-size: 14px;
        }
        
        .sku-price {
          font-size: 14px;
        }
      }
    }
  }
}
</style>