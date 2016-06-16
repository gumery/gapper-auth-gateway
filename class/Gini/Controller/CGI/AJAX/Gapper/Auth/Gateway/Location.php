<?php

namespace Gini\Controller\CGI\AJAX\Gapper\Auth\Gateway;

class Location extends \Gini\Controller\CGI
{
    public function actionGetCampuses()
    {
        $data = (array) \Gini\Gapper\Auth\Gateway::getCampuses();
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'data'=> $data
        ]);
    }

    public function actionGetBuildings($campus)
    {
        $data = (array) \Gini\Gapper\Auth\Gateway::getBuildings($campus);
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'data'=> $data
        ]);
    }
}

