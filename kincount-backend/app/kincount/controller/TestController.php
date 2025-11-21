<?php
declare (strict_types = 1);

namespace app\kincount\controller;

class TestController extends BaseController
{
    /** 测试接口 */
    public function index()
    {
        return $this->success([
            'message' => 'KinCount 测试接口正常',
            'time'    => date('Y-m-d H:i:s'),
            'rand'    => mt_rand(1000, 9999),
        ]);
    }
}