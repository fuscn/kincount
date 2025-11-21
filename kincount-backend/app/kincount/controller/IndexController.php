<?php
namespace app\kincount\controller;

class IndexController extends BaseController
{
    public function index()
    {
        return $this->success([
            'message' => 'KinCount后端服务运行正常！',
            'version' => '1.0.0',
            'timestamp' => time()
        ]);
    }
}