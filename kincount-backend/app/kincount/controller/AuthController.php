<?php

declare(strict_types=1);

namespace app\kincount\controller;

use app\kincount\model\User;
use app\kincount\model\Role;

use think\exception\ValidateException;

class AuthController extends BaseController
{
    /**
     * 登录
     * POST /auth/login
     */
    public function login()
    {
        $post = input('post.');
        validate([
            'username' => 'require|length:3,50',
            'password' => 'require|length:6,30'
        ])->check($post);

        $user = User::where('username', $post['username'])
            ->where('status', 1)
            ->find();



        if (!$user || !password_verify($post['password'], $user->password)) {
            return $this->error('账号或密码错误');
        }

        // 更新登录信息
        $user->last_login_time = date('Y-m-d H:i:s');
        $user->last_login_ip   = request()->ip();
        $user->save();

        // 一次性拉权限
        $permissions = [];
        if ($user->role_id) {
            // 如果角色表里有 permissions 字段（json）
            $role = Role::find($user->role_id);
            $permissions = $role?->permissions ?: [];

            // 若权限是独立表，用关联查询
            // $permissions = $user->role->permissions()->column('code');
        }

        // 生成 JWT
        $token = $this->buildJwt($user);

        return $this->success([
            'token'       => $token,
            'user'        => $user->hidden(['password'])
                ->toArray() + ['permissions' => $permissions]
        ]);
    }

    /**
     * 退出
     * POST /auth/logout
     */
    public function logout()
    {
        // 前端自己丢弃 token 即可，这里返回成功
        return $this->success([], '已退出');
    }

    /**
     * 刷新 Token
     * POST /auth/refresh
     */
    public function refresh()
    {
        try {
            // 获取当前用户（通过 JWT 中间件已经验证）
            $user = request()->user;

            // 重新生成 JWT
            $newToken = $this->buildJwt($user);

            return $this->success([
                'token' => $newToken,
                'user'  => $user->hidden(['password'])
            ], 'Token 刷新成功');
        } catch (\Exception $e) {
            return $this->error('Token 刷新失败: ' . $e->getMessage());
        }
    }

    /**
     * 当前用户信息（含角色 & 权限）
     * GET /auth/userinfo
     */
    public function userinfo()
    {
        // 拿到当前用户 ID（JWT 中间件已注入）
        $userId = request()->user_id;

        // 重新查模型，并携带关联角色
        $user = User::with(['role'])->find($userId);
        if (!$user) {
            return $this->error('用户不存在');
        }

        // 角色名数组
        $roles = $user->role ? [$user->role->name] : [];

        // 权限平铺数组（role.permissions 是 JSON 数组）
        $permissions = $user->role && $user->role->permissions
            ? $user->role->permissions
            : [];

        // 隐藏敏感字段 + 追加字段
        $data = $user->hidden(['password', 'deleted_at'])
            ->toArray();

        $data['roles']       = $roles;
        $data['permissions'] = $permissions;

        return $this->success($data);
    }

    /**
     * 更新个人资料
     * PUT /auth/profile
     */
    public function updateProfile()
    {
        try {
            // 获取当前用户 ID（JWT 中间件已注入）
            $userId = request()->user_id;
            $user = User::find($userId);
            if (!$user) {
                return $this->error('用户不存在');
            }

            // 获取并过滤数据（白名单）
            $data = input('put.');
            $allow = ['real_name', 'phone', 'email', 'avatar', 'department'];
            $data = array_intersect_key($data, array_flip($allow));

            // 数据验证
            if (!empty($data)) {
                $rules = [];
                if (isset($data['real_name'])) {
                    $rules['real_name'] = 'length:2,10';
                }
                if (isset($data['phone'])) {
                    $rules['phone'] = 'mobile';
                }
                if (isset($data['email'])) {
                    $rules['email'] = 'email';
                }
                if (isset($data['avatar'])) {
                    $rules['avatar'] = 'url';
                }
                if (isset($data['department'])) {
                    $rules['department'] = 'max:50';
                }

                if (!empty($rules)) {
                    validate($rules)->check($data);
                }
            }

            if (empty($data)) {
                return $this->error('没有要更新的数据');
            }

            // 更新数据
            $user->save($data);

            // 返回更新后的用户信息（包含角色信息）
            $updatedUser = User::with(['role'])->find($userId);
            $roles = $updatedUser->role ? [$updatedUser->role->name] : [];
            $permissions = $updatedUser->role && $updatedUser->role->permissions
                ? $updatedUser->role->permissions
                : [];

            $userData = $updatedUser->hidden(['password', 'deleted_at'])->toArray();
            $userData['roles'] = $roles;
            $userData['permissions'] = $permissions;

            return $this->success($userData, '资料更新成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            return $this->error('更新失败: ' . $e->getMessage());
        }
    }

    /**
     * 修改密码
     * PUT /auth/password
     */
    public function changePassword()
    {
        try {
            $userId = request()->user_id;
            $user = User::find($userId);
            if (!$user) {
                return $this->error('用户不存在');
            }

            $data = input('put.');

            // 验证数据
            validate([
                'old_password' => 'require',
                'new_password' => 'require|length:6,30',
            ])->check($data);

            // 验证旧密码 - 使用模型中的方法或直接使用password_verify
            if (!password_verify($data['old_password'], $user->password)) {
                return $this->error('原密码错误');
            }

            // 更新密码：直接设置明文密码，让模型修改器加密
            $user->password = $data['new_password'];
            $user->save();

            return $this->success([], '密码修改成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            return $this->error('修改失败: ' . $e->getMessage());
        }
    }

    /* ------------------------------------------------------------------ */
    /* 工具函数
    /* ------------------------------------------------------------------ */
    private function buildJwt(User $user): string
    {
        if (!$user) {
            throw new \Exception('用户对象不能为空');
        }
        $payload = [
            'iss'  => config('jwt.issuer'),
            'iat'  => time(),
            'exp'  => time() + config('jwt.access_ttl'),
            'user_id'  => $user->id,
            'username' => $user->username,
            'role_id'  => $user->role_id,
        ];

        return \Firebase\JWT\JWT::encode(
            $payload,
            config('jwt.secret'),
            config('jwt.algorithm')
        );
    }
}
