<?php
declare (strict_types = 1);

namespace app\kincount\controller;

use think\facade\Route;

class DebugController extends BaseController
{
    /** 路由列表（仅调试） */
    public function routes()
    {
        $list = Route::getRuleList();
        $data = array_map(function ($v) {
            return [
                'rule'   => $v['rule'],
                'route'  => $v['route'],
                'method' => $v['method'],
            ];
        }, $list);
        return $this->success($data);
    }

    /** 应用信息 */
    public function appInfo()
    {
        return $this->success([
            'app_name'    => config('app.app_name', 'KinCount'),
            'app_version' => '1.0.0',
            'php_version' => PHP_VERSION,
            'tp_version'  => app()::VERSION,
            'timezone'    => config('app.default_timezone', 'Asia/Shanghai'),
            'debug'       => env('APP_DEBUG', false),
        ]);
    }
}