<template>
  <div class="customer-page">
    <van-nav-bar title="客户管理" fixed placeholder>
      <template #right>
        <van-button size="small" type="primary" @click="handleAdd" v-perm="PERM.CUSTOMER_ADD">
          新增
        </van-button>
      </template>
    </van-nav-bar>

    <SearchBar placeholder="搜索客户名称/联系人/电话" @search="handleSearch" @clear="handleClearSearch" />

    <van-pull-refresh v-model="refreshing" @refresh="onRefresh">
      <van-list
        v-model:loading="listLoading"
        :finished="finished"
        finished-text="没有更多了"
        @load="onLoad"
      >
        <van-swipe-cell v-for="item in list" :key="item.id">
          <van-cell
            :title="item.name"
            :label="`联系人：${item.contact_person} | 电话：${item.phone}`"

          >
            <template #value>
              <van-switch
                :model-value="item.status === 1"
                size="20"
                @click.stop
                @update:model-value="toggleStatus(item)"
                v-perm="PERM.CUSTOMER_EDIT"
              />
            </template>
          </van-cell>

          <template #right>
            <van-button square type="primary" text="编辑" @click="handleEdit(item)" v-perm="PERM.CUSTOMER_EDIT" />
            <van-button square type="danger" text="删除" @click="handleDelete(item)" v-perm="PERM.CUSTOMER_DELETE" />
          </template>
        </van-swipe-cell>

        <van-empty v-if="!listLoading && !refreshing && list.length === 0" description="暂无客户数据" />
      </van-list>
    </van-pull-refresh>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { showToast, showConfirmDialog, showSuccessToast } from 'vant'
import { useCustomerStore } from '@/store/modules/customer'
import { PERM } from '@/constants/permissions'
import SearchBar from '@/components/common/SearchBar.vue'

const router = useRouter()
const customerStore = useCustomerStore()

// 响应式数据
const list = ref([])
const refreshing = ref(false)
const listLoading = ref(false)
const finished = ref(false)
const pagination = reactive({ 
  page: 1, 
  pageSize: 15, 
  total: 0 
})
const filters = reactive({ 
  keyword: '' 
})


/**
 * 加载客户列表
 * @param {boolean} isRefresh - 是否为刷新操作
 */
const loadList = async (isRefresh = false) => {
  
  // 防止重复请求
  if ((!isRefresh && listLoading.value) || (isRefresh && refreshing.value)) {
    return
  }
  
  try {
    // 设置加载状态
    if (isRefresh) {
      pagination.page = 1
      finished.value = false
      refreshing.value = true
    } else {
      listLoading.value = true
    }

    // 准备请求参数
    const params = { 
      page: pagination.page, 
      limit: pagination.pageSize,
      keyword: filters.keyword 
    }


    // 调用 store 方法获取数据
    const res = await customerStore.loadList(params)

    if (!res) {
      throw new Error('Store 返回数据为空')
    }

    const data = res.list || []
    const total = res.total || 0


    // 更新列表数据
    if (isRefresh) {
      list.value = data
    } else {
      list.value.push(...data)
    }

    // 更新分页信息
    pagination.total = total
    
    // 判断是否已加载完所有数据
    finished.value = list.value.length >= total
    
    // 如果还有更多数据，增加页码
    if (!finished.value && !isRefresh) {
      pagination.page++
    }

  } catch (error) {
    debugInfo.lastError = error.message
    showToast('加载失败: ' + (error.message || '未知错误'))
    finished.value = true
  } finally {
    // 重置加载状态
    refreshing.value = false
    listLoading.value = false
  }
}

/**
 * 下拉刷新
 */
const onRefresh = () => {
  loadList(true)
}

/**
 * 滚动加载
 */
const onLoad = () => {
  loadList(false)
}

/**
 * 处理搜索
 */
const handleSearch = (keyword) => {
  filters.keyword = keyword
  loadList(true)
}

/**
 * 清除搜索
 */
const handleClearSearch = () => {
  filters.keyword = ''
  loadList(true)
}

/**
 * 新增客户
 */
const handleAdd = () => {
  router.push('/customer/create')
}

/**
 * 编辑客户
 */
const handleEdit = (item) => {
  router.push(`/customer/edit/${item.id}`)
}

/**
 * 切换客户状态
 */
const toggleStatus = async (item) => {
  try {
    const newStatus = item.status === 1 ? 0 : 1
    await customerStore.toggleStatus(item.id, newStatus)
    showSuccessToast('操作成功')
    loadList(true)
  } catch (error) {
    showToast('操作失败')
  }
}

/**
 * 删除客户
 */
const handleDelete = async (item) => {
  try {
    await showConfirmDialog({ 
      title: '确认删除', 
      message: `确定删除客户【${item.name}】吗？` 
    })
    
    
    // 调用删除方法
    await customerStore.deleteRow(item.id)
    
    showSuccessToast('已删除')
    loadList(true)
    
  } catch (error) {
    // 用户取消操作
    if (error === 'cancel') {
      return
    }
    
    // 显示更详细的错误信息
    if (error.message) {
      showToast(`删除失败: ${error.message}`)
    } else {
      showToast('删除失败，请检查控制台查看详细信息')
    }
  }
}

// 临时添加 onMounted 进行调试
onMounted(() => {
  setTimeout(() => {
    loadList(true)
  }, 1000)
})
</script>

<style scoped lang="scss">
.customer-page {
  background: #f7f8fa;
  min-height: 100vh;
  
  :deep(.van-swipe-cell) {
    margin-bottom: 8px;
    
    .van-cell {
      border-radius: 8px;
      margin: 0 12px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
    
    .van-button {
      height: 100%;
      border-radius: 0;
    }
  }
}

.debug-info {
  background: #fff3cd;
  border: 1px solid #ffeaa7;
  border-radius: 4px;
  padding: 8px;
  margin: 8px;
  font-size: 12px;
  color: #856404;
  
  p {
    margin: 2px 0;
  }
}

// 响应式适配
@media (max-width: 480px) {
  .customer-page {
    :deep(.van-swipe-cell) {
      .van-cell {
        margin: 0 8px;
      }
    }
  }
}
</style>