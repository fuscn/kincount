export default [
  {
    path: 'category',
    name: 'Category',
    component: () => import('@/views/category/Index.vue'),
    meta: { title: '分类管理' }
  },
  {
    path: 'category/create',
    name: 'CategoryCreate',
    component: () => import('@/views/category/Form.vue'),
    meta: {
      title: '新增分类',
      showTabbar: false,
      showLayoutNavBar: false
    }
  },
  {
    path: 'category/edit/:id',
    name: 'CategoryEdit',
    component: () => import('@/views/category/Form.vue'),
    meta: {
      title: '编辑分类',
      showTabbar: false,
      showLayoutNavBar: false
    }
  }
]