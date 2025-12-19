<template>
  <!-- 触发区域 -->
  <div v-if="!hideTrigger" class="warehouse-select-trigger" @click="openPicker">
    <!-- 插槽：自定义触发器 -->
    <slot name="trigger" :selected="selectedWarehouse" :open="openPicker">
      <!-- 默认触发按钮 -->
      <div class="default-trigger" :class="{ 'trigger-disabled': disabled }">
        <van-button
          :type="triggerButtonType"
          :size="triggerButtonSize"
          :block="triggerButtonBlock"
          :disabled="disabled"
          :loading="triggerLoading"
        >
          <template v-if="selectedWarehouse">
            {{ displayText }}
          </template>
          <template v-else>
            {{ placeholder || '选择仓库' }}
          </template>
          <van-icon v-if="showTriggerIcon" :name="triggerIcon" />
        </van-button>
      </div>
    </slot>
  </div>

  <!-- 仓库选择弹窗 -->
  <van-popup
    v-model:show="showPicker"
    :position="popupPosition"
    round
    :style="popupStyle"
    :close-on-click-overlay="closeOnClickOverlay"
  >
    <div class="warehouse-picker">
      <!-- 标题和关闭按钮 -->
      <div class="picker-header">
        <div class="header-title">{{ popupTitle || '选择仓库' }}</div>
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
          :placeholder="searchPlaceholder || '搜索仓库'"
          @update:model-value="handleSearchInput"
          @search="handleSearch"
          @clear="handleClearSearch"
        />
      </div>
      
      <!-- 仓库列表 -->
      <div class="warehouse-list-container">
        <div class="warehouse-list" v-if="!loading">
          <!-- 全部选项 -->
          <div
            v-if="showAllOption"
            class="warehouse-item"
            :class="{ 'warehouse-item-selected': isSelected(0) }"
            @click="handleWarehouseSelect({ id: 0, name: '全部仓库' })"
          >
            <div class="warehouse-info">
              <div class="warehouse-name">全部仓库</div>
            </div>
            <div class="warehouse-status">
              <van-icon
                v-if="isSelected(0)"
                name="success"
                color="#07c160"
              />
            </div>
          </div>
          
          <!-- 仓库列表项 -->
          <div
            v-for="warehouse in filteredWarehouses"
            :key="warehouse.id"
            class="warehouse-item"
            :class="{
              'warehouse-item-selected': isSelected(warehouse.id),
              'warehouse-item-disabled': warehouse.status === 0
            }"
            @click="handleWarehouseSelect(warehouse)"
          >
            <div class="warehouse-info">
              <div class="warehouse-name">{{ warehouse.name }}</div>
              <div class="warehouse-details">
                <span v-if="warehouse.code" class="warehouse-code">{{ warehouse.code }}</span>
                <span v-if="warehouse.address" class="warehouse-address">{{ warehouse.address }}</span>
              </div>
            </div>
            
            <div class="warehouse-status">
              <van-tag
                v-if="warehouse.status === 0"
                size="small"
                type="danger"
                plain
              >
                已禁用
              </van-tag>
              <van-icon
                v-if="isSelected(warehouse.id)"
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
          v-if="!loading && filteredWarehouses.length === 0 && (!showAllOption || searchKeyword)"
          :description="searchKeyword ? '未找到相关仓库' : '暂无仓库数据'"
        />
      </div>
      
      <!-- 操作按钮（可选） -->
      <div v-if="showConfirmButton" class="picker-actions">
        <van-button type="default" @click="handleCancel">取消</van-button>
        <van-button 
          type="primary" 
          @click="handleConfirm"
          :disabled="!tempSelection"
        >
          确定
        </van-button>
      </div>
    </div>
  </van-popup>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { showToast } from 'vant'
import { useWarehouseStore } from '@/store/modules/warehouse'

const props = defineProps({
    // 值绑定
    modelValue: {
        type: [Number, String],
        default: null
    },
    
    // 触发按钮配置
    hideTrigger: {
        type: Boolean,
        default: false
    },
    placeholder: {
        type: String,
        default: '请选择仓库'
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
        default: '选择仓库'
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
        default: '搜索仓库'
    },
    closeOnClickOverlay: {
        type: Boolean,
        default: true
    },
    showConfirmButton: {
        type: Boolean,
        default: false
    },
    
    // 数据配置
    showAllOption: {
        type: Boolean,
        default: false
    },
    onlyEnabled: {
        type: Boolean,
        default: true
    },
    excludeIds: {
        type: Array,
        default: () => []
    },
    autoLoad: {
        type: Boolean,
        default: true
    },
    allowDisabled: {
        type: Boolean,
        default: false
    },
    immediateSelect: {
      type: Boolean,
      default: true
    },
    // 使用状态管理缓存
    useStoreCache: {
      type: Boolean,
      default: true
    }
  })
  
  const emit = defineEmits(['update:modelValue', 'change', 'select', 'confirm', 'cancel', 'clear', 'search'])

