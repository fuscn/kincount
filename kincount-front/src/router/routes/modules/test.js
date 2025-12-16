export default [
  {
    path: '/test',
    name: 'Test',
    component: () => import('@/views/Test.vue'),
    meta: { title: '测试页面', showTabbar: false }
  }
]