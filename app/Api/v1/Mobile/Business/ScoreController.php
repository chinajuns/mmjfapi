<?php

namespace App\Api\v1\Mobile\Business;

use App\Api\Models\IntegralListModel;
use App\Api\Models\UserModel;
use App\Api\v1\Controllers\BaseController;
use Illuminate\Http\Request;

class ScoreController extends BaseController
{
    //积分首页
    public function index(Request $request){
        dd(date('t'));

        $uid=$request->input('uid');
        $userModel=UserModel::select('integral as score_total')->where(['id'=>$uid])->first();
        if($userModel==null){
            return $this->setstatusCode('2001')->setMessage('用户信息有误')->result();
        }
        $today_start=strtotime(date('Y-m-d',time()));
        $today_end=$today_start+24*3600;
        $today=IntegralListModel::select('sum(number) as score_today')->where(['user_id'=>$uid])->whereBetween('create_time',[$today_start,$today_end])->frist();
        $score_today=$today?$today->score_today:0;
        $month_start=strtotime(date('Y-m',time()));
    }
}
