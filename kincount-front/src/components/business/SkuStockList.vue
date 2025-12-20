<template>
  <!-- 列表模式 -->
  <van-pull-refresh v-model="refreshing" @refresh="onRefresh">
    <van-list
      v-model:loading="loading"
      :finished="finished"
      finished-text="没有更多"
      @load="onLoad"
    >
      <div class="sku-card-list">
        <div 
          v-for="item in list" 
          :key="item.id" 
          class="sku-card"
          @click="$emit('click-card', item)"
        >
          <!-- 选中状态勾号 -->
          <div v-if="selectable && selectedIds.includes(item.id)" class="selected-check">
            <van-icon name="success" size="16" color="#fff" />
          </div>
          
          <!-- 信息区域 -->
          <div class="sku-info">
            <!-- 第一行：商品名称 + 售价 -->
            <div class="row product-row">
              <div class="product-name">{{ getProductName(item) }}</div>
              <div class="sku-price">¥{{ getPrice(item) }}</div>
            </div>
            
            <!-- 第二行：SKU编码 + 库存 -->
            <div class="row sku-row">
              <div class="sku-code">{{ getSkuCode(item) }}</div>
              <div class="stock-info" :class="{ warning: isWarning(item) }">
                <van-icon name="bag-o" size="12" />
                库存: {{ item.quantity || 0 }}
              </div>
            </div>
            
            <!-- 第三行：规格 + 仓库 -->
            <div class="row spec-row">
              <div class="sku-spec">{{ getSpecText(item) }}</div>
              <div class="warehouse-info">
                <van-icon name="location-o" size="12" />
                {{ getWarehouseName(item) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </van-list>
  </van-pull-refresh>
</template>

<script setup>
import { ref, watch } from 'vue'
import { getStockList } from '@/api/stock'

const props = defineProps({
  // 列表模式参数
  warehouseId: [String, Number],
  keyword: String,
  warningType: String, // low | high
  
  // 选择模式参数
  selectable: {
    type: Boolean,
    default: false
  },
  selectedIds: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['click-card'])

const list = ref([])
const loading = ref(false)
const finished = ref(false)
const refreshing = ref(false)

// 图片错误处理记录
const imageErrorMap = ref(new Map())

// 获取商品名称（最重要的信息）
const getProductName = (item) => {
  // 优先从 product 对象获取商品名称
  if (item.sku?.product?.name) return item.sku.product.name
  if (item.product?.name) return item.product.name
  
  // 如果商品名称不存在，尝试从 SKU 编码推断（实际项目中可能需要额外API调用）
  if (item.sku?.sku_code) {
    // 假设 SKU 编码格式为 "商品名-SKU编号"，尝试提取商品名
    const skuCode = item.sku.sku_code
    const dashIndex = skuCode.lastIndexOf('-')
    if (dashIndex > 0) {
      return skuCode.substring(0, dashIndex)
    }
    return skuCode
  }
  
  // 最后回退方案
  return `商品-${item.sku_id || item.id}`
}

// 获取 SKU 编码
const getSkuCode = (item) => {
  if (item.sku && item.sku.sku_code) return item.sku.sku_code
  if (item.sku_code) return item.sku_code
  return `SKU-${item.sku_id || item.id}`
}

// 获取规格文本
const getSpecText = (item) => {
  const spec = item.sku?.spec || item.spec
  if (!spec) return '无规格'
  
  if (typeof spec === 'string') {
    try {
      const parsedSpec = JSON.parse(spec)
      return Object.values(parsedSpec).join(' / ')
    } catch {
      return spec
    }
  }
  
  if (typeof spec === 'object') {
    return Object.values(spec).join(' / ')
  }
  
  return '无规格'
}

// 获取价格
const getPrice = (item) => {
  if (item.sku?.sale_price) return item.sku.sale_price
  if (item.sale_price) return item.sale_price
  return '0.00'
}

// 获取产品图片 - 优化版本
const getProductImage = (item) => {
  // 检查是否已经记录过图片错误
  const itemId = item.id
  if (imageErrorMap.value.has(itemId)) {
    return defaultImage
  }
  
  // 尝试获取图片URL
  let imageUrl = ''
  if (item.sku?.product?.image) {
    imageUrl = item.sku.product.image
  } else if (item.product?.image) {
    imageUrl = item.product.image
  }
  
  // 验证图片URL有效性
  if (!imageUrl || imageUrl === 'null' || imageUrl === 'undefined') {
    imageErrorMap.value.set(itemId, true)
    return defaultImage
  }
  
  return imageUrl
}

// 图片加载失败处理 - 优化版本
const handleImageError = (event) => {
  const img = event.target
  const itemId = img.dataset.itemId
  
  // 记录图片加载失败
  if (itemId) {
    imageErrorMap.value.set(itemId, true)
  }
  
  // 立即替换为默认图片，避免反复重试
  img.src = defaultImage
  
  // 移除错误监听，防止后续再次触发
  img.onerror = null
}

// 获取仓库名称
const getWarehouseName = (item) => {
  if (item.warehouse?.name) return item.warehouse.name
  if (item.warehouse_name) return item.warehouse_name
  return '未知仓库'
}

// 获取预警标签
const getWarningTag = (item) => {
  // 根据业务逻辑设置预警标签
  const quantity = item.quantity || 0
  if (quantity === 0) return '缺货'
  if (quantity < 10) return '低库存'
  if (quantity > 500) return '高库存'
  return ''
}

// 判断是否预警
const isWarning = (item) => {
  const quantity = item.quantity || 0
  return quantity === 0 || quantity < 10 || quantity > 500
}

let page = 1
let isLoading = false // 加载锁，防止重复请求

const onLoad = async () => {
  if (isLoading) return
  
  try {
    isLoading = true
    loading.value = true
    
    const res = await getStockList({
      page,
      limit: 10,
      warehouse_id: props.warehouseId,
      keyword: props.keyword,
      warning_type: props.warningType
    })
    
    // 处理响应数据 - 注意拦截器已经过滤了数据，直接使用 res
    let dataList = []
    if (res && res.list) {
      dataList = res.list
    } else if (Array.isArray(res)) {
      dataList = res
    } else if (res && res.data && res.data.list) {
      dataList = res.data.list
    }
    
    if (refreshing.value) {
      list.value = []
      refreshing.value = false
      // 清空图片错误记录
      imageErrorMap.value.clear()
    }
    
    if (dataList && dataList.length > 0) {
      list.value.push(...dataList)
      finished.value = dataList.length < 10
      page++
    } else {
      finished.value = true
    }
    
  } catch (error) {
    console.error('加载库存数据失败:', error)
    finished.value = true
  } finally {
    loading.value = false
    isLoading = false
  }
}

const onRefresh = () => {
  page = 1
  finished.value = false
  loading.value = true
  // 清空图片错误记录
  imageErrorMap.value.clear()
  onLoad()
}

// 监听 props 变化重新加载
watch(
  () => [props.warehouseId, props.keyword, props.warningType],
  () => {
    page = 1
    list.value = []
    finished.value = false
    // 清空图片错误记录
    imageErrorMap.value.clear()
    onLoad()
  }
)
</script>

<style scoped lang="scss">


/* 列表模式样式（原有） */
.sku-card-list {
  padding: 8px;
}

.sku-card {
  display: flex;
  background: #fff;
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: all 0.2s ease;
  position: relative;
  
  &:active {
    background: #f5f5f5;
  }
  
  .selected-check {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 20px;
    height: 20px;
    background: #07c160;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
  }
  
  .sku-info {
    flex: 1;
    min-width: 0; /* 防止内容溢出 */
    
    .row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 6px;
      
      &:last-child {
        margin-bottom: 0;
      }
    }
    
    .product-row {
      .product-name {
        font-size: 15px;
        font-weight: 600;
        color: #333;
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        margin-right: 8px;
      }
      
      .sku-price {
        font-size: 15px;
        font-weight: bold;
        color: #ff4444;
        flex-shrink: 0;
      }
    }
    
    .sku-row {
      .sku-code {
        font-size: 12px;
        color: #666;
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        margin-right: 8px;
      }
      
      .stock-info {
        display: flex;
        align-items: center;
        font-size: 12px;
        color: #999;
        flex-shrink: 0;
        
        :deep(.van-icon) {
          margin-right: 2px;
        }
        
        &.warning {
          color: #ff4444;
          font-weight: 500;
        }
      }
    }
    
    .spec-row {
      .sku-spec {
        font-size: 12px;
        color: #888;
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        margin-right: 8px;
      }
      
      .warehouse-info {
        display: flex;
        align-items: center;
        font-size: 12px;
        color: #999;
        flex-shrink: 0;
        
        :deep(.van-icon) {
          margin-right: 2px;
        }
      }
    }
  }
}

/* 响应式调整 */
@media (max-width: 320px) {
  .sku-card {
    padding: 8px;
    
    .sku-image {
      width: 50px;
      height: 50px;
      margin-right: 8px;
    }
    
    .sku-info {
      .product-row {
        .product-name {
          font-size: 14px;
        }
        
        .sku-price {
          font-size: 14px;
        }
      }
    }
  }
}
</style>