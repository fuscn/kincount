export default [
  {
    path: 'supplier',
    name: 'Supplier',
    component: () => import('@/views/supplier/Index.vue'),
    meta: { title: '供应商管理' }
  },
  {
    path: 'supplier/create',
    name: 'SupplierCreate',
    component: () => import('@/views/supplier/Form.vue'),
    meta: { 
      title: '新增供应商', 
      showTabbar: false, 
      showLayoutNavBar: false 
    }
  },
  {
    path: 'supplier/edit/:id',
    name: 'SupplierEdit',
    component: () => import('@/views/supplier/Form.vue'),
    meta: { 
      title: '编辑供应商', 
      showTabbar: false, 
      showLayoutNavBar: false 
    }
  }
]