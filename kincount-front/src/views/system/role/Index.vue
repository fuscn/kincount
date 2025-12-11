<template>
  <div class="system-role-page">
    <van-nav-bar title="角色管理" fixed placeholder>
      <template #right>
        <van-button 
          size="small" 
          type="primary" 
          @click="handleAdd"
          v-perm="PERM.ROLE_ADD"
        >
          新增角色
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 搜索栏 -->
    <SearchBar 
      placeholder="搜索角色名称/描述" 
      @search="handleSearch" 
      @clear="handleClearSearch" 
    />

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
            :title="item.name"
            :label="item.description || '暂无描述'"
            @click="handleView(item)"
          >
            <template #value>
              <div class="cell-right-content">
                <van-tag :type="item.status === 1 ? 'success' : 'danger'" class="status-tag">
                  {{ item.status === 1 ? '启用' : '禁用' }}
                </van-tag>
                <span class="user-count">
                  {{ item.users_count || 0 }}人
                </span>
              </div>
            </template>
          </van-cell>

          <template #right>
            <van-button 
              square 
              type="primary" 
              text="编辑" 
              @click="handleEdit(item)" 
              v-perm="PERM.ROLE_EDIT"
            />
            <van-button 
              square 
              type="warning" 
              text="权限" 
              @click="handlePermission(item)"
              v-perm="PERM.ROLE_EDIT"
            />
            <van-button 
              square 
              type="danger" 
              text="删除" 
              @click="handleDelete(item)" 
              v-perm="PERM.ROLE_DELETE"
            />
          </template>
        </van-swipe-cell>

        <van-empty 
          v-if="!listLoading && !refreshing && list.length === 0" 
          description="暂无角色数据" 
        />
      </van-list>
    </van-pull-refresh>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { 
  showToast, 
  showConfirmDialog, 
  showSuccessToast, 
  showFailToast 
} from 'vant'
import { useSystemStore } from '@/store/modules/system'
import { PERM } from '@/constants/permissions'
import SearchBar from '@/components/common/SearchBar.vue'

const router = useRouter()
const systemStore = useSystemStore()

/* 响应式数据 */
const list = ref([])
const refreshing = ref(false)
const listLoading = ref(false)
const finished = ref(false)
const isLoading = ref(false) // 防止重复请求的锁

const pagination = reactive({ 
  page: 1, 
  pageSize: 15, 
  total: 0 
})

const filters = reactive({ 
  keyword: '' 
})

/* 计算属性 */
const roleList = computed(() => systemStore.roleList || [])
const totalRoles = computed(() => systemStore.roleTotal || 0)

/* 数据加载方法 */
const loadList = async (isRefresh = false) => {
  // 防止重复请求
  if (isLoading.value) return
  isLoading.value = true

  try {
    if (isRefresh) {
      pagination.page = 1
      finished.value = false
      refreshing.value = true
    } else {
      // 如果是加载更多且已经完成，直接返回
      if (finished.value) {
        isLoading.value = false
        return
      }
      listLoading.value = true
    }

    const params = {
      page: pagination.page,
      limit: pagination.pageSize,
      keyword: filters.keyword
    }

    // 调用store方法加载数据
    const result = await systemStore.loadRoleList(params)
    
    // 从store获取数据
    const data = roleList.value
    const total = totalRoles.value

    console.log('角色列表数据:', data) // 调试用

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
    
    // 判断是否已加载完所有数据
    finished.value = list.value.length >= pagination.total
    
    // 如果还有更多数据，页码+1
    if (data.length > 0 && !finished.value) {
      pagination.page++
    }
    
    // 调试信息
    console.log('加载结果:', {
      loadedCount: list.value.length,
      total: pagination.total,
      finished: finished.value,
      currentPage: pagination.page
    })
    
  } catch (error) {
    console.error('加载角色列表失败:', error)
    showFailToast('加载失败')
    finished.value = true
  } finally {
    refreshing.value = false
    listLoading.value = false
    isLoading.value = false
  }
}

/* 事件处理方法 */
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
  router.push('/system/role/create')
}

const handleEdit = (row) => {
  router.push(`/system/role/edit/${row.id}`)
}

const handleView = (row) => {
  // 查看详情，可以跳转到详情页面或直接编辑页面
  router.push(`/system/role/edit/${row.id}`)
}

const handlePermission = (row) => {
  // 权限配置（跳转到权限分配页面或使用弹窗）
  // 这里先跳转到编辑页面，并自动展开权限配置部分
  router.push(`/system/role/edit/${row.id}#permissions`)
}

