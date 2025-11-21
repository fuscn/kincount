<template>
  <div class="sku-list-page">
    <van-nav-bar
      title="SKU总列表"
      left-text="返回"
      right-text="新增SKU"
      left-arrow
      @click-left="$emit('back')"
      @click-right="handleAdd"
    />

    <van-pull-refresh v-model="refreshing" @refresh="onRefresh">
      <van-list
        v-model:loading="loading"
        :finished="finished"
        finished-text="没有更多数据"
        @load="onLoad"
      >
        <van-swipe-cell v-for="item in skuList" :key="item.id">
          <van-card
            :title="item.sku_code"
            :desc="getSpecText(item)"
            :thumb="getThumbImage(item)"
          >
            <template #tags>
              <van-tag v-if="item.status === 1" type="success">启用</van-tag>
              <van-tag v-else type="default">禁用</van-tag>
            </template>
            <template #price>
              <span class="price">¥{{ item.sale_price }}</span>
            </template>
            <template #footer>
              <div class="footer-info">
                <span>库存: {{ item.stock_quantity || 0 }}</span>
                <span>成本: ¥{{ item.cost_price }}</span>
              </div>
            </template>
          </van-card>
          <template #right>
            <van-button square type="primary" text="编辑" @click="handleEdit(item)" />
            <van-button square type="danger" text="删除" @click="handleDelete(item.id)" />
          </template>
        </van-swipe-cell>

        <van-empty v-if="skuList.length === 0 && !loading" description="暂无SKU数据" />
      </van-list>
    </van-pull-refresh>

    <!-- 新增/编辑弹窗 -->
    <van-popup
      v-model:show="showForm"
      position="bottom"
      round
      :style="{ height: '80%' }"
      closeable
      @close="resetForm"
    >
      <div class="form-title">{{ isEditing ? '编辑SKU' : '新增SKU' }}</div>
      <van-form ref="skuForm" @submit="submitForm">
        <van-field
          v-model="formData.sku_code"
          label="SKU编码"
          placeholder="请输入SKU编码"
          :rules="[{ required: true, message: '请输入SKU编码' }]"
        />
        
        <!-- 规格字段编辑 -->
        <div class="spec-fields" v-if="showSpecFields">
          <div class="section-title">规格信息</div>
          <van-field
            v-model="formData.spec.thickness"
            label="厚度"
            placeholder="请输入厚度"
          />
          <van-field
            v-model="formData.spec.length"
            label="长度"
            placeholder="请输入长度"
          />
          <van-field
            v-model="formData.spec.color"
            label="颜色"
            placeholder="请输入颜色"
          />
        </div>

        <!-- 或者使用文本区域编辑整个规格 -->
        <van-field
          v-model="specTextInput"
          label="规格文本"
          placeholder="请输入规格描述"
          type="textarea"
          rows="2"
          autosize
          @blur="handleSpecTextChange"
        />
        
        <van-field
          v-model.number="formData.cost_price"
          label="成本价"
          type="number"
          placeholder="0.00"
          :rules="[{ required: true, message: '请输入成本价' }]"
        />
        <van-field
          v-model.number="formData.sale_price"
          label="销售价"
          type="number"
          placeholder="0.00"
          :rules="[{ required: true, message: '请输入销售价' }]"
        />
        <van-field
          v-model="formData.barcode"
          label="条码"
          placeholder="请输入条码"
        />
        <van-field
          v-model="formData.unit"
          label="单位"
          placeholder="个/件/箱"
          :rules="[{ required: true, message: '请输入单位' }]"
        />
        <van-switch
          v-model="formData.status"
          :model-value="formData.status === 1"
          @update:model-value="formData.status = $event ? 1 : 0"
          label="状态"
          active-text="启用"
          inactive-text="禁用"
        />
        <div class="form-actions">
          <van-button round block type="primary" native-type="submit" :loading="submitting">
            保存
          </van-button>
        </div>
      </van-form>
    </van-popup>
  </div>
</template>

<script setup>
import { ref, reactive, watch, onMounted, computed } from 'vue'
import { showToast, showConfirmDialog } from 'vant'
import { getSkuList, addSku, updateSku, deleteSku } from '@/api/product'

const props = defineProps({
  productId: {
    type: [String, Number],
    required: true
  }
})

const emit = defineEmits(['back', 'update'])

// 状态管理
const skuList = ref([])
const loading = ref(false)
const finished = ref(false)
const refreshing = ref(false)
const page = ref(1)
const limit = ref(10)

// 表单相关
const showForm = ref(false)
const submitting = ref(false)
const isEditing = ref(false)
const specTextInput = ref('') // 规格文本输入

// 表单数据结构
const formData = reactive({
  id: '',
  sku_code: '',
  spec: {
    thickness: '',
    length: '',
    color: ''
  },
  cost_price: 0,
  sale_price: 0,
  barcode: '',
  unit: '',
  status: 1,
  product_id: props.productId
})

// 是否显示规格字段（根据业务需求决定）
const showSpecFields = ref(true)

// 初始化加载
onMounted(() => {
  loadSkuList()
})

// 监听商品ID变化重新加载
watch(() => props.productId, (newVal) => {
  if (newVal) {
    formData.product_id = newVal
    resetList()
    loadSkuList()
  }
})

