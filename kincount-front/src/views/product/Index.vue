<template>
  <div class="product-page">
    <!-- 头部 -->
    <van-nav-bar title="商品库存列表" fixed placeholder>
      <template #right>
        <van-button 
          size="small" 
          type="primary" 
          @click="handleCreateSku" 
          v-perm="PERM.PRODUCT_ADD"
        >
          新增商品
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 搜索 -->
    <SearchBar 
      placeholder="搜索 SKU 编码/规格/商品名称" 
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

    <!-- SKU 级库存列表（公共组件） -->
    <SkuStockList
      :warehouse-id="filters.warehouse_id"
      :keyword="filters.keyword"
      :category-id="filters.category_id"
      :brand-id="filters.brand_id"
      :warning-type="filters.warning_type"
      ref="skuListRef"
    />

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
import { getWarehouseOptions } from '@/api/warehouse' // 需要添加这个API
import { PERM } from '@/constants/permissions'
import SearchBar from '@/components/common/SearchBar.vue'
import SkuStockList from '@/components/business/SkuStockList.vue'

/* -------------------- 路由 & 状态 -------------------- */
const router = useRouter()
const productStore = useProductStore()
const skuListRef = ref() // 引用 SkuStockList 组件

/* -------------------- 筛选条件 -------------------- */
const filters = reactive({
  keyword: '',
  category_id: '',
  brand_id: '',
  warehouse_id: '',
  warning_type: ''
})

const categoryOptions = ref([{ text: '全部分类', value: '' }])
const brandOptions = ref([{ text: '全部品牌', value: '' }])
const warehouseOptions = ref([{ text: '全部仓库', value: '' }])

/* -------------------- 列表参数 -------------------- */
const initialLoading = ref(false)

/* -------------------- 下拉数据 -------------------- */
const loadFilterOptions = async () => {
  try {
    const [cat, brd, wh] = await Promise.all([
      getCategoryOptions(), 
      getBrandOptions(),
      getWarehouseOptions() // 获取仓库选项
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

/* -------------------- 事件处理 -------------------- */
const handleSearch = (kw) => {
  filters.keyword = kw
  // 触发列表刷新
  nextTick(() => {
    if (skuListRef.value && skuListRef.value.refresh) {
      skuListRef.value.refresh()
    }
  })
}

const handleClearSearch = () => {
  filters.keyword = ''
  // 触发列表刷新
  nextTick(() => {
    if (skuListRef.value && skuListRef.value.refresh) {
      skuListRef.value.refresh()
    }
  })
}

const handleFilterChange = () => {
  // 筛选条件变化时刷新列表
  nextTick(() => {
    if (skuListRef.value && skuListRef.value.refresh) {
      skuListRef.value.refresh()
    }
  })
}

const handleCreateSku = () => {
  router.push('/product/create')
}

/* -------------------- 生命周期 -------------------- */
onMounted(async () => {
  initialLoading.value = true
  try {
    await loadFilterOptions()
  } catch (error) {
    console.error('页面初始化失败:', error)
  } finally {
    initialLoading.value = false
  }
  // SkuStockList 会自动触发第一次加载，不需要额外调用
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