<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017-12-14
 * Time: 13:58
 */

return [
    //图片上传的配置文件
    'IMG_PATH' => date("Y-m-d",time()),

    //信贷经理审核的常量配置
    'LOANER_AUTH' => [
        'AUDIT' => 1,     //未审核
        'UNAUDITED' => 2,  //审核中
        'PASS' => 3,  //审核通过
        'REFUSE' => 4,  //审核拒绝
    ],

    //用户审核的常量配置
    'USER_AUTH' => [
        'AUDIT' => 1,     //未审核
        'UNAUDITED' => 2,  //审核中
        'PASS' => 3,  //审核通过
        'REFUSE' => 4,  //审核拒绝
    ],

    //店铺审核的常量配置
    'SHOP_AUTH' => [
        'AUDIT' => 1,     //未审核
        'PASS' => 2,  //审核通过
        'REFUSE' => 3,  //审核拒绝
    ],

    //举报审核的常量配置
    'REPORT' => [
        'TYPE' => [
            'CHEAT' => 1,   //举报存在欺诈行为
            'CHARGE' => 2,  //举报乱收费行为
            'SERVICE_ATTITUDE' => 3,    //举报服务态度
        ],

        'POINT' => [
            'B_C' => 1, //信贷经理举报用户
            'C_B' => 2, //用户举报信贷经理
        ],

        'AUTH' => [
            'AUDIT' => 1,     //未审核
            'REFUSE' => 2,  //审核拒绝
            'PASS' => 3,  //审核通过

        ],
    ],

    //反馈审核的常量配置
    'FEEDBACK_AUTH' => [
        'AUDIT' => 1,     //未审核
        'REFUSE' => 2,  //审核拒绝
        'PASS' => 3,  //审核通过
    ],

    //甩单审核的常量配置
    'JUNK_LOAN' => [
        'AUTH' => [
            'AUDIT' => 1,     //未审核
            'PASS' => 2,  //审核通过
            'REFUSE' => 3,  //审核拒绝
        ],

        'IS_VIP' => [
            'GOOD' => 1,    //优质订单
            'BAD' => 2,    //非优质订单
        ],

        'STATUS' => [
            'NORMAL' => 1,  //正常
            'OVERDUE' => 2,  //过期
            'TRADE' => 3,  //已交易
        ],

        'MARRY' => [
            'MARRIED' => 1,   //已婚
            'UNMARRIED' => 0, //未婚
        ],
    ],
];