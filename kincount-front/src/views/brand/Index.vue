<template>
  <div class="brand-page">
    <van-nav-bar title="品牌管理" fixed placeholder>
      <template #right>
        <van-button size="small" type="primary" @click="handleAdd" v-perm="PERM.BRAND_ADD">
          新增
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 搜索 -->
    <SearchBar placeholder="搜索品牌名称" @search="handleSearch" @clear="handleClearSearch" />

    <!-- 列表 -->
    <van-pull-refresh v-model="refreshing" @refresh="loadList(true)">
      <van-list
        v-model:loading="listLoading"
        :finished="finished"
        finished-text="没有更多了"
        @load="loadList"
      >
        <van-swipe-cell v-for="item in brandStore.list" :key="item.id">
          <van-cell
            :title="item.name"
            :label="`英文名: ${item.code || '无'} | 排序: ${item.sort}`"
            @click="handleEdit(item)"
          >
            <template #value>
              <van-switch
                :model-value="item.status === 1"
                size="20"
                @click.stop
                @update:model-value="toggleStatus(item)"
                v-perm="PERM.BRAND_EDIT"
              />
            </template>
          </van-cell>

          <template #right>
            <van-button square type="primary" text="编辑" @click="handleEdit(item)" v-perm="PERM.BRAND_EDIT" />
            <van-button square type="danger" text="删除" @click="handleDelete(item)" v-perm="PERM.BRAND_DELETE" />
          </template>
        </van-swipe-cell>

        <van-empty v-if="!listLoading && !refreshing && brandStore.list.length === 0" description="暂无品牌数据" />
      </van-list>
    </van-pull-refresh>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { showToast, showConfirmDialog, showSuccessToast } from 'vant'
import { useBrandStore } from '@/store/modules/brand'
import { PERM } from '@/constants/permissions'
import SearchBar from '@/components/common/SearchBar.vue'

const router = useRouter()
const brandStore = useBrandStore()

const refreshing = ref(false)
const listLoading = ref(false)
const finished = ref(false)
const pagination = reactive({ page: 1, pageSize: 15, total: 0 })
const filters = reactive({ keyword: '' })

const loadList = async (isRefresh = false) => {
  if (isRefresh) {
    pagination.page = 1
    finished.value = false
    refreshing.value = true
  } else {
    // 如果是滚动加载，检查是否已经加载完毕
    if (finished.value) return
    listLoading.value = true
  }

  try {
    const params = { page: pagination.page, limit: pagination.pageSize, keyword: filters.keyword }
    const res = await brandStore.loadList(params)

    pagination.total = brandStore.total
    finished.value = brandStore.list.length >= pagination.total
    
    // 只有在加载更多时才增加页码
    if (!isRefresh && !finished.value) {
      pagination.page++
    }
  } catch {
    showToast('加载失败')
    finished.value = true
  } finally {
    refreshing.value = false
    listLoading.value = false
  }
}

const handleSearch = (keyword) => {
  filters.keyword = keyword
  loadList(true)
}

const handleClearSearch = () => {
  filters.keyword = ''
  loadList(true)
}

const handleAdd = () => router.push('/brand/create')
const handleEdit = (item) => router.push(`/brand/edit/${item.id}`)

const toggleStatus = async (item) => {
  try {
    await brandStore.toggleStatus(item.id, item.status === 1 ? 0 : 1)
    showSuccessToast('操作成功')
  } catch {
    showToast('操作失败')
  }
}

const handleDelete = async (item) => {
  try {
    // 使用await等待确认对话框
    await showConfirmDialog({ 
      title: '确认删除', 
      message: `确定删除品牌【${item.name}】吗？` 
    })
    
    // 执行删除操作
    await brandStore.deleteRow(item.id)
    showSuccessToast('已删除')
    
    // 更新本地分页信息
    pagination.total -= 1
    
    // 如果删除后列表为空且不是第一页，返回上一页
    if (brandStore.list.length === 0 && pagination.page > 1) {
      pagination.page -= 1
      loadList(true)
    }
  } catch (e) {
    // 只有当错误不是用户取消时才显示错误提示
    if (e && e !== 'cancel') {
      console.error('删除失败:', e)
      showToast('删除失败')
    }
  }
}
</script>

<style scoped lang="scss">
.brand-page {
  background: #f7f8fa;
  min-height: 100vh;
}

// 确保列表项样式正确
:deep(.van-swipe-cell) {
  margin-bottom: 1px;
  
  .van-cell {
    padding: 12px 16px;
  }
  
  .van-swipe-cell__right {
    display: flex;
    
    .van-button {
      height: 100%;
      border-radius: 0;
    }
  }
}
</style>