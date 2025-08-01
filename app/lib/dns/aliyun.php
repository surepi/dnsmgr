<?php

namespace app\lib\dns;

use app\lib\DnsInterface;
use app\lib\client\Aliyun as AliyunClient;
use Exception;

class aliyun implements DnsInterface
{
    private $AccessKeyId;
    private $AccessKeySecret;
    private $Endpoint = 'alidns.aliyuncs.com'; //API接入域名
    private $Version = '2015-01-09'; //API版本号
    private $error;
    private $domain;
    private $domainid;
    private $domainInfo;
    private AliyunClient $client;

    public function __construct($config)
    {
        $this->AccessKeyId = $config['ak'];
        $this->AccessKeySecret = $config['sk'];
        $proxy = isset($config['proxy']) ? $config['proxy'] == 1 : false;
        $this->client = new AliyunClient($this->AccessKeyId, $this->AccessKeySecret, $this->Endpoint, $this->Version, $proxy);
        $this->domain = $config['domain'];
    }

    public function getError()
    {
        return $this->error;
    }

    public function check()
    {
        if ($this->getDomainList() != false) {
            return true;
        }
        return false;
    }

    //获取域名列表
    public function getDomainList($KeyWord = null, $PageNumber = 1, $PageSize = 20)
    {
        $param = ['Action' => 'DescribeDomains', 'KeyWord' => $KeyWord, 'PageNumber' => $PageNumber, 'PageSize' => $PageSize];
        $data = $this->request($param, true);
        if ($data) {
            $list = [];
            foreach ($data['Domains']['Domain'] as $row) {
                $list[] = [
                    'DomainId' => $row['DomainId'],
                    'Domain' => $row['DomainName'],
                    'RecordCount' => $row['RecordCount'],
                ];
            }
            return ['total' => $data['TotalCount'], 'list' => $list];
        }
        return false;
    }

    //获取解析记录列表
    public function getDomainRecords($PageNumber = 1, $PageSize = 20, $KeyWord = null, $SubDomain = null, $Value = null, $Type = null, $Line = null, $Status = null)
    {
        $param = ['Action' => 'DescribeDomainRecords', 'DomainName' => $this->domain, 'PageNumber' => $PageNumber, 'PageSize' => $PageSize];
        if (!empty($SubDomain) || !empty($Type) || !empty($Line) || !empty($Value)) {
            $param += ['SearchMode' => 'ADVANCED', 'RRKeyWord' => $SubDomain, 'ValueKeyWord' => $Value, 'Type' => $Type, 'Line' => $Line];
        } elseif (!empty($KeyWord)) {
            $param += ['KeyWord' => $KeyWord];
        }
        if (!isNullOrEmpty($Status)) {
            $Status = $Status == '1' ? 'Enable' : 'Disable';
            $param += ['Status' => $Status];
        }
        $data = $this->request($param, true);
        if ($data) {
            $list = [];
            foreach ($data['DomainRecords']['Record'] as $row) {
                $list[] = [
                    'RecordId' => $row['RecordId'],
                    'Domain' => $row['DomainName'],
                    'Name' => $row['RR'],
                    'Type' => $row['Type'],
                    'Value' => $row['Value'],
                    'Line' => $row['Line'],
                    'TTL' => $row['TTL'],
                    'MX' => isset($row['Priority']) ? $row['Priority'] : null,
                    'Status' => $row['Status'] == 'ENABLE' ? '1' : '0',
                    'Weight' => isset($row['Weight']) ? $row['Weight'] : null,
                    'Remark' => isset($row['Remark']) ? $row['Remark'] : null,
                    'UpdateTime' => isset($row['UpdateTimestamp']) ? date('Y-m-d H:i:s', intval($row['UpdateTimestamp'] / 1000)) : null,
                ];
            }
            return ['total' => $data['TotalCount'], 'list' => $list];
        }
        return false;
    }

