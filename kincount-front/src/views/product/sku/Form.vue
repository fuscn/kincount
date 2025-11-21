<template>
  <div class="sku-form-page">
    <van-nav-bar
      :title="isEdit ? '编辑SKU' : '新增SKU'"
      left-text="取消"
      right-text="保存"
      left-arrow
      @click-left="handleCancel"
      @click-right="handleSubmit"
    />

    <van-form ref="formRef" @submit="handleSubmit" class="form-container">
      <van-cell-group title="基本信息">
        <van-field
          v-model="form.sku_code"
          label="SKU编码"
          placeholder="请输入SKU编码"
          maxlength="50"
          show-word-limit
          required
          :rules="[{ required: true, message: '请输入SKU编码' }]"
          :readonly="isEdit"
        />
        <van-field
          v-model="form.spec_text"
          label="规格描述"
          placeholder="请输入规格描述（如颜色、尺寸等）"
          maxlength="100"
          show-word-limit
          required
          :rules="[{ required: true, message: '请输入规格描述' }]"
        />
      </van-cell-group>

      <van-cell-group title="价格信息">
        <van-field
          v-model.number="form.cost_price"
          label="成本价"
          type="number"
          placeholder="请输入成本价"
          required
          :rules="[
            { required: true, message: '请输入成本价' },
            { validator: validatePrice, message: '成本价必须大于0' }
          ]"
        >
          <template #extra>元</template>
        </van-field>
        <van-field
          v-model.number="form.sale_price"
          label="售价"
          type="number"
          placeholder="请输入售价"
          required
          :rules="[
            { required: true, message: '请输入售价' },
            { validator: validatePrice, message: '售价必须大于0' }
          ]"
        >
          <template #extra>元</template>
        </van-field>
      </van-cell-group>

      <van-cell-group title="库存信息">
        <van-field
          v-model.number="form.stock"
          label="初始库存"
          type="number"
          placeholder="请输入初始库存"
          required
          :rules="[
            { required: true, message: '请输入初始库存' },
            { validator: validateStock, message: '库存不能为负数' }
          ]"
        >
          <template #extra>件</template>
        </van-field>
      </van-cell-group>

      <van-cell-group title="状态设置">
        <van-cell center title="启用SKU">
          <template #right-icon>
            <van-switch v-model="statusBool" />
          </template>
        </van-cell>
      </van-cell-group>

      <van-cell-group title="备注信息">
        <van-field
          v-model="form.remark"
          label="备注"
          type="textarea"
          placeholder="请输入备注（可选）"
          rows="2"
          autosize
          maxlength="200"
          show-word-limit
        />
      </van-cell-group>

      <van-cell-group title="时间信息" v-if="isEdit">
        <van-cell title="创建时间" :value="formatDate(form.created_at)" />
        <van-cell title="更新时间" :value="formatDate(form.updated_at)" />
      </van-cell-group>
    </van-form>

    <van-loading v-if="loading" class="page-loading" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showToast, showConfirmDialog, showSuccessToast } from 'vant'
import { addSku, updateSku, getSkuDetail } from '@/api/product'
import dayjs from 'dayjs'

const route = useRoute()
const router = useRouter()
const formRef = ref()

// 路由参数获取商品ID和SKU ID
const productId = route.params.productId
const skuId = route.params.skuId

/* 响应式数据 */
const loading = ref(false)
const isEdit = computed(() => !!skuId)

const form = reactive({
  id: '',
  sku_code: '',
  spec_text: '',
  cost_price: null,
  sale_price: null,
  stock: 0,
  status: 1,
  remark: '',
  product_id: productId, // 关联商品聚合ID
  created_at: '',
  updated_at: ''
})

// 状态开关双向绑定
const statusBool = computed({
  get: () => form.status === 1,
  set: (val) => (form.status = val ? 1 : 0)
})

/* 工具函数 */
const formatDate = (d) => (d ? dayjs(d).format('YYYY-MM-DD HH:mm') : '')

// 价格验证（必须大于0）
const validatePrice = (value) => {
  return value > 0
}

// 库存验证（不能为负数）
const validateStock = (value) => {
  return value >= 0
}

/* 数据加载 */
const loadDetail = async () => {
  if (!isEdit.value) return
  loading.value = true
  try {
    const res = await getSkuDetail(skuId)
    const data = res.data || res
    // 映射接口返回数据到表单
    Object.assign(form, {
      id: data.id,
      sku_code: data.sku_code,
      spec_text: data.spec_text,
      cost_price: data.cost_price,
      sale_price: data.sale_price,
      stock: data.stock,
      status: data.status,
      remark: data.remark || '',
      created_at: data.created_at,
      updated_at: data.updated_at
    })
  } catch (error) {
    console.error('加载SKU详情失败:', error)
    showToast('加载SKU详情失败')
    router.back()
  } finally {
    loading.value = false
  }
}

/* 事件处理 */
const handleCancel = () => {
  showConfirmDialog({
    title: '确认取消',
    message: '未保存的内容将丢失，是否继续？'
  })
    .then(() => router.back())
    .catch(() => {})
}

const handleSubmit = async () => {
  try {
    await formRef.value.validate()
    if (loading.value) return
    loading.value = true

    // 构建提交数据
    const payload = {
      sku_code: form.sku_code.trim(),
      spec_text: form.spec_text.trim(),
      cost_price: Number(form.cost_price),
      sale_price: Number(form.sale_price),
      stock: Number(form.stock),
      status: form.status,
      remark: form.remark.trim(),
      product_id: productId
    }

    if (isEdit.value) {
      // 编辑模式：调用更新接口
      await updateSku(form.id, payload)
      showSuccessToast('SKU更新成功')
    } else {
      // 新增模式：调用创建接口
      await addSku(payload)
      showSuccessToast('SKU创建成功')
    }

    // 提交成功后返回SKU列表页
    router.replace(`/product/${productId}/skus`)
  } catch (error) {
    if (error?.name !== 'ValidateError') {
      console.error('保存SKU失败:', error)
      showToast(error?.message || '保存失败，请重试')
    }
  } finally {
    loading.value = false
  }
}

/* 生命周期 */
onMounted(() => {
  loadDetail()
})
</script>

<style scoped lang="scss">
.sku-form-page {
  background: #f7f8fa;
  min-height: 100vh;
}

.form-container {
  padding: 12px;
}

.page-loading {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 9999;
}

:deep(.van-cell-group__title) {
  font-size: 14px;
  color: #323233;
  font-weight: 500;
  padding: 16px 16px 8px;
  background: #f7f8fa;
}

:deep(.van-field__label) {
  width: 100px;
  flex: none;
}
</style>