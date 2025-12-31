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
          <!-- 筛选条件行 -->
          <div class="filter-row">
            <!-- 分类选择 -->
            <div class="filter-item">
              <CategorySelect v-model="filterCategory" :placeholder="'分类'" :title="'选择分类'" :button-type="'default'"
                :button-size="'small'" :button-block="false" :show-search="true" :allow-select-parent="false"
                :auto-close="true" @change="handleCategoryChange" />
            </div>

            <!-- 品牌选择 -->
            <div class="filter-item">
              <BrandSelect v-model="filterBrand" :placeholder="'品牌'" :popup-title="'选择品牌'"
                :trigger-button-type="'default'" :trigger-button-size="'small'" :trigger-button-block="false"
                :return-object="false" @change="handleBrandChange" />
            </div>

            <!-- 重置按钮 -->
            <div class="filter-reset">
              <van-button size="small" type="default" plain @click="handleResetFilters" :disabled="!hasActiveFilters"
                class="reset-btn">
                重置
              </van-button>
            </div>
          </div>
        </div>
      </div>

      <!-- SKU列表 -->
      <div class="sku-list-container" ref="listContainerRef"
        @scroll="onScroll"
        style="height: 600px; overflow-y: auto;">
        <van-checkbox-group v-model="selectedSkuIds">
          <van-cell v-for="sku in skuList" :key="sku.id" @click="toggleSkuSelection(sku)">
            <template #title>
              <div class="sku-title">
                <span class="product-name">{{ getProductName(sku) }}</span>
                <span class="sku-spec-text" v-if="getSpecText(sku)">{{ getSpecText(sku) }}</span>
              </div>
            </template>
            <template #label>
              <div class="sku-info">
                <div class="sku-code">{{ sku.sku_code }}</div>
                <div class="sku-details">
                  <span class="stock" :class="getStockClass(sku.stock || 0)">
                    库存: {{ sku.stock_quantity || 0 }}
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
        </van-checkbox-group>
        <!-- 加载状态 -->
        <div v-if="loading" class="loading-text"><van-loading size="20" /> 正在加载中...</div>
        <div v-if="finished && skuList.length > 0" class="finished-text">没有更多数据了</div>
        <div v-if="!loading && !finished && skuList.length === 0" class="finished-text">暂无商品数据</div>
      </div>

      <!-- 底部操作区域 -->
      <div class="sku-select-footer" v-if="showFooter">
        <div class="selected-info">
          已选择 {{ selectedSkuIds.length }} 个商品
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

// 导入组件
import CategorySelect from './CategorySelect.vue'
import BrandSelect from './BrandSelect.vue'

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

// 选中的SKU
const selectedSkuIds = ref([...props.modelValue])
const selectedSkuData = ref([])

// 递归更新防护
let recursionGuard = false

// 检查是否有活跃的筛选条件
const hasActiveFilters = computed(() => {
  return filterCategory.value !== '' ||
    filterBrand.value !== '' ||
    searchKeyword.value !== ''
})

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

// 自定义滚动监听
const handleScroll = (e) => {
  const { scrollTop, scrollHeight, clientHeight } = e.target
  if (scrollHeight - scrollTop - clientHeight <= 50 && !loading.value && !finished.value) {
    loadMoreSkus()
  }
}

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

// 滚动容器引用
const listContainerRef = ref(null)
// 滚动防抖定时器
let lastScrollTime = 0
// 自定义滚动事件处理
const onScroll = (event) => {
  const now = Date.now()
  if (now - lastScrollTime > 100) { // 防抖间隔100ms
    const target = event.target
    const { scrollTop, scrollHeight, clientHeight } = target
    lastScrollTime = now
    // 当滚动到底部附近（距离100px）时加载更多
    if (scrollHeight - scrollTop - clientHeight <= 100 && !loading.value && !finished.value) {
      loadMoreSkus()
    }
  }
}

// 初始化
onMounted(() => {
  if (props.autoLoad) {
    loadInitialData()
  }
})

// 加载初始数据
const loadInitialData = async () => {
  currentPage.value = 1
  finished.value = false
  productStore.skuSelectOptions = []
  await loadMoreSkus()
}

