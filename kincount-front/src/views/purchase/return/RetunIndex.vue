<template>
  <div class="purchase-return-index">
    <!-- 导航栏 -->
    <van-nav-bar title="采购退货" fixed placeholder>
      <template #right>
        <van-button size="small" type="primary" @click="handleCreate">
          新建退货单
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 筛选区域 -->
    <div class="filter-wrapper">
      <!-- 状态标签筛选 -->
      <van-tabs v-model:active="activeTab" @change="onTabChange" :animated="false">
        <van-tab title="全部" :name="99"></van-tab>
        <van-tab title="待审核" :name="0"></van-tab>
        <van-tab title="已审核" :name="1"></van-tab>
        <van-tab title="已入库" :name="3"></van-tab>
        <van-tab title="已完成" :name="5"></van-tab>
        <van-tab title="已取消" :name="6"></van-tab>
      </van-tabs>

      <!-- 搜索与高级筛选 -->
      <div class="search-filter">
        <van-search v-model="searchKeyword" placeholder="搜索退货单号/供应商名称" @search="onSearch" @clear="handleClearSearch" />
        
        <!-- 筛选行：供应商、时间在同一行 -->
        <div class="filter-row">
          <!-- 供应商筛选 -->
          <div class="supplier-filter">
            <SupplierSelect
              v-model="selectedSupplier"
              :placeholder="getSupplierPlaceholder()"
              :show-all-option="true"
              :show-confirm-button="false"
              :trigger-button-type="'default'"
              :trigger-button-size="'normal'"
              :trigger-button-block="true"
              @change="handleSupplierChange"
            />
          </div>
          
          <!-- 时间筛选 -->
          <div class="date-filter">
            <van-dropdown-menu class="date-dropdown-menu">
              <van-dropdown-item 
                v-model="dateRange" 
                :options="dateOptions" 
                :title="getDateTitle()"
                @change="handleFilterChange" 
              />
            </van-dropdown-menu>
          </div>
        </div>
      </div>
    </div>

    <!-- 退货单列表 -->
    <van-pull-refresh v-model="refreshing" @refresh="handleRefresh">
      <van-list v-model:loading="loading" :finished="finished" finished-text="没有更多退货单了" @load="handleLoadMore" :immediate-check="false">
        <!-- 退货单项 -->
        <van-cell-group class="return-list">
          <van-cell v-for="item in list" :key="item.id" :title="`退货单号：${item.return_no || '未生成'}`" :label="getReturnLabel(item)" @click="handleDetail(item.id)" is-link>
            <template #extra>
              <div class="return-extra">
                <van-tag :type="getStatusType(item.status)" size="small">
                  {{ getStatusText(item.status) }}
                </van-tag>
                <div class="amount">¥{{ formatAmount(item.total_amount) }}</div>
              </div>
            </template>
          </van-cell>
        </van-cell-group>

        <!-- 空状态 -->
        <van-empty v-if="!loading && !refreshing && list.length === 0" image="search" description="暂无采购退货单数据" />
      </van-list>
    </van-pull-refresh>

    <!-- 初始加载状态 -->
    <van-loading v-if="initialLoading" class="initial-loading" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { showToast, showConfirmDialog } from 'vant'
import { usePurchaseStore } from '@/store/modules/purchase'
import SupplierSelect from '@/components/business/SupplierSelect.vue'

const router = useRouter()
const purchaseStore = usePurchaseStore()

// 响应式数据
const initialLoading = ref(false)
const refreshing = ref(false)
const loading = ref(false)
const finished = ref(false)
const isLoading = ref(false)

// 筛选参数
const activeTab = ref(99) // 99:全部, 0:待审核, 1:已审核, 3:已入库, 5:已完成, 6:已取消
const searchKeyword = ref('')
const selectedSupplier = ref('')
const dateRange = ref('')

// 日期选项
const dateOptions = ref([
  { text: '全部时间', value: '' },
  { text: '今日', value: 'today' },
  { text: '本周', value: 'week' },
  { text: '本月', value: 'month' },
  { text: '近3个月', value: 'quarter' }
])

// 列表相关
const list = ref([])
const page = ref(1)
const pageSize = 10

// 获取供应商占位符文本
const getSupplierPlaceholder = () => {
  if (selectedSupplier.value === 0) return '全部供应商'
  if (selectedSupplier.value) {
    return `供应商${selectedSupplier.value}`
  }
  return '选择供应商'
}

// 获取日期标题
const getDateTitle = () => {
  const option = dateOptions.value.find(opt => opt.value === dateRange.value)
  return option ? option.text : '选择时间'
}

// 获取退货单标签信息
const getReturnLabel = (item) => {
  const labels = []
  if (item.supplier_name) labels.push(`供应商：${item.supplier_name}`)
  if (item.warehouse_name) labels.push(`仓库：${item.warehouse_name}`)
  if (item.created_at) labels.push(`创建时间：${formatDate(item.created_at)}`)
  if (item.creator?.real_name) labels.push(`创建人：${item.creator.real_name}`)
  return labels.join(' | ')
}

// 获取状态文本
const getStatusText = (status) => {
  const statusMap = {
    0: '待审核',
    1: '已审核',
    2: '部分入库',
    3: '已入库',
    4: '已退款',
    5: '已完成',
    6: '已取消'
  }
  return statusMap[status] || `未知(${status})`
}

// 获取状态类型
const getStatusType = (status) => {
  const typeMap = {
    0: 'warning',  // 待审核 - 警告色
    1: 'primary',  // 已审核 - 主要色
    2: 'warning',  // 部分入库 - 警告色
    3: 'success',  // 已入库 - 成功色
    4: 'success',  // 已退款 - 成功色
    5: 'success',  // 已完成 - 成功色
    6: 'danger'    // 已取消 - 危险色
  }
  return typeMap[status] || 'default'
}

