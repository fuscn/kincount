<template>
  <div class="permission-tree">
    <div class="tree-header">
      <slot name="header">
        <span class="title">权限选择</span>
        <van-checkbox v-model="allChecked" @change="handleAllCheck">
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
import { ref, computed, watch } from 'vue'

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

// 全选状态
const allChecked = ref(false)

// 计算选中的权限
const selectedPermissions = computed(() => {
  return props.modules
    .flatMap(module => module.permissions)
    .filter(permission => permission.checked)
    .map(permission => permission.key)
})

// 更新全选状态
const updateAllChecked = () => {
  const allPermissions = props.modules.flatMap(m => m.permissions)
  const checkedCount = allPermissions.filter(p => p.checked).length
  const totalCount = allPermissions.length
  
  allChecked.value = checkedCount === totalCount && totalCount > 0
}

// 更新模块状态
const updateModuleState = (module) => {
  const permissions = module.permissions
  const checkedCount = permissions.filter(p => p.checked).length
  const totalCount = permissions.length
  
  module.checked = checkedCount === totalCount && totalCount > 0
  module.indeterminate = checkedCount > 0 && checkedCount < totalCount
}

// 事件处理
const toggleModule = (module) => {
  module.expanded = !module.expanded
}

const handleModuleCheck = (module) => {
  module.permissions.forEach(permission => {
    permission.checked = module.checked
  })
  updateAllChecked()
  emitSelection()
}

const handlePermissionCheck = (permission, module) => {
  updateModuleState(module)
  updateAllChecked()
  emitSelection()
}

const handleAllCheck = (checked) => {
  props.modules.forEach(module => {
    module.checked = checked
    module.permissions.forEach(permission => {
      permission.checked = checked
    })
  })
  emitSelection()
}

// 发出选中事件
const emitSelection = () => {
  emit('update:modelValue', selectedPermissions.value)
  emit('change', selectedPermissions.value)
}

// 监听外部值变化
watch(() => props.value, (newVal) => {
  if (!newVal || !Array.isArray(newVal)) return
  
  props.modules.forEach(module => {
    module.permissions.forEach(permission => {
      permission.checked = newVal.includes(permission.key)
    })
    updateModuleState(module)
  })
  updateAllChecked()
}, { immediate: true })
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