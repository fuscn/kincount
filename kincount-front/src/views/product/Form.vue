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
        <van-field
          label="商品分类"
          readonly
          is-link
          :value="getCategoryName(form.category_id)"
          placeholder="请选择分类"
          @click="showCategoryPicker = true"
          :rules="[{ required: true, message: '请选择商品分类' }]"
        />
        
        <!-- 品牌选择 -->
        <van-field
          label="品牌"
          readonly
          is-link
          :value="getBrandName(form.brand_id)"
          placeholder="请选择品牌"
          @click="showBrandPicker = true"
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

    <!-- 分类选择器 -->
    <van-popup v-model:show="showCategoryPicker" position="bottom" round>
      <van-picker
        :columns="categoryColumns"
        @confirm="onCategoryConfirm"
        @cancel="showCategoryPicker = false"
        show-toolbar
        title="选择分类"
      />
    </van-popup>

    <!-- 品牌选择器 -->
    <van-popup v-model:show="showBrandPicker" position="bottom" round>
      <van-picker
        :columns="brandColumns"
        @confirm="onBrandConfirm"
        @cancel="showBrandPicker = false"
        show-toolbar
        title="选择品牌"
      />
    </van-popup>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showToast, showConfirmDialog, showSuccessToast } from 'vant'
import { getCategoryOptions, getBrandOptions } from '@/api/category'
import {
  getProductAggregate,
  saveProductAggregate,
  updateProductAggregate
} from '@/api/product'
import Upload from '@/components/common/Upload.vue'

/* ===== 基础数据 ===== */
const route = useRoute()
const router = useRouter()
const formRef = ref()
const isEdit = computed(() => !!route.params.id)

const form = reactive({
  id: '',
  name: '',
  unit: '',
  category_id: '',
  brand_id: '',
  images: [],
  description: ''
})

const showCategoryPicker = ref(false)
const showBrandPicker = ref(false)
const categoryColumns = ref([])
const brandColumns = ref([])

/* ===== 处理分类数据 ===== */
// 根据您提供的分类数据结构，处理为选择器选项
const processCategoryData = (categories) => {
  const result = []
  
  categories.forEach(category => {
    // 根据"--"前缀判断层级，顶级分类不能选择
    const isTopLevel = !category.label.startsWith('--')
    
    if (isTopLevel) {
      // 顶级分类，显示为不可用状态
      result.push({
        text: category.label,
        value: category.value,
        disabled: true
      })
    } else {
      // 子分类，去除"--"前缀并添加到选项
      const displayText = category.label.replace(/^--+/, '')
      result.push({
        text: displayText,
        value: category.value,
        disabled: false
      })
    }
  })
  
  return result
}

// 获取分类名称
const getCategoryName = (categoryId) => {
  if (!categoryId) return ''
  
  const category = categoryColumns.value.find(item => item.value === categoryId)
  return category ? category.text : ''
}

// 获取品牌名称
const getBrandName = (brandId) => {
  if (!brandId) return ''
  const brand = brandColumns.value.find(item => item.value === brandId)
  return brand ? brand.text : ''
}

/* ===== 加载选项 ===== */
onMounted(async () => {
  try {
    const [cat, brd] = await Promise.all([getCategoryOptions(), getBrandOptions()])
    
    console.log('原始分类数据:', cat)
    
    // 处理分类数据
    const processedCategories = processCategoryData(cat || [])
    
    console.log('处理后的分类数据:', processedCategories)
    
    // 添加"请选择"选项
    categoryColumns.value = [
      { text: '请选择分类', value: '', disabled: false },
      ...processedCategories
    ]
    
    // 处理品牌数据
    const brandData = brd || []
    brandColumns.value = [
      { text: '请选择品牌', value: '' },
      ...brandData.map(i => ({ 
        text: i.name || i.label || i.text, 
        value: i.id || i.value 
      }))
    ]
    
    console.log('品牌数据:', brandColumns.value)
    
    if (isEdit.value) await loadAggregate()
  } catch (error) {
    console.error('加载数据失败:', error)
    showToast('加载数据失败')
  }
})

