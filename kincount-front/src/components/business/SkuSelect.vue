<!-- src/components/business/SkuSelect.vue -->
<template>
  <div class="sku-select-component">
    <!-- 顶部导航栏 - 修改为相对定位 -->
    <div class="nav-bar-container" v-if="showHeader">
      <van-nav-bar :title="headerTitle" left-text="取消" right-text="确定" @click-left="handleCancel"
        @click-right="handleConfirm" />
    </div>

    <div class="sku-content" :class="{ 'with-header': showHeader }">
      <!-- 搜索和筛选区域 -->
      <div class="sku-select-header">
        <van-search v-model="searchKeyword" placeholder="搜索商品编码、名称或规格" @update:model-value="handleSearch"
          @search="handleSearch" />
        <div class="filter-section" v-if="showFilters">
          <van-dropdown-menu>
            <van-dropdown-item v-model="filterCategory" :options="categoryOptions" @change="handleFilterChange" />
            <van-dropdown-item v-model="filterBrand" :options="brandOptions" @change="handleFilterChange" />
          </van-dropdown-menu>
        </div>
      </div>

      <!-- SKU列表 -->
      <div class="sku-list-container">
        <van-checkbox-group v-model="selectedSkuIds">
          <van-list v-model:loading="loading" :finished="finished"
            :finished-text="skuList.length === 0 ? '暂无商品数据' : '没有更多商品了'" @load="loadMoreSkus">
            <van-cell v-for="sku in skuList" :key="sku.id" @click="toggleSkuSelection(sku)">
              <template #title>
                <div class="sku-title">
                  <span class="product-name">{{ getProductName(sku) }}</span>
                  <span class="sku-code">{{ sku.sku_code }}</span>
                </div>
              </template>
              <template #label>
                <div class="sku-info">
                  <div class="sku-spec" v-if="getSpecText(sku)">规格: {{ getSpecText(sku) }}</div>
                  <div class="sku-details">
                    <span class="stock" :class="getStockClass(sku.stock || 0)">
                      库存: {{ sku.stock || 0 }}
                    </span>
                    <span class="cost-price" v-if="sku.cost_price">成本: ¥{{ sku.cost_price }}</span>
                    <span class="sale-price" v-if="sku.sale_price">售价: ¥{{ sku.sale_price }}</span>
                  </div>
                </div>
              </template>
              <template #right-icon>
                <van-checkbox :name="sku.id" />
              </template>
            </van-cell>
          </van-list>
        </van-checkbox-group>
      </div>

      <!-- 底部操作区域 -->
      <div class="sku-select-footer" v-if="showFooter">
        <div class="selected-info">
          已选择 {{ selectedSkuIds.length }} 个商品
        </div>
        <div class="action-buttons">
          <van-button type="default" @click="handleCancel">取消</van-button>
          <van-button type="primary" @click="handleConfirm">确定</van-button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { showToast } from 'vant'
import { useProductStore } from '@/store/modules/product'
import { useCategoryStore } from '@/store/modules/category'
import { useBrandStore } from '@/store/modules/brand'
import { searchSkuSelect } from '@/api/product'

// 定义 props
const props = defineProps({
  // 选中的SKU ID列表
  modelValue: {
    type: Array,
    default: () => []
  },
  // 是否多选
  multiple: {
    type: Boolean,
    default: true
  },
  // 是否显示筛选条件
  showFilters: {
    type: Boolean,
    default: true
  },
  // 是否显示底部操作栏
  showFooter: {
    type: Boolean,
    default: true
  },
  // 是否显示顶部导航栏
  showHeader: {
    type: Boolean,
    default: false
  },
  // 导航栏标题
  headerTitle: {
    type: String,
    default: '选择商品'
  },
  // 是否自动加载数据
  autoLoad: {
    type: Boolean,
    default: true
  }
})

// 定义 emits
const emit = defineEmits([
  'update:modelValue',
  'confirm',
  'cancel',
  'change',
  'load-more'
])

// 状态管理
const productStore = useProductStore()
const categoryStore = useCategoryStore()
const brandStore = useBrandStore()

// 响应式数据
const searchKeyword = ref('')
const loading = ref(false)
const finished = ref(false)
const currentPage = ref(1)

// 筛选条件
const filterCategory = ref('')
const filterBrand = ref('')
const categoryOptions = ref([{ text: '全部分类', value: '' }])
const brandOptions = ref([{ text: '全部品牌', value: '' }])

// 选中的SKU
const selectedSkuIds = ref([...props.modelValue])
const selectedSkuData = ref([])

// 递归更新防护
let recursionGuard = false

// 安全更新函数
const safeUpdate = (callback) => {
  if (recursionGuard) return
  recursionGuard = true
  try {
    callback()
  } finally {
    setTimeout(() => {
      recursionGuard = false
    }, 0)
  }
}

// 计算属性
const skuList = computed(() => productStore.skuSelectOptions || [])

// 初始化选中数据
const initSelectedData = () => {
  selectedSkuData.value = selectedSkuIds.value.map(id => {
    return skuList.value.find(sku => sku.id === id)
  }).filter(Boolean)
}

