<template>
  <div class="purchase-order-index">
    <!-- 导航栏 -->
    <van-nav-bar title="采购订单管理" fixed placeholder>
      <template #right>
        <van-button size="small" type="primary" @click="handleCreate">
          新增订单
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 筛选区域 -->
    <div class="filter-wrapper">
      <!-- 状态标签筛选 -->
      <van-tabs v-model="activeStatus" @change="handleStatusChange">
        <van-tab title="全部" name="" />
        <van-tab title="待审核" name="0" />
        <van-tab title="已审核" name="1" />
        <van-tab title="已完成" name="3" />
        <van-tab title="已取消" name="4" />
      </van-tabs>

      <!-- 搜索与高级筛选 -->
      <div class="search-filter">
        <van-search v-model="keyword" placeholder="搜索订单号/供应商名称" @search="handleSearch" @clear="handleClearSearch" />
        
        <!-- 使用 SupplierSelect 组件替换原有的下拉菜单 -->
        <div class="filter-row">
          <!-- 供应商筛选 -->
          <div class="supplier-filter">
            <SupplierSelect
              ref="supplierSelectRef"
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
            <van-dropdown-menu style="width: 100%; height: 100%;">
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

    <!-- 订单列表 -->
    <van-pull-refresh v-model="refreshing" @refresh="handleRefresh">
      <van-list v-model:loading="loading" :finished="finished" finished-text="没有更多订单了" @load="handleLoadMore" :immediate-check="false">
        <!-- 订单项 -->
        <van-cell-group class="order-list">
          <van-cell v-for="order in orderList" :key="order.id" :title="`订单号：${order.order_no || '未生成'}`" :label="getOrderLabel(order)" @click="handleDetail(order.id)" is-link>
            <template #extra>
              <div class="order-extra">
                <van-tag :type="getStatusTagType(order.status)">
                  {{ getStatusText(order.status) }}
                </van-tag>
                <div class="amount">¥{{ (order.total_amount || 0).toFixed(2) }}</div>
              </div>
            </template>
          </van-cell>
        </van-cell-group>

        <!-- 空状态 -->
        <van-empty v-if="!loading && !refreshing && orderList.length === 0" image="search" description="暂无采购订单数据" />
      </van-list>
    </van-pull-refresh>

    <!-- 初始加载状态 -->
    <van-loading v-if="initialLoading" class="initial-loading" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import { usePurchaseStore } from '@/store/modules/purchase'
import { useSupplierStore } from '@/store/modules/supplier'
import SupplierSelect from '@/components/business/SupplierSelect.vue'

const router = useRouter()
const purchaseStore = usePurchaseStore()
const supplierStore = useSupplierStore()

// 响应式数据
const initialLoading = ref(true)
const refreshing = ref(false)
const loading = ref(false)
const finished = ref(false)

// 筛选参数
const activeStatus = ref([])
const keyword = ref('')
const selectedSupplier = ref('')
const dateRange = ref('')
const isLoading = ref(false)

// 分页参数
const pagination = reactive({
  page: 1,
  pageSize: 10
})

// 日期选项
const dateOptions = ref([
  { text: '全部时间', value: '' },
  { text: '今日', value: 'today' },
  { text: '本周', value: 'week' },
  { text: '本月', value: 'month' },
  { text: '近3个月', value: 'quarter' }
])

// 获取供应商占位符文本
const getSupplierPlaceholder = () => {
  if (selectedSupplier.value === 0) return '全部供应商'
  if (selectedSupplier.value) {
    const supplier = supplierStore.list.find(item => item.id == selectedSupplier.value)
    return supplier ? supplier.name : '选择供应商'
  }
  return '选择供应商'
}

// 获取日期标题
const getDateTitle = () => {
  const option = dateOptions.value.find(opt => opt.value === dateRange.value)
  return option ? option.text : '选择时间'
}

const orderList = computed(() => purchaseStore.orderList)

/**
 * 供应商选择变更事件
 */