// 加载SKU列表
const loadSkuList = async () => {
  if (loading.value) return

  loading.value = true
  try {
    const res = await getSkuList(props.productId, {
      page: page.value,
      limit: limit.value
    })

    const data = res.list || res.data?.list || []
    if (page.value === 1) {
      skuList.value = data
    } else {
      skuList.value = [...skuList.value, ...data]
    }

    finished.value = data.length < limit.value
    page.value++
  } catch (error) {
    showToast('加载SKU列表失败')
    console.error('加载SKU列表失败:', error)
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

// 下拉刷新
const onRefresh = () => {
  resetList()
  loadSkuList()
}

// 上拉加载
const onLoad = () => {
  if (finished.value) return
  loadSkuList()
}

// 重置列表
const resetList = () => {
  page.value = 1
  finished.value = false
  skuList.value = []
}

// 格式化规格文本
const getSpecText = (item) => {
  if (item.spec && typeof item.spec === 'object') {
    return Object.values(item.spec).filter(Boolean).join(' / ')
  }
  return item.spec_text || '无规格'
}

// 获取缩略图
const getThumbImage = (item) => {
  if (item.image) return item.image
  if (item.product?.image) return item.product.image
  return 'https://img.yzcdn.cn/vant/placeholder.png'
}

// 处理规格文本变化
const handleSpecTextChange = () => {
  // 这里可以添加从文本解析到spec对象的逻辑
  // 例如：如果用户输入 "15mm/3050mm/红胡桃"，可以解析到对应的spec字段
  if (specTextInput.value) {
    const parts = specTextInput.value.split('/').map(part => part.trim())
    if (parts.length >= 3) {
      formData.spec.thickness = parts[0]
      formData.spec.length = parts[1]
      formData.spec.color = parts[2]
    }
  }
}

// 从spec对象生成规格文本
const generateSpecText = (spec) => {
  if (!spec || typeof spec !== 'object') return ''
  return Object.values(spec).filter(Boolean).join(' / ')
}

// 新增SKU
const handleAdd = () => {
  resetForm()
  isEditing.value = false
  showForm.value = true
}

// 编辑SKU
const handleEdit = (item) => {
  isEditing.value = true
  
  // 填充表单数据
  formData.id = item.id
  formData.sku_code = item.sku_code
  formData.cost_price = Number(item.cost_price)
  formData.sale_price = Number(item.sale_price)
  formData.barcode = item.barcode || ''
  formData.unit = item.unit || ''
  formData.status = item.status || 1
  
  // 处理规格数据
  if (item.spec && typeof item.spec === 'object') {
    // 直接使用spec对象
    formData.spec = { ...item.spec }
    // 同时更新规格文本输入
    specTextInput.value = generateSpecText(item.spec)
  } else if (item.spec_text) {
    // 如果有spec_text字段，使用它
    specTextInput.value = item.spec_text
  } else {
    // 重置规格数据
    formData.spec = { thickness: '', length: '', color: '' }
    specTextInput.value = ''
  }
  
  showForm.value = true
}

// 重置表单
const resetForm = () => {
  formData.id = ''
  formData.sku_code = ''
  formData.spec = { thickness: '', length: '', color: '' }
  formData.cost_price = 0
  formData.sale_price = 0
  formData.barcode = ''
  formData.unit = ''
  formData.status = 1
  specTextInput.value = ''
  submitting.value = false
}

// 准备提交数据
const prepareSubmitData = () => {
  const submitData = {
    ...formData,
    product_id: props.productId
  }
  
  // 确保spec字段是对象格式
  if (typeof submitData.spec !== 'object') {
    submitData.spec = {}
  }
  
  return submitData
}

// 提交表单
const submitForm = async () => {
  submitting.value = true
  try {
    const submitData = prepareSubmitData()
    
    if (isEditing.value) {
      await updateSku(formData.id, submitData)
      showToast('编辑成功')
    } else {
      await addSku(submitData)
      showToast('新增成功')
    }
    showForm.value = false
    resetList()
    loadSkuList()
    emit('update')
  } catch (error) {
    showToast(isEditing.value ? '编辑失败' : '新增失败')
    console.error('SKU操作失败:', error)
  } finally {
    submitting.value = false
  }
}

// 删除SKU
const handleDelete = async (id) => {
  try {
    await showConfirmDialog({
      title: '确认删除',
      message: '确定要删除该SKU吗？此操作不可恢复'
    })

    await deleteSku(id)
    showToast('删除成功')
    resetList()
    loadSkuList()
    emit('update')
  } catch (error) {
    if (error !== 'cancel') {
      showToast('删除失败')
      console.error('删除SKU失败:', error)
    }
  }
}
</script>

<style scoped lang="scss">
.sku-list-page {
  background-color: #f7f8fa;
  min-height: 100vh;
}

.van-card {
  margin: 10px;
  border-radius: 8px;
  overflow: hidden;
}

.price {
  color: #ee0a24;
  font-weight: bold;
}

.footer-info {
  display: flex;
  justify-content: space-between;
  width: 100%;
  font-size: 12px;
  color: #666;
}

.form-title {
  padding: 16px;
  font-size: 16px;
  font-weight: 500;
  text-align: center;
  border-bottom: 1px solid #eee;
}

.form-actions {
  padding: 16px;
}

.van-swipe-cell {
  background-color: transparent;
}

.spec-fields {
  padding: 0 16px;
}

.section-title {
  padding: 16px 0 8px;
  font-size: 14px;
  color: #969799;
  border-bottom: 1px solid #ebedf0;
  margin-bottom: 8px;
}
</style>