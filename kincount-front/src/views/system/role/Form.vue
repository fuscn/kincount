<template>
  <div class="role-form-page">
    <van-nav-bar 
      :title="form.id ? '编辑角色' : '新增角色'" 
      fixed 
      placeholder
      :left-arrow="true"
      @click-left="router.back()"
    >
      <template #right>
        <van-button 
          size="small" 
          type="primary" 
          @click="handleSubmit"
          :loading="submitting"
          v-perm="form.id ? PERM.ROLE_EDIT : PERM.ROLE_ADD"
        >
          保存
        </van-button>
      </template>
    </van-nav-bar>

    <van-form @submit="handleSubmit" class="role-form">
      <!-- 基本信息 -->
      <van-cell-group title="基本信息" inset>
        <van-field
          v-model="form.name"
          label="角色名称"
          placeholder="请输入角色名称"
          :rules="[{ required: true, message: '请填写角色名称' }]"
          clearable
        />
        
        <van-field
          v-model="form.description"
          label="角色描述"
          placeholder="请输入角色描述"
          type="textarea"
          rows="2"
          autosize
          maxlength="200"
          show-word-limit
        />
        
        <van-field name="status" label="状态">
          <template #input>
            <van-switch v-model="form.status" size="20" />
            <span class="status-text">{{ form.status ? '启用' : '禁用' }}</span>
          </template>
        </van-field>
      </van-cell-group>

      <!-- 权限配置 -->
      <van-cell-group title="权限配置" inset>
        <div class="permission-section">
          <div class="permission-header">
            <span class="section-title">选择权限</span>
            <van-checkbox v-model="selectAll" @change="handleSelectAll">
              全选/全不选
            </van-checkbox>
          </div>
          
          <div class="permission-tree">
            <!-- 权限树组件 -->
            <PermissionTree 
              v-model:modules="permissionTree"
              :value="form.permissions"
              @change="handlePermissionsChange"
            />
          </div>
        </div>
      </van-cell-group>
    </van-form>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { 
  showToast, 
  showSuccessToast, 
  showFailToast 
} from 'vant'
import { PERM, getPermissionTree } from '@/constants/permissions'
import { 
  getRoleDetail, 
  addRole, 
  updateRole, 
  getRolePermissions  // ✅ 使用正确的函数名
} from '@/api/system'
import PermissionTree from '@/components/business/PermissionTree.vue'

const router = useRouter()
const route = useRoute()

const isEdit = computed(() => !!route.params.id)
const submitting = ref(false)
const selectAll = ref(false)

// 表单数据
const form = reactive({
  id: null,
  name: '',
  description: '',
  permissions: [],
  status: 1
})

// 权限树数据
const permissionTree = ref([])

// 加载权限数据
const loadPermissions = async () => {
  try {
    // 使用正确的函数名 getRolePermissions
    const res = await getRolePermissions()
    const permissions = res.data || res || []
    
    // 构建权限树结构（按模块分组）
    const modules = {
      system: { title: '系统管理', permissions: [] },
      product: { title: '商品管理', permissions: [] },
      purchase: { title: '采购管理', permissions: [] },
      sale: { title: '销售管理', permissions: [] },
      stock: { title: '库存管理', permissions: [] },
      customer: { title: '客户管理', permissions: [] },
      supplier: { title: '供应商管理', permissions: [] },
      financial: { title: '财务管理', permissions: [] }
    }
    
    // 根据权限key的前缀分组
    permissions.forEach(perm => {
      const moduleKey = perm.key?.split(':')[0] || perm.split(':')[0]
      if (modules[moduleKey]) {
        modules[moduleKey].permissions.push({
          key: perm.key || perm,
          name: perm.name || perm,
          description: perm.description || '',
          checked: false
        })
      }
    })
    
    // 转换为数组并添加控制属性
    permissionTree.value = Object.entries(modules)
      .filter(([_, module]) => module.permissions.length > 0)
      .map(([key, module]) => ({
        key,
        title: module.title,
        permissions: module.permissions,
        expanded: true,
        checked: false,
        indeterminate: false
      }))
      
  } catch (error) {
    console.error('加载权限列表失败:', error)
    showFailToast('加载权限失败')
    
    // 如果API调用失败，使用前端定义的权限树作为后备
    permissionTree.value = getPermissionTree()
  }
}

// 加载角色详情
const loadRoleDetail = async (id) => {
  try {
    const res = await getRoleDetail(id)
    const data = res.data || res || {}
    
    Object.assign(form, {
      id: data.id,
      name: data.name,
      description: data.description,
      status: data.status || 1,
      permissions: data.permissions ? Object.values(data.permissions) : []
    })
    
    // 设置权限选中状态
    updatePermissionSelection()
  } catch (error) {
    console.error('加载角色详情失败:', error)
    showFailToast('加载失败')
    router.back()
  }
}

// 更新权限选择状态
const updatePermissionSelection = () => {
  if (!form.permissions || form.permissions.length === 0) return
  
  permissionTree.value.forEach(module => {
    module.permissions.forEach(permission => {
      permission.checked = form.permissions.includes(permission.key)
    })
    updateModuleState(module)
  })
}

// 更新模块状态
const updateModuleState = (module) => {
  const permissions = module.permissions
  const checkedCount = permissions.filter(p => p.checked).length
  const totalCount = permissions.length
  
  module.checked = checkedCount === totalCount && totalCount > 0
  module.indeterminate = checkedCount > 0 && checkedCount < totalCount
}

// 表单提交
const handleSubmit = async () => {
  try {
    submitting.value = true
    
    // 验证必填项
    if (!form.name.trim()) {
      showToast('请填写角色名称')
      return
    }
    
    // 收集选中的权限
    const selectedPermissions = permissionTree.value
      .flatMap(module => module.permissions)
      .filter(permission => permission.checked)
      .map(permission => permission.key)
    
    const formData = {
      name: form.name.trim(),
      description: form.description?.trim() || '',
      permissions: selectedPermissions,
      status: form.status ? 1 : 0
    }
    
    if (isEdit.value) {
      await updateRole(form.id, formData)
      showSuccessToast('更新成功')
    } else {
      await addRole(formData)
      showSuccessToast('创建成功')
    }
    
    router.back()
  } catch (error) {
    console.error('保存角色失败:', error)
    showFailToast('保存失败')
  } finally {
    submitting.value = false
  }
}

// 初始化
onMounted(async () => {
  await loadPermissions()
  
  if (isEdit.value) {
    await loadRoleDetail(route.params.id)
  }
})
</script>

<style scoped lang="scss">
.role-form-page {
  background: #f7f8fa;
  min-height: 100vh;
  
  .role-form {
    padding-top: 16px;
  }
  
  :deep(.van-cell-group__title) {
    color: #323233;
    font-size: 15px;
    padding: 16px 0 8px;
  }
  
  .status-text {
    margin-left: 8px;
    font-size: 14px;
    color: #323233;
  }
  
  .permission-section {
    margin-top: 8px;
    
    .permission-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 16px;
      background: white;
      border-bottom: 1px solid #f0f0f0;
      
      .section-title {
        font-size: 15px;
        font-weight: 500;
        color: #323233;
      }
    }
    
    .permission-tree {
      background: white;
    }
  }
}
</style>