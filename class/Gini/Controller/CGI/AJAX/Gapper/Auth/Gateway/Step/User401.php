<?php

namespace Gini\Controller\CGI\AJAX\Gapper\Auth\Gateway\Step;

class User401 extends \Gini\Controller\CGI
{
    public function __index()
    {
        $appIds = (array) \Gini\Config::get('app.auto_install_apps_for_user');
        if (empty($appIds)) {
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', T('您无权访问该应用，请联系系统管理员.'));
        }
        $myClientID = \Gini\Gapper\Client::getId();
        if ($myClientID && !in_array($myClientID, $appIds)) {
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', T('您无权访问该应用，请联系系统管理员!'));
        }

        $appInfo = \Gini\Gapper\Client::getInfo();
        if (!$appInfo['id']) {
            return $this->_showError();
        }

        $form = $this->form('post');
        $identity = $_SESSION['gapper-auth-gateway.username'];
        if (!$identity && !\Gini\Gapper\Client::getUserName()) {
            return $this->_showError();
        }

        $config = (object)\Gini\Config::get('gapper.auth')['gateway'];
        $userInfo = $this->_getUserInfo($identity);
        if (!$userInfo->ref_no) {
            //unset($_SESSION['gapper-auth-gateway.username']);
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $config->tips['nobody']);
        }

        $gapperRPC = \Gini\Gapper\Client::getRPC();
        $username = \Gini\Gapper\Client::getUserName();
        $user = \Gini\Gapper\Client::getUserInfo();
        if ($username) {
           if (!$gapperRPC->gapper->app->installTo($myClientID, 'user', $user['id'])) {
               return \Gini\IoC::construct('\Gini\CGI\Response\JSON', T('您无权访问该应用，请联系系统管理员'));
           }

           \Gini\Gapper\Client::getUserApps(\Gini\Gapper\Client::getUserName(), true);
           
           return \Gini\IoC::construct('\Gini\CGI\Response\JSON', true);
        }
        // TODO 参考Group401, 引导用户创建gapper用户

        // $user = \Gini\Gapper\Client::getUserInfo();
        // if (!$user['id']) {
        //     return $this->_showError();
        // }

        if (isset($form['email'])) {
            $school = trim($form['school']);
            $department = trim($form['department']);
            $name = trim($form['name']);
            $email = trim($form['email']);

            $school_code = '';
            $school_name = '';
            $department_code = '';
            $department_name = '';

            $validator = new \Gini\CGI\Validator();
            try {
                if (!\Gini\Gapper\Client::getUserName()) {
                    $validator
                        ->validate('name', $name, T('请输入真实姓名'))
                        ->validate('email', function() use ($email) {
                            $pattern = '/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/';
                            if (!preg_match($pattern, $email)) {
                                return false;
                            }
                            return true;
                        }, T('请使用正确的Email'))
                            ->validate('email', function() use ($email, $gapperRPC) {
                                $identityUser = $gapperRPC->gapper->user->getInfo($email);
                                if (!empty($identityUser)) return false;
                                return true;
                            }, T('Email已被占用, 请换一个'));
                }

                $validator->done();
                // 如果没有Gapper用户, 首先创建Gapper用户
                if (!\Gini\Gapper\Client::getUserName()) {
                    $uid = $gapperRPC->gapper->user->registerUserWithIdentity([
                        'username' => $email,
                        'password' => \Gini\Util::randPassword(),
                        'name' => $name,
                        'email' => $email,
                    ], $config->source, $identity);
                    if ($uid) {
                        if (!$gapperRPC->gapper->app->installTo($myClientID, 'user', $uid)) {
                            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', T('您无权访问该应用，请联系系统管理员'));
                        }
                        \Gini\Gapper\Client::loginByUserName($email);
                    }

                    if (!\Gini\Gapper\Client::getUserName()) {
                        $validator->validate('*', false, T('用户注册失败，请重试!'))->done();
                    }
                }
                return \Gini\IoC::construct('\Gini\CGI\Response\JSON', true);
            }
            catch (\Exception $e) {
                $error = $validator->errors();
                if (empty($error)) {
                    $error['*'] = T('目前网络不稳定，建议您重新提交该表单');
                }
            }
        }

        $vars = [
            'icon' => $config->icon,
            'type' => $config->name,
            'email' => $userInfo->email,
            'name' => $userInfo->name,
            'form'=> $form,
            'error' => $error,
        ];

        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'type'=> 'modal',
            'message'=> (string)V('gapper/auth/gateway/user-register-form', $vars)
        ]);
    }

    private function _hasInstall()
    {
        $apps = \Gini\Gapper\Client::getUserApps(\Gini\Gapper\Client::getUserName(), true);
        if (!$pps || !count($apps)) return false;
        $client_id = \Gini\Config::get('gapper.rpc')['client_id'];
        if (!isset($apps[$client_id])) return false;

        return true;
    }

    private function _getUserInfo($identity)
    {
        $rpc = \Gini\Module\AppBase::getGatewayRPC();
        return (object)$rpc->Gateway->People->getUser(['ref_no'=>$identity]);
    }

    private function _showError()
    {
        // unset($_SESSION['gapper-auth-gateway.username']);
        $view = (string)V(\Gini\Config::get('gapper.views')['client/error/401-user'] ?: 'gapper/client/error/401-user');
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'type'=> 'modal',
            'message'=> $view
        ]);
    }
}
