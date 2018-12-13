<?php
/**
 * Created by PhpStorm.
 * User: lea21st
 * Date: 2018-12-08
 * Time: 16:24
 */

namespace lea21st\proxy;
class Common
{

    /**
     * 日志目录
     * @var string
     */
    protected $log_dir = '/tmp';

    /**
     * 设置属性值
     * @param $attr
     * @param $val
     */
    public function set($attr, $val)
    {
        $this->{$attr} = $val;
    }


    /**
     * 输出日志
     * @param string $msg
     * @param string $level
     * @return bool
     */
    public function log($msg = '', $level = 'info')
    {
        list($sec, $time) = explode(" ", microtime());
        $str = '[ ' . date('H:i:s', $time) . '.' . $sec . " ] [ {$level} ]" . $msg . PHP_EOL;

        $destination = "{$this->log_dir}/" . date('Ymd') . '.log';
        $destination = str_replace('//', '/', $destination);

        $path = dirname($destination);
        !is_dir($path) && mkdir($path, 0755, true);

        echo $str;
        return error_log($str, 3, $destination);
    }
}