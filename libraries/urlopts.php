<?
/**
* Matts little 'URLOpts' library
* Contains useful functionality to create pretty URLs.
*/
class URLopts {
	/**
	* The contents of the last Get() call to extract the current URL strack
	* @var array
	*/
	var $_opts;

	/**
	* The URL to force this module to use as defined by Set()
	* If this value is non-null the Segments() function will always split this variable rather than magicly trying to figure out the current URL
	* @var null|string
	*/
	var $_url;

	/**
	* How many segments to ignore
	* By default the first three segments are ignored which corresponds to the hostname, CodeIgniter controller and Method
	* @var int
	*/
	var $_ignore;

	function __construct() {
		$this->_ignore = 3;
	}

	/**
	* Extract an array of parameters from a function arg stack
	* This is usually called with $this->urlopts->get(func_get_args());
	* so /controller/method/filter1/value1/filter2/value2 => array('filter1' => 'value1', 'filter2' => 'value2')
	* @param null|array $stack The argument stack to process. If no specific stack is specified Segments() is automatically called
	*/
	function Get($stack = null) {
		if ($stack === null)
			$stack = $this->Segments();
		$this->_opts = array();
		$iskey = 0;
		foreach ($stack as $index => $item) {
			if ($index < $this->_ignore) // Skip ignored items
				continue;
			$iskey = !$iskey;
			if ($iskey) {
				$key = $item;
			} else
				$this->_opts[$key] = $item;
		}
		return $this->_opts;
	}

	/**
	* Set the currently active URL.
	* This overrides the default method to magicly determine the active URL
	* @param string $url The URL to force this module to use
	*/
	function Set($url) {
		$this->_url = rtrim($url, '/');
	}

	/**
	* Return the URL segments as an array
	* This function will use the CodeIgniter URL library if its present, otherwise it will use the $_SERVER information
	* If Set() was called beforehand that will ALWAYS be used as the current URL
	* @return array An array of URL segments
	*/
	function Segments() {
		if ($this->_url !== null) {
			return explode('/', ltrim($this->_url, '/'));
		} elseif (function_exists('get_instance')) {
			$CI = get_instance();
			return $CI->uri->segments;
		}
	}

	/**
	* Specify a string of URL parameters to add or remove
	*
	* Each parameter to this function is specified in one of the following formats:
	*
	* * foo=bar	- Set the option 'foo' to 'bar'
	* * +foo	- Set the option 'foo' to '1'
	* * -foo	- Remove the option 'foo' from the URL
	*
	* @param string,... $option Edit, Add or remove an option from the URL parameter
	* @return string The URL with the specified corrections applied
	*/
	function Edit() {
		$args = func_get_args();
		$set = array();
		$url = '/';
		foreach ($args as $arg) {
			if (substr($arg, 0, 1) == '+') {
				$set[strtolower(substr($arg, 1))] = 1;
			} elseif (substr($arg, 0, 1) == '-') {
				$set[strtolower(substr($arg, 1))] = FALSE;
			} elseif ($bits = explode('=', $arg, 2)) {
				$set[strtolower($bits[0])] = $bits[1];
			} else
				trigger_error("Unknown call to urlopts->Edit(). Dont know what to do with the string '$arg'. Prefix with '+' to add, '-' to remove to add a '=' to set.");
		}

		$doreplace = FALSE;
		$replacenext = null;
		foreach ($this->Segments() as $seg) {
			if (isset($set[$seg])) {
				$doreplace = TRUE;
				$replacenext = $set[$seg];
				if ($replacenext !== FALSE)
					$url .= $seg . '/';
				unset($set[$seg]);
			} elseif ($doreplace) {
				if ($replacenext !== FALSE)
					$url .= $replacenext . '/';
				$doreplace = FALSE;
			} else
				$url .= $seg . '/';
		}
		foreach ($set as $key => $val)
			if ($val !== FALSE)
				$url .= "$key/$val/";
		return rtrim($url, '/');
	}

	/**
	* Executes an Edit() compatible operation on the URL then redirects to the result
	* This function is fatal
	* @param string,... $option The Edit() operation to apply
	* @see Edit()
	*/
	function Go() {
		$args = func_get_args();
		$url = call_user_func_array(array($this, 'edit'), $args);
		header("Location: $url");
		exit();
	}
	
	/**
	* Add a parameter to the URL and return the result
	* e.g. <a href="<?=$this->urlopts->Add('page', $page+1)?>">Next page</a>
	* @param string $param The parameter to add
	* @param string $value Optional new value to use. Set to false or omit to remove
	*/
	function Add($param, $value = FALSE) {
		$replacenext = 0;
		$replaced = 0;
		$url = '/';
		foreach ($this->Segments() as $index => $seg) {
			if ($index < $this->_ignore) { // Ignored segment
				$url .= $seg . '/';
			} elseif ($seg == $param) {
				$replacenext = 1;
				if ($value !== FALSE)
					$url .= $seg . '/';
			} elseif ($replacenext) {
				if ($value !== FALSE)
					$url .= $value . '/';
				$replacenext = 0;
				$replaced = 1;
			} else
				$url .= $seg . '/';
		}
		if (!$replaced && $value !== FALSE) { // Not replaced - append new parameter
			$url .= "$param/$value";
		} else
			$url = rtrim($url, '/');
		return $url;
	}

	/**
	* Remove a parameter from the URL and return the result
	* This function is really just an alias for Add($param, FALSE)
	* @param string $param The parameter to remove
	*/
	function Remove($param) {
		return $this->Add($param, FALSE);
	}
}
