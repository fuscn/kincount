<template>
  <div class="product-page">
    <!-- 头部 -->
    <van-nav-bar title="商品列表" fixed placeholder>
      <template #right>
        <van-button size="small" type="primary" @click="handleCreateProduct" v-perm="PERM.PRODUCT_ADD">
          新增商品
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 搜索 -->
    <SearchBar placeholder="搜索商品名称/编号" @search="handleSearch" @clear="handleClearSearch" />
    <!-- 筛选条件区域 - 精简一行布局 -->
    <div class="filter-row">
      <!-- 分类选择 - 只允许选择叶子节点 -->
      <div class="filter-item">
        <CategorySelect
          v-model="filters.category_id"
          :placeholder="selectedCategoryText"
          :allow-select-parent="false"  
          :auto-close="true"
          :button-size="'small'"
          @change="handleFilterChange"
        />
      </div>

      <!-- 品牌选择 -->
      <div class="filter-item">
        <BrandSelect
          v-model="filters.brand_id"
          :placeholder="selectedBrandText"
          :only-enabled="true"
          :allow-disabled="false"
          :trigger-button-size="'small'"
          @change="handleFilterChange"
        />
      </div>

      <!-- 重置按钮 -->
      <div class="filter-reset">
        <van-button 
          size="small" 
          type="default" 
          plain
          @click="handleResetFilters"
          :disabled="!hasActiveFilters"
          class="reset-btn"
        >
          重置
        </van-button>
      </div>
    </div>

    <!-- 商品聚合列表 -->
    <van-pull-refresh v-model="refreshing" @refresh="onRefresh" :disabled="initialLoading">
      <van-list 
        v-model:loading="listLoading" 
        :finished="finished" 
        :immediate-check="false"
        :finished-text="productList.length === 0 ? '暂无商品数据' : '没有更多了'" 
        @load="onLoad"
      >
        <van-swipe-cell v-for="product in productList" :key="product.id">
          <ProductCard 
            :name="product.name"
            :productNo="product.product_no" 
            :unit="product.unit" 
            :costPrice="product.cost_price"
            :salePrice="product.sale_price" 
            :minStock="product.min_stock" 
            :category="product.category"
            :brand="product.brand" 
            :productId="product.id"
            :totalStock="product.total_stock"
            @click="handleProductItemClick"
          />
          <template #right>
            <van-button 
              square 
              type="primary" 
              text="编辑" 
              class="edit-button"
              @click="handleEditProduct(product)"
            />
          </template>
        </van-swipe-cell>

        <van-empty 
          v-if="!listLoading && !refreshing && productList.length === 0" 
          description="暂无商品数据"
          image="search"
        />
      </van-list>
    </van-pull-refresh>

    <!-- 首次加载遮罩 -->
    <van-loading v-if="initialLoading" class="page-loading" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick, watch } from 'vue'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import { useProductStore } from '@/store/modules/product'
import { useCategoryStore } from '@/store/modules/category'
import { useBrandStore } from '@/store/modules/brand'
import { PERM } from '@/constants/permissions'
import SearchBar from '@/components/common/SearchBar.vue'
import ProductCard from '@/components/business/ProductCard.vue'
import CategorySelect from '@/components/business/CategorySelect.vue'
import BrandSelect from '@/components/business/BrandSelect.vue'

/* -------------------- 路由 & 状态 -------------------- */
const router = useRouter()
const productStore = useProductStore()
const categoryStore = useCategoryStore()
const brandStore = useBrandStore()

// 使用计算属性获取状态
const productList = computed(() => productStore.productList)
const productTotal = computed(() => productStore.productTotal)

/* -------------------- 筛选条件 -------------------- */
const filters = reactive({
  keyword: '',
  category_id: null,
  brand_id: null,
  page: 1,
  limit: 15
})

// 计算选中项的显示文本
const selectedCategoryText = computed(() => {
  if (!filters.category_id) return '分类'
  
  const category = categoryStore.list.find(item => item.id === filters.category_id)
  return category ? (category.name.length > 4 ? category.name.substring(0, 4) + '...' : category.name) : '分类'
})

const selectedBrandText = computed(() => {
  if (!filters.brand_id) return '品牌'
  
  const brand = brandStore.list.find(item => item.id === filters.brand_id)
  return brand ? (brand.name.length > 4 ? brand.name.substring(0, 4) + '...' : brand.name) : '品牌'
})

// 检查是否有活跃的筛选条件
const hasActiveFilters = computed(() => {
  return filters.category_id !== null || 
         filters.brand_id !== null ||
         filters.keyword !== ''
})

