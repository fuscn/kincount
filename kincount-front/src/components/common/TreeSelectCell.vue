<!-- 文件位置：src/components/TreeSelect/TreeSelectCell.vue -->
<template>
  <div class="tree-select-cell-wrapper">
    <!-- 使用 van-cell 作为触发器 -->
    <van-cell
      :title="title"
      :value="displayText"
      :is-link="true"
      :clickable="true"
      @click="showTree = true"
      :class="cellClass"
      class="tree-select-cell"
    />
    <!-- 自定义分割线 -->
    <div class="custom-divider" v-if="showDivider"></div>
    
    <!-- 树形选择弹窗 -->
    <van-popup
      v-model:show="showTree"
      position="bottom"
      round
      :style="{ height: popupHeight }"
      class="tree-popup"
    >
      <div class="tree-popup-content">
        <!-- 弹窗头部 -->
        <div class="tree-header">
          <div class="tree-title">{{ popupTitle || title }}</div>
          <div class="tree-actions">
            <van-button
              v-if="searchable"
              size="small"
              icon="search"
              @click="showSearch = true"
            >
              搜索
            </van-button>
            <van-button
              size="small"
              type="primary"
              @click="showTree = false"
            >
              关闭
            </van-button>
          </div>
        </div>

        <!-- 搜索框 -->
        <div v-if="showSearch" class="tree-search">
          <van-search
            v-model="searchKeyword"
            placeholder="请输入关键词搜索"
            @clear="onSearchClear"
          />
        </div>

        <!-- 树形内容 -->
        <div class="tree-content" ref="treeContent">
          <!-- 加载状态 -->
          <div v-if="loading" class="tree-loading">
            <van-loading type="spinner" size="24px">加载中...</van-loading>
          </div>

          <!-- 空状态 -->
          <div v-else-if="!filteredTreeData.length" class="tree-empty">
            <van-empty description="暂无数据" />
          </div>

          <!-- 树形列表 -->
          <div v-else class="tree-list">
            <!-- 顶级分类选项 -->
            <div 
              class="tree-item top-level"
              :class="{ 'active': selectedValue === 0 }"
              @click="selectItem(0, '顶级分类')"
            >
              <span class="tree-text">顶级分类</span>
            </div>
            
            <!-- 树形节点 -->
            <tree-node
              v-for="node in filteredTreeData"
              :key="getNodeKey(node)"
              :node="node"
              :level="0"
              :selected-keys="selectedKeys"
              :expanded-keys="expandedKeys"
              :field-names="fieldNames"
              @select="onNodeSelect"
              @expand="onNodeExpand"
            />
          </div>
        </div>
      </div>
    </van-popup>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import TreeNode from './TreeNode.vue'

