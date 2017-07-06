<?php

/*
  <#日期 = "2017-4-3">
  <#时间 = "8:42:54">
  <#人物 = "buff" >
  <#备注 = "爬虫curlv1.0.2">
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
    /**
     * 配置
     * @param array $data
     */
    public
            function __construct($data) {
        foreach ($data as $k => $v) {
            $this->$k = $v;
        }
        set_time_limit($this->max_time);
    }
    /**
 * 开始下载入口
 * @return null
 */
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
                echo "时间" . microtime(1) . "未成功匹配,请检查正则表达式!\n";
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
                    $download[$now_step][$i]->down_pic = &$this->down_pic;
                    $download[$now_step][$i]->start();
                    unset($download[$now_step][$i]);
                }
            }
        }
    }

    /**
     * 为页面内容执行正则搜素和替换
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
                    $res[$j][0] = $url[0];
                }
                if (preg_match_all($regexpSearch[$i], $res[$j][0], $resultUrl)) {
                    $allResultUrl[$j] = $resultUrl[0];

                }else {
                    echo "报错regexp为: " . $regexpSearch[$i] . "\n";
                    echo "报错所在页面为: " . $res[$j][1] . "\n";
                    echo "搜索内容为res: " . $res[$j][0] . "\n";
                    return 0;
                }
            }
            if($regexpRNum !== 0) {
                    if (count($allResultUrl) !== 0) {
                        foreach ($allResultUrl[$j] as $k => $v) {
                            $allResultUrl[$j][$k] = str_replace($regexpReplace[0], $regexpReplace[1], $v);
                        }
                    }
                    else {
                        $res[$j][1] = str_replace($regexpReplace[0], $regexpReplace[1], $res[$j][1]);
                        $resultUrl[0] = $res[$j][1];
                        $allResultUrl[$j] = $resultUrl;
                    }
                
            }
        }
        return $allResultUrl;
    }

    /**
     * 检查文件名是否已存在,如果存在在后面加上 _1 _2 _3 _4...
     * @param string $fileName
     * @param int $num
     * @return null
     */
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

    /**
     *  保存文件
     * @param mixed $res
     */
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

    /**
     * 获取curl执行后得到的页面内容数组 或者返回错误代码
     * @param mixed $ch
     * @return mixed 
     */
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
    /**
     * 执行$mh的所有子连接 并移除句柄之后返回子curl句柄数组
     * @param resource $mh
     * @param array $ch
     * @return mixed
     */
    protected
            function buffCurlMultiExec($mh, $ch) {
        $now_curl_handle = $this->current_connect;
        do {
            $mrc = curl_multi_exec($mh, $active);
            $info = curl_multi_info_read($mh, $msgq);
            if ($info) {
                $result[] = $info['handle'];
                if ($now_curl_handle < $this->url_num) {
                    curl_multi_add_handle($mh, $ch[$now_curl_handle]);
                    $now_curl_handle++;
                }
                curl_multi_remove_handle($mh, $info['handle']);
            }else{
                usleep(1000);
            }
        }
        while ($active && $mrc == CURLM_OK || $msgq > 0);
        curl_multi_close($mh);
        return $result;
    }
    /**
     * 初始化curl并添加到curl_multi
     * @param resource $mh
     * @param array $ch
     */
    protected
            function buffCurlMultiInit($mh, &$ch) {
        for ($i = 0; $i < $this->url_num; $i++) {
            $ch[$i] = curl_init();
            $this->buffSetCurlOpt($ch[$i], $i);
        }
        for ($j = 0; $j < $this->current_connect && $j < $this->url_num; $j++) {
            curl_multi_add_handle($mh, $ch[$j]);
        }
    }

    /**
     * @param  string   $ch 传入的curl句柄 
     * @param  int      $i 当前的页码
     * @affect 设置curl的配置
     * @return null  
     */
    protected
            function buffSetCurlOpt($ch, $i) {
        $ip = mt_rand(58, 220) . '.' . mt_rand(0, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(0, 255);
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
//这里放了4个data 就是demo用的,分别是从step 0-3 四层测试下载.以及不同的regexp
$data0 = [
    'entrance_url' => 'http://img.ivsky.com/img/tupian/pic/201007/13/laohu-010.jpg',
    'cookie' => 'UM_distinctid=15b106fd471d3e-0bd4ac226a5ba8-79482645-1fa400-15b106fd472a0a; CNZZDATA1259295795=1187515984-1491099760-http%253A%252F%252Fwww.ivsky.com%252F%7C1491099760; CNZZDATA1254368934=771597025-1490637474-http%253A%252F%252Fwww.ivsky.com%252F%7C1491200194; CNZZDATA87348=cnzz_eid%3D330874521-1490626948-null%26ntime%3D1491258755; Hm_lvt_862071acf8e9faf43a13fd4ea795ff8c=1491024144,1491063837,1491204750,1491260529; Hm_lpvt_862071acf8e9faf43a13fd4ea795ff8c=1491260529; CNZZDATA1629164=cnzz_eid%3D1938686851-1490627119-null%26ntime%3D1491256536',
    'folderName' => '野生动物',
    'step' => 0,
    "down_timeout" => 30,
    'min_size_file' => 10240
    ];
$data1 = [
    'entrance_url' => 'http://www.ivsky.com/download_pic.html?picurl=/img/tupian/pic/201007/13/laohu-010.jpg',
    'cookie' => 'UM_distinctid=15b106fd471d3e-0bd4ac226a5ba8-79482645-1fa400-15b106fd472a0a; CNZZDATA1259295795=1187515984-1491099760-http%253A%252F%252Fwww.ivsky.com%252F%7C1491099760; CNZZDATA1254368934=771597025-1490637474-http%253A%252F%252Fwww.ivsky.com%252F%7C1491200194; CNZZDATA87348=cnzz_eid%3D330874521-1490626948-null%26ntime%3D1491258755; Hm_lvt_862071acf8e9faf43a13fd4ea795ff8c=1491024144,1491063837,1491204750,1491260529; Hm_lpvt_862071acf8e9faf43a13fd4ea795ff8c=1491260529; CNZZDATA1629164=cnzz_eid%3D1938686851-1490627119-null%26ntime%3D1491256536',
    'folderName' => '野生动物',
    'step' => 1,
    'regexp' => '[
                    {
                        "search": [
                       
                        ],
                        "replace": [
                           ["http:\/\/www.ivsky.com\/download_pic.html?picurl="],
                           ["http:\/\/img.ivsky.com"]
                        ]
                    }
                ]',
    "url_num" => 1,
    "down_timeout" => 30,
    'min_size_file' => 10240
];
$data2 = [
    'entrance_url' => 'http://www.ivsky.com/tupian/laohu_v24/pic_574.html',
    'cookie' => 'UM_distinctid=15b106fd471d3e-0bd4ac226a5ba8-79482645-1fa400-15b106fd472a0a; CNZZDATA1259295795=1187515984-1491099760-http%253A%252F%252Fwww.ivsky.com%252F%7C1491099760; CNZZDATA1254368934=771597025-1490637474-http%253A%252F%252Fwww.ivsky.com%252F%7C1491200194; CNZZDATA87348=cnzz_eid%3D330874521-1490626948-null%26ntime%3D1491258755; Hm_lvt_862071acf8e9faf43a13fd4ea795ff8c=1491024144,1491063837,1491204750,1491260529; Hm_lpvt_862071acf8e9faf43a13fd4ea795ff8c=1491260529; CNZZDATA1629164=cnzz_eid%3D1938686851-1490627119-null%26ntime%3D1491256536',
    'folderName' => '野生动物',
    'step' => 2,
    'regexp' => '[
                    {
                        "search": [
                       
                        ],
                        "replace": [
                           ["http:\/\/www.ivsky.com\/download_pic.html?picurl="],
                           ["http:\/\/img.ivsky.com"]
                        ]
                    },
                    {
                         "search": [
                         "/(?<=id=\"imgis\" src=[\'\"])[^\'\"]*jpg(?=[\'\"] alt)/"
                        ],
                        "replace": [
                            ["http:\/\/img.ivsky.com","\/img\/tupian\/pre"],
                             ["http:\/\/www.ivsky.com","\/download_pic.html?picurl=\/img\/tupian\/pic"]
                        ]
                    }
                ]',
    "url_num" => 1,
    "down_timeout" => 30,
    'min_size_file' => 10240
];
$data3 = [
    'entrance_url' => 'http://www.ivsky.com/tupian/yeshengdongwu_t81/index.html',
    'cookie' => 'UM_distinctid=15b106fd471d3e-0bd4ac226a5ba8-79482645-1fa400-15b106fd472a0a; CNZZDATA1259295795=1187515984-1491099760-http%253A%252F%252Fwww.ivsky.com%252F%7C1491099760; CNZZDATA1254368934=771597025-1490637474-http%253A%252F%252Fwww.ivsky.com%252F%7C1491200194; CNZZDATA87348=cnzz_eid%3D330874521-1490626948-null%26ntime%3D1491258755; Hm_lvt_862071acf8e9faf43a13fd4ea795ff8c=1491024144,1491063837,1491204750,1491260529; Hm_lpvt_862071acf8e9faf43a13fd4ea795ff8c=1491260529; CNZZDATA1629164=cnzz_eid%3D1938686851-1490627119-null%26ntime%3D1491256536',
    'folderName' => '野生动物',
    'step' => 3,
    'regexp' => '[
                    {
                        "search": [
                       
                        ],
                        "replace": [
                           ["http:\/\/www.ivsky.com\/download_pic.html?picurl="],
                           ["http:\/\/img.ivsky.com"]
                        ]
                    },
                    {
                         "search": [
                         "/(?<=id=\"imgis\" src=[\'\"])[^\'\"]*jpg(?=[\'\"] alt)/"
                        ],
                        "replace": [
                            ["http:\/\/img.ivsky.com","\/img\/tupian\/pre"],
                             ["http:\/\/www.ivsky.com","\/download_pic.html?picurl=\/img\/tupian\/pic"]
                        ]
                    },
                    {
                         "search": [
                         "~(?<=class=\"pli\")[\\\S\\\s]*(?=id=\"tplistleft1\")~",
                         "/(?<=href=\")[^\"]*(?=\" ti)/"
                        ],
                        "replace": [
                            ["\/tupian"],
                            ["http:\/\/www.ivsky.com\/tupian"]
                        ]
                    }
                ]',
    "url_num" => 180,
    "url_connect" => '_',
    "down_timeout" => 30,
    'addSiteReplace' => ['index', 'index'],
    'current_connect' => 30,
    'min_size_file' => 10240
];
$nowtime = microtime(1);
echo "运行开始现在时间" . $nowtime . "\n";
$down = new BuffCurl($data3);
$down->start();
$aftertime = microtime(1);
echo "运行完毕现在时间 : ", $aftertime . "\n";
echo "共用时  ", $aftertime - $nowtime . "秒\n";
