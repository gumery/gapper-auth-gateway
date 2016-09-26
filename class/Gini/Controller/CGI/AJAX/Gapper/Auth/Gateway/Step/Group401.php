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
        if (!$identity && !\Gini\Gapper\Client::getUserName()) {
            return $this->_showError();
        }

        $appInfo = \Gini\Gapper\Client::getInfo();
        if (!$appInfo['id']) {
            return $this->_showError();
        }

        if (\Gini\Gapper\Client::getUserName() && $this->_hasGroup()) {
            return $this->_showError();
        }

        if ($identity) {
            $userInfo = $this->_getUserInfo($identity);
        } else {
            $userInfo = $this->_getUserInfoByGapperUser(\Gini\Gapper\Client::getUserName());
            $identity = $userInfo->ref_no;
        }

        if (!$userInfo->ref_no) {
            // unset($_SESSION['gapper-auth-gateway.username']);
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $config->tips['nobody']);
        }

        if (!in_array($userInfo->type, ['staff', 'pi', 'admin'])) {
            // unset($_SESSION['gapper-auth-gateway.username']);
            return \Gini\IoC::construct('\Gini\CGI\Response\JSON', $config->tips['not_staff']);
        }

        $userNameChangable = $config->user_name_changable;

        $step = 'active';
        if ($form['step'] == $step) {
            $gapperRPC = \Gini\Gapper\Client::getRPC();
            $gatewayRPC = $this->_getGatewayRPC();

            if ($userNameChangable) {
                $name = trim($form['name']);
                $title = trim($form['title']);
            } else {
                $name = $userInfo->name;
                $title = T('%name课题组', ['%name'=>$name]);
            }

            $campus = trim($form['campus']);
            $building = trim($form['building']);
            $room = trim($form['room']);

            $school = trim($form['school']);
            $department = trim($form['department']);
            $email = trim($form['email']);

            $school_code = '';
            $school_name = '';
            $department_code = '';
            $department_name = '';
            $campus_name = '';
            $building_name = '';

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

                if ($userNameChangable) {
                    $validator->validate('title', $title, T('请填写课题组名称'));
                }

                if ($campus) {
                    $data = (array)\Gini\Gapper\Auth\Gateway::getCampuses();
                    foreach ($data as $l) {
                        if ($campus == $l['code']) {
                            $campus_name = $l['name'];
                            break;
                        }
                    }
                }

                if ($building && $campus) {
                    $data = (array)\Gini\Gapper\Auth\Gateway::getBuildings($campus);
                    foreach ($data as $d) {
                        if ($building == $d['code']) {
                            $building_name = $l['name'];
                            break;
                        }
                    }
                }

                if ($school) {
                    $data = (array) \Gini\Gapper\Auth\Gateway::getSchools();
                    foreach ($data as $org) {
                        if ($org['code']==$school) {
                            $school_name = $org['name'];
                            break;
                        }
                    }
                }

                if ($school && $department) {
                    $data = (array)\Gini\Gapper\Auth\Gateway::getDepartments($school);
                    foreach ($data as $org) {
                        if ($org['code']==$department) {
                            $department_name = $org['name'];
                            break;
                        }
                    }
                }

                if (!!$config->form_requires['campus']) {
                    $validator->validate('campus', $campus_name, T('请选择校区'));
                }
                
                if (!!$config->form_requires['building']) {
                    $validator->validate('building', $building_name, T('请选择楼宇信息'));
                }
                
                if (!!$config->form_requires['school']) {
                    $validator->validate('school', $school_name, T('请选择学院信息'));
                }
                
                if (!!$config->form_requires['department']) {
                    $validator->validate('department', $department_name, T('请选择正确的专业信息'));
                }
                
                if (!!$config->form_requires['room']) {
                    $validator->validate('room', $room, T('请输入您的房间'));
                }

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

                $this->_setTagData($gid, [
                    'organization'=> [
                        'code'=> $department,
                        'name'=> $department_name,
                        'parent'=> [
                            'code'=> $school,
                            'name'=> $school_name
                        ],
                        'school_code' => $school,
                        'school_name' => $school_name,
                        'department_code' => $department,
                        'department_name' => $department_name,
                    ],
                    'location'=> [
                        'campus_code'=> $campus,
                        'campus_name'=> $campus_name,
                        'building_code'=> $building,
                        'building_name'=> $building_name,
                        'room_name'=> $room
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
            $form['title'] = T('%name课题组', ['%name'=>$userInfo->name]);
            $form['email'] = $userInfo->email;
            $form['school'] = $userInfo->school['code'];
            $form['department'] = $userInfo->department['code'];
        }

        $vars = [
            'icon' => $config->icon,
            'type' => $config->name,
            'form'=> $form,
            'step'=> $step,
            'error' => $error,
            'userNameChangable' => $userNameChangable,
        ];

        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'type'=> 'modal',
            'message'=> (string)V('gapper/auth/gateway/register-form', $vars)
        ]);
    }

    private function _showError()
    {
        // unset($_SESSION['gapper-auth-gateway.username']);
        $view = (string)V(\Gini\Config::get('gapper.views')['client/error/401-group'] ?: 'gapper/client/error/401-group');
        return \Gini\IoC::construct('\Gini\CGI\Response\JSON', [
            'type'=> 'modal',
            'message'=> $view
        ]);
    }

    private function _getGatewayRPC()
    {
        return \Gini\Gapper\Auth\Gateway::getRPC();
    }

    private function _setTagData($gid, $data)
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

    private function _hasGroup()
    {
        $groups = \Gini\Gapper\Client::getGroups(\Gini\Gapper\Client::getUserName(), true);
        if (!$groups || !count($groups)) return false;
        return true;
    }

    private function _getUserInfoByGapperUser($username) {
        try {
            $rpc = \Gini\Gapper\Auth\Gateway::getRPC();
            $info = (array) $rpc->Gateway->People->getUser(['username' => $username]);
        } catch (\Exception $e) {
        }

        return (object)$info;
    }

    private function _getUserInfo($identity)
    {
        return (object) \Gini\Gapper\Auth\Gateway::getUserInfo($identity);
    }

}


