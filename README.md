# Templater
PHP/HTML templater


Basic Template Vars:
------------------------
 Template vars can contains 0-9, a-z, A-Z and _ and must be 3 characters in length at least.
   
    $template_class->set('BASIC_VAR', 'value');
    Use in template as {BASIC_VAR} -> value
  
Nested/Namespaced Template Vars:
---------------------------
  Template vars can be nested inside each other for name spacing.
  
    $template_class->set('USER:NAME', 'value');
    OR $template->set('USER', array('NAME' => 'value', 'ID' => 1));
    Use in template as {USER:NAME}
  
Conditionals:
----------------
  Conditional statements can be used in the templates as so:
  
      {IF: condition} ... {ELSEIF: condition} ... {ELSE:} ... {/IF} - space between colon and condition is vital except in {ELSE:}
      
Condition:
* The condition can be any valid PHP conditional expression
* The condition can optionally contain template vars and nested vars in. Eg. {IF: BASIC_VAR > 5} or {IF: USER:ID == 5}
* The condition can be strung for longer conditions: {IF: BASIC_VAR > 5 && BASIC_VAR < 10}
* The condition can use additional operators: and, or, not, eq, neq, gt, lt, lte, gte {IF: not USER}
* Each part of the condition must be seperated by a space. DONT use space in any of literal values in conditions.
* The condition can be wrapped in braces for priority evaluating: Eg {IF: (BASIC_VAR > 5) && OTHER_VAR}
  
Loops:
----------------
Loops can be used for repeating blocks. Loop names should be named with the same naming conventions as template vars.

    $template->set_loop('myloop', array('LOOPVAR' => 1, 'VARLOOP' => 2)); // first record of loop
    $template->set_loop('myloop', array('LOOPVAR' => 2, 'VARLOOP' => 3)); // second record of loop
    
In template:
    
      {LOOP: myloop} Loop var: {myloop.LOOPVAR}, Var Loop: {myloop.VARLOOP} {/LOOP: myloop}
      Result: Loop var: 1, Var Loop: 2 Loop var: 2, Var Loop: 3
  
Loop vars can be used in conditions: 
     
     {IF: myloop.LOOPVAR == 2}
     
Loops can be nested:

     {LOOP: myloop} {LOOP: myloop.innerloop} {myloop.innerloop.INNERVAR} {/LOOP: myloop.innerloop} {/LOOP: myloop}
  
Loops can NOT be nested/namesspaced vars:

     {LOOP: NAME:loop} - X - NOT ALLOWED
    
 However namespace vars can be used in loops:
 
     {LOOP: myloop} {myloop.NAME:VAR} {/LOOP: myloop}
  
Includes:
------------------
Includes can be used to include another template which will also get parsed. 

    {INCLUDE: header.html}
  
 
Ignore block:
-----------------
Ignore blocks are ignored by the parser, and nothing in it is parsed.

    {IGNORE} {THIS_VAR_WILL_NOT_BE_PARSED} {/IGNORE}
	
Constant Output Var:
--------------------
You can output PHP constants or defined constants directly in the templates: 

  	{C:PHP_CONSTANT} or {IF: C:PHP_CONSTANT == TEMPLATE_VAR}
 
