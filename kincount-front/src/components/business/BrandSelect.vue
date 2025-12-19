<!-- 修改 BrandSelect.vue 模板 -->
<template>
  <!-- 品牌选择器组件 -->
  <div>
    <!-- 触发器 -->
    <div v-if="!hideTrigger" class="brand-select" @click="openPicker">
      <!-- 插槽：自定义触发器 -->
      <slot name="trigger" :selected="selectedBrand" :open="openPicker">
        <!-- 默认触发器：简单按钮 -->
        <div class="default-trigger" :class="{ 'trigger-disabled': disabled }">
          <van-button
            :type="triggerButtonType"
            :size="triggerButtonSize"
            :block="triggerButtonBlock"
            :disabled="disabled"
            :loading="triggerLoading || loading"
          >
            <template v-if="selectedBrand">
              {{ displayText }}
            </template>
            <template v-else>
              {{ placeholder }}
            </template>
            <van-icon v-if="showTriggerIcon" :name="triggerIcon" />
          </van-button>
        </div>
      </slot>
    </div>

    <!-- 品牌选择弹出层 -->
    <van-popup
      v-model:show="showPicker"
      :position="popupPosition"
      round
      :style="popupStyle"
      :close-on-click-overlay="closeOnClickOverlay"
      @closed="handlePopupClosed"
    >
      <div class="brand-picker">
        <!-- 标题和关闭按钮 -->
        <div class="picker-header">
          <div class="header-title">{{ popupTitle }}</div>
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
            :placeholder="searchPlaceholder"
            @update:model-value="handleSearchInput"
            @search="handleSearch"
            @clear="handleClearSearch"
          />
        </div>

        <!-- 品牌列表 -->
        <div class="brand-list-container">
          <div class="brand-list" v-if="!loading">
            <!-- 全部品牌选项 -->
            <div
              v-if="showAllOption"
              class="brand-item"
              :class="{ 'brand-item-selected': isSelected(0) }"
              @click="handleBrandSelect({ id: 0, name: '全部品牌' })"
            >
              <div class="brand-info">
                <div class="brand-name">全部品牌</div>
              </div>
              <div class="brand-status">
                <van-icon
                  v-if="isSelected(0)"
                  name="success"
                  color="#07c160"
                />
              </div>
            </div>

            <!-- 品牌列表项 -->
            <div
              v-for="brand in filteredBrands"
              :key="brand.id"
              class="brand-item"
              :class="{
                'brand-item-selected': isSelected(brand.id),
                'brand-item-disabled': brand.status === 0
              }"
              @click="handleBrandSelect(brand)"
            >
              <div class="brand-info">
                <div class="brand-name">{{ brand.name }}</div>
                <div v-if="brand.code" class="brand-code">{{ brand.code }}</div>
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
                  v-if="isSelected(brand)"
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
            v-if="!loading && filteredBrands.length === 0 && (!showAllOption || searchKeyword)"
            :description="searchKeyword ? '未找到相关品牌' : '暂无品牌数据'"
          />
        </div>
        
        <!-- 操作按钮 -->
        <div v-if="showActions" class="picker-actions">
          <van-button type="default" @click="handleCancel">{{ cancelButtonText }}</van-button>
          <van-button 
            type="primary" 
            @click="handleConfirm"
            :disabled="!tempSelection"
          >
            {{ confirmButtonText }}
          </van-button>
        </div>
      </div>
    </van-popup>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { showToast } from 'vant'
import { useBrandStore } from '@/store/modules/brand'

