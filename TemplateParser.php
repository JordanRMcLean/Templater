<?php

//For use with Template.php class 
//PHP 7+ required.

// define an interface so all code written using template parser can be re-used 
// with future/other Templaters that follow the same interface. 
interface Parser {
	public function parse(&$template);
	public function compile(&$template);
}



class TemplateParser implements Parser
{
	private $handle_errors = true; 
	private $last_error_message = ''; 
	private $max_cache_age = 36000;
	
	private $regex = array(
		'output_match'		=> '#\\{(C:)?(?:[0-9a-zA-Z_:]{3,})\\}#',
		'condition_match'	=> '#\\{(?:IF|ELSEIF):\\s.+?(\\s([!=<>]=?|&&|(?:\\|\\|)|neq|eq|and|not|or|gt|lt|gte|lte) .+?)*\\}#',
		'loop_match'		=> '#\\{LOOP: ([0-9a-zA-Z_]{3,}?)\\}(?:.|[\\n\\r])*?\\{/LOOP: \\1\\}#',
		'include_match'		=> '#\\{INCLUDE: (.*?)\\}#',
		'ignore_match'		=> '#\\{IGNORE\\}((?:.|[\\r\\n])*?)\\{/IGNORE\\}#',
		'ignore_tags'		=> '#\\{/?IGNORE\\}#',
		
		'operator'			=> '#([!=<>]=?|&&|(?:\\|\\|)|neq|eq|and|not|or|gt|lt|gte|lte)#',
		'output_block'		=> '#^[0-9a-zA-Z_]{3,}(:[0-9a-zA-Z_]+)*$#',
		'endif'				=> '#\\{/IF\\}#',
		'else'				=> '#\\{ELSE:\\s?\\}#'
	);
	
	
	