// 格式化金额
const formatAmount = (amount) => {
  if (amount === null || amount === undefined || amount === '') return '0.00'
  const num = Number(amount)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

// 格式化日期
const formatDate = (dateString) => {
  if (!dateString) return ''
  return new Date(dateString).toLocaleDateString()
}

// 搜索
const onSearch = () => {
  loadReturnList(true)
}

// 清除搜索
const handleClearSearch = () => {
  searchKeyword.value = ''
  loadReturnList(true)
}

// Tab切换
const onTabChange = () => {
  loadReturnList(true)
}

// 供应商变更
const handleSupplierChange = (value, name) => {
  selectedSupplier.value = value
  loadReturnList(true)
}

// 筛选条件变化
const handleFilterChange = () => {
  loadReturnList(true)
}

// 下拉刷新
const handleRefresh = () => {
  loadReturnList(true)
}

// 加载更多
const handleLoadMore = () => {
  loadReturnList(false)
}

// 加载退货单列表
const loadReturnList = async (isRefresh = false) => {
  if (isLoading.value) return
  if (loading.value && !isRefresh) return
  if (refreshing.value && isRefresh) return

  isLoading.value = true

  try {
    if (isRefresh) {
      page.value = 1
      finished.value = false
      refreshing.value = true
    } else {
      if (finished.value) return
      loading.value = true
    }

    // 处理状态参数格式
    const statusParam = activeTab.value !== 99 ? activeTab.value : ''

    const params = {
      page: page.value,
      pageSize: pageSize,
      keyword: searchKeyword.value,
      status: statusParam,
      supplier_id: selectedSupplier.value,
      date_range: dateRange.value,
      type: 1 // 采购退货类型标识（数据库定义：0-销售退货 1-采购退货）
    }
    
    // 移除空值参数
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] == null || (Array.isArray(params[key]) && params[key].length === 0)) {
        delete params[key]
      }
    })

    const res = await purchaseStore.loadReturnList(params)
    
    // 根据响应结构处理数据
    let data = []
    if (res && res.code === 200) {
      if (Array.isArray(res.data)) {
        data = res.data
      } else if (res.data && Array.isArray(res.data.list)) {
        data = res.data.list
      } else if (res.data && Array.isArray(res.data.data)) {
        data = res.data.data
      }
    }
    
    if (isRefresh) {
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
      if (!isRefresh) {
        page.value++
      }
    }
  } catch (error) {
    showToast('加载采购退货单列表失败')
    console.error('loadReturnList error:', error)
    finished.value = false
  } finally {
    initialLoading.value = false
    refreshing.value = false
    loading.value = false
    isLoading.value = false
  }
}

// 创建新退货单
const handleCreate = () => {
  router.push('/purchase/return/create')
}

// 查看详情
const handleDetail = (id) => {
  router.push(`/purchase/return/detail/${id}`)
}

// 页面加载时初始化数据
onMounted(() => {
  // 确保默认选中"全部"选项卡
  activeTab.value = 99
  loadReturnList(true)
})
</script>

<style scoped lang="scss">
.purchase-return-index {
  min-height: 100vh;
  background-color: #f5f5f5;
  padding-top: 46px; // 适配fixed导航栏

  // 筛选区域样式
  .filter-wrapper {
    background-color: #fff;
    margin-bottom: 10px;

    .search-filter {
      padding: 0 10px 10px;

      .filter-row {
        display: flex;
        align-items: stretch; // 确保子元素高度一致
        gap: 10px;
        margin-top: 10px;
        height: 30px; // 设置固定高度，确保两个按钮高度一致
        
        .supplier-filter,
        .date-filter {
          flex: 1; // 平分宽度
          display: flex;
        }
        
        // 供应商选择器样式
        .supplier-filter {
          :deep(.supplier-select-trigger) {
            width: 100%;
            height: 100%;
          }
          
          :deep(.default-trigger) {
            width: 100%;
            height: 100%;
            
            .van-button {
              width: 100%;
              height: 100%;
              border-radius: 6px;
              background-color: #f7f8fa;
              border: 1px solid #ebedf0;
              color: #323233;
              font-size: 14px;
              
              &:active {
                background-color: #f2f3f5;
              }
            }
          }
        }
        
        // 日期筛选样式
        .date-filter {
          :deep(.date-dropdown-menu) {
            width: 100%;
            height: 100%;
            
            .van-dropdown-menu__bar {
              height: 100%;
              box-shadow: none;
              border-radius: 6px;
              background-color: #f7f8fa;
              border: 1px solid #ebedf0;
              
              .van-dropdown-menu__item {
                flex: 1;
                
                &:first-child {
                  border-radius: 6px;
                }
                
                .van-dropdown-menu__title {
                  font-size: 14px;
                  color: #323233;
                  padding: 0 12px;
                  line-height: 30px;
                  
                  &::after {
                    border-color: #969799;
                  }
                }
              }
            }
          }
        }
      }

      :deep(.van-dropdown-menu) {
        margin-top: 0;
      }
    }
  }

  // 退货单列表样式
  .return-list {
    .van-cell {
      padding: 15px 10px;
      margin-bottom: 10px;
      background-color: #fff;
      border-radius: 8px;

      .return-extra {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 5px;

        .amount {
          font-size: 14px;
          font-weight: 600;
          color: #ee0a24;
        }
      }
    }
  }

  // 初始加载样式
  .initial-loading {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
  }
}
</style>