// 修复监听逻辑 - 避免循环更新
watch(() => props.modelValue, (newVal) => {
  safeUpdate(() => {
    if (JSON.stringify(newVal) !== JSON.stringify(selectedSkuIds.value)) {
      selectedSkuIds.value = [...newVal]
      initSelectedData() // 同步初始化选中数据
    }
  })
}, { immediate: true })

// 简化 selectedSkuIds 的 watch，避免循环
watch(selectedSkuIds, (newVal, oldVal) => {
  safeUpdate(() => {
    // 只在真正变化时触发更新
    if (JSON.stringify(newVal) !== JSON.stringify(oldVal)) {
      emit('update:modelValue', newVal)
      // 更新 selectedSkuData
      selectedSkuData.value = newVal.map(id => {
        return skuList.value.find(sku => sku.id === id) ||
          selectedSkuData.value.find(sku => sku.id === id)
      }).filter(Boolean)

      emit('change', {
        selectedIds: newVal,
        selectedData: selectedSkuData.value
      })
    }
  })
}, { deep: true })

// 获取商品名称
const getProductName = (sku) => {
  if (sku.product && sku.product.name) {
    return sku.product.name
  }
  // 如果product为null，尝试从sku的其他字段获取名称
  return sku.product_name || sku.name || '未知商品'
}

// 获取规格文本
const getSpecText = (sku) => {
  if (sku.spec_text) {
    return sku.spec_text
  }
  if (sku.spec && typeof sku.spec === 'object') {
    // 将规格对象转换为字符串
    return Object.entries(sku.spec)
      .map(([key, value]) => `${key}:${value}`)
      .join(' ')
  }
  return ''
}

// 初始化
onMounted(() => {
  if (props.autoLoad) {
    loadInitialData()
  }
  if (props.showFilters) {
    loadCategories()
    loadBrands()
  }
})

// 加载初始数据
const loadInitialData = async () => {
  currentPage.value = 1
  finished.value = false
  productStore.skuSelectOptions = []
  await loadMoreSkus()
}

// 加载分类选项
const loadCategories = async () => {
  try {
    const res = await categoryStore.loadList({ page: 1, limit: 100 })
    
    
    // 适配标准响应结构：从 res.data 中获取数据
    let categories = []
    if (res && res.code === 200) {
      // 标准响应结构
      if (Array.isArray(res.data)) {
        categories = res.data
      } else if (res.data && res.data.list && Array.isArray(res.data.list)) {
        // 如果 data 是分页对象
        categories = res.data.list
      }
    } else if (Array.isArray(res)) {
      // 兼容直接返回数组的情况
      categories = res
    } else if (res && res.list) {
      // 兼容旧结构
      categories = res.list
    }
    
    
    categoryOptions.value = [
      { text: '全部分类', value: '' },
      ...categories.map(item => ({
        text: item.name,
        value: item.id
      }))
    ]
  } catch (error) {
  }
}

// 加载品牌选项
const loadBrands = async () => {
  try {
    const res = await brandStore.loadList({ page: 1, limit: 100 })
    
    
    // 适配标准响应结构：从 res.data 中获取数据
    let brands = []
    if (res && res.code === 200) {
      // 标准响应结构
      if (Array.isArray(res.data)) {
        brands = res.data
      } else if (res.data && res.data.list && Array.isArray(res.data.list)) {
        // 如果 data 是分页对象
        brands = res.data.list
      }
    } else if (Array.isArray(res)) {
      // 兼容直接返回数组的情况
      brands = res
    } else if (res && res.list) {
      // 兼容旧结构
      brands = res.list
    }
    
    
    brandOptions.value = [
      { text: '全部品牌', value: '' },
      ...brands.map(item => ({
        text: item.name,
        value: item.id
      }))
    ]
  } catch (error) {
  }
}

// 加载SKU列表
const loadMoreSkus = async () => {
  if (loading.value || finished.value) return
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      limit: 20,
      keyword: searchKeyword.value,
      category_id: filterCategory.value,
      brand_id: filterBrand.value
    }
    const res = await searchSkuSelect(params)
    
    
    // 适配标准响应结构：从 res.data 中获取数据
    let list = []
    if (res && res.code === 200) {
      // 标准响应结构
      if (Array.isArray(res.data)) {
        list = res.data
      } else if (res.data && res.data.list && Array.isArray(res.data.list)) {
        // 如果 data 是分页对象
        list = res.data.list
      }
    } else if (Array.isArray(res)) {
      // 兼容直接返回数组的情况
      list = res
    } else if (res && res.list) {
      // 兼容旧结构
      list = res.list
    }
    
    
    if (currentPage.value === 1) {
      productStore.skuSelectOptions = list
    } else {
      productStore.skuSelectOptions = [...productStore.skuSelectOptions, ...list]
    }
    
    // 重新初始化选中数据，确保新加载的数据也能正确匹配
    initSelectedData()
    
    // 检查是否还有更多数据
    if (list.length < 20) {
      finished.value = true
    } else {
      currentPage.value++
    }
    emit('load-more', {
      page: currentPage.value,
      list: list,
      finished: finished.value
    })
  } catch (error) {
    showToast('加载商品失败')
  } finally {
    loading.value = false
  }
}
// 搜索处理
const handleSearch = () => {
  safeUpdate(() => {
    currentPage.value = 1
    finished.value = false
    productStore.skuSelectOptions = []
    loadMoreSkus()
  })
}

