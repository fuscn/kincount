<?php
declare (strict_types = 1);

namespace app\kincount\controller;

use app\kincount\model\SystemConfig;
use think\facade\Env;
use think\facade\Log;

class SystemController extends BaseController
{
    /** 系统健康检查 */
    public function status()
    {
        return $this->success([
            'database' => $this->checkDatabase(),
            'disk'     => $this->checkDisk(),
            'timestamp'=> time(),
        ]);
    }

    /** 全部配置列表 */
    public function configs()
    {
        $list = SystemConfig::select();
        return $this->success($list);
    }

    /** 保存配置（批量） */
    public function saveConfigs()
    {
        $post = input('post.');
        if (!isset($post['group']) || !isset($post['items'])) {
            return $this->error('参数缺失');
        }
        SystemConfig::setGroup($post['items'], $post['group']);
        return $this->success([], '保存成功');
    }

    /** 某分组配置 */
    public function configGroup($group)
    {
        return $this->success(SystemConfig::getGroup($group));
    }

    /** 系统信息 */
    public function info()
    {
        return $this->success([
            'php_version'     => PHP_VERSION,
            'thinkphp_version'=> app()::VERSION,
            'mysql_version'   => $this->mysqlVersion(),
            'upload_max_size' => ini_get('upload_max_filesize'),
            'company_name'    => SystemConfig::companyName(),
        ]);
    }

    /** 查看日志（最近 50 条） */
    public function logs()
    {
        $file = Log::getLogPath() . 'log.log';
        if (!is_file($file)) return $this->success([]);
        $logs = array_slice(file($file), -50);
        return $this->success(array_map('trim', $logs));
    }

    /** 清空日志 */
    public function clearLogs()
    {
        $files = glob(Log::getLogPath() . '*.log');
        array_map('unlink', $files);
        return $this->success([], '日志已清空');
    }

    /*----------  工具函数 ----------*/
    private function checkDatabase(): bool
    {
        try {
            \think\facade\Db::query('SELECT 1');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkDisk(): array
    {
        $total = disk_total_space('.');
        $free  = disk_free_space('.');
        return [
            'total' => round($total / 1024 / 1024 / 1024, 2) . ' GB',
            'free'  => round($free / 1024 / 1024 / 1024, 2) . ' GB',
            'usage' => round((1 - $free / $total) * 100, 2) . ' %',
        ];
    }

    private function mysqlVersion(): string
    {
        return \think\facade\Db::query('SELECT VERSION() as v')[0]['v'] ?? 'unknown';
    }
}