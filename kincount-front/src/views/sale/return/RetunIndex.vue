<template>
  <div class="sale-return-page">
    <!-- 导航栏 -->
    <van-nav-bar title="销售退货" fixed placeholder>
      <template #right>
        <van-button 
          size="small" 
          type="primary" 
          @click="handleCreateReturn"
        >
          新建退货
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
        <van-tab title="已完成" name="5" />
        <van-tab title="已取消" name="6" />
      </van-tabs>

      <!-- 搜索与高级筛选 -->
      <div class="search-filter">
        <van-search 
          v-model="keyword" 
          placeholder="搜索退货单号/订单号" 
          @search="handleSearch" 
          @clear="handleClearSearch" 
        />
        
        <!-- 筛选行：客户、退货类型、时间在同一行 -->
        <div class="filter-row">
          <!-- 客户筛选 -->
          <div class="customer-filter">
            <CustomerSelect
              ref="customerSelectRef"
              v-model="selectedCustomer"
              :placeholder="getCustomerPlaceholder()"
              :show-all-option="true"
              :show-confirm-button="false"
              :trigger-button-type="'default'"
              :trigger-button-size="'normal'"
              :trigger-button-block="true"
              @change="handleCustomerChange"
            />
          </div>
          
          <!-- 退货类型筛选 -->
          <div class="type-filter">
            <van-dropdown-menu style="width: 100%; height: 100%;">
              <van-dropdown-item 
                v-model="returnType" 
                :options="returnTypeOptions" 
                :title="getTypeTitle()"
                @change="handleFilterChange" 
              />
            </van-dropdown-menu>
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

    <!-- 退货列表 -->
    <van-pull-refresh v-model="refreshing" @refresh="handleRefresh">
      <van-list 
        v-model:loading="loading" 
        :finished="finished" 
        finished-text="没有更多退货单了" 
        @load="handleLoadMore" 
        :immediate-check="false"
      >
        <!-- 退货项 -->
        <van-cell-group class="return-list">
          <van-cell 
            v-for="returnOrder in returnList" 
            :key="returnOrder.id" 
            :title="`退货单号：${returnOrder.return_no || '未生成'}`" 
            :label="getReturnLabel(returnOrder)" 
            @click="handleViewReturn(returnOrder.id)" 
            is-link
          >
            <template #extra>
              <div class="return-extra">
                <van-tag :type="getStatusTagType(returnOrder.status)">
                  {{ getStatusText(returnOrder.status) }}
                </van-tag>
                <div class="amount">-¥{{ formatPrice(returnOrder.total_amount) }}</div>
              </div>
            </template>
          </van-cell>
        </van-cell-group>

        <!-- 空状态 -->
        <van-empty 
          v-if="!loading && !refreshing && returnList.length === 0" 
          image="search" 
          :description="getEmptyDescription()" 
        />
      </van-list>
    </van-pull-refresh>

    <!-- 初始加载状态 -->
    <van-loading v-if="initialLoading" class="initial-loading" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { showToast, showFailToast } from 'vant'
import { useSaleStore } from '@/store/modules/sale'
import CustomerSelect from '@/components/business/CustomerSelect.vue'

const router = useRouter()
const saleStore = useSaleStore()

// 响应式数据
const initialLoading = ref(true)
const refreshing = ref(false)
const loading = ref(false)
const finished = ref(false)

// 筛选参数
const activeStatus = ref('')
const keyword = ref('')
const selectedCustomer = ref('')
const returnType = ref('')
const dateRange = ref('')
const isLoading = ref(false)

// 分页参数
const pagination = reactive({
  page: 1,
  pageSize: 10
})

// 退货类型选项
const returnTypeOptions = ref([
  { text: '全部类型', value: '' },
  { text: '质量问题', value: '1' },
  { text: '客户原因', value: '2' },
  { text: '发错货', value: '3' },
  { text: '其他', value: '4' }
])

// 日期选项
const dateOptions = ref([
  { text: '全部时间', value: '' },
  { text: '今日', value: 'today' },
  { text: '本周', value: 'week' },
  { text: '本月', value: 'month' },
  { text: '近3个月', value: 'quarter' }
])

// 计算属性
const returnList = computed(() => saleStore.returnList || [])

// 获取客户占位符文本
const getCustomerPlaceholder = () => {
  if (selectedCustomer.value === 0) return '全部客户'
  if (selectedCustomer.value) {
    return selectedCustomer.value
  }
  return '选择客户'
}

// 获取退货类型标题
const getTypeTitle = () => {
  const option = returnTypeOptions.value.find(opt => opt.value === returnType.value)
  return option ? option.text : '选择类型'
}