	function __construct($handle_errors = true, $max_cache_age = 36000)
	{
		$this->handle_errors = $handle_errors;
		$this->max_cache_age = $max_cache_age;
	}
	
	
	public function compile(&$template)
	{
		if( !is_object($template) || !($template instanceof Template) )
		{
			return $this->error('Compiler must receive Template object to compile.');
		}
		
		if($template->is_compiled())
		{
			return $template->get_compiled();	
		}
		
		if(!$template->is_parsed())
		{
			$this->parse($template);
		}
		
		$parsed_content = $template->get_parsed();		
		$vars = $template->get_vars();
		
		//Start output buffering and eval the template
		ob_start(); 
		eval('?>' . $parsed_content . '<?');
		$compiled_content = ob_get_clean();	
		
		
		$template->set_compiled($compiled_content);
		
		return $compiled_content;
	}
	
	
	private function error($message) 
	{
		if($this->handle_errors)
		{
			//could write a fancy error template to use here. 
			trigger_error($message);
		}
		else 
		{
			$this->error = $message;
		}
	}
	
	
	public static function cache($template)
	{
		if( !is_object($template) || !($template instanceof Template) )
		{
			return $this->error('Compiler must receive Template object to compile.');
		}
		
		$cache = $template->template_dir . '/cache';
		$file = $cache . '/parsed_' . $template->filename();
		
		if( !file_exists($cache) )
		{
			//create cache directory.
			//catch errors because if its not created then there will just be no cache. Not fatal. 
			
			$user_mask = umask(0);
			@mkdir($cache, 0644);
			
			umask($user_mask); //return old mask.
		}
		
		if( $template->is_parsed() ) 
		{
			//its been parsed so cache it.
			//supress errors in case file permissions do not allow. 
			@file_put_contents($file, $template->get_parsed());	
		}
		else
		{
			//The template has not been parsed so check if a cached version exists and provide it.
			if( file_exists($file) )
			{
				//time when cached
				$cached_time = filemtime($file);
				
				//last modification of the original template.
				$last_modified = filemtime($template->filename(true));
				
				if($last_modified > $cached_time)
				{
					//time now - cached_time = the age. If younger than max cache age then good to use. 
					if( (time() - $cached_time) < $this->max_cache_age )
					{
						return file_get_contents($file);
					}
				}
				else 
				{
					//Been cached more recently than last modification so return it
					return file_get_contents($file);
				}		
			}
		}
		
		return null;
	}
	
	
	//Turns the template into actual PHP which can be executed.
	//Returns the instance of this object so can chain the compile function ->parse()->compile()
	public function parse(&$template = false)
	{
		if( !is_object($template) || !($template instanceof Template) )
		{
			return $this->error('Compiler must receive Template object to compile.');
		}
		
		if($template->is_parsed())
		{
			return $this;
		}
		
		//before parsing check if it has been cached.
		$cached = $this->cache($template);
		
		if($cached) //the parsed template was cached, so no need to parse.
		{
			$template->set_parsed($cached);
			return $this;
		}
		
		//no cached template, so begin parsing.
		$content = $template->get_unparsed_content();
		
		//Steps to parsing:
		//1. remove ignore blocks.
		//2. include any include templates
		//3. replace all output variables
		//4. replace all loop blocks
		//5. sort conditions out
		//6. return ignore blocks
		
		
		//1. remove ignore blocks and store them until the end.
		$ignore_blocks = array();
		$ignore_blocks_storage = array();
		preg_match_all($this->regex['ignore_match'], $content, $ignore_blocks);
		$ignore_blocks = $ignore_blocks[0];
		
		if($ignore_blocks)
		{
			$i = 0;
			$replacement_key = 'IGNORE_BLOCK_' . time() . '_';
			
			foreach($ignore_blocks as $blok)
			{
				$replacement_var = '{@' . $replacement_key . ($i++) .'@}';
				$replacement_content = preg_replace($this->regex['ignore_match'], '$1', $blok);
				
				//store the ignore content 
				$ignore_blocks_storage[ $replacement_var ] = $replacement_content; 
				
				//Replace the ignore content with unique replacement. 
				$content = str_replace($blok, $replacement_var, $content);
			}
		}		
		//now all ignore blocks have been swapped for unique vars and will be added in just like normal vars.
	
		
		//2. include any include templates
		$include_vars = array();
		
		while(preg_match($this->regex['include_match'], $content) === 1)
		{
			preg_match_all($this->regex['include_match'], $content, $include_vars);
			$include_vars = $include_vars[0];
			
			//we need the directory of the template to include other templates within the dame directory.
			$directory = $template->template_dir;
		
			if($include_vars)
			{
				foreach($include_vars as $i_var)
				{
					$replacement = $this->parse_include_block($i_var, $directory);
					$content = str_replace($i_var, $replacement, $content);
				}
			}
		}
		
		//3. Replace all output vars	
		$vars = $template->get_vars();
		
		$output_vars = array();
		preg_match_all($this->regex['output_match'], $content, $output_vars);
		$output_vars = $output_vars[0];
		
		if($output_vars)
		{
			foreach($output_vars as $t_var)
			{
				$var_alias = $this->parse_output_block($t_var);
				if(preg_match('#^[A-Z0-9_]+$#', $var_alias)) //is this a constant?
				{
					
					$replacement = "<? if(defined('$var_alias')): echo $var_alias; endif; ?>";
				}
				else
				{
					$replacement = "<? echo isset($var_alias) ? $var_alias : '' ?>";
				}
			
				$content = str_replace($t_var, $replacement, $content);
			}
		}	
		
		//4. Replace all loop blocks
		$loop_blocks = array();
		preg_match_all($this->regex['loop_match'], $content, $loop_blocks);		
		$loop_blocks = $loop_blocks[0];	

		
		if($loop_blocks)
		{
			foreach($loop_blocks as $l_var)
			{
				$replacement = $this->parse_loop_block($l_var);
				$content = str_replace($l_var, $replacement, $content);
			}
		}
		
		
		//5. Sort conditions out
		$condition_blocks = array();
		preg_match_all($this->regex['condition_match'], $content, $condition_blocks);
		$condition_blocks = $condition_blocks[0];
		
		if($condition_blocks)
		{
			foreach($condition_blocks as $c_var)
			{
				$stripped_condition = preg_replace('#^\{|\}$#', '', $c_var);
				$replacement = $this->parse_condition_block($stripped_condition);
				$content = str_replace($c_var, $replacement, $content);
			}
		}
		
		$content = preg_replace($this->regex['else'], '<? else: ?>', $content);
		$content = preg_replace($this->regex['endif'], '<? endif; ?>', $content);
		
		
		//6. return ignore blocks
		foreach($ignore_blocks_storage as $unique_key => $ignored_content) {
			$content = str_replace($unique_key, $ignored_content, $content);
			
		}
		
		$template->set_parsed($content);
		
		$this->cache($template);
		
		//return this object in order to optionally chain compile method straight after.
		return $this;
	}
	
