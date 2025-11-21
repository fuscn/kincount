<!-- kincount\kincount-front\src\views\stock\Index.vue -->
<template>
  <div class="stock-page">
    <van-nav-bar title="库存查询" fixed placeholder />

    <!-- 搜索和筛选 -->
    <div class="filter-section">
      <van-search
        v-model="filters.keyword"
        placeholder="搜索商品名称/编号"
        show-action
        @search="handleSearch"
        @clear="handleClearSearch"
      >
        <template #action>
          <div @click="handleSearch">搜索</div>
        </template>
      </van-search>
      
      <van-dropdown-menu>
        <van-dropdown-item 
          v-model="filters.warehouse_id" 
          :options="warehouseOptions" 
          @change="loadStockList(true)"
        />
        <van-dropdown-item 
          v-model="filters.category_id" 
          :options="categoryOptions" 
          @change="loadStockList(true)"
        />
      </van-dropdown-menu>
    </div>

    <!-- 库存统计 -->
    <div class="stats-cards">
      <van-row gutter="12">
        <van-col span="8">
          <div class="stat-card">
            <div class="stat-value">{{ statistics.productCount || 0 }}</div>
            <div class="stat-label">商品种类</div>
          </div>
        </van-col>
        <van-col span="8">
          <div class="stat-card">
            <div class="stat-value">¥{{ statistics.totalValue || 0 }}</div>
            <div class="stat-label">库存价值</div>
          </div>
        </van-col>
        <van-col span="8">
          <div class="stat-card warning">
            <div class="stat-value">{{ statistics.warningCount || 0 }}</div>
            <div class="stat-label">预警商品</div>
          </div>
        </van-col>
      </van-row>
    </div>

    <!-- 商品聚合库存列表 -->
    <van-pull-refresh v-model="refreshing" @refresh="loadStockList(true)">
      <van-list
        v-model:loading="listLoading"
        :finished="finished"
        :finished-text="aggregatedList.length === 0 ? '暂无库存数据' : '没有更多了'"
        @load="loadStockList"
      >
        <div class="product-list">
          <van-swipe-cell 
            v-for="product in aggregatedList" 
            :key="product.id"
          >
            <div 
              class="product-item"
              @click="handleViewProduct(product)"
            >
              <div class="product-header">
                <div class="product-name">{{ product.name }}</div>
                <van-tag :type="getStockTagType(product)">
                  {{ getStockStatusText(product) }}
                </van-tag>
              </div>
              
              <div class="product-info">
                <div class="info-row">
                  <span class="label">编号：</span>
                  <span class="value">{{ product.product_no }}</span>
                </div>
                <div class="info-row">
                  <span class="label">规格：</span>
                  <span class="value">{{ product.spec || '无' }}</span>
                </div>
                <div class="info-row">
                  <span class="label">分类：</span>
                  <span class="value">{{ product.category_name }}</span>
                </div>
              </div>

              <div class="stock-info">
                <div class="total-stock">
                  <div class="stock-value">{{ product.total_quantity }}</div>
                  <div class="stock-label">总库存 ({{ product.unit }})</div>
                </div>
                
                <div class="warehouse-stocks">
                  <div 
                    v-for="wh in product.warehouses" 
                    :key="wh.warehouse_id"
                    class="warehouse-item"
                  >
                    <span class="wh-name">{{ wh.warehouse_name }}：</span>
                    <span class="wh-quantity">{{ wh.quantity }}{{ product.unit }}</span>
                  </div>
                </div>
              </div>

              <div class="price-info">
                <div class="cost-price">成本价：¥{{ product.cost_price }}</div>
                <div class="total-value">库存价值：¥{{ product.total_amount }}</div>
              </div>
            </div>

            <template #right>
              <van-button 
                square 
                type="primary" 
                text="调拨" 
                @click="handleStockTransfer(product)" 
              />
              <van-button 
                square 
                type="warning" 
                text="盘点" 
                @click="handleStockTake(product)" 
              />
            </template>
          </van-swipe-cell>
        </div>

        <!-- 空状态 -->
        <van-empty
          v-if="!listLoading && !refreshing && aggregatedList.length === 0"
          description="暂无库存数据"
          image="search"
        />
      </van-list>
    </van-pull-refresh>

    <!-- 操作菜单 -->
    <van-action-sheet 
      v-model:show="showActionSheet" 
      :actions="actions" 
      @select="onActionSelect"
      cancel-text="取消"
    />

    <!-- 商品详情弹窗 -->
    <van-popup 
      v-model:show="showProductDetail" 
      position="bottom" 
      :style="{ height: '70%' }"
      round
    >
      <div class="product-detail" v-if="selectedProduct">
        <van-nav-bar
          :title="selectedProduct.name"
          left-text="返回"
          @click-left="showProductDetail = false"
        />
        
        <div class="detail-content">
          <van-cell-group title="基本信息">
            <van-cell title="商品编号" :value="selectedProduct.product_no" />
            <van-cell title="规格型号" :value="selectedProduct.spec || '无'" />
            <van-cell title="计量单位" :value="selectedProduct.unit" />
            <van-cell title="商品分类" :value="selectedProduct.category_name" />
          </van-cell-group>

          <van-cell-group title="库存信息">
            <van-cell title="总库存数量" :value="`${selectedProduct.total_quantity}${selectedProduct.unit}`" />
            <van-cell title="安全库存" :value="`${selectedProduct.min_stock}${selectedProduct.unit}`" />
            <van-cell title="最大库存" :value="`${selectedProduct.max_stock || '无限制'}${selectedProduct.unit}`" />
          </van-cell-group>

          <van-cell-group title="价格信息">
            <van-cell title="成本价格" :value="`¥${selectedProduct.cost_price}`" />
            <van-cell title="库存价值" :value="`¥${selectedProduct.total_amount}`" />
          </van-cell-group>

          <van-cell-group title="仓库库存分布">
            <van-cell
              v-for="wh in selectedProduct.warehouses"
              :key="wh.warehouse_id"
              :title="wh.warehouse_name"
              :value="`${wh.quantity}${selectedProduct.unit}`"
              :label="`库存价值: ¥${wh.amount}`"
            >
              <template #extra>
                <van-tag :type="getWarehouseStockTagType(wh.quantity, selectedProduct.min_stock)">
                  {{ getWarehouseStockStatus(wh.quantity, selectedProduct.min_stock) }}
                </van-tag>
              </template>
            </van-cell>
          </van-cell-group>
        </div>
      </div>
    </van-popup>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { 
  showToast,
  showConfirmDialog
} from 'vant'
import { useStockStore } from '@/store/modules/stock'
import { getWarehouseOptions } from '@/api/warehouse'
import { getCategoryOptions } from '@/api/category'

