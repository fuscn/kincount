<!-- kincount\kincount-front\src\components\business\ProductCard.vue -->
<template>
  <Card class="product-card" @click="handleClick">
    <template #thumb>
      <div class="product-icon">
        <div class="icon-placeholder">
          <!-- 后期可替换为实际图标 -->
          <div class="default-icon">📦</div>
        </div>
      </div>
    </template>
    <template #title>
      <div class="title-line">
        <div class="title">{{ name }}</div>
        <div class="product-no">#{{ productNo }}</div>
      </div>
    </template>
    <template #desc>
      <!-- 第一行：分类品牌 + 库存 -->
      <div class="first-line">
        <div class="category-brand">
          <span v-if="categoryName" class="category">{{ categoryName }}</span>
          <span v-if="brandName" class="brand">{{ brandName }}</span>
        </div>
        <div class="stock-info">
          <Tag :type="stockTagType" size="small">
            库存 {{ stock }}{{ unit }}
          </Tag>
          <span v-if="showStockWarning" class="warning-text">需补货</span>
        </div>
      </div>
      
      <!-- 第二行：价格信息 -->
      <!-- <div class="second-line">
        <div class="price-info">
          <span class="cost">成本 ¥{{ formatPrice(costPrice) }}</span>
          <span class="sale">售价 ¥{{ formatPrice(salePrice) }}</span>
        </div>
      </div> -->
    </template>
  </Card>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Card, Tag } from 'vant'
import { useProductStore } from '@/store/modules/product'

const props = defineProps({
  name:        { type: String, default: '' },
  productNo:   { type: String, default: '' },
  unit:        { type: String, default: '' },
  costPrice:   { type: [String, Number], default: 0 },
  salePrice:   { type: [String, Number], default: 0 },
  minStock:    { type: Number, default: 0 },
  images:      { 
    type: [Array, Object, String], 
    default: () => [] 
  },
  category:    { type: Object, default: () => ({}) },
  brand:       { type: Object, default: () => ({}) },
  productId:   { type: [String, Number], default: '' }
})

const emit = defineEmits(['click'])
const productStore = useProductStore()

// 从状态管理获取商品总库存
const stock = computed(() => {
  return productStore.getProductStock(props.productId) || 0
})

// 库存标签类型
const stockTagType = computed(() => {
  const stockNum = Number(stock.value)
  const minStockNum = Number(props.minStock) || 0
  
  if (stockNum <= minStockNum) return 'danger'
  if (stockNum <= minStockNum * 1.5) return 'warning'
  return 'primary'
})

// 库存预警显示
const showStockWarning = computed(() => {
  const stockNum = Number(stock.value)
  const minStockNum = Number(props.minStock) || 0
  return stockNum <= minStockNum
})

// 分类名称
const categoryName = computed(() => {
  return props.category?.name || ''
})

// 品牌名称
const brandName = computed(() => {
  return props.brand?.name || ''
})

// 价格格式化
const formatPrice = (price) => {
  const num = Number(price)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

// 卡片点击事件
const handleClick = () => {
  emit('click', {
    id: props.productId,
    name: props.name,
    productNo: props.productNo
  })
}
</script>

<style scoped lang="scss">
.product-card {
  margin-bottom: 8px;
  transition: all 0.2s ease;
  
  &:active {
    transform: scale(0.98);
    background: #f8f9fa;
  }
  
  // 商品图标区域
  .product-icon {
    width: 88px;
    height: 88px;
    display: flex;
    align-items: center;
    justify-content: center;
    
    .icon-placeholder {
      width: 64px;
      height: 64px;
      background: #f5f5f5;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      
      .default-icon {
        font-size: 28px;
        opacity: 0.6;
      }
    }
  }
  
  // 标题行（第一行）
  .title-line {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
    
    .title {
      font-weight: 700; /* 加粗商品名称 */
      font-size: 15px;
      flex: 1;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      margin-right: 8px;
    }
    
    .product-no {
      font-size: 12px;
      color: #969799;
      white-space: nowrap;
    }
  }
  
  // 第一行：分类品牌 + 库存信息
  .first-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
    
    .category-brand {
      display: flex;
      gap: 4px;
      flex: 1;
      
      .category, .brand {
        font-size: 11px;
        background: #f2f3f5;
        padding: 2px 6px;
        border-radius: 10px;
        color: #646566;
        white-space: nowrap;
        max-width: 70px;
        overflow: hidden;
        text-overflow: ellipsis;
      }
    }
    
    .stock-info {
      display: flex;
      align-items: center;
      gap: 4px;
      
      .warning-text {
        font-size: 11px;
        color: #ee0a24;
        font-weight: 500;
        white-space: nowrap;
      }
    }
  }
  
  // 第二行：价格信息
  .second-line {
    .price-info {
      display: flex;
      gap: 12px;
      
      .cost {
        color: #ee0a24;
        font-size: 13px;
        font-weight: 500;
      }
      
      .sale {
        color: #323233;
        font-size: 13px;
        font-weight: 600;
      }
    }
  }
}
</style>