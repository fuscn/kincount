<!-- src/views/system/user/Password.vue -->
<template>
  <div class="password-page">
    <van-nav-bar
      title="修改密码"
      left-text="取消"
      right-text="保存"
      left-arrow
      @click-left="$router.back()"
      @click-right="handleSubmit"
    />

    <van-form ref="formRef" @submit="handleSubmit" class="form-container">
      <van-cell-group title="旧密码">
        <van-field
          v-model="form.oldPassword"
          type="password"
          label="旧密码"
          placeholder="请输入旧密码"
          required
          :rules="[{ required: true, message: '请输入旧密码' }]"
        />
      </van-cell-group>

      <van-cell-group title="新密码">
        <van-field
          v-model="form.newPassword"
          type="password"
          label="新密码"
          placeholder="6-20 位字母+数字组合"
          required
          :rules="[
            { required: true, message: '请输入新密码' },
            { validator: validatePassword, message: '6-20 位字母+数字组合' }
          ]"
        />
        <van-field
          v-model="form.confirmPassword"
          type="password"
          label="确认密码"
          placeholder="请再次输入新密码"
          required
          :rules="[
            { required: true, message: '请再次输入新密码' },
            { validator: validateConfirm, message: '两次密码输入不一致' }
          ]"
        />
      </van-cell-group>
    </van-form>

    <van-loading v-if="loading" class="page-loading" />
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { showToast, showSuccessToast } from 'vant'
import { changePassword } from '@/api/auth'

const router = useRouter()
const formRef = ref()
const loading = ref(false)

const form = reactive({
  oldPassword: '',
  newPassword: '',
  confirmPassword: ''
})

/* 校验规则 */
const validatePassword = val =>
  /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{6,20}$/.test(val)

const validateConfirm = val => val === form.newPassword

/* 提交 */
const handleSubmit = async () => {
  try {
    await formRef.value.validate()
    loading.value = true
    await changePassword({
      old_password: form.oldPassword,
      new_password: form.newPassword
    })
    showSuccessToast('密码已修改，请重新登录')
    router.replace('/login')
  } catch (e) {
    showToast(e?.message || '修改失败')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped lang="scss">
.password-page {
  min-height: 100vh;
  background: #f7f8fa;
}
.form-container {
  padding: 12px;
}
.page-loading {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}
</style>