// 获取日期标题
const getDateTitle = () => {
  const option = dateOptions.value.find(opt => opt.value === dateRange.value)
  return option ? option.text : '选择时间'
}

// 获取空状态描述
const getEmptyDescription = () => {
  if (keyword.value) return `未找到"${keyword.value}"相关退货单`
  if (selectedCustomer.value && selectedCustomer.value !== 0) return '该客户暂无退货单'
  if (returnType.value) return '该类型暂无退货单'
  if (dateRange.value) return '该时间段内暂无退货单'
  return '暂无销售退货数据'
}

// 格式化金额
const formatPrice = (price) => {
  if (price === null || price === undefined || price === '') return '0.00'
  const num = Number(price)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

// 格式化时间
const formatTime = (time) => {
  if (!time) return ''
  try {
    const d = new Date(time)
    if (isNaN(d.getTime())) return ''
    return d.toLocaleDateString()
  } catch (error) {
    return ''
  }
}

// 获取状态文本
const getStatusText = (status) => {
  const statusMap = {
    1: '待审核',
    2: '已审核',
    3: '已完成',
    4: '已取消'
  }
  return statusMap[status] || '未知状态'
}

// 获取状态标签类型
const getStatusTagType = (status) => {
  const typeMap = {
    1: 'warning',
    2: 'primary',
    3: 'success',
    4: 'danger'
  }
  return typeMap[status] || 'default'
}

// 获取退货类型文本
const getReturnTypeText = (type) => {
  const typeMap = {
    1: '质量问题',
    2: '客户原因',
    3: '发错货',
    4: '其他'
  }
  return typeMap[type] || '未知类型'
}

// 获取退货单标签信息
const getReturnLabel = (returnOrder) => {
  const labels = []
  if (returnOrder.target?.name) labels.push(`客户：${returnOrder.target.name}`)
  if (returnOrder.source_order_id) labels.push(`关联订单：${returnOrder.source_order_id}`)
  if (returnOrder.return_type) labels.push(`退货类型：${getReturnTypeText(returnOrder.return_type)}`)
  if (returnOrder.created_at) labels.push(`退货时间：${formatTime(returnOrder.created_at)}`)
  if (returnOrder.return_reason) labels.push(`退货原因：${returnOrder.return_reason}`)
  return labels.join(' | ')
}

// 客户选择变更事件
const handleCustomerChange = (value, name) => {
  console.log('客户变更:', value, name)
  selectedCustomer.value = value
  handleFilterChange()
}

// 状态标签变更事件
const handleStatusChange = (name) => {
  activeStatus.value = name
  loadReturnList(true)
}

// 搜索相关方法
const handleSearch = () => {
  loadReturnList(true)
}

const handleClearSearch = () => {
  keyword.value = ''
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

// 加载退货列表
const loadReturnList = async (isRefresh = false) => {
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

    // 处理时间范围参数
    let dateRangeParam = ''
    if (dateRange.value) {
      dateRangeParam = dateRange.value
    }

    const params = {
      page: pagination.page,
      pageSize: pagination.pageSize,
      keyword: keyword.value,
      status: activeStatus.value,
      return_type: returnType.value,
      date_range: dateRangeParam
    }
    
    // 只有当customer_id存在且不为0时才加入参数
    if (selectedCustomer.value && selectedCustomer.value !== 0) {
      params.customer_id = selectedCustomer.value
    }
    
    // 移除空值参数
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] == null || (Array.isArray(params[key]) && params[key].length === 0)) {
        delete params[key]
      }
    })

    await saleStore.loadReturnList(params)
    
    const currentList = returnList.value || []
    const total = saleStore.returnTotal || 0
    
    // 判断是否加载完成
    if (currentList.length >= total || currentList.length === 0) {
      finished.value = true
    } else {
      if (!isRefresh) {
        pagination.page++
      }
    }
  } catch (error) {
    showFailToast('加载销售退货失败')
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
const handleCreateReturn = () => {
  router.push('/sale/return/create')
}

// 查看退货单详情
const handleViewReturn = (id) => {
  router.push(`/sale/return/detail/${id}`)
}

// 页面挂载时加载数据
onMounted(() => {
  loadReturnList(true)
})
</script>

<style scoped lang="scss">
.sale-return-page {
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
        height: 30px; // 设置固定高度，确保按钮高度一致
        
        .customer-filter,
        .type-filter,
        .date-filter {
          flex: 1; // 平分宽度
          display: flex;
        }
        
        // 客户选择器样式
        .customer-filter {
          :deep(.customer-select) {
            width: 100%;
            height: 100%;
            
            .default-trigger {
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
        }
        
        // 退货类型筛选样式
        .type-filter,
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

  // 退货列表样式
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