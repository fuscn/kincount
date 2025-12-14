<template>
  <div class="product-aggregate-form">
    <van-nav-bar 
      :title="isEdit ? '编辑商品资料' : '新增商品资料'" 
      left-text="取消" 
      right-text="保存" 
      left-arrow 
      @click-left="onCancel"
      @click-right="onSubmit" 
    />

    <van-form ref="formRef" class="form-wrap" @submit="onSubmit">
      <!-- 基础信息 -->
      <van-cell-group title="基础信息">
        <van-field 
          v-model="form.name" 
          name="name"
          label="商品名称" 
          placeholder="请输入商品名称"
          :rules="[{ required: true, message: '请输入商品名称' }]" 
        />
        <van-field 
          v-model="form.unit" 
          name="unit"
          label="单位" 
          placeholder="请输入单位" 
          :rules="[{ required: true, message: '请输入单位' }]" 
        />
      </van-cell-group>

      <!-- 分类品牌 -->
      <van-cell-group title="分类品牌">
        <!-- 分类选择 -->
        <CategorySelect
          v-model="form.category_id"
          label="商品分类"
          placeholder="请选择分类"
          :required="true"
          title="选择商品分类"
          @confirm="onCategorySelect"
          @change="onCategoryChange"
        />
        
        <!-- 品牌选择 - 使用 BrandSelect 组件 -->
        <BrandSelect
          v-model="form.brand_id"
          label="品牌"
          placeholder="请选择品牌"
          @change="onBrandSelect"
        />
      </van-cell-group>

      <!-- 商品图片 -->
      <van-cell-group title="商品图片">
        <Upload v-model="form.images" :max-count="5" />
      </van-cell-group>

      <!-- 商品描述 -->
      <van-cell-group title="商品描述">
        <van-field 
          v-model="form.description" 
          name="description"
          label="描述" 
          type="textarea" 
          placeholder="请输入商品描述" 
          rows="3" 
          autosize 
        />
      </van-cell-group>
    </van-form>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showToast, showConfirmDialog, showSuccessToast } from 'vant'
import {
  getProductAggregate,
  saveProductAggregate,
  updateProductAggregate
} from '@/api/product'
import CategorySelect from '@/components/business/CategorySelect.vue'
import BrandSelect from '@/components/business/BrandSelect.vue'
import Upload from '@/components/common/Upload.vue'

/* ===== 基础数据 ===== */
const route = useRoute()
const router = useRouter()
const formRef = ref()
const isEdit = computed(() => !!route.params.id)
const submitted = ref(false)

const form = reactive({
  id: '',
  name: '',
  unit: '',
  category_id: '',
  brand_id: '',
  images: [],
  description: ''
})

/* ===== 加载商品数据 ===== */
onMounted(async () => {
  try {
    if (isEdit.value) {
      await loadAggregate()
    }
  } catch (error) {
    showToast('加载数据失败')
  }
})

async function loadAggregate() {
  try {
    const data = await getProductAggregate(route.params.id)
    Object.assign(form, data)
  } catch (error) {
    showToast('加载商品详情失败')
  }
}

/* ===== 事件处理 ===== */
const onCategorySelect = (id, item) => {
  console.log('分类选择确认:', id, item)
}

const onCategoryChange = (id) => {
  console.log('分类变化:', id)
}

const onBrandSelect = (brand) => {
  console.log('品牌选择:', brand)
}

/* ===== 表单提交 ===== */
const onSubmit = async () => {
  try {
    submitted.value = true
    
    // 验证必填字段
    if (!form.name.trim()) {
      showToast('请输入商品名称')
      return
    }
    
    if (!form.unit.trim()) {
      showToast('请输入单位')
      return
    }
    
    if (!form.category_id) {
      showToast('请选择商品分类')
      return
    }
    
    // 构建提交数据
    const payload = {
      name: form.name,
      unit: form.unit,
      category_id: form.category_id,
      brand_id: form.brand_id || undefined,
      images: form.images || [],
      description: form.description || ''
    }

    // 过滤掉 undefined 和 null
    Object.keys(payload).forEach(key => {
      if (payload[key] === undefined || payload[key] === null) {
        delete payload[key]
      }
    })

    // 调用 API
    let responseData
    if (isEdit.value) {
      payload.id = form.id
      responseData = await updateProductAggregate(payload)
    } else {
      responseData = await saveProductAggregate(payload)
    }

    showSuccessToast(isEdit.value ? '更新成功' : '创建成功')
    
    // 处理跳转
    if (responseData && responseData.id) {
      const productId = String(responseData.id)
      
      if (!isEdit.value) {
        // 新增商品后跳转到SKU创建页面
        router.replace(`/product/${productId}/skus/create`)
      } else {
        // 编辑商品后跳转到SKU列表页面
        router.replace(`/product/${productId}/skus`)
      }
    } else {
      // 如果API没有返回ID，跳转到商品列表
      router.replace('/product/list')
    }
    
  } catch (error) {
    console.error('表单提交错误:', error)
    
    // 特殊处理：如果错误消息包含成功信息
    if (error.message && (error.message.includes('成功') || error.message === '创建成功' || error.message === '更新成功')) {
      showSuccessToast(isEdit.value ? '更新成功' : '创建成功')
      
      // 延迟跳转，确保用户看到成功提示
      setTimeout(() => {
        if (!isEdit.value) {
          // 新增成功，跳转到商品列表
          router.replace('/product/list')
        } else {
          // 编辑成功，返回上一页或商品列表
          router.back()
        }
      }, 1500)
      return
    }
    
    showToast(error?.message || '提交失败，请重试')
  }
}

const onCancel = async () => {
  try {
    await showConfirmDialog({
      title: '取消',
      message: '未保存的数据将丢失，确定取消吗？'
    })
    router.back()
  } catch {
    // 用户点击取消，什么都不做
  }
}
</script>

<style scoped lang="scss">
.product-aggregate-form {
  background: #f7f8fa;
  min-height: 100vh;
  padding-bottom: 20px;
}

.form-wrap {
  padding: 0 16px;
}

:deep(.van-cell-group__title) {
  padding: 16px 16px 8px;
  font-weight: 500;
}
</style>