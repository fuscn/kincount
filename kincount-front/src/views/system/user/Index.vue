<template>
  <div class="system-user-page">
    <van-nav-bar title="用户管理" fixed placeholder>
      <template #right>
        <van-button size="small" type="primary" @click="handleAdd" v-perm="PERM.USER_MANAGE">
          新增
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 搜索 -->
    <SearchBar placeholder="搜索用户名/姓名/手机" @search="handleSearch" @clear="handleClearSearch" />

    <!-- 列表 -->
    <van-pull-refresh v-model="refreshing" @refresh="onRefresh">
      <van-list
        v-model:loading="listLoading"
        :finished="finished"
        :immediate-check="false"
        finished-text="没有更多了"
        @load="onLoad"
      >
        <van-swipe-cell v-for="item in list" :key="item.id">
          <van-cell
            :title="item.real_name || item.username"
            :label="`账号：${item.username} | 手机：${item.phone} | 角色：${item.role?.name || '无'}`"
            
          >
            <template #value>
              <van-tag :type="item.status === 1 ? 'success' : 'danger'" class="status-tag">
                {{ item.status === 1 ? '启用' : '禁用' }}
              </van-tag>
              <van-switch
                :model-value="item.status === 1"
                size="20"
                @click.stop
                @update:model-value="toggleStatus(item)"
                v-perm="PERM.USER_MANAGE"
              />
            </template>
          </van-cell>

          <template #right>
            <van-button square type="primary" text="编辑" @click="handleEdit(item)" v-perm="PERM.USER_MANAGE" />
            <van-button square type="warning" text="重置密码" @click="handleResetPwd(item)" v-perm="PERM.USER_MANAGE" />
            <van-button square type="danger" text="删除" @click="handleDelete(item)" v-perm="PERM.USER_MANAGE" />
          </template>
        </van-swipe-cell>

        <van-empty v-if="!listLoading && !refreshing && list.length === 0" description="暂无用户数据" />
      </van-list>
    </van-pull-refresh>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { 
  showToast, 
  showConfirmDialog, 
  showSuccessToast, 
  showFailToast 
} from 'vant'
import { useAuthStore } from '@/store/modules/auth'
import { PERM } from '@/constants/permissions'
import { getUserList, setUserStatus, resetUserPassword, deleteUser } from '@/api/system'
import SearchBar from '@/components/common/SearchBar.vue'

const router = useRouter()
const authStore = useAuthStore()

/* 响应式数据 */
const list = ref([])
const refreshing = ref(false)
const listLoading = ref(false)
const finished = ref(false)
const isLoading = ref(false) // 加载锁，防止重复请求

const pagination = reactive({ 
  page: 1, 
  pageSize: 15, 
  total: 0 
})

const filters = reactive({ 
  keyword: '' 
})

/* 数据加载方法 */
const loadList = async (isRefresh = false) => {
  // 加载锁，防止重复请求
  if (isLoading.value) return
  isLoading.value = true

  try {
    if (isRefresh) {
      pagination.page = 1
      finished.value = false
      refreshing.value = true
    } else {
      // 如果是加载更多且已经完成，直接返回
      if (finished.value) return
      listLoading.value = true
    }

    const params = {
      page: pagination.page,
      limit: pagination.pageSize,
      keyword: filters.keyword
    }

    const res = await getUserList(params)
    console.log('API响应数据:', res) // 调试用
    
    // 根据你提供的后端数据结构解析数据
    const data = res.list || []
    const total = res.total || 0

    if (isRefresh) {
      // 刷新时直接替换数据
      list.value = data
    } else {
      // 加载更多时数据去重
      const existingIds = new Set(list.value.map(item => item.id))
      const newItems = data.filter(item => !existingIds.has(item.id))
      list.value.push(...newItems)
    }

    pagination.total = total
    finished.value = list.value.length >= pagination.total
    
    if (!finished.value) {
      pagination.page++
    }
    
    console.log('当前列表数据:', list.value) // 调试用
  } catch (error) {
    console.error('加载用户列表失败:', error)
    showFailToast('加载失败')
    finished.value = true
  } finally {
    refreshing.value = false
    listLoading.value = false
    isLoading.value = false
  }
}

/* 分离的事件处理方法 */
const onRefresh = () => {
  loadList(true)
}

const onLoad = () => {
  loadList(false)
}

const handleSearch = (keyword) => {
  filters.keyword = keyword
  pagination.page = 1 // 搜索时重置页码
  loadList(true)
}

const handleClearSearch = () => {
  filters.keyword = ''
  pagination.page = 1 // 清空搜索时重置页码
  loadList(true)
}

const handleAdd = () => {
  router.push('/system/user/create')
}

const handleEdit = (row) => {
  router.push(`/system/user/edit/${row.id}`)
}

const toggleStatus = async (row) => {
  try {
    const newStatus = row.status === 1 ? 0 : 1
    await setUserStatus(row.id, newStatus)
    showSuccessToast('操作成功')
    // 更新本地状态，避免重新加载整个列表
    const item = list.value.find(item => item.id === row.id)
    if (item) {
      item.status = newStatus
    }
  } catch (error) {
    console.error('切换用户状态失败:', error)
    showFailToast('操作失败')
  }
}

const handleResetPwd = async (row) => {
  try {
    await showConfirmDialog({
      title: '重置密码',
      message: `将用户【${row.real_name || row.username}】密码重置为 "123456"，继续？`
    })
    
    await resetUserPassword(row.id, { password: '123456' })
    showSuccessToast('密码已重置为 123456')
  } catch (error) {
    if (error !== 'cancel') {
      console.error('重置密码失败:', error)
      showFailToast('重置失败')
    }
  }
}

const handleDelete = async (row) => {
  try {
    await showConfirmDialog({ 
      title: '删除用户', 
      message: `确定删除用户【${row.real_name || row.username}】？` 
    })
    
    await deleteUser(row.id)
    showSuccessToast('用户已删除')
    
    // 删除后重新加载数据，重置页码
    pagination.page = 1
    loadList(true)
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除用户失败:', error)
      showFailToast('删除失败')
    }
  }
}

// 添加 onMounted 来初始加载数据
onMounted(() => {
  console.log('组件挂载，开始加载数据...')
  loadList(true)
})
</script>

<style scoped lang="scss">
.system-user-page {
  background: #f7f8fa;
  min-height: 100vh;
}

// 优化滑动单元格按钮样式
.van-swipe-cell {
  margin-bottom: 8px;
  
  :deep(.van-swipe-cell__right) {
    display: flex;
    
    .van-button {
      height: 100%;
      border-radius: 0;
    }
  }
}

.van-cell {
  background: white;
}

.status-tag {
  margin-right: 8px;
}
</style>