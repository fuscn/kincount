export default [
  {
    path: '/',
    redirect: '/dashboard',
    component: () => import('@/layout/MobileLayout.vue'),
    meta: { requireAuth: true },
    children: [] // 子路由由各模块导入
  }
]