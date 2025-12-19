<!-- 修改 CategorySelect.vue 模板 -->
<template>
  <!-- 确保只有一个根元素 -->
  <div class="category-select-wrapper">
    <!-- 触发按钮/区域 -->
    <div v-if="!hideTrigger" @click="showSelector = true">
      <slot name="trigger" :selected="selectedItem">
        <!-- 默认触发按钮 -->
        <van-button :type="buttonType" :size="buttonSize" :block="buttonBlock">
          {{ displayText || placeholder || '选择分类' }}
        </van-button>
      </slot>
    </div>

    <!-- 分类选择器弹窗 -->
    <van-popup v-model:show="showSelector" position="bottom" :style="{ height: '70%' }" round @close="handleClose">
      <div class="category-select-popup">
        <!-- 标题栏 -->
        <div class="popup-header">
          <van-nav-bar :title="title" :left-text="cancelText" :right-text="confirmText" @click-left="handleCancel"
            @click-right="handleConfirm" />
        </div>

        <!-- 分类树 -->
        <div class="tree-container">
          <van-loading v-if="loading && !hasCachedData" class="loading" />

          <van-empty v-if="!loading && !treeData.length" description="暂无分类数据" />

          <!-- 树形列表 -->
          <div v-else class="tree-list">
            <CategoryTreeItem v-for="node in treeData" :key="node.id" :node="node" :selected-id="tempSelectedId"
              :allow-select-parent="allowSelectParent" :expanded-node-id="expandedNodeId" @select="handleSelectNode"
              @toggle-expand="handleToggleExpand" />
          </div>
        </div>
      </div>
    </van-popup>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useCategoryStore } from '@/store/modules/category'
import CategoryTreeItem from './CategoryTreeItem.vue'
import { Button as VanButton, Popup, NavBar, Loading, Empty } from 'vant'

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
  // 是否强制刷新数据
  forceRefresh: {
    type: Boolean,
    default: false
  },
  // 是否启用缓存
  enableCache: {
    type: Boolean,
    default: true
  },
  // 触发按钮类型（可选：button, text, custom）
  triggerType: {
    type: String,
    default: 'button',
    validator: (value) => ['button', 'text', 'custom'].includes(value)
  },
  // 按钮样式
  buttonType: {
    type: String,
    default: 'default'
  },
  buttonSize: {
    type: String,
    default: 'normal'
  },
  buttonBlock: {
    type: Boolean,
    default: true
  },
  // 是否显示搜索框
  showSearch: {
    type: Boolean,
    default: false
  },
  // 搜索框占位符
  searchPlaceholder: {
    type: String,
    default: '搜索分类'
  },
  // 是否显示已选中的分类路径
  showSelectedPath: {
    type: Boolean,
    default: false
  },
  // 最大选择层级（0表示不限制）
  maxLevel: {
    type: Number,
    default: 0
  },
  // 是否多选
  multiple: {
    type: Boolean,
    default: false
  },
  // 多选时最多可选数量（0表示不限制）
  maxCount: {
    type: Number,
    default: 0
  },
  // 是否显示全部分类选项
  showAllOption: {
    type: Boolean,
    default: false
  },
  // 全部分类选项的文本
  allOptionText: {
    type: String,
    default: '全部分类'
  },
  // 是否显示分类数量
  showCount: {
    type: Boolean,
    default: false
  },
  // 是否显示分类图标
  showIcon: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits([
  'update:modelValue',
  'confirm',
  'cancel',
  'change',
  'search',
  'open',
  'close',
  'select'
])

// 状态
const showSelector = ref(false)
const loading = ref(false)
const tempSelectedId = ref(null)
const expandedNodeId = ref(null)
const categoryStore = useCategoryStore()
const treeData = ref([])
const searchKeyword = ref('')
const selectedItems = ref([]) // 用于多选模式

// 计算属性
const hasCachedData = computed(() => {
  return treeData.value.length > 0
})

