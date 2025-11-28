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
    <SearchBar 
      placeholder="搜索商品名称/编号" 
      @search="handleSearch" 
      @clear="handleClearSearch" 
    />

    <!-- 下拉筛选 -->
    <van-dropdown-menu>
      <van-dropdown-item 
        v-model="filters.category_id" 
        :options="categoryOptions" 
        @change="handleFilterChange" 
      />
      <van-dropdown-item 
        v-model="filters.brand_id" 
        :options="brandOptions" 
        @change="handleFilterChange" 
      />
      <van-dropdown-item 
        v-model="filters.warehouse_id" 
        :options="warehouseOptions" 
        @change="handleFilterChange" 
      />
    </van-dropdown-menu>

    <!-- 商品聚合列表 -->
    <van-pull-refresh v-model="refreshing" @refresh="loadProductList(true)">
      <van-list
        v-model:loading="listLoading"
        :finished="finished"
        :finished-text="productStore.productList.length === 0 ? '暂无商品数据' : '没有更多了'"
        @load="loadProductList"
      >
        <ProductCard 
          v-for="product in productStore.productList" 
          :key="product.id"
          :name="product.name"
          :productNo="product.product_no"
          :unit="product.unit"
          :costPrice="product.cost_price"
          :salePrice="product.sale_price"
          :minStock="product.min_stock"
          :images="product.images"
          :category="product.category"
          :brand="product.brand"
          :productId="product.id"
          @click="handleProductItemClick(product)"
        />

        <van-empty
          v-if="!listLoading && !refreshing && productStore.productList.length === 0"
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
import { ref, reactive, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import { useProductStore } from '@/store/modules/product'
import { getCategoryOptions } from '@/api/category'
import { getBrandOptions } from '@/api/brand'
import { getWarehouseOptions } from '@/api/warehouse'
import { PERM } from '@/constants/permissions'
import SearchBar from '@/components/common/SearchBar.vue'
import ProductCard from '@/components/business/ProductCard.vue'

/* -------------------- 路由 & 状态 -------------------- */
const router = useRouter()
const productStore = useProductStore()

/* -------------------- 筛选条件 -------------------- */
const filters = reactive({
  keyword: '',
  category_id: '',
  brand_id: '',
  warehouse_id: '',
  page: 1,
  limit: 15
})

const categoryOptions = ref([{ text: '全部分类', value: '' }])
const brandOptions = ref([{ text: '全部品牌', value: '' }])
const warehouseOptions = ref([{ text: '全部仓库', value: '' }])

/* -------------------- 列表状态 -------------------- */
const initialLoading = ref(false)
const listLoading = ref(false)
const refreshing = ref(false)
const finished = ref(false)

/* -------------------- 下拉数据加载 -------------------- */
const loadFilterOptions = async () => {
  try {
    const [cat, brd, wh] = await Promise.all([
      getCategoryOptions(),
      getBrandOptions(),
      getWarehouseOptions()
    ])

    categoryOptions.value = [
      { text: '全部分类', value: '' },
      ...(cat || []).map(i => ({ text: i.label || i.name, value: i.value || i.id }))
    ]

    brandOptions.value = [
      { text: '全部品牌', value: '' },
      ...(brd || []).map(i => ({ text: i.name, value: i.id }))
    ]

    warehouseOptions.value = [
      { text: '全部仓库', value: '' },
      ...(wh || []).map(i => ({ text: i.name, value: i.id }))
    ]
  } catch (error) {
    console.error('加载筛选条件失败:', error)
    showToast('加载筛选条件失败')
  }
}

/* -------------------- 商品列表加载 -------------------- */
const loadProductList = async (isRefresh = false) => {
  if ((!isRefresh && listLoading.value) || refreshing.value) return

  if (isRefresh) {
    filters.page = 1
    finished.value = false
    refreshing.value = true
  } else {
    listLoading.value = true
  }

  try {
    // 准备请求参数
    const params = {
      page: filters.page,
      limit: filters.limit,
      keyword: filters.keyword,
      category_id: filters.category_id,
      brand_id: filters.brand_id,
      warehouse_id: filters.warehouse_id
    }

    // 调用状态管理中的加载方法
    await productStore.loadProductList(params, isRefresh)

    // 更新分页状态
    finished.value = productStore.productList.length >= productStore.productTotal

    // 准备下一页
    if (!finished.value && !isRefresh) {
      filters.page++
    }
  } catch (error) {
    console.error('加载商品列表失败:', error)
    showToast('加载商品列表失败')
    finished.value = true // 加载失败时终止列表加载
  } finally {
    refreshing.value = false
    listLoading.value = false
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

const handleProductItemClick = (product) => {
  // 跳转到商品SKU列表页
  router.push(`/product/${product.id}/skus`)
}

const handleCreateProduct = () => {
  router.push('/product/create')
}

/* -------------------- 生命周期 -------------------- */
onMounted(async () => {
  initialLoading.value = true
  try {
    await loadFilterOptions()
    await loadProductList(true)
  } catch (error) {
    console.error('页面初始化失败:', error)
  } finally {
    initialLoading.value = false
  }
})
</script>

<style scoped lang="scss">
.product-page {
  background: #f7f8fa;
  min-height: 100vh;
  padding-top: 46px; // 适配fixed导航栏

  .van-dropdown-menu {
    padding: 0 12px;
    background-color: #fff;
  }

  .van-list {
    padding: 12px;
  }
}

.page-loading {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}
</style>