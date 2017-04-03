<?php

/*
  <#日期 = "2017-4-3">
  <#时间 = "8:42:54">
  <#人物 = "buff" >
  <#备注 = "爬虫升级版v1.0.1">已爬取http://www.quazero.com/
 */
class BuffCurl {
    protected
            $referer = null; //来源地址
    protected
            $max_time = 0; //最大下载时间
    protected
            $url_num = 1; //第一页网页的同级链接个数
    protected
            $url_connect = '_'; //同级目录分类样式 index_2.html 或者index-2.html
    protected
            $entrance_url = null; //入口文件
    protected
            $step = 0; //从入口文件到下载文件需要几步
    protected
            $cookie = null;
    protected
            $folderName = 'BuffCurl'; //保存图片的文件夹名
    protected
            $min_size_file = 10240; //低于此字节文件不保存
    protected
            $regexp = null; //正则搜索 替换
    public
            $down_pic = 0; //总下载数
    protected
            $down_timeout = 30; //最后一步下载超时
    protected
            $addSiteReplace = null; //网址渐进格式
    protected
            $current_connect = 50; //并发连接数
    public
            function __construct($data) {
        foreach ($data as $k => $v) {
            $this->$k = $v;
        }
        set_time_limit($this->max_time);
    }

    public
            function start() {
        $mh = curl_multi_init();
        $this->buffCurlMultiInit($mh, $ch);
        $ch = $this->buffCurlMultiExec($mh, $ch);
        $res = $this->buffGetCurlResult($ch);
        if ($this->step === 0) {
            if (is_array($res)) {
                $this->buffSavaFile($res);
            }
            else {
                echo $res;
                unset($res);
            }
        }
        else {
            $resDoReg = $this->buffDoRegexp($res);
            if (is_string($resDoReg)) {
                echo $resDoReg;
                return;
            }
            elseif (is_int($resDoReg)) {
                echo "时间".microtime(1)."未成功匹配,请检查正则表达式!\n";
                return;
            }
            else {
                $now_step = --$this->step;
                for ($i = 0; $i < count($resDoReg); $i++) {
                    $data = [
                        'entrance_url' => $resDoReg[$i],
                        'cookie' => $this->cookie,
                        'folderName' => $this->folderName,
                        'step' => $now_step,
                        'regexp' => $this->regexp,
                        "url_num" => count($resDoReg[$i]),
                        "url_connect" => $this->url_connect,
                        "down_timeout" => $this->down_timeout,
                        'addSiteReplace' => $this->addSiteReplace,
                        'current_connect' => $this->current_connect,
                        'referer' => $this->referer
                    ];
                    $download[$now_step][$i] = new BuffCurl($data);
                    $download[$now_step][$i]->down_pic=&$this->down_pic;
                    $download[$now_step][$i]->start();
                    unset($download[$now_step][$i]);
                }
            }
        }
    }

    /**
     * 
     * @param mixed $res  curl获取的结果,包含页面内容和页面url的数组或者错误代码字符串
     * @return mixed      
     *      0 :未成功匹配,请检查正则表达式
     *      string :返回由上层curl导致的错误代码字符串
     */
    protected
            function buffDoRegexp($res) {
        if (is_string($res)) {
            return $res;
        }
        $regexp = json_decode($this->regexp);
        $tempNum = $this->step - 1;
        $regexpSearch = $regexp[$tempNum]->search;
        $regexpReplace = $regexp[$tempNum]->replace;
        $regexpSNum = count($regexpSearch);
        $regexpRNum = count($regexpReplace);
        $pageNum = count($res);
        $allResultUrl = [];
        for ($j = 0; $j < $pageNum; $j++) {
            if ($regexpSNum !== 0) {
                for ($i = 0; $i < $regexpSNum - 1; $i++) {
                    preg_match($regexpSearch[$i], $res[$j][0], $url);
//                    echo "regexpS : ",$regexpSearch[$i],"\n";
//                    echo "res : ",$res[$j][0],"\n";
//                    echo "url : ",$url[0],"\n";
                    $res[$j][0] = $url[0];
                }
                if (preg_match_all($regexpSearch[$i], $res[$j][0], $resultUrl)) {
                    $allResultUrl[$j] = $resultUrl[0];
                    
                  foreach($allResultUrl[$j] as $k =>$v){  
                    if(substr($v,0,1)=='/'){
                       $v=preg_replace('~^/~','http://www.quazero.com/',$v);
                    }
                  }
                  
                }
                else {
                    echo "报错regexp为: ".$regexpSearch[$i]."\n";
                    echo "搜索内容为res: ".$res[$j][0]."\n";
                    return 0;
                }
            }
            elseif ($regexpRNum !== 0) {
                for ($i = 0; $i < $regexpRNum; $i++) {
                    $res[$j][1] = str_replace($regexpReplace[$i][0], $regexpReplace[$i][1], $res[$j][1]);
                }
                $resultUrl[0] = $res[$j][1];
                $allResultUrl[$j] = $resultUrl;
            }
        }
        return $allResultUrl;
    }