const handleSupplierChange = (value, name) => {
  console.log('供应商变更:', value, name)
  selectedSupplier.value = value
  handleFilterChange()
}

/**
 * 加载采购订单列表
 */
const loadOrderList = async (isRefresh = false) => {
  if (isLoading.value) return
  if (loading.value && !isRefresh) return
  if (refreshing.value && isRefresh) return

  isLoading.value = true

  try {
    if (isRefresh) {
      pagination.page = 1
      finished.value = false
      refreshing.value = true
    } else {
      if (finished.value) return
      loading.value = true
    }

    // 处理状态参数格式
    const statusParam = activeStatus.value.length > 0 ? activeStatus.value : ''

    const params = {
      page: pagination.page,
      pageSize: pagination.pageSize,
      status: statusParam,
      keyword: keyword.value,
      supplierId: selectedSupplier.value,
      dateRange: dateRange.value
    }
    
    // 移除空值参数
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] == null || (Array.isArray(params[key]) && params[key].length === 0)) {
        delete params[key]
      }
    })

    const res = await purchaseStore.loadOrderList(params)
    
    const currentList = purchaseStore.orderList || []
    const currentDataLength = res?.data?.list?.length || currentList.length
    
    if (currentDataLength < pagination.pageSize) {
      finished.value = true
    } else {
      if (!isRefresh) {
        pagination.page++
      }
    }
  } catch (error) {
    showToast('加载采购订单失败')
    console.error('loadOrderList error:', error)
    finished.value = false
  } finally {
    initialLoading.value = false
    refreshing.value = false
    loading.value = false
    isLoading.value = false
  }
}

/**
 * 状态标签变更事件
 */
const handleStatusChange = (name) => {
  // 如果是全部，清空状态数组
  if (name === '') {
    activeStatus.value = []
  } else {
    // 单选模式下，只保留当前选中的状态
    activeStatus.value = [name]
  }
  
  loadOrderList(true)
}

// 搜索相关方法
const handleSearch = () => {
  loadOrderList(true)
}

const handleClearSearch = () => {
  keyword.value = ''
  loadOrderList(true)
}

const handleFilterChange = () => {
  loadOrderList(true)
}

const handleRefresh = () => {
  loadOrderList(true)
}

const handleLoadMore = () => {
  loadOrderList(false)
}

// 路由跳转方法
const handleCreate = () => {
  router.push('/purchase/order/create')
}

const handleDetail = (id) => {
  router.push(`/purchase/order/detail/${id}`)
}

// 状态显示方法
const getStatusText = (status) => {
  const statusMap = {
    0: '待审核',
    1: '已审核', 
    2: '部分入库',
    3: '已完成',
    4: '已取消'
  }
  return statusMap[status] || '未知状态'
}

const getStatusTagType = (status) => {
  const typeMap = {
    0: 'warning',
    1: 'primary',
    2: 'info',
    3: 'success',
    4: 'danger'
  }
  return typeMap[status] || 'default'
}

const getOrderLabel = (order) => {
  const labels = []
  if (order.supplier && order.supplier.name) labels.push(`供应商：${order.supplier.name}`)
  if (order.created_at) labels.push(`创建时间：${formatTime(order.created_at)}`)
  if (order.warehouse && order.warehouse.name) labels.push(`目标仓库：${order.warehouse.name}`)
  if (order.creator && order.creator.real_name) labels.push(`创建人：${order.creator.real_name}`)
  return labels.join(' | ')
}

const formatTime = (time) => {
  if (!time) return ''
  return new Date(time).toLocaleDateString()
}

onMounted(async () => {
  // 预加载供应商数据
  await supplierStore.loadList()
  initialLoading.value = false
})
</script>

<style scoped lang="scss">
.purchase-order-index {
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
              // 保持默认样式，不变成蓝色
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
          :deep(.van-dropdown-menu) {
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
                  line-height: 42px;
                  
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

  // 订单列表样式
  .order-list {
    .van-cell {
      padding: 15px 10px;
      margin-bottom: 10px;
      background-color: #fff;
      border-radius: 8px;

      .order-extra {
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