## 简易的php版本dubbo服务调用程序

2016/4/7 0:46:34 

chrome + php -S 127.0.0.1:1234 测试通过
chrome + docker + ubuntu + php-fpm + nginx 测试通过

写啰嗦的java好烦，~花了大半天时间用php草草写了个有点意思的玩具~

程序本身木有什么参考价值，过程式的风格~

**原理:** [dubbo-telnet-doc](http://dubbo.io/Telnet+Command+Reference-zh-showComments=true&showCommentArea=true.htm)

已知问题：

1. telnet ls -l 读取的方法签名无泛型与参数名，所以需要根据服务的api文档调用
2. 复杂对象的参数传递起来比较复杂，得用类型完全符合的严格的json，因为dubbo-telnet其实只是 => json_decode("[你的参数]")，反射调用
3. 貌似还发现了chrome渲染datalist的俩bug~

以上问题可以通过扩展dubbo telnet command的实现来解决，但是事实上没什么意义；

而且，调试服务的话，其实发布个restful服务也不错~


1. **初始界面**
![初始界面](https://github.com/goghcrow/dubbo-man/raw/master/screenshots/disconnected.png)

2. **连接界面**
![连接界面](https://github.com/goghcrow/dubbo-man/raw/master/screenshots/connected.png)

3. **调用1**
![调用1](https://github.com/goghcrow/dubbo-man/raw/master/screenshots/easy_invoke.png)

4. **调用2**
![调用2](https://github.com/goghcrow/dubbo-man/raw/master/screenshots/insert.png)


加班结束~ 
