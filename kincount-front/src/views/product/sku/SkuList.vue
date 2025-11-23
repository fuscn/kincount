<template>
  <div class="sku-list-page">
    <van-nav-bar
      title="SKU总列表"
      left-text="返回"
      left-arrow
      @click-left="$router.back()"
    />

    <!-- 快捷筛选栏 -->
    <div class="quick-filters">
      <van-button 
        size="small" 
        :type="filter.status === '' ? 'primary' : 'default'"
        @click="setStatusFilter('')"
      >全部</van-button>
      <van-button 
        size="small" 
        :type="filter.status === 1 ? 'primary' : 'default'"
        @click="setStatusFilter(1)"
      >启用</van-button>
      <van-button 
        size="small" 
        :type="filter.status === 0 ? 'primary' : 'default'"
        @click="setStatusFilter(0)"
      >禁用</van-button>
      <van-button 
        size="small" 
        :type="filter.stock === 'low' ? 'primary' : 'default'"
        @click="setStockFilter('low')"
      >低库存</van-button>
      <van-button 
        size="small" 
        :type="filter.stock === 'out' ? 'primary' : 'default'"
        @click="setStockFilter('out')"
      >缺货</van-button>
    </div>

    <van-pull-refresh v-model="refreshing" @refresh="onRefresh">
      <van-list
        v-model:loading="loading"
        :finished="finished"
        :finished-text="skuList.length > 10 ? '没有更多数据' : ''"
        @load="onLoad"
      >
        <!-- 高密度列表项 -->
        <div class="dense-list">
          <div 
            v-for="item in filteredList" 
            :key="item.id" 
            class="dense-item"
            :class="getItemClass(item)"
            @click="handleItemClick(item)"
          >
            <div class="item-badge" v-if="getStockLevel(item) === 'out'">缺货</div>
            <div class="item-badge warning" v-else-if="getStockLevel(item) === 'low'">低库存</div>
            
            <div class="item-content">
              <div class="item-header">
                <span class="sku-code">{{ item.sku_code }}</span>
                <div class="item-status">
                  <van-tag 
                    v-if="item.status === 1" 
                    type="success" 
                    size="mini"
                  >启</van-tag>
                  <van-tag v-else type="default" size="mini">禁</van-tag>
                </div>
              </div>
              
              <!-- 显示产品信息 -->
              <div class="product-info" v-if="item.product">
                <span class="product-name">{{ item.product.name }}</span>
                <span class="product-no">{{ item.product.product_no }}</span>
              </div>
              
              <div class="item-details">
                <div class="spec">{{ getCompactSpecText(item) }}</div>
                <div class="prices">
                  <span class="sale">¥{{ item.sale_price }}</span>
                  <span class="cost">成本:¥{{ item.cost_price }}</span>
                </div>
              </div>
              
              <div class="item-meta">
                <span class="stock" :class="getStockLevel(item)">
                  {{ item.stock_quantity || 0 }}{{ item.unit }}
                </span>
                <span class="barcode" v-if="item.barcode">{{ item.barcode }}</span>
              </div>
            </div>
          </div>
        </div>

        <van-empty v-if="filteredList.length === 0 && !loading" description="暂无SKU数据" />
      </van-list>
    </van-pull-refresh>

    <!-- 操作面板 -->
    <van-action-sheet 
      v-model:show="showActionSheet" 
      :actions="actions" 
      @cancel="handleActionSheetCancel"
      @select="handleActionSheetSelect"
      cancel-text="取消"
    />

    <!-- SKU表单组件 -->
    <SkuForm 
      ref="skuFormRef"
      @success="handleFormSuccess"
      @close="handleFormClose"
    />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { showToast, showConfirmDialog } from 'vant'
import { getSkuList, deleteSku } from '@/api/product'
import SkuForm from './sku/Form.vue'

const emit = defineEmits(['update'])

// 状态管理
const skuList = ref([])
const loading = ref(false)
const finished = ref(false)
const refreshing = ref(false)
const page = ref(1)
const limit = ref(10)

// 表单引用
const skuFormRef = ref(null)

// 快捷筛选
const filter = reactive({
  status: '',
  stock: ''
})

// 操作面板
const showActionSheet = ref(false)
const selectedItem = ref(null)
const actions = [
  { name: '编辑', callback: () => handleEdit(selectedItem.value) },
  { name: '删除', color: '#ee0a24', callback: () => handleDelete(selectedItem.value.id) }
]

// 计算筛选后的列表
const filteredList = computed(() => {
  return skuList.value.filter(item => {
    if (filter.status !== '' && item.status !== filter.status) {
      return false
    }
    
    if (filter.stock === 'low' && (item.stock_quantity || 0) >= 10) {
      return false
    }
    if (filter.stock === 'out' && (item.stock_quantity || 0) > 0) {
      return false
    }
    
    return true
  })
})

// 初始化加载
onMounted(() => {
  loadSkuList()
})