const selectedItem = computed(() => {
  // 如果没有值，直接返回 null
  if (!props.modelValue) return null

  // 如果没有数据，也返回 null
  if (!treeData.value.length) return null

  // 多选模式
  if (props.multiple) {
    if (Array.isArray(props.modelValue)) {
      return props.modelValue.map(id => findNodeById(id, treeData.value)).filter(Boolean)
    }
    return []
  }

  // 单选模式
  return findNodeById(props.modelValue, treeData.value)
})

const displayText = computed(() => {
  if (props.multiple) {
    // 多选模式
    if (!selectedItem.value || selectedItem.value.length === 0) {
      return props.placeholder || '选择分类'
    }

    if (selectedItem.value.length === 1) {
      return props.showFullPath
        ? getFullName(selectedItem.value[0])
        : selectedItem.value[0].name
    }

    return `已选择 ${selectedItem.value.length} 个分类`
  } else {
    // 单选模式
    if (!selectedItem.value) return props.placeholder || '选择分类'

    if (props.showFullPath) {
      return getFullName(selectedItem.value)
    }
    return selectedItem.value.name
  }
})

const filteredTreeData = computed(() => {
  if (!searchKeyword.value.trim() || !props.showSearch) {
    return treeData.value
  }

  const keyword = searchKeyword.value.toLowerCase()
  const filterNodes = (nodes) => {
    return nodes.filter(node => {
      // 检查当前节点是否匹配
      const isMatch = node.name.toLowerCase().includes(keyword)

      // 检查子节点是否匹配
      let childrenMatch = false
      if (node.children && node.children.length) {
        node.children = filterNodes(node.children)
        childrenMatch = node.children.length > 0
      }

      // 如果当前节点匹配或子节点匹配，保留该节点
      if (isMatch || childrenMatch) {
        // 如果是子节点匹配，展开当前节点
        if (childrenMatch && !isMatch) {
          node._expanded = true
        }
        return true
      }
      return false
    })
  }

  return filterNodes(JSON.parse(JSON.stringify(treeData.value)))
})

