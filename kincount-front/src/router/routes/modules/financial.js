export default [
  {
    path: 'financial/record',
    name: 'FinancialRecord',
    component: () => import('@/views/financial/FinancialRecord.vue'),
    meta: { title: '收支记录' }
  },
  {
    path: 'financial/record/create',
    name: 'FinancialRecordCreate',
    component: () => import('@/views/financial/FinancialRecordCreate.vue'),
    meta: {
      title: '新建收支记录', 
      showTabbar: false, 
      showLayoutNavBar: false
    }
  },
  {
    path: 'financial/record/detail/:id',
    name: 'FinancialRecordDetail',
    component: () => import('@/views/financial/FinancialRecordDetail.vue'),
    meta: {
      title: '收支记录详情',
      showTabbar: false,
      showLayoutNavBar: false
    }
  },
  {
    path: 'financial/record/edit/:id',
    name: 'FinancialRecordEdit',
    component: () => import('@/views/financial/FinancialRecordEdit.vue'),
    meta: {
      title: '编辑收支记录',
      showTabbar: false,
      showLayoutNavBar: false
    }
  },
  {
    path: 'financial/report/profit',
    name: 'FinancialReportProfit',
    component: () => import('@/views/Placeholder.vue'),
    meta: { title: '利润报表', showTabbar: false }
  },
  {
    path: 'financial/report/cashflow',
    name: 'FinancialReportCashflow',
    component: () => import('@/views/Placeholder.vue'),
    meta: { title: '资金流水', showTabbar: false }
  },
  {
    path: 'financial/dashboard',
    name: 'FinancialDashboard',
    component: () => import('@/views/financial/FinancialDashboard.vue'),
    meta: { title: '财务概览', showTabbar: false }
  }
]