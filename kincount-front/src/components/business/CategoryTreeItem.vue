<template>
  <div class="category-tree-item">
    <div 
      class="tree-node" 
      :style="{ paddingLeft: (node._level || 0) * 20 + 16 + 'px' }"
      @click="handleNodeClick"
      :class="{ 
        'selected': isSelected,
        'has-children': hasChildren,
        'leaf-node': !hasChildren,
        'can-select-parent': allowSelectParent && hasChildren
      }"
    >
      <!-- 展开/收起按钮 -->
      <div 
        v-if="hasChildren" 
        class="expand-icon"
        @click.stop="toggleExpand"
      >
        <van-icon :name="isExpanded ? 'arrow-down' : 'arrow'" />
      </div>
      
      <div v-else class="leaf-indicator">
        <van-icon name="circle" size="10" color="#999" />
      </div>
      
      <!-- 节点信息 -->
      <div class="node-content">
        <div class="node-name">
          {{ node.name }}
        </div>
        <div v-if="node.description" class="node-description">
          {{ node.description }}
        </div>
      </div>
      
      <!-- 选中标记 -->
      <van-icon 
        v-if="isSelected" 
        name="success" 
        color="#1989fa" 
        size="16" 
        style="margin-left: 8px;"
      />
    </div>
    
    <!-- 子节点 -->
    <div v-if="isExpanded && hasChildren" class="children">
      <CategoryTreeItem
        v-for="child in node.children"
        :key="child.id"
        :node="child"
        :selected-id="selectedId"
        :allow-select-parent="allowSelectParent"
        :expanded-node-id="expandedNodeId"
        @select="$emit('select', $event)"
        @toggle-expand="$emit('toggle-expand', $event)"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  node: {
    type: Object,
    required: true
  },
  selectedId: {
    type: [Number, String],
    default: null
  },
  allowSelectParent: {
    type: Boolean,
    default: false
  },
  disabled: {
    type: Boolean,
    default: false
  },
  expandedNodeId: {
    type: [Number, String],
    default: null
  }
})

const emit = defineEmits(['select', 'toggle-expand'])

// 计算属性
const hasChildren = computed(() => {
  return props.node.children && props.node.children.length > 0
})

const isExpanded = computed(() => {
  // 根据互斥模式，判断当前节点是否应该展开
  return props.expandedNodeId === props.node.id
})

const isSelected = computed(() => {
  return props.selectedId !== null && 
         props.selectedId !== undefined && 
         props.selectedId === props.node.id
})

// 方法
const toggleExpand = () => {
  // 通知父组件要展开/折叠这个节点
  emit('toggle-expand', props.node)
}

const handleNodeClick = () => {
  if (props.disabled) return
  
  if (hasChildren.value) {
    if (props.allowSelectParent) {
      // 允许选择父节点：触发选中事件
      emit('select', props.node)
    } else {
      // 不允许选择父节点：展开/收起
      toggleExpand()
    }
  } else {
    // 叶子节点：选中
    emit('select', props.node)
  }
}
</script>

<style lang="scss" scoped>
.category-tree-item {
  .tree-node {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid #f5f5f5;
    background: #fff;
    cursor: pointer;
    
    &:hover {
      background: #fafafa;
    }
    
    &.selected {
      background: #e6f7ff;
      border-left: 3px solid #1890ff;
    }
    
    &.has-children {
      cursor: pointer;
      
      .node-name {
        font-weight: 500;
        color: #333;
      }
      
      &:hover {
        background: #f0f0f0;
      }
    }
    
    &.can-select-parent {
      &:hover {
        background: #e6f7ff;
      }
    }
    
    &.leaf-node {
      .node-name {
        color: #666;
      }
      
      &:hover {
        background: #f0f9ff;
      }
    }
    
    .expand-icon {
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 8px;
      color: #666;
      
      .van-icon {
        font-size: 14px;
      }
    }
    
    .leaf-indicator {
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 8px;
      
      .van-icon {
        opacity: 0.6;
      }
    }
    
    .node-content {
      flex: 1;
      
      .node-name {
        font-size: 14px;
        line-height: 1.4;
      }
      
      .node-description {
        font-size: 12px;
        color: #999;
        margin-top: 2px;
        line-height: 1.2;
      }
    }
  }
  
  .children {
    background: #fafafa;
    
    .tree-node {
      background: #fafafa;
      
      &:hover {
        background: #f0f0f0;
      }
      
      &.selected {
        background: #e6f7ff;
      }
    }
  }
}
</style>