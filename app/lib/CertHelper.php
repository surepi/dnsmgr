<?php

namespace app\lib;

use think\facade\Db;

class CertHelper
{
    public static $cert_config = [
        'letsencrypt' => [
            'name' => 'Let\'s Encrypt',
            'class' => 1,
            'icon' => 'letsencrypt.ico',
            'wildcard' => true,
            'max_domains' => 100,
            'cname' => true,
            'note' => null,
            'inputs' => [
                'email' => [
                    'name' => '邮箱地址',
                    'type' => 'input',
                    'placeholder' => '用于注册Let\'s Encrypt账号',
                    'required' => true,
                ],
                'mode' => [
                    'name' => '环境选择',
                    'type' => 'radio',
                    'options' => [
                        'live' => '正式环境',
                        'staging' => '测试环境',
                    ],
                    'value' => 'live'
                ],
                'proxy' => [
                    'name' => '使用代理服务器',
                    'type' => 'radio',
                    'options' => [
                        '0' => '否',
                        '1' => '是',
                    ],
                    'value' => '0'
                ],
            ]
        ],
        'zerossl' => [
            'name' => 'ZeroSSL',
            'class' => 1,
            'icon' => 'zerossl.ico',
            'wildcard' => true,
            'max_domains' => 100,
            'cname' => true,
            'note' => '<a href="https://app.zerossl.com/developer" target="_blank" rel="noreferrer">ZeroSSL密钥手动获取</a>',
            'inputs' => [
                'email' => [
                    'name' => '邮箱地址',
                    'type' => 'input',
                    'placeholder' => 'EAB申请邮箱',
                    'required' => true,
                ],
                'eabMode' => [
                    'name' => 'EAB获取方式',
                    'type' => 'radio',
                    'options' => [
                        'auto' => '自动获取',
                        'manual' => '手动输入',
                    ],
                    'value' => 'manual'
                ],
                'kid' => [
                    'name' => 'EAB KID',
                    'type' => 'input',
                    'placeholder' => '',
                    'required' => true,
                    'show' => 'eabMode==\'manual\'',
                ],
                'key' => [
                    'name' => 'EAB HMAC Key',
                    'type' => 'input',
                    'placeholder' => '',
                    'required' => true,
                    'show' => 'eabMode==\'manual\'',
                ],
                'proxy' => [
                    'name' => '使用代理服务器',
                    'type' => 'radio',
                    'options' => [
                        '0' => '否',
                        '1' => '是',
                    ],
                    'value' => '0'
                ],
            ]
        ],
        'google' => [
            'name' => 'Google SSL',
            'class' => 1,
            'icon' => 'google.ico',
            'wildcard' => true,
            'max_domains' => 100,
            'cname' => true,
            'note' => 'EAB支持通过第三方接口<a href="https://panel.haozi.net" target="_blank" rel="noreferrer">（耗子面板提供）</a>自动获取（不支持测试环境）或手动输入，<a href="https://cloud.google.com/certificate-manager/docs/public-ca-tutorial" target="_blank" rel="noreferrer">查看Google SSL账户手动配置说明</a>',
            'inputs' => [
                'email' => [
                    'name' => '邮箱地址',
                    'type' => 'input',
                    'placeholder' => 'EAB申请邮箱',
                    'required' => true,
                ],
                'eabMode' => [
                    'name' => 'EAB获取方式',
                    'type' => 'radio',
                    'options' => [
                        'auto' => '自动获取',
                        'manual' => '手动输入',
                    ],
                    'value' => 'manual'
                ],
                'kid' => [
                    'name' => 'keyId',
                    'type' => 'input',
                    'placeholder' => '',
                    'required' => true,
                    'show' => 'eabMode==\'manual\'',
                ],
                'key' => [
                    'name' => 'b64MacKey',
                    'type' => 'input',
                    'placeholder' => '',
                    'required' => true,
                    'show' => 'eabMode==\'manual\'',
                ],
                'mode' => [
                    'name' => '环境选择',
                    'type' => 'radio',
                    'options' => [
                        'live' => '正式环境',
                        'staging' => '测试环境',
                    ],
                    'value' => 'live'
                ],
                'proxy' => [
                    'name' => '使用代理服务器',
                    'type' => 'radio',
                    'options' => [
                        '0' => '否',
                        '1' => '是',
                        '2' => '是（反向代理）'
                    ],
                    'value' => '0'
                ],
                'proxy_url' => [
                    'name' => '反向代理地址',
                    'type' => 'input',
                    'placeholder' => 'https://gts.rat.dev',
                    'required' => true,
                    'show' => 'proxy==2',
	                'note' => '反向代理配置参考：
					<pre>resolver 8.8.8.8 ipv6=off valid=300s;
resolver_timeout 10s;

location / {
    set $empty "";
    proxy_pass https://dv.acme-v02.api.pki.goog$empty;
    proxy_set_header Accept-Encoding "";
    proxy_ssl_session_reuse off;
    proxy_ssl_server_name on;
    proxy_ssl_protocols TLSv1.2 TLSv1.3;
    proxy_http_version 1.1;

    sub_filter_once off;
    sub_filter_types *;
    sub_filter \'dv.acme-v02.api.pki.goog\' \'gts.rat.dev\'; # 替换自己的域名
}</pre>',
                ],
            ]
        ],
        'tencent' => [
            'name' => '腾讯云免费SSL',
            'class' => 2,
            'icon' => 'tencent.png',
            'wildcard' => false,
            'max_domains' => 1,
            'cname' => false,
            'note' => '一个账号有50张免费证书额度，证书到期或吊销可释放额度。<a href="https://cloud.tencent.com/document/product/400/89868" target="_blank" rel="noreferrer">腾讯云免费SSL简介与额度说明</a>',
            'inputs' => [
                'SecretId' => [
                    'name' => 'SecretId',
                    'type' => 'input',
                    'placeholder' => '',
                    'required' => true,
                ],
                'SecretKey' => [
                    'name' => 'SecretKey',
                    'type' => 'input',
                    'placeholder' => '',
                    'required' => true,
                ],
                'email' => [
                    'name' => '邮箱地址',
                    'type' => 'input',
                    'placeholder' => '申请证书时填写的邮箱',
                    'required' => true,
                ],
                'proxy' => [
                    'name' => '使用代理服务器',
                    'type' => 'radio',
                    'options' => [
                        '0' => '否',
                        '1' => '是',
                    ],
                    'value' => '0'
                ],
            ]
        ],
        'aliyun' => [
            'name' => '阿里云免费SSL',
            'class' => 2,
            'icon' => 'aliyun.png',
            'wildcard' => false,
            'max_domains' => 1,
            'cname' => false,
            'note' => '每个自然年有20张免费证书额度，证书到期或吊销不释放额度。需要先进入阿里云控制台-<a href="https://yundun.console.aliyun.com/?p=cas#/certExtend/free/cn-hangzhou" target="_blank" rel="noreferrer">数字证书管理服务</a>，购买个人测试证书资源包。',
            'inputs' => [
                'AccessKeyId' => [
                    'name' => 'AccessKeyId',
                    'type' => 'input',
                    'placeholder' => '',
                    'required' => true,
                ],
                'AccessKeySecret' => [
                    'name' => 'AccessKeySecret',
                    'type' => 'input',
                    'placeholder' => '',
                    'required' => true,
                ],
                'username' => [
                    'name' => '姓名',
                    'type' => 'input',
                    'placeholder' => '申请联系人的姓名',
                    'required' => true,
                ],
                'phone' => [
                    'name' => '手机号码',
                    'type' => 'input',
                    'placeholder' => '申请联系人的手机号码',
                    'required' => true,
                ],
                'email' => [
                    'name' => '邮箱地址',
                    'type' => 'input',
                    'placeholder' => '申请联系人的邮箱地址',
                    'required' => true,
                ],
                'proxy' => [
                    'name' => '使用代理服务器',
                    'type' => 'radio',
                    'options' => [
                        '0' => '否',
                        '1' => '是',
                    ],
                    'value' => '0'
                ],
            ]
        ],
        'ucloud' => [
            'name' => 'UCloud免费SSL',
            'class' => 2,
            'icon' => 'ucloud.ico',
            'wildcard' => false,
            'max_domains' => 1,
            'cname' => false,
            'note' => '一个账号有40张免费证书额度，证书到期或吊销可释放额度。',
            'inputs' => [
                'PublicKey' => [
                    'name' => '公钥',
                    'type' => 'input',
                    'placeholder' => '',
                    'required' => true,
                ],
                'PrivateKey' => [
                    'name' => '私钥',
                    'type' => 'input',
                    'placeholder' => '',
                    'required' => true,
                ],
                'username' => [
                    'name' => '姓名',
                    'type' => 'input',
                    'placeholder' => '申请联系人的姓名',
                    'required' => true,
                ],
                'phone' => [
                    'name' => '手机号码',
                    'type' => 'input',
                    'placeholder' => '申请联系人的手机号码',
                    'required' => true,
                ],
                'email' => [
                    'name' => '邮箱地址',
                    'type' => 'input',
                    'placeholder' => '申请联系人的邮箱地址',
                    'required' => true,
                ],
            ]
        ],
        'customacme' => [
            'name' => '自定义ACME',
            'class' => 1,
            'icon' => 'ssl.ico',
            'wildcard' => true,
            'max_domains' => 100,
            'cname' => true,
            'note' => null,
            'inputs' => [
                'directory' => [
                    'name' => 'ACME地址',
                    'type' => 'input',
                    'placeholder' => 'ACME Directory 地址',
                    'required' => true,
                ],
                'email' => [
                    'name' => '邮箱地址',
                    'type' => 'input',
                    'placeholder' => '证书申请邮箱',
                    'required' => true,
                ],
                'kid' => [
                    'name' => 'EAB KID',
                    'type' => 'input',
                    'placeholder' => '留空则不使用EAB认证',
                ],
                'key' => [
                    'name' => 'EAB HMAC Key',
                    'type' => 'input',
                    'placeholder' => '留空则不使用EAB认证',
                ],
                'proxy' => [
                    'name' => '使用代理服务器',
                    'type' => 'radio',
                    'options' => [
                        '0' => '否',
                        '1' => '是',
                    ],
                    'value' => '0'
                ],
            ]
        ],
    ];

