Owncloud for Sina App Engine
============================

移植owncloud到Sina App Engine平台上，基于最新发布的版本3.0.0。移植的主要问题在于SAE不支持本地文件操作，因此必须用SAE的分布式存储系统作OC的存储后台。代码中主要增加了一个SAE的后台存储引擎（lib/filestorage/sae.php），并且修改了其他相关部分。

演示平台：<http://owncloud3demo.sinaapp.com>，用户名和密码都是demo。

目前状态和问题
--------------

1. Calendar和Contacts两个App基本上能够正常运行，CalDAV和CardDAV没有测试。
2. 能够上传和下载文件，但不支持WebDAV访问。
3. SAE提供的分布式存储系统对目录的支持不完善，因此暂时不支持目录。
4. 只支持单用户。
5. 其他还有很多地方没有测试过，特别是涉及到文件操作的地方，可能会有问题。

安装方法
--------

1. 创建SAE应用，启用MySQL和Storage两项服务，并创建一个Storage domain。
2. 打开config/config.php文件，修改username和password，填入用户名和密码。修改domain，填入上一步创建的domain。
3. 上传代码到SAE，具体方法见SAE文档。
4. 访问http://your\_app\_name.sinaapp.com/setup.php，将自动执行初始化操作，创建数据库表格和用户帐号。
5. 访问http://your\_app\_name.sinaapp.com，用第2步中的帐号登录。

References
----------

1. <http://www.owncloud.org>
2. <http://sae.sina.com.cn/?m=devcenter>
3. <http://life-sucks.net/wiki/doku.php?id=stuff:owncloud4sae>
