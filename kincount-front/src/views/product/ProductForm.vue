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
        <!-- 商品分类选择 - 使用 van-field 触发 -->
        <van-field
          readonly
          clickable
          label="商品分类"
          :placeholder="categoryDisplayText || '请选择分类'"
          :rules="[{ required: true, message: '请选择商品分类' }]"
          :error-message="formErrors.category_id"
          @click="openCategorySelect"
          class="category-field"
        >
          <template #input>
            <span class="category-display">{{ categoryDisplayText }}</span>
          </template>
          <template #right-icon>
            <van-icon name="arrow" />
          </template>
        </van-field>
        
        <!-- 品牌选择 - 使用 van-field 触发 -->
        <van-field
          readonly
          clickable
          label="品牌"
          :placeholder="selectedBrand?.name || '请选择品牌'"
          @click="openBrandSelect"
        >
          <template #input>
            {{ selectedBrand?.name || '' }}
          </template>
          <template #right-icon>
            <van-icon name="arrow" />
          </template>
        </van-field>
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

    <!-- 分类选择器组件 - 隐藏触发按钮 -->
    <CategorySelect
      ref="categorySelectRef"
      v-model="form.category_id"
      :allow-select-parent="false"
      :title="'选择商品分类'"
      :cancel-text="'取消'"
      :confirm-text="'确定'"
      :show-full-path="true"
      :auto-close="true"
      :hide-trigger="true"
      @confirm="onCategorySelectConfirm"
      @cancel="onCategorySelectCancel"
      @change="onCategorySelectChange"
    />

    <!-- 品牌选择器组件 - 使用空的触发器插槽 -->
    <BrandSelect
      ref="brandSelectRef"
      v-model="form.brand_id"
      :popup-title="'选择品牌'"
      :search-placeholder="'搜索品牌'"
      :use-store-cache="true"
      :return-object="true"
      @change="onBrandSelectChange"
    >
      <!-- 空的触发器插槽，不显示任何内容 -->
      <template #trigger="{ selected, open }"></template>
    </BrandSelect>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
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
const categorySelectRef = ref()
const brandSelectRef = ref()

const isEdit = computed(() => !!route.params.id)
const submitted = ref(false)
const formErrors = reactive({
  category_id: ''
})

const form = reactive({
  id: '',
  name: '',
  unit: '',
  category_id: '',
  brand_id: '',
  images: [],
  description: ''
})

/* ===== 计算属性 ===== */
// 获取分类显示文本 - 包含完整路径
const categoryDisplayText = computed(() => {
  if (!form.category_id) return ''
  
  // 从 CategorySelect 组件获取选中的分类节点
  const selectedNode = categorySelectRef.value?.getSelectedItem?.()
  if (!selectedNode) return ''
  
  // 检查节点是否有完整路径信息
  if (selectedNode.fullPath) {
    // 如果组件提供了完整路径，直接使用
    return selectedNode.fullPath
  } else if (selectedNode.pathNames && Array.isArray(selectedNode.pathNames)) {
    // 如果组件提供了路径名称数组
    return selectedNode.pathNames.join(' > ')
  } else if (selectedNode.parentNames && Array.isArray(selectedNode.parentNames)) {
    // 如果组件提供了父级名称数组
    return [...selectedNode.parentNames, selectedNode.name].join(' > ')
  }
  
  // 如果都没有，只显示分类名称
  return selectedNode.name
})

// 获取选中的分类对象（可选，如果需要的话）
const selectedCategory = computed(() => {
  if (!form.category_id) return null
  return categorySelectRef.value?.getSelectedItem?.() || null
})

// 获取选中的品牌对象
const selectedBrand = computed(() => {
  if (!form.brand_id) return null
  if (typeof form.brand_id === 'object') {
    return form.brand_id
  }
  return brandSelectRef.value?.getSelectedBrand?.() || null
})

/* ===== 生命周期 ===== */
onMounted(async () => {
  try {
    if (isEdit.value) {
      await loadAggregate()
    }
    
    // 预加载分类和品牌数据
    setTimeout(() => {
      categorySelectRef.value?.reload?.()
      brandSelectRef.value?.refreshBrands?.()
    }, 300)
  } catch (error) {
    console.error('初始化错误:', error)
    showToast('初始化失败')
  }
})

/* ===== 数据加载 ===== */
async function loadAggregate() {
  try {
    const data = await getProductAggregate(route.params.id)
    Object.assign(form, data)
    
    // 等待DOM更新后刷新选择器显示
    nextTick(() => {
      if (form.category_id) {
        categorySelectRef.value?.reload?.()
        // 如果编辑时需要获取分类路径，可以在这里处理
        setTimeout(() => {
          console.log('当前分类显示文本:', categoryDisplayText.value)
        }, 500)
      }
      if (form.brand_id) {
        brandSelectRef.value?.refreshBrands?.()
      }
    })
  } catch (error) {
    console.error('加载商品详情失败:', error)
    showToast('加载商品详情失败')
  }
}

/* ===== 事件处理 ===== */
// 分类选择确认事件 - 更新显示文本
const onCategorySelectConfirm = (id, node) => {
  console.log('分类选择确认:', id, node)
  form.category_id = id
  formErrors.category_id = ''
  
  // 记录选择的分类路径信息
  if (node) {
    console.log('选择的分类节点:', node)
    console.log('完整路径:', categoryDisplayText.value)
  }
}

// 分类选择取消事件
const onCategorySelectCancel = () => {
  console.log('分类选择取消')
  // 可以忽略这个事件，因为点击遮罩层关闭时也会触发
}

