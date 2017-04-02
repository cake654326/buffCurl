<?php

/* <#日期 = "2017-4-1">
 * <#时间 = "03:27:27">
 * <#人物 = "buff" >
 * <#备注 = "爬虫类v1过程化">
 * <#版本 = v1.0.1>
 * <#支持 step1> 兼容3层
 */
/*
 * $max_time 总下载时间限制 default:0
 * $url_num  总共有多少个第一页的url default:1
 * $url_connect 第一页和第二页的连接符样式 index_2.html 或者index-2.html default :'_'
 * $entrance_url 入口url
 * $step 步数 0表示终点文件 1表示终点文件的上一层 ... default :0
 * $cookie  
 * $folderName 保存的文件夹名
 * $min_size_file 低于此大小文件不保存 default
 */
class buffCurl {
    private
            $referer = null; //来源地址
    private
            $max_time = 0; //最大下载时间
    private
            $url_num = 1; //第一页网页的同级链接个数
    private
            $url_connect = '_'; //同级目录分类样式 index_2.html 或者index-2.html
    private
            $entrance_url = ''; //入口文件
    private
            $step = 0; //从入口文件到下载文件需要几步
    private
            $cookie = '';
    private
            $folderName = 'buffCurl'; //保存图片的文件夹名
    private
            $min_size_file = 10240; //低于此字节文件不保存
    private
            $regexp = null; //
    private
            $down_pic = 0; //总下载数
    public
            function __construct($data) {
        foreach ($data as $k => $v) {
            $this->$k = $v;
        }
        set_time_limit($this->max_time);
    }
    public
            function start() {
        if ($this->step === 0) {
            $ch = curl_init();
            $referer = $this->referer ? $this->referer : $this->entrance_url;
            $this->buffSetOpt($ch, $this->entrance_url, $referer, $this->cookie);
            $res = curl_exec($ch);
            if (!$res) {
                die("获取数据失败!");
            }
            $fileName = basename($this->entrance_url);
            $this->buffCheckFileName($fileName);
            echo $this->buffSaveFile($res, $fileName);

            unset($res);

            curl_close($ch);
            unset($ch);
        }
        else {
            $mh = curl_multi_init();
            for ($i = 0; $i < $this->url_num; $i++) {
                $ch[$i] = curl_init();
                $referer = $this->referer ? $this->referer : $this->entrance_url;
                if ($i === 0) {
                    $url[$i] = $this->entrance_url;
                }
                else {
                    $i_page = $i + 1;
                    $url[$i] = str_replace('index.html', 'index' . $this->url_connect . $i_page . '.html', $this->entrance_url);
                }
                $this->buffSetOpt($ch[$i], $url[$i], $this->referer, $this->cookie);
                curl_multi_add_handle($mh, $ch[$i]);
            }
            $this->buffMultiExec($mh);
            $nowStep = --$this->step;
            for ($i = 0; $i < $this->url_num; $i++) {
                $this->regexp = json_decode($this->regexp);
                $regexp_search = count($this->regexp[$nowStep]->search);
                $res[$i] = curl_multi_getcontent($ch[$i]);
                curl_multi_remove_handle($mh, $ch[$i]);
                curl_close($ch[$i]);
                unset($ch[$i]);
                if ($regexp_search !== 0) {
                    $pageUrl = $this->buffDoRegexpSearch($this->regexp[$nowStep]->search, $res[$i]);
                }
                if (count($this->regexp[$nowStep]->replace) !== 0) {
                    $regexp = $this->regexp[$nowStep]->replace[0];
                    $replace = $this->regexp[$nowStep]->replace[1];
                    $pageUrl = isset($pageUrl) ? $this->buffDoRegexpReplace($regexp, $replace, $pageUrl) : $this->buffDoRegexpReplace($regexp, $replace, $url[$i]);
                }

                $temp = 'buffCurl';
                $buffCurl = $temp . $this->step;
                $this->regexp = json_encode($this->regexp);
                if (is_array($pageUrl)) {

                    $pageUrlNum = count($pageUrl);
                    for ($j = 0; $j < $pageUrlNum; $j++) {
                        $tempdata[$j] = [
                            'entrance_url' => $pageUrl[$j],
                            'cookie' => $this->cookie,
                            'folderName' => $this->folderName,
                            'step' => $nowStep,
                            'regexp' => &$this->regexp,
                            'down_load' => &$this->down_pic
                        ];
                        $$buffCurl[$j] = new buffCurl($tempdata[$j]);
                        $$buffCurl[$j]->start();
                        unset($tempdata[$j]);
                        unset($$buffCurl[$j]);
                    }
                }
                else {
                    $tempdata = [
                        'entrance_url' => $pageUrl,
                        'cookie' => $this->cookie,
                        'folderName' => $this->folderName,
                        'step' => $nowStep,
                        'regexp' => &$this->regexp,
                        'down_load' => &$this->down_pic
                    ];
                    $$buffCurl = new buffCurl($tempdata);
                    $$buffCurl->start();
                    unset($tempdata);
                    unset($$buffCurl);
                }
            }

            curl_multi_close($mh);
            unset($mh);
        }
    }
    function buffDoRegexpSearch($regexp, $url) {
        for ($i = 0; $i < count($regexp) - 1; $i++) {
            preg_match($regexp[$i], $url, $res);
            echo"搜索\n";
        echo"\$regexp{$i}=".$regexp[$i]."\n";
        echo"\$url=".$url."\n";
        echo "结果\n";print_r($res);
            $url = $res[0];
        }
        $i = count($regexp) - 1;
        preg_match_all($regexp[$i], $url, $res2);
        echo"搜索\n";
        echo"\$regexp{$i}=".$regexp[$i]."\n";
        echo"\$url=".$url."\n";
        echo "结果\n";print_r($res2[0]);
        return $res2[0];
    }
    function buffDoRegexpReplace($regexp, $replace, $url) {
        echo"替换\n"; 
        echo"\$regexp=";
        print_r($regexp);
        echo"\$replace=";
        print_r($replace);
        echo "结果\n";
        print_r(str_replace($regexp, $replace, $url));
        return str_replace($regexp, $replace, $url);
    }
    function buffMultiExec($mh) {
        do {
            $mrc = curl_multi_exec($mh, $active);
        }
        while ($mrc == CURLM_CALL_MULTI_PERFORM);
        while ($active and $mrc == CURLM_OK) {
            if (curl_multi_select($mh) == -1) {
                usleep(10000);
            }
            do {
                $mrc = curl_multi_exec($mh, $active);
            }
            while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }
    }
    function buffSetOpt($curl, $url, $referer, $cookie) {
        $data = array(
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1;Win64;x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3026.3 Safari/537.36',
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HEADER => 0,
            CURLOPT_REFERER => $referer,
            CURLOPT_COOKIE => $cookie
        );
        curl_setopt_array($curl, $data);
    }
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
    function buffSaveFile($data, $fileName) {
        if (!file_exists(dirname(__FILE__) . '/' . $this->folderName)) {
            mkdir(dirname(__FILE__) . '/' . $this->folderName);
        }
        $downFileName = dirname(__FILE__) . '/' . $this->folderName . '/' . $fileName;
        if (file_put_contents($downFileName, $data)) {
            $fileSize = filesize($downFileName);
            if ($fileSize < $this->min_size_file) {
                unlink($downFileName);
                return "文件太小 " . ceil($fileSize /= 1024) . "k 已删除\n";
            }
            $fileSize /= 1024;
            $this->down_pic++;
            return "保存文件成功--文件大小:" . ceil($fileSize) . 'k ' . $fileName . "\n当前共下载{$this->down_pic}张图片\n";
        }
        else {
            return "保存文件失败!\n";
        }
    }
}
$data1 = [
    'entrance_url' => 'http://www.jj20.com/bz/dwxz/kaxg/3118.html',
    'cookie' => 'UM_distinctid=15b06511a9cd18-04aab118995218-79482645-1fa400-15b06511a9de41; BDTUJIAID=5f1172afe4857d54d0df3c9eb5a3b8d0; ftcpvcouplet_fidx=2; AJSTAT_ok_pages=8; AJSTAT_ok_times=2; CNZZDATA3190107=cnzz_eid%3D987761191-1490459197-null%26ntime%3D1491002969',
    'folderName' => 'gou',
    'step' => 1,
    'regexp' => '[
  {
  "search": [
  "/(?<=id=\"bigImg\" src=\")[^\"]*(?=\")/i"
  ],
  "replace": [
  ]
  }
  ]',
    'down_pic' => 0
];
$data2 = [
    'entrance_url' => 'http://www.ivsky.com/tupian/keai_de_ciwei_v40495/pic_650776.html',
    'cookie' => 'UM_distinctid=15b106fd471d3e-0bd4ac226a5ba8-79482645-1fa400-15b106fd472a0a; statistics_clientid=me; CNZZDATA87348=cnzz_eid%3D330874521-1490626948-null%26ntime%3D1490983500; CNZZDATA1629164=cnzz_eid%3D1938686851-1490627119-null%26ntime%3D1490985073; CNZZDATA1254368934=771597025-1490637474-http%253A%252F%252Fwww.ivsky.com%252F%7C1490987541; Hm_lvt_862071acf8e9faf43a13fd4ea795ff8c=1490659966,1490660029,1490712053,1490987948; Hm_lpvt_862071acf8e9faf43a13fd4ea795ff8c=1490990975',
    'folderName' => 'gou',
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
    "down_pic" => 0
];
$data = [
    'entrance_url' => 'http://www.3xnxn.com/art/Ypic/index.html',
    'cookie' => '__cfduid=d410d2e126a1954e3b73a7bba56e4c8111491085971; UM_distinctid=15b2ba653cb2f5-07ae5e79a0d334-1e1c7459-1fa400-15b2ba653cca5b; CNZZDATA1260801081=1086891965-1491082534-null%7C1491082534; CNZZDATA1258947629=1840815222-1491082510-null%7C1491082510',
    'folderName' => 'yazhou',
    'step' => 2,
    'regexp' => '[
                    {
                        "search": [
                       "~(?<=class=\"content\")[\\\S\\\s]*(?=<\/ul>)~",
                         "/(?<=img src=\")[^\"]*(?=\")/"
                        ],
                        "replace": [
                          
                        ]
                    },
                    {
                         "search": [
                         "/(?<=class=\"zuo\")[\\\S\\\s]*(?=class=\"clear\")/",
                         "/(?<=href=\")[^\"]*(?=\")/"
                        ],
                        "replace": [
                            ["\/art"],
                            ["http:\/\/3xnxn.com\/art"]
                        ]
                    }
                ]',
    "down_pic" => 0,
    "url_num" => 158,
    "url_connect"=>'-'
];
$down = new buffCurl($data);
$down->start();

