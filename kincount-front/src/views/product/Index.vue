<template>
  <div class="product-page">
    <!-- 头部 -->
    <van-nav-bar title="库存查询" fixed placeholder>
      <template #right>
        <van-button size="small" type="primary" @click="handleCreateSku" v-perm="PERM.PRODUCT_ADD">
          新增SKU
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 搜索 -->
    <SearchBar placeholder="搜索 SKU 编码/规格/商品名称" @search="handleSearch" @clear="handleClearSearch" />

    <!-- 下拉筛选 -->
    <van-dropdown-menu>
      <van-dropdown-item v-model="filters.category_id" :options="categoryOptions" @change="handleFilterChange" />
      <van-dropdown-item v-model="filters.brand_id" :options="brandOptions" @change="handleFilterChange" />
    </van-dropdown-menu>

    <!-- SKU 级库存列表（公共组件） -->
    <SkuStockList
      :warehouse-id="warehouseId"
      :keyword="filters.keyword"
      :warning-type="warningType"
    />

    <!-- 首次加载遮罩 -->
    <van-loading v-if="initialLoading" class="page-loading" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import { useProductStore } from '@/store/modules/product'
import { getCategoryOptions } from '@/api/category'
import { getBrandOptions } from '@/api/brand'
import { PERM } from '@/constants/permissions'
import SearchBar from '@/components/common/SearchBar.vue'
import SkuStockList from '@/components/business/SkuStockList.vue'

/* -------------------- 路由 & 状态 -------------------- */
const router = useRouter()
const productStore = useProductStore()

/* -------------------- 筛选条件 -------------------- */
const filters = reactive({
  keyword: '',
  category_id: '',
  brand_id: ''
})
const categoryOptions = ref([{ text: '全部分类', value: '' }])
const brandOptions = ref([{ text: '全部品牌', value: '' }])

/* -------------------- 列表参数 -------------------- */
const warehouseId = ref(0)        // 0=全部仓库，可绑定仓库选择器
const warningType = ref('')       // low | high | ''
const initialLoading = ref(false)

/* -------------------- 下拉数据 -------------------- */
const loadFilterOptions = async () => {
  try {
    const [cat, brd] = await Promise.all([getCategoryOptions(), getBrandOptions()])
    categoryOptions.value = [
      { text: '全部分类', value: '' },
      ...(cat || []).map(i => ({ text: i.label || i.name, value: i.value || i.id }))
    ]
    brandOptions.value = [
      { text: '全部品牌', value: '' },
      ...(brd || []).map(i => ({ text: i.name, value: i.id }))
    ]
  } catch {
    showToast('加载筛选条件失败')
  }
}

/* -------------------- 事件处理 -------------------- */
const handleSearch = (kw) => {
  filters.keyword = kw
  // SkuStockList 内部会监听 keyword 变化自动重新请求
}
const handleClearSearch = () => {
  filters.keyword = ''
}
const handleFilterChange = () => {
  // 可扩展：把 category_id / brand_id 作为额外参数传给 SkuStockList
  // 当前组件内部未使用，留口即可
}

const handleCreateSku = () => {
  router.push('/product/create') // 你的新增 SKU 路由
}
const handleViewSku = (item) => {
  // item 内包含 sku_id / product_id 等字段
  router.push(`/product/${item.product_id || item.id}/skus`)
}

/* -------------------- 生命周期 -------------------- */
onMounted(async () => {
  initialLoading.value = true
  await loadFilterOptions()
  initialLoading.value = false
  // SkuStockList 会自动触发第一次加载
})
</script>

<style scoped lang="scss">
.product-page {
  background: #f7f8fa;
  min-height: 100vh;
}
.page-loading {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}
</style>