    //获取子域名解析记录列表
    public function getSubDomainRecords($SubDomain, $PageNumber = 1, $PageSize = 20, $Type = null, $Line = null)
    {
        $param = ['Action' => 'DescribeSubDomainRecords', 'SubDomain' => $SubDomain . '.' . $this->domain, 'PageNumber' => $PageNumber, 'PageSize' => $PageSize, 'Type' => $Type, 'Line' => $Line];
        $data = $this->request($param, true);
        if ($data) {
            $list = [];
            foreach ($data['DomainRecords']['Record'] as $row) {
                $list[] = [
                    'RecordId' => $row['RecordId'],
                    'Domain' => $row['DomainName'],
                    'Name' => $row['RR'],
                    'Type' => $row['Type'],
                    'Value' => $row['Value'],
                    'Line' => $row['Line'],
                    'TTL' => $row['TTL'],
                    'MX' => isset($row['Priority']) ? $row['Priority'] : null,
                    'Status' => $row['Status'] == 'ENABLE' ? '1' : '0',
                    'Weight' => isset($row['Weight']) ? $row['Weight'] : null,
                    'Remark' => isset($row['Remark']) ? $row['Remark'] : null,
                    'UpdateTime' => isset($row['UpdateTimestamp']) ? date('Y-m-d H:i:s', intval($row['UpdateTimestamp'] / 1000)) : null,
                ];
            }
            return ['total' => $data['TotalCount'], 'list' => $list];
        }
        return false;
    }

    //获取解析记录详细信息
    public function getDomainRecordInfo($RecordId)
    {
        $param = ['Action' => 'DescribeDomainRecordInfo', 'RecordId' => $RecordId];
        $data = $this->request($param, true);
        if ($data) {
            return [
                'RecordId' => $data['RecordId'],
                'Domain' => $data['DomainName'],
                'Name' => $data['RR'],
                'Type' => $data['Type'],
                'Value' => $data['Value'],
                'Line' => $data['Line'],
                'TTL' => $data['TTL'],
                'MX' => isset($data['Priority']) ? $data['Priority'] : null,
                'Status' => $data['Status'] == 'ENABLE' ? '1' : '0',
                'Weight' => isset($data['Weight']) ? $data['Weight'] : null,
                'Remark' => isset($data['Remark']) ? $data['Remark'] : null,
                'UpdateTime' => isset($row['UpdateTimestamp']) ? date('Y-m-d H:i:s', intval($data['UpdateTimestamp'] / 1000)) : null,
            ];
        }
        return false;
    }

    //添加解析记录
    public function addDomainRecord($Name, $Type, $Value, $Line = 'default', $TTL = 600, $MX = null, $Weight = null, $Remark = null)
    {
        $param = ['Action' => 'AddDomainRecord', 'DomainName' => $this->domain, 'RR' => $Name, 'Type' => $Type, 'Value' => $Value, 'Line' => $this->convertLineCode($Line), 'TTL' => intval($TTL)];
        if ($MX) {
            $param['Priority'] = intval($MX);
        }
        $data = $this->request($param, true);
        if ($data) {
            return $data['RecordId'];
        }
        return false;
    }

    //修改解析记录
    public function updateDomainRecord($RecordId, $Name, $Type, $Value, $Line = 'default', $TTL = 600, $MX = null, $Weight = null, $Remark = null)
    {
        $param = ['Action' => 'UpdateDomainRecord', 'RecordId' => $RecordId, 'RR' => $Name, 'Type' => $Type, 'Value' => $Value, 'Line' => $this->convertLineCode($Line), 'TTL' => intval($TTL)];
        if ($MX) {
            $param['Priority'] = intval($MX);
        }
        return $this->request($param);
    }

    //修改解析记录备注
    public function updateDomainRecordRemark($RecordId, $Remark)
    {
        $param = ['Action' => 'UpdateDomainRecordRemark', 'RecordId' => $RecordId, 'Remark' => $Remark];
        return $this->request($param);
    }

    //删除解析记录
    public function deleteDomainRecord($RecordId)
    {
        $param = ['Action' => 'DeleteDomainRecord', 'RecordId' => $RecordId];
        return $this->request($param);
    }

    //删除子域名的解析记录
    public function deleteSubDomainRecords($SubDomain)
    {
        $param = ['Action' => 'DeleteSubDomainRecords', 'DomainName' => $this->domain, 'RR' => $SubDomain];
        return $this->request($param);
    }

    //设置解析记录状态
    public function setDomainRecordStatus($RecordId, $Status)
    {
        $Status = $Status == '1' ? 'Enable' : 'Disable';
        $param = ['Action' => 'SetDomainRecordStatus', 'RecordId' => $RecordId, 'Status' => $Status];
        return $this->request($param);
    }

