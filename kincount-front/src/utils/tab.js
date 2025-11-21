import { useAppStore } from '@/store/modules/app'

// 添加缓存
export function addTab(route) {
  const app = useAppStore()
  if (!app.cachedTabs.includes(route.name)) {
    app.cachedTabs.push(route.name)
  }
}

// 关闭缓存
export function closeTab(name) {
  const app = useAppStore()
  const idx = app.cachedTabs.indexOf(name)
  idx > -1 && app.cachedTabs.splice(idx, 1)
}

// 刷新当前页
export function refreshPage(router) {
  const { currentRoute } = router
  const name = currentRoute.value.name
  closeTab(name)
  router.replace({ name: 'Redirect', params: { path: currentRoute.value.fullPath } })
}