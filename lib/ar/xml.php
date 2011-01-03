<?php

	require_once(dirname(__FILE__).'/../ar.php');

	ar_pinp::allow( 'ar_xml' );
	ar_pinp::allow( 'ar_xmlElement' );
	ar_pinp::allow( 'ar_xmlNode' );
	ar_pinp::allow( 'ar_xmlNodes' );
	ar_pinp::allow( 'ar_xmlDataBinding' );

	class ar_xml extends arBase {

		public static $indenting = true;
		private static $comments = true;
		public static $indent = "\t";
		public static $strict = false;
		public static $preserveWhiteSpace = false;
		
		public static function configure( $option, $value ) {
			switch ( $option ) {
				case 'indent' :
					if ( is_bool( $value ) ) {
						self::$indenting = (bool) $value;
					} else if ( is_string( $value ) ) {
						self::$indenting = true;
						self::$indent = $value;
					} else if (!$value) {
						self::$indenting = false;
					}
				break;
				case 'comments' :
					self::$comments = (bool)$value;
				break;
				case 'strict':
					self::$strict = (bool)$value;
				break;
				case 'preserveWhiteSpace' :
					self::$preserveWhiteSpace = (bool) $value;
				break;
			}			
		}

		public static function preamble( $version = '1.0', $encoding = 'UTF-8', $standalone = null ) {
			if ( isset($standalone) ) {
				if ( $standalone === 'false' ) {
					$standalone = 'no';
				} else if ( $standalone === 'true' ) {
					$standalone = 'yes';
				}
				$standalone = self::attribute( 'standalone', $standalone );
			} else {
				$standalone = '';
			}
			return new ar_xmlNode('<?xml version="' . self::value($version) 
				. '" encoding="' . self::value($encoding) . '"' . $standalone . ' ?>');
		}
		
		public static function comment( $comment ) {
			return ( self::$comments ? new ar_xmlNode('<!-- '.self::value( $comment ).' -->') : '' );
		}

		public static function name( $name ) {
			ar::untaint($name, FILTER_UNSAFE_RAW);
			if (self::$strict) {
				$newname = preg_replace( '/[^-.0-9:a-z_]/isU', '', $name);
				$newname = preg_replace( '/^[^:a-z_]*/isU', '', $newname);
				//FIXME: throw an error here or something if newname !== name
				$name = $newname;
			}
			return $name;
		}

		public static function value( $value, $current = 0 ) {
			ar::untaint( $value, FILTER_UNSAFE_RAW );
			if ( is_array( $value ) ) {
				$content = '';
				foreach( $value as $subvalue ) {
					$content = rtrim($content) . ' ' . ltrim( self::value( $subvalue, $current ) );
				}
				$content = trim( $content );
			} else if ( is_bool( $value ) ) {
				$content = $value ? 'true' : 'false';
			} else if ( $value instanceof ar_listExpression ) {
				$content = self::value( $value->item( $current ) );
			} else {
				if ( preg_match( '/^\s*<!\[CDATA\[/', $content ) ) {
					$content = $value;
				} else {
					$content = htmlspecialchars( $value );
				}
			}
			return $content;
		}
		
		public static function attribute( $name, $value, $current = 0 ) {
			if ( is_numeric( $name ) ) {					
				return ' ' . self::name( $value );
			} else {
				return ' ' . self::name( $name ) . '="' . self::value( $value, $current ) . '"';
			}
		}
		
		public static function attributes( $attributes ) {
			$content = '';
			if ( is_array( $attributes ) ) {
				foreach( $attributes as $key => $value ) {
					$content .= self::attribute( $key, $value );
				}
			}
			return $content;
		}

		public static function cdata( $value ) {
			ar::untaint( $value, FILTER_UNSAFE_RAW );
			return new ar_xmlNode($value, null, true); //'<![CDATA[' . str_replace( ']]>', ']]&gt;', $value ) . ']]>';
		}
		
		public static function tag() {
			$args = func_get_args();
			return call_user_func_array( array( 'ar_xml', 'el' ), $args );
		}
		
		public static function element() {
			$args = func_get_args();
			return call_user_func_array( array( 'ar_xml', 'el' ), $args );
		}

		public static function el() {
			$args       = func_get_args();
			$name       = array_shift($args);
			$attributes = array();
			$content    = array();
			foreach ($args as $arg) {
				if ( is_array( $arg ) && !is_a( $arg, 'ar_xmlNodes' ) ) {
					$attributes = array_merge($attributes, $arg);
				} else if ($arg instanceof ar_xmlNodes) {
					$content    = array_merge( $content, (array) $arg);
				} else {
					$content[]  = $arg;
				}
			}
			if ( !count( $content ) ) {
				$content = null;
			} else {
				$content = new ar_xmlNodes( $content );
			}
			return new ar_xmlElement($name, $attributes, $content);
		}
		
		public static function indent( $content, $indent=null ) {
			if ( ( isset($indent) || self::$indenting ) && preg_match( '/^(\s*)</', $content) ) {
				if ( !isset($indent) ) {
					$indent = self::$indent;
				}
				return "\n" . preg_replace( '/^(\s*)</m', $indent . '$1<', $content ); 
			} else {
				return $content;
			}
		}
		
		public static function nodes() {
			$args  = func_get_args();
			$nodes = call_user_func_array( array( 'ar_xmlNodes', 'mergeArguments' ), $args );
			return new ar_xmlNodes( $nodes );
		}
		
		protected static function parseAttributes( $DOMElement ) {
			// get all attributes including namespaced ones and namespaces themselves...
			// this is the best I could do given the many bugs and oversights in php's
			// DOM implementation.

			$declaredns = array();

			// this part retrieves all available namespaces on the parent
			// xpath is the only reliable way
			$x = new DOMXPath( $DOMElement->ownerDocument );
			$p = $DOMElement->parentNode;
			if ($p && $p instanceof DOMNode ) {
				$pns = $x->query('namespace::*', $p );
				foreach( $pns as $node ) {
					$allns[$node->localName] = $p->lookupNamespaceURI( $node->localName );
				}
			}
			// this retrieves all namespaces on the current node
			// all 'new' namespace must have been declared on this node
			$ns = $x->query('namespace::*', $DOMElement);
			foreach( $ns as $node) {
				$uri = $DOMElement->lookupNamespaceURI( $node->localName );
				if ($allns[$node->localName]!=$uri && $node->localName!='xmlns') {
					$declaredns['xmlns:'.$node->localName] = $uri;
					//$allns[$node->localName] = $uri;
				}
			}

			// finally check if the default namespace has been altered
			$dns = $DOMElement->getAttribute('xmlns');
			if ($dns) {
				$declaredns['xmlns'] = $dns;
				//$allns['{default}'] = $dns;
			}

			$result = $declaredns;

			$length = $DOMElement->attributes->length;
			for ($i=0; $i<$length; $i++) {
				$a = $DOMElement->attributes->item($i);
				if ($a->prefix) {
					$prefix = $a->prefix.':';
				}
				$result[$prefix.$a->name] = $a->value;
			}

			return $result;
		}
		
		protected static function parseChildren( $DOMElement ) {
			$result = array();
			foreach ( $DOMElement->childNodes as $child ) {
				if ( $child instanceof DOMCharacterData ) {
					if ( self::$preserveWhiteSpace || trim( $child->data ) ) {
						$result[] = $child->data;
					}
				} else if ( $child instanceof DOMCdataSection ) {
					if ( self::$preserveWhiteSpace || trim( $child->data ) ) {
						$result[] = self::cdata( $child->data );
					}
				} else if ( $child instanceof DOMElement ) {
					$result[] = self::el( $child->tagName, self::parseAttributes( $child ), self::parseChildren( $child ) );
				}
			}
			return self::nodes( $result );
		}
		
		protected static function parseHead( DOMDocument $dom ) {
			$result = self::nodes();
			if ($dom->xmlVersion && $dom->xmlEncoding) {
				$result[] = self::preamble( $dom->xmlVersion, $dom->xmlEncoding, $dom->xmlStandalone );
			}
			if ($dom->doctype) {
				$doctype = '<!DOCTYPE '.$dom->doctype->name;
				if ($dom->doctype->publicId) {
					$doctype .= ' PUBLIC "'.$dom->doctype->publicId.'"';
				}
				if ($dom->doctype->systemId) {
					$doctype .= ' "'.$dom->doctype->systemId.'"';
				}
				$doctype .= '>';
				$result[] = $doctype;
			}
			return $result;
		}
		
		public static function parse( $xml ) {
			$dom = new DOMDocument();
			if ( $dom->loadXML( $xml ) ) {
				$domroot = $dom->documentElement;
				if ( $domroot ) {
					$result = self::parseHead( $dom );
					$root = self::el( $domroot->tagName, self::parseAttributes( $domroot ), self::parseChildren( $domroot ) );
					$s = simplexml_import_dom( $dom );
					$n = $s->getDocNamespaces();
					foreach( $n as $prefix => $ns ) {
						if ($prefix) {
							$prefix = ':'.$prefix;
						}
						$root->setAttribute('xmlns'.$prefix, $ns);
					}
					$result[] = $root;
					return $result;
				}
			}
			return ar_error::raiseError( 'Incorrect xml passed', ar_exceptions::ILLEGAL_ARGUMENT );
		}
	}

	/*
		This class is used for generic nodelists as well as childNodes
		The difference is in whether or not parentNode is set. As a
		generic nodelist the child nodes can have any parentNode, so the
		list is an in memory reference to a set of nodes. As a childNodes
		list the child nodes must have the same parentNode as the list.
		If you set the parentNode of the nodes list, it will also set the
		parentNode of all the childNodes and remove them from any other parent
	*/
	interface ar_xmlNodeInterface {	}
	
	class ar_xmlNodes extends ArrayObject implements ar_xmlNodeInterface {

		private $parentNode = null;
		public $attributes  = array();
		public $isDocumentFragment = true;
		private $nodeValue = '';
		
		public static function mergeArguments(){
			$args  = func_get_args();
			$nodes = array();
			foreach ( $args as $input ) {
				if ( is_array( $input ) || $input instanceof ar_xmlNodes ) {
					$nodes = array_merge( $nodes, (array) $input );
				} else if ($input) { // skip empty and NULL arguments
					$nodes[] = $input;
				}
			}
			return $nodes;
		}

		public function __construct() {
			$args  = func_get_args();
			$nodes = call_user_func_array( array( 'ar_xmlNodes', 'mergeArguments' ), $args );
			foreach( $nodes as $key => $node) {
				if (!$node instanceof ar_xmlNode) {
					$nodes[$key] = new ar_xmlNode( $node );
				}
			}
			parent::__construct($nodes);
		}

		public function offsetSet($offset, $value) {
			if (!$value instanceof ar_xmlNode) {
				$value = new ar_xmlNode( $value );
			}
			parent::offsetSet($offset, $value);
		}
		
		private static function removeEmptyNodes( $var ) {
			return (!trim($var)=='');
		}

		public function __toString() {
			return $this->toString();
		}

		public function toString( $indentWith = null ) {
			foreach ( $this->attributes as $name => $value ) {
				$position = 0;
				foreach ( $this as $key => $node ) {
					if ($node instanceof ar_xmlElement) {
						$appliedValue = $this->_applyValues($value, $position);
						$node->setAttribute( $name, $appliedValue );
						$position++;
					}
				}
			}
			$result = '';
			$indent = isset($indentWith) ? $indentWith : (ar_xml::$indenting ? ar_xml::$indent : '');
			
			$position = 0;
			foreach ( $this as $node) {
				if ( $node instanceof ar_xmlElement) {
					$result .= $node->toString($indentWith, $position);
					$position++;
				} else if ( $node instanceof ar_xmlNode) {
					$stringValue = (string) $node;
					if ( trim($stringValue) !== "" ) {
						$result .= $stringValue;
					}
				} else if ( is_string($node) ) {
					$node = trim($node);
					if( $node !== "" ) {
						$result .= ar_xml::indent( (string) $node, $indentWith);
					}
				}
			}
			return $result;
		}
		
		public function setAttributes( array $attributes, $dynamic = true ) {
			foreach ($attributes as $name => $value) {
				$this->setAttribute( $name, $value, $dynamic );
			}
			return $this;
		}

		private function _runPatterns( $value ) {
			if ($value instanceof ar_listExpression_Pattern) {
				$count = 0;
				foreach ( $this as $key => $node ) {
					if ($node instanceof ar_xmlElement) {
						$count++;
					}
				}
				$value = ar::listExpression( $count )->pattern( $value->patterns );
			} else if ( is_array( $value ) ) {
				$newvalue = array();
				foreach ($value as $key => $subvalue ) {
					$newvalue[$key] = $this->_runPatterns( $subvalue );
				}
				$value = $newvalue;
			}
			return $value;
		}

		private function _applyValues( $value, $position = 0 ) {
			if ($value instanceof ar_listExpression) {
				$result = $value->item( $position );
			} else if ( is_array($value) ) {
				$result = array();
				foreach( $value as $key => $subvalue ) {
					$result[$key] = $this->_applyValues( $subvalue, $position );
				}
			} else {
				$result = $value;
			}
			return $result;
		}
		
		public function setAttribute( $name, $value, $dynamic = true ) {
			$value = $this->_runPatterns($value);
			if ($dynamic) {
				if ( isset($this->attributes[$name]) && is_array($value) && !isset($value[0]) ) {
					if (!is_array($this->attributes[$name])) {
						$this->attributes[$name] = array( $this->attributes[$name] );
					}
					$this->attributes[$name] = array_merge( (array) $this->attributes[$name], $value );
				} else {
					$this->attributes[$name] = $value;
				}
			}
			$position = 0;
			foreach ( $this as $key => $node ) {
				if ($node instanceof ar_xmlElement) {
					$appliedValue = $this->_applyValues($value, $position);
					$node->setAttribute( $name, $appliedValue );
					$position++;
				}
			}
			return $this;
		}
		
		public function __get( $name ) {
			switch ( $name ) {
				case 'parentNode' :
					return $this->parentNode;
				break;
				case 'firstChild' :
					return $this[0];
				break;
				case 'lastChild' :
					return $this[count($this)-1];
				break;
				case 'childNodes' :
					return $this;
				break;
				case 'nodeValue' :
					if ( count($this)==1 ) {
						return $this[0]->nodeValue;
					} else {
						$result = array();
						foreach($this as $node) {
							$result[] = $node->nodeValue;
						}
						return $result;
					}
				break;
				case 'attributes' :
					if ( count($this)==1 ) {
						return $this[0]->attributes;
					} else {
						$result = array();
						foreach($this as $node) {
							if ($node instanceof ar_xmlElement || $node instanceof ar_xmlNodes ) {
								$result[] = $node->attributes;
							}
						}
						return $result;
					}
				break;				
				default :
					if (!isset($this->parentNode) && !$this->isDocumentFragment ) {
						$result = array();
						foreach ($this as $node) {
							if ($node instanceof ar_xmlElement || $node instanceof ar_xmlNodes ) {
								$temp = $node->getElementsByTagName( $name, false );
								$result = array_merge( $result, (array) $temp);
							}
						}
						$result = $this->getNodeList( $result );
						$result->isDocumentFragment = false;
						return $result;
					} else {
						return $this->getElementsByTagName( $name, false );
					}
				break;
			}
		}
		
		public function __set( $name, $value ) {
			switch( $name ) {
				case 'parentNode' :
					$this->setParentNode($value);
				break;
				default :
					if (isset($this[0]) && is_object($this[0]) ) {
						$el = $this[0];
						$el->{$name} = $value;
					} else if ($value instanceof ar_xmlElement) {
						$this[] = $value;
					} else {
						$this[] = ar_xml::tag($name, (string)$value);
					}
				break;
			}
		}
		
		public function __call( $name, $params ) {
			if (($name[0]==='_')) {
				$realName = substr($name, 1);
				if (ar_pinp::isAllowed($this, $realName)) {
					return call_user_func_array(array($this, $realName), $params);
				} else {
					trigger_error("Method $realName not found in class ".get_class($this), E_USER_ERROR);
				}
			} else if (isset($this[0]) && is_object($this[0]) ) {
				$el = $this[0];
				return call_user_func_array( array( $el, $name ), $params );
			} else {
				return null;
			}
		}
		
		public function cloneNode( $recurse = false ) {
			if (!$recurse) {
				$result = $this->getNodeList();
			} else {
				$result = clone $this;
				$result->parentNode = null;
				foreach ( $result as $pos => $el ) {
					$result[$pos] = $el->cloneNode($recurse);
				}
			}
			return $result;
		}
		
		protected function getNodeList() {
			$params = func_get_args();
			return call_user_func_array( array( 'ar_xml', 'nodes'), $params );
		}
		
		function getElementsByTagName( $name, $recurse = true ) {
			$nodeList = array(); 
			foreach ($this as $node) {
				if ( $node instanceof ar_xmlElement ) {				
					if ( $name == '*' || $node->tagName == $name) {
						$nodeList[] = $node;
					}
					if ($recurse) {
						$nodeList = array_merge( $nodeList, (array) $node->getElementsByTagName( $name ) );
					}
				}
			}
			$result = $this->getNodeList( $nodeList );
			$result->isDocumentFragment = false;
			return $result;
		}
		
		function getElementById( $id ) {
			if (isset($this->parentNode)) {
				return $this->parentNode->getElementById($id);
			} else {
				foreach ($this as $node ) {
					if ( $node instanceof ar_xmlElement ) {
						$el = $node->getElementById($id);
						if ( isset($el) ) {
							return $el;
						}
					}
				}
				return null;
			}
		}
		
		function __clearAllNodes() {
			parent::__construct();
		}
		
		function setParentNode( ar_xmlElement $el ) {
			$this->parentNode = $el;
			foreach ($this as $node) {
				if ($node instanceof ar_xmlElement) {
					if ( isset($node->parentNode) ) {
						if ( $node->parentNode !== $el ) {
							$node->parentNode->removeChild($node);
						}
					} else {
						$node->parentNode = $el;
					}
				}
			}
			$this->isDocumentFragment = false;
		}
		
		function getPreviousSibling( ar_xmlNode $el ) {
			$pos = $this->getPosition( $el );
			if ( $pos > 0 ) {
				return $this[ $pos - 1 ];
			} else {
				return null;
			}
		}
		
		function getNextSibling( ar_xmlNode $el ) {
			$pos = $this->getPosition( $el );
			if ( $pos < count( $this ) ) {
				return $this[ $pos + 1 ];
			} else {
				return null;
			}
		}
		
		function getPosition( $el ) {
			if ( is_array($el) || $el instanceof Traversable ) {
				return $this->getPosition( reset($el) );
			} else {
				foreach ( $this as $pos => $node ) {
					if ( $node === $el ) {
						return $pos;
					}
				}
			}
		}
		
		private function _removeChildNodes( $el ) {
			if ( isset( $this->parentNode ) ) {
				if ( is_array( $el ) ) {
					foreach ( $el as $subEl ) {
						if ( isset($subEl->parentNode) ) {
							$subEl->parentNode->removeChild( $subEl );
						}
					}
				} else {
					if ( isset($el->parentNode) ) {
						$el->parentNode->removeChild( $el );
					}
				}
			}
		}
		
		private function _setParentNodes( $el ) {
			if ( isset( $this->parentNode ) ) {
				if ( is_array( $el ) || $el instanceof Traversable ) {
					foreach ( $el as $subEl ) {
						$subEl->__clearParentIdCache();
						$subEl->parentNode = $this->parentNode;
						$subEl->__restoreParentIdCache();
					}
				} else {
					$el->__clearParentIdCache();
					$el->parentNode = $this->parentNode;
					$el->__restoreParentIdCache();
				}
			}		
		}
		
		function appendChild( ar_xmlNodeInterface $el ) {
			$this->_removeChildNodes( $el );
			$result = $this->_appendChild( $el );
			return $result;
		}
		
		private function _appendChild( $el ) {
			$this->_setParentNodes( $el );
			if ( !is_array( $el ) && !( $el instanceof ArrayObject ) ) {
				$list = array( $el );
			} else {
				$list = (array) $el;
			}
			parent::__construct( array_merge( (array) $this, $list ) );
			return $el;
		}

		function insertBefore( ar_xmlNodeInterface $el, ar_xmlNodeInterface $referenceEl = null ) {
			$this->_removeChildNodes( $el );
			if ( !isset($referenceEl) ) {
				return $this->_appendChild( $el );
			} else {
				$pos = $this->getPosition( $referenceEl );
				if ( !isset($pos) ) {
					$this->_appendChild( $el );
				} else {
					$this->_setParentNodes( $el );
					if ( !is_array( $el ) ) {
						$list = array( $el );
					} else {
						$list = (array) $el;
					}
					$arr = (array) $this;
					array_splice( $arr, $pos, 0, $list );
					parent::__construct( $arr );
				}
			}
			return $el;
		}
		
		function replaceChild( ar_xmlNodeInterface $el, ar_xmlNodeInterface $referenceEl ) {
			$this->_removeChildNodes( $el );
			$pos = $this->getPosition( $referenceEl );
			if ( !isset($pos) ) { 
				return null;
			} else {
				$this->_setParentNodes( $el );
				if ( !is_array( $el ) ) {
					$list = array( $el );
				} else {
					$list = (array) $el;
				}
				$arr = (array) $this;
				array_splice( $arr, $pos, 0, $list ); 
				parent::__construct( $arr );
				return $this->removeChild( $referenceEl );
			}
		}	

		public function removeChild( ar_xmlNodeInterface $el ) {
			// Warning: must never ever call _removeChildNodes, can be circular.
			if ( is_array( $el ) ) {
				foreach( $el as $subEl ) {
					$this->removeChild( $subEl );
				}
			} else {
				$pos = $this->getPosition( $el );
				if ( isset($pos) ) {
					$oldEl = $this[$pos];
					$arr = (array) $this;
					array_splice( $arr, $pos, 1);
					parent::__construct( $arr );
					if ( isset($this->parentNode) ) {
						$oldEl->__clearParentIdCache();
						$oldEl->parentNode = null;
					}
				} else {
					return null;
				}
			}
			return $el;
		}

		public function bind( $nodes, $name, $type = 'string' ) {
			$b = new ar_xmlDataBinding( );
			return $b->bind( $nodes, $name, $type );
		}

		public function bindAsArray( $nodes, $type = 'string' ) {
			$b = new ar_xmlDataBinding( );
			return $b->bindAsArray( $nodes, 'list', $type)->list;
		}

	}
	
	class ar_xmlNode extends arBase implements ar_xmlNodeInterface {
		public $parentNode = null;
		private $nodeValue = '';
		public $cdata = false;
		
		function __construct($value, $parentNode = null, $cdata = false) {
			$this->nodeValue  = $value;
			$this->parentNode = $parentNode;
			$this->cdata      = $cdata;
		}
		
		function __toString() {
			return $this->toString();
		}

		function toString() {
			if ($this->cdata) {
				return "<![CDATA[" . str_replace("]]>", "]]&gt;", $this->nodeValue) . "]]>";
			} else {
				return (string) $this->nodeValue;
			}
		}
		
		function __get( $name ) {
			switch( $name ) {
				case 'previousSibling' :
					if (isset($this->parentNode)) {
						return $this->parentNode->childNodes->getPreviousSibling($this);
					}
				break;
				case 'nextSibling' :
					if (isset($this->parentNode)) {
						return $this->parentNode->childNodes->getNextSibling($this);
					}
				break;
				case 'nodeValue' :
					return $this->nodeValue;
				break;
			}
		}
		
		function __set( $name, $value ) {
			switch ($name) {
				case 'previousSibling' :
				case 'nextSibling' :
					return false;
				break;
				case 'nodeValue' :
					$this->nodeValue = $value;
				break;
			}
		}
		
		function __isset( $name ) {
			$value = $this->__get($name);
			return isset($value);
		}
		
		function __clone() {
			$this->parentNode = null;
		}
		
		function cloneNode( $recurse = false ) {
			return clone($this);
		}
		
		public function __clearParentIdCache() {
		}
		
		public function __restoreParentIdCache() {
		}

	}
	
	class ar_xmlElement extends ar_xmlNode implements ar_xmlNodeInterface {
		public $tagName     = null;
		public $attributes  = array();
		private $childNodes = null;
		public $parentNode  = null;
		private $idCache    = array();
		private $nodeValue  = '';
		
		function __construct($name, $attributes, $childNodes, $parentNode = null) {
			$this->tagName    = $name;
			$this->parentNode = $parentNode;
			$this->childNodes = $this->getNodeList();
			$this->childNodes->setParentNode( $this );
			if ($childNodes) {
				$this->appendChild( $childNodes );
			}
			$this->setAttributes( $attributes );
		}
		
		public function __clearParentIdCache() {
			if ( isset($this->parentNode) && count( $this->idCache ) ) {
				foreach( $this->idCache as $id => $value ) {
					$this->parentNode->__updateIdCache($id, null, $value);
				}
			}
		}
		
		public function __restoreParentIdCache() {
			if ( isset($this->parentNode) && count( $this->idCache ) ) {
				foreach( $this->idCache as $id => $value ) {
					$this->parentNode->__updateIdCache($id, $value);
				}
			}
		}

		public function __updateIdCache($id, $el, $oldEl = null) {
			if ( !isset($el) ) {
				// remove id cache entry
				if ( isset($this->idCache[$id]) && ($this->idCache[$id]===$oldEl) ) {
					// only remove id cache pointers to the correct element
					unset($this->idCache[$id]);
				}
			} else {
				$this->idCache[$id] = $el;
			}
			if (isset($this->parentNode)) {
				$this->parentNode->__updateIdCache($id, $el, $oldEl);
			}
		}
		
		function setAttributes( $attributes ) {
			foreach ( $attributes as $name => $value ) {
				$this->setAttribute( $name, $value );
			}
			return $this;
		}

		function setAttribute( $name, $value ) {
			if ( $name == 'id' ) {
				$oldId = null;
				if (isset($this->attributes['id'])) {
					$oldId = $this->attributes['id'];
				}
			}
			if ( is_array($value) && !isset($value[0]) ) {
				// this bit of magic allows ar_xmlNodes->setAttribute to override only
				// specific attribute values, leaving others alone, by specifying a
				// non-number key.
				if ( !is_array($this->attributes[$name]) ) {
					$this->attributes[$name] = array( $this->attributes[$name] );
				}
				$this->attributes[$name] = array_merge( $this->attributes[$name], $value );
			} else {
				$this->attributes[$name] = $value;
			}
			if ($name=='id') {
				if ( isset($oldId) ) {
					$this->__updateIdCache( $oldId, null, $this );
				}
				$this->__updateIdCache($value, $this);
			}
			return $this;
		}
		
		function __toString() {
			return $this->toString();
		}

		function toString( $indent = '', $current = 0 ) {
			$indent = ar_xml::$indenting ? $indent : '';
			$result = "\n" . $indent . '<' . ar_xml::name( $this->tagName );
			if ( is_array($this->attributes) ) {
				foreach ( $this->attributes as $name => $value ) {
					$result .= ar_xml::attribute($name, $value, $current);
				}
			} else if ( is_string($this->attributes) ) {
				$result .= ltrim(' '.$this->attributes);
			}
			if ( $this->childNodes instanceof ar_xmlNodes && count($this->childNodes) ) {
				$result .= '>';
				$result .= $this->childNodes->toString( ar_xml::$indent . $indent );
				if ( substr($result, -1) == ">") {
					$result .= "\n" . $indent;
				}
				$result .= '</' . ar_xml::name( $this->tagName ) . '>';
			} else {
				$result .= ' />';
			}			
			return $result;
		}

		public function getNodeList() {
			$params = func_get_args();
			return call_user_func_array( array( 'ar_xml', 'nodes'), $params );
		}
		
		function __get( $name ) {
			switch( $name ) {
				case 'firstChild' :
					if (isset($this->childNodes) && count($this->childNodes)) {
						return $this->childNodes[0];
					}
				break;
				case 'lastChild' :
					if (isset($this->childNodes) && count($this->childNodes)) {
						return $this->childNodes[count($this->childNodes)-1];
					}
				break;
				case 'childNodes' :
					return $this->childNodes;
				break;
				case 'nodeValue' :
					//echo get_class($this->childNodes[0]).'('.$this->childNodes[0].')';
					if (isset($this->childNodes) && count($this->childNodes) ) {
						return $this->childNodes->nodeValue;
					}
				break;
			}
			$result = parent::__get( $name );
			if ( isset($result) ) {
				return $result;
			}
			return $this->getElementsByTagName( $name, false );
		}
		
		function __set( $name, $value ) {
			$result = parent::__set($name, $value);
			if (isset($result)) {
				return $result;
			}
			switch ($name ) {
				case 'childNodes' :
					if ( !isset($value) ) {
						$value = $this->getNodeList();
					} else if ( !($value instanceof ar_xmlNodes) ) {
						$value = $this->getNodeList($value);
					}
					$this->childNodes->setParentNode( null );
					$this->childNodes = $value;
					$this->childNodes->setParentNode( $this );
				break;
				default:
					$nodeList = $this->__get( $name );
					if (isset($nodeList[0])) {
						$node = $nodeList[0];
						$node->tagName = $value->tagName;
						$node->attributes = $value->attributes;
						$node->childNodes = $value->childNodes;
					}
				break;
			}
		}
		
		function __clone() {
			parent::__clone();
			$this->childNodes = $this->getNodeList();
		}
		
		function cloneNode( $recurse = false ) {
			$childNodes = $this->childNodes->cloneNode( $recurse );
			$result = parent::cloneNode( $recurse );
			$result->childNodes = $childNodes;
			return $result;
		}
		
		function getElementsByTagName( $name , $recurse = true ) {
			if ( isset( $this->childNodes ) ) {
				return $this->childNodes->getElementsByTagName( $name, $recurse );
			}
		}
		
		function getElementById( $id ) {
			if (isset($this->idCache[$id])) {
				return $this->idCache[$id];
			}
		}
		
		function appendChild( $el ) {
			return $this->childNodes->appendChild( $el );
		}
		
		function insertBefore( $el, $referenceEl = null ) {
			return $this->childNodes->insertBefore( $el, $referenceEl );
		}
		
		function replaceChild( $el, $referenceEl ) {
			return $this->childNodes->replaceChild( $el, $referenceEl );
		}	

		function removeChild( $el ) {
			return $this->childNodes->removeChild( $el );
		}

		public function bind( $nodes, $name, $type = 'string' ) {
			$b = new ar_xmlDataBinding( );
			return $b->bind( $nodes, $name, $type );
		}

		public function bindAsArray( $nodes, $type = 'string' ) {
			$b = new ar_xmlDataBinding( );
			return $b->bindAsArray( $nodes, 'list', $type)->list;
		}
		
	}
	
	class ar_xmlDataBinding extends arBase {

		public function bindAsArray( $nodes, $name, $type='string') {
			$total = count($nodes);
			$this->{$name} = array();
			foreach ( $nodes as $key => $node ) {
				$this->{$name}[$key] = $this->bindValue( $node, $type);
			}
			return $this;
		}

		public function bind( $node, $name, $type='string' ) {
			if ( ( is_array($node) || ( $node instanceof Countable ) ) && count($node)>1 ) {
				return $this->bindAsArray( $node, $name, $type );
			}
			$this->{$name} = $this->bindValue( $node, $type );
			return $this;
		}
		
		public function __toString() {
			return $this->source->toString();
		}
		
		protected function bindValue( $source, $type ) {
			if ( $source instanceof ar_xmlNode || $source instanceof ar_xmlNodes ) {
				$nodeValue = $source->nodeValue;
				if (is_array($nodeValue) && !count($nodeValue)) {
					$nodeValue = null;
				}
			} else {
				$nodeValue = $source;
			}
			if ( is_callable($type) ) {
				$nodeValue = call_user_func( $type, $source );
			} else {
				switch ($type) {
					case 'int'    : $nodeValue = (int) $nodeValue;
					break;
					case 'float'  : $nodeValue = (float) $nodeValue;
					break;
					case 'string' : $nodeValue = (string) $nodeValue;
					break;
					case 'bool'   : $nodeValue = (bool) $nodeValue;
					break;
					case 'xml' :
					case 'html' :
							if ($source instanceof ar_xmlNode || $source instanceof ar_xmlNodes) {
								$nodeValue = (string) $source->childNodes;
							}
					break;
					default       :
						if ( is_string($type) && class_exists($type) && ar_pinp::isAllowed($type, '__construct') ) {
							$nodeValue = new $type($nodeValue);
						}
					break;
				}
			}
			return $nodeValue;
		}

	}

?>