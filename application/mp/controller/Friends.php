<?php
// +----------------------------------------------------------------------
// | [RhaPHP System] Copyright (c) 2017 http://www.rhaphp.com/
// +----------------------------------------------------------------------
// | [RhaPHP] 并不是自由软件,你可免费使用,未经许可不能去掉RhaPHP相关版权
// +----------------------------------------------------------------------
// | Author: Geeson <qimengkeji@vip.qq.com>
// +----------------------------------------------------------------------


namespace app\mp\controller;

use app\common\model\MpFriends;
use think\facade\Cache;
use think\Db;
use think\facade\Request;

class Friends extends Base
{
    public $friendMode;

    public function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub
    }

    public function index()
    {
        $where = [];
        $nickname = '';
        $times = '';
        $need = '';
        $sex = '';
        if (Request::isGet()) {
            $_data = input('get.');
            $nickname = isset($_data['nickname']) ? $_data['nickname'] : '';
            $where=[];
            if(isset($_data['nickname'])){
                $where[] = ['nickname','like', "%{$_data['nickname']}%"];
            }
            if (isset($_data['times'])) {
                $times = explode('到', $_data['times']);
                if (count($times) == 2) {
                    $where[] = ['subscribe_time','between', [strtotime($times[0]), strtotime($times[1]) + 86400]];
                }
                $times = $_data['times'];
            }
            if (isset($_data['need']) && $_data['need'] == 1) {

                $where[] = ['last_time','>', (time() - (86400 * 2))];
                $need = 1;
            }
            if (isset($_data['sex']) && $_data['sex'] != 0) {
                $sex = $_data['sex'] ? $_data['sex'] : '0';
                $where[]=['sex','=',$_data['sex']];
            }
        }
        $post['nickname'] = $nickname;
        $post['times'] = $times;
        $post['need'] = $need;
        $post['sex'] = $sex;
        $FriendList = MpFriends::where(['mpid' => $this->mid, 'subscribe' => 1])
            ->where($where)
            ->order('subscribe_time DESC')->paginate(20);
        $this->assign('friendList', $FriendList);
        $this->assign('post', $post);
        return view();
    }

    public function SynFriends()
    {
        ini_set('max_execution_time', '0');
        $this->assign('url', '');
        $this->assign('jdtCss', '');
        $IN = input();
        $next_openid = isset($IN['next_openid']) ? $IN['next_openid'] : null;
        $wechatObj = getWechatActiveObj();
        $friendList = $wechatObj->getUserList($next_openid);
        if (!empty($friendList)) {
            $friends = [];
            $i = 0;
            foreach ($friendList['data']['openid'] as $key => $v) {
                $i++;
                if ($i > 50) {
                    $url = getHostDomain() . url('mp/Friends/SynFriends', ['next_openid' => $v]);
                    $jdtCss = abs(ceil((($friendList['count'] - $friendList['total']) / $friendList['total'] * 100)));
                    $this->assign('text', $jdtCss . '%');
                    $this->assign('jdtCss', $jdtCss . '%');
                    $this->assign('url', $url);
                    return view('friend');
                } else {
                    $friends[$key] = $wechatObj->getUserInfo($v);
                    $friends[$key]['mpid'] = $this->mid;
                    $friends[$key]['tagid_list'] = json_encode($friends[$key]['tagid_list']);
                    // $friends[$key]['nickname']=preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $friends[$key]['nickname']);
                    if (!empty($friends[$key])) {
                        if (Db::name('mp_friends')->where(['mpid' => $this->mid, 'openid' => $v])->field('id')->find()) {
                            Db::name('mp_friends')->where(['mpid' => $this->mid, 'openid' => $v])->update($friends[$key]);
                            $update = true;
                        } else {
                            $insert = Db::name('mp_friends')->where(['mpid' => $this->mid, 'openid' => $v])->insert($friends[$key]);
                        }
                    }
                }
            }
            $this->assign('jdtCss', '100%');
            $this->assign('text', '同步完成');
            return view('friend');
        } else {
            if ($wechatObj->errCode != '40001' && $wechatObj->errCode != '' && $wechatObj->errMsg != '') {
                exit('errCode:' . $wechatObj->errCode . 'errMsg:' . $wechatObj->errMsg);
            }
            $this->assign('jdtCss', '100%');
            $this->assign('text', '已没有可同步粉丝');
            return view('friend');
        }

    }

    public function synSelect()
    {
        $IN = input();
        if (!empty($IN) && isset($IN['openids'])) {
            $wechatObj = getWechatActiveObj();
            $friends = [];
            foreach ($IN['openids'] as $key => $val) {
                $results = $wechatObj->getUserInfo($val);
                if ($results) {
                    $friends[$key] =$results;
                    $friends[$key]['mpid'] = $this->mid;
                    $friends[$key]['tagid_list'] = json_encode($friends[$key]['tagid_list']);
                    //  $friends[$key]['nickname']=preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $friends[$key]['nickname']);
                    if (!empty($friends[$key])) {
                        if (Db::name('mp_friends')->where(['mpid' => $this->mid, 'openid' => $val])->find()) {
                            Db::name('mp_friends')->where(['mpid' => $this->mid, 'openid' => $val])->update($friends[$key]);
                            $update = true;
                        } else {
                            $insert = Db::name('mp_friends')->where(['mpid' => $this->mid, 'openid' => $val])->insert($friends[$key]);
                        }
                    }
                }else{
                    ajaxMsg('0', '遇到错误：errCode:'.$wechatObj->errCode.'errMsg:'.$wechatObj->errMsg);
                }
            }
            if ($update || $insert) {
                ajaxMsg('1', '同步成功');
            }

        } else {
            ajaxMsg('0', '你没有选择要同步的粉丝');
        }

    }


}