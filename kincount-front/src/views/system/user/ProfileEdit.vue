<!-- src/views/system/user/ProfileEdit.vue -->
<template>
  <div class="profile-edit-page">
    <van-nav-bar
      :title="pageTitle"
      left-text="取消"
      right-text="保存"
      left-arrow
      @click-left="$router.back()"
      @click-right="handleSubmit"
    />

    <van-form ref="formRef" @submit="handleSubmit" class="form-container">
      <van-cell-group>
        <!-- 根据字段类型显示不同的输入框 -->
        <van-field
          v-if="field === 'real_name'"
          v-model="formValue"
          label="姓名"
          placeholder="请输入真实姓名"
          maxlength="10"
          required
          :rules="[{ required: true, message: '请输入真实姓名' }]"
        />

        <van-field
          v-else-if="field === 'phone'"
          v-model="formValue"
          label="手机号"
          type="tel"
          placeholder="请输入手机号"
          maxlength="11"
          required
          :rules="phoneRules"
        />

        <van-field
          v-else-if="field === 'email'"
          v-model="formValue"
          label="邮箱"
          type="email"
          placeholder="请输入邮箱"
          maxlength="50"
          :rules="emailRules"
        />

        <van-field
          v-else
          v-model="formValue"
          label="内容"
          placeholder="请输入内容"
          :rules="[{ required: true, message: '请输入内容' }]"
        />
      </van-cell-group>
    </van-form>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showToast, showSuccessToast } from 'vant'
import { useAuthStore } from '@/store/modules/auth'
import { getErrorMsg } from '@/utils/error-code'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const formRef = ref()
const field = ref('')
const formValue = ref('')

/* 校验规则（同上，省略） */
const phoneRules = [
  { required: true, message: '请输入手机号' },
  { pattern: /^1[3-9]\d{9}$/, message: '手机号格式错误' }
]
const emailRules = [
  { validator: v => !v || /^[\w.-]+@[\w.-]+\.\w+$/.test(v.trim()), message: '邮箱格式错误' }
]

const pageTitle = computed(() => {
  const map = { real_name: '编辑姓名', phone: '编辑手机号', email: '编辑邮箱' }
  return map[field.value] || '编辑资料'
})

onMounted(() => {
  field.value = route.query.field || ''
  const u = authStore.user
  formValue.value = { real_name: u.real_name || '', phone: u.phone || '', email: u.email || '' }[field.value] || ''
})

/* 提交 → 改走 UserStore */
const handleSubmit = async () => {
  try {
    await formRef.value.validate()
    const payload = { [field.value]: formValue.value.trim() }
    await authStore.updateProfile(payload)   // ← 走 store
    showSuccessToast('更新成功')
    router.back()
  } catch (e) {
    if (e?.name === 'ValidateError') return
    showToast(getErrorMsg(e?.code, e?.message) || '更新失败')
  }
}
</script>

<style scoped lang="scss">
.profile-edit-page {
  background: #f7f8fa;
  min-height: 100vh;
}

.form-container {
  padding: 12px;
}

:deep(.van-field__label) {
  width: 80px;
  flex: none;
}
</style>