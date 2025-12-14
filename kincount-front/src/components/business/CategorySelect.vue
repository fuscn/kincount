<template>
  <!-- 触发按钮 -->
  <div v-if="!hideTrigger" @click="showSelector = true">
    <slot name="trigger" :selected="selectedItem">
      <van-field
        v-model="displayText"
        readonly
        :label="label"
        :placeholder="placeholder"
        is-link
        :rules="rules"
        :required="required"
        @click="showSelector = true"
      />
    </slot>
  </div>

  <!-- 分类选择器弹窗 -->
  <van-popup
    v-model:show="showSelector"
    position="bottom"
    :style="{ height: '70%' }"
    round
    closeable
    @close="handleClose"
  >
    <div class="category-select-popup">
      <!-- 标题栏 -->
      <div class="popup-header">
        <van-nav-bar
          :title="title"
          :left-text="cancelText"
          :right-text="confirmText"
          @click-left="handleCancel"
          @click-right="handleConfirm"
        />
      </div>

      <!-- 分类树 -->
      <div class="tree-container">
        <van-loading v-if="loading && !hasCachedData" class="loading" />
        
        <van-empty
          v-if="!loading && !treeData.length"
          description="暂无分类数据"
        />
        
        <!-- 树形列表 -->
        <div v-else class="tree-list">
          <CategoryTreeItem
            v-for="node in treeData"
            :key="node.id"
            :node="node"
            :selected-id="tempSelectedId"
            :allow-select-parent="allowSelectParent"
            :expanded-node-id="expandedNodeId"
            @select="handleSelectNode"
            @toggle-expand="handleToggleExpand"
          />
        </div>
      </div>
    </div>
  </van-popup>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useCategoryStore } from '@/store/modules/category'
import CategoryTreeItem from './CategoryTreeItem.vue'