    protected
            function buffCheckFileName(&$fileName, $num = 1) {
        $num_str = '_' . $num;
        if (file_exists(dirname(__FILE__) . '/' . $this->folderName . '/' . $fileName)) {
            $fileName = substr_replace($fileName, $num_str, -4, 0);
            $num++;
            return $this->buffCheckFileName($fileName, $num);
        }
        else {
            return;
        }
    }

    protected
            function buffSavaFile($res) {
        for ($i = 0; $i < count($res); $i++) {
            $fileName = basename($res[$i][1]);
            if (!file_exists(dirname(__FILE__) . '/' . $this->folderName)) {
                mkdir(dirname(__FILE__) . '/' . $this->folderName);
            }
            $this->buffCheckFileName($fileName);
            $downFileName = dirname(__FILE__) . '/' . $this->folderName . '/' . $fileName;
            if (file_put_contents($downFileName, $res[$i][0])) {
                $fileSize = filesize($downFileName);
                if ($fileSize < $this->min_size_file) {
                    unlink($downFileName);
                    echo "文件太小 " . ceil($fileSize /= 1024) . "k 已删除\n";
                }
                else {
                    $fileSize /= 1024;
                    $this->down_pic++;
                    echo "保存文件成功--文件大小:" . ceil($fileSize) . 'k ' . $fileName . "\n当前共下载{$this->down_pic}张图片\n";
                }
            }
            else {
                echo "保存文件失败!\n";
            }
        }
        unset($res);
    }

    protected
            function buffGetCurlResult($ch) {
        foreach ($ch as $k => $v) {
            $http_code = curl_getinfo($v, CURLINFO_HTTP_CODE);
            $http_url = curl_getinfo($v, CURLINFO_EFFECTIVE_URL);
            if ($http_code >= 400) {
                $res = "错误http代码" . $http_code . " url地址" . $http_url . "\n";
                curl_close($v);
                continue;
            }
            $res[$k][0] = curl_multi_getcontent($v);
            $res[$k][1] = $http_url;
            curl_close($v);
        }
        return $res;
    }

    protected
            function buffCurlMultiExec($mh, $ch) {
//        $nowtime = microtime(1);
        $now_curl_handle = $this->current_connect;
        do {
            //$mrc =0表示已经全部传输完毕 -1 表示还有子连接要传输
            //$active  表示还剩多少个会话  未完成的子连接
            $mrc = curl_multi_exec($mh, $active);
//            curl_multi_select($mh);
// 获取当前连接的信息, $msgq是当前队列中还有多少条消息  
            $info = curl_multi_info_read($mh, $msgq);
            if ($info) {
//                $nowtime2=microtime(1);
                $result[] = $info['handle'];

                if ($now_curl_handle < $this->url_num) {
                    curl_multi_add_handle($mh, $ch[$now_curl_handle]);
                    $now_curl_handle++;
                }

                curl_multi_remove_handle($mh, $info['handle']);
//                echo "\$active=$active \$mrc=$mrc\n";
            }
        }
        while ($active && $mrc == CURLM_OK || $msgq > 0);
        curl_multi_close($mh);
//        echo "执行multi句柄共用时", microtime(1) - $nowtime, "\n";
        return $result;
    }

