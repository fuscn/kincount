<template>
  <div class="purchase-return-index">
    <van-nav-bar title="采购退货" fixed placeholder>
      <template #right>
        <van-button size="small" type="primary" @click="handleCreate">
          <van-icon name="plus" />新建
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 搜索和筛选 -->
    <div class="search-filter">
      <van-search v-model="searchKeyword" placeholder="搜索退货单号/供应商" @search="onSearch" />
      <div class="filter-tabs">
        <van-tabs v-model:active="activeTab" @change="onTabChange">
          <van-tab title="全部" :name="-1"></van-tab>
          <van-tab title="待审核" :name="1"></van-tab>
          <van-tab title="已审核" :name="2"></van-tab>
          <van-tab title="已入库" :name="4"></van-tab>
          <van-tab title="已完成" :name="6"></van-tab>
          <van-tab title="已取消" :name="7"></van-tab>
        </van-tabs>
      </div>
    </div>

    <!-- 列表 -->
    <van-list v-model:loading="loading" :finished="finished" :finished-text="list.length ? '没有更多了' : ''"
      @load="onLoad">
      <van-empty v-if="!loading && list.length === 0" description="暂无数据" />

      <div class="return-list" v-else>
        <van-cell-group v-for="item in list" :key="item.id" class="return-item" @click="handleDetail(item.id)">
          <van-cell :title="`退货单号：${item.return_no}`" :label="getReturnLabel(item)" />
          <van-cell title="供应商" :value="item.supplier_name || '无'" />
          <van-cell title="退货金额" :value="`¥${item.total_amount}`" />
          <van-cell title="状态">
            <template #default>
              <van-tag :type="getStatusType(item.status)" size="medium">
                {{ getStatusText(item.status) }}
              </van-tag>
            </template>
          </van-cell>
          <van-cell title="创建时间" :value="formatDate(item.created_at)" />
        </van-cell-group>
      </div>
    </van-list>

    <!-- 操作菜单 -->
    <van-action-sheet v-model:show="showActionSheet" :actions="actions" @select="onActionSelect" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { showToast, showConfirmDialog } from 'vant'
import { usePurchaseStore } from '@/store/modules/purchase'

const router = useRouter()
const purchaseStore = usePurchaseStore()

// 搜索相关
const searchKeyword = ref('')
const activeTab = ref(-1) // -1:全部, 1:待审核, 2:已审核, 4:已入库, 6:已完成, 7:已取消

// 列表相关
const list = ref([])
const loading = ref(false)
const finished = ref(false)
const page = ref(1)
const pageSize = 20

// 操作菜单相关
const showActionSheet = ref(false)
const currentReturnId = ref(null)
const actions = ref([
  { name: '查看详情', value: 'detail' },
  { name: '编辑', value: 'edit' },
  { name: '审核', value: 'audit' },
  { name: '取消', value: 'cancel' },
  { name: '删除', value: 'delete' }
])

// 获取退货单标签
const getReturnLabel = (item) => {
  const supplier = item.supplier_name || item.supplier?.name || '无供应商'
  const warehouse = item.warehouse_name || '无仓库'
  const date = item.return_date || item.created_at?.split(' ')[0] || ''
  return `${supplier} | ${warehouse} | ${date}`
}

// 获取状态文本
const getStatusText = (status) => {
  const statusMap = {
    1: '待审核',
    2: '已审核',
    3: '部分入库',
    4: '已入库',
    5: '已退款',
    6: '已完成',
    7: '已取消'
  }
  return statusMap[status] || `未知(${status})`
}

// 获取状态类型
const getStatusType = (status) => {
  const typeMap = {
    1: 'warning',  // 待审核
    2: 'primary',  // 已审核
    3: 'warning',  // 部分入库
    4: 'success',  // 已入库
    5: 'success',  // 已退款
    6: 'success',  // 已完成
    7: 'danger'    // 已取消
  }
  return typeMap[status] || 'default'
}

// 格式化日期
const formatDate = (dateString) => {
  if (!dateString) return ''
  return dateString.split(' ')[0]
}

// 搜索
const onSearch = () => {
  resetList()
  loadData()
}

// Tab切换
const onTabChange = () => {
  resetList()
  loadData()
}

// 重置列表
const resetList = () => {
  list.value = []
  page.value = 1
  finished.value = false
  loading.value = false
}

