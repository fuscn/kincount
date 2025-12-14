<template>
  <div class="category-index-page">
    <van-nav-bar title="分类管理" fixed placeholder>
      <template #right>
        <van-button size="small" type="primary" @click="handleAdd" v-perm="PERM.CATEGORY_ADD">
          新增
        </van-button>
      </template>
    </van-nav-bar>

    <!-- 搜索 -->
    <SearchBar placeholder="搜索分类名称" @search="handleSearch" @clear="handleClearSearch" />


    <!-- 树形列表 -->
    <van-pull-refresh v-model="refreshing" @refresh="onRefresh">
      <div class="tree-container">
        <!-- 使用递归组件渲染树 -->
        <CategoryTreeItem
          v-for="node in treeData"
          :key="node.id"
          :node="node"
          @edit="handleEdit"
          @delete="handleDelete"
          @toggle-status="handleToggleStatus"
        />

        <van-empty 
          v-if="!isLoading && !refreshing && !treeData.length" 
          description="暂无分类数据" 
        />
      </div>
    </van-pull-refresh>

    <!-- 加载指示器 -->
    <van-loading v-if="isLoading && !refreshing" class="page-loading" />
  </div>
</template>

<script setup>
/* -------------------- 依赖 -------------------- */
import { ref, reactive, onMounted, computed, defineComponent, h } from 'vue'
import { useRouter } from 'vue-router'
import { 
  showToast, 
  showConfirmDialog, 
  showSuccessToast,
  SwipeCell,
  Cell,
  Switch,
  Button,

} from 'vant'
import { useCategoryStore } from '@/store/modules/category'
import { PERM } from '@/constants/permissions'
import SearchBar from '@/components/common/SearchBar.vue'

/* -------------------- 响应式 -------------------- */
const router = useRouter()
const categoryStore = useCategoryStore()

const treeData = ref([])
const refreshing = ref(false)
const isLoading = ref(false)

// 使用计算属性计算统计信息
const stats = computed(() => {
  let enable = 0
  let total = 0
  
  const walk = (arr) => {
    arr.forEach(node => {
      total++
      if (node.status === 1) enable++
      if (node.children && node.children.length) {
        walk(node.children)
      }
    })
  }
  
  walk(treeData.value)
  
  return {
    total,
    enable,
    disable: total - enable
  }
})

// 值样式类
const valueClass = (type) => {
  return type === 'success' ? 'success-value' : 'danger-value'
}

/* -------------------- 内部递归组件定义 -------------------- */
// 定义CategoryTreeItem组件，使用defineComponent创建
const CategoryTreeItem = defineComponent({
  name: 'CategoryTreeItem',
  props: {
    node: {
      type: Object,
      required: true
    },
    level: {
      type: Number,
      default: 0
    }
  },
  emits: ['edit', 'delete', 'toggle-status'],
  setup(props, { emit }) {
    // 递归渲染子节点
    const renderChildren = () => {
      if (!props.node.children || props.node.children.length === 0) {
        return []
      }
      
      return props.node.children.map(child => 
        h(CategoryTreeItem, {
          key: child.id,
          node: child,
          level: props.level + 1,
          onEdit: (node) => emit('edit', node),
          onDelete: (node) => emit('delete', node),
          onToggleStatus: (node) => emit('toggle-status', node)
        })
      )
    }

    return () => {
      const children = renderChildren()
      return h('div', [
        // 主项
        h(SwipeCell, {
          class: 'van-swipe-cell-item'
        }, {
          default: () => h(Cell, {
            style: { paddingLeft: props.level * 20 + 16 + 'px' },
            title: props.node.name,
            label: `排序 ${props.node.sort} | ${props.node.description || '暂无描述'}`,
            onClick: () => emit('edit', props.node),
            class: { 'top-level': props.level === 0 }
          }, {
            title: () => h('span', {
              class: { 'bold-title': props.level === 0 }
            }, props.node.name),
            value: () => h(Switch, {
              modelValue: props.node.status === 1,
              size: "20",
              onClick: (e) => e.stopPropagation(),
              'onUpdate:modelValue': () => emit('toggle-status', props.node)
            })
          }),
          right: () => [
            h(Button, {
              square: true,
              type: "primary",
              text: "编辑",
              onClick: () => emit('edit', props.node),
            }),
            h(Button, {
              square: true,
              type: "danger",
              text: "删除",
              onClick: () => emit('delete', props.node)
            })
          ]
        }),
        
        // 递归渲染子节点
        ...children
      ])
    }
  }
})