const router = useRouter()
const stockStore = useStockStore()

// 响应式数据
const filters = reactive({
  keyword: '',
  warehouse_id: '',
  category_id: ''
})

const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 0
})

const warehouseOptions = ref([{ text: '全部仓库', value: '' }])
const categoryOptions = ref([{ text: '全部分类', value: '' }])
const stockList = ref([])
const aggregatedList = ref([])
const statistics = ref({
  productCount: 0,
  totalValue: 0,
  warningCount: 0
})
const refreshing = ref(false)
const listLoading = ref(false)
const finished = ref(false)
const showActionSheet = ref(false)
const showProductDetail = ref(false)
const selectedProduct = ref(null)

const actions = ref([
  { name: '查看库存详情', key: 'detail' },
  { name: '库存调拨', key: 'transfer' },
  { name: '库存盘点', key: 'take' },
  { name: '查看商品档案', key: 'product' }
])

// 聚合商品库存数据
const aggregateProducts = (warehouseStocks) => {
  const productMap = new Map()

  warehouseStocks.forEach(stock => {
    const productId = stock.product_id
    
    if (!productMap.has(productId)) {
      productMap.set(productId, {
        id: productId,
        name: stock.product_name,
        product_no: stock.product_no,
        spec: stock.spec,
        unit: stock.unit,
        min_stock: stock.min_stock,
        max_stock: stock.max_stock,
        cost_price: stock.cost_price,
        category_name: stock.category_name,
        total_quantity: 0,
        total_amount: 0,
        warehouses: []
      })
    }

    const product = productMap.get(productId)
    const quantity = Number(stock.quantity) || 0
    const amount = Number(stock.total_amount) || 0

    product.total_quantity += quantity
    product.total_amount += amount
    product.warehouses.push({
      warehouse_id: stock.warehouse_id,
      warehouse_name: stock.warehouse_name,
      quantity: quantity,
      amount: amount
    })
  })

  return Array.from(productMap.values())
}

