export default [
  {
    path: 'warehouse',
    name: 'Warehouse',
    component: () => import('@/views/warehouse/Index.vue'),
    meta: { title: '仓库管理' }
  },
  {
    path: 'warehouse/create',
    name: 'WarehouseCreate',
    component: () => import('@/views/warehouse/Form.vue'),
    meta: { 
      title: '新增仓库', 
      showTabbar: false, 
      showLayoutNavBar: false 
    }
  },
  {
    path: 'warehouse/edit/:id',
    name: 'WarehouseEdit',
    component: () => import('@/views/warehouse/Form.vue'),
    meta: { 
      title: '编辑仓库', 
      showTabbar: false, 
      showLayoutNavBar: false 
    }
  }
]