// 方法：根据ID从树数据中查找节点
const findNodeById = (id, nodes) => {
  if (!id || !nodes || !nodes.length) return null

  for (const node of nodes) {
    if (node.id === id) {
      // 返回干净的节点对象，去掉内部属性
      const { _level, _expanded, children, ...cleanNode } = node

      // 获取父级节点路径
      const parentNodes = []
      let parent = findParent(node.id, nodes)
      while (parent) {
        parentNodes.unshift(parent)
        parent = findParent(parent.id, nodes)
      }

      return {
        ...cleanNode,
        hasChildren: children && children.length > 0,
        // 添加完整路径信息
        fullPath: getFullName(node),
        pathNames: [...parentNodes.map(p => p.name), node.name],
        parentNames: parentNodes.map(p => p.name)
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
    throw error
  } finally {
    loading.value = false
  }
}

const handleSelectNode = (node) => {
  // 检查最大层级限制
  if (props.maxLevel > 0 && node.level > props.maxLevel) {
    return
  }

  // 多选模式
  if (props.multiple) {
    const index = selectedItems.value.findIndex(item => item.id === node.id)

    if (index === -1) {
      // 检查最大数量限制
      if (props.maxCount > 0 && selectedItems.value.length >= props.maxCount) {
        return
      }
      selectedItems.value.push(node)
    } else {
      selectedItems.value.splice(index, 1)
    }

    // 触发选择事件
    emit('select', node, selectedItems.value)

    // 多选时不自动关闭
    return
  }

  // 单选模式
  // 如果允许选择父节点，或者节点没有子节点，则选中
  if (props.allowSelectParent || !node.children || node.children.length === 0) {
    const selectedId = node.id

    // 立即更新选中状态，让用户看到视觉反馈
    tempSelectedId.value = selectedId

    // 触发选择事件
    emit('select', node)

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
const isConfirmed = ref(false)
const handleConfirm = () => {
  // 多选模式
  if (props.multiple) {
    if (selectedItems.value.length > 0) {
      const selectedIds = selectedItems.value.map(item => item.id)
      emit('update:modelValue', selectedIds)
      emit('confirm', selectedIds, selectedItems.value)
      emit('change', selectedIds)
    }
    showSelector.value = false
    return
  }

  // 单选模式
  if (tempSelectedId.value !== null && tempSelectedId.value !== undefined) {
    // 从 treeData 中查找完整的节点对象
    const selectedNode = findNodeById(tempSelectedId.value, treeData.value)

    emit('update:modelValue', tempSelectedId.value)
    emit('confirm', tempSelectedId.value, selectedNode)
    emit('change', tempSelectedId.value)

    // 设置确认标志
    isConfirmed.value = true
  }
  showSelector.value = false
}

const handleCancel = () => {
  // 取消时，不重置选中状态，保持当前选择
  showSelector.value = false
  emit('cancel')
}

// 修改 handleClose 方法
const handleClose = () => {
  // 如果是确认后关闭，不触发取消事件
  if (isConfirmed.value) {
    isConfirmed.value = false
    return
  }

  // 否则视为取消
  handleCancel()
}

const handleSearch = () => {
  emit('search', searchKeyword.value)
}

const clearSearch = () => {
  searchKeyword.value = ''
}

// 监听显示状态
watch(showSelector, async (newVal) => {
  if (newVal) {
    // 打开弹窗时加载数据（使用缓存）
    await loadCategories()

    // 多选模式
    if (props.multiple) {
      if (props.modelValue && Array.isArray(props.modelValue)) {
        selectedItems.value = props.modelValue
          .map(id => findNodeById(id, treeData.value))
          .filter(Boolean)
      } else {
        selectedItems.value = []
      }
    } else {
      // 单选模式：如果有当前选中的值，设置为临时选中
      if (props.modelValue) {
        tempSelectedId.value = props.modelValue
      } else {
        tempSelectedId.value = null
      }
    }

    // 重置展开状态
    expandedNodeId.value = null

    // 触发打开事件
    emit('open')
  } else {
    // 弹窗关闭时，不要重置tempSelectedId
    // 保持当前选中状态，直到下次打开
    expandedNodeId.value = null
    searchKeyword.value = ''

    // 触发关闭事件
    emit('close')
  }
})

// 监听外部值变化
watch(() => props.modelValue, (newVal) => {
  // 只有当弹窗未显示时才更新 tempSelectedId
  if (!showSelector.value) {
    if (props.multiple) {
      if (Array.isArray(newVal)) {
        selectedItems.value = newVal
          .map(id => findNodeById(id, treeData.value))
          .filter(Boolean)
      } else {
        selectedItems.value = []
      }
    } else {
      tempSelectedId.value = newVal
    }
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
    if (props.multiple) {
      emit('update:modelValue', [])
      selectedItems.value = []
    } else {
      emit('update:modelValue', null)
      tempSelectedId.value = null
    }
    expandedNodeId.value = null
  },
  reload: async (forceRefresh = true) => {
    // 强制刷新数据
    await loadCategories(forceRefresh)
  },
  getSelectedItem: () => {
    const item = selectedItem.value
    if (!item) return null

    // 如果是多选模式，返回数组
    if (props.multiple) {
      return item.map(node => ({
        ...node,
        fullPath: getFullName(node),
        // 添加路径信息
        pathNames: (() => {
          const parentNodes = []
          let parent = findParent(node.id, treeData.value)
          while (parent) {
            parentNodes.unshift(parent)
            parent = findParent(parent.id, treeData.value)
          }
          return [...parentNodes.map(p => p.name), node.name]
        })(),
        parentNames: (() => {
          const parentNodes = []
          let parent = findParent(node.id, treeData.value)
          while (parent) {
            parentNodes.unshift(parent)
            parent = findParent(parent.id, treeData.value)
          }
          return parentNodes.map(p => p.name)
        })()
      }))
    }

    // 单选模式，返回单个节点
    if (item.fullPath) {
      return item // 如果已经有完整路径信息，直接返回
    }

    // 否则添加完整路径信息
    return {
      ...item,
      fullPath: getFullName(item),
      pathNames: (() => {
        const parentNodes = []
        let parent = findParent(item.id, treeData.value)
        while (parent) {
          parentNodes.unshift(parent)
          parent = findParent(parent.id, treeData.value)
        }
        return [...parentNodes.map(p => p.name), item.name]
      })(),
      parentNames: (() => {
        const parentNodes = []
        let parent = findParent(item.id, treeData.value)
        while (parent) {
          parentNodes.unshift(parent)
          parent = findParent(parent.id, treeData.value)
        }
        return parentNodes.map(p => p.name)
      })()
    }
  },
  // 新增：手动刷新缓存
  refreshCache: async () => {
    await categoryStore.loadTree('', true)
  },
  // 新增：查找节点（返回带完整路径的节点）
  findNode: (id) => {
    const node = findNodeById(id, treeData.value)
    if (!node) return null

    // 确保返回的节点有完整路径信息
    if (!node.fullPath) {
      return {
        ...node,
        fullPath: getFullName(node),
        pathNames: (() => {
          const parentNodes = []
          let parent = findParent(node.id, treeData.value)
          while (parent) {
            parentNodes.unshift(parent)
            parent = findParent(parent.id, treeData.value)
          }
          return [...parentNodes.map(p => p.name), node.name]
        })(),
        parentNames: (() => {
          const parentNodes = []
          let parent = findParent(node.id, treeData.value)
          while (parent) {
            parentNodes.unshift(parent)
            parent = findParent(parent.id, treeData.value)
          }
          return parentNodes.map(p => p.name)
        })()
      }
    }

    return node
  },
  // 新增：获取分类树数据
  getTreeData: () => {
    return treeData.value
  },
  // 新增：展开指定节点
  expandNode: (nodeId) => {
    expandedNodeId.value = nodeId
  },
  // 新增：折叠所有节点
  collapseAll: () => {
    expandedNodeId.value = null
  },
  // 新增：选择指定节点
  selectNode: (nodeId) => {
    const node = findNodeById(nodeId, treeData.value)
    if (node) {
      handleSelectNode(node)
    }
  },
  // 新增：获取分类的完整路径
  getFullPath: (id) => {
    const node = findNodeById(id, treeData.value)
    return node ? getFullName(node) : ''
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

      .van-nav-bar__title {
        font-weight: 600;
      }

      .van-nav-bar__left {
        .van-nav-bar__text {
          color: #969799;
        }
      }

      .van-nav-bar__right {
        .van-nav-bar__text {
          color: #1989fa;

          &:disabled {
            color: #c8c9cc;
          }
        }
      }
    }
  }

  .search-box {
    flex-shrink: 0;
    padding: 10px 16px;
    background: #f7f8fa;
    border-bottom: 1px solid #ebedf0;

    :deep(.van-search) {
      padding: 0;

      .van-search__content {
        border-radius: 16px;
        background: #fff;
      }
    }
  }

  .selected-path {
    flex-shrink: 0;
    padding: 12px 16px;
    background: #fff;
    border-bottom: 1px solid #ebedf0;
    font-size: 14px;
    color: #323233;

    .path-label {
      color: #969799;
      margin-right: 8px;
    }

    .path-content {
      font-weight: 500;
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

// 可选：添加自定义触发样式
.trigger-text {
  color: #1989fa;
  cursor: pointer;
  padding: 8px 0;
  display: inline-block;

  &:hover {
    opacity: 0.8;
  }
}

.trigger-custom {
  cursor: pointer;
}

// 多选提示
.multiple-tip {
  font-size: 12px;
  color: #969799;
  text-align: center;
  padding: 8px;
  background: #f7f8fa;
  border-top: 1px solid #ebedf0;
}
</style>