<?php

namespace App\Http\Controllers;

abstract class Controller
{
    public function res_success($message = '', $data = null)
    {
        return response()->json([
            'result' => true,
            'message' => $message,
            'data' => $data
        ]);
    }
    protected function res_error($message, $status = 400)
    {
        return response([
            'result' => false,
            'message' => $message
        ], $status);
    }
    public function res_paginate($page, $message = '', $data = null, $counts = [])
    {
        return response()->json([
            'result' => true,
            'message' => $message,
            'data' => $data,
            'counts' => $counts,
            'paginate' => [
                'has_page' => $page->hasPages(),
                'has_more_pages' => $page->hasMorePages(),
                'total' => $page->total(),
                'total_page' => $page->lastPage(),
                'current_page' => $page->currentPage()
            ]
        ]);
    }
}