// 筛选条件变化
const handleFilterChange = () => {
  safeUpdate(() => {
    currentPage.value = 1
    finished.value = false
    productStore.skuSelectOptions = []
    loadMoreSkus()
  })
}

// 切换SKU选择
const toggleSkuSelection = (sku) => {
  safeUpdate(() => {
    if (!props.multiple) {
      // 单选模式
      selectedSkuIds.value = [sku.id]
      selectedSkuData.value = [sku]
    } else {
      // 多选模式
      const index = selectedSkuIds.value.indexOf(sku.id)
      if (index > -1) {
        // 取消选中
        selectedSkuIds.value.splice(index, 1)
        selectedSkuData.value.splice(index, 1)
      } else {
        // 新增选中
        selectedSkuIds.value.push(sku.id)
        selectedSkuData.value.push(sku)
      }
    }

    // 触发更新
    emit('update:modelValue', selectedSkuIds.value)
    emit('change', {
      selectedIds: selectedSkuIds.value,
      selectedData: selectedSkuData.value
    })
  })
}

// 获取库存样式类
const getStockClass = (stock) => {
  if (stock === 0) {
    return 'out-of-stock'
  } else if (stock < 10) {
    return 'low-stock'
  }
  return ''
}

// 确认选择
const handleConfirm = () => {
  safeUpdate(() => {
    if (selectedSkuIds.value.length === 0) {
      showToast('请选择商品')
      return
    }
    emit('confirm', {
      selectedIds: selectedSkuIds.value,
      selectedData: selectedSkuData.value
    })
  })
}

// 取消选择
const handleCancel = () => {
  safeUpdate(() => {
    emit('cancel')
  })
}

// 外部方法：重置组件
const reset = () => {
  safeUpdate(() => {
    searchKeyword.value = ''
    filterCategory.value = ''
    filterBrand.value = ''
    selectedSkuIds.value = []
    selectedSkuData.value = []
    currentPage.value = 1
    finished.value = false
    productStore.skuSelectOptions = []
  })
}

// 外部方法：重新加载
const reload = () => {
  safeUpdate(() => {
    reset()
    loadInitialData()
  })
}

// 暴露方法给父组件
defineExpose({
  reset,
  reload,
  loadMoreSkus,
  getSelectedData: () => selectedSkuData.value,
  selectedSkuIds: () => selectedSkuIds.value,
  skuList: () => skuList.value
})
</script>

<style scoped lang="scss">
.sku-select-component {
  height: 100%;
  display: flex;
  flex-direction: column;
  background: #f7f8fa;
  position: relative;
  /* 重要：为内部绝对定位元素提供参考 */
}

/* 导航栏容器 - 使用绝对定位在组件内部 */
.nav-bar-container {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  z-index: 1000;
  background: white;

  /* 确保导航栏在弹出层内部 */
  :deep(.van-nav-bar) {
    position: relative;
    /* 改为相对定位 */
    background: white;
  }
}

.sku-content {
  height: 100%;
  display: flex;
  flex-direction: column;

  &.with-header {
    padding-top: 46px; // 为导航栏留出空间
  }
}

.sku-select-header {
  background: white;
  margin-bottom: 8px;

  .filter-section {
    :deep(.van-dropdown-menu) {
      box-shadow: none;
    }
  }
}

.sku-list-container {
  flex: 1;
  overflow: hidden;
}

.sku-select-footer {
  background: white;
  border-top: 1px solid #ebedf0;

  .selected-info {
    padding: 12px 16px;
    text-align: center;
    color: #969799;
    font-size: 14px;
  }

  .action-buttons {
    display: flex;
    gap: 12px;
    padding: 16px;

    .van-button {
      flex: 1;
    }
  }
}

.sku-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
  flex-wrap: wrap;
}

.product-name {
  font-weight: bold;
  color: #323233;
  font-size: 14px;
}

.sku-code {
  color: #646566;
  font-size: 13px;
  font-weight: normal;
}

.sku-info {
  font-size: 12px;
}

.sku-spec {
  color: #646566;
  margin-bottom: 4px;
}

.sku-details {
  display: flex;
  gap: 12px;
  color: #969799;
  flex-wrap: wrap;
}

.stock {
  color: #07c160;
}

.stock.low-stock {
  color: #ff976a;
  font-weight: bold;
}

.stock.out-of-stock {
  color: #ee0a24;
  font-weight: bold;
}

.cost-price {
  color: #ff976a;
}

.sale-price {
  color: #ee0a24;
}

:deep(.van-list) {
  height: 100%;
  overflow-y: auto;
}

:deep(.van-cell) {
  padding: 12px 16px;

  .van-cell__value {
    display: flex;
    align-items: center;
  }
}
</style>