const warehouseStore = useWarehouseStore()
    
    // 状态
    const showPicker = ref(false)
    const searchKeyword = ref('')
    const loading = ref(false)
    const tempSelection = ref(null)
    
    // 计算属性
    const selectedWarehouse = computed(() => {
      if (props.modelValue === null || props.modelValue === undefined || props.modelValue === '') {
        return null
      }
      
      // 如果选了"全部"
      if (props.modelValue === 0 && props.showAllOption) {
        return { id: 0, name: '全部仓库' }
      }
      
      return warehouseStore.list.find(
        item => item.id == props.modelValue
      ) || null
    })
    
    const displayText = computed(() => {
      if (!selectedWarehouse.value) return ''
      return selectedWarehouse.value.name
    })
    
    // 获取仓库列表数据
    const warehouseList = computed(() => {
      let warehouses = warehouseStore.list
      
      // 排除指定的仓库
      if (props.excludeIds.length > 0) {
        warehouses = warehouses.filter(item => !props.excludeIds.includes(item.id))
      }
      
      return warehouses
    })
    
    // 过滤后的仓库列表（用于搜索）
    const filteredWarehouses = computed(() => {
      let warehouses = warehouseList.value
      
      // 过滤状态
      if (props.onlyEnabled && !props.allowDisabled) {
        warehouses = warehouses.filter(item => item.status !== 0)
      }
      
      // 搜索过滤
      if (searchKeyword.value.trim()) {
        const keyword = searchKeyword.value.toLowerCase()
        return warehouses.filter(item => {
          // 匹配仓库名称
          if (item.name && item.name.toLowerCase().includes(keyword)) {
            return true
          }
          // 匹配仓库编码
          if (item.code && item.code.toLowerCase().includes(keyword)) {
            return true
          }
          // 匹配仓库地址
          if (item.address && item.address.toLowerCase().includes(keyword)) {
            return true
          }
          return false
        })
      }
      
      return warehouses
    })
    
    // 检查是否选中
    const isSelected = (warehouseId) => {
      if (props.showConfirmButton) {
        // 显示确认按钮时，使用临时选择
        return tempSelection.value == warehouseId
      }
      return props.modelValue == warehouseId
    }
    
    // 打开选择器
    const openPicker = () => {
      if (props.disabled) return
      
      showPicker.value = true
    }
    
    // 加载仓库数据
    const loadWarehouses = async () => {
      if (loading.value) return
      
      loading.value = true
      try {
        await warehouseStore.loadList()
      } catch (error) {
        console.error('加载仓库列表失败:', error)
        showToast('加载仓库列表失败')
      } finally {
        loading.value = false
      }
    }
    
    // 搜索处理
    const handleSearch = () => {
      // 搜索逻辑已经在filteredWarehouses计算属性中处理
      emit('search', searchKeyword.value)
    }
    
    const handleSearchInput = () => {
      // 简单的输入处理，可以在这里添加防抖逻辑
      handleSearch()
    }
    
    const handleClearSearch = () => {
      searchKeyword.value = ''
      handleSearch()
    }
    
    // 选择仓库
    const handleWarehouseSelect = (warehouse) => {
      // 如果是"全部"选项
      if (warehouse.id === 0) {
        // 如果显示确认按钮，使用临时选择
        if (props.showConfirmButton) {
          tempSelection.value = 0
          return
        }
        
        // 否则立即确认选择
        emit('update:modelValue', 0)
        emit('change', 0, '全部仓库')
        emit('select', { id: 0, name: '全部仓库' })
        showPicker.value = false
        return
      }
      
      // 检查是否允许选择已禁用的仓库
      if (warehouse.status === 0 && !props.allowDisabled) {
        showToast('该仓库已禁用，无法选择')
        return
      }
      
      // 如果显示确认按钮，使用临时选择
      if (props.showConfirmButton) {
        tempSelection.value = tempSelection.value === warehouse.id ? null : warehouse.id
        return
      }
      
      // 如果选择的是已选中的仓库，则不重复选择
      if (warehouse.id === props.modelValue) {
        if (props.immediateSelect) {
          showPicker.value = false
        }
        return
      }
      
      // 立即确认选择
      emit('update:modelValue', warehouse.id)
      emit('change', warehouse.id, warehouse.name)
      emit('select', warehouse)
      
      if (props.immediateSelect) {
        showPicker.value = false
      }
    }
    
    // 确认选择
    const handleConfirm = () => {
      if (tempSelection.value === null || tempSelection.value === undefined) return
      
      // 处理"全部"选项
      if (tempSelection.value === 0 && props.showAllOption) {
        emit('update:modelValue', 0)
        emit('change', 0, '全部仓库')
        emit('confirm', 0)
        showPicker.value = false
        tempSelection.value = null
        return
      }
      
      const warehouse = warehouseList.value.find(item => item.id == tempSelection.value)
      if (!warehouse) return
      
      emit('update:modelValue', warehouse.id)
      emit('change', warehouse.id, warehouse.name)
      emit('confirm', warehouse.id)
      
      showPicker.value = false
      tempSelection.value = null
    }
    
    // 取消选择
    const handleCancel = () => {
      showPicker.value = false
      tempSelection.value = null
      searchKeyword.value = ''
      emit('cancel')
    }
    
    // 监听弹窗显示/隐藏
    watch(showPicker, (newVal) => {
      if (newVal) {
        // 初始化临时选择
        if (props.showConfirmButton) {
          tempSelection.value = props.modelValue || null
        }
        
        // 弹窗显示时，清空搜索关键词
        searchKeyword.value = ''
        
        // 如果仓库列表为空或不使用缓存，则加载数据
        if (warehouseList.value.length === 0 || !props.useStoreCache) {
          loadWarehouses()
        }
        
        // 自动聚焦搜索框（需要等待弹窗渲染完成）
        nextTick(() => {
          const searchInput = document.querySelector('.warehouse-picker .van-search__field')
          if (searchInput) {
            searchInput.focus()
          }
        })
      } else {
        // 弹窗关闭时，重置临时选择
        tempSelection.value = null
        searchKeyword.value = ''
      }
    })
    
    // 监听外部值变化
    watch(() => props.modelValue, (newVal) => {
      // 这里可以处理外部值变化时的逻辑
    }, { immediate: true })
    
    // 组件挂载时预加载仓库数据
    onMounted(() => {
      if (props.autoLoad && !props.useStoreCache) {
        // 预加载数据（如果不使用store缓存）
        loadWarehouses()
      }
    })
    
    // 暴露给父组件的方法
