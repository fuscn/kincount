<!-- kincount\kincount-front\src\components\business\ProductCard.vue -->
<template>
  <Card class="product-card" @click="handleClick">
    <template #thumb>
      <Image 
        :src="firstImage" 
        width="88" 
        height="88" 
        fit="cover" 
        :alt="name"
        :show-loading="true"
        :show-error="true"
        @load="onImageLoad"
        @error="onImageError"
      />
    </template>
    <template #title>
      <div class="title">{{ name }}</div>
    </template>
    <template #desc>
      <div class="sku">编号：{{ productNo }} | 规格：{{ spec }}</div>
      <div class="category-brand">
        <span v-if="categoryName" class="category">{{ categoryName }}</span>
        <span v-if="brandName" class="brand">{{ brandName }}</span>
      </div>
      <div class="price">
        <span class="cost">成本 ¥{{ formatPrice(costPrice) }}</span>
        <span class="sale">售价 ¥{{ formatPrice(salePrice) }}</span>
      </div>
      <div class="stock-info">
        <Tag :type="stockTagType">
          库存 {{ stock }}{{ unit }}
        </Tag>
        <span v-if="showStockWarning" class="warning-text">(低于安全库存)</span>
      </div>
    </template>
  </Card>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Card, Image, Tag, showToast } from 'vant'

const props = defineProps({
  name:        { type: String, default: '' },
  productNo:   { type: String, default: '' },
  spec:        { type: String, default: '' },
  unit:        { type: String, default: '' },
  costPrice:   { type: [String, Number], default: 0 },
  salePrice:   { type: [String, Number], default: 0 },
  stock:       { type: Number, default: 0 },
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

// 使用 ref 来管理图片状态，避免计算属性的重复计算
const imageLoadError = ref(false)
const imageLoading = ref(false)

// 优化图片处理
const firstImage = computed(() => {
  // 重置错误状态
  if (imageLoadError.value) {
    imageLoadError.value = false
  }
  
  let images = props.images
  
  // 统一处理 images 数据
  if (!images) {
    return getDefaultImage()
  }
  
  // 如果是字符串，尝试转换为数组
  if (typeof images === 'string') {
    try {
      images = JSON.parse(images)
    } catch {
      images = [images]
    }
  }
  
  // 如果是对象，转换为数组
  if (images && typeof images === 'object' && !Array.isArray(images)) {
    images = Object.values(images)
  }
  
  // 确保是数组
  if (!Array.isArray(images)) {
    return getDefaultImage()
  }
  
  // 过滤空值
  const validImages = images.filter(img => img && typeof img === 'string')
  
  if (validImages.length === 0) {
    return getDefaultImage()
  }
  
  return validImages[0]
})

const getDefaultImage = () => {
  // 使用相对路径，避免绝对路径问题
  return './src/assets/default-product.png'
}

const stockTagType = computed(() => {
  const stockNum = Number(props.stock) || 0
  const minStockNum = Number(props.minStock) || 0
  
  if (stockNum <= minStockNum) return 'danger'
  if (stockNum <= minStockNum * 1.5) return 'warning'
  return 'primary'
})

const showStockWarning = computed(() => {
  const stockNum = Number(props.stock) || 0
  const minStockNum = Number(props.minStock) || 0
  return stockNum <= minStockNum
})

const categoryName = computed(() => {
  return props.category?.name || ''
})

const brandName = computed(() => {
  return props.brand?.name || ''
})

// 格式化价格
const formatPrice = (price) => {
  const num = Number(price)
  if (isNaN(num)) return '0.00'
  return num.toFixed(2)
}

// 图片加载事件
const onImageLoad = () => {
  imageLoading.value = false
  console.log('✅ 商品图片加载成功:', props.productId)
}

const onImageError = (error) => {
  imageLoading.value = false
  imageLoadError.value = true
  console.warn('❌ 商品图片加载失败:', props.productId, error)
}

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
  
  .title {
    font-weight: 500;
    margin-bottom: 4px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  
  .sku {
    font-size: 12px;
    color: #969799;
    margin: 4px 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  
  .category-brand {
    margin: 4px 0;
    font-size: 12px;
    
    .category, .brand {
      display: inline-block;
      background: #f2f3f5;
      padding: 2px 6px;
      border-radius: 4px;
      margin-right: 6px;
      color: #646566;
    }
  }
  
  .price {
    margin: 4px 0;
    
    .cost {
      margin-right: 12px;
      color: #ee0a24;
      font-size: 14px;
    }
    
    .sale {
      color: #323233;
      font-size: 14px;
      font-weight: 500;
    }
  }
  
  .stock-info {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 4px;
    
    .warning-text {
      font-size: 12px;
      color: #ee0a24;
    }
  }
}
</style>