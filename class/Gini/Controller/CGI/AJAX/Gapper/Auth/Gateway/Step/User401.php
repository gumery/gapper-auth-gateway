<?php

namespace Gini\Controller\CGI\AJAX\Gapper\Auth\Gateway\Step;

class User401 extends \Gini\Controller\CGI
{
    public function __index()
    {
        $user = \Gini\Gapper\Client::getUserInfo();
        if (!$user['id']) {
            return $this->_showError();
        }

        $appIds = (array) \Gini\Config::get('app.auto_install_apps_for_user');
        if (empty($appIds)) {
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', T('您无权访问该应用，请联系系统管理员.'));
        }
        $myClientID = \Gini\Gapper\Client::getId();
        if ($myClientID && !in_array($myClientID, $appIds)) {
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', T('您无权访问该应用，请联系系统管理员!'));
        }

        $gapperRPC = \Gini\Gapper\Client::getRPC();
        if (!$gapperRPC->gapper->app->installTo($myClientID, 'user', $user['id'])) {
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', T('您无权访问该应用，请联系系统管理员'));
        }

        \Gini\Gapper\Client::getUserApps(\Gini\Gapper\Client::getUserName(), true);

        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', true);
    }

}


