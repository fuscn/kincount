export default [
  {
    path: 'dashboard',
    name: 'Dashboard',
    component: () => import('@/views/dashboard/Index.vue'),
    meta: { title: '首页', icon: 'home-o' }
  }
]