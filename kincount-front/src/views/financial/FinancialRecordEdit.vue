<template>
  <div class="financial-record-edit-page">
    <van-nav-bar 
      title="编辑收支记录"
      left-text="取消"
      right-text="保存"
      left-arrow
      @click-left="handleCancel"
      @click-right="handleSubmit"
    />

    <van-form ref="formRef" @submit="handleSubmit" class="form-container">
      <!-- 基本信息 -->
      <van-cell-group title="基本信息">
        <van-field
          v-model="typeDisplayText"
          label="收支类型"
          placeholder="请选择收支类型"
          readonly
          is-link
          required
          :rules="[{ required: true, message: '请选择收支类型' }]"
          @click="showTypePicker = true"
        />
        <van-field
          v-model="form.category_name"
          label="收支分类"
          placeholder="请选择分类"
          readonly
          is-link
          required
          :rules="[{ required: true, message: '请选择收支分类' }]"
          @click="showCategoryPicker = true"
        />
        <van-field
          v-model="form.amount"
          label="金额"
          type="number"
          placeholder="请输入金额"
          required
          :rules="[
            { required: true, message: '请输入金额' },
            { validator: validateAmount, message: '金额必须大于0' }
          ]"
        >
          <template #extra>元</template>
        </van-field>
        <van-field
          v-model="form.record_date"
          label="收支日期"
          placeholder="请选择日期"
          readonly
          is-link
          required
          :rules="[{ required: true, message: '请选择收支日期' }]"
          @click="showDatePicker = true"
        />
      </van-cell-group>

      <!-- 详细信息 -->
      <van-cell-group title="详细信息">
        <van-field
          v-model="form.description"
          label="备注说明"
          type="textarea"
          placeholder="请输入备注信息（可选）"
          rows="2"
          autosize
          maxlength="200"
          show-word-limit
        />
        <van-field
          v-model="form.payment_method"
          label="支付方式"
          placeholder="请选择支付方式"
          readonly
          is-link
          required
          :rules="[{ required: true, message: '请选择支付方式' }]"
          @click="showPaymentPicker = true"
        />
        <van-field
          v-model="form.related_order"
          label="关联单号"
          placeholder="请输入关联订单号（可选）"
        />
      </van-cell-group>

      <!-- 附件上传 -->
      <van-cell-group title="附件上传">
        <div class="upload-section">
          <van-uploader
            v-model="fileList"
            :max-count="3"
            :max-size="5 * 1024 * 1024"
            @oversize="onOverSize"
            :before-read="beforeRead"
            :after-read="afterRead"
            @delete="onFileDelete"
          />
          <div class="upload-tips">支持上传图片，最多3张，每张不超过5M</div>
        </div>
      </van-cell-group>

      <!-- 金额预览 -->
      <van-cell-group title="金额预览" v-if="form.amount">
        <van-cell title="收支类型" :value="typeDisplayText" />
        <van-cell title="金额" :value="getAmountDisplay(form.type, form.amount)" 
                  :value-class="getAmountClass(form.type)" />
      </van-cell-group>

      <!-- 记录信息 -->
      <van-cell-group title="记录信息" v-if="detail.record_no">
        <van-cell title="记录编号" :value="detail.record_no" />
        <van-cell title="创建人" :value="getCreatorName()" />
        <van-cell title="创建时间" :value="formatDateTime(detail.created_at)" />
        <van-cell title="最后更新" :value="formatDateTime(detail.updated_at)" />
      </van-cell-group>
    </van-form>

    <!-- 收支类型选择器 -->
    <van-popup v-model:show="showTypePicker" position="bottom">
      <van-picker
        :columns="typeOptions"
        @confirm="onTypeConfirm"
        @cancel="showTypePicker = false"
      />
    </van-popup>

    <!-- 分类选择器 -->
    <van-popup v-model:show="showCategoryPicker" position="bottom">
      <van-picker
        :columns="categoryOptions"
        @confirm="onCategoryConfirm"
        @cancel="showCategoryPicker = false"
      />
    </van-popup>

    <!-- 日期选择器 -->
    <van-popup v-model:show="showDatePicker" position="bottom">
      <van-date-picker
        :min-date="minDate"
        :max-date="maxDate"
        :default-date="currentDate"
        @confirm="onDateConfirm"
        @cancel="showDatePicker = false"
      />
    </van-popup>

    <!-- 支付方式选择器 -->
    <van-popup v-model:show="showPaymentPicker" position="bottom">
      <van-picker
        :columns="paymentOptions"
        @confirm="onPaymentConfirm"
        @cancel="showPaymentPicker = false"
      />
    </van-popup>

    <!-- 加载状态 -->
    <van-loading v-if="loading" class="page-loading" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { 
  showToast,
  showConfirmDialog,
  showSuccessToast,
  showFailToast
} from 'vant'
import { useFinancialStore } from '@/store/modules/financial'
import { getFinancialRecordDetail, updateFinancialRecord } from '@/api/financial'
import { uploadImage } from '@/api/utils'
import dayjs from 'dayjs'