    //获取解析记录操作日志
    public function getDomainRecordLog($PageNumber = 1, $PageSize = 20, $KeyWord = null, $StartDate = null, $endDate = null)
    {
        $param = ['Action' => 'DescribeRecordLogs', 'DomainName' => $this->domain, 'PageNumber' => $PageNumber, 'PageSize' => $PageSize, 'KeyWord' => $KeyWord, 'StartDate' => $StartDate, 'endDate' => $endDate, 'Lang' => 'zh'];
        $data = $this->request($param, true);
        if ($data) {
            $list = [];
            foreach ($data['RecordLogs']['RecordLog'] as $row) {
                $list[] = ['time' => date('Y-m-d H:i:s', intval($row['ActionTimestamp'] / 1000)), 'data' => $row['Message']];
            }
            return ['total' => $data['TotalCount'], 'list' => $list];
        }
        return false;
    }

    //获取解析线路列表
    public function getRecordLine()
    {
        $data = $this->getDomainInfo();
        if ($data) {
            $list = [];
            foreach ($data['RecordLines']['RecordLine'] as $row) {
                $list[$row['LineCode']] = ['name' => $row['LineDisplayName'], 'parent' => isset($row['FatherCode']) ? $row['FatherCode'] : null];
            }
            return $list;
        }
        return false;
    }

    //获取域名信息
    public function getDomainInfo()
    {
        if (!empty($this->domainInfo)) return $this->domainInfo;
        $param = ['Action' => 'DescribeDomainInfo', 'DomainName' => $this->domain, 'NeedDetailAttributes' => 'true'];
        $data = $this->request($param, true);
        if ($data) {
            $this->domainInfo = $data;
            return $data;
        }
        return false;
    }

    //获取域名最低TTL
    public function getMinTTL()
    {
        $data = $this->getDomainInfo();
        if ($data) {
            return $data['MinTtl'];
        }
        return false;
    }

    //获取权重配置子域名列表
    public function getWeightSubDomains($PageNumber = 1, $PageSize = 20, $SubDomain = null)
    {
        $param = ['Action' => 'DescribeDNSSLBSubDomains', 'DomainName' => $this->domain, 'PageNumber' => $PageNumber, 'PageSize' => $PageSize];
        if (!empty($SubDomain)) {
            $param += ['Rr' => $SubDomain];
        }
        $data = $this->request($param, true);
        if ($data) {
            $list = $data['SlbSubDomains']['SlbSubDomain'];
            $i = 1;
            foreach ($list as &$v) {
                $v['id'] = $i++;
                $v['rr'] = substr($v['SubDomain'], 0, -strlen($this->domain) - 1);
            }
            return ['total' => $data['TotalCount'], 'list' => $list];
        }
        return false;
    }

    //开启关闭权重配置
    public function setWeightStatus($SubDomain, $Open, $Type = null, $Line = null)
    {
        $param = ['Action' => 'SetDNSSLBStatus', 'DomainName' => $this->domain, 'SubDomain' => $SubDomain, 'Open' => $Open == '1' ? 'true' : 'false'];
        if (!empty($Type)) {
            $param += ['Type' => $Type];
        }
        if (!empty($Line)) {
            $param += ['Line' => $Line];
        }
        return $this->request($param);
    }

    //修改权重
    public function updateRecordWeight($RecordId, $Weight)
    {
        $param = ['Action' => 'UpdateDNSSLBWeight', 'RecordId' => $RecordId, 'Weight' => $Weight];
        return $this->request($param);
    }

    public function addDomain($Domain)
    {
        $param = ['Action' => 'AddDomain', 'DomainName' => $Domain];
        $result = $this->request($param, true);
        if ($result) {
            return ['id' => $result['DomainId'], 'name' => $result['DomainName']];
        }
        return false;
    }

    private function convertLineCode($line)
    {
        $convert_dict = ['0' => 'default', '10=1' => 'unicom', '10=0' => 'telecom', '10=3' => 'mobile', '10=2' => 'edu', '3=0' => 'oversea', '10=22' => 'btvn', '80=0' => 'search', '7=0' => 'internal'];
        if (array_key_exists($line, $convert_dict)) {
            return $convert_dict[$line];
        }
        return $line;
    }

    private function request($param, $returnData = false)
    {
        if (empty($this->AccessKeyId) || empty($this->AccessKeySecret)) return false;
        try {
            $result = $this->client->request($param);
        } catch (Exception $e) {
            try {
                usleep(50000);
                $result = $this->client->request($param);
            } catch (Exception $e) {
                $this->setError($e->getMessage());
                return false;
            }
        }
        return $returnData ? $result : true;
    }

    private function setError($message)
    {
        $this->error = $message;
        //file_put_contents('logs.txt',date('H:i:s').' '.$message."\r\n", FILE_APPEND);
    }
}
