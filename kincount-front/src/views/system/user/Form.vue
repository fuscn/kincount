<template>
  <div class="system-user-form-page">
    <van-nav-bar
      :title="isEdit ? '编辑用户' : '新增用户'"
      left-text="取消"
      right-text="保存"
      left-arrow
      @click-left="handleCancel"
      @click-right="handleSubmit"
    />

    <van-form ref="formRef" @submit="handleSubmit" class="form-container">
      <!-- 账号信息 -->
      <van-cell-group title="账号信息">
        <van-field
          v-model="form.username"
          label="用户名"
          placeholder="请输入用户名"
          maxlength="20"
          :readonly="isEdit"
          required
          :rules="[{ required: true, message: '请输入用户名' }]"
        />
        <van-field
          v-if="!isEdit"
          v-model="form.password"
          type="password"
          label="初始密码"
          placeholder="留空默认为 123456"
          maxlength="20"
        />
        <van-field
          v-model="form.real_name"
          label="真实姓名"
          placeholder="请输入真实姓名"
          maxlength="10"
          required
          :rules="[{ required: true, message: '请输入真实姓名' }]"
        />
        <van-field
          v-model="form.phone"
          label="手机号"
          type="tel"
          placeholder="请输入手机号"
          maxlength="11"
          required
          :rules="phoneRules"
        />
        <van-field
          v-model="form.email"
          label="邮箱"
          type="email"
          placeholder="请输入邮箱（可选）"
          maxlength="50"
          :rules="emailRules"
        />
      </van-cell-group>

      <!-- 角色与状态 -->
      <van-cell-group title="角色权限">
        <van-field
          v-model="roleText"
          label="角色"
          placeholder="请选择角色"
          is-link
          readonly
          required
          :rules="[{ required: true, message: '请选择角色' }]"
          @click="showRolePicker = true"
        />
      </van-cell-group>

      <van-cell-group title="状态">
        <van-cell center title="启用账号">
          <template #right-icon>
            <van-switch v-model="statusBool" />
          </template>
        </van-cell>
      </van-cell-group>

      <!-- 备注 -->
      <van-cell-group title="备注">
        <van-field
          v-model="form.remark"
          label="备注"
          type="textarea"
          placeholder="请输入备注（可选）"
          rows="2"
          autosize
          maxlength="200"
          show-word-limit
        />
      </van-cell-group>

      <!-- 时间信息（编辑） -->
      <van-cell-group title="时间信息" v-if="isEdit">
        <van-cell title="创建时间" :value="formatDate(form.created_at)" />
        <van-cell title="最后登录" :value="formatDate(form.last_login_time) || '从未登录'" />
      </van-cell-group>
    </van-form>

    <!-- 角色选择器 -->
    <van-popup v-model:show="showRolePicker" position="bottom">
      <van-picker
        :columns="roleOptions"
        @confirm="onRoleConfirm"
        @cancel="showRolePicker = false"
      />
    </van-popup>

    <van-loading v-if="loading" class="page-loading" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showToast, showConfirmDialog, showSuccessToast, showFailToast } from 'vant'
import { useAuthStore } from '@/store/modules/auth'
import {
  getUserDetail,
  addUser,
  updateUser,
  getRoleOptions
} from '@/api/system'
import dayjs from 'dayjs'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

/* 基础数据 */
const loading = ref(false)
const isEdit = computed(() => !!route.params.id)
const formRef = ref()

const form = reactive({
  id: '',
  username: '',
  password: '',
  real_name: '',
  phone: '',
  email: '',
  role_id: '',
  status: 1,
  remark: '',
  department: '',
  created_at: '',
  last_login_time: ''
})

const statusBool = computed({
  get: () => form.status === 1,
  set: (v) => (form.status = v ? 1 : 0)
})

/* 角色下拉 */
const showRolePicker = ref(false)
const roleOptions = ref([])
const roleText = computed(() => {
  const hit = roleOptions.value.find((i) => i.value === form.role_id)
  return hit ? hit.text : ''
})

