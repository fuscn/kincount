export default [
  {
    path: 'brand',
    name: 'Brand',
    component: () => import('@/views/brand/Index.vue'),
    meta: { title: '品牌管理' }
  },
  {
    path: 'brand/create',
    name: 'BrandCreate',
    component: () => import('@/views/brand/Form.vue'),
    meta: { 
      title: '新增品牌', 
      showTabbar: false, 
      showLayoutNavBar: false 
    }
  },
  {
    path: 'brand/edit/:id',
    name: 'BrandEdit',
    component: () => import('@/views/brand/Form.vue'),
    meta: { 
      title: '编辑品牌', 
      showTabbar: false, 
      showLayoutNavBar: false 
    }
  }
]