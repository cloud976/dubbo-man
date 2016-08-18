<?php
namespace jiedaibao\dubbo;

error_reporting(E_ALL);

function connect($host, $port, &$err = null)
{
    $conn = new FoolSock($host, $port);
    if ($conn === false) {
        $err = "$errstr ($errno)";
    }
    $conn->pconnect(100);
    return $conn;
}

function servList($fd)
{
    if (!is_resource($fd)) {
        return [];
    }
    $serv_arr = _exec_cmd($fd, "ls", true);
    $result   = [];
    foreach ($serv_arr as $serv) {
        $serv          = trim($serv);
        $detail_arr    = _exec_cmd($fd, "ls -l $serv", true);
        $result[$serv] = array_map(function ($m) {return _method_parse($m);}, $detail_arr);
    }
    return $result;
}

function invoke($fd, $serv, $method, array $args = [], $pretty_print = false)
{
    if (!is_resource($fd)) {
        return "无效连接~";
    }
    $arg_str = implode(", ", $args);
    $receive = _exec_cmd($fd, "invoke $serv.$method($arg_str)");
    $receive = str_replace("dubbo>", "", $receive);
    if (function_exists("iconv")) {
        $receive = iconv("GB2312", "UTF-8", $receive);
    }
    if ($pretty_print) {
        $ret_arr = explode("\r\n", $receive);
        if (!isset($ret_arr[1])) {
            return $receive;
        }

        $result = json_decode($ret_arr[0], true);
        if ($result === false || $result === true) {
            return $receive;
        }
        $result = [
            'result' => json_decode($ret_arr[0], true),
            'time'   => $ret_arr[1],
        ];
        $pretty = jsonReadableEncode($result);
        return $pretty;
    } else {
        return $receive;
    }
}

// @http://stackoverflow.com/questions/834303/startswith-and-endswith-functions-in-php
function startsWith($haystack, $needle)
{
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
}
function endsWith($haystack, $needle)
{
    return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
}

function _exec_cmd($fd, $cmd, $array = false)
{
    if (!is_resource($fd)) {
        return false;
    }
    $fd->send($cmd);

    $receive = "";
    // 以dubbo>结尾判断数据包完整
    while (!endsWith($receive, "dubbo>")) {
        $receive .= $fd->read(1 << 13);
    }

    if ($array) {
        $arr = explode("\n", $receive);
        array_pop($arr);
        return $arr;
    } else {
        return $receive;
    }
}

function _method_parse($method_signature)
{
    static $method_pattern = '/^([a-zA-Z0-9|\.]*)\s([a-zA-Z0-9]*)\(([a-zA-Z0-9|\.|,]*)\)$/';
    if (preg_match($method_pattern, trim($method_signature), $matches)) {
        return [
            "return" => $matches[1],
            "method" => $matches[2],
            "args"   => explode(",", $matches[3]),
        ];
    } else {
        // debug
        // exit($method_signature);
    }
    return [];
}

function jsonReadableEncode($in, $indent = 0, $from_array = false)
{
    $_myself = __FUNCTION__;
    $_escape = function ($str) {
        return preg_replace("!([\b\t\n\r\f\"\\'])!", "\\\\\\1", $str);
    };

    $out = '';

    foreach ($in as $key => $value) {
        $out .= str_repeat("\t", $indent + 1);
        $out .= "\"" . $_escape((string) $key) . "\": ";

        if (is_object($value) || is_array($value)) {
            $out .= "\n";
            $out .= $_myself($value, $indent + 1);
        } elseif (is_bool($value)) {
            $out .= $value ? 'true' : 'false';
        } elseif (is_null($value)) {
            $out .= 'null';
        } elseif (is_string($value)) {
            $out .= "\"" . $_escape($value) . "\"";
        } else {
            $out .= $value;
        }

        $out .= ",\n";
    }

    if (!empty($out)) {
        $out = substr($out, 0, -2);
    }

    $out = str_repeat("\t", $indent) . "{\n" . $out;
    $out .= "\n" . str_repeat("\t", $indent) . "}";

    return $out;
}