const route = useRoute()
const router = useRouter()
const financialStore = useFinancialStore()
const formRef = ref()

// 响应式数据
const loading = ref(false)
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

// 表单数据
const form = reactive({
  type: '',           // 收支类型: income/expense
  category_id: '',    // 分类ID
  category_name: '',  // 分类名称
  amount: '',         // 金额
  record_date: '',    // 收支日期
  description: '',    // 备注
  payment_method: '', // 支付方式
  related_order: '',  // 关联单号
  attachments: []     // 附件
})

// 选择器状态
const showTypePicker = ref(false)
const showCategoryPicker = ref(false)
const showDatePicker = ref(false)
const showPaymentPicker = ref(false)

// 文件上传
const fileList = ref([])

// 选项数据
const typeOptions = ref([
  { text: '收入', value: 'income' },
  { text: '支出', value: 'expense' }
])

const categoryOptions = ref([])
const incomeCategories = ref([])
const expenseCategories = ref([])

const paymentOptions = ref([
  { text: '现金', value: '现金' },
  { text: '银行卡', value: '银行卡' },
  { text: '微信支付', value: '微信支付' },
  { text: '支付宝', value: '支付宝' },
  { text: '转账', value: '转账' },
  { text: '其他', value: '其他' }
])

// 日期范围
const minDate = new Date(2020, 0, 1)
const maxDate = new Date(2030, 11, 31)

// 当前日期（用于日期选择器默认值）
const currentDate = computed(() => {
  return form.record_date ? new Date(form.record_date) : new Date()
})

// 计算属性 - 收支类型显示文本
const typeDisplayText = computed(() => {
  if (!form.type) return ''
  const typeMap = {
    'income': '收入',
    'expense': '支出',
    '1': '收入',
    '2': '支出'
  }
  return typeMap[form.type] || '未知类型'
})

// 获取金额显示
const getAmountDisplay = (type, amount) => {
  if (!amount) return ''
  const sign = (type === 'income' || type === '1') ? '+' : '-'
  return `${sign}¥${parseFloat(amount).toFixed(2)}`
}

// 获取金额样式类
const getAmountClass = (type) => {
  return (type === 'income' || type === '1') ? 'income-amount' : 'expense-amount'
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

// 验证金额
const validateAmount = (value) => {
  const num = Number(value)
  return !isNaN(num) && num > 0
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
    
    // 填充表单数据
    form.type = detailData.type === 1 ? 'income' : 'expense'
    form.category_name = detailData.category
    form.amount = detailData.amount
    form.record_date = detailData.record_date
    form.description = detailData.description || detailData.remark || ''
    form.payment_method = detailData.payment_method
    form.related_order = detailData.related_order || ''
    form.attachments = detailData.attachments || []

    // 初始化文件列表
    if (form.attachments.length) {
      fileList.value = form.attachments.map((url, index) => ({
        url: url,
        status: 'done',
        message: '已上传',
        isImage: true
      }))
    }

    console.log('填充后的表单数据:', form)

    // 加载分类数据
    await loadCategories()

  } catch (error) {
    console.error('加载收支记录详情失败:', error)
    showFailToast('加载记录详情失败')
  } finally {
    loading.value = false
  }
}

