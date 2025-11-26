<!-- src/components/business/SkuSelect.vue -->
<template>
  <div class="sku-select-component">
    <!-- 搜索和筛选区域 -->
    <div class="sku-select-header">
      <van-search
        v-model="searchKeyword"
        placeholder="搜索商品编码、名称或规格"
        @update:model-value="handleSearch"
        @search="handleSearch"
      />
      <div class="filter-section" v-if="showFilters">
        <van-dropdown-menu>
          <van-dropdown-item 
            v-model="filterCategory" 
            :options="categoryOptions" 
            @change="handleFilterChange"
          />
          <van-dropdown-item 
            v-model="filterBrand" 
            :options="brandOptions" 
            @change="handleFilterChange"
          />
        </van-dropdown-menu>
      </div>
    </div>
    <!-- SKU列表 -->
    <div class="sku-list-container">
      <van-checkbox-group v-model="selectedSkuIds">
        <van-list
          v-model:loading="loading"
          :finished="finished"
          :finished-text="skuList.length === 0 ? '暂无商品数据' : '没有更多商品了'"
          @load="loadMoreSkus"
        >
          <van-cell
            v-for="sku in skuList"
            :key="sku.id"
            @click="toggleSkuSelection(sku)"
          >
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
    <!-- 底部操作区域（可选） -->

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
  // 是否显示操作按钮
  showActions: {
    type: Boolean,
    default: false
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
const selectedSkuIds = ref([])
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

// 监听选中状态变化 - 修复递归问题
watch(selectedSkuIds, (newVal) => {
  safeUpdate(() => {
    emit('update:modelValue', newVal)
    emit('change', {
      selectedIds: newVal,
      selectedData: selectedSkuData.value
    })
  })
}, { deep: true })

watch(() => props.modelValue, (newVal) => {
  safeUpdate(() => {
    selectedSkuIds.value = [...newVal]
  })
}, { immediate: true })

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
    const categories = res.list || []
    categoryOptions.value = [
      { text: '全部分类', value: '' },
      ...categories.map(item => ({
        text: item.name,
        value: item.id
      }))
    ]
  } catch (error) {
    console.error('加载分类失败:', error)
  }
}

// 加载品牌选项
const loadBrands = async () => {
  try {
    const res = await brandStore.loadList({ page: 1, limit: 100 })
    const brands = res.list || []
    brandOptions.value = [
      { text: '全部品牌', value: '' },
      ...brands.map(item => ({
        text: item.name,
        value: item.id
      }))
    ]
  } catch (error) {
    console.error('加载品牌失败:', error)
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
    const list = res.list || res || []
    if (currentPage.value === 1) {
      productStore.skuSelectOptions = list
    } else {
      productStore.skuSelectOptions = [...productStore.skuSelectOptions, ...list]
    }
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
    console.error('加载SKU列表失败:', error)
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
        selectedSkuIds.value.splice(index, 1)
        selectedSkuData.value = selectedSkuData.value.filter(s => s.id !== sku.id)
      } else {
        selectedSkuIds.value.push(sku.id)
        selectedSkuData.value.push(sku)
      }
    }
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
  getSelectedData: () => selectedSkuData.value
})
</script>

<style scoped lang="scss">
.sku-select-component {
  height: 100%;
  display: flex;
  flex-direction: column;
  background: #f7f8fa;
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