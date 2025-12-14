<!-- src/components/business/BrandSelect.vue -->
<template>
  <div class="brand-select">
    <!-- 选择器触发按钮 -->
    <van-field
      v-model="selectedName"
      :label="label"
      :placeholder="placeholder"
      :required="required"
      :readonly="readonly"
      :disabled="disabled"
      :rules="rules"
      is-link
      readonly
      @click="handleFieldClick"
    />
    
    <!-- 品牌选择弹窗 -->
    <van-popup
      v-model:show="showPicker"
      position="bottom"
      round
      :style="{ height: '80%' }"
    >
      <div class="brand-picker">
        <!-- 标题和关闭按钮 -->
        <div class="picker-header">
          <div class="header-title">{{ label || '选择品牌' }}</div>
          <van-icon
            name="cross"
            class="close-icon"
            @click="showPicker = false"
          />
        </div>
        
        <!-- 搜索框 -->
        <div class="search-box">
          <van-search
            v-model="searchKeyword"
            placeholder="搜索品牌"
            @update:model-value="handleSearch"
            @clear="handleClear"
          />
        </div>
        
        <!-- 品牌列表 -->
        <div class="brand-list-container">
          <div class="brand-list" v-if="!loading">
            <div
              v-for="brand in filteredBrands"
              :key="brand.id"
              class="brand-item"
              :class="{ 'brand-item-disabled': brand.status === 0 }"
              @click="handleBrandSelect(brand)"
            >
              <div class="brand-info">
                <div class="brand-name">{{ brand.name }}</div>
                <!-- <div v-if="brand.code" class="brand-code">{{ brand.code }}</div> -->
              </div>
              
              <div class="brand-status">
                <van-tag
                  v-if="brand.status === 0"
                  size="small"
                  type="danger"
                  plain
                >
                  已禁用
                </van-tag>
                <van-icon
                  v-if="isSelected(brand.id)"
                  name="success"
                  color="#07c160"
                />
              </div>
            </div>
          </div>
          
          <!-- 加载中 -->
          <div class="loading" v-else>
            <van-loading type="spinner" size="24px" />
            <span class="loading-text">加载中...</span>
          </div>
          
          <!-- 空状态 -->
          <van-empty
            v-if="!loading && filteredBrands.length === 0"
            :description="searchKeyword ? '未找到相关品牌' : '暂无品牌数据'"
          />
        </div>
      </div>
    </van-popup>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { showToast } from 'vant'
import { getBrandOptions } from '@/api/brand'

const props = defineProps({
  // 双向绑定的品牌ID
  modelValue: {
    type: [Number, String],
    default: null
  },
  // 标签文本
  label: {
    type: String,
    default: '品牌'
  },
  // 占位符
  placeholder: {
    type: String,
    default: '请选择品牌'
  },
  // 是否必填
  required: {
    type: Boolean,
    default: false
  },
  // 是否只读
  readonly: {
    type: Boolean,
    default: false
  },
  // 是否禁用
  disabled: {
    type: Boolean,
    default: false
  },
  // 验证规则
  rules: {
    type: Array,
    default: () => []
  },
  // 是否只显示启用状态的品牌
  onlyEnabled: {
    type: Boolean,
    default: true
  },
  // 是否允许选择已禁用的品牌
  allowDisabled: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'change'])

// 状态
const showPicker = ref(false)
const searchKeyword = ref('')
const loading = ref(false)
const brandList = ref([])

// 计算属性
const selectedName = computed(() => {
  if (!props.modelValue) return ''
  const brand = brandList.value.find(item => item.id == props.modelValue)
  return brand ? brand.name : ''
})

const filteredBrands = computed(() => {
  let brands = brandList.value
  
  // 过滤状态
  if (props.onlyEnabled && !props.allowDisabled) {
    brands = brands.filter(brand => brand.status !== 0)
  }
  
  // 搜索过滤
  if (searchKeyword.value.trim()) {
    const keyword = searchKeyword.value.toLowerCase()
    return brands.filter(brand => {
      // 匹配品牌名称
      if (brand.name && brand.name.toLowerCase().includes(keyword)) {
        return true
      }
      // 匹配品牌编码
      if (brand.code && brand.code.toLowerCase().includes(keyword)) {
        return true
      }
      return false
    })
  }
  
  return brands
})

// 检查是否选中
const isSelected = (brandId) => {
  return props.modelValue == brandId
}

