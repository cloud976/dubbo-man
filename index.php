<?php
namespace xiaofeng;

require __DIR__ . "/dubbo.php";
error_reporting(E_ALL);
date_default_timezone_set("Asia/Shanghai");
header("Content-type: text/html; charset=utf-8");

// chrome datalist 俩bug
// 1. 太长了的话木有滚轮
// 2. 用.innerHTML设置datalist，渲染出错，把旧的数据渲染出来

function get_host()
{
    $host = filter_input(INPUT_POST, "host", FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    if (!$host) {
        $host = filter_input(INPUT_COOKIE, "host", FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }
    return $host ?: "127.0.0.1";
}

function get_port()
{
    $port = filter_input(INPUT_POST, "port", FILTER_VALIDATE_INT);
    if (!$port) {
        $port = filter_input(INPUT_COOKIE, "port", FILTER_VALIDATE_INT);
    }
    return $port ?: "20880";
}

function get_post($k, $or = "")
{
    if (!isset($_POST[$k])) {
        $_POST[$k] = $or;
    }
    return $_POST[$k];
}

function get_java_type_json($java_type)
{
    // 线上正好有个以前留下的nashorn的eval环境~~
    // 木有的话直接 return "" , 一些参数需要手写了~~
    return "";
    static $url = "http://xxxxxxxx/debug/eval";
    if (dubbo\startsWith($java_type, "java.")) {
        return "";
    }

    $payload = <<<JS
(function(){
	var LoadRate= Java.type("$java_type")
	return 反正就是个bean2Map(new LoadRate())
	// 其实最好是个fastjson啥的,直接转成json就好了
}())
JS;
    $opts = ["http" => [
        "method" => "POST",
        "header" => "Content-Type:text/plain;charset=UTF-8",
        "content" => $payload,
    ]];
    $ctx = stream_context_create($opts);
    $rec = file_get_contents($url, false, $ctx);
    $keys = explode(",", trim($rec, " \t\n\r\0\x0B{}"));
    if (count($keys) <= 1) {
        return "";
    }
    $keys = array_map(function ($a) {return trim(explode("=", $a)[0]);}, $keys);
    $array = array_combine($keys, array_fill(0, count($keys), null));
    return json_encode($array, JSON_PRETTY_PRINT);
}

// 连接
if (isset($_POST["connect"])) {
    $expire = time() + 60 * 60 * 24 * 30;
    setcookie("host", get_host(), $expire);
    setcookie("port", get_port(), $expire);
    header("Refresh: 0;");
    exit;
}

$disconnected = false;
$remote_host = get_host() . ":" . get_port();
$fd = @dubbo\connect($remote_host, $err);
if ($err) {
    if (function_exists("iconv")) {
        $err = iconv("GB2312", "UTF-8", $err);
    }
    $disconnected = true;
}
$title = $disconnected ? "<span class='error'>[DISCONNCETED]</span>" : "<span class='normal'>[CONNCETED]</span>";

// 调用dubbo服务
if (isset($_POST["invoke"])) {
    echo dubbo\invoke($fd, get_post("serv"), get_post("serv_method"), get_post("args", []), true);
    exit;
}

$serv_list = dubbo\serv_list($fd);
$entity_json = [];
foreach ($serv_list as $serv => &$methods) {
    foreach ($methods as &$method) {
        if (isset($method['args'])) {
            foreach ($method["args"] as $i => $arg) {
                if (isset($entity_json[$arg])) {
                    $method["args_type"][$i] = $entity_json[$arg];
                } else {
                    $json = get_java_type_json($arg);
                    $method["args_type"][$i] = $json;
                    $entity_json[$arg] = $json;
                }
            }
        }
    }
}
unset($methods);

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>dubbo-man</title>
	<link rel="stylesheet" href="http://yui.yahooapis.com/pure/0.6.0/pure-min.css">
	<style>
	html, button, input, select, textarea,
	.pure-g [class *= "pure-u"] {
		font-family: "微软雅黑", Georgia, Times, "Times New Roman", serif;
	}
	.hide { display: none; }
	.error { color: red; }
	.normal { color: green; }
	.title, .connect { margin-left: 20px; }
	#container { margin: 10px 20px; }
	/*#container .left, #container .right { padding: 5px 5px;}*/
	#container .args { }
	#container .btn { margin-top: 10px; }
	#container #result { font-family: "微软雅黑"; font-size: 15px; }
	#container textarea, #container input { width: 80%; }
	</style>
</head>
<body>
<h2 class="title">dubbo-man <?php echo $title ?></h2>
<div class="connect">
	<?php if ($disconnected) {
    echo "<p class='error'>ERROR: $err</p>";
}
?>
	<form method="post" action="" class="pure-form">
		<fieldset>
			<input type="hidden" name="connect" value="1">
			<input type="text" name="host" placeholder="Host" required="true" value="<?php echo get_host() ?>">
			<input type="number" name="port" step="1" placeholder="port" required="true" style="width: 100px;" value="<?php echo get_port() ?>">
			<button type="submit" class="pure-button pure-button-primary">Connect</button>
		</fieldset>
</form>
</div>
<div id="container" class="pure-g <?php if ($disconnected) {
    echo "hide";
}
?>">
	<div class="pure-u-1-2 left">
		<form method="post" action="" id="dubbo_form" class="pure-form pure-form-stacked">
			<fieldset>
			<p>双击下拉列表展开，复杂对象使用json传输</p>
			<input type="hidden" name="invoke" value="1">

			<label for="serv_input">Service</label>
			<input type="hidden" name="serv" id="serv_hidden" value="">
			<input id="serv_input" list="serv_list" placeholder="Type Service">
			<datalist id="serv_list">
				<?php foreach ($serv_list as $serv => $methods): ?>
					<option
						data-serv="<?php echo $serv ?>"
						value="<?php echo array_slice(explode(".", $serv), -1, 1)[0] ?>"></option>
				<?php endforeach?>
			</datalist>

			<label for="method_input">Methods</label>
			<!-- name 不能写成method，否则会覆盖form自身的form属性	-->
			<input id="method_input" list="method_list" name="serv_method" placeholder="Type Method">
			<datalist id="method_list">
			</datalist>

			<div id="method_args"></div>
			<button type="submit" class="pure-button pure-button-primary btn">Invoke</button>
			</fieldset>
		</form>
	</div>

	<div class="pure-u-1-2 right">
		<pre id="result" style="line-height:20px"></pre>
	</div>
</div>
</body>
<script>
document.addEventListener("DOMContentLoaded", function(event) {
	var $serv_input = document.getElementById("serv_input")
	var $serv_list = document.getElementById("serv_list")
	var $serv_hidden = document.getElementById("serv_hidden")
	var $method_list = document.getElementById("method_list")
	var $method_input = document.getElementById("method_input")
	var $form = document.getElementById("dubbo_form")
	var $method_args = document.getElementById("method_args")
	var $result = document.getElementById("result")

	window.serv_list = <?php echo json_encode($serv_list) ?>;
	window.entity_json = <?php echo json_encode($entity_json) ?>;

	function get_serv() {
		var val = $serv_input.value
		if(val.endsWith("Service")) {
			var option = [].slice.call($serv_list.options).find(function(option) { return option.value === val })
			if(option) {
				return option.dataset.serv
			}
		}
		return void 0
	}

	function get_method() {
		var val = $method_input.value
		var option = [].slice.call($method_list.options).find(function(option) { return option.value === val })
		if(option) {
			return JSON.parse(option.dataset.method)
		}
	}

	function arg_input(type, id) {
		if(!type) {
			return ""
		}
		var input = {
			"java.lang.Byte": '<input id="' + id + '" required="true" name="args[]" type="text" placeholder="java.lang.Byte"/>',
			"java.lang.Boolean": '<input id="' + id + '" required="true" name="args[]" type="text" placeholder="java.lang.Boolean"/>',
			"java.lang.Character": '<input id="' + id + '" required="true" name="args[]" type="text" placeholder="java.lang.Character"/>',
			"java.lang.Short": '<input id="' + id + '" required="true" name="args[]" type="number" placeholder="java.lang.Short"/>',
			"java.lang.Integer": '<input id="' + id + '" required="true" name="args[]" type="number" step="1" placeholder="java.lang.Integer"/>',
			"java.lang.Long": '<input id="' + id + '" required="true" name="args[]" type="number" step="1" placeholder="java.lang.Long"/>',
			"java.lang.Float": '<input id="' + id + '" required="true" name="args[]" type="number" placeholder="java.lang.Float"/>',
			"java.lang.Double": '<input id="' + id + '" required="true" name="args[]" type="number" placeholder="java.lang.Double"/>',

			"byte": '<input id="' + id + '" required="true" name="args[]" type="text" placeholder="byte"/>',
			"boolean": '<input id="' + id + '" required="true" name="args[]" type="text" placeholder="boolean"/>',
			"char": '<input id="' + id + '" required="true" name="args[]" type="text" placeholder="char"/>',
			"short": '<input id="' + id + '" required="true" name="args[]" type="number" placeholder="short"/>',
			"int": '<input id="' + id + '" required="true" name="args[]" type="number" step="1" placeholder="int"/>',
			"long": '<input id="' + id + '" required="true" name="args[]" type="number" step="1" placeholder="long"/>',
			"float": '<input id="' + id + '" required="true" name="args[]" type="number" placeholder="float"/>',
			"double": '<input id="' + id + '" required="true" name="args[]" type="number" placeholder="double"/>',

			"java.lang.String": "<input id='" + id + "' required='true' name='args[]' type='text' placeholder='java.lang.String' value='\"  \"'/>",
			"java.util.List": '<textarea id="' + id + '" required="true" name="args[]" placeholder="Type Strict Json Format Text"/>[  ]</textarea>',
		}
		if(input[type]) {
			return input[type]
		} else {
			var def_json = entity_json[type] ? entity_json[type] : "{  }"
			return '<textarea id="' + id + '" required="true" name="args[]" placeholder="Type Strict Json Format Text">' +  def_json + '</textarea>'
		}
	}


	$serv_input.addEventListener("dblclick", function() {
		this.value = ""
	})

	$method_input.addEventListener("dblclick", function() {
		this.value = ""
	})

	// 选择服务
	$serv_input.addEventListener("input", function() {
		var serv = get_serv()
		if(serv) {
			$method_args.innerHTML = ""

			$serv_hidden.value = serv
			$method_input.value = ""
			$method_list.innerHTML = serv_list[serv].map(function(m) {
				return '<option value="' + m.method + '"' + "data-method='" + JSON.stringify(m) + "'>" + '</option>'
			}).join("")
		}
	}, false)

	// 选择方法
	$method_input.addEventListener("input", function() {
		var method = get_method()
		if(method) {
			$method_args.innerHTML = ""

			console.info("service", get_serv())
			console.info("method", method)
			$method_args.innerHTML = '<p class="args">Arguments:</p>' + method.args.map(function(type, i) {
				return '<label for="arg' + i + '">Type: ' + type + '</label>' + arg_input(type, "arg" + i)
			}).join("")
		}
	}, false)

	$form.addEventListener("submit", function(event) {
		event.preventDefault()
		if(!this.checkValidity()) {
			return false
		}
		var data = new FormData(this)
		var xhr = new XMLHttpRequest()

		xhr.open(this.method, this.action)
		xhr.onload = function(e) {
			if(xhr.status == 200 && xhr.responseText) {
				$result.innerHTML = xhr.responseText
			} else {
				$result.innerHTML = "FAIL"
			}
		}.bind(this)
		xhr.send(data)
	})

}, false);
</script>
</html>
