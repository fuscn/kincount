<template>
  <div class="product-card" @click="handleClick">
    <!-- 商品基本信息区域 -->
    <div class="product-header">
      <div class="product-title">
        <span class="product-name">{{ name }}</span>
        <span class="product-no">#{{ productNo }}</span>
      </div>
      
      <!-- 分类、品牌和库存信息在同一行 -->
      <div class="product-meta">
        <!-- 分类标签 -->
        <span v-if="categoryName" class="meta-tag category">
          {{ categoryName }}
        </span>
        
        <!-- 品牌标签 -->
        <span v-if="brandName" class="meta-tag brand">
          {{ brandName }}
        </span>
        
        <!-- 库存信息 -->
        <div class="stock-info" :class="stockLevelClass">
          <span class="stock-count">{{ totalStock !== undefined ? totalStock : 0 }}</span>
          <span class="stock-unit">{{ unit }}</span>
          <van-icon v-if="showStockWarning" name="warning" class="warning-icon" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, defineProps} from 'vue'

const props = defineProps({
  name: { 
    type: String, 
    required: true,
    default: ''
  },
  productNo: { 
    type: String, 
    default: '' 
  },
  unit: { 
    type: String, 
    default: '件' 
  },
  costPrice: { 
    type: [Number, String], 
    default: 0 
  },
  salePrice: { 
    type: [Number, String], 
    default: 0 
  },
  minStock: { 
    type: [Number, String], 
    default: 0 
  },
  category: { 
    type: [Object, String], 
    default: () => ({}) 
  },
  brand: { 
    type: [Object, String], 
    default: () => ({}) 
  },
  productId: { 
    type: [Number, String], 
    default: '' 
  },
  totalStock: { // 新增：从父组件传递的库存数据
    type: [Number, String],
    default: 0 // 改为默认0
  }
})

const emit = defineEmits(['click'])

// 分类名称
const categoryName = computed(() => {
  return typeof props.category === 'object' ? props.category?.name : props.category
})

// 品牌名称
const brandName = computed(() => {
  return typeof props.brand === 'object' ? props.brand?.name : props.brand
})

// 库存水平分类
const stockLevelClass = computed(() => {
  const stockNum = Number(props.totalStock)
  const minStockNum = Number(props.minStock) || 0
  
  if (stockNum <= 0) return 'stock-danger'
  if (stockNum <= minStockNum) return 'stock-warning'
  return 'stock-normal'
})

// 库存预警显示
const showStockWarning = computed(() => {
  const stockNum = Number(props.totalStock)
  const minStockNum = Number(props.minStock) || 0
  return stockNum <= minStockNum
})

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
  background: #fff;
  border-radius: 8px;
  padding: 12px 16px;
  margin-bottom: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  transition: all 0.2s ease;
  
  &:active {
    transform: translateY(1px);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
    background: #fafafa;
  }

  // 商品头部信息
  .product-header {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  // 商品标题行
  .product-title {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;

    .product-name {
      font-size: 15px;
      font-weight: 600;
      color: #1a1a1a;
      line-height: 1.4;
      flex: 1;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .product-no {
      font-size: 11px;
      color: #8c8c8c;
      background: #f5f5f5;
      padding: 2px 6px;
      border-radius: 3px;
      white-space: nowrap;
      flex-shrink: 0;
      align-self: flex-start;
      line-height: 1.4;
    }
  }

  // 元信息行：分类、品牌、库存
  .product-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    min-height: 24px;

    // 分类和品牌标签
    .meta-tag {
      font-size: 11px;
      padding: 2px 8px;
      border-radius: 10px;
      white-space: nowrap;
      line-height: 1.4;
      
      &.category {
        background: #e6f7ff;
        color: #1890ff;
        border: 1px solid #91d5ff;
      }
      
      &.brand {
        background: #f6ffed;
        color: #52c41a;
        border: 1px solid #b7eb8f;
      }
    }

    // 库存信息
    .stock-info {
      display: flex;
      align-items: center;
      gap: 4px;
      padding: 2px 8px;
      border-radius: 10px;
      font-size: 11px;
      font-weight: 500;
      line-height: 1.4;
      margin-left: auto; // 靠右对齐
      white-space: nowrap;
      
      .stock-count {
        font-weight: 600;
      }
      
      .stock-unit {
        opacity: 0.8;
      }
      
      .warning-icon {
        font-size: 10px;
        margin-left: 2px;
      }
      
      // 库存状态样式
      &.stock-danger {
        background: #fff2f0;
        color: #f5222d;
        border: 1px solid #ffccc7;
        
        .warning-icon {
          color: #f5222d;
        }
      }
      
      &.stock-warning {
        background: #fff7e6;
        color: #fa8c16;
        border: 1px solid #ffd591;
        
        .warning-icon {
          color: #fa8c16;
        }
      }
      
      &.stock-normal {
        background: #f6f6f6;
        color: #595959;
        border: 1px solid #d9d9d9;
      }
    }
  }
}

// 响应式设计
@media (max-width: 375px) {
  .product-card {
    padding: 10px 12px;
    
    .product-title {
      .product-name {
        font-size: 14px;
      }
    }
    
    .product-meta {
      .stock-info {
        font-size: 10px;
        padding: 2px 6px;
      }
    }
  }
}
</style>