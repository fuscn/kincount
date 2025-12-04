<template>
  <!-- 悬浮气泡 -->
  <van-floating-bubble v-model:offset="offset" axis="xy" magnetic="x" :gap="0" teleport="body" @click="toggleMenu">
    <van-icon :name="isExpanded ? 'cross' : 'apps-o'" />
  </van-floating-bubble>

  <!-- 快捷菜单弹层 -->
  <van-popup v-model:show="isExpanded" position="bottom" round :style="{ height: '80%' }" teleport="body">
    <div class="floating-menu-popup">
      <!-- 头部 -->
      <div class="menu-header">
        <h3 class="menu-title">快捷操作</h3>
        <div class="header-actions">
          <van-icon name="replay" class="reset-icon" @click="resetPosition" title="重置位置" />
          <van-icon name="cross" class="close-icon" @click="closeMenu" />
        </div>
      </div>

      <!-- 搜索 -->
      <div class="menu-search">
        <van-search v-model="searchKeyword" placeholder="搜索操作..." shape="round" background="transparent"
          @clear="clearSearch" />
      </div>

      <!-- 分类树 -->
      <div class="menu-content">
        <van-tree-select v-model:main-active-index="activeCategoryIndex" :items="menuTreeData" height="100%">
          <template #content>
            <div class="category-content">
              <div v-for="item in currentCategoryItems" :key="item.id" class="menu-item"
                :class="{ disabled: !hasPermission(item.perm) }" @click="handleMenuItemClick(item)">


                <div class="item-icon">
                  <van-icon :name="item.icon" />
                </div>
                <div class="item-content">
                  <div class="item-title">{{ item.name }}</div>
                  <div v-if="item.description" class="item-desc">
                    {{ item.description }}
                  </div>
                </div>
                <van-icon name="arrow" class="item-arrow" />
              </div>

              <van-empty v-if="currentCategoryItems.length === 0" description="暂无操作项" image="search" />
            </div>
          </template>
        </van-tree-select>
      </div>

      <!-- 最近使用 -->
      <div v-if="recentItems.length > 0" class="recent-section">

        <div class="section-title">最近使用</div>

        <div class="recent-items">
          <van-tag type="primary" size="medium" @click="goHome">
            <van-icon name="home-o" />
            返回首页
          </van-tag>
          <van-tag v-for="item in recentItems" :key="item.id" type="primary" size="medium"
            @click="handleMenuItemClick(item)">
            <van-icon :name="item.icon" class="recent-icon" />
            {{ item.name }}
          </van-tag>
        </div>
      </div>
    </div>
  </van-popup>
</template>