/* -------------------- 列表状态 -------------------- */
const initialLoading = ref(false)
const listLoading = ref(false)
const refreshing = ref(false)
const finished = ref(false)
const isLoading = ref(false) // 加载锁，防止重复请求

/* -------------------- 商品列表加载 -------------------- */
const loadProductList = async (isRefresh = false) => {
  // 加锁，防止重复请求
  if (isLoading.value) return
  isLoading.value = true
  
  // 设置加载状态
  if (isRefresh) {
    filters.page = 1
    finished.value = false
    // 如果不是初始加载状态，才显示下拉刷新动画
    if (!initialLoading.value) {
      refreshing.value = true
    }
  } else {
    // 如果已经完成加载，不再请求
    if (finished.value) {
      isLoading.value = false
      return
    }
    // 初始加载期间不显示列表加载动画
    if (!initialLoading.value) {
      listLoading.value = true
    }
  }

  try {
    // 准备请求参数
    const params = {
      page: filters.page,
      limit: filters.limit,
      keyword: filters.keyword,
      category_id: filters.category_id,
      brand_id: filters.brand_id
    }

    // 调用状态管理中的加载方法
    await productStore.loadProductList(params, isRefresh)

    // 检查是否还有更多数据
    const currentTotal = productTotal.value
    const currentList = productList.value
    
    // 如果当前列表长度小于限制，或者总条数小于等于当前列表长度，说明没有更多数据了
    if (currentList.length < filters.limit || currentList.length >= currentTotal) {
      finished.value = true
    } else {
      // 准备下一页
      if (!isRefresh) {
        filters.page++
      }
    }
  } catch (error) {
    console.error('加载商品列表失败:', error)
    showToast(error.message || '加载商品列表失败')
    finished.value = true
  } finally {
    refreshing.value = false
    listLoading.value = false
    isLoading.value = false
  }
}

/* -------------------- 分离刷新和加载事件 -------------------- */
const onRefresh = () => {
  loadProductList(true)
}

const onLoad = () => {
  loadProductList(false)
}

/* -------------------- 手动触发初始加载 -------------------- */
const initLoad = async () => {
  initialLoading.value = true
  try {
    // 只加载商品列表数据，分类和品牌数据由组件自己请求
    await loadProductList(true)
  } catch (error) {
    console.error('初始化失败:', error)
    showToast('初始化失败')
  } finally {
    // 使用nextTick确保状态更新后再关闭初始加载
    await nextTick()
    initialLoading.value = false
  }
}

/* -------------------- 事件处理 -------------------- */
const handleSearch = (kw) => {
  filters.keyword = kw
  nextTick(() => loadProductList(true))
}

const handleClearSearch = () => {
  filters.keyword = ''
  nextTick(() => loadProductList(true))
}

const handleFilterChange = () => {
  nextTick(() => loadProductList(true))
}

const handleResetFilters = () => {
  filters.category_id = null
  filters.brand_id = null
  filters.keyword = ''
  
  nextTick(() => loadProductList(true))
}

const handleProductItemClick = (product) => {
  // 跳转到商品SKU列表页
  router.push(`/product/${product.id}/skus`)
}

const handleCreateProduct = () => {
  router.push('/product/create')
}

const handleEditProduct = (product) => {
  router.push(`/product/edit/${product.id}`)
}

/* -------------------- 监听筛选条件变化 -------------------- */
watch(
  () => [filters.category_id, filters.brand_id],
  () => {
    // 筛选条件变化时重置页码
    filters.page = 1
  }
)

/* -------------------- 生命周期 -------------------- */
onMounted(() => {
  // 手动触发初始加载
  initLoad()
})
</script>

<style scoped lang="scss">
.product-page {
  background: #f7f8fa;
  min-height: 100vh;
  padding-top: 46px; // 适配fixed导航栏

  .filter-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background-color: #fff;
    border-bottom: 1px solid #ebedf0;
    height: 48px; // 固定高度，避免太高

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

  .van-list {
    padding: 12px;
    
    .van-swipe-cell {
      margin-bottom: 12px;
      
      &:last-child {
        margin-bottom: 0;
      }
      
      .edit-button {
        height: 100%;
        min-width: 80px;
      }
    }
  }
}

.page-loading {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}

// 响应式调整
@media (max-width: 480px) {
  .product-page {
    .filter-row {
      gap: 6px;
      padding: 6px 10px;
      height: 44px;
      
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

@media (max-width: 320px) {
  .product-page {
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
</style>