/* -------------------- 加载树数据 -------------------- */
const loadTreeData = async (isRefresh = false) => {
  if (isLoading.value) return
  
  isLoading.value = true
  if (!isRefresh) {
    refreshing.value = false
  }
  
  try {
    // 使用 loadTree 方法获取树形数据
    const data = await categoryStore.loadTree(categoryStore.keyword)
    
    // 确保数据有层级信息
    const addLevelInfo = (nodes, level = 0) => {
      return nodes.map(node => ({
        ...node,
        _level: level,
        children: node.children ? addLevelInfo(node.children, level + 1) : []
      }))
    }
    
    treeData.value = addLevelInfo(data || [])
    
  } catch (error) {
    console.error('加载失败:', error)
    showToast('加载失败')
  } finally {
    isLoading.value = false
    refreshing.value = false
  }
}

/* -------------------- 刷新 -------------------- */
const onRefresh = () => {
  loadTreeData(true)
}

/* -------------------- 搜索 -------------------- */
const handleSearch = (kw) => {
  categoryStore.keyword = kw
  loadTreeData()
}

const handleClearSearch = () => {
  categoryStore.keyword = ''
  loadTreeData()
}

/* -------------------- 增删改操作 -------------------- */
const handleAdd = () => {
  router.push('/category/create')
}

const handleEdit = (row) => {
  router.push(`/category/edit/${row.id}`)
}

const handleToggleStatus = async (row) => {
  try {
    const newStatus = row.status === 1 ? 0 : 1
    await categoryStore.toggleStatus(row.id, newStatus)
    showSuccessToast('操作成功')
    
    // 更新本地数据
    row.status = newStatus
  } catch (error) {
    console.error('状态切换失败:', error)
    showToast('操作失败')
  }
}

const handleDelete = async (row) => {
  try {
    await showConfirmDialog({
      title: '确认删除',
      message: `确定删除分类「${row.name}」吗？`
    })
    
    await categoryStore.deleteRow(row.id)
    showSuccessToast('已删除')
    loadTreeData()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      showToast('删除失败')
    }
  }
}

/* -------------------- 生命周期 -------------------- */
onMounted(() => {
  loadTreeData()
})
</script>

<style lang="scss" scoped>
.category-index-page {
  min-height: 100vh;
  background: #f7f8fa;
  padding-bottom: env(safe-area-inset-bottom);
}

.stat-card {
  margin: 12px 16px 0;
  border-radius: 8px;
  overflow: hidden;
  
  :deep(.success-value) { 
    color: var(--van-success-color); 
  }
  
  :deep(.danger-value) { 
    color: var(--van-danger-color); 
  }
}

.tree-container {
  min-height: 200px;
}

.page-loading {
  display: flex;
  justify-content: center;
  padding: 20px;
}

// CategoryTreeItem组件的样式
:deep(.van-swipe-cell-item) {
  border-bottom: 1px solid #f5f5f5;
  
  .van-cell {
    align-items: center;
    
    .van-cell__title {
      flex: 1;
    }
    
    .van-cell__value {
      flex: 0 0 auto;
    }
    
    // 顶级分类加粗
    &.top-level {
      .bold-title {
        font-weight: bold;
        font-size: 16px;
      }
    }
  }
}
</style>