// 加载SKU列表
const loadSkuList = async () => {
  if (loading.value) return

  loading.value = true
  try {
    const res = await getSkuList({
      page: page.value,
      limit: limit.value
    })

    const data = res.list || res.data?.list || []
    if (page.value === 1) {
      skuList.value = data
    } else {
      skuList.value = [...skuList.value, ...data]
    }

    finished.value = data.length < limit.value
    page.value++
  } catch (error) {
    showToast('加载SKU列表失败')
    console.error('加载SKU列表失败:', error)
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

// 下拉刷新
const onRefresh = () => {
  resetList()
  loadSkuList()
}

// 上拉加载
const onLoad = () => {
  if (finished.value) return
  loadSkuList()
}

// 重置列表
const resetList = () => {
  page.value = 1
  finished.value = false
  skuList.value = []
}

// 格式化规格文本
const getSpecText = (item) => {
  if (item.spec && typeof item.spec === 'object') {
    return Object.entries(item.spec)
      .map(([key, value]) => `${key}: ${value}`)
      .join(' | ')
  }
  return item.spec_text || '无规格'
}

// 获取紧凑规格文本
const getCompactSpecText = (item) => {
  if (item.spec && typeof item.spec === 'object') {
    const values = Object.values(item.spec).filter(Boolean)
    return values.length > 0 ? values.join('/') : '无规格'
  }
  return item.spec_text || '无规格'
}

// 设置状态筛选
const setStatusFilter = (status) => {
  filter.status = status
}

// 设置库存筛选
const setStockFilter = (stock) => {
  filter.stock = stock
}

// 获取库存级别
const getStockLevel = (item) => {
  const stock = item.stock_quantity || 0
  if (stock === 0) return 'out'
  if (stock < 10) return 'low'
  return 'normal'
}

// 获取项目类名
const getItemClass = (item) => {
  return {
    'disabled': item.status === 0,
    'stock-out': getStockLevel(item) === 'out',
    'stock-low': getStockLevel(item) === 'low'
  }
}

// 处理项目点击
const handleItemClick = (item) => {
  selectedItem.value = item
  showActionSheet.value = true
}

// 处理操作面板取消
const handleActionSheetCancel = () => {
  showActionSheet.value = false
  selectedItem.value = null
}

// 处理操作面板选择
const handleActionSheetSelect = (action) => {
  showActionSheet.value = false
  // 直接执行回调函数
  if (action.callback) {
    action.callback()
  }
  selectedItem.value = null
}

// 新增SKU
const handleAdd = () => {
  skuFormRef.value.openAddForm()
}

// 编辑SKU
const handleEdit = (item) => {
  skuFormRef.value.openEditForm(item)
}

// 处理表单成功
const handleFormSuccess = () => {
  resetList()
  loadSkuList()
  emit('update')
}

// 处理表单关闭
const handleFormClose = () => {
  // 可以在这里处理表单关闭后的逻辑
}

// 删除SKU
const handleDelete = async (id) => {
  try {
    await showConfirmDialog({
      title: '确认删除',
      message: '确定要删除该SKU吗？此操作不可恢复'
    })

    await deleteSku(id)
    showToast('删除成功')
    resetList()
    loadSkuList()
    emit('update')
  } catch (error) {
    if (error !== 'cancel') {
      showToast('删除失败')
      console.error('删除SKU失败:', error)
    }
  }
}
</script>

<style scoped lang="scss">
.sku-list-page {
  background-color: #f7f8fa;
  min-height: 100vh;
}

.quick-filters {
  display: flex;
  gap: 8px;
  padding: 12px;
  background: white;
  border-bottom: 1px solid #ebedf0;
  overflow-x: auto;
  white-space: nowrap;
  
  .van-button {
    flex-shrink: 0;
  }
}

.dense-list {
  padding: 8px;
}

.dense-item {
  position: relative;
  display: flex;
  align-items: center;
  padding: 10px 12px;
  margin-bottom: 6px;
  background: white;
  border-radius: 6px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
  transition: all 0.2s ease;
  
  &:active {
    background: #f5f5f5;
  }
  
  &.disabled {
    opacity: 0.6;
    background: #fafafa;
  }
  
  &.stock-out {
    border-left: 3px solid #ee0a24;
  }
  
  &.stock-low {
    border-left: 3px solid #ff976a;
  }
  
  .item-badge {
    position: absolute;
    top: -2px;
    right: -2px;
    background: #ee0a24;
    color: white;
    font-size: 10px;
    padding: 1px 4px;
    border-radius: 4px;
    
    &.warning {
      background: #ff976a;
    }
  }
  
  .item-content {
    flex: 1;
    min-width: 0;
  }
  
  .item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
    
    .sku-code {
      font-size: 13px;
      font-weight: 500;
      color: #323233;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
  }

  .product-info {
    display: flex;
    gap: 8px;
    margin-bottom: 4px;
    font-size: 11px;
    
    .product-name {
      color: #1989fa;
      font-weight: 500;
    }
    
    .product-no {
      color: #969799;
      background: #f7f8fa;
      padding: 1px 4px;
      border-radius: 2px;
    }
  }
  
  .item-details {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
    
    .spec {
      font-size: 12px;
      color: #646566;
      flex: 1;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      margin-right: 8px;
    }
    
    .prices {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      flex-shrink: 0;
      
      .sale {
        font-size: 14px;
        font-weight: bold;
        color: #ee0a24;
        line-height: 1.2;
      }
      
      .cost {
        font-size: 10px;
        color: #969799;
        line-height: 1.2;
      }
    }
  }
  
  .item-meta {
    display: flex;
    gap: 8px;
    font-size: 11px;
    color: #969799;
    
    .stock {
      &.out {
        color: #ee0a24;
        font-weight: 500;
      }
      
      &.low {
        color: #ff976a;
        font-weight: 500;
      }
    }
    
    .barcode {
      font-family: monospace;
      background: #f7f8fa;
      padding: 1px 3px;
      border-radius: 2px;
    }
  }
  
  .item-actions {
    margin-left: 8px;
    flex-shrink: 0;
  }
}

/* 响应式设计 */
@media (max-width: 320px) {
  .dense-item {
    padding: 8px 10px;
    
    .item-details {
      flex-direction: column;
      align-items: flex-start;
      
      .prices {
        flex-direction: row;
        align-items: center;
        gap: 8px;
        margin-top: 2px;
        
        .cost {
          font-size: 11px;
        }
      }
    }
  }
}
</style>