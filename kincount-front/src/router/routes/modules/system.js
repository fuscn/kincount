export default [
  {
    path: '/system/user',
    name: 'SystemUser',
    component: () => import('@/views/system/user/Index.vue'),
    meta: { title: '用户管理' }
  },
  {
    path: '/system/user/create',
    name: 'SystemUserCreate',
    component: () => import('@/views/system/user/Form.vue'),
    meta: { title: '新增用户', showTabbar: false, showLayoutNavBar: false }
  },
  {
    path: '/system/user/edit/:id',
    name: 'SystemUserEdit',
    component: () => import('@/views/system/user/Form.vue'),
    meta: { title: '编辑用户', showTabbar: false, showLayoutNavBar: false }
  },
  {
    path: '/system/user/password',
    name: 'SystemUserPassword',
    component: () => import('@/views/system/user/Password.vue'),
    meta: { title: '修改密码', showTabbar: false, showLayoutNavBar: false }
  },
  {
    path: '/system/user/profile',
    name: 'Profile',
    component: () => import('@/views/system/user/Profile.vue'),
    meta: { title: '我的资料', showTabbar: false, showLayoutNavBar: false }
  },
  {
    path: '/system/user/profile/edit',
    name: 'ProfileEdit',
    component: () => import('@/views/system/user/ProfileEdit.vue'),
    meta: {
      title: '编辑资料', 
      showTabbar: false, 
      showLayoutNavBar: false
    }
  },
  {
    path: '/system/role',
    name: 'Role',
    component: () => import('@/views/system/role/Index.vue'),
    meta: { title: '角色权限', requireAuth: true, perm: 'role:view' }
  },
  {
    path: '/system/role/create',
    name: 'RoleCreate',
    component: () => import('@/views/system/role/Form.vue'),
    meta: {
      title: '新增角色',
      requireAuth: true,
      perm: 'role:add'
    }
  },
  {
    path: '/system/role/edit/:id',
    name: 'RoleEdit',
    component: () => import('@/views/system/role/Form.vue'),
    meta: {
      title: '编辑角色',
      requireAuth: true,
      perm: 'role:edit'
    }
  },
  {
    path: '/system/config',
    name: 'SystemConfig',
    component: () => import('@/views/system/config/Index.vue'),
    meta: { title: '系统配置', showTabbar: false }
  },
  {
    path: '/system/config/info',
    name: 'SystemInfo',
    component: () => import('@/views/system/config/SystemInfo.vue'),
    meta: { title: '系统信息', showTabbar: false, requireAuth: true, perm: 'system:info:view' }
  },
  {
    path: '/system/logs',
    name: 'SystemLogs',
    component: () => import('@/views/system/config/SystemLogs.vue'),
    meta: { title: '系统日志', showTabbar: false, requireAuth: true, perm: 'system:logs:view' }
  },
  {
    path: '/system/operationflowguide',
    name: 'OperationFlowGuide',
    component: () => import('@/views/system/config/OperationFlowGuide.vue'),
    meta: { title: '操作指南', showTabbar: false }
  }
]