// 加载库存列表
const loadStockList = async (isRefresh = false) => {
  if ((!isRefresh && listLoading.value) || refreshing.value) return

  if (isRefresh) {
    pagination.page = 1
    finished.value = false
    refreshing.value = true
  } else {
    listLoading.value = true
  }

  try {
    const params = {
      page: pagination.page,
      limit: pagination.pageSize,
      ...filters
    }

    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] == null) delete params[key]
    })

    const response = await stockStore.loadList(params)

    let listData = []
    let totalCount = 0

    if (response?.code === 200 && response.data) {
      listData = response.data.list || []
      totalCount = response.data.total || 0
    } else if (response?.list) {
      listData = response.list
      totalCount = response.total || 0
    } else {
      listData = stockStore.list || []
      totalCount = stockStore.total || 0
    }

    if (isRefresh) {
      stockList.value = listData
    } else {
      stockList.value = [...stockList.value, ...listData]
    }

    // 聚合商品数据
    aggregatedList.value = aggregateProducts(stockList.value)

    pagination.total = totalCount

    if (stockList.value.length >= totalCount || listData.length === 0) {
      finished.value = true
    }

    calculateStatistics()

  } catch (error) {
    showToast('加载失败')
    finished.value = true
  } finally {
    refreshing.value = false
    listLoading.value = false
  }
}

// 计算统计信息
const calculateStatistics = () => {
  const stats = {
    productCount: new Set(),
    totalValue: 0,
    warningCount: 0
  }

  aggregatedList.value.forEach(product => {
    stats.productCount.add(product.id)
    stats.totalValue += product.total_amount
    if (product.total_quantity <= product.min_stock) {
      stats.warningCount++
    }
  })

  statistics.value = {
    productCount: stats.productCount.size,
    totalValue: stats.totalValue.toFixed(2),
    warningCount: stats.warningCount
  }
}

// 加载筛选选项
const loadFilterOptions = async () => {
  try {
    const [warehouses, categories] = await Promise.all([
      getWarehouseOptions(),
      getCategoryOptions()
    ])

    // 处理仓库选项
    warehouseOptions.value = [
      { text: '全部仓库', value: '' },
      ...(warehouses?.data || warehouses || []).map(item => ({
        text: item.name || item.label,
        value: item.id || item.value
      }))
    ]

    // 处理分类选项
    const categoryData = categories?.data || categories || []
    
    categoryOptions.value = [
      { text: '全部分类', value: '' },
      ...categoryData.map(item => ({
        text: item.label,
        value: item.value
      }))
    ]

  } catch (error) {
    showToast('加载筛选选项失败')
  }
}

// 获取库存状态标签类型
const getStockTagType = (product) => {
  if (product.total_quantity <= 0) return 'danger'
  if (product.total_quantity <= product.min_stock) return 'warning'
  return 'success'
}

// 获取库存状态文本
const getStockStatusText = (product) => {
  if (product.total_quantity <= 0) return '缺货'
  if (product.total_quantity <= product.min_stock) return '预警'
  return '正常'
}

// 获取仓库库存状态标签类型
const getWarehouseStockTagType = (quantity, minStock) => {
  if (quantity <= 0) return 'danger'
  if (quantity <= minStock) return 'warning'
  return 'success'
}

// 获取仓库库存状态文本
const getWarehouseStockStatus = (quantity, minStock) => {
  if (quantity <= 0) return '缺货'
  if (quantity <= minStock) return '预警'
  return '正常'
}

