<?php

namespace Gini\Controller\CGI\AJAX\Gapper\Auth\Gateway;

class Organization extends \Gini\Controller\CGI
{
    public function actionGetSchools()
    {
        $data = (array) \Gini\Gapper\Auth\Gateway::getSchools();
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'data'=> array_values($data)
        ]);
    }

    public function actionGetDepartments($schoolCode=0)
    {
        $data = (array) \Gini\Gapper\Auth\Gateway::getDepartments($schoolCode);
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'data'=> array_values($data)
        ]);
    }

}