// 加载分类数据
const loadCategories = async () => {
  try {
    await financialStore.loadCategories()
    
    console.log('原始分类数据:', financialStore.categories)
    
    // 处理收入分类 - 将对象转换为数组
    const incomeData = financialStore.categories.income || {}
    incomeCategories.value = Object.keys(incomeData).map(key => ({
      text: incomeData[key],
      value: key
    }))
    
    // 处理支出分类 - 将对象转换为数组
    const expenseData = financialStore.categories.expense || {}
    expenseCategories.value = Object.keys(expenseData).map(key => ({
      text: expenseData[key],
      value: key
    }))
    
    console.log('处理后的收入分类:', incomeCategories.value)
    console.log('处理后的支出分类:', expenseCategories.value)
    
    // 初始化分类选项
    updateCategoryOptions()
    
  } catch (error) {
    console.error('加载分类数据失败:', error)
    showToast('加载分类数据失败')
  }
}

// 更新分类选项（根据选择的类型）
const updateCategoryOptions = () => {
  if (form.type === 'income') {
    categoryOptions.value = incomeCategories.value
  } else if (form.type === 'expense') {
    categoryOptions.value = expenseCategories.value
  } else {
    categoryOptions.value = []
  }
  
  console.log('更新后的分类选项:', categoryOptions.value)
}

// 文件上传处理
const beforeRead = (file) => {
  if (!['image/jpeg', 'image/png', 'image/jpg', 'image/gif'].includes(file.type)) {
    showToast('请上传 jpg/png/gif 格式图片')
    return false
  }
  return true
}

const afterRead = async (file) => {
  file.status = 'uploading'
  file.message = '上传中...'
  
  try {
    const result = await uploadImage(file.file)
    file.status = 'done'
    file.message = '上传成功'
    file.url = result.url || result.data || result
    
    // 添加到表单附件
    if (file.url && !form.attachments.includes(file.url)) {
      form.attachments.push(file.url)
    }
    
    console.log('文件上传成功:', file.url)
    
  } catch (error) {
    console.error('文件上传失败:', error)
    file.status = 'failed'
    file.message = '上传失败'
    showToast('文件上传失败')
  }
}

const onOverSize = () => {
  showToast('图片大小不能超过 5M')
}

const onFileDelete = (file) => {
  // 从表单附件中移除（不调用 deleteFile API）
  const index = form.attachments.indexOf(file.url)
  if (index > -1) {
    form.attachments.splice(index, 1)
  }
  console.log('文件已从前端列表中删除:', file.url)
}

// 选择器确认事件
const onTypeConfirm = (value) => {
  const selectedValue = value.selectedOptions[0].value
  form.type = selectedValue
  showTypePicker.value = false
  console.log('选择的收支类型:', selectedValue, '显示文本:', typeDisplayText.value)
  updateCategoryOptions()
}

const onCategoryConfirm = (value) => {
  const selectedOption = value.selectedOptions[0]
  form.category_id = selectedOption.value
  form.category_name = selectedOption.text
  showCategoryPicker.value = false
  console.log('选择的分类:', selectedOption)
}

const onDateConfirm = (value) => {
  const selectedDate = new Date(
    value.selectedValues.year,
    value.selectedValues.month - 1,
    value.selectedValues.day
  )
  form.record_date = dayjs(selectedDate).format('YYYY-MM-DD')
  showDatePicker.value = false
}

const onPaymentConfirm = (value) => {
  form.payment_method = value.selectedOptions[0].value
  showPaymentPicker.value = false
}

