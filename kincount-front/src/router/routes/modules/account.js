export default [
  {
    path: 'account/receivable',
    name: 'AccountReceivable',
    component: () => import('@/views/account/AccountReceivable.vue'),
    meta: { title: '应收账款' }
  },
  {
    path: 'account/payable',
    name: 'AccountPayable',
    component: () => import('@/views/account/AccountPayable.vue'),
    meta: { title: '应付账款' }
  },
  {
    path: 'account/settlement',
    name: 'AccountSettlement',
    component: () => import('@/views/account/SettlementIndex.vue'),
    meta: { title: '账款核销' }
  },
  {
    path: 'account/settlement/create',
    name: 'AccountSettlementCreate',
    component: () => import('@/views/account/SettlementForm.vue'),
    meta: { title: '创建核销' }
  },
  {
    path: 'account/settlement/:id',
    name: 'AccountSettlementDetail',
    component: () => import('@/views/account/SettlementDetail.vue'),
    meta: { title: '核销详情' }
  },
  {
    path: 'account/payable/create',
    name: 'AccountPayableCreate',
    component: () => import('@/views/financial/AccountPayableCreate.vue'),
    meta: { title: '新增应付记录', showTabbar: false }
  }
]