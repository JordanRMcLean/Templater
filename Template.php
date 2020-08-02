<?php 

//Template.php

//Define an interface for templates as all these methods are required by the parser. 
interface TemplateInterface {
	public function set($name, $value);
	public function set_loop($name, $values);
	public function set_content($content);
	public function get_unparsed_content();
	public function get_vars();
	public function set_compiled($content);
	public function get_compiled();
	public function is_compiled();
	public function set_parsed($content);
	public function get_parsed();
	public function is_parsed();
	public function filename($full_filename);
}



class Template implements TemplateInterface
{
	static  $template_dir = 'Templates'; //static in case templates are stored in different areas. 
	private $filename = '';
	private $filename_full = '';
	
	private $parsed = false;
	private $unparsed_content = '';
	private $parsed_content = '';
	
	private $compiled = false;
	private $compiled_content = '';
	
	private $vars = array();
	public $overwrite_vars = true;
	
	static $template_store = array(
		'TEST'	=> 'Test template. {TEST_VAR1} <br> {TEST_VAR2} <br> Condition: {IF: TEST_VAR3 === 1} Condition is true {/IF}'
	);
	
	
	//construct will load template file if provided. If not, will need to before parsing. 
	function __construct($templateFile = false) 
	{
		if( preg_match('#[/\\\]*$#', self::$template_dir) )
		{
			$this->template_dir = preg_replace('#[/\\\]*$#', '', self::$template_dir);
		}
		
		if($templateFile) 
		{
			$this->load($templateFile);
		}
	}
	
	//Load template file. 
	public function load($templateFile)
	{	
		$this->filename = $templateFile;
		$templateFile = self::$template_dir . '/' . $templateFile;
		$this->filename_full = $templateFile;
		$content = '';
		
		if( isset(self::$template_store[$templateFile]) ) //check if its been loaded already
		{
			$content = self::$template_store[$templateFile];
		}
		elseif( file_exists($templateFile) )
		{
			$content = file_get_contents($templateFile);
		}
		else
		{
			die('Could not find Template file: ' . $templateFile);
		}
		
		$this->set_content($content, $templateFile);
	}
	
	/* Assign vars. If both name and value are strings, one var is set. 
	 * If name is an array, then its array full of vars, key = name. 
	 * If name is a string and value an array then namespace vars are set.
	 */
	public function set($name, $value = '')
	{
		if(is_string($name))
		{
			$this->_assign_var($name, $value);
		}
		elseif(is_array($name))
		{
			foreach($name as $n => $v)
			{
				$this->_assign_var($n, $v);
			}
		}
	}
	
	//Add another record to a template iteration. 
	public function set_loop($name, $values)
	{
		if( !is_array($values) || !is_string($name) ) 
		{
			return;
		}
		
		$level = &$this->vars;
	
		$nests = explode('.', $name); // testloop innerloop
		$last = $nests[ count($nests) - 1 ]; // innerloop
		
		foreach($nests as $n) 
		{
			if($n === $last) 
			{
				break;
			}
			
			if( !is_array($level[$n]) )
			{
				return;
			}
				
			$level = &$level[$n][ count($level[$n]) - 1 ];
		}
				
		if( is_array($level[$last]) )
		{
			$level[$last][] = $values;
		}
		else
		{
			$level[$last] = array($values);
		}
	}
	
	
	public function set_content($content = '', $name = '')
	{
		$this->unparsed_content = $content;
		
		if($name) 
		{
			self::$template_store[$name] = $content;
		}
	}
	
	
	public function get_unparsed_content()
	{
		return $this->unparsed_content;
	}
	
	
	public function get_vars()
	{
		return $this->vars;
	}
	
	
	private function _assign_nested_vars($path, $value) //SOMENEST:NEXTNEST:NEW_VAR, value
	{
		$current = &$this->vars; 
		$split = explode(':', $path);  //SOMENEST NEXTNEST NEW_VAR
		$new_varname = $split[ count($split) - 1 ];
				
		foreach($split as $n)
		{
			if($n === $new_varname)
			{
				$current[$n] = $value;
				break;
			}
			
			if( !isset($current[$n]) )
			{
				$current[$n] = array();
			}
			
			if( is_array($current[$n]) )
			{
				$current = &$current[$n];
			}
			elseif( $this->overwrite_vars )
			{
				$current[$n] = array();
				$current = &$current[$n];
			}
			else
			{
				return;
			}
		}
	}
	
	
	private function _assign_var($name, $value)
	{
		if( !isset($this->vars[$name]) || $this->overwrite_vars )
		{
			if( is_int( strpos($name, ':') ) ) // nested var
			{
				$this->_assign_nested_vars($name, $value);
			}
			else
			{		
				$this->vars[ $name ] = $value;
			}
		}
	}
	
	public function set_compiled($content = '')
	{
		$this->compiled = true;
		$this->compiled_content = $content;
	}
	
	public function is_compiled()
	{
		return $this->compiled;
	}
	
	public function get_compiled()
	{
		return $this->compiled_content;
	}
	
	public function set_parsed($content = '')
	{
		$this->parsed = true;
		$this->parsed_content = $content;
	}
	
	public function is_parsed()
	{
		return $this->parsed;
	}
	
	public function get_parsed()
	{
		return $this->parsed_content;
	}
	
	public function filename($full = false)
	{
		return $full ? $this->filename_full : $this->filename;
	}
}

?>