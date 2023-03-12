<?php

namespace Sleuren\Http\Controllers;

use Illuminate\Http\Request;

class ReportController
{
    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     */
    public function report(Request $request)
    {
        /** @var \Sleuren\Sleuren $sleuren */
        $sleuren = app('sleuren');

        $sleuren->handle(
            new \ErrorException($request->input('message')),
            'javascript',
            [
                'file' => $request->input('file'),
                'line' => $request->input('line'),
                'message' => $request->input('message'),
                'stack' => $request->input('stack'),
                'url' => $request->input('url'),
            ]
        );

        return response('ok', 200);
    }
}
