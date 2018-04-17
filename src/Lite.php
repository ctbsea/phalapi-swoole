<?php
namespace ctbsea\phalapiSwoole;



class Lite extends Pool
{
    const DEFAULT_HOST = '127.0.0.1';
    const DEFAULT_PORT = 3306;
    
    function __construct($config, $maxConnection = 100)
    {
        if (empty($config['host']))
        {
            $config['host'] = '127.0.0.1';
        }
        if (empty($config['port']))
        {
            $config['port'] = self::DEFAULT_PORT;
        }
        parent::__construct($config, $maxConnection);
        $this->create(array($this, 'connect'));
    }
    
    /*
     * 当连接池接受到任务的时候 没有空闲连接 会通过create 创建新的数据库连接
     * 设定事件 如果db关闭了连接 同时连接池移除资源
     * 当数据库连接成功 同时加入连接池资源
     */
    protected function connect()
    {
        $db = new \swoole_mysql;
        $db->on('close', function ($db)
        {
            $this->remove($db);
        });
        
        return $db->connect($this->config, function ($db, $result)
        {
            if ($result)
            {
                $this->join($db);
            }
            else
            {
                $this->failure();
                trigger_error("connect to mysql server[{$this->config['host']}:{$this->config['port']}] failed. Error: {$db->connect_error}[{$db->connect_errno}].");
            }
        });
    }
    
    function query($sql, callable $callabck)
    {
        $this->request(function (\swoole_mysql $db) use ($callabck, $sql)
        {
            return $db->query($sql, function (\swoole_mysql $db, $result) use ($callabck)
            {
                call_user_func($callabck, $db, $result);
                $this->release($db);
            });
        });
    }
    
    function isFree()
    {
        return $this->taskQueue->count() == 0 and $this->idlePool->count() == count($this->resourcePool);
    }
    
    /**
     * 关闭连接池
     */
    function close()
    {
        foreach ($this->resourcePool as $conn)
        {
            $conn->close();
        }
    }
    
    function __destruct()
    {
        $this->close();
    }
}
