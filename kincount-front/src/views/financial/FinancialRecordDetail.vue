<template>
  <div class="financial-record-detail-page">
    <van-nav-bar 
      title="收支记录详情"
      left-text="返回"
      left-arrow
      @click-left="handleBack"
    >
      <template #right>
        <van-button 
          v-if="hasEditPermission" 
          size="small" 
          type="primary" 
          @click="handleEdit"
        >
          编辑
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 加载状态 -->
    <van-loading v-if="loading" class="page-loading" />

    <!-- 主要内容 -->
    <div v-else class="detail-content">
      <!-- 基本信息卡片 -->
      <van-cell-group title="基本信息" class="info-card">
        <van-cell title="记录编号" :value="detail.record_no" />
        <van-cell title="收支类型" :value="getTypeText(detail.type)" />
        <van-cell title="收支分类" :value="detail.category" />
        <van-cell title="金额" :value="getAmountDisplay(detail.type, detail.amount)" 
                  :value-class="getAmountClass(detail.type)" />
        <van-cell title="收支日期" :value="formatDate(detail.record_date)" />
        <van-cell title="支付方式" :value="detail.payment_method" />
        <van-cell title="创建人" :value="getCreatorName()" />
        <van-cell title="创建时间" :value="formatDateTime(detail.created_at)" />
      </van-cell-group>

      <!-- 备注信息 -->
      <van-cell-group title="备注信息" class="remark-card" v-if="detail.remark || detail.description">
        <van-cell>
          <template #value>
            <div class="remark-content">{{ detail.remark || detail.description }}</div>
          </template>
        </van-cell>
      </van-cell-group>

      <!-- 关联信息 -->
      <van-cell-group title="关联信息" class="related-card" v-if="detail.related_order">
        <van-cell title="关联单号" :value="detail.related_order" />
      </van-cell-group>

      <!-- 附件信息 -->
      <van-cell-group title="附件信息" class="attachments-card" v-if="detail.attachments && detail.attachments.length">
        <van-cell>
          <template #value>
            <div class="attachments-list">
              <div 
                v-for="(attachment, index) in detail.attachments" 
                :key="index"
                class="attachment-item"
                @click="previewImage(attachment, index)"
              >
                <van-image
                  :src="attachment"
                  width="80"
                  height="80"
                  fit="cover"
                  class="attachment-image"
                >
                  <template v-slot:error>
                    <div class="attachment-error">
                      <van-icon name="photo-fail" size="24" />
                      <div>加载失败</div>
                    </div>
                  </template>
                  <template v-slot:loading>
                    <van-loading type="spinner" size="20" />
                  </template>
                </van-image>
              </div>
            </div>
          </template>
        </van-cell>
      </van-cell-group>

      <!-- 操作按钮 -->
      <div class="action-buttons" v-if="hasEditPermission || hasDeletePermission">
        <van-button 
          v-if="hasEditPermission"
          type="primary" 
          block
          @click="handleEdit"
          class="edit-button"
        >
          编辑记录
        </van-button>
        <van-button 
          v-if="hasDeletePermission"
          type="danger" 
          block
          @click="handleDelete"
          class="delete-button"
        >
          删除记录
        </van-button>
      </div>

      <!-- 空状态 -->
      <van-empty
        v-if="!loading && !detail.id"
        description="记录不存在或已被删除"
        image="error"
      >
        <van-button type="primary" @click="handleBack">返回列表</van-button>
      </van-empty>
    </div>

    <!-- 图片预览 -->
    <van-image-preview
      v-model:show="showImagePreview"
      :images="previewImages"
      :start-position="previewIndex"
    />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { 
  showToast,
  showConfirmDialog,
  showSuccessToast,
  showFailToast
} from 'vant'
import { useFinancialStore } from '@/store/modules/financial'
import { getFinancialRecordDetail, deleteFinancialRecord } from '@/api/financial'
import { PERM } from '@/constants/permissions'
import { useAuthStore } from '@/store/modules/auth'
import dayjs from 'dayjs'

const route = useRoute()
const router = useRouter()
const financialStore = useFinancialStore()
const authStore = useAuthStore()

// 响应式数据
const loading = ref(true)
const detail = reactive({
  id: '',
  record_no: '',
  type: '',
  category: '',
  amount: 0,
  record_date: '',
  payment_method: '',
  description: '',
  remark: '',
  related_order: '',
  attachments: [],
  created_by: '',
  created_by_name: '',
  created_at: '',
  updated_at: '',
  creator: null
})

// 图片预览
const showImagePreview = ref(false)
const previewImages = ref([])
const previewIndex = ref(0)

// 权限检查
const hasEditPermission = computed(() => authStore.hasPerm(PERM.FINANCE_ADD))
const hasDeletePermission = computed(() => authStore.hasPerm(PERM.FINANCE_ADD))

// 获取类型文本
const getTypeText = (type) => {
  const typeMap = {
    '1': '收入',
    '2': '支出',
    'income': '收入',
    'expense': '支出'
  }
  return typeMap[type] || '未知类型'
}

// 获取金额显示
const getAmountDisplay = (type, amount) => {
  if (!amount) return ''
  const sign = type === '1' || type === 'income' ? '+' : '-'
  return `${sign}¥${parseFloat(amount).toFixed(2)}`
}

