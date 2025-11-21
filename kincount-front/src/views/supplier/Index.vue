<template>
  <div class="supplier-page">
    <van-nav-bar title="供应商管理" fixed placeholder>
      <template #right>
        <van-button 
          size="small" 
          type="primary" 
          @click="handleAdd" 
          v-perm="PERM.SUPPLIER_ADD"
        >
          新增
        </van-button>
      </template>
    </van-nav-bar>

    <SearchBar 
      placeholder="搜索供应商名称/联系人/电话" 
      @search="handleSearch" 
      @clear="handleClearSearch" 
    />

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
            @click="handleEdit(item)"
          >
            <template #value>
              <van-switch
                :model-value="item.status === 1"
                size="20"
                @click.stop
                @update:model-value="toggleStatus(item)"
                v-perm="PERM.SUPPLIER_EDIT"
              />
            </template>
          </van-cell>

          <template #right>
            <van-button 
              square 
              type="primary" 
              text="编辑" 
              @click="handleEdit(item)" 
              v-perm="PERM.SUPPLIER_EDIT" 
            />
            <van-button 
              square 
              type="danger" 
              text="删除" 
              @click="handleDelete(item)" 
              v-perm="PERM.SUPPLIER_DELETE" 
            />
          </template>
        </van-swipe-cell>

        <van-empty 
          v-if="!listLoading && !refreshing && list.length === 0" 
          description="暂无供应商数据" 
        />
      </van-list>
    </van-pull-refresh>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { showToast, showConfirmDialog, showSuccessToast } from 'vant'
import { useSupplierStore } from '@/store/modules/supplier'
import { PERM } from '@/constants/permissions'
import SearchBar from '@/components/common/SearchBar.vue'

const router = useRouter()
const supplierStore = useSupplierStore()

const list = ref([])
const refreshing = ref(false)
const listLoading = ref(false)
const finished = ref(false)
const pagination = reactive({ page: 1, pageSize: 15, total: 0 })
const filters = reactive({ keyword: '' })

// 防止重复加载的锁
let isLoading = false

const loadList = async (isRefresh = false) => {
  // 如果已经在加载中，直接返回
  if (isLoading) return
  
  isLoading = true
  
  if (isRefresh) {
    pagination.page = 1
    finished.value = false
    refreshing.value = true
  } else {
    // 如果已经加载完所有数据，则不再加载
    if (finished.value) {
      isLoading = false
      return
    }
    listLoading.value = true
  }

  try {
    const params = { 
      page: pagination.page, 
      limit: pagination.pageSize, 
      keyword: filters.keyword 
    }
    
    // 调用 store 方法获取数据
    const res = await supplierStore.loadList(params)
    
    // 根据 store 中的处理，直接使用 list 和 total
    const data = supplierStore.list || []
    const total = supplierStore.total || 0

    if (isRefresh) {
      list.value = data
    } else {
      // 避免重复添加相同的数据
      const newItems = data.filter(newItem => 
        !list.value.some(existingItem => existingItem.id === newItem.id)
      )
      list.value.push(...newItems)
    }

    pagination.total = total
    
    // 判断是否已加载完所有数据
    finished.value = list.value.length >= total
    
    // 如果当前页数据已加载完且还有更多数据，则页码+1
    if (data.length > 0 && !finished.value) {
      pagination.page++
    }
  } catch (error) {
    console.error('加载供应商列表失败:', error)
    showToast('加载失败')
    finished.value = true
  } finally {
    refreshing.value = false
    listLoading.value = false
    isLoading = false
  }
}

// 下拉刷新
const onRefresh = () => {
  loadList(true)
}

// 滚动加载
const onLoad = () => {
  loadList(false)
}

const handleSearch = (keyword) => {
  filters.keyword = keyword
  loadList(true)
}

const handleClearSearch = () => {
  filters.keyword = ''
  loadList(true)
}

const handleAdd = () => router.push('/supplier/create')
const handleEdit = (item) => router.push(`/supplier/edit/${item.id}`)

const toggleStatus = async (item) => {
  try {
    // 使用通用更新方法更新状态
    await supplierStore.updateSupplier(item.id, { 
      status: item.status === 1 ? 0 : 1 
    })
    showSuccessToast('操作成功')
    loadList(true)
  } catch (error) {
    console.error('切换状态失败:', error)
    showToast('操作失败')
  }
}

const handleDelete = async (item) => {
  try {
    await showConfirmDialog({ 
      title: '确认删除', 
      message: `确定删除供应商【${item.name}】吗？` 
    })
    await supplierStore.deleteSupplier(item.id)
    showSuccessToast('已删除')
    
    // 删除后刷新列表，但重置页码为1
    pagination.page = 1
    loadList(true)
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除供应商失败:', error)
      showToast('删除失败')
    }
  }
}

// 注意：移除了 onMounted 中的 loadList 调用，避免重复请求
// van-list 的 @load 事件会在初始化时自动触发一次
</script>

<style scoped lang="scss">
.supplier-page {
  background: #f7f8fa;
  min-height: 100vh;
}

// 优化滑动单元格按钮样式
:deep(.van-swipe-cell__right) {
  display: flex;
  
  .van-button {
    height: 100%;
    border-radius: 0;
  }
}
</style>