const props = defineProps({
  // 双向绑定的品牌ID或对象
  modelValue: {
    type: [Number, String, Object],
    default: null
  },
  
  // 触发按钮配置
  hideTrigger: {
    type: Boolean,
    default: false
  },
  placeholder: {
    type: String,
    default: '选择品牌'
  },
  disabled: {
    type: Boolean,
    default: false
  },
  triggerButtonType: {
    type: String,
    default: 'default',
    validator: (value) => ['default', 'primary', 'success', 'warning', 'danger'].includes(value)
  },
  triggerButtonSize: {
    type: String,
    default: 'normal',
    validator: (value) => ['large', 'normal', 'small', 'mini'].includes(value)
  },
  triggerButtonBlock: {
    type: Boolean,
    default: true
  },
  triggerLoading: {
    type: Boolean,
    default: false
  },
  showTriggerIcon: {
    type: Boolean,
    default: true
  },
  triggerIcon: {
    type: String,
    default: 'arrow-down'
  },
  
  // 弹窗配置
  popupTitle: {
    type: String,
    default: '选择品牌'
  },
  popupPosition: {
    type: String,
    default: 'bottom',
    validator: (value) => ['bottom', 'center', 'top'].includes(value)
  },
  popupStyle: {
    type: Object,
    default: () => ({ height: '80%' })
  },
  searchPlaceholder: {
    type: String,
    default: '搜索品牌'
  },
  closeOnClickOverlay: {
    type: Boolean,
    default: true
  },
  showActions: {
    type: Boolean,
    default: false
  },
  showConfirmButton: {
    type: Boolean,
    default: false
  },
  confirmButtonText: {
    type: String,
    default: '确定'
  },
  cancelButtonText: {
    type: String,
    default: '取消'
  },
  
  // 数据配置
  onlyEnabled: {
    type: Boolean,
    default: true
  },
  allowDisabled: {
    type: Boolean,
    default: false
  },
  returnObject: {
    type: Boolean,
    default: false
  },
  immediate: {
    type: Boolean,
    default: true
  },
  useStoreCache: {
    type: Boolean,
    default: true
  },
  showAllOption: {
    type: Boolean,
    default: false
  },
  allBrandValue: {
    type: [Number, String],
    default: 0
  },
  excludeIds: {
    type: Array,
    default: () => []
  },
  autoLoad: {
    type: Boolean,
    default: true
  },
  immediateSelect: {
    type: Boolean,
    default: true
  },
  useSearchDebounce: {
    type: Boolean,
    default: true
  },
  debounceDelay: {
    type: Number,
    default: 500
  }
})

const emit = defineEmits(['update:modelValue', 'change', 'select', 'confirm', 'cancel', 'clear', 'search'])

// 使用状态管理
const brandStore = useBrandStore()

// 状态
const showPicker = ref(false)
const searchKeyword = ref('')
const loading = ref(false)
const tempSelection = ref(null)

// 防抖定时器
let searchTimer = null

// 计算属性
const brandList = computed(() => {
  return brandStore.list || []
})

const selectedBrand = computed(() => {
  if (!props.modelValue) return null
  
  // 如果返回的是对象
  if (typeof props.modelValue === 'object' && props.modelValue !== null) {
    return props.modelValue
  }
  
  // 如果是ID，从状态管理中查找
  const brand = brandList.value.find(item => item.id == props.modelValue)
  return brand || null
})

const displayText = computed(() => {
  if (!selectedBrand.value) return ''
  return selectedBrand.value.name || ''
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
const isSelected = (id) => {
  if (props.showActions) {
    // 显示操作按钮时，使用临时选择
    if (props.returnObject) {
      return tempSelection.value && tempSelection.value.id === id
    } else {
      return tempSelection.value == id
    }
  }
  
  // 不显示操作按钮时，直接使用modelValue
  if (props.returnObject) {
    return props.modelValue && props.modelValue.id === id
  } else {
    return props.modelValue == id
  }
}

// 打开选择器
const openPicker = () => {
  if (props.disabled) return
  
  showPicker.value = true
}

// 加载品牌数据
const loadBrands = async () => {
  if (loading.value) return

  loading.value = true
  try {
    // 使用状态管理加载数据
    await brandStore.loadList()
  } catch (error) {
    console.error('加载品牌列表失败:', error)
    showToast('加载品牌列表失败')
  } finally {
    loading.value = false
  }
}

// 搜索处理
const handleSearch = () => {
  // 搜索逻辑已经在filteredBrands计算属性中处理
  emit('search', searchKeyword.value)
}

const handleSearchInput = () => {
  if (props.useSearchDebounce) {
    // 使用防抖搜索
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
      handleSearch()
    }, props.debounceDelay)
  } else {
    // 立即搜索
    handleSearch()
  }
}

const handleClearSearch = () => {
  searchKeyword.value = ''
  if (props.useSearchDebounce) {
    handleSearch()
  }
}