    protected
            function buffCurlMultiInit($mh, &$ch) {
//        $nowtime = microtime(1);
        for ($i = 0; $i < $this->url_num; $i++) {
            $ch[$i] = curl_init();
            $this->buffSetCurlOpt($ch[$i], $i);
        }
//        $nowtime2 = microtime(1);
//        echo "初始化为curl 添加配置用时", $nowtime2 - $nowtime, "秒\n";
        for ($j = 0; $j < $this->current_connect && $j < $this->url_num; $j++) {
            curl_multi_add_handle($mh, $ch[$j]);
        }
//        $nowtime3 = microtime(1);
//        echo "初始化为multi_curl 添加子句柄", $nowtime3 - $nowtime2, "秒\n";
//        echo "初始化共用时", microtime(1) - $nowtime, "\n";
    }

    /**
     * @param  string   $ch 传入的curl句柄 
     * @param  int      $i 当前的页码
     * @affect 设置curl的配置
     * @return null  
     */
    protected
            function buffSetCurlOpt($ch, $i) {
        $ip = rand(58, 220) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);
        $headerArr = ['X-FORWARDED-FOR:' . $ip, 'CLIENT-IP:' . $ip];
        $url = is_array($this->entrance_url) ? $this->entrance_url[$i] : $this->buffAddThisSite($this->entrance_url, $i);
        $opt = [
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1;Win64;x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3026.3 Safari/537.36',
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_TIMEOUT => $this->down_timeout,
            CURLOPT_HTTPHEADER => $headerArr,
            CURLOPT_HEADER => 0,
            CURLOPT_REFERER => isset($this->referer) ? $this->referer : $url,
            CURLOPT_COOKIE => $this->cookie
        ];
        curl_setopt_array($ch, $opt);
    }

    /**
     * @param  string   $url 传入的初始url 
     * @param  int      $i 当前的页码 
     * @return string  传入子curl的正确url
     */
    protected
            function buffAddThisSite($url, $i) {
        if ($i === 0) {
            return $url;
        }
        else {
            $replace = $this->addSiteReplace[1] . $this->url_connect . ++$i;
            return str_replace($this->addSiteReplace[0], $replace, $url);
        }
    }

}

$data = [
    'entrance_url' => 'http://www.quazero.com/a/daizitupian/list_11_1.html',
    'cookie' => '__cfduid=d7fd08aaea33e3fd9a22b8e72fd9f224e1491174264; BDTUJIAID=e31e6f6a312b83a5b557d1df52c98a09',
    'folderName' => '带字美图',
    'step' => 2,
    'regexp' => '[
                    {
                        "search": [
                       "~(?<=id=\"bbbb\")[\\\S\\\s]*(?=<\/ul>)~",
                         "/(?<=img src=[\"\'])[^\"\']*(?=[\"\'])/"
                        ],
                        "replace": [
                          
                        ]
                    },
                    {
                        "search": [
                       "~(?<=class=\"row\")[\\\S\\\s]*(?=class=\"pages)~",
                         "/(?<=href=[\"\'])[^\"\']*(?=[\"\'])/"
                        ],
                        "replace": [
                          
                        ]
                    }
                ]',
    "url_num" => 4,
    "url_connect" => '_',
    "down_timeout" => 30,
    'addSiteReplace' => ['list_11_1', 'list_11'],
    'current_connect' => 30
];
$nowtime = microtime(1);
echo "now time" . $nowtime . "\n";
$down = new BuffCurl($data);
$down->start();
$aftertime = microtime(1);
echo "now time is ", $aftertime ."\n";
echo "all the time is ", $aftertime - $nowtime . "\n";
