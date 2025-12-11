<!-- src/views/system/user/Profile.vue -->
<template>
  <div class="profile-page">
    <van-nav-bar title="我的资料" left-text="返回" left-arrow @click-left="$router.back()" />

    <!-- 头像栏 -->
    <div class="avatar-bar">
      <van-image round width="80" height="80" :src="user.avatar || defaultAvatar" @click="changeAvatar" />
      <div class="name">{{ user.real_name || user.username }}</div>
      <div class="sub-name">账号：{{ user.username }}</div>
    </div>

    <!-- 资料列表 -->
    <van-cell-group title="基本信息">
      <van-cell title="姓名" :value="user.real_name" is-link @click="edit('real_name')" />
      <van-cell title="手机" :value="user.phone || '未填写'" is-link @click="edit('phone')" />
      <van-cell title="邮箱" :value="user.email || '未填写'" is-link @click="edit('email')" />
      <van-cell title="所属角色" :value="roleText" />
      <van-cell title="所属部门" :value="user.dept_name || '未设置'" />
    </van-cell-group>

    <van-cell-group title="系统信息">
      <van-cell title="用户ID" :value="user.id" />
      <van-cell title="最后登录" :value="formatLastLogin" />
      <van-cell title="账户状态">
        <template #value>
          <van-tag :type="user.status === 1 ? 'success' : 'danger'">
            {{ user.status === 1 ? '正常' : '禁用' }}
          </van-tag>
        </template>
      </van-cell>
    </van-cell-group>
    <!-- 在「系统信息」van-cell-group 下方追加 -->
    <van-cell-group title="账户操作">
      <van-button type="danger" plain round block @click="onLogout">
        退出登录
      </van-button>
    </van-cell-group>
    <!-- 头像上传input（隐藏） -->
    <input ref="avatarInput" type="file" accept="image/*" style="display: none" @change="onAvatarChange" />
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useAuthStore } from '@/store/modules/auth'
import { useRouter } from 'vue-router'
import { showToast, showImagePreview } from 'vant'
import { uploadImage } from '@/api/utils'
import { updateProfile } from '@/api/auth'
import dayjs from 'dayjs'

const authStore = useAuthStore()
const router = useRouter()

const avatarInput = ref()
const defaultAvatar = new URL('@/assets/avatar-default.png', import.meta.url).href

const user = computed(() => authStore.user)

const roleText = computed(() => {
  if (!user.value.roles || user.value.roles.length === 0) return '未分配'
  return user.value.roles.join('、')
})

const formatLastLogin = computed(() => {
  const t = user.value.last_login_time
  return t ? dayjs(t).format('YYYY-MM-DD HH:mm') : '暂无记录'
})

/* 更换头像 */
function changeAvatar() {
  avatarInput.value.click()
}
async function onAvatarChange(e) {
  const file = e.target.files[0]
  if (!file) return
  try {
    const { url } = await uploadImage(file)
    await updateProfile({ avatar: url })
    await authStore.refreshUser()
    showToast('头像已更新')
  } catch {
    showToast('上传失败')
  }
}
/* 退出登录 */
function onLogout() {
  authStore.logout()          // 1. 清本地 token / user 信息
  router.replace({ name: 'Login' }) // 2. 跳转到登录页
}
/* 跳转编辑页 */
function edit(field) {
  router.push({
    name: 'ProfileEdit',
    query: { field }
  })
}
</script>

<style scoped lang="scss">
.profile-page {
  background: #f7f8fa;
  min-height: 100vh;
  padding-bottom: env(safe-area-inset-bottom);
}

.avatar-bar {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 24px 16px;
  background: #fff;
  margin-bottom: 12px;

  .name {
    margin-top: 12px;
    font-size: 18px;
    font-weight: 600;
  }

  .sub-name {
    margin-top: 4px;
    font-size: 13px;
    color: #969799;
  }
}
</style>