// 事件处理
const handleCancel = () => {
  showConfirmDialog({
    title: '确认取消',
    message: '确定要取消编辑吗？所有未保存的修改将会丢失。'
  }).then(() => {
    router.back()
  }).catch(() => {
    // 取消操作
  })
}

// 处理提交数据，匹配后端格式
const handleSubmit = async () => {
  try {
    // 表单验证
    await formRef.value.validate()
    
    // 检查必填字段
    if (!form.type) {
      showToast('请选择收支类型')
      return
    }
    
    if (!form.category_name) {
      showToast('请选择收支分类')
      return
    }
    
    if (!form.amount || Number(form.amount) <= 0) {
      showToast('请输入有效的金额')
      return
    }

    if (!form.payment_method) {
      showToast('请选择支付方式')
      return
    }
    
    // 构建符合后端格式的提交数据
    const submitData = {
      type: form.type === 'income' ? 1 : 2, // 转换为数字：1=收入，2=支出
      category: form.category_name,         // 使用分类名称
      amount: Number(form.amount),
      record_date: form.record_date,
      payment_method: form.payment_method,
      description: form.description || '',
      related_order: form.related_order || '',
      attachments: form.attachments
    }

    console.log('提交的编辑数据:', submitData)

    loading.value = true
    
    // 调用更新API
    const result = await updateFinancialRecord(detail.id, submitData)
    showSuccessToast('收支记录更新成功')
    
    // 返回详情页
    router.push(`/financial/record/detail/${detail.id}`)
    
  } catch (error) {
    console.error('更新失败:', error)
    if (error.message && error.message !== 'cancel') {
      // 显示更具体的错误信息
      if (error.message.includes('category')) {
        showToast('分类字段错误，请重新选择')
      } else if (error.message.includes('type')) {
        showToast('收支类型错误，请重新选择')
      } else {
        showToast(error.message || '更新失败，请检查表单数据')
      }
    }
  } finally {
    loading.value = false
  }
}

// 监听类型变化，更新分类选项
watch(() => form.type, (newType) => {
  console.log('收支类型变化:', newType)
  updateCategoryOptions()
})

onMounted(() => {
  console.log('FinancialRecordEdit 页面挂载，记录ID:', route.params.id)
  loadDetail()
})
</script>

<style scoped lang="scss">
.financial-record-edit-page {
  background: #f7f8fa;
  min-height: 100vh;
}

.form-container {
  padding: 16px;
}

.upload-section {
  padding: 16px;
  
  .upload-tips {
    font-size: 12px;
    color: #969799;
    margin-top: 8px;
    text-align: center;
  }
}

.income-amount {
  color: #07c160;
  font-weight: bold;
}

.expense-amount {
  color: #ee0a24;
  font-weight: bold;
}

.page-loading {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 9999;
}

// 表单字段样式优化
:deep(.van-field__label) {
  width: 80px;
  flex: none;
}

:deep(.van-field__value) {
  text-align: right;
}

// 金额输入框样式
:deep(input[type="number"]) {
  text-align: right;
  font-size: 16px;
}

// 选择器样式优化
:deep(.van-picker) {
  background: #fff;
}

:deep(.van-picker__confirm) {
  color: #1989fa;
}

:deep(.van-picker__cancel) {
  color: #969799;
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

// 上传器样式
:deep(.van-uploader) {
  width: 100%;
}

:deep(.van-uploader__upload) {
  width: 80px;
  height: 80px;
  margin: 0 8px 8px 0;
}

:deep(.van-uploader__preview) {
  width: 80px;
  height: 80px;
  margin: 0 8px 8px 0;
}

:deep(.van-uploader__preview-image) {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

// 文本域样式
:deep(.van-field__body textarea) {
  min-height: 60px;
  resize: none;
}

// 记录信息样式
:deep(.van-cell-group:last-child) {
  margin-top: 16px;
}
</style>