/* 规则 - 修复邮箱验证 */
const phoneRules = [
  { required: true, message: '请输入手机号' },
  { pattern: /^1[3-9]\d{9}$/, message: '手机号格式错误' }
]

// 修复邮箱验证：允许为空，不为空时才验证格式
const emailRules = [
  {
    validator: (value) => {
      // 如果值为空或undefined，验证通过（因为可选）
      if (!value || value.trim() === '') {
        return true
      }
      // 如果不为空，验证邮箱格式
      return /^[\w.-]+@[\w.-]+\.\w+$/.test(value)
    },
    message: '邮箱格式错误'
  }
]

/* 工具函数 */
const formatDate = (d) => (d ? dayjs(d).format('YYYY-MM-DD HH:mm') : '')

/* 加载详情 + 角色下拉 */
const loadDetail = async () => {
  if (!isEdit.value) return
  loading.value = true
  try {
    const res = await getUserDetail(route.params.id)
    console.log('用户详情数据:', res)
    Object.assign(form, res.data || res)
  } catch (error) {
    console.error('加载详情失败:', error)
    showFailToast('加载详情失败')
    router.back()
  } finally {
    loading.value = false
  }
}

const loadRoleOptions = async () => {
  try {
    const res = await getRoleOptions()
    console.log('角色选项数据:', res)
    const list = res.list || res.data?.list || res || []
    roleOptions.value = list.map((i) => ({ text: i.name, value: i.id }))
    console.log('处理后的角色选项:', roleOptions.value)
  } catch (error) {
    console.error('加载角色列表失败:', error)
    showFailToast('加载角色列表失败')
  }
}

/* 选择角色 */
const onRoleConfirm = ({ selectedOptions }) => {
  if (selectedOptions && selectedOptions[0]) {
    form.role_id = selectedOptions[0].value
    showRolePicker.value = false
  }
}

/* 提交 */
const handleCancel = () => {
  showConfirmDialog({
    title: '确认取消',
    message: '未保存的内容将丢失，是否继续？'
  })
    .then(() => {
      router.back()
    })
    .catch(() => {
      // 用户取消操作
    })
}

const handleSubmit = async () => {
  try {
    // 验证表单
    await formRef.value.validate()
    
    if (loading.value) return
    loading.value = true

    // 构造提交数据
    const payload = {
      username: form.username.trim(),
      real_name: form.real_name.trim(),
      phone: form.phone.trim(),
      role_id: form.role_id,
      status: form.status,
    }

    // 只有邮箱有值时才提交
    if (form.email && form.email.trim()) {
      payload.email = form.email.trim()
    }

    // 只有备注有值时才提交
    if (form.remark && form.remark.trim()) {
      payload.remark = form.remark.trim()
    }

    // 只有部门有值时才提交
    if (form.department && form.department.trim()) {
      payload.department = form.department.trim()
    }

    // 新增用户时处理密码
    if (!isEdit.value) {
      payload.password = form.password || '123456'
    }

    console.log('提交数据:', payload)

    // 调用API
    if (isEdit.value) {
      await updateUser(form.id, payload)
      showSuccessToast('用户更新成功')
    } else {
      await addUser(payload)
      showSuccessToast('用户创建成功')
    }
    
    // 成功后跳转
    router.replace('/system/user')
  } catch (error) {
    console.error('保存失败:', error)
    if (error && error.name === 'ValidateError') {
      // 表单验证错误，不显示额外提示
      return
    }
    showFailToast(error?.message || '保存失败，请检查网络或联系管理员')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  console.log('表单页面挂载，isEdit:', isEdit.value)
  loadRoleOptions()
  loadDetail()
})
</script>

<style scoped lang="scss">
.system-user-form-page {
  background: #f7f8fa;
  min-height: 100vh;
}

.form-container {
  padding: 12px;
}

.page-loading {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 9999;
}

:deep(.van-cell-group__title) {
  font-size: 14px;
  color: #323233;
  font-weight: 500;
  padding: 16px 16px 8px;
  background: #f7f8fa;
}

:deep(.van-field__label) {
  width: 80px;
  flex: none;
}
</style>