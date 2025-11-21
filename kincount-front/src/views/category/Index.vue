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

    <!-- 统计卡片 -->
    <van-cell-group class="stat-card" v-if="stats.total">
      <van-cell title="分类总数" :value="stats.total" />
      <van-cell title="启用数" :value="stats.enable" value-class="success" />
      <van-cell title="禁用数" :value="stats.disable" value-class="danger" />
    </van-cell-group>

    <!-- 树形列表 -->
    <van-pull-refresh v-model="refreshing" @refresh="loadTree(true)">
      <van-list
        v-model:loading="listLoading"
        :finished="finished"
        finished-text="没有更多了"
        @load="loadTree"
        :immediate-check="false"
      >
        <!-- 直接使用模板递归渲染 -->
        <div v-for="node in treeData" :key="node.id">
          <van-swipe-cell>
            <van-cell
              :style="{ paddingLeft: (node._level || 0) * 20 + 16 + 'px' }"
              :title="node.name"
              :label="`排序 ${node.sort} | ${node.description || '暂无描述'}`"
              @click="handleEdit(node)"
              :class="{ 'top-level': node._level === 0 }"
            >
              <template #title>
                <span :class="{ 'bold-title': node._level === 0 }">{{ node.name }}</span>
              </template>
              <template #value>
                <van-switch
                  :model-value="node.status === 1"
                  size="20"
                  @click.stop
                  @update:model-value="handleToggleStatus(node)"
                />
              </template>
            </van-cell>
            
            <template #right>
              <van-button
                square
                type="primary"
                text="编辑"
                @click="handleEdit(node)"
                v-perm="PERM.CATEGORY_EDIT"
              />
              <van-button
                square
                type="danger"
                text="删除"
                @click="handleDelete(node)"
                v-perm="PERM.CATEGORY_DELETE"
              />
            </template>
          </van-swipe-cell>

          <!-- 递归渲染子节点 -->
          <template v-if="node.children && node.children.length">
            <div v-for="child in node.children" :key="child.id">
              <van-swipe-cell>
                <van-cell
                  :style="{ paddingLeft: (child._level || 1) * 20 + 16 + 'px' }"
                  :title="child.name"
                  :label="`排序 ${child.sort} | ${child.description || '暂无描述'}`"
                  @click="handleEdit(child)"
                >
                  <template #value>
                    <van-switch
                      :model-value="child.status === 1"
                      size="20"
                      @click.stop
                      @update:model-value="handleToggleStatus(child)"
                    />
                  </template>
                </van-cell>
                
                <template #right>
                  <van-button
                    square
                    type="primary"
                    text="编辑"
                    @click="handleEdit(child)"
                    v-perm="PERM.CATEGORY_EDIT"
                  />
                  <van-button
                    square
                    type="danger"
                    text="删除"
                    @click="handleDelete(child)"
                    v-perm="PERM.CATEGORY_DELETE"
                  />
                </template>
              </van-swipe-cell>

              <!-- 第二级子节点 -->
              <template v-if="child.children && child.children.length">
                <div v-for="grandChild in child.children" :key="grandChild.id">
                  <van-swipe-cell>
                    <van-cell
                      :style="{ paddingLeft: (grandChild._level || 2) * 20 + 16 + 'px' }"
                      :title="grandChild.name"
                      :label="`排序 ${grandChild.sort} | ${grandChild.description || '暂无描述'}`"
                      @click="handleEdit(grandChild)"
                    >
                      <template #value>
                        <van-switch
                          :model-value="grandChild.status === 1"
                          size="20"
                          @click.stop
                          @update:model-value="handleToggleStatus(grandChild)"
                        />
                      </template>
                    </van-cell>
                    
                    <template #right>
                      <van-button
                        square
                        type="primary"
                        text="编辑"
                        @click="handleEdit(grandChild)"
                        v-perm="PERM.CATEGORY_EDIT"
                      />
                      <van-button
                        square
                        type="danger"
                        text="删除"
                        @click="handleDelete(grandChild)"
                        v-perm="PERM.CATEGORY_DELETE"
                      />
                    </template>
                  </van-swipe-cell>
                </div>
              </template>
            </div>
          </template>
        </div>

        <van-empty v-if="!listLoading && !refreshing && !treeData.length" description="暂无分类数据" />
      </van-list>
    </van-pull-refresh>
  </div>
