<?php
/**
 *Create by PhpStorm
 *User:liangtao
 *Date:2021-7-14
 */

namespace App\Api\V1\Controllers\Member;

use App\Api\Common\Controllers\BaseController;
use Illuminate\Http\Request;
use App\Api\Logic\Member\Scope\Scope;

class ScopeController extends BaseController
{
    public function Create( Request $request )
    {
        $param = $this->getContentArray( $request );
        if ( empty( $param['channel'] ) || empty( $param['identify_data'] ) )
        {
            $this->setErrorMsg( '参数错误' );
            return $this->outputFormat( $param, 10001 );
        }

        $error = "";
        $scopeLogic = new Scope();
        $response = $scopeLogic->create( $param['channel'], $param['identify_data'], $error );
        if ( $response === false )
        {
            $this->setErrorMsg( '创建失败：' . $error );
            return $this->outputFormat( $param, 10002 );
        }

        $this->setErrorMsg( 'success' );
        return $this->outputFormat( [ 'rule_bn' => $response ], 0 );
    }

    public function Update(Request $request)
    {
        $param = $this->getContentArray($request);
        if (empty($param['channel']) || empty($param['rule_bn']) || empty($param['data'])) {
            $this->setErrorMsg('参数错误');
            return $this->outputFormat($param, 10001);
        }

        $error = "";
        $scopeLogic = new Scope();
        $response = $scopeLogic->update($param['channel'], $param['rule_bn'], $param['data'], $error);
        if ($response === false) {
            $this->setErrorMsg('修改失败：'.$error);
            return $this->outputFormat($param, 10002);
        }

        $this->setErrorMsg('success');
        return $this->outputFormat(['Result' => true], 0);
    }

    public function GetScopeIdentifyListByRuleBn( Request $request )
    {
        $param = $this->getContentArray( $request );
        if ( empty( $param['rule_bn'] ) )
        {
            $this->setErrorMsg( '参数错误' );
            return $this->outputFormat( $param, 10001 );
        }

        $error = "";
        $page = $param['page'] ? : 1;
        $limit = $param['limit'] ? : 1;

        $scopeLogic = new Scope();
        $response = $scopeLogic->getScopeIdentifyListByRuleBn( $param['rule_bn'], $page, $limit, $error );
        if ( $response === false )
        {
            $this->setErrorMsg( '查询失败：' . $error );
            return $this->outputFormat( $param, 10002 );
        }

        $this->setErrorMsg( 'success' );
        return $this->outputFormat( $response, 0 );
    }

    public function GetScopeByRuleBnsAndIdentify( Request $request )
    {
        $param = $this->getContentArray( $request );

        if ( empty( $param['rule_bns'] ) || empty( $param['identify_data'] ) )
        {
            $this->setErrorMsg( '参数错误' );
            return $this->outputFormat( $param, 10001 );
        }

        $error = "";
        $scopeLogic = new Scope();
        $response = $scopeLogic->getScopeByRuleBnsAndIdentify( $param['rule_bns'], $param['identify_data'], $error );
        if ( $response === false )
        {
            $this->setErrorMsg( '查询失败：' . $error );
            return $this->outputFormat( $param, 10002 );
        }

        $this->setErrorMsg( 'success' );
        return $this->outputFormat( $response, 0 );
    }
}
