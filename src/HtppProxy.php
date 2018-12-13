<?php
/**
 * Created by PhpStorm.
 * User: lea21st
 * Date: 2018-12-08
 * Time: 16:07
 */

namespace lea21st\proxy;

class HtppProxy extends Common
{

    /**
     * 服务对象
     * @var \Swoole\Server
     */
    private $server;

    private $clients = [];


    private $config = [
        'timeout'         => 10,
        'worker_num'      => 1,
        'poll_thread_num' => 1,
        'max_conn'        => 1000,
        'dispatch_mode'   => 3,
        'debug_mode'      => 1,
    ];

    const EVENT = ['WorkerStart', 'Connect', 'Receive', 'Close', 'WorkerStop'];

    public function __construct($ip, $port, $config = [])
    {
        $this->server = new \Swoole\Server($ip, $port);

        //设置信息
        $config = array_merge($this->config, $config);
        $this->server->set($config);

        //设置事件
        foreach (self::EVENT as $event) {
            $this->server->on($event, [$this, "on{$event}"]);
        }

    }

    /**
     * 服务启动
     */
    public function start()
    {
        $this->server->start();
    }

    /**
     * 此事件在Worker进程/Task进程启动时发生。这里创建的对象可以在进程生命周期内使用
     * @param     $server
     * @param int $worker_id 是一个从0-$worker_num之间的数字，表示这个worker进程的ID
     */
    public function onWorkerStart($server, int $worker_id)
    {
        $this->log("{$worker_id} on start...");
        $this->log("Swoole version is [" . SWOOLE_VERSION . "]");
    }

    /**
     * 此事件在worker进程终止时发生。在此函数中可以回收worker进程申请的各类资源。
     * @param     $server
     * @param int $worker_id
     */
    public function onWorkerStop($server, int $worker_id)
    {
        $this->log("Server: stop");
    }

    /**
     * 有新的连接进入时，在worker进程中回调
     * @param $server
     * @param $fd         连接的文件描述符，发送数据/关闭连接时需要此参数
     * @param $reactor_id 来自哪个Reactor线程
     */
    public function onConnect($server, $fd, $reactor_id)
    {
        $this->log("Client[$fd] Connect.");
    }

    /**
     * 接收到数据时回调此函数，发生在worker进程中。
     * @param        $server
     * @param int    $fd
     * @param int    $reactor_id
     * @param string $data 收到的数据内容
     */
    public function onReceive($server, int $fd, int $reactor_id, $data)
    {
        //判断是否为新连接
        if (!isset($this->clients[$fd])) {
            //判断代理模式
            list($method, $url) = explode(' ', $data, 3);
            $url = parse_url($url);

            //ipv6为啥外面还有个方括号？
            if (strpos($url['host'], ']')) {
                $url['host'] = str_replace(['[', ']'], '', $url['host']);
            }

            //解析host+port
            $host = $url['host'];
            $port = isset($url['port']) ? $url['port'] : 80;

            //ipv4/v6处理,
            $tcp_mode = strpos($url['host'], ':') !== false ? SWOOLE_SOCK_TCP6 : SWOOLE_SOCK_TCP;
            $client   = new \Swoole\Client($tcp_mode, SWOOLE_SOCK_ASYNC);

            if ($method == 'CONNECT') {
                $client->on("connect", function ($cli) use ($fd) {
                    //$this->log("隧道模式-连接成功!", 'debug');
                    //告诉客户端准备就绪，可以继续发包
                    $cli->send($fd, "HTTP/1.1 200 Connection Established\r\n\r\n");
                });
            } else {
                $client->on("connect", function ($cli) use ($data) {
                    //$this->log("正常模式-连接成功!", 'debug');
                    //直接转发数据
                    $cli->send($data);
                });
            }

            /**
             * 注意这儿的data是参数
             */
            $client->on("receive", function ($cli, $data) use ($fd) {
                //将收到的数据转发到客户端
                if ($this->server->exist($fd)) {
                    $this->server->send($fd, $data);
                }
            });

            $client->on("error", function ($cli) use ($fd) {
                $this->log("Client {$fd} error");
            });

            $client->on("close", function ($cli) use ($fd) {
                $this->log("Client {$fd} connection close");
            });

            $this->clients[$fd] = $client;
            $this->clients[$fd]->connect($host, $port);
        } else {
            //已连接，正常转发数据
            if ($this->clients[$fd]->isConnected()) {
                $this->clients[$fd]->send($data);
            }
        }
    }

    /**
     * TCP客户端连接关闭后，在worker进程中回调此函数
     * @param     $server
     * @param int $fd
     * @param int $reactorId
     */
    public function onClose($server, int $fd, int $reactorId)
    {
        if (isset($this->clients[$fd])) {
            $client = $this->clients[$fd];
            unset($this->clients[$fd]);
            $client->close();
            $this->log("Client {$fd} close connection");
        }
    }


}
