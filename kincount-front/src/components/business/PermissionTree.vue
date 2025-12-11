<template>
  <div class="permission-tree">
    <div class="tree-header">
      <slot name="header">
        <span class="title">权限选择</span>
        <van-checkbox 
          v-model="allChecked" 
          :indeterminate="isIndeterminate"
          @change="handleAllCheck"
        >
          全选
        </van-checkbox>
      </slot>
    </div>
    
    <div class="tree-content">
      <div 
        v-for="module in modules" 
        :key="module.key" 
        class="tree-module"
        :class="{ expanded: module.expanded }"
      >
        <div class="module-header" @click="toggleModule(module)">
          <van-checkbox 
            v-model="module.checked"
            :indeterminate="module.indeterminate"
            @click.stop
            @change="handleModuleCheck(module)"
          >
            <span class="module-title">{{ module.title }}</span>
          </van-checkbox>
          <van-icon :name="module.expanded ? 'arrow-down' : 'arrow'" />
        </div>
        
        <div v-if="module.expanded" class="permission-list">
          <div 
            v-for="permission in module.permissions" 
            :key="permission.key"
            class="permission-item"
          >
            <van-checkbox 
              v-model="permission.checked"
              @change="handlePermissionCheck(permission, module)"
            >
              <div class="permission-info">
                <div class="permission-name">{{ permission.name }}</div>
                <div class="permission-desc">{{ permission.description }}</div>
              </div>
            </van-checkbox>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'

const props = defineProps({
  modules: {
    type: Array,
    required: true,
    default: () => []
  },
  value: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update:modelValue', 'change'])

// 全选状态和半选状态
const allChecked = ref(false)
const isIndeterminate = ref(false)

// 获取所有权限
const getAllPermissions = () => {
  return props.modules.flatMap(module => module.permissions)
}

// 获取选中的权限数量
const getCheckedCount = () => {
  return getAllPermissions().filter(p => p.checked).length
}

// 获取总权限数量
const getTotalCount = () => {
  return getAllPermissions().length
}

// 计算选中的权限key
const selectedPermissions = computed(() => {
  return getAllPermissions()
    .filter(permission => permission.checked)
    .map(permission => permission.key)
})

// 更新全选和半选状态
const updateAllCheckState = () => {
  const checkedCount = getCheckedCount()
  const totalCount = getTotalCount()
  
  if (totalCount === 0) {
    allChecked.value = false
    isIndeterminate.value = false
    return
  }
  
  if (checkedCount === 0) {
    allChecked.value = false
    isIndeterminate.value = false
  } else if (checkedCount === totalCount) {
    allChecked.value = true
    isIndeterminate.value = false
  } else {
    allChecked.value = false
    isIndeterminate.value = true
  }
}

// 更新模块状态
const updateModuleState = (module) => {
  const permissions = module.permissions
  const checkedCount = permissions.filter(p => p.checked).length
  const totalCount = permissions.length
  
  if (totalCount === 0) {
    module.checked = false
    module.indeterminate = false
    return
  }
  
  if (checkedCount === 0) {
    module.checked = false
    module.indeterminate = false
  } else if (checkedCount === totalCount) {
    module.checked = true
    module.indeterminate = false
  } else {
    module.checked = false
    module.indeterminate = true
  }
}

// 事件处理
const toggleModule = (module) => {
  module.expanded = !module.expanded
}

const handleModuleCheck = (module) => {
  // 阻止事件冒泡，避免重复触发
  const isChecked = module.checked
  
  module.permissions.forEach(permission => {
    permission.checked = isChecked
  })
  
  // 更新全选状态
  updateAllCheckState()
  
  // 发出选中事件
  emitSelection()
}

const handlePermissionCheck = (permission, module) => {
  // 更新模块状态
  updateModuleState(module)
  
  // 更新全选状态
  updateAllCheckState()
  
  // 发出选中事件
  emitSelection()
}

const handleAllCheck = (checked) => {
  // 更新所有模块和权限
  props.modules.forEach(module => {
    module.checked = checked
    module.indeterminate = false
    module.permissions.forEach(permission => {
      permission.checked = checked
    })
  })
  
  // 更新全选状态
  allChecked.value = checked
  isIndeterminate.value = false
  
  // 发出选中事件
  emitSelection()
}

// 发出选中事件
const emitSelection = () => {
  // 使用 nextTick 确保状态更新完成
  nextTick(() => {
    const selected = selectedPermissions.value
    emit('update:modelValue', selected)
    emit('change', selected)
  })
}

// 初始化模块状态
const initModuleState = () => {
  props.modules.forEach(module => {
    updateModuleState(module)
  })
  updateAllCheckState()
}

// 监听外部值变化
watch(() => props.value, (newVal) => {
  if (!newVal || !Array.isArray(newVal)) return
  
  // 设置每个权限的选中状态
  props.modules.forEach(module => {
    module.permissions.forEach(permission => {
      permission.checked = newVal.includes(permission.key)
    })
    updateModuleState(module)
  })
  
  // 更新全选状态
  updateAllCheckState()
}, { immediate: true })

// 监听模块数据变化
watch(() => props.modules, () => {
  initModuleState()
}, { deep: true })
</script>

<style scoped lang="scss">
.permission-tree {
  background: white;
  border-radius: 8px;
  overflow: hidden;
  
  .tree-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    border-bottom: 1px solid #f0f0f0;
    
    .title {
      font-size: 16px;
      font-weight: 500;
      color: #323233;
    }
  }
  
  .tree-content {
    max-height: 400px;
    overflow-y: auto;
    
    .tree-module {
      border-bottom: 1px solid #f0f0f0;
      
      &:last-child {
        border-bottom: none;
      }
      
      .module-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        background: #fafafa;
        cursor: pointer;
        
        .module-title {
          font-weight: 500;
          margin-left: 8px;
        }
      }
      
      .permission-list {
        padding: 0 16px;
        
        .permission-item {
          padding: 10px 0;
          border-bottom: 1px solid #f7f8fa;
          
          &:last-child {
            border-bottom: none;
          }
          
          .permission-info {
            margin-left: 8px;
            
            .permission-name {
              font-size: 14px;
              color: #323233;
            }
            
            .permission-desc {
              font-size: 12px;
              color: #969799;
              margin-top: 2px;
            }
          }
        }
      }
    }
  }
}
</style>