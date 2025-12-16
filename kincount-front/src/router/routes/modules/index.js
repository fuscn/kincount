import dashboard from './dashboard'
import product from './product'
import category from './category'
import brand from './brand'
import customer from './customer'
import supplier from './supplier'
import warehouse from './warehouse'
import purchase from './purchase'
import sale from './sale'
import stock from './stock'
import financial from './financial'
import account from './account'
import system from './system'
import test from './test'

// 合并所有模块的子路由
const allChildren = [
  ...dashboard,
  ...product,
  ...category,
  ...brand,
  ...customer,
  ...supplier,
  ...warehouse,
  ...purchase,
  ...sale,
  ...stock,
  ...financial,
  ...account,
  ...system,
  ...test
]

export default [
  {
    path: '/',
    redirect: '/dashboard',
    component: () => import('@/layout/MobileLayout.vue'),
    children: allChildren
  }
]