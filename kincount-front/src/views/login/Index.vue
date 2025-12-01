<!-- src/views/login/Index.vue -->
<template>
  <div class="login-wrap">
    <div class="logo">简库存</div>
    <van-form @submit="onSubmit" class="form">
      <van-field
        v-model="form.username"
        name="username"
        label="用户名"
        placeholder="请输入用户名"
        :rules="[{ required: true, message: '请输入用户名' }]"
      />
      <van-field
        v-model="form.password"
        type="password"
        name="password"
        label="密码"
        placeholder="请输入密码"
        :rules="[{ required: true, message: '请输入密码' }]"
      />
      <div style="margin: 16px;">
        <van-button round block type="primary" native-type="submit" :loading="loading">
          登录
        </van-button>
      </div>
    </van-form>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { showSuccessToast, showFailToast } from 'vant'
import { useAuthStore } from '@/store/modules/auth'

const router = useRouter()
const auth = useAuthStore()

const form = ref({ username: 'admin', password: 'a123456' })
const loading = ref(false)
const debug = ref(true) // 开启调试信息

onMounted(() => {
  console.log('🔐 登录页面加载')
  console.log('当前Token:', auth.token)
  console.log('当前用户:', auth.user)
})

async function onSubmit() {
  console.log('📝 提交登录表单:', form.value)
  loading.value = true
  
  try {
    await auth.login(form.value)
    console.log('✅ 登录成功，准备跳转')
    showSuccessToast('登录成功')
    
    // 确保跳转到 dashboard
    await router.replace('/dashboard')
    console.log('🔄 跳转完成')
    
  } catch (e) {
    console.error('❌ 登录失败:', e)
    showFailToast(e.message || '登录失败')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped lang="scss">
.login-wrap {
  height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #f7f8fa;
  padding: 20px;
}
.logo {
  font-size: 32px;
  font-weight: bold;
  margin-bottom: 40px;
  color: #1989fa;
}
.form {
  width: 100%;
  max-width: 400px;
}
.debug-info {
  margin-top: 20px;
  padding: 12px;
  background: #fff3cd;
  border-radius: 8px;
  font-size: 12px;
}
</style>