// 加载SKU列表
const loadMoreSkus = async (paramsOverride = {}) => {
  // 防止重复请求
  if (loading.value || finished.value) {
      return
    }
  loading.value = true
  try {
    // 构建请求参数
    const params = {
      keyword: searchKeyword.value || '',
      category_id: paramsOverride.category_id !== undefined ? paramsOverride.category_id : (filterCategory.value || ''),
      brand_id: paramsOverride.brand_id !== undefined ? paramsOverride.brand_id : (filterBrand.value || ''),
      page: currentPage.value,
      limit: 20
    }
    // 执行API调用
    const res = await searchSkuSelect(params)
    let newData = []
    // 处理响应数据
    if (res && (res.code === 200 || res.code === 0) && res.data) {
      newData = res.data
    }
    // 更新数据列表
    if (currentPage.value === 1) {
      productStore.skuSelectOptions = newData
    } else {
      productStore.skuSelectOptions = [...productStore.skuSelectOptions, ...newData]
    }
    // 初始化选中数据
    initSelectedData()
    // 更新分页状态
    if (newData.length < 20) {
      finished.value = true
    } else {
      currentPage.value++
    }
    // 触发自定义事件
    emit('load-more', {
      page: currentPage.value,
      list: newData,
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
    loading.value = false // 强制重置加载状态
    loadMoreSkus()
  })
}

// 分类选择变化
const handleCategoryChange = (categoryId) => {
  filterCategory.value = categoryId || ''
  currentPage.value = 1
  finished.value = false
  productStore.skuSelectOptions = []
  loading.value = false // 强制重置加载状态
  loadMoreSkus({ category_id: categoryId })
}

// 品牌选择变化
const handleBrandChange = (brandId) => {
  filterBrand.value = brandId || ''
  currentPage.value = 1
  finished.value = false
  productStore.skuSelectOptions = []
  loading.value = false // 强制重置加载状态
  loadMoreSkus({ brand_id: brandId })
}

// 筛选条件变化
const handleFilterChange = (...args) => {
  safeUpdate(() => {
    currentPage.value = 1
    finished.value = false
    productStore.skuSelectOptions = []
    loadMoreSkus()
  })
}

// 重置筛选条件
const handleResetFilters = () => {
  safeUpdate(() => {
    searchKeyword.value = ''
    filterCategory.value = ''
    filterBrand.value = ''
    currentPage.value = 1
    finished.value = false
    productStore.skuSelectOptions = []
    loading.value = false // 强制重置加载状态
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
  padding-top: 0;
  margin-top: 0;

  :deep(.van-search) {
    --van-search-content-background-color: #f7f8fa;
    --van-search-padding: 8px 16px;
    --van-search-input-height: 36px;
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    display: block !important;
    box-sizing: border-box !important;

    // 确保搜索框所有内部元素都完全铺满宽度
    .van-search__content,
    .van-field,
    .van-field__body,
    .van-field__control,
    .van-search__field-wrap {
      width: 100% !important;
      max-width: 100% !important;
      box-sizing: border-box !important;
      flex: 1 !important;
    }
  }

  .filter-section {
    padding: 8px 12px;
    background: #f7f8fa;

    .filter-row {
      display: flex;
      align-items: center;
      gap: 8px;

      .filter-item {
        flex: 1;
        min-width: 0; // 防止内容溢出

        // 自定义选择组件样式
        :deep(.van-button) {
          width: 100%;
          height: 32px;
          padding: 0 8px;
          font-size: 13px;

          .van-button__text {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: calc(100% - 16px);
          }

          .van-icon {
            margin-left: 2px;
            flex-shrink: 0;
            font-size: 14px;
          }
        }
      }

      .filter-reset {
        flex-shrink: 0;

        .reset-btn {
          height: 32px;
          min-width: 60px;
          padding: 0 12px;
          font-size: 13px;

          &:disabled {
            opacity: 0.5;
            cursor: not-allowed;
          }
        }
      }
    }
  }
}

/* SKU列表区域 */
.sku-list-container {
    height: 600px;
    overflow-y: auto;
}

/* 加载状态样式 */
.loading-text,
.finished-text {
  text-align: center;
  padding: 1rem;
  color: #999;
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

.sku-spec-text {
  color: #646566;
  font-size: 13px;
  font-weight: normal;
}

.sku-info {
  font-size: 12px;
}

.sku-code {
  color: #646566;
  font-size: 13px;
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

// 响应式调整
@media (max-width: 480px) {
  .sku-select-header {
    .filter-section {
      .filter-row {
        gap: 6px;
        padding: 6px 10px;

        .filter-item {
          :deep(.van-button) {
            height: 30px;
            font-size: 12px;
            padding: 0 6px;
          }
        }

        .filter-reset {
          .reset-btn {
            height: 30px;
            min-width: 54px;
            padding: 0 8px;
            font-size: 12px;
          }
        }
      }
    }
  }
}

@media (max-width: 320px) {
  .sku-select-header {
    .filter-section {
      .filter-row {
        gap: 4px;
        padding: 4px 8px;

        .filter-item {
          :deep(.van-button) {
            font-size: 11px;
            padding: 0 4px;
          }
        }

        .filter-reset {
          .reset-btn {
            min-width: 48px;
            padding: 0 6px;
            font-size: 11px;
          }
        }
      }
    }
  }
}
</style>