    public static $class_config = [
        1 => '基于ACME的SSL证书',
        2 => '云服务商的SSL证书',
    ];

    public static function getList()
    {
        return self::$cert_config;
    }

    private static function getConfig($aid)
    {
        $account = Db::name('cert_account')->where('id', $aid)->find();
        if (!$account) return false;
        return $account;
    }

    public static function getInputs($type, $config = null)
    {
        $config = $config ? json_decode($config, true) : [];
        $inputs = self::$cert_config[$type]['inputs'];
        foreach ($inputs as &$input) {
            if (isset($config[$input['name']])) {
                $input['value'] = $config[$input['name']];
            }
        }
        return $inputs;
    }

    /**
     * @return CertInterface|bool
     */
    public static function getModel($aid)
    {
        $account = self::getConfig($aid);
        if (!$account) return false;
        $type = $account['type'];
        $class = "\\app\\lib\\cert\\{$type}";
        if (class_exists($class)) {
            $config = json_decode($account['config'], true);
            $ext = $account['ext'] ? json_decode($account['ext'], true) : null;
            $model = new $class($config, $ext);
            return $model;
        }
        return false;
    }

    /**
     * @return CertInterface|bool
     */
    public static function getModel2($type, $config, $ext = null)
    {
        $class = "\\app\\lib\\cert\\{$type}";
        if (class_exists($class)) {
            $model = new $class($config, $ext);
            return $model;
        }
        return false;
    }

    public static function getPfx($fullchain, $privatekey, $pwd = '123456')
    {
        openssl_pkcs12_export($fullchain, $pfx, $privatekey, $pwd);
        return $pfx;
    }
}
