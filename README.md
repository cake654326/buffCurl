# BuffCurl
爬虫下载图片类

爬虫思路 :<br/>

    1.进入一个网站的栏目页.

    2.判断从入口的栏目页到终点站图片的url地址需要几层 (step) 如果入口页就是图片的url地址那么step等于0

    3.创建$mh 主句柄 .假如这个栏目有180页.为他添加180个子curl

    4.在180个子curl结果中执行当前(step-1)的正则(在当前页中找到下一层url的正则,做成json)  因为入口页就是图片url的话那么就不需要正则.

      如果进入入口页需要执行一次正则搜索或者替换才能找到图片url那么此入口的step=1 即使用json对象 $regexp[step-1] 

    5.每获取到下一层url step-1. 并且创建新的$mh对象 并添加所有下一层url.然后执行.执行后销毁 ,如果下一次step为0 .就直接下载


我是新手.看的慕课网的curl教学视频做的一个.网上的教学太少了,我做了好多天.我感觉不怎么样,而且很麻烦.每次都要写regexp

人家wget 那种直接整站给扒了.太恐怖了.

    希望大家交流一下,我是一个菜鸟.大部分内容都是网上找来的.

    我已经测试过几个网站.狗狗图片,还有一些初中男生爱看的网站我已经下载了几十个g了.因为我不停的写,写了好多个版本.前面都是用过程化写的.

    其中还有一些小漏洞没有改.一些是我不会,一些事懒得改.因为用不到.
   
    请大家多支支招.我感觉现在就是下载很慢.下载一些大图片的时候比如10m以上的,速度可以达到10m左右.
    但是下载一些三四兆的那么速度就是三四兆.我现在想的是不管下载多少都是用我网速的全部去下载.因为curl_multi是并发下载的,
    我觉得应该是可以做到的. 搞不懂.太菜了. 我还是要去学c 学好了底层才能知其所以然.

文件中的域名我是已经改过了,那几个字母被我打乱了,如果你们猜到了真实域名并且去下载了几十个g. 对不起我不认识你

图片为运行效果<br/>
![pic](https://github.com/buffge/buffCurl/blob/master/2.gif "脚本运行效果")<br/>


![pic](https://github.com/buffge/buffCurl/blob/master/%E6%80%BB%E6%97%B6%E9%97%B4.png "总时间和总下载数")

这个是配置数组<br/>
$data3 = [
    'entrance_url' => 'http://www.yksvi.com/tupian/yeshengdongwu_t81/index.html', //入口页面 必须要写index.html 或者list.html 等全路径

    //cookie只要你登录一次他们网站然后复制过来就行
    'cookie' => 'UM_distinctid=15b106fd471d3e-0bd4ac226a5ba8-79482645-1fa400-15b106fd472a0a; CNZZDATA1259295795=1187515984-1491099760-http%253A%252F%252Fwww.yksvi.com%252F%7C1491099760; CNZZDATA1254368934=771597025-1490637474-http%253A%252F%252Fwww.yksvi.com%252F%7C1491200194; CNZZDATA87348=cnzz_eid%3D330874521-1490626948-null%26ntime%3D1491258755; Hm_lvt_862071acf8e9faf43a13fd4ea795ff8c=1491024144,1491063837,1491204750,1491260529; Hm_lpvt_862071acf8e9faf43a13fd4ea795ff8c=1491260529; CNZZDATA1629164=cnzz_eid%3D1938686851-1490627119-null%26ntime%3D1491256536',
    
    'folderName' => '野生动物',//要保存的文件夹名

    'step' => 3,//从入口页面到图片url需要的层数 即需要点几次链接

    'regexp' => '[  //正则 先转换成json对象,然后$regexp[0]就是step 1用的 $regexp[1]就是step 2用的 $regexp[2]就是step 3用的
                    {
                        "search": [
                       
                        ],
                        "replace": [
                           ["http:\/\/www.yksvi.com\/download_pic.html?picurl="],
                           ["http:\/\/img.yksvi.com"]
                        ]
                    },
                    {
                         "search": [
                         "/(?<=id=\"imgis\" src=[\'\"])[^\'\"]*jpg(?=[\'\"] alt)/"
                        ],
                        "replace": [
                            ["http:\/\/img.yksvi.com","\/img\/tupian\/pre"],
                             ["http:\/\/www.yksvi.com","\/download_pic.html?picurl=\/img\/tupian\/pic"]
                        ]
                    },
                    {
                         "search": [
                         "~(?<=class=\"pli\")[\\\S\\\s]*(?=id=\"tplistleft1\")~",
                         "/(?<=href=\")[^\"]*(?=\" ti)/"
                        ],
                        "replace": [
                            ["\/tupian"],
                            ["http:\/\/www.yksvi.com\/tupian"]
                        ]
                    }
                ]',
    "url_num" => 180,//共有多少个入口页面  
    "url_connect" => '_',//入口页面一般是index.html 第二页就是inedx_2.html 或者 index-2.html 这里就是设置连接符
    "down_timeout" => 30,//下载超时设置
    'addSiteReplace' => ['index', 'index'],//替换下一个页面时所需要替换的内容  因为不是每一个网站都是index.html  有的是list_1.html 之类的
    'current_connect' => 30, //并发连接数 
    'min_size_file' => 10240, //低于此字节图片删除
];
 