async function loadAggregate() {
  try {
    const data = await getProductAggregate(route.params.id)
    Object.assign(form, data)
  } catch (error) {
    console.error('加载商品详情失败:', error)
    showToast('加载商品详情失败')
  }
}

/* ===== 选择器确认 ===== */
const onCategoryConfirm = (value) => {
  // 检查是否选择了被禁用的顶级分类
  const selectedOption = categoryColumns.value.find(item => item.value === value.value)
  if (selectedOption && selectedOption.disabled) {
    showToast('请选择子分类')
    return
  }
  
  form.category_id = value.value
  showCategoryPicker.value = false
}

const onBrandConfirm = (value) => {
  form.brand_id = value.value
  showBrandPicker.value = false
}

/* ===== 提交 ===== */
const onSubmit = async () => {
  try {
    // 使用 van-form 的 validate 方法
    await formRef.value.validate()
    
    // 验证分类是否选择（不能是空字符串）
    if (!form.category_id) {
      showToast('请选择商品分类')
      return
    }
    
    // 构建提交数据
    const payload = {
      name: form.name,
      unit: form.unit,
      category_id: form.category_id,
      brand_id: form.brand_id || undefined, // 如果为空则设为undefined
      images: form.images || [],
      description: form.description || ''
    }

    // 过滤掉 undefined 和 null
    Object.keys(payload).forEach(key => {
      if (payload[key] === undefined || payload[key] === null) {
        delete payload[key]
      }
    })

    console.log('提交数据:', payload)

    // 直接调用 API，拦截器会返回 data 部分
    let responseData
    if (isEdit.value) {
      payload.id = form.id
      responseData = await updateProductAggregate(payload)
    } else {
      responseData = await saveProductAggregate(payload)
    }

    console.log('API 响应数据:', responseData)
    
    showSuccessToast(isEdit.value ? '更新成功' : '创建成功')
    
    // 直接使用 responseData 中的 id 进行跳转
    if (responseData && responseData.id) {
      // 确保 id 是字符串格式
      const productId = String(responseData.id)
      
      // 新增商品后跳转到新增SKU页面
      if (!isEdit.value) {
        router.replace(`/product/${productId}/skus/create`)
      } else {
        // 编辑商品后跳转到SKU列表页
        router.replace(`/product/${productId}/skus`)
      }
    } else {
      console.warn('响应数据中没有找到 ID，跳转到列表页')
      router.replace('/product/list')
    }
    
  } catch (error) {
    console.error('提交失败:', error)
    
    // 如果是验证错误，不显示 toast，van-form 会自动处理
    if (error && error.length > 0) {
      return
    }
    
    // 特殊处理：如果错误消息包含成功信息，说明可能是拦截器问题
    if (error.message && (error.message.includes('成功') || error.message === '创建成功' || error.message === '更新成功')) {
      showSuccessToast(isEdit.value ? '更新成功' : '创建成功')
      
      // 尝试从错误对象中提取数据或使用默认跳转
      try {
        const responseData = error.response?.data || error.data
        if (responseData && responseData.id) {
          const productId = String(responseData.id)
          
          // 新增商品后跳转到新增SKU页面
          if (!isEdit.value) {
            router.replace(`/product/${productId}/skus/create`)
          } else {
            // 编辑商品后跳转到SKU列表页
            router.replace(`/product/${productId}/skus`)
          }
        } else {
          // 如果没有 id，等待后跳转到列表页
          setTimeout(() => {
            router.replace('/product/list')
          }, 1500)
        }
      } catch (e) {
        setTimeout(() => {
          router.replace('/product/list')
        }, 1500)
      }
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

/* 分类选择器样式 */
:deep(.van-picker-column__item--disabled) {
  color: #c8c9cc;
  cursor: not-allowed;
}
</style>