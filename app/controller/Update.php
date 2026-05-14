<?php
namespace app\controller;

use app\BaseController;
use think\facade\Config;
use think\facade\Db;
use think\facade\Cache;
use think\facade\Log;
use ZipArchive;
use Exception;

class Update extends BaseController
{
    // =========================================================
    // ⚠️ 必须修改：换成你自己的 GitHub 用户名和仓库名
    // 例如：'https://api.github.com/repos/surepi/dnsmgr/releases/latest'
    // =========================================================
    private $github_api = 'https://api.github.com/repos/netcccyun/dnsmgr/releases/latest';

    /**
     * 获取 GitHub API 请求上下文 (GitHub 要求必须带有 User-Agent)
     */
    private function getStreamContext($timeout = 10)
    {
        return stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: DnsMgr-Auto-Updater',
                    'Accept: application/vnd.github.v3+json'
                ],
                'timeout' => $timeout
            ]
        ]);
    }

    /**
     * 检查是否有新版本
     */
    public function check()
    {
        $currentVersion = Config::get('app.version', '1.0.0');
        
        try {
            $context = $this->getStreamContext(5);
            $response = file_get_contents($this->github_api, false, $context);
            
            if ($response === false) {
                throw new Exception("无法连接到 GitHub，请检查服务器网络");
            }

            $data = json_decode($response, true);
            if (!isset($data['tag_name'])) {
                throw new Exception("无法解析 GitHub 的版本信息");
            }
            
            // GitHub 版本号通常带 'v' (例如 v1.1.0)，需要去掉 'v' 才能正确比对
            $remoteVersion = ltrim($data['tag_name'], 'v');
            
            if (version_compare($remoteVersion, $currentVersion, '>')) {
                return json([
                    'code' => 0, 
                    'msg' => '发现新版本', 
                    'data' => [
                        'version'   => $remoteVersion,
                        'changelog' => $data['body'] ?? '暂无更新说明', // Release 的描述文本
                    ]
                ]);
            }
            
            return json(['code' => 1, 'msg' => '当前已是最新版本']);
            
        } catch (Exception $e) {
            Log::error('GitHub 检查更新失败：' . $e->getMessage());
            return json(['code' => -1, 'msg' => '检查更新失败：' . $e->getMessage()]);
        }
    }

    /**
     * 执行自动升级
     */
    public function process()
    {
        set_time_limit(300);
        ini_set('memory_limit', '256M');

        try {
            // 1. 重新请求 GitHub 获取下载地址
            $context = $this->getStreamContext(10);
            $response = file_get_contents($this->github_api, false, $context);
            $data = json_decode($response, true);
            
            // 2. 在 Release 的 Assets 中寻找更新包 (.zip)
            $downloadUrl = '';
            if (isset($data['assets']) && is_array($data['assets'])) {
                foreach ($data['assets'] as $asset) {
                    // 约定：你上传的更新包文件名最好包含 "update" 或 "dnsmgr"，且必须是 zip
                    if (strpos($asset['name'], '.zip') !== false) {
                        $downloadUrl = $asset['browser_download_url'];
                        break;
                    }
                }
            }

            if (empty($downloadUrl)) {
                throw new Exception("在最新的 Release 中没有找到有效的 .zip 更新包");
            }

            // 3. 设定路径
            $rootPath = root_path(); 
            $runtimePath = runtime_path();
            $zipPath = $runtimePath . 'update_' . date('YmdHis') . '.zip';

            // 4. 从 GitHub 下载更新包
            // 注意：GitHub 下载可能存在 302 重定向，file_get_contents 默认支持重定向
            $fileData = file_get_contents($downloadUrl, false, $context);
            if ($fileData === false) {
                throw new Exception("从 GitHub 下载包失败，可能是由于网络被墙或超时");
            }
            file_put_contents($zipPath, $fileData);

            // 5. 解压并覆盖文件
            $zip = new ZipArchive();
            if ($zip->open($zipPath) === TRUE) {
                if (!$zip->extractTo($rootPath)) {
                    $zip->close();
                    throw new Exception("解压覆盖文件失败，请检查目录权限");
                }
                $zip->close();
            } else {
                throw new Exception("ZIP 包损坏或无法打开");
            }

            // 6. 执行 SQL 变更 (如有)
            $sqlFile = $rootPath . 'app/sql/update.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                $this->executeSql($sql);
                @unlink($sqlFile); 
            }

            // 7. 清理现场
            @unlink($zipPath);
            Cache::clear();

            return json(['code' => 0, 'msg' => '升级成功，请刷新页面！']);
            
        } catch (Exception $e) {
            Log::error('GitHub 自动升级异常：' . $e->getMessage());
            return json(['code' => -1, 'msg' => '升级失败：' . $e->getMessage()]);
        }
    }

    private function executeSql($sql)
    {
        $sql = str_replace("\r", "\n", $sql);
        $queries = explode(";\n", $sql);
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                try {
                    Db::execute($query);
                } catch (Exception $e) {
                    Log::warning('SQL跳过/失败：' . $query);
                }
            }
        }
    }
}