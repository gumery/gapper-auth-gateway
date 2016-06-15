<?php

namespace Gini\Controller\CGI\AJAX\Gapper\Auth\Gateway;

class Organization extends \Gini\Controller\CGI
{
    public function actionGetSchools()
    {
        $rpc = self::_getRPC();
        $data = (array)$rpc->Gateway->Organization->getSchools();

        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'data'=> array_values($data)
        ]);
    }

    public function actionGetDepartments($schoolCode=0)
    {
        $rpc = self::_getRPC();
        $data = (array)$rpc->Gateway->Organization->getDepartments(['school' => $schoolCode]);

        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'data'=> array_values($data)
        ]);
    }

    private static $_rpc;
    private static function _getRPC()
    {
        if (!self::$_rpc) {
            $confs = \Gini\Config::get('app.rpc');

            $gateway = (array) $confs["gateway"];
            $gatewayURL = $gateway['url'];
            $clientId = $gateway['client_id'];
            $clientSecret = $gateway['client_secret'];
            $rpc = \Gini\IoC::construct('\Gini\RPC', $gatewayURL);
            $rpc->Gateway->authorize($clientId, $clientSecret);
            self::$_rpc = $rpc;
        }
        return self::$_rpc;
    }
}