<script setup>
/* ===== 脚本逻辑与之前一致，仅 menuData 补全 ===== */
import { ref, reactive, computed, onMounted, watch, nextTick, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/store/modules/auth'
import { PERM } from '@/constants/permissions'
import { showToast } from 'vant'

const router = useRouter()
const authStore = useAuthStore()

/* -------------------- 数据 -------------------- */
const offset = ref({ x: 100, y: 400 })
const isExpanded = ref(false)
const searchKeyword = ref('')
const activeCategoryIndex = ref(0)
const recentItems = ref([])

const goHome = () => {
  router.push('/dashboard')
  closeMenu()
}

/* -------------------- 完整菜单 -------------------- */
const menuData = [
  /* ===== 商品管理 ===== */
  {
    id: 'product',
    name: '商品管理',
    icon: 'apps-o',
    items: [
      {
        id: 'product-create',
        name: '新增商品',
        icon: 'add-o',
        path: '/product/create',
        perm: PERM.PRODUCT_ADD,
        description: '添加新商品到库存'
      },
      { id: 'product-list', name: '商品列表', icon: 'photo-o', path: '/product', perm: PERM.PRODUCT_VIEW },
      { id: 'category', name: '分类管理', icon: 'label-o', path: '/category', perm: PERM.CATEGORY_VIEW },
      { id: 'brand', name: '品牌管理', icon: 'star-o', path: '/brand', perm: PERM.BRAND_VIEW },
    ]
  },
  /* ===== 采购管理 ===== */
  {
    id: 'purchase',
    name: '采购管理',
    icon: 'cart-o',
    items: [
      { id: 'po-create', name: '新增采购订单', icon: 'add-o', path: '/purchase/order/create', perm: PERM.PURCHASE_ADD },
      { id: 'po-list', name: '采购订单', icon: 'orders-o', path: '/purchase/order', perm: PERM.PURCHASE_VIEW },
      { id: 'pi-list', name: '采购入库', icon: 'logistics', path: '/purchase/stock', perm: PERM.PURCHASE_VIEW }
    ]
  },
  /* ===== 销售管理 ===== */
  {
    id: 'sale',
    name: '销售管理',
    icon: 'balance-o',
    items: [
      { id: 'so-create', name: '新增销售订单', icon: 'add-o', path: '/sale/order/create', perm: PERM.SALE_ADD },
      { id: 'so-list', name: '销售订单列表', icon: 'orders-o', path: '/sale/order', perm: PERM.SALE_VIEW },
      { id: 'so-stock', name: '销售出库列表', icon: 'logistics', path: '/sale/stock', perm: PERM.SALE_VIEW },
      { id: 'so-return', name: '销售退货列表', icon: 'refund-o', path: '/sale/return', perm: PERM.SALE_VIEW }
    ]
  },
  /* ===== 库存管理 ===== */
  {
    id: 'stock',
    name: '库存管理',
    icon: 'bag-o',
    items: [
      { id: 'stock-list', name: '库存查询', icon: 'search', path: '/stock', perm: PERM.STOCK_VIEW },
      { id: 'stock-take', name: '库存盘点', icon: 'todo-list-o', path: '/stock/take', perm: PERM.STOCK_TAKE },
      { id: 'stock-transfer', name: '库存调拨', icon: 'exchange', path: '/stock/transfer', perm: PERM.STOCK_TRANSFER },
      { id: 'stock-warning', name: '库存预警', icon: 'warning-o', path: '/stock/warning', perm: PERM.STOCK_WARNING }
    ]
  },
  /* ===== 账款管理 ===== */
  {
    id: 'account',
    name: '账款管理',
    icon: 'balance-o',
    items: [
      { id: 'ar-create', name: '新增应收', icon: 'add-o', path: '/account/receivable/create', perm: PERM.FINANCE_ADD },
      { id: 'ar-list', name: '应收款项', icon: 'cash-back', path: '/account/receivable', perm: PERM.FINANCE_VIEW },
      { id: 'ap-create', name: '新增应付', icon: 'add-o', path: '/account/payable/create', perm: PERM.FINANCE_ADD },
      { id: 'ap-list', name: '应付款项', icon: 'cash-on-deliver', path: '/account/payable', perm: PERM.FINANCE_VIEW }
    ]
  },
  /* ===== 财务管理 ===== */
  {
    id: 'financial',
    name: '财务管理',
    icon: 'balance-list-o',
    items: [
      { id: 'fi-record', name: '收支记录', icon: 'records-o', path: '/financial/record', perm: PERM.FINANCE_VIEW },
      { id: 'fi-create', name: '新增收支', icon: 'add-o', path: '/financial/record/create', perm: PERM.FINANCE_ADD },
      { id: 'fi-dashboard', name: '财务概览', icon: 'chart-trending-o', path: '/financial/dashboard', perm: PERM.FINANCE_VIEW },
      { id: 'fi-profit', name: '利润报表', icon: 'chart-pie-o', path: '/financial/report/profit', perm: PERM.FINANCE_REPORT },
      { id: 'fi-cashflow', name: '资金流水', icon: 'bill-o', path: '/financial/report/cashflow', perm: PERM.FINANCE_REPORT }
    ]
  },
  /* ===== 系统管理 ===== */
  {
    id: 'system',
    name: '系统管理',
    icon: 'setting-o',
    items: [
      { id: 'sys-user', name: '用户管理', icon: 'friends-o', path: '/system/user', perm: PERM.USER_MANAGE },
      { id: 'sys-role', name: '角色权限', icon: 'manager-o', path: '/system/role', perm: PERM.ROLE_MANAGE },
      { id: 'sys-config', name: '系统配置', icon: 'setting-o', path: '/system/config', perm: PERM.CONFIG_MANAGE }
    ]
  },
  /* ===== 基础资料 ===== */
  {
    id: 'base',
    name: '基础资料',
    icon: 'bookmark-o',
    items: [
      { id: 'customer', name: '客户管理', icon: 'friends-o', path: '/customer', perm: PERM.CUSTOMER_VIEW },
      { id: 'supplier', name: '供应商管理', icon: 'shop-o', path: '/supplier', perm: PERM.SUPPLIER_VIEW },
      { id: 'warehouse', name: '仓库管理', icon: 'home-o', path: '/warehouse', perm: PERM.WAREHOUSE_VIEW }
    ]
  },
  /* ===== 个人中心 ===== */
  {
    id: 'profile',
    name: '个人中心',
    icon: 'user-o',
    items: [
      {
        id: 'profile-info',
        name: '我的资料',
        icon: 'contact',
        path: '/system/user/profile',   // 复用已有路由或新建
        perm: null                      // 登录即可见
      },
      {
        id: 'profile-password',
        name: '修改密码',
        icon: 'lock',
        path: '/system/user/password',  // 你已有该路由
        perm: null
      }
    ]
  }
]

/* -------------------- 计算属性 & 方法 -------------------- */
const menuTreeData = computed(() =>
  menuData.map(g => ({ text: g.name }))
)

const currentCategoryItems = computed(() => {
  const items = menuData[activeCategoryIndex.value]?.items || []
  let list = items.filter(it => hasPermission(it.perm))

  if (searchKeyword.value) {
    const kw = searchKeyword.value.toLowerCase()
    list = list.filter(it =>
      it.name.toLowerCase().includes(kw) ||
      (it.description && it.description.toLowerCase().includes(kw))
    )
  }
  return list
})

const hasPermission = p => authStore.hasPerm(p)

const toggleMenu = () => {
  isExpanded.value = !isExpanded.value
  if (isExpanded.value) loadRecentItems()
}

const closeMenu = () => {
  isExpanded.value = false
  searchKeyword.value = ''
}

const resetPosition = () => {
  offset.value = { x: 100, y: window.innerHeight - 100 }
  savePosition()
  showToast('位置已重置')
}

const clearSearch = () => (searchKeyword.value = '')

const handleMenuItemClick = item => {
  if (!hasPermission(item.perm)) {
    showToast('没有操作权限')
    return
  }
  addToRecentItems(item)
  router.push(item.path)
  closeMenu()
}

const addToRecentItems = item => {
  recentItems.value = [
    item,
    ...recentItems.value.filter(i => i.id !== item.id)
  ].slice(0, 5)
  localStorage.setItem('recentMenuItems', JSON.stringify(recentItems.value))
}

const loadRecentItems = () => {
  try {
    const raw = localStorage.getItem('recentMenuItems')
    if (raw) recentItems.value = JSON.parse(raw)
  } catch { }
}

const savePosition = () =>
  localStorage.setItem('floatingMenuPosition', JSON.stringify(offset.value))

/* -------------------- 生命周期 -------------------- */
onMounted(() => {
  loadRecentItems()
  try {
    const raw = localStorage.getItem('floatingMenuPosition')
    if (raw) offset.value = JSON.parse(raw)
  } catch { }
  window.addEventListener('resize', () => {
    if (offset.value.y > window.innerHeight - 150)
      offset.value = { ...offset.value, y: window.innerHeight - 100 }
    savePosition()
  })
})

onUnmounted(() => {
  window.removeEventListener('resize', () => { })
})
</script>

<style scoped lang="scss">
/* 与旧文件样式 100% 兼容，无需改动 */
.floating-menu-popup {
  height: 100%;
  display: flex;
  flex-direction: column;
  background: #f7f8fa;
}

.menu-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #f0f0f0;
  background: white;
  flex-shrink: 0;
}

