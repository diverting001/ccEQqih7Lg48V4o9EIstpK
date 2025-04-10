<?php

namespace App\Api\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiDecoratorMiddleware
{
    /**
     * 内购api request处理
     *
     * @param  Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        //压测标记
        if (isset($_SERVER['HTTP_TESTING'])) {
            $request->testing = $_SERVER['HTTP_TESTING'];
        } else {
            $request->testing = 0;
        }

        $request->businessData = [
            'traceId'   => $this->getQueryId($request),
            'timestamp' => $this->getTimestamp()
        ];

        $response = $next($request);
        return $response;
    }

    public function getTimestamp()
    {
        $mtime = explode(' ', microtime());
        return $mtime[1] + $mtime[0];
    }

    private function getQueryId(Request $request)
    {
        if (isset($_SERVER['HTTP_TRACKID'])) {
            $request->headers->set('trackid', $_SERVER['HTTP_TRACKID']);
        } else {
            if (!$request->headers->has('trackid')) {
                $request->headers->set('trackid', uniqid());
            }
        }
        return $request->headers->get('trackid');
    }
}