// 事件处理
const handleSearch = () => {
  loadStockList(true)
}

const handleClearSearch = () => {
  filters.keyword = ''
  loadStockList(true)
}

const handleViewProduct = (product) => {
  selectedProduct.value = product
  showActionSheet.value = true
}

const onActionSelect = (action) => {
  showActionSheet.value = false
  
  if (!selectedProduct.value) return

  switch (action.key) {
    case 'detail':
      showProductDetail.value = true
      break
    case 'transfer':
      handleStockTransfer(selectedProduct.value)
      break
    case 'take':
      handleStockTake(selectedProduct.value)
      break
    case 'product':
      router.push({
        path: '/product/detail',
        query: { id: selectedProduct.value.id }
      })
      break
  }
}

// 库存调拨
const handleStockTransfer = (product) => {
  showConfirmDialog({
    title: '库存调拨',
    message: `是否要对商品「${product.name}」进行库存调拨？`
  }).then(() => {
    router.push({
      path: '/stock/transfer',
      query: { 
        product_id: product.id,
        product_name: product.name
      }
    })
  }).catch(() => {
    // 取消操作
  })
}

// 库存盘点
const handleStockTake = (product) => {
  showConfirmDialog({
    title: '库存盘点',
    message: `是否要对商品「${product.name}」进行库存盘点？`
  }).then(() => {
    router.push({
      path: '/stock/take',
      query: { 
        product_id: product.id,
        product_name: product.name
      }
    })
  }).catch(() => {
    // 取消操作
  })
}

onMounted(() => {
  loadFilterOptions()
  loadStockList(true)
})
</script>

<style scoped lang="scss">
.stock-page {
  padding: 16px;
  background: #f7f8fa;
  min-height: 100vh;
}

.filter-section {
  background: white;
  margin-bottom: 12px;
  border-radius: 8px;
  overflow: hidden;
}

.stats-cards {
  margin-bottom: 16px;
}

.stat-card {
  background: white;
  border-radius: 8px;
  padding: 16px;
  text-align: center;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  
  &.warning {
    background: #fff2f0;
    .stat-value {
      color: #ff4d4f;
    }
  }
  
  .stat-value {
    font-size: 18px;
    font-weight: bold;
    color: #1989fa;
    margin-bottom: 4px;
  }
  
  .stat-label {
    font-size: 12px;
    color: #969799;
  }
}

.product-list {
  padding: 8px 0;
}

.product-item {
  background: white;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  
  .product-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
    
    .product-name {
      font-size: 16px;
      font-weight: 500;
      flex: 1;
      margin-right: 12px;
    }
  }
  
  .product-info {
    margin-bottom: 12px;
    
    .info-row {
      display: flex;
      margin-bottom: 4px;
      font-size: 13px;
      
      .label {
        color: #969799;
        min-width: 50px;
      }
      
      .value {
        color: #323233;
        flex: 1;
      }
    }
  }
  
  .stock-info {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
    padding: 12px;
    background: #f7f8fa;
    border-radius: 6px;
    
    .total-stock {
      text-align: center;
      
      .stock-value {
        font-size: 20px;
        font-weight: bold;
        color: #1989fa;
      }
      
      .stock-label {
        font-size: 12px;
        color: #969799;
        margin-top: 4px;
      }
    }
    
    .warehouse-stocks {
      flex: 1;
      margin-left: 16px;
      
      .warehouse-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 4px;
        font-size: 12px;
        
        .wh-name {
          color: #646566;
        }
        
        .wh-quantity {
          color: #323233;
          font-weight: 500;
        }
      }
    }
  }
  
  .price-info {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    
    .cost-price {
      color: #ee0a24;
    }
    
    .total-value {
      color: #07c160;
      font-weight: 500;
    }
  }
}

.product-detail {
  height: 100%;
  display: flex;
  flex-direction: column;
}

.detail-content {
  flex: 1;
  overflow-y: auto;
  padding-bottom: 20px;
}
</style>