const toggleStatus = async (row) => {
  try {
    const newStatus = row.status === 1 ? 0 : 1
    
    // 调用API更新状态（这里需要添加相应的API函数）
    // await updateRoleStatus(row.id, { status: newStatus })
    
    showSuccessToast('操作成功')
    
    // 更新本地状态，避免重新加载整个列表
    const item = list.value.find(item => item.id === row.id)
    if (item) {
      item.status = newStatus
    }
  } catch (error) {
    console.error('切换角色状态失败:', error)
    showFailToast('操作失败')
  }
}

const handleDelete = async (row) => {
  try {
    // 检查是否有用户使用该角色
    if (row.user_count && row.user_count > 0) {
      showFailToast(`该角色有 ${row.user_count} 个用户正在使用，无法删除`)
      return
    }
    
    await showConfirmDialog({ 
      title: '删除角色', 
      message: `确定删除角色【${row.name}】？此操作不可恢复！` 
    })
    
    await systemStore.removeRole(row.id)
    showSuccessToast('角色已删除')
    
    // 删除后重新加载数据
    pagination.page = 1
    loadList(true)
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除角色失败:', error)
      showFailToast('删除失败')
    }
  }
}

// 在组件挂载时初始化加载数据
onMounted(() => {
  console.log('角色列表组件挂载，开始加载数据...')
  
  // 初始加载数据
  if (list.value.length === 0) {
    loadList(true)
  }
  
  // 同时加载角色选项（如果有需要）
  systemStore.loadRoleOptions().catch(error => {
    console.error('加载角色选项失败:', error)
  })
})
</script>

<style scoped lang="scss">
.system-role-page {
  background: #f7f8fa;
  min-height: 100vh;
}

// 优化滑动单元格样式
.van-swipe-cell {
  margin-bottom: 8px;
  
  &:last-child {
    margin-bottom: 0;
  }
  
  :deep(.van-swipe-cell__right) {
    display: flex;
    
    .van-button {
      height: 100%;
      border-radius: 0;
      min-width: 60px;
      
      &[type="primary"] {
        background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
      }
      
      &[type="warning"] {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      }
      
      &[type="danger"] {
        background: linear-gradient(135deg, #f43b47 0%, #453a94 100%);
      }
      
      &:active {
        opacity: 0.9;
      }
    }
  }
}

.van-cell {
  background: white;
  border-radius: 8px;
  margin: 8px 12px;
  padding: 12px 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  transition: all 0.3s ease;
  
  &:active {
    background-color: #f8f9fa;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  }
  
  .van-cell__title {
    font-weight: 500;
    color: #323233;
    
    .van-cell__label {
      margin-top: 4px;
      font-size: 12px;
      color: #969799;
      line-height: 1.4;
    }
  }
  
  .cell-right-content {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
    
    .status-tag {
      align-self: flex-end;
      font-size: 11px;
      padding: 2px 6px;
      border-radius: 10px;
      font-weight: 500;
      
      &.van-tag--success {
        background: linear-gradient(135deg, #66bb6a 0%, #43a047 100%);
        border: none;
      }
      
      &.van-tag--danger {
        background: linear-gradient(135deg, #ef5350 0%, #e53935 100%);
        border: none;
      }
    }
    
    .user-count {
      font-size: 12px;
      color: #969799;
      background: #f5f5f5;
      padding: 2px 8px;
      border-radius: 4px;
    }
  }
}

// 空状态样式
:deep(.van-empty) {
  padding-top: 100px;
  
  .van-empty__image {
    width: 150px;
    height: 150px;
  }
  
  .van-empty__description {
    color: #969799;
    font-size: 15px;
    margin-top: 12px;
  }
}

// 加载更多样式
:deep(.van-list__finished-text) {
  color: #969799;
  font-size: 13px;
  padding: 20px 0;
}

// 下拉刷新样式
:deep(.van-pull-refresh__head) {
  color: #969799;
  font-size: 13px;
}

// 导航栏按钮样式
:deep(.van-nav-bar__right) {
  .van-button {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 4px;
    font-weight: 500;
    
    &:active {
      opacity: 0.9;
    }
  }
}

// 搜索栏样式
:deep(.search-bar) {
  background: white;
  padding: 12px 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  margin-bottom: 8px;
  
  .van-field {
    background: #f7f8fa;
    border-radius: 8px;
    padding: 8px 12px;
    
    .van-field__control {
      font-size: 14px;
      color: #323233;
    }
    
    .van-field__right-icon {
      color: #969799;
    }
  }
}

// 响应式调整
@media (max-width: 320px) {
  .van-cell {
    margin: 8px;
    padding: 10px 12px;
  }
  
  :deep(.van-swipe-cell__right) {
    .van-button {
      min-width: 50px;
      font-size: 12px;
    }
  }
}

// 添加动画效果
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.van-swipe-cell {
  animation: fadeInUp 0.3s ease-out;
  animation-fill-mode: both;
  
  @for $i from 1 through 10 {
    &:nth-child(#{$i}) {
      animation-delay: #{$i * 0.05}s;
    }
  }
}
</style>