</template>

<script setup>
/* -------------------- 依赖 -------------------- */
import { ref, reactive, onMounted, onActivated } from 'vue'
import { useRouter } from 'vue-router'
import { showToast, showConfirmDialog, showSuccessToast } from 'vant'
import { useCategoryStore } from '@/store/modules/category'
import { PERM } from '@/constants/permissions'
import SearchBar from '@/components/common/SearchBar.vue'

/* -------------------- 响应式 -------------------- */
const router = useRouter()
const categoryStore = useCategoryStore()

const treeData = ref([])
const refreshing = ref(false)
const listLoading = ref(false)
const finished = ref(false)
const pager = reactive({ page: 1, pageSize: 200 })
const stats = reactive({ total: 0, enable: 0, disable: 0 })
const isLoading = ref(false) // 防止重复请求

/* -------------------- 加载树数据 -------------------- */
const loadTree = async (reload = false) => {
  // 防止重复请求
  if (isLoading.value) {
    console.log('请求正在进行中，跳过重复请求')
    return
  }
  
  console.log('开始加载树数据...', '重载:', reload)
  
  if (reload) {
    pager.page = 1
    finished.value = false
    refreshing.value = true
    // 清空现有数据
    treeData.value = []
  } else {
    // 如果不是重载且已经完成，则不再加载
    if (finished.value) {
      listLoading.value = false
      return
    }
    listLoading.value = true
  }

  isLoading.value = true

  try {
    const res = await categoryStore.loadList({ page: pager.page, limit: pager.pageSize })
    console.log('API返回数据:', res)
    
    // 处理返回数据
    let roots = []
    if (Array.isArray(res)) {
      roots = res
    } else if (res && res.data) {
      roots = res.data
    } else if (res && res.list) {
      roots = res.list
    }
    
    console.log('处理后的树数据:', roots)
    
    // 添加层级信息
    const addLevel = (nodes, level = 0) => {
      nodes.forEach(node => {
        node._level = level
        if (node.children && node.children.length) {
          addLevel(node.children, level + 1)
        }
      })
    }
    
    addLevel(roots)
    
    if (reload) {
      treeData.value = roots
    } else {
      treeData.value.push(...roots)
    }

    pager.page += 1
    finished.value = true

    // 计算统计信息
    calculateStats()
    
  } catch (error) {
    console.error('加载失败:', error)
    showToast('加载失败')
    finished.value = true
  } finally {
    refreshing.value = false
    listLoading.value = false
    isLoading.value = false
  }
}

/* -------------------- 计算统计信息 -------------------- */
const calculateStats = () => {
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
  
  stats.total = total
  stats.enable = enable
  stats.disable = total - enable
  
  console.log('统计信息:', stats)
}

/* -------------------- 搜索 -------------------- */
const handleSearch = (kw) => {
  categoryStore.keyword = kw
  loadTree(true)
}

const handleClearSearch = () => {
  categoryStore.keyword = ''
  loadTree(true)
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
    calculateStats()
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
    loadTree(true)
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      showToast('删除失败')
    }
  }
}

/* -------------------- 生命周期 -------------------- */
onMounted(() => {
  console.log('分类管理页面已挂载')
  // 清空数据并重新加载
  treeData.value = []
  loadTree(true)
})

// 使用 onActivated 处理从编辑页面返回的情况
onActivated(() => {
  console.log('页面激活')
  // 如果是从编辑页面返回，可能需要刷新数据
  // 这里可以根据实际需求决定是否需要刷新
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
  
  :deep(.van-cell__value) {
    &.success { 
      color: var(--van-success-color); 
    }
    &.danger { 
      color: var(--van-danger-color); 
    }
  }
}

:deep(.van-swipe-cell) {
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