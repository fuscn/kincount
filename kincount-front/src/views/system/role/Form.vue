<template>
    <div class="role-form-page">
        <van-nav-bar :title="form.id ? '编辑角色' : '新增角色'" fixed placeholder :left-arrow="true"
            @click-left="router.back()">
            <template #right>
                <van-button size="small" type="primary" @click="handleSubmit" :loading="submitting"
                    v-perm="form.id ? PERM.ROLE_EDIT : PERM.ROLE_ADD">
                    保存
                </van-button>
            </template>
        </van-nav-bar>

        <van-form @submit="handleSubmit" class="role-form">
            <!-- 基本信息 -->
            <van-cell-group title="基本信息" inset>
                <van-field v-model="form.name" label="角色名称" placeholder="请输入角色名称"
                    :rules="[{ required: true, message: '请填写角色名称' }]" clearable />

                <van-field v-model="form.description" label="角色描述" placeholder="请输入角色描述" type="textarea" rows="2"
                    autosize maxlength="200" show-word-limit />

                <van-field name="status" label="状态">
                    <template #input>
                        <van-switch v-model="form.enabled" size="20" />
                        <span class="status-text">{{ form.enabled ? '启用' : '禁用' }}</span>
                    </template>
                </van-field>
            </van-cell-group>

            <!-- 权限配置 -->
            <van-cell-group title="权限配置" inset>
                <div class="permission-section">
                    <div class="permission-tree">
                        <!-- 权限树组件 -->
                        <PermissionTree v-if="permissionTree.length > 0" v-model:modules="permissionTree"
                            :value="form.permissions" @change="handlePermissionsChange" />
                        <div v-else class="loading-permissions">
                            <van-loading type="spinner" size="24px">加载权限中...</van-loading>
                        </div>
                    </div>
                </div>
            </van-cell-group>
        </van-form>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue'
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
    updateRole
} from '@/api/system'
import PermissionTree from '@/components/business/PermissionTree.vue'

const router = useRouter()
const route = useRoute()

const isEdit = computed(() => !!route.params.id)
const submitting = ref(false)
const selectAll = ref(false)
const isSelectingAll = ref(false) // 防止递归触发

// 表单数据
const form = reactive({
    id: null,
    name: '',
    description: '',
    permissions: [],
    enabled: true
})

// 权限树数据 - 创建深拷贝，避免修改原始数据
const permissionTree = ref(JSON.parse(JSON.stringify(getPermissionTree())))

// 获取所有权限的key
const getAllPermissionKeys = () => {
    return permissionTree.value.flatMap(module => 
        module.permissions.map(permission => permission.key)
    )
}

// 更新全选状态
const updateSelectAllState = () => {
    const allPermissionKeys = getAllPermissionKeys()
    const selectedKeys = form.permissions
    
    if (allPermissionKeys.length === 0) {
        selectAll.value = false
        return
    }
    
    // 检查是否所有权限都被选中
    selectAll.value = allPermissionKeys.every(key => selectedKeys.includes(key))
}

// 事件处理函数
const handleSelectAll = (checked) => {
    if (isSelectingAll.value) return
    
    isSelectingAll.value = true
    selectAll.value = checked
    
    if (checked) {
        // 全选：选中所有权限
        const allKeys = getAllPermissionKeys()
        form.permissions = [...allKeys]
        
        // 更新权限树的选中状态
        permissionTree.value.forEach(module => {
            module.checked = true
            module.indeterminate = false
            module.permissions.forEach(permission => {
                permission.checked = true
            })
        })
    } else {
        // 全不选：清空所有权限
        form.permissions = []
        
        // 更新权限树的选中状态
        permissionTree.value.forEach(module => {
            module.checked = false
            module.indeterminate = false
            module.permissions.forEach(permission => {
                permission.checked = false
            })
        })
    }
    
    setTimeout(() => {
        isSelectingAll.value = false
    }, 100)
}

const handlePermissionsChange = (permissions) => {
    // 更新表单权限
    form.permissions = permissions
    
    // 更新权限树的模块状态
    permissionTree.value.forEach(module => {
        const modulePermissions = module.permissions
        const modulePermissionKeys = modulePermissions.map(p => p.key)
        const moduleSelectedKeys = permissions.filter(key => modulePermissionKeys.includes(key))
        
        // 更新模块下的权限选中状态
        modulePermissions.forEach(permission => {
            permission.checked = moduleSelectedKeys.includes(permission.key)
        })
        
        // 更新模块的选中和半选状态
        if (moduleSelectedKeys.length === 0) {
            module.checked = false
            module.indeterminate = false
        } else if (moduleSelectedKeys.length === modulePermissionKeys.length) {
            module.checked = true
            module.indeterminate = false
        } else {
            module.checked = false
            module.indeterminate = true
        }
    })
    
    // 更新全选状态
    updateSelectAllState()
}

// 监听form.permissions变化，同步更新权限树状态
watch(() => form.permissions, () => {
    updateSelectAllState()
}, { deep: true })

// 加载角色详情
const loadRoleDetail = async (id) => {
    try {
        const res = await getRoleDetail(id)
        const data = res.data || res || {}

        Object.assign(form, {
            id: data.id,
            name: data.name,
            description: data.description || '',
            enabled: data.status === 1,
            permissions: data.permissions ? Object.values(data.permissions) : []
        })

        // 重置权限树
        permissionTree.value = JSON.parse(JSON.stringify(getPermissionTree()))
        
        // 设置权限选中状态
        handlePermissionsChange(form.permissions)

    } catch (error) {
        console.error('加载角色详情失败:', error)
        showFailToast('加载失败')
        router.back()
    }
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

        // 准备提交数据
        const formData = {
            name: form.name.trim(),
            description: form.description?.trim() || '',
            permissions: form.permissions,
            status: form.enabled ? 1 : 0
        }

        console.log('提交的数据:', formData)

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
            min-height: 200px;

            .loading-permissions {
                display: flex;
                justify-content: center;
                align-items: center;
                height: 200px;

                :deep(.van-loading__text) {
                    margin-left: 8px;
                    font-size: 14px;
                    color: #969799;
                }
            }
        }
    }
}
</style>