defineExpose({
    openPicker,
    closePicker: () => { showPicker.value = false },
    loadWarehouses,
    clear: () => {
        emit('update:modelValue', null)
        searchKeyword.value = ''
    },
    getSelectedWarehouse: () => selectedWarehouse.value,
    refresh: () => { loadWarehouses() }
})
</script>

<style scoped>
.warehouse-select-trigger {
  display: inline-block;
  width: 100%;
}

.default-trigger {
  cursor: pointer;
  
  &.trigger-disabled {
    cursor: not-allowed;
    opacity: 0.6;
  }
}

.warehouse-picker {
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

.warehouse-list-container {
  flex: 1;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
}

.warehouse-list {
  padding: 8px 0;
}

.warehouse-item {
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
  
  &.warehouse-item-selected {
    background-color: #f0f9ff;
  }
  
  &.warehouse-item-disabled {
    opacity: 0.6;
    cursor: not-allowed;
    
    &:hover,
    &:active {
      background-color: transparent;
    }
  }
}

.warehouse-info {
  flex: 1;
  margin-right: 12px;
  overflow: hidden;
  
  .warehouse-name {
    font-size: 14px;
    font-weight: 500;
    color: #323233;
    margin-bottom: 4px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  
  .warehouse-details {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }
  
  .warehouse-code,
  .warehouse-address {
    font-size: 12px;
    color: #969799;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.warehouse-status {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
  
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

/* 响应式调整 */
@media (max-width: 768px) {
  .picker-header {
    padding: 14px;
  }
  
  .search-box {
    padding: 8px 14px;
  }
  
  .warehouse-item {
    padding: 10px 14px;
  }
  
  .picker-actions {
    padding: 10px 14px;
  }
}
</style>