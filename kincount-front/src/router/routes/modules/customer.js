export default [
  {
    path: 'customer',
    name: 'Customer',
    component: () => import('@/views/customer/Index.vue'),
    meta: { title: '客户管理' }
  },
  {
    path: 'customer/create',
    name: 'CustomerCreate',
    component: () => import('@/views/customer/Form.vue'),
    meta: { 
      title: '新增客户', 
      showTabbar: false, 
      showLayoutNavBar: false 
    }
  },
  {
    path: 'customer/edit/:id',
    name: 'CustomerEdit',
    component: () => import('@/views/customer/Form.vue'),
    meta: { 
      title: '编辑客户', 
      showTabbar: false, 
      showLayoutNavBar: false 
    }
  }
]