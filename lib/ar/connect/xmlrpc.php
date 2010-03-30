<?php
global $AR;
require_once($AR->dir->install."/lib/includes/ripcord/ripcord.php");

ar_pinp::allow('ar_connect_xmlrpc');
ar_pinp::allow('ar_connect_xmlrpcClientWrapper');
ar_pinp::allow('ar_connect_xmlrpcServerWrapper');


class ar_connect_xmlrpc extends arBase {

	private static function services( $methods ) {
		$result = array();
		foreach ($methods as $methodName => $method) {
			$result[$methodName] = ar_pinp::getCallback($method);
		}
		return $result;
	}

	public static function client($url, $options = null) {
		return new ar_connect_xmlrpcClientWrapper( ripcord::client($url, $options) );
	}
	
	public static function server($methods, $options = null, $documentation = false) {
		return new ar_connect_xmlrpcServerWrapper( ripcord::server(
			self::services( $methods ),
			$options, 
			$documentation ? new ar_connect_xmlrpcDocumentor( $options, $documentation ) : false
		) );
	}

	public static function base64($binary) {
		return ripcord::base64( $binary );
	}
	
	public static function binary($base64) {
		return ripcord::binary( $base64 );
	}
	
	public static function datetime($timestamp) {
		return ripcord::datetime($timestamp);
	}
	
	public static function timestamp($datetime) {
		return ripcord::timestamp($datetime);
	}
	
	public static function fault($code, $message) {
		return ripcord::fault($code, $message);
	}
	
	public static function isFault($fault) {
		return ripcord::isFault($fault);
	}
	
	public static function getType($arg) {
		return ripcord::getType($arg);
	}
	
	public static function encodeCall() {
		$params = func_get_args();
		return call_user_func_array( array('ripcord', 'encodeCall'), $params );
	}
}

class ar_connect_xmlrpcClientWrapper extends arBase {
	private $wrapped = null;
	
	public function __construct( $wrapped ) {
		$this->wrapped = $wrapped;
	}

	public function __get($name) {
		$result = $this->wrapped->{$name};
		if ( is_a('Ripcord_Client', $result) ) {
			return new ar_connect_xmlrpcClientWrapper($result);
		} else {
			return $result;
		}
	}
	
	public function __call($method, $params) {
		if ($method[0]=='_') {
			$method = substr($method, 1);
		}
		return $this->wrapped->__call($method, $params);
	}
	
	public function __set($name, $value) {
		$this->wrapped->{$name} = $value;
	}
}

class ar_connect_xmlrpcServerWrapper extends arBase {
	private $wrapped = null;
	
	public function __construct( $wrapped ) {
		$this->wrapped = $wrapped;
	}

	public function __get($name) {
		$result = $this->wrapped->{$name};
	}
	
	public function __call($method, $params) {
		if ($method[0]=='_') {
			$method = substr($method, 1);
		}
		return call_user_func_array( array($this->wrapped, $method), $params);
	}
	
	public function __set($name, $value) {
		$this->wrapped->{$name} = $value;
	}
}

class ar_connect_xmlrpcDocumentor extends arBase implements Ripcord_Documentor_Interface {
	private $documentation = null;
	
	public function __construct( $options = null ) {
		$args = func_get_args();
		if (isset($args[1])) {
			$this->documentation = $args[1];
		}
	}
	
	public function getIntroSpectionXML() {
		return false;
	}
	
	public function setMethodData($methods) {
		return false;
	}
	
	public function handle($request) {
		echo $this->documentation;
	}
}

?>