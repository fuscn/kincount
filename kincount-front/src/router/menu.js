import { MENU } from '@/constants/permissions'
import { useAuthStore } from '@/store/modules/auth'

/**
 * 根据权限拍平菜单，供侧边栏渲染
 * @returns {Array} 有权访问的菜单
 */
export function getPermissionMenu() {
  const auth = useAuthStore()

  function filter(list) {
    return list.reduce((arr, item) => {
      // 没有权限直接丢弃
      if (item.perm && !auth.hasPerm(item.perm)) return arr

      // 有子菜单继续过滤
      if (item.children) {
        const children = filter(item.children)
        if (children.length) arr.push({ ...item, children })
      } else {
        arr.push(item)
      }
      return arr
    }, [])
  }

  return filter(MENU)
}