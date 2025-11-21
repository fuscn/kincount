<template>
  <div class="category-form-page">
    <van-nav-bar
      :title="isEdit ? '编辑分类' : '新增分类'"
      left-text="取消"
      right-text="保存"
      left-arrow
      @click-left="handleCancel"
      @click-right="handleSubmit"
    />

    <van-form ref="formRef" @submit="handleSubmit" class="form-container">
      <!-- 基础信息 -->
      <van-cell-group title="基础信息">
        <van-field
          v-model="form.name"
          label="分类名称"
          placeholder="请输入分类名称"
          maxlength="20"
          show-word-limit
          required
          :rules="[{ required: true, message: '请输入分类名称' }]"
        />
        
        <!-- 使用 TreeSelectCell 组件 -->
        <TreeSelectCell
          v-model="form.parent_id"
          :data="categoryTree"
          title="父级分类"
          placeholder="顶级分类"
          popup-title="选择父级分类"
          :field-names="{ key: 'id', title: 'name', children: 'children' }"
          :close-on-select="true"
          :show-divider="true"
          @change="onParentChange"
        />
        
        <van-field
          v-model.number="form.sort"
          label="排序"
          type="digit"
          placeholder="数字越小越靠前"
          required
          :rules="[{ required: true, message: '请输入排序' }]"
        >
          <template #extra>升序</template>
        </van-field>
      </van-cell-group>

      <!-- 状态 -->
      <van-cell-group title="状态设置">
        <van-cell center title="启用分类">
          <template #right-icon>
            <van-switch v-model="form.status" :active-value="1" :inactive-value="0" />
          </template>
        </van-cell>
      </van-cell-group>

      <!-- 描述 -->
      <van-cell-group title="描述信息">
        <van-field
          v-model="form.description"
          label="分类描述"
          type="textarea"
          rows="3"
          autosize
          maxlength="200"
          show-word-limit
          placeholder="请输入分类描述（选填）"
        />
      </van-cell-group>

      <!-- 预览 -->
      <van-cell-group title="预览信息" v-if="isEdit">
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
import { getCategoryList, getCategoryDetail, addCategory, updateCategory } from '@/api/category'
import TreeSelectCell from '@/components/common/TreeSelectCell.vue'
import dayjs from 'dayjs'

const route = useRoute()
const router = useRouter()
const formRef = ref()

const isEdit = computed(() => !!route.params.id)
const loading = ref(false)

// 表单数据
const form = reactive({
  id: '',
  name: '',
  parent_id: 0,
  sort: 99,
  description: '',
  status: 1,
  created_at: '',
  updated_at: ''
})

// 分类树数据
const categoryTree = ref([])

// 日期格式化
const formatDate = (d) => (d ? dayjs(d).format('YYYY-MM-DD HH:mm') : '')

// 加载分类详情
const loadDetail = async () => {
  if (!isEdit.value) return
  loading.value = true
  try {
    const res = await getCategoryDetail(route.params.id)
    const data = res.data || res
    Object.assign(form, {
      id: data.id,
      name: data.name,
      parent_id: data.parent_id || 0,
      sort: data.sort,
      description: data.description || '',
      status: data.status,
      created_at: data.created_at,
      updated_at: data.updated_at
    })
  } catch (error) {
    console.error('加载详情失败:', error)
    showToast('加载详情失败')
    router.back()
  } finally {
    loading.value = false
  }
}

// 加载分类树
const loadCategoryTree = async () => {
  try {
    const res = await getCategoryList()
    categoryTree.value = res.data || res
  } catch (error) {
    console.error('加载分类树失败:', error)
    showToast('加载分类树失败')
  }
}

// 父级分类变更处理
const onParentChange = (data) => {
  console.log('父级分类已选择:', data)
  if (data.key === form.id) {
    showToast('不能选择自己作为父级分类')
    form.parent_id = 0
  }
}

// 取消操作
const handleCancel = () => {
  showConfirmDialog({
    title: '确认取消',
    message: '未保存的内容将丢失，是否继续？'
  }).then(() => {
    router.back()
  }).catch(() => {
    // 用户取消操作
  })
}

// 提交表单
const handleSubmit = async () => {
  try {
    await formRef.value.validate()
    
    if (loading.value) return
    loading.value = true
    
    const payload = {
      name: form.name.trim(),
      parent_id: form.parent_id,
      sort: Number(form.sort),
      description: form.description.trim(),
      status: form.status
    }
    
    if (isEdit.value) {
      await updateCategory(form.id, payload)
      showSuccessToast('分类更新成功')
    } else {
      await addCategory(payload)
      showSuccessToast('分类创建成功')
    }
    
    router.replace('/category')
  } catch (error) {
    console.error('保存失败:', error)
    if (error?.name !== 'ValidateError') {
      showToast(error?.message || '保存失败，请重试')
    }
  } finally {
    loading.value = false
  }
}

// 初始化加载
onMounted(() => {
  loadCategoryTree()
  loadDetail()
})
</script>

<style lang="scss" scoped>
.category-form-page {
  min-height: 100vh;
  background: #f7f8fa;
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

// 表单验证错误样式
:deep(.van-field--error) {
  .van-field__control {
    color: #ee0a24;
  }
}
</style>