	//---------------------------------------------
	//Private functions for parsing from here on.
	//---------------------------------------------
	
	private function parse_condition_block($condition)
	{
		//split the condition block into the type of condition and the condition statements. 
		list($condition_type, $condition_parts) 
			= explode(' ^v^ ', preg_replace('#^(IF|ELSE|ELSEIF): (.+)$#', '$1 ^v^ $2', $condition) ); 
		
		$condition_parts = explode(' ', $condition_parts);
		$new_condition_parts = array(); //build the fixed condition parts here.
		
		foreach($condition_parts as $part)
		{
			//in order to support braces in the conditions we must check now.
			if( $part[0] === '(' )
			{
				$new_condition_parts[] = '(';
				$part = substr($part, 1);
			}
			
			$closing_brace = false;
			
			if( substr($part, -1) === ')' )
			{
				$closing_brace = true;
				$part = substr($part, 0, strlen($part) - 1);
			}
			
			if( preg_match($this->regex['output_block'], $part) ) //is this an output var used in the condition?
			{
				$new_condition_parts[] = $this->parse_output_block($part);
			}
			elseif( preg_match($this->regex['operator'], $part) ) //is this part of the condition an operator?
			{
				$new_condition_parts[] = $this->operator($part);
			}
			else //it might be a string or int or bool literal. Leave it as it is.
			{
				$new_condition_parts[] = $part;
			}
			
			if($closing_brace) 
			{
				$new_condition_parts[] = ')';
			}
		}
		
		return '<? ' . strtolower($condition_type) . ' (' . implode(' ', $new_condition_parts) . '): ?>';		
	}
	
	
	//parses namespace template blocks: {NAMESPACE:VAR} or {NAMESPACE1:NESTED:NESTED2:VAR}
	private function parse_namespace($block, $top_level = '$vars') 
	{
		$varnames = explode(':', $block); // split = NAMESPACE1 NESTED NESTED2 VAR
		$alias = $top_level;
		
		foreach($varnames as $nest) 
		{
			$alias .= "['$nest']";
		}
		
		return $alias;
	}
	
	
	private function parse_output_block($block, $top_level = '$vars')
	{
		$block = preg_replace('#^\{|\}$#', '', $block);
		
		if( is_int( strpos($block, ':') ) ) // is this a namespaced var?
		{
			if( strpos($block, 'C:') === 0 ) // is this a constant?
			{
				return substr($block, 2);
			}
			
			return $this->parse_namespace($block, $top_level);
		}
		else
		{
			return $top_level . '[\'' . $block .'\']';		
		}
	}
	
	
	private function parse_loop_block($loop, $php_loop_alias = '$vars')
	{
		//first parse opening tag and get loop name.
		$loop_name = preg_replace('#\\{LOOP: ([^\\}]+)\\}(?:.|[\n\r])*#', '$1', $loop);
		$quoted_loop_name = preg_quote($loop_name);
		$loop_name_alias = '$' . str_replace('.', '_', $loop_name); //create a name for the loop for in the php
		
		if( strpos($loop_name, '.') )  // is this loop nested? Only the last part is needed.
		{
			$nests = explode('.', $loop_name);
			$php_loop_alias .= '[\'' . $nests[ count($nests) - 1 ] . '\']';
		}
		else
		{
			$php_loop_alias .= '[\'' . $loop_name . '\']';
		}
		
		$loop_start_replacement = "
		<? if(isset({$php_loop_alias}) && is_array({$php_loop_alias})):
			{$loop_name_alias}_count = count({$php_loop_alias}); 
			foreach({$php_loop_alias} as {$loop_name_alias}_index => {$loop_name_alias}): 
				{$loop_name_alias}['IS_FIRST_ROW'] = !!({$loop_name_alias}_index === 0);
				{$loop_name_alias}['IS_ODD_ROW'] = !!({$loop_name_alias}_index % 2 === 0);
				{$loop_name_alias}['IS_EVEN_ROW'] = !{$loop_name_alias}['IS_ODD_ROW'];
				{$loop_name_alias}['IS_LAST_ROW'] = !!({$loop_name_alias}_index === {$loop_name_alias}_count - 1);
		?>";
		
		//replace the start and end loop tags. We know it has an end tag since it was matched by our regex which includes end tag
		$loop = str_replace("{LOOP: $loop_name}", $loop_start_replacement, $loop);		
		$loop = str_replace("{/LOOP: $loop_name}", '<? endforeach; unset(' . $loop_name_alias . '_count); endif; ?>', $loop);
		
		//now swap all the loop vars inside the loop
		$loop_vars = array();
		preg_match_all('#\\{' . $quoted_loop_name . '\\.[A-Za-z0-9_]+(:[A-Za-z0-9_]+)*\\}#', $loop, $loop_vars);
		$loop_vars = $loop_vars[0];
		
		if($loop_vars) 
		{
			foreach($loop_vars as $l_var)
			{
				//remove the loopname from the output block. Since it exists in our foreach alias
				$varname = str_replace($loop_name . '.', '', $l_var); 
				$replacement = $this->parse_output_block($varname, $loop_name_alias);
				$loop = str_replace($l_var, "<? echo isset($replacement) ? $replacement : '' ?>", $loop);
			}
		}
		
		//now we swap any loop vars used inside condition within the loop.
		$loop_vars = array();
		preg_match_all('#\\{(?:IF|ELSE):.*? (' . $quoted_loop_name . '\\.[A-Za-z0-9_]{3,}(?::[A-Za-z0-9_]+)*)(?:\\s.*?)?\\}#', $loop, $loop_vars);
		$conditions = $loop_vars[0];
		
		if($conditions)
		{
			foreach($conditions as $c)
			{
				//match all occurences of loop vars in this conditional.
				$condition_vars = array();
				preg_match_all('#\s' . $quoted_loop_name . '\\.[A-Za-z0-9_]{3,}(?::[A-Za-z0-9_]+)*(?=\\s|\\})#', $c, $condition_vars);
				$replacement_c = $c;
				$condition_vars = $condition_vars[0];
				
				if($condition_vars)
				{
					foreach($condition_vars as $c_var)
					{
						$fixedname = str_replace(' ' . $loop_name . '.', '', $c_var); //remove the loop name from the var
						$replacement_c = str_replace($c_var, ' ' . $this->parse_output_block($fixedname, $loop_name_alias), $replacement_c);
					}
				}
			
				//now all the loop vars in this condition have been replaced, replace the condition with our new fixed one.
				$loop = str_replace($c, $replacement_c, $loop);
			} 
		}
		
		unset($conditions, $loop_name); 
		
		//last of all, check for any loops inside this loop. and send them back through. 
		$loop_blocks = array();
		preg_match_all($this->regex['loop_match'], $loop, $loop_blocks);
		$loop_blocks = $loop_blocks[0];
		
		if($loop_blocks)
		{
			foreach($loop_blocks as $l) $loop = str_replace($l, $this->parse_loop_block($l, $loop_name_alias), $loop);
		}
			
		return $loop;
	}
	
	
	private function parse_include_block($block, $template_directory = '')
	{
		//get the file name. 
		$filename = $template_directory . '/' . preg_replace($this->regex['include_match'], '$1', $block);

		if( file_exists($filename) )
		{
			return file_get_contents($filename);
		}
		else
		{
			$this->error('Can not find include template: ' . $filename);
		}
	}
	
	private function operator($operator_alias)
	{
		switch($operator_alias)
		{
			case 'not': return '!';
			case 'and': return '&&';
			case 'or' : return '||';
			case 'eq' : return '==';
			case 'neq': return '!=';
			case 'gt' : return '>';
			case 'lt' : return '<';
			case 'lte': return '<=';
			case 'gte': return '>=';
			default: return $operator_alias;
		}
	}
};

?>
