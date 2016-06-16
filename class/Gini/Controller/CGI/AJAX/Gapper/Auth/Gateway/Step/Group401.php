<?php

namespace Gini\Controller\CGI\AJAX\Gapper\Auth\Gateway\Step;

use \Overtrue\Pinyin\Pinyin;

class Group401 extends \Gini\Controller\CGI
{
    private function _config() {
        return (object) \Gini\Config::get('gapper.auth')['gateway'];
    }

    public function __index()
    {
        $form = $this->form('post');
        $config = $this->_config();

        $identity = $_SESSION['gapper-auth-gateway.username'];
        if (!$identity) {
            return $this->_showError();
        }

        $appInfo = \Gini\Gapper\Client::getInfo();
        if (!$appInfo['id']) {
            \Gini\Gapper\Client::logout();
            return $this->_showError();
        }

        if (\Gini\Gapper\Client::getUserName() && self::_hasGroup()) {
            \Gini\Gapper\Client::logout();
            return $this->_showError();
        }

        $userInfo = self::_getUserInfo($identity);
        if (empty($userInfo)) {
            unset($_SESSION['gapper-auth-gateway.username']);
            \Gini\Gapper\Client::logout();
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $config->tips['nobody']);
        }
        
        if (!in_array($userInfo->type, ['staff', 'pi'])) {
            unset($_SESSION['gapper-auth-gateway.username']);
            \Gini\Gapper\Client::logout();
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $config->tips['not_staff']);
        }

        $step = 'active';
        if ($form['step'] == $step) {
            $gapperRPC = \Gini\Gapper\Client::getRPC();
            $gatewayRPC = self::_getGatewayRPC();

            $title = trim($form['title']);
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

                $validator
                    ->validate('title', $title, T('请填写课题组名称'))
                    ->validate('school', function() use ($school, $gatewayRPC, &$school_code, &$school_name) {
                        if (!$school) return true;
                        $data = (array)$gatewayRPC->Gateway->Organization->getSchools();
                        if (empty($data)) return false;
                        foreach ($data as $org) {
                            if ($org['code']==$school) {
                                $school_code = $org['code'];
                                $school_name = $org['name'];
                                return true;
                            }
                        }
                        return false;
                    }, T('请选择学院信息'))
                    ->validate('department', function() use ($school, $department, $gatewayRPC, &$department_code, &$department_name) {
                        if (!$department) return true;
                        if ($school) {
                            $data = (array)$gatewayRPC->Gateway->Organization->getDepartments(['school' => $school]);
                            if (empty($data)) return false;
                            foreach ($data as $org) {
                                if ($org['code']==$department) {
                                    $department_code = $org['code'];
                                    $department_name = $org['name'];
                                    return true;
                                }
                            }
                            return false;
                        }
                    }, T('请选择正确的专业信息'));

                $validator->done();

                // 如果没有Gapper用户, 首先创建Gapper用户
                if (!\Gini\Gapper\Client::getUserName()) {
                    $uid = $gapperRPC->gapper->user->registerUser([
                        'username' => $email,
                        'password' => \Gini\Util::randPassword(),
                        'name' => $name,
                        'email' => $email,
                    ]);
                    if ($uid) {
                        \Gini\Gapper\Client::loginByUserName($email);
                        \Gini\Gapper\Client::linkIdentity($config->source, $identity);
                    }

                    if (!\Gini\Gapper\Client::getUserName()) {
                        $validator->validate('*', false, T('用户注册失败，请重试!'))->done();
                    }
                }

                // 检查是否需要创建一个组
                
                // 尝试建组
                $titlePinyin = Pinyin::trans($title, [
                    'delimiter' => '',
                    'accent' => false,
                ]);
                $groupName = $gapperRPC
                    ->gapper->group->getRandomGroupName($titlePinyin);
                $validator->validate('*', !!$groupName, T('课题组标识冲突，请重试!'))->done();

                $gid = $gapperRPC->Gapper->Group->create([
                    'user'=> \Gini\Gapper\Client::getUserName(),
                    'name'=> $groupName,
                    'title'=> $title
                ]);
                $validator->validate('*', !!$gid, T('创建课题组失败，请重试!'))->done();

                self::_setTagData($gid, [
                    'organization'=> [
                        'school_code' => $school_code,
                        'school_name' => $school_name,
                        'department_code' => $department_code, 
                        'department_name' => $department_name,
                    ]
                ]);

                // 3. 自动安装组相关应用
                $appIds = (array) \Gini\Config::get('app.auto_install_apps_for_new_group');
                foreach ($appIds as $appId) {
                    $gapperRPC->gapper->app->installTo($appId, 'group', (int) $gid);
                }

                \Gini\Gapper\Client::chooseGroup((int)$gid, true);

                return \Gini\IoC::construct('\Gini\CGI\Response\JSON', true);
            }
            catch (\Exception $e) {
                $error = $validator->errors();
            }

        } else {
            $form['name'] = $userInfo->name;
            $form['email'] = $userInfo->email;
            $form['title'] = T('%name课题组', ['%name'=>$userInfo->name]); 
            $form['school'] = $userInfo->school['code'];
            $form['department'] = $userInfo->department['code'];
        }

        $vars = [
            'icon' => $config->icon,
            'type' => $config->name,
            'form'=> $form,
            'step'=> $step,
            'error' => $error,
        ];

        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'type'=> 'modal',
            'message'=> (string)V('gapper/auth/gateway/register-form', $vars)
        ]);
    }

    private function _showError()
    {
        \Gini\Gapper\Client::logout();
        $view = $view ?: (string)V(\Gini\Config::get('gapper.views')['client/error/401-group'] ?: 'gapper/client/error/401-group');
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'type'=> 'modal',
            'message'=> $view
        ]);
    }

    private static $_gatewayRPC;
    private static function _getGatewayRPC()
    {
        if (!self::$_gatewayRPC) {
            $conf = \Gini\Config::get('app.rpc');
            $gateway = $conf['gateway'];
            $gatewayURL = $gateway['url'];
            $clientId = $gateway['client_id'];
            $clientSecret = $gateway['client_secret'];
            $rpc = \Gini\IoC::construct('\Gini\RPC', $gatewayURL);
            $rpc->Gateway->authorize($clientId, $clientSecret);
            self::$_gatewayRPC = $rpc;
        }
        return self::$_gatewayRPC;
    }

    private static function _setTagData($gid, $data)
    {
        $conf = \Gini\Config::get('tag-db.rpc');
        $url = $conf['url'];
        $client = \Gini\Config::get('tag-db.client');
        $clientId = $client['id'];
        $clientSecret = $client['secret'];
        $rpc = \Gini\IoC::construct('\Gini\RPC', $url);
        $rpc->tagdb->authorize($clientId, $clientSecret);

        $source = $this->_config()->source;
        $tag = "labmai-{$source}/{$gid}";
        return !!$rpc->tagdb->data->set($tag, $data);
    }

    private static function _hasGroup()
    {
        $groups = \Gini\Gapper\Client::getGroups(\Gini\Gapper\Client::getUserName(), true);
        if (!$groups || !count($groups)) return false;
        return true;
    }

    private static function _getUserInfo($identity)
    {
        try {
            $config = (array) \Gini\Config::get('app.rpc');
            $config = $config['gateway'];
            $api = $config['url'];
            $client_id = $config['client_id'];
            $client_secret = $config['client_secret'];
            $rpc = \Gini\IoC::construct('\Gini\RPC', $api);
            if ($rpc->Gateway->authorize($client_id, $client_secret)) {
                $info = (array) $rpc->Gateway->People->getUser($identity);
            }
        } catch (\Exception $e) {
        }

        if (empty($info)) {
            return;
        }

        return (object) $info;
    }
}


