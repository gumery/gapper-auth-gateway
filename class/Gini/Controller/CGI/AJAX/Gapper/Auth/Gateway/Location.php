<?php

namespace Gini\Controller\CGI\AJAX\Gapper\Auth\Gateway;

class Location extends \Gini\Controller\CGI
{
    public function actionGetCampuses()
    {
        $data = (array) \Gini\Gapper\Auth\Gateway::getCampuses();
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'data'=> array_values($data)
        ]);
    }

    public function actionGetBuildings($campus=0)
    {
        $data = (array) \Gini\Gapper\Auth\Gateway::getBuildings(['campus'=>$campus]);
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'data'=> array_values($data)
        ]);
    }

    public function actionGetRooms($building=0)
    {
        $data = (array) \Gini\Gapper\Auth\Gateway::getRooms(['building'=>$building]);
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'data'=> array_values($data)
        ]);
    }
}

