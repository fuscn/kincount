<?php

namespace app\kincount\controller;

class BaseController
{
    /**
     * 获取当前用户ID
     */
    protected function getUserId()
    {
        return request()->user_id ?? 0;
    }

    /**
     * 获取当前用户信息
     */
    protected function getUser()
    {
        return request()->user ?? [];
    }

    /**
     * 成功响应
     */
    protected function success($data = [], $msg = '操作成功', $code = 200)
    {
        return json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data
        ]);
    }

    /**
     * 错误响应
     */
    protected function error($msg = '操作失败', $code = 400, $data = [])
    {
        return json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data
        ]);
    }

    /**
     * 分页响应
     */
    protected function paginate($list, $msg = '获取成功')
    {
        return $this->success([
            'list' => $list->items(),
            'total' => $list->total(),
            'page' => $list->currentPage(),
            'page_size' => $list->listRows()
        ], $msg);
    }
    /** 下载 Excel（公共） */
    protected function downloadExcel(string $filename, array $rows)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($rows, null, 'A1');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $fileName = $filename . '_' . date('YmdHis') . '.xlsx';
        $tempFile = sys_get_temp_dir() . '/' . $fileName;
        $writer->save($tempFile);
        return download($tempFile, $fileName, true);
    }
}