const props = defineProps({
  // 选中的值
  modelValue: {
    type: [String, Number],
    default: ''
  },
  // 数据源
  data: {
    type: Array,
    default: () => []
  },
  // 字段映射
  fieldNames: {
    type: Object,
    default: () => ({
      key: 'id',
      title: 'name',
      children: 'children'
    })
  },
  // 标题
  title: {
    type: String,
    default: '选择'
  },
  // 弹窗标题
  popupTitle: {
    type: String,
    default: ''
  },
  // 占位符
  placeholder: {
    type: String,
    default: '请选择'
  },
  // 弹窗高度
  popupHeight: {
    type: String,
    default: '70%'
  },
  // 是否可搜索
  searchable: {
    type: Boolean,
    default: false
  },
  // 异步加载函数
  loadData: {
    type: Function,
    default: null
  },
  // 默认展开的层级
  defaultExpandLevel: {
    type: Number,
    default: 1
  },
  // 默认展开所有
  defaultExpandAll: {
    type: Boolean,
    default: false
  },
  // Cell 的类名
  cellClass: {
    type: String,
    default: ''
  },
  // 选择后是否自动关闭弹窗
  closeOnSelect: {
    type: Boolean,
    default: true
  },
  // 是否显示分割线
  showDivider: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['update:modelValue', 'change', 'confirm'])

// 响应式数据
const showTree = ref(false)
const showSearch = ref(false)
const searchKeyword = ref('')
const loading = ref(false)
const expandedKeys = ref(new Set())
const treeContent = ref(null)

// 计算属性
const selectedValue = computed(() => props.modelValue)
const selectedKeys = computed(() => {
  return props.modelValue ? new Set([props.modelValue]) : new Set()
})

const displayText = computed(() => {
  if (!props.modelValue) return props.placeholder
  
  // 查找选中的节点名称
  const findNodeName = (nodes, value) => {
    for (const node of nodes) {
      const key = node[props.fieldNames.key]
      if (key === value) {
        return node[props.fieldNames.title]
      }
      if (node[props.fieldNames.children]) {
        const result = findNodeName(node[props.fieldNames.children], value)
        if (result) return result
      }
    }
    return null
  }
  
  if (props.modelValue === 0) return '顶级分类'
  
  const name = findNodeName(props.data, props.modelValue)
  return name || props.placeholder
})

const filteredTreeData = computed(() => {
  if (!searchKeyword.value) return props.data
  
  const filter = (nodes) => {
    const result = []
    for (const node of nodes) {
      const title = node[props.fieldNames.title] || ''
      const children = node[props.fieldNames.children]
      
      if (title.toLowerCase().includes(searchKeyword.value.toLowerCase())) {
        result.push(node)
      } else if (children && children.length) {
        const filteredChildren = filter(children)
        if (filteredChildren.length) {
          result.push({
            ...node,
            [props.fieldNames.children]: filteredChildren
          })
        }
      }
    }
    return result
  }
  
  return filter(props.data)
})

// 方法
const getNodeKey = (node) => {
  return node[props.fieldNames.key]
}

const onNodeSelect = (node) => {
  const key = getNodeKey(node)
  selectItem(key, node[props.fieldNames.title])
}

const selectItem = (key, title) => {
  emit('update:modelValue', key)
  emit('change', { key, title })
  
  // 选择后自动关闭弹窗
  if (props.closeOnSelect) {
    showTree.value = false
  }
}

const onNodeExpand = (node, expanded) => {
  const key = getNodeKey(node)
  if (expanded) {
    expandedKeys.value.add(key)
  } else {
    expandedKeys.value.delete(key)
  }
}

const onSearchClear = () => {
  searchKeyword.value = ''
  showSearch.value = false
}

// 初始化展开状态
const initExpandedKeys = () => {
  if (props.defaultExpandAll) {
    const expandAll = (nodes) => {
      for (const node of nodes) {
        expandedKeys.value.add(getNodeKey(node))
        if (node[props.fieldNames.children]) {
          expandAll(node[props.fieldNames.children])
        }
      }
    }
    expandAll(props.data)
  } else if (props.defaultExpandLevel > 0) {
    const expandToLevel = (nodes, level) => {
      if (level > props.defaultExpandLevel) return
      for (const node of nodes) {
        expandedKeys.value.add(getNodeKey(node))
        if (node[props.fieldNames.children]) {
          expandToLevel(node[props.fieldNames.children], level + 1)
        }
      }
    }
    expandToLevel(props.data, 1)
  }
}

// 监听数据变化
watch(() => props.data, (newData) => {
  if (newData.length) {
    nextTick(() => {
      initExpandedKeys()
    })
  }
}, { immediate: true })
</script>

<style lang="scss" scoped>
.tree-select-cell-wrapper {
  position: relative;
  
  .tree-select-cell {
    // 确保单元格宽度填满容器
    width: 100%;
  }
  
  .custom-divider {
    position: absolute;
    bottom: 0;
    left: 16px;
    right: 0;
    height: 1px;
    background-color: #ebedf0;
    transform: scaleY(0.5);
    z-index: 1;
  }
}

.tree-popup {
  .tree-popup-content {
    height: 100%;
    display: flex;
    flex-direction: column;
    
    .tree-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px;
      border-bottom: 1px solid #f0f0f0;
      
      .tree-title {
        font-size: 16px;
        font-weight: bold;
        color: #323233;
      }
      
      .tree-actions {
        display: flex;
        gap: 8px;
      }
    }
    
    .tree-search {
      border-bottom: 1px solid #f0f0f0;
    }
    
    .tree-content {
      flex: 1;
      overflow: auto;
      
      .tree-loading,
      .tree-empty {
        display: flex;
        justify-content: center;
        align-items: center;
        height: 200px;
      }
      
      .tree-list {
        padding: 8px 0;
        
        .tree-item {
          display: flex;
          align-items: center;
          padding: 12px 16px;
          cursor: pointer;
          transition: background-color 0.2s;
          
          &:hover {
            background-color: #f5f5f5;
          }
          
          &.active {
            background-color: #e6f7ff;
            color: #1890ff;
          }
          
          &.top-level {
            font-weight: 500;
          }
          
          .tree-text {
            flex: 1;
          }
        }
      }
    }
  }
}
</style>