// 分类选择变化事件
const onCategorySelectChange = (id) => {
  console.log('分类变化:', id)
  formErrors.category_id = ''
  
  if (!id) {
    console.log('已清空分类选择')
  }
}

// 品牌选择变化事件
const onBrandSelectChange = (brand) => {
  console.log('品牌选择变化:', brand)
  if (brand && typeof brand === 'object') {
    form.brand_id = brand
    console.log('选择的品牌:', brand.name, 'ID:', brand.id)
  } else if (brand === null) {
    form.brand_id = null
    console.log('已清空品牌选择')
  }
}

// 手动触发分类选择器打开
const openCategorySelect = () => {
  categorySelectRef.value?.open?.()
}

// 手动触发品牌选择器打开
const openBrandSelect = () => {
  brandSelectRef.value?.openPicker?.()
}

// 清空分类选择
const clearCategory = () => {
  form.category_id = ''
  formErrors.category_id = ''
  categorySelectRef.value?.clear?.()
}

// 清空品牌选择
const clearBrand = () => {
  form.brand_id = null
  brandSelectRef.value?.clear?.()
}

/* ===== 表单验证 ===== */
const validateForm = () => {
  const errors = []
  
  if (!form.name.trim()) {
    errors.push('请输入商品名称')
  }
  
  if (!form.unit.trim()) {
    errors.push('请输入单位')
  }
  
  if (!form.category_id) {
    errors.push('请选择商品分类')
    formErrors.category_id = '请选择商品分类'
  } else {
    formErrors.category_id = ''
  }
  
  return errors
}

/* ===== 表单提交 ===== */
const onSubmit = async () => {
  try {
    submitted.value = true
    
    // 验证表单
    const errors = validateForm()
    if (errors.length > 0) {
      showToast(errors[0])
      return
    }
    
    // 构建提交数据
    const payload = {
      name: form.name.trim(),
      unit: form.unit.trim(),
      category_id: form.category_id,
      brand_id: form.brand_id ? (typeof form.brand_id === 'object' ? form.brand_id.id : form.brand_id) : null,
      images: form.images || [],
      description: form.description?.trim() || ''
    }

    console.log('提交的分类ID:', payload.category_id)
    console.log('分类显示文本:', categoryDisplayText.value)

    // 调用 API
    let response
    if (isEdit.value) {
      payload.id = form.id
      response = await updateProductAggregate(payload)
    } else {
      response = await saveProductAggregate(payload)
    }

    // 调试：打印完整响应
    console.log('API完整响应:', response)
    
    // 根据实际API响应结构处理
    let responseData
    let productId
    
    // 情况1：直接返回数据对象
    if (response && response.id) {
      responseData = response
      productId = response.id
    }
    // 情况2：返回 {code, msg, data} 格式
    else if (response && response.code === 200 && response.data) {
      responseData = response.data
      productId = response.data.id
    }
    // 情况3：返回其他格式
    else if (response && typeof response === 'object') {
      // 尝试从各种可能的字段获取id
      productId = response.id || response.productId || response.product_id
      responseData = response
    }

    // 检查是否成功获取到productId
    if (productId) {
      showSuccessToast(isEdit.value ? '更新成功' : '创建成功')
      
      const idStr = String(productId)
      console.log('获取到productId:', idStr, 'isEdit:', isEdit.value)
      
      if (!isEdit.value) {
        // 新增商品后跳转到SKU创建页面
        console.log('跳转到SKU创建页面:', `/product/${idStr}/skus`)
        await router.replace(`/product/${idStr}/skus`)
      } else {
        // 编辑商品后跳转到SKU列表页面
        console.log('跳转到SKU列表页面:', `/product/${idStr}/skus`)
        await router.replace(`/product/${idStr}/skus`)
      }
    } else {
      // 如果没有productId，显示成功但跳转到商品列表
      console.warn('未获取到productId，完整响应:', response)
      showSuccessToast(isEdit.value ? '更新成功' : '创建成功')
      setTimeout(() => {
        router.replace('/product')
      }, 1500)
    }
    
  } catch (error) {
    console.error('表单提交错误:', error)
    showToast(error?.message || '提交失败，请重试')
  }
}
/* ===== 取消操作 ===== */
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

/* ===== 重置表单 ===== */
const resetForm = () => {
  Object.assign(form, {
    id: '',
    name: '',
    unit: '',
    category_id: '',
    brand_id: null,
    images: [],
    description: ''
  })
  Object.assign(formErrors, {
    category_id: ''
  })
  submitted.value = false
  
  // 重置选择器
  nextTick(() => {
    categorySelectRef.value?.clear?.()
    brandSelectRef.value?.clear?.()
  })
}
</script>

<style scoped lang="scss">
.product-aggregate-form {
  background: #f7f8fa;
  min-height: 100vh;
  padding-bottom: 20px;
}

.form-wrap {
  :deep(.van-cell-group__title) {
    padding-top: 16px;
    padding-bottom: 8px;
    font-weight: 500;
    color: #333;
  }
  
  :deep(.van-field__label) {
    font-weight: 500;
  }
}

/* 分类字段样式 */
.category-field {
  :deep(.van-field__control) {
    // 允许文本换行
    white-space: normal;
    word-break: break-all;
    min-height: 24px;
    line-height: 1.4;
  }
  
  .category-display {
    display: inline-block;
    width: 100%;
    color: #323233;
  }
}

/* 如果需要完全隐藏品牌选择器的默认按钮，可以添加这个样式 */
:deep(.default-trigger) {
  display: none !important;
}
</style>