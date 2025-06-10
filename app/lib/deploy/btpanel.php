<?php

namespace app\lib\deploy;

use app\lib\DeployInterface;
use Exception;

class btpanel implements DeployInterface
{
    private $logger;
    private $url;
    private $key;
    private $proxy;

    public function __construct($config)
    {
        $this->url = rtrim($config['url'], '/');
        $this->key = $config['key'];
        $this->proxy = $config['proxy'] == 1;
    }

    public function check()
    {
        if (empty($this->url) || empty($this->key)) throw new Exception('请填写面板地址和接口密钥');

        $path = '/config?action=get_config';
        $response = $this->request($path, []);
        $result = json_decode($response, true);
        if (isset($result['status']) && ($result['status']==1 || isset($result['sites_path']))) {
            return true;
        } else {
            throw new Exception(isset($result['msg']) ? $result['msg'] : '面板地址无法连接');
        }
    }

    public function deploy($fullchain, $privatekey, $config, &$info)
    {
        if ($config['type'] == '1') {
            $this->deployPanel($fullchain, $privatekey);
            $this->log("面板证书部署成功");
            return;
        }
        $sites = explode("\n", $config['sites']);
        $success = 0;
        $errmsg = null;
        foreach ($sites as $site) {
            $siteName = trim($site);
            if (empty($siteName)) continue;
            if ($config['type'] == '3') {
                try {
                    $this->deployDocker($siteName, $fullchain, $privatekey);
                    $this->log("Docker域名 {$siteName} 证书部署成功");
                    $success++;
                } catch (Exception $e) {
                    $errmsg = $e->getMessage();
                    $this->log("Docker域名 {$siteName} 证书部署失败：" . $errmsg);
                }
            } elseif ($config['type'] == '2') {
                try {
                    $this->deployMailSys($siteName, $fullchain, $privatekey);
                    $this->log("邮局域名 {$siteName} 证书部署成功");
                    $success++;
                } catch (Exception $e) {
                    $errmsg = $e->getMessage();
                    $this->log("邮局域名 {$siteName} 证书部署失败：" . $errmsg);
                }
            } else {
                try {
                    $this->deploySite($siteName, $fullchain, $privatekey);
                    $this->log("网站 {$siteName} 证书部署成功");
                    $success++;
                } catch (Exception $e) {
                    $errmsg = $e->getMessage();
                    $this->log("网站 {$siteName} 证书部署失败：" . $errmsg);
                }
            }
        }
        if ($success == 0) {
            throw new Exception($errmsg ? $errmsg : '要部署的网站不存在');
        }
    }

    private function deployPanel($fullchain, $privatekey)
    {
        $path = '/config?action=SavePanelSSL';
        $data = [
            'privateKey' => $privatekey,
            'certPem' => $fullchain,
        ];
        $response = $this->request($path, $data);
        $result = json_decode($response, true);
        if (isset($result['status']) && $result['status']) {
            return true;
        } elseif (isset($result['msg'])) {
            throw new Exception($result['msg']);
        } else {
            throw new Exception($response ? $response : '返回数据解析失败');
        }
    }

    private function deploySite($siteName, $fullchain, $privatekey)
    {
        $path = '/site?action=SetSSL';
        $data = [
            'type' => '0',
            'siteName' => $siteName,
            'key' => $privatekey,
            'csr' => $fullchain,
        ];
        $response = $this->request($path, $data);
        $result = json_decode($response, true);
        if (isset($result['status']) && $result['status']) {
            return true;
        } elseif (isset($result['msg'])) {
            throw new Exception($result['msg']);
        } else {
            throw new Exception($response ? $response : '返回数据解析失败');
        }
    }

    private function deployMailSys($domain, $fullchain, $privatekey)
    {
        $path = '/plugin?action=a&name=mail_sys&s=set_mail_certificate_multiple';
        $data = [
            'domain' => $domain,
            'key' => $privatekey,
            'csr' => $fullchain,
            'act' => 'add',
        ];
        $response = $this->request($path, $data);
        $result = json_decode($response, true);
        if (isset($result['status']) && $result['status']) {
            return true;
        } elseif (isset($result['msg'])) {
            throw new Exception($result['msg']);
        } else {
            throw new Exception($response ? $response : '返回数据解析失败');
        }
    }

    private function deployDocker($domain, $fullchain, $privatekey)
    {
        $path = '/mod/docker/com/set_ssl';
        $data = [
            'site_name' => $domain,
            'key' => $privatekey,
            'csr' => $fullchain,
        ];
        $response = $this->request($path, $data);
        $result = json_decode($response, true);
        if (isset($result['status']) && $result['status']) {
            return true;
        } elseif (isset($result['msg'])) {
            throw new Exception($result['msg']);
        } else {
            throw new Exception($response ? $response : '返回数据解析失败');
        }
    }

    public function setLogger($func)
    {
        $this->logger = $func;
    }

    private function log($txt)
    {
        if ($this->logger) {
            call_user_func($this->logger, $txt);
        }
    }

    private function request($path, $params)
    {
        $url = $this->url . $path;

        $now_time = time();
        $post_data = [
            'request_token' => md5($now_time . md5($this->key)),
            'request_time' => $now_time
        ];
        $post_data = array_merge($post_data, $params);
        $response = http_request($url, $post_data, null, null, null, $this->proxy);
        return $response['body'];
    }
}
