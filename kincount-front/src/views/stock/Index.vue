<template>
  <div class="stock-page">
    <van-nav-bar title="库存查询" fixed placeholder />

    <!-- 搜索和筛选 -->
    <div class="filter-section">
      <van-search
        v-model="filters.keyword"
        placeholder="搜索商品名称/编号/SKU编码"
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

    <!-- SKU库存列表 -->
    <van-pull-refresh v-model="refreshing" @refresh="loadStockList(true)">
      <van-list
        v-model:loading="listLoading"
        :finished="finished"
        :finished-text="skuList.length === 0 ? '暂无库存数据' : '没有更多了'"
        @load="loadStockList"
      >
        <div class="sku-list">
          <van-swipe-cell 
            v-for="sku in skuList" 
            :key="`${sku.sku_id}_${sku.warehouse_id}`"
          >
            <div 
              class="sku-item"
              @click="handleViewSku(sku)"
            >
              <div class="sku-header">
                <div class="sku-name">{{ sku.product_name }}</div>
                <van-tag :type="getStockTagType(sku)">
                  {{ getStockStatusText(sku) }}
                </van-tag>
              </div>
              
              <div class="sku-info">
                <div class="info-row">
                  <span class="label">SKU编码：</span>
                  <span class="value">{{ sku.sku_code }}</span>
                </div>
                <div class="info-row">
                  <span class="label">产品编号：</span>
                  <span class="value">{{ sku.product_no }}</span>
                </div>
                
                <!-- SKU规格信息 -->
                <div class="info-row" v-if="sku.spec">
                  <span class="label">规格：</span>
                  <span class="value specs">
                    <template v-if="typeof sku.spec === 'object'">
                      <van-tag 
                        v-for="(value, key) in sku.spec" 
                        :key="key"
                        size="mini"
                        type="primary"
                        plain
                      >
                        {{ key }}:{{ value }}
                      </van-tag>
                    </template>
                    <template v-else>
                      {{ sku.spec }}
                    </template>
                  </span>
                </div>
                
                <div class="info-row">
                  <span class="label">仓库：</span>
                  <span class="value">{{ sku.warehouse_name }}</span>
                </div>
                <div class="info-row">
                  <span class="label">单位：</span>
                  <span class="value">{{ sku.unit }}</span>
                </div>
              </div>

              <div class="stock-details">
                <div class="quantity-info">
                  <div class="quantity-value">{{ sku.quantity }}</div>
                  <div class="quantity-label">库存数量</div>
                </div>
                
                <div class="price-info">
                  <div class="price-row">
                    <span class="price-label">成本价：</span>
                    <span class="price-value">¥{{ formatPrice(sku.cost_price) }}</span>
                  </div>
                  <div class="price-row">
                    <span class="price-label">销售价：</span>
                    <span class="price-value sale">¥{{ formatPrice(sku.sale_price) }}</span>
                  </div>
                  <div class="price-row total">
                    <span class="price-label">库存价值：</span>
                    <span class="price-value">¥{{ formatPrice(sku.total_amount) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <template #right>
              <van-button 
                square 
                type="primary" 
                text="调拨" 
                @click="handleStockTransfer(sku)" 
              />
              <van-button 
                square 
                type="warning" 
                text="盘点" 
                @click="handleStockTake(sku)" 
              />
            </template>
          </van-swipe-cell>
        </div>

        <!-- 空状态 -->
        <van-empty
          v-if="!listLoading && !refreshing && skuList.length === 0"
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

    <!-- SKU详情弹窗 -->
    <van-popup 
      v-model:show="showSkuDetail" 
      position="bottom" 
      :style="{ height: '80%' }"
      round
    >
      <div class="sku-detail" v-if="selectedSku">
        <van-nav-bar
          :title="selectedSku.product_name"
          left-text="返回"
          @click-left="showSkuDetail = false"
        />
        
        <div class="detail-content">
          <van-cell-group title="基本信息">
            <van-cell title="SKU编码" :value="selectedSku.sku_code" />
            <van-cell title="产品编号" :value="selectedSku.product_no" />
            <van-cell title="产品名称" :value="selectedSku.product_name" />
            <van-cell title="规格">
              <template #value>
                <div v-if="selectedSku.spec">
                  <template v-if="typeof selectedSku.spec === 'object'">
                    <van-tag 
                      v-for="(value, key) in selectedSku.spec" 
                      :key="key"
                      size="small"
                      type="primary"
                      plain
                    >
                      {{ key }}:{{ value }}
                    </van-tag>
                  </template>
                  <template v-else>
                    {{ selectedSku.spec }}
                  </template>
                </div>
                <span v-else>无</span>
              </template>
            </van-cell>
            <van-cell title="单位" :value="selectedSku.unit" />
            <van-cell title="条形码" :value="selectedSku.barcode || '无'" />
          </van-cell-group>

          <van-cell-group title="库存信息">
            <van-cell title="仓库" :value="selectedSku.warehouse_name" />
            <van-cell title="库存数量" :value="`${selectedSku.quantity}${selectedSku.unit}`" />
            <van-cell title="安全库存" :value="`${selectedSku.min_stock || 0}${selectedSku.unit}`" />
            <van-cell title="最大库存" :value="`${selectedSku.max_stock || '无限制'}${selectedSku.unit}`" />
          </van-cell-group>

          <van-cell-group title="价格信息">
            <van-cell title="成本价格" :value="`¥${formatPrice(selectedSku.cost_price)}`" />
            <van-cell title="销售价格" :value="`¥${formatPrice(selectedSku.sale_price)}`" />
            <van-cell title="库存价值" :value="`¥${formatPrice(selectedSku.total_amount)}`" />
          </van-cell-group>

          <van-cell-group title="其他信息">
            <van-cell title="创建时间" :value="formatDateTime(selectedSku.created_at)" />
            <van-cell title="更新时间" :value="formatDateTime(selectedSku.updated_at)" />
            <van-cell title="状态" :value="selectedSku.status === 1 ? '正常' : '停用'" />
          </van-cell-group>
        </div>
      </div>
    </van-popup>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
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
const skuList = ref([])
const statistics = ref({
  skuCount: 0,
  totalQuantity: 0,
  totalValue: 0,
  warningCount: 0
})
const refreshing = ref(false)
const listLoading = ref(false)
const finished = ref(false)
const showActionSheet = ref(false)
const showSkuDetail = ref(false)
const selectedSku = ref(null)

const actions = ref([
  { name: '查看详情', key: 'detail' },
  { name: '库存调拨', key: 'transfer' },
  { name: '库存盘点', key: 'take' },
  { name: '查看产品档案', key: 'product' }
])

// 格式化价格
const formatPrice = (price) => {
  if (price === null || price === undefined || price === '') return '0.00'
  const num = Number(price)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

// 格式化日期时间
const formatDateTime = (dateTime) => {
  if (!dateTime) return '--'
  try {
    const d = new Date(dateTime)
    if (isNaN(d.getTime())) return '--'
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    const hours = String(d.getHours()).padStart(2, '0')
    const minutes = String(d.getMinutes()).padStart(2, '0')
    return `${year}-${month}-${day} ${hours}:${minutes}`
  } catch (error) {
    return '--'
  }
}

// 处理SKU数据，提取需要的信息
const processSkuData = (stocks) => {
  return stocks.map(stock => {
    const sku = stock.sku || {}
    const product = sku.product || {}
    const warehouse = stock.warehouse || {}
    
    return {
      // 库存记录信息
      id: stock.id,
      sku_id: stock.sku_id,
      warehouse_id: stock.warehouse_id,
      quantity: stock.quantity || 0,
      cost_price: stock.cost_price || sku.cost_price,
      total_amount: stock.total_amount || 0,
      created_at: stock.created_at,
      updated_at: stock.updated_at,
      
      // SKU信息
      sku_code: sku.sku_code,
      spec: sku.spec,
      barcode: sku.barcode,
      sale_price: sku.sale_price,
      unit: sku.unit || product.unit,
      status: sku.status,
      
      // 产品信息
      product_id: product.id,
      product_name: product.name,
      product_no: product.product_no,
      min_stock: product.min_stock || 0,
      max_stock: product.max_stock,
      
      // 仓库信息
      warehouse_name: warehouse.name,
      warehouse_code: warehouse.code,
      warehouse_address: warehouse.address
    }
  })
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

    // 处理SKU数据
    const processedData = processSkuData(listData)

    if (isRefresh) {
      skuList.value = processedData
    } else {
      skuList.value = [...skuList.value, ...processedData]
    }

    pagination.total = totalCount

    if (skuList.value.length >= totalCount || listData.length === 0) {
      finished.value = true
    }

    calculateStatistics()

  } catch (error) {
    console.error('加载库存列表失败:', error)
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
    skuCount: new Set(),
    totalQuantity: 0,
    totalValue: 0,
    warningCount: 0
  }

  skuList.value.forEach(sku => {
    stats.skuCount.add(sku.sku_id)
    stats.totalQuantity += Number(sku.quantity) || 0
    stats.totalValue += Number(sku.total_amount) || 0
    
    // 计算预警SKU（库存低于安全库存）
    if (sku.quantity <= (sku.min_stock || 0)) {
      stats.warningCount++
    }
  })

  statistics.value = {
    skuCount: stats.skuCount.size,
    totalQuantity: stats.totalQuantity,
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
    console.error('加载筛选选项失败:', error)
    showToast('加载筛选选项失败')
  }
}

// 获取库存状态标签类型
const getStockTagType = (sku) => {
  const quantity = Number(sku.quantity) || 0
  const minStock = Number(sku.min_stock) || 0
  
  if (quantity <= 0) return 'danger'
  if (quantity <= minStock) return 'warning'
  return 'success'
}

// 获取库存状态文本
const getStockStatusText = (sku) => {
  const quantity = Number(sku.quantity) || 0
  const minStock = Number(sku.min_stock) || 0
  
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

const handleViewSku = (sku) => {
  selectedSku.value = sku
  showActionSheet.value = true
}

const onActionSelect = (action) => {
  showActionSheet.value = false
  
  if (!selectedSku.value) return

  switch (action.key) {
    case 'detail':
      showSkuDetail.value = true
      break
    case 'transfer':
      handleStockTransfer(selectedSku.value)
      break
    case 'take':
      handleStockTake(selectedSku.value)
      break
    case 'product':
      router.push({
        path: '/product/detail',
        query: { id: selectedSku.value.product_id }
      })
      break
  }
}

// 库存调拨
const handleStockTransfer = (sku) => {
  showConfirmDialog({
    title: '库存调拨',
    message: `是否要对SKU「${sku.sku_code}」进行库存调拨？`
  }).then(() => {
    router.push({
      path: '/stock/transfer',
      query: { 
        sku_id: sku.sku_id,
        sku_name: sku.product_name,
        warehouse_id: sku.warehouse_id
      }
    })
  }).catch(() => {
    // 取消操作
  })
}

// 库存盘点
const handleStockTake = (sku) => {
  showConfirmDialog({
    title: '库存盘点',
    message: `是否要对SKU「${sku.sku_code}」进行库存盘点？`
  }).then(() => {
    router.push({
      path: '/stock/take',
      query: { 
        sku_id: sku.sku_id,
        sku_name: sku.product_name,
        warehouse_id: sku.warehouse_id
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

.sku-list {
  padding: 8px 0;
}

.sku-item {
  background: white;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  
  .sku-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
    
    .sku-name {
      font-size: 16px;
      font-weight: 500;
      flex: 1;
      margin-right: 12px;
    }
  }
  
  .sku-info {
    margin-bottom: 12px;
    
    .info-row {
      display: flex;
      margin-bottom: 6px;
      font-size: 13px;
      
      .label {
        color: #969799;
        min-width: 70px;
      }
      
      .value {
        color: #323233;
        flex: 1;
        
        &.specs {
          display: flex;
          flex-wrap: wrap;
          gap: 4px;
          
          :deep(.van-tag) {
            margin-bottom: 2px;
          }
        }
      }
    }
  }
  
  .stock-details {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: #f7f8fa;
    border-radius: 6px;
    
    .quantity-info {
      text-align: center;
      min-width: 80px;
      
      .quantity-value {
        font-size: 20px;
        font-weight: bold;
        color: #1989fa;
      }
      
      .quantity-label {
        font-size: 12px;
        color: #969799;
        margin-top: 4px;
      }
    }
    
    .price-info {
      flex: 1;
      margin-left: 16px;
      
      .price-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 4px;
        font-size: 12px;
        
        &.total {
          margin-top: 6px;
          padding-top: 6px;
          border-top: 1px solid #e5e5e5;
          
          .price-label {
            font-weight: 600;
          }
          
          .price-value {
            font-weight: 700;
            color: #ee0a24;
          }
        }
        
        .price-label {
          color: #969799;
        }
        
        .price-value {
          color: #323233;
          
          &.sale {
            color: #07c160;
          }
        }
      }
    }
  }
}

.sku-detail {
  height: 100%;
  display: flex;
  flex-direction: column;
}

.detail-content {
  flex: 1;
  overflow-y: auto;
  padding-bottom: 20px;
}

// 操作面板样式调整
:deep(.van-action-sheet) {
  .van-action-sheet__item {
    font-size: 16px;
    
    &[style*="color: #1989fa"] {
      color: #1989fa !important;
    }
    
    &[style*="color: #07c160"] {
      color: #07c160 !important;
    }
    
    &[style*="color: #7232dd"] {
      color: #7232dd !important;
    }
    
    &[style*="color: #ff976a"] {
      color: #ff976a !important;
    }
    
    &[style*="color: #ee0a24"] {
      color: #ee0a24 !important;
    }
  }
}

// 优化导航栏样式
:deep(.van-nav-bar) {
  background: white;
  
  .van-nav-bar__title {
    font-weight: 600;
  }
}
</style>