.menu-title {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  color: #323233;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.reset-icon,
.close-icon {
  font-size: 18px;
  color: #969799;
  cursor: pointer;
  padding: 4px;

  &:active {
    background: #f7f8fa;
    border-radius: 4px;
  }
}

.menu-search {
  padding: 12px 16px;
  background: white;
  border-bottom: 1px solid #f7f8fa;
  flex-shrink: 0;
}

.menu-content {
  flex: 1;
  overflow: hidden;
  background: white;
}

.category-content {
  padding: 16px;
  height: 100%;
  overflow-y: auto;
}

.menu-item {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  margin-bottom: 8px;
  background: #fafafa;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;

  &:active:not(.disabled) {
    background: #f0f0f0;
    transform: scale(0.98);
  }

  &.disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
}

.item-icon {
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 12px;
  flex-shrink: 0;

  .van-icon {
    font-size: 16px;
    color: #1989fa;
  }
}

.item-content {
  flex: 1;
  min-width: 0;
}

.item-title {
  font-size: 14px;
  font-weight: 500;
  color: #323233;
  margin-bottom: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.item-desc {
  font-size: 12px;
  color: #969799;
  line-height: 1.3;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.item-arrow {
  font-size: 12px;
  color: #c8c9cc;
  margin-left: 8px;
  flex-shrink: 0;
}

.recent-section {
  padding: 16px;
  border-top: 1px solid #f0f0f0;
  background: #f8f9fa;
  flex-shrink: 0;
}

.section-title {
  font-size: 14px;
  font-weight: 500;
  color: #646566;
  margin-bottom: 8px;
}

.recent-items {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.recent-icon {
  margin-right: 4px;
  font-size: 12px;
}

/* 滚动条美化 */
.category-content::-webkit-scrollbar {
  width: 4px;
}

.category-content::-webkit-scrollbar-thumb {
  background: #c8c9cc;
  border-radius: 2px;
}

/* 响应式 */
@media (max-width: 768px) {
  .menu-header {
    padding: 14px 16px;
  }

  .menu-search {
    padding: 10px 12px;
  }

  .category-content {
    padding: 12px;
  }

  .menu-item {
    padding: 10px 12px;
    margin-bottom: 6px;
  }
}
</style>