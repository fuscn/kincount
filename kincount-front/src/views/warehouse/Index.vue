<template>
  <div class="warehouse-page">
    <van-nav-bar title="仓库管理" fixed placeholder>
      <template #right>
        <van-button size="small" type="primary" @click="handleAdd" v-perm="PERM.WAREHOUSE_ADD">
          新增
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 搜索 -->
    <SearchBar placeholder="搜索仓库名称" @search="handleSearch" @clear="handleClearSearch" />

    <!-- 列表 -->
    <van-pull-refresh v-model="refreshing" @refresh="loadList(true)">
      <van-list
        v-model:loading="listLoading"
        :finished="finished"
        finished-text="没有更多了"
        :immediate-check="false"
        @load="loadList"
      >
        <van-swipe-cell v-for="item in warehouseStore.list" :key="item.id">
          <van-cell
            :title="item.name"
            :label="`编码: ${item.code} | 地址: ${item.address}`"
           
          >
            <template #value>
              <div class="contact-info">
                <div>负责人: {{ item.manager }}</div>
                <div>电话: {{ item.phone }}</div>
              </div>
            </template>
            <template #right-icon>
              <van-switch
                :model-value="item.status === 1"
                size="20"
                @click.stop
                @update:model-value="toggleStatus(item)"
                v-perm="PERM.WAREHOUSE_EDIT"
              />
            </template>
          </van-cell>

          <template #right>
            <van-button square type="primary" text="编辑" @click="handleEdit(item)" v-perm="PERM.WAREHOUSE_EDIT" />
            <van-button square type="danger" text="删除" @click="() => handleDelete(item)" v-perm="PERM.WAREHOUSE_DELETE" />
          </template>
        </van-swipe-cell>

        <van-empty v-if="!listLoading && !refreshing && warehouseStore.list.length === 0" description="暂无仓库数据" />
      </van-list>
    </van-pull-refresh>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { showToast, showConfirmDialog, showSuccessToast } from 'vant'
import { useWarehouseStore } from '@/store/modules/warehouse'
import { PERM } from '@/constants/permissions'
import SearchBar from '@/components/common/SearchBar.vue'

const router = useRouter()
const warehouseStore = useWarehouseStore()

const refreshing = ref(false)
const listLoading = ref(false)
const finished = ref(false)
const pagination = reactive({ page: 1, pageSize: 15 })
const filters = reactive({ keyword: '' })

// 防止重复请求
let hasMounted = false

const loadList = async (isRefresh = false) => {
  if (listLoading.value) return
  
  if (isRefresh) {
    pagination.page = 1
    finished.value = false
    refreshing.value = true
  } else {
    if (finished.value) return
    listLoading.value = true
  }

  try {
    const params = { 
      page: pagination.page, 
      limit: pagination.pageSize, 
      keyword: filters.keyword 
    }
    
    await warehouseStore.loadList(params)
    
    // 判断是否加载完成
    finished.value = warehouseStore.list.length >= warehouseStore.total
    
    if (!finished.value && !isRefresh) {
      pagination.page++
    }
    
  } catch (error) {
    console.error('加载仓库列表失败:', error)
    showToast('加载失败: ' + (error.message || '未知错误'))
    finished.value = true
  } finally {
    refreshing.value = false
    listLoading.value = false
  }
}

const handleSearch = (keyword) => {
  filters.keyword = keyword
  pagination.page = 1
  loadList(true)
}

const handleClearSearch = () => {
  filters.keyword = ''
  pagination.page = 1
  loadList(true)
}

const handleAdd = () => router.push('/warehouse/create')
const handleEdit = (item) => router.push(`/warehouse/edit/${item.id}`)
const handleView = (item) => router.push(`/warehouse/detail/${item.id}`)

const toggleStatus = async (item) => {
  try {
    const newStatus = item.status === 1 ? 0 : 1
    // 这里需要添加切换状态的 API 调用
    // await warehouseStore.toggleStatus(item.id, newStatus)
    showSuccessToast('操作成功')
    
    // 更新本地状态
    const targetItem = warehouseStore.list.find(i => i.id === item.id)
    if (targetItem) {
      targetItem.status = newStatus
    }
  } catch (error) {
    console.error('切换状态失败:', error)
    showToast('操作失败')
  }
}

// 修复删除方法 - 确保它是普通的函数
const handleDelete = async (item) => {
  try {
    await showConfirmDialog({ 
      title: '确认删除', 
      message: `确定删除仓库【${item.name}】吗？删除后无法恢复！` 
    })
    
    await warehouseStore.deleteWarehouse(item.id)
    showSuccessToast('删除成功')
    
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      
      // 根据不同的错误类型显示不同的提示
      if (error.message && error.message.includes('存在库存记录')) {
        showToast('删除失败：请先清空该仓库的所有库存记录后再删除')
      } else {
        showToast('删除失败: ' + (error.message || '未知错误'))
      }
    }
  }
}

onMounted(() => {
  if (!hasMounted) {
    loadList(true)
    hasMounted = true
  }
})
</script>

<style scoped lang="scss">
.warehouse-page {
  background: #f7f8fa;
  min-height: 100vh;
}

.van-swipe-cell {
  margin-bottom: 8px;
  
  &__wrapper {
    border-radius: 8px;
    overflow: hidden;
    margin: 0 12px;
  }
}

.van-cell {
  align-items: flex-start;
  
  :deep(.van-cell__title) {
    font-weight: bold;
    margin-bottom: 4px;
    flex: 2;
  }
  
  :deep(.van-cell__label) {
    color: #666;
    font-size: 12px;
    margin-top: 2px;
  }
  
  :deep(.van-cell__value) {
    text-align: left;
    flex: 3;
  }
}

.contact-info {
  font-size: 12px;
  color: #999;
  
  div {
    margin-bottom: 2px;
  }
}

.van-button {
  height: 100%;
  border-radius: 0;
}
</style>