// 点击字段触发
const handleFieldClick = () => {
  if (props.disabled || props.readonly) return
  
  showPicker.value = true
}

// 加载品牌数据
const loadBrands = async () => {
  if (loading.value) return
  
  loading.value = true
  try {
    const res = await getBrandOptions()
    if (res.code === 200) {
      brandList.value = Array.isArray(res.data) ? res.data : []
    } else {
      brandList.value = []
      showToast(res.msg || '加载品牌列表失败')
    }
  } catch (error) {
    console.error('加载品牌列表失败:', error)
    brandList.value = []
    showToast('加载品牌列表失败')
  } finally {
    loading.value = false
  }
}

// 搜索处理
const handleSearch = () => {
  // 搜索逻辑已经在filteredBrands计算属性中处理
  // 这里可以添加防抖处理，但当前简单实现
}

const handleClear = () => {
  searchKeyword.value = ''
}

// 选择品牌
const handleBrandSelect = (brand) => {
  // 检查是否允许选择已禁用的品牌
  if (brand.status === 0 && !props.allowDisabled) {
    showToast('该品牌已禁用，无法选择')
    return
  }
  
  // 如果选择的是已选中的品牌，则不重复选择
  if (brand.id === props.modelValue) {
    showPicker.value = false
    return
  }
  
  // 更新选中值
  emit('update:modelValue', brand.id)
  emit('change', brand)
  
  // 关闭弹窗
  showPicker.value = false
  
  // 清空搜索关键词（可选）
  // searchKeyword.value = ''
}

// 监听弹窗显示/隐藏
watch(showPicker, (newVal) => {
  if (newVal) {
    // 弹窗显示时，清空搜索关键词
    searchKeyword.value = ''
    
    // 如果品牌列表为空，则加载数据
    if (brandList.value.length === 0) {
      loadBrands()
    }
    
    // 自动聚焦搜索框（需要等待弹窗渲染完成）
    nextTick(() => {
      const searchInput = document.querySelector('.brand-picker .van-search__field')
      if (searchInput) {
        searchInput.focus()
      }
    })
  }
})

// 监听外部值变化
watch(() => props.modelValue, (newVal) => {
  // 这里可以处理外部值变化时的逻辑
}, { immediate: true })

// 组件挂载时预加载品牌数据（可选）
onMounted(() => {
  // 可以在这里预加载，或者在弹窗打开时懒加载
  // loadBrands()
})
</script>

<style scoped>
.brand-select {
  width: 100%;
}

.brand-picker {
  height: 100%;
  display: flex;
  flex-direction: column;
  background-color: #fff;
}

.picker-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px;
  border-bottom: 1px solid #ebedf0;
  
  .header-title {
    font-size: 16px;
    font-weight: 600;
    color: #323233;
  }
  
  .close-icon {
    font-size: 18px;
    color: #969799;
    cursor: pointer;
  }
}

.search-box {
  padding: 10px 16px;
  background-color: #f7f8fa;
  border-bottom: 1px solid #ebedf0;
}

.brand-list-container {
  flex: 1;
  overflow-y: auto;
}

.brand-list {
  padding: 8px 0;
}

.brand-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-bottom: 1px solid #f5f5f5;
  cursor: pointer;
  transition: background-color 0.2s;
  
  &:hover {
    background-color: #f7f8fa;
  }
  
  &:active {
    background-color: #ebedf0;
  }
  
  &.brand-item-disabled {
    opacity: 0.6;
  }
}

.brand-info {
  flex: 1;
  margin-right: 12px;
  
  .brand-name {
    font-size: 14px;
    font-weight: 500;
    color: #323233;
    margin-bottom: 4px;
  }
  
  .brand-code {
    font-size: 12px;
    color: #969799;
  }
}

.brand-status {
  display: flex;
  align-items: center;
  gap: 8px;
  
  :deep(.van-tag) {
    flex-shrink: 0;
  }
  
  .van-icon-success {
    font-size: 16px;
    flex-shrink: 0;
  }
}

.loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 0;
  
  .loading-text {
    margin-top: 12px;
    font-size: 14px;
    color: #969799;
  }
}

/* 空状态样式调整 */
:deep(.van-empty) {
  padding: 40px 0;
}

/* 确保弹窗内容不会超出屏幕 */
:deep(.van-popup) {
  overflow: hidden;
}

/* 调整搜索框样式 */
:deep(.van-search) {
  padding: 0;
  
  .van-search__content {
    border-radius: 16px;
    background-color: #fff;
  }
}
</style>