// 加载数据
const loadData = async () => {
  if (loading.value || finished.value) return

  loading.value = true

  try {
    const params = {
      page: page.value,
      limit: pageSize,
      keyword: searchKeyword.value.trim(),
      type: 2 // 采购退货类型标识
    }

    // 根据tab筛选状态
    if (activeTab.value !== -1) {
      params.status = activeTab.value
    }

    const res = await purchaseStore.loadReturnList(params)

    if (res && res.code === 200) {
      // 处理响应数据
      let data = []
      if (Array.isArray(res.data)) {
        data = res.data
      } else if (res.data && Array.isArray(res.data.list)) {
        data = res.data.list
      } else if (res.data && Array.isArray(res.data.data)) {
        data = res.data.data
      }

      if (page.value === 1) {
        list.value = data
      } else {
        // 去重合并
        const existingIds = new Set(list.value.map(item => item.id))
        const newItems = data.filter(item => !existingIds.has(item.id))
        list.value = [...list.value, ...newItems]
      }

      // 判断是否加载完成
      if (data.length < pageSize) {
        finished.value = true
      } else {
        page.value++
      }
    } else {
      finished.value = true
    }
  } catch (error) {
    console.error('加载退货单列表失败:', error)
    showToast('加载失败')
    finished.value = true
  } finally {
    loading.value = false
  }
}

// 列表加载更多
const onLoad = () => {
  loadData()
}

// 创建新退货单
const handleCreate = () => {
  router.push('/purchase/return/create')
}

// 查看详情
const handleDetail = (id) => {
  router.push(`/purchase/return/detail/${id}`)
}

// 长按显示操作菜单
const handleLongPress = (id) => {
  currentReturnId.value = id
  showActionSheet.value = true
}

// 操作选择
const onActionSelect = async (action) => {
  showActionSheet.value = false
  const id = currentReturnId.value

  if (!id) return

  try {
    switch (action.value) {
      case 'detail':
        handleDetail(id)
        break
      case 'edit':
        router.push(`/purchase/return/edit/${id}`)
        break
      case 'audit':
        await handleAudit(id)
        break
      case 'cancel':
        await handleCancel(id)
        break
      case 'delete':
        await handleDelete(id)
        break
    }
  } catch (error) {
    console.error('操作失败:', error)
    showToast('操作失败')
  }
}

// 审核退货单
const handleAudit = async (id) => {
  showConfirmDialog({
    title: '提示',
    message: '确定要审核该退货单吗？'
  }).then(async () => {
    const result = await purchaseStore.auditReturn(id)
    if (result) {
      showToast('审核成功')
      // 刷新列表
      resetList()
      loadData()
    }
  }).catch(() => {
    // 用户取消
  })
}

// 取消退货单
const handleCancel = async (id) => {
  showConfirmDialog({
    title: '提示',
    message: '确定要取消该退货单吗？'
  }).then(async () => {
    const result = await purchaseStore.cancelReturn(id)
    if (result) {
      showToast('取消成功')
      // 刷新列表
      resetList()
      loadData()
    }
  }).catch(() => {
    // 用户取消
  })
}

// 删除退货单
const handleDelete = async (id) => {
  showConfirmDialog({
    title: '警告',
    message: '确定要删除该退货单吗？删除后不可恢复！'
  }).then(async () => {
    const result = await purchaseStore.deleteReturn(id)
    if (result) {
      showToast('删除成功')
      // 刷新列表
      resetList()
      loadData()
    }
  }).catch(() => {
    // 用户取消
  })
}

onMounted(() => {
  // 首次加载
  loadData()
})
</script>

<style scoped lang="scss">
.purchase-return-index {
  background-color: #f7f8fa;
  min-height: 100vh;
  padding-top: 46px;
}

.search-filter {
  background: white;
  position: sticky;
  top: 46px;
  z-index: 10;
}

.return-list {
  padding: 8px;

  .return-item {
    margin-bottom: 12px;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    transition: transform 0.2s;

    &:active {
      transform: scale(0.98);
    }

    :deep(.van-cell) {
      padding: 10px 16px;

      &:not(:last-child)::after {
        border-bottom: 1px solid #f5f5f5;
      }

      .van-cell__title {
        flex: none;
        width: 80px;
        color: #969799;
        font-size: 14px;
      }

      .van-cell__value {
        text-align: left;
        color: #323233;
        font-size: 14px;
      }

      .van-cell__label {
        font-size: 13px;
        color: #646566;
        margin-top: 2px;
      }
    }
  }
}
</style>