<template>
  <div class="tree-node">
    <div 
      class="tree-item"
      :class="{ 
        'active': isSelected,
        'has-children': hasChildren
      }"
      :style="{ paddingLeft: (level * 20 + 16) + 'px' }"
      @click="handleSelect"
    >
      <!-- 展开/收起图标 -->
      <span 
        v-if="hasChildren"
        class="expand-icon"
        @click.stop="toggleExpand"
      >
        {{ isExpanded ? '−' : '+' }}
      </span>
      <span v-else class="expand-placeholder"></span>
      
      <!-- 节点内容 -->
      <div class="node-content">
        <span class="node-text">{{ nodeTitle }}</span>
        <van-loading v-if="loading" size="16px" class="node-loading" />
      </div>
    </div>
    
    <!-- 子节点 -->
    <div v-if="hasChildren && isExpanded" class="tree-children">
      <tree-node
        v-for="child in nodeChildren"
        :key="getNodeKey(child)"
        :node="child"
        :level="level + 1"
        :selected-keys="selectedKeys"
        :expanded-keys="expandedKeys"
        :field-names="fieldNames"
        @select="$emit('select', $event)"
        @expand="$emit('expand', $event)"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  node: {
    type: Object,
    required: true
  },
  level: {
    type: Number,
    default: 0
  },
  selectedKeys: {
    type: Set,
    default: () => new Set()
  },
  expandedKeys: {
    type: Set,
    default: () => new Set()
  },
  fieldNames: {
    type: Object,
    default: () => ({
      key: 'id',
      title: 'name',
      children: 'children'
    })
  }
})

const emit = defineEmits(['select', 'expand'])

// 响应式数据
const loading = ref(false)
const childrenLoaded = ref(false)

// 计算属性
const nodeKey = computed(() => {
  return props.node[props.fieldNames.key]
})

const nodeTitle = computed(() => {
  return props.node[props.fieldNames.title]
})

const nodeChildren = computed(() => {
  return props.node[props.fieldNames.children] || []
})

const hasChildren = computed(() => {
  return nodeChildren.value.length > 0
})

const isSelected = computed(() => {
  return props.selectedKeys.has(nodeKey.value)
})

const isExpanded = computed(() => {
  return props.expandedKeys.has(nodeKey.value)
})

// 方法
const getNodeKey = (node) => {
  return node[props.fieldNames.key]
}

const handleSelect = () => {
  emit('select', props.node)
}

const toggleExpand = () => {
  emit('expand', props.node, !isExpanded.value)
}
</script>

<style lang="scss" scoped>
.tree-node {
  .tree-item {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    cursor: pointer;
    transition: background-color 0.2s;
    
    &:hover {
      background-color: #f5f5f5;
    }
    
    &.active {
      background-color: #e6f7ff;
      color: #1890ff;
    }
    
    .expand-icon {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 20px;
      height: 20px;
      margin-right: 8px;
      background-color: #f0f0f0;
      border-radius: 2px;
      font-size: 12px;
      font-weight: bold;
      flex-shrink: 0;
    }
    
    .expand-placeholder {
      width: 20px;
      height: 20px;
      margin-right: 8px;
      flex-shrink: 0;
    }
    
    .node-content {
      display: flex;
      align-items: center;
      flex: 1;
      
      .node-text {
        flex: 1;
      }
      
      .node-loading {
        margin-left: 8px;
      }
    }
  }
  
  .tree-children {
    // 子节点容器
  }
}
</style>