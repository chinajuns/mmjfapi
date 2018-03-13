<?php

namespace App\Api\v1\Mobile\Business;

use App\Api\v1\Controllers\BaseController;
use Illuminate\Http\Request;

class WalletController extends BaseController
{
    //钱包首页
    public function index(Request $request){
        $uid=$request->input('uid');
    }
}