const props = defineProps({
  // 选择值
  modelValue: {
    type: [Number, String],
    default: null
  },
  
  // 是否允许选择父节点
  allowSelectParent: {
    type: Boolean,
    default: false
  },
  
  // 显示配置
  label: {
    type: String,
    default: '选择分类'
  },
  placeholder: {
    type: String,
    default: '请选择分类'
  },
  title: {
    type: String,
    default: '选择分类'
  },
  cancelText: {
    type: String,
    default: '取消'
  },
  confirmText: {
    type: String,
    default: '确定'
  },
  
  // 验证配置
  required: {
    type: Boolean,
    default: false
  },
  rules: {
    type: Array,
    default: () => []
  },
  
  // 其他配置
  hideTrigger: {
    type: Boolean,
    default: false
  },
  // 是否显示完整的分类路径
  showFullPath: {
    type: Boolean,
    default: true
  },
  // 是否自动关闭弹窗（点击节点后立即关闭）
  autoClose: {
    type: Boolean,
    default: true
  },
  // 新增：是否强制刷新数据
  forceRefresh: {
    type: Boolean,
    default: false
  },
  // 新增：是否启用缓存
  enableCache: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel', 'change'])

// 状态
const showSelector = ref(false)
const loading = ref(false)
const tempSelectedId = ref(null)
const expandedNodeId = ref(null)
const categoryStore = useCategoryStore()
const treeData = ref([])

// 计算属性
const hasCachedData = computed(() => {
  return treeData.value.length > 0
})

const selectedItem = computed(() => {
  // 如果没有值，直接返回 null
  if (!props.modelValue) return null
  
  // 如果没有数据，也返回 null
  if (!treeData.value.length) return null
  
  return findNodeById(props.modelValue, treeData.value)
})

const displayText = computed(() => {
  if (!selectedItem.value) return ''
  
  if (props.showFullPath) {
    return getFullName(selectedItem.value)
  }
  return selectedItem.value.name
})

// 方法：根据ID从树数据中查找节点
const findNodeById = (id, nodes) => {
  if (!id || !nodes || !nodes.length) return null
  
  for (const node of nodes) {
    if (node.id === id) {
      // 返回干净的节点对象，去掉内部属性
      const { _level, _expanded, children, ...cleanNode } = node
      return {
        ...cleanNode,
        hasChildren: children && children.length > 0
      }
    }
    if (node.children && node.children.length) {
      const found = findNodeById(id, node.children)
      if (found) return found
    }
  }
  return null
}

const getFullName = (node) => {
  const names = [node.name]
  let parent = findParent(node.id, treeData.value)
  while (parent) {
    names.unshift(parent.name)
    parent = findParent(parent.id, treeData.value)
  }
  return names.join(' > ')
}

const findParent = (childId, nodes, parent = null) => {
  for (const node of nodes) {
    if (node.id === childId) return parent
    if (node.children && node.children.length) {
      const found = findParent(childId, node.children, node)
      if (found) return found
    }
  }
  return null
}

const loadCategories = async () => {
  // 如果有缓存数据且不需要强制刷新，直接使用缓存
  if (props.enableCache && hasCachedData.value && !props.forceRefresh) {
    return
  }
  
  loading.value = true
  try {
    // 调用 store 的 loadTree，传递是否强制刷新
    const data = await categoryStore.loadTree('', props.forceRefresh)
    
    // 深度拷贝数据，避免与其他组件共享状态
    const copyData = JSON.parse(JSON.stringify(data))
    
    // 处理数据：所有节点默认折叠
    const processData = (nodes, level = 0) => {
      return nodes.map(node => ({
        ...node,
        _level: level,
        _expanded: false, // 所有节点都折叠
        children: node.children ? processData(node.children, level + 1) : []
      }))
    }
    
    treeData.value = processData(copyData)
    
    // 重置展开状态
    expandedNodeId.value = null
    
  } catch (error) {
    console.error('加载分类失败:', error)
  } finally {
    loading.value = false
  }
}

const handleSelectNode = (node) => {
  // 如果允许选择父节点，或者节点没有子节点，则选中
  if (props.allowSelectParent || !node.children || node.children.length === 0) {
    const selectedId = node.id
    
    // 立即更新选中状态，让用户看到视觉反馈
    tempSelectedId.value = selectedId
    
    // 如果设置了自动关闭，延迟确认并关闭弹窗
    if (props.autoClose) {
      setTimeout(() => {
        handleConfirm()
      }, 150)
    }
  }
}

const handleToggleExpand = (node) => {
  // 实现互斥折叠（手风琴模式）
  if (expandedNodeId.value === node.id) {
    // 当前节点已展开，折叠它
    expandedNodeId.value = null
  } else {
    // 展开当前节点，折叠其他所有节点
    expandedNodeId.value = node.id
  }
}

const handleConfirm = () => {
  if (tempSelectedId.value !== null && tempSelectedId.value !== undefined) {
    // 从 treeData 中查找完整的节点对象
    const selectedNode = findNodeById(tempSelectedId.value, treeData.value)
    
    emit('update:modelValue', tempSelectedId.value)
    emit('confirm', tempSelectedId.value, selectedNode)
    emit('change', tempSelectedId.value)
  }
  showSelector.value = false
}

const handleCancel = () => {
  // 取消时，不重置选中状态，保持当前选择
  // 这样下次打开时，用户还能看到之前的选择
  showSelector.value = false
  emit('cancel')
}

const handleClose = () => {
  // 弹窗关闭时，保持当前选中状态
  showSelector.value = false
}

// 监听显示状态
watch(showSelector, async (newVal) => {
  if (newVal) {
    // 打开弹窗时加载数据（使用缓存）
    await loadCategories()
    
    // 如果有当前选中的值，设置为临时选中
    if (props.modelValue) {
      tempSelectedId.value = props.modelValue
    } else {
      tempSelectedId.value = null
    }
    
    // 重置展开状态
    expandedNodeId.value = null
  } else {
    // 弹窗关闭时，不要重置tempSelectedId
    // 保持当前选中状态，直到下次打开
    expandedNodeId.value = null
  }
})

// 监听外部值变化
watch(() => props.modelValue, (newVal) => {
  // 只有当弹窗未显示时才更新 tempSelectedId
  if (!showSelector.value) {
    tempSelectedId.value = newVal
  }
}, { immediate: true })

// 暴露方法给父组件
defineExpose({
  open: () => {
    showSelector.value = true
  },
  close: () => {
    showSelector.value = false
  },
  clear: () => {
    emit('update:modelValue', null)
    tempSelectedId.value = null
    expandedNodeId.value = null
  },
  reload: async (forceRefresh = true) => {
    // 强制刷新数据
    await loadCategories(forceRefresh)
  },
  getSelectedItem: () => {
    return selectedItem.value
  },
  // 新增：手动刷新缓存
  refreshCache: async () => {
    await categoryStore.loadTree('', true)
  },
  // 新增：查找节点
  findNode: (id) => {
    return findNodeById(id, treeData.value)
  }
})
</script>

<style lang="scss" scoped>
.category-select-popup {
  height: 100%;
  display: flex;
  flex-direction: column;
  background: #f7f8fa;
  
  .popup-header {
    flex-shrink: 0;
    
    :deep(.van-nav-bar) {
      background: transparent;
    }
  }
  
  .tree-container {
    flex: 1;
    overflow-y: auto;
    position: relative;
    
    .loading {
      padding: 40px 0;
      text-align: center;
    }
    
    .tree-list {
      background: #fff;
    }
  }
}
</style>