// 选择品牌
const handleBrandSelect = (brand) => {
  // 检查是否允许选择已禁用的品牌
  if (brand.status === 0 && !props.allowDisabled) {
    showToast('该品牌已禁用，无法选择')
    return
  }

  // 如果显示操作按钮，使用临时选择
  if (props.showActions) {
    if (props.returnObject) {
      tempSelection.value = tempSelection.value?.id === brand.id ? null : brand
    } else {
      tempSelection.value = tempSelection.value === brand.id ? null : brand.id
    }
    return
  }
  
  // 直接确认选择
  const newValue = props.returnObject ? brand : brand.id
  emit('update:modelValue', newValue)
  emit('change', newValue, brand ? brand.name : '')
  emit('select', brand)
  
  // 关闭弹窗
  showPicker.value = false
  
  // 清空搜索关键词
  searchKeyword.value = ''
}

// 确认选择
const handleConfirm = () => {
  if (!tempSelection.value) return
  
  let newValue
  if (props.returnObject) {
    // 查找完整的品牌对象
    const brand = brandList.value.find(item => item.id == tempSelection.value.id)
    if (!brand) return
    newValue = brand
  } else {
    newValue = tempSelection.value
  }
  
  emit('update:modelValue', newValue)
  emit('change', newValue, selectedBrand.value ? selectedBrand.value.name : '')
  emit('confirm', newValue)
  
  // 关闭弹窗
  showPicker.value = false
  searchKeyword.value = ''
  tempSelection.value = null
}

// 取消选择
const handleCancel = () => {
  showPicker.value = false
  searchKeyword.value = ''
  tempSelection.value = null
  emit('cancel')
}

// 弹窗关闭处理
const handlePopupClosed = () => {
  searchKeyword.value = ''
  tempSelection.value = null
}

// 监听弹窗显示/隐藏
watch(showPicker, (newVal) => {
  if (newVal) {
    // 初始化临时选择
    if (props.showActions) {
      if (props.returnObject) {
        tempSelection.value = selectedBrand.value
      } else {
        tempSelection.value = props.modelValue || null
      }
    }
    
    // 弹窗显示时，清空搜索关键词
    searchKeyword.value = ''

    // 如果品牌列表为空，则加载数据
    if (brandList.value.length === 0 || !props.useStoreCache) {
      loadBrands()
    }

    // 自动聚焦搜索框（需要等待弹窗渲染完成）
    nextTick(() => {
      const searchInput = document.querySelector('.brand-picker .van-search__field')
      if (searchInput) {
        searchInput.focus()
      }
    })
  } else {
    // 弹窗关闭时，重置临时选择和搜索关键词
    tempSelection.value = null
    searchKeyword.value = ''
  }
})

// 监听外部值变化
watch(() => props.modelValue, (newVal) => {
  // 可以处理外部值变化时的逻辑
}, { immediate: true })

// 组件挂载时预加载品牌数据
onMounted(() => {
  if (props.immediate && props.useStoreCache) {
    loadBrands()
  }
})

// 导出方法给父组件
defineExpose({
  openPicker,
  closePicker: () => { showPicker.value = false },
  loadBrands,
  clear: () => {
    emit('update:modelValue', null)
    searchKeyword.value = ''
  },
  getSelectedBrand: () => selectedBrand.value,
  refresh: () => { loadBrands() }
})
</script>

<style scoped>
.brand-select {
  display: inline-block;
  width: 100%;
  
  .trigger-disabled {
    cursor: not-allowed;
    opacity: 0.6;
  }
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
  
  &.brand-item-selected {
    background-color: #f0f9ff;
  }
  
  &.brand-item-disabled {
    opacity: 0.6;
    cursor: not-allowed;
    
    &:hover,
    &:active {
      background-color: transparent;
    }
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

/* 操作按钮 */
.picker-actions {
  display: flex;
  padding: 12px 16px;
  border-top: 1px solid #ebedf0;
  gap: 12px;
  
  .van-button {
    flex: 1;
  }
}

/* 响应式调整 */
@media (max-width: 768px) {
  .picker-header {
    padding: 14px;
  }
  
  .search-box {
    padding: 8px 14px;
  }
  
  .brand-item {
    padding: 10px 14px;
  }
  
  .picker-actions {
    padding: 10px 14px;
  }
}
</style>