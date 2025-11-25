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
        <!-- 分类选择 - 使用 Cascader -->
        <van-field
          name="category_id"
          label="商品分类"
          readonly
          is-link
          :model-value="getCategoryFullPath(form.category_id)"
          placeholder="请选择分类"
          @click="showCategoryCascader = true"
          :rules="[{ required: true, message: '请选择商品分类' }]"
        />
        
        <!-- 品牌选择 -->
        <van-field
          name="brand_id"
          label="品牌"
          readonly
          is-link
          :model-value="getBrandName(form.brand_id)"
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

    <!-- 分类级联选择器 -->
    <van-popup v-model:show="showCategoryCascader" position="bottom" round class="category-cascader-popup">
      <van-cascader
        v-model="categoryCascaderValue"
        :options="categoryOptions"
        :field-names="categoryFieldNames"
        :closeable="true"
        active-color="#1989fa"
        @close="showCategoryCascader = false"
        @finish="onCategoryFinish"
        title="选择分类"
      />
    </van-popup>

    <!-- 品牌选择器 - 使用 ActionSheet 实现点击即选择 -->
    <van-action-sheet 
      v-model:show="showBrandPicker" 
      title="选择品牌"
      :actions="brandActions"
      @select="onBrandSelect"
      cancel-text="取消"
      close-on-click-action
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showToast, showConfirmDialog, showSuccessToast } from 'vant'
import { getCategoryList } from '@/api/category'
import { getBrandOptions } from '@/api/brand'
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

const showCategoryCascader = ref(false)
const showBrandPicker = ref(false)
const categoryCascaderValue = ref('')
const categoryOptions = ref([])
const brandActions = ref([])

// 存储完整的分类数据用于显示名称
const allCategories = ref([])

// Cascader 字段映射
const categoryFieldNames = {
  text: 'name',
  value: 'id',
  children: 'children'
}

/* ===== 处理分类数据 ===== */
// 转换分类数据为 Cascader 格式
const processCategoryData = (categories) => {
  const result = []
  
  categories.forEach(category => {
    const categoryItem = {
      name: category.name,
      id: category.id
    }
    
    // 如果有子分类，递归处理
    if (category.children && category.children.length > 0) {
      categoryItem.children = processCategoryData(category.children)
    } else {
      // 如果没有子分类，检查是否是顶级分类
      // 根据您的数据结构，顶级分类的 parent_id 为 0
      if (category.parent_id === 0) {
        // 顶级分类且没有子分类，设置为禁用
        categoryItem.disabled = true
      }
    }
    
    result.push(categoryItem)
  })
  
  return result
}

// 获取分类完整路径（父类->子类）
const getCategoryFullPath = (categoryId) => {
  if (!categoryId) return ''
  
  // 递归查找分类路径
  const findCategoryPath = (categories, targetId, path = []) => {
    for (const category of categories) {
      const currentPath = [...path, category.name]
      
      if (category.id === targetId) {
        return currentPath
      }
      
      if (category.children && category.children.length > 0) {
        const found = findCategoryPath(category.children, targetId, currentPath)
        if (found) return found
      }
    }
    return null
  }
  
  const path = findCategoryPath(allCategories.value, categoryId)
  const fullPath = path ? path.join(' -> ') : ''
  return fullPath
}

// 获取品牌名称
const getBrandName = (brandId) => {
  if (!brandId) return ''
  const brand = brandActions.value.find(item => item.value === brandId)
  const name = brand ? brand.name : ''
  return name
}

/* ===== 加载选项 ===== */
onMounted(async () => {
  try {
    // 加载分类数据
    const categoryResponse = await getCategoryList()
    const categories = categoryResponse.data || categoryResponse
    
    
    // 保存完整的分类数据用于显示名称
    allCategories.value = JSON.parse(JSON.stringify(categories))
    
    // 处理分类数据
    categoryOptions.value = processCategoryData(categories)
    
    
    // 加载品牌数据 - 使用新的接口
    const brandResponse = await getBrandOptions()
    const brands = brandResponse.data || brandResponse || []
    
    
    // 处理品牌数据为 ActionSheet 格式
    brandActions.value = [
      ...brands.map(brand => ({ 
        name: brand.name, 
        value: brand.id 
      }))
    ]
    
    
    if (isEdit.value) await loadAggregate()
  } catch (error) {
    showToast('加载数据失败')
  }
})

async function loadAggregate() {
  try {
    const data = await getProductAggregate(route.params.id)
    Object.assign(form, data)
    
    
    // 如果编辑模式下有分类ID，设置级联选择器的值
    if (form.category_id) {
      categoryCascaderValue.value = form.category_id
    }
  } catch (error) {
    showToast('加载商品详情失败')
  }
}

/* ===== 选择器确认 ===== */
const onCategoryFinish = ({ selectedOptions }) => {
  
  if (selectedOptions.length > 0) {
    const lastOption = selectedOptions[selectedOptions.length - 1]
    
    // 检查是否选择了禁用的分类
    if (lastOption.disabled) {
      showToast('该分类不可选择，请选择其他分类')
      return
    }
    
    // 只提交最后一级子类的ID
    form.category_id = lastOption.id
    showCategoryCascader.value = false
    
    
    // 强制更新视图
    setTimeout(() => {
      if (formRef.value) {
        formRef.value.validate('category_id')
      }
    }, 100)
  }
}

// 品牌选择 - 使用 ActionSheet，点击即选择
const onBrandSelect = (brand) => {
  
  form.brand_id = brand.value
  showBrandPicker.value = false
  
  
  // 强制更新视图
  setTimeout(() => {
    // 触发重新渲染
    form.brand_id = form.brand_id
  }, 0)
}

/* ===== 表单提交 ===== */
const onSubmit = async () => {
  try {
    // 使用 van-form 的 validate 方法
    await formRef.value.validate()
    
    // 验证分类是否选择（不能是空字符串）
    if (!form.category_id) {
      showToast('请选择商品分类')
      return
    }
    
    // 构建提交数据 - 只提交子类ID
    const payload = {
      name: form.name,
      unit: form.unit,
      category_id: form.category_id, // 这里只提交最后一级子类的ID
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
        router.replace(`/product/${productId}/skus`)
        // router.replace(`/product/${productId}/skus/create`)
      } else {
        router.replace(`/product/${productId}/skus`)
      }
    } else {
      router.replace('/product/list')
    }
    
  } catch (error) {
    
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
          
          if (!isEdit.value) {
            router.replace(`/product/${productId}/skus/create`)
          } else {
            router.replace(`/product/${productId}/skus`)
          }
        } else {
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

/* 分类级联选择器样式 */
:deep(.category-cascader-popup) {
  height: 50%;
}

/* 禁用分类项的样式 */
:deep(.category-cascader-popup .van-cascader__option--disabled) {
  color: #c8c9cc !important;
  cursor: not-allowed;
}
</style>