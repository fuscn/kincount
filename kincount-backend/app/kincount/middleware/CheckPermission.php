<?php
namespace app\kincount\middleware;

use app\kincount\model\Role;
use think\facade\Db;

class CheckPermission
{
    public function handle($request, \Closure $next, $permission = null)
    {
        // 获取当前用户
        $user = $request->user;
        
        if (!$user) {
            return json([
                'code' => 401,
                'msg' => '用户未登录',
                'data' => null
            ]);
        }
        
        // 如果没有指定权限，直接通过
        if (!$permission) {
            return $next($request);
        }
        
        // 获取用户权限
        $permissions = $this->getUserPermissions($user);
        
        // 检查是否有所有权限
        if (in_array('*', $permissions)) {
            return $next($request);
        }
        
        // 检查是否有指定权限
        if (!in_array($permission, $permissions)) {
            return json([
                'code' => 403,
                'msg' => '没有访问权限',
                'data' => null
            ]);
        }
        
        return $next($request);
    }
    
    /**
     * 获取用户权限
     */
    private function getUserPermissions($user)
    {
        // 如果用户没有角色，返回空数组
        if (!$user['role_id']) {
            return [];
        }
        
        // 从角色中获取权限
        $role = Role::find($user['role_id']);
        
        if (!$role || !$role->permissions) {
            return [];
        }
        
        return is_array($role->permissions) ? $role->permissions : json_decode($role->permissions, true);
    }
}