// 获取金额样式类
const getAmountClass = (type) => {
  return (type === '1' || type === 'income') ? 'income-amount' : 'expense-amount'
}

// 格式化日期
const formatDate = (date) => {
  if (!date) return ''
  return dayjs(date).format('YYYY-MM-DD')
}

// 格式化日期时间
const formatDateTime = (date) => {
  if (!date) return ''
  return dayjs(date).format('YYYY-MM-DD HH:mm:ss')
}

// 获取创建人姓名
const getCreatorName = () => {
  if (detail.creator && detail.creator.real_name) {
    return detail.creator.real_name
  } else if (detail.creator && detail.creator.username) {
    return detail.creator.username
  } else if (detail.created_by_name) {
    return detail.created_by_name
  }
  return '未知'
}

// 加载详情数据
const loadDetail = async () => {
  const recordId = route.params.id
  if (!recordId) {
    showToast('记录ID不存在')
    return
  }

  loading.value = true
  try {
    console.log('开始加载收支记录详情，ID:', recordId)
    
    // 调用API获取详情
    const result = await getFinancialRecordDetail(recordId)
    console.log('收支记录详情响应:', result)

    // 处理不同的响应结构
    let detailData = {}
    if (result && result.data) {
      detailData = result.data
    } else {
      detailData = result || {}
    }

    // 更新详情数据
    Object.assign(detail, detailData)
    
    console.log('处理后的详情数据:', detail)

    // 准备图片预览数据
    if (detail.attachments && detail.attachments.length) {
      previewImages.value = detail.attachments
    }

  } catch (error) {
    console.error('加载收支记录详情失败:', error)
    showFailToast('加载记录详情失败')
  } finally {
    loading.value = false
  }
}

// 图片预览
const previewImage = (image, index) => {
  previewIndex.value = index
  showImagePreview.value = true
}

// 事件处理
const handleBack = () => {
  router.back()
}

const handleEdit = () => {
  if (!detail.id) {
    showToast('记录ID不存在')
    return
  }
  router.push(`/financial/record/edit/${detail.id}`)
}

const handleDelete = async () => {
  if (!detail.id) {
    showToast('记录ID不存在')
    return
  }

  try {
    await showConfirmDialog({
      title: '确认删除',
      message: `确定要删除收支记录 "${detail.record_no}" 吗？此操作不可恢复。`
    })

    // 调用删除API
    await deleteFinancialRecord(detail.id)
    showSuccessToast('删除成功')
    
    // 返回列表页
    router.push('/financial/record')
    
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      showFailToast('删除失败')
    }
  }
}

onMounted(() => {
  console.log('FinancialRecordDetail 页面挂载，记录ID:', route.params.id)
  loadDetail()
})
</script>

<style scoped lang="scss">
.financial-record-detail-page {
  background: #f7f8fa;
  min-height: 100vh;
}

.page-loading {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 9999;
}

.detail-content {
  padding: 16px;
}

.info-card,
.remark-card,
.related-card,
.attachments-card {
  margin-bottom: 16px;
  border-radius: 8px;
  overflow: hidden;
}

.remark-content {
  padding: 8px 0;
  line-height: 1.5;
  color: #323233;
}

.attachments-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 8px 0;
}

.attachment-item {
  position: relative;
  border-radius: 4px;
  overflow: hidden;
  cursor: pointer;
  transition: transform 0.2s ease;
  
  &:active {
    transform: scale(0.95);
  }
}

.attachment-image {
  border-radius: 4px;
  overflow: hidden;
}

.attachment-error {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: #969799;
  font-size: 12px;
}

.action-buttons {
  padding: 16px;
  background: white;
  border-radius: 8px;
  margin-top: 16px;
  
  .edit-button {
    margin-bottom: 12px;
  }
}

.income-amount {
  color: #07c160;
  font-weight: bold;
  font-size: 16px;
}

.expense-amount {
  color: #ee0a24;
  font-weight: bold;
  font-size: 16px;
}

// 导航栏样式
:deep(.van-nav-bar) {
  background: #fff;
}

:deep(.van-nav-bar__text) {
  color: #1989fa;
}

:deep(.van-nav-bar .van-icon) {
  color: #1989fa;
}

// 单元格组标题样式
:deep(.van-cell-group__title) {
  font-size: 14px;
  color: #323233;
  font-weight: 500;
  padding: 16px 16px 8px;
  background: #f7f8fa;
}

// 单元格样式优化
:deep(.van-cell) {
  padding: 12px 16px;
}

:deep(.van-cell__title) {
  flex: 2;
  color: #646566;
}

:deep(.van-cell__value) {
  flex: 3;
  text-align: right;
  color: #323233;
}

// 按钮样式
:deep(.van-button) {
  border-radius: 6px;
  height: 44px;
  font-size: 16px;
}

// 空状态样式
:deep(.van-empty) {
  padding: 60px 0;
}

// 图片预览样式
:deep(.van-image-preview) {
  z-index: 2000;
}

// 响应式设计
@media (max-width: 768px) {
  .detail-content {
    padding: 12px;
  }
  
  .attachments-list {
    gap: 6px;
  }
  
  :deep(.van-cell) {
    padding: 10px 12px;
  }
}
</style>