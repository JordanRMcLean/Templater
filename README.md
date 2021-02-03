# Templater
PHP/HTML templater


## Basic Template Vars:
Template vars can contain 0-9, A-Z and _ and must be 2 or more characters in length.

Use in PHP: `$template->set('BASIC_VAR', 'value');`

Use in Template: `{BASIC_VAR}`


## Nested/Namespaced Template Vars:
Template vars can be nested inside each other for namespacing and follow the same rules as above.

Use in PHP:
```php
$template->set('USER:NAME', 'value');

$template->set('USER', array('NAME' => 'value', 'ID' => 1));
```
Use in Template: `{USER:NAME} {USER:ID}`

Template vars can be nested several times: `{USER:ROLE:NAME}`

## Conditionals:
Conditional statements can be used in the templates for dynamic content.
Conditions follow these rules:
- can be any valid PHP conditional expression
- can contain template vars and nested vars. `{IF: BASIC_VAR > 5} {IF: USER:ID == 5}`
- can be strung for longer conditions: `{IF: BASIC_VAR > 5 && BASIC_VAR < 10}`
- can use additional operators: and, or, not, eq, neq, gt, lt, lte, gte `{IF: not USER}`
- can be wrapped in braces for priority evaluating: Eg `{IF: (BASIC_VAR > 5) && OTHER_VAR}`
- space after the colon is vital except in `{ELSE:}`

Use in Template:
```
	{IF: condition}
 		...
	{ELSEIF: condition}
		...
	{ELSE:}
		...
	{/IF}
```

## Template Loops:
Loops can be used for iterating within the template.
Loop names can contain 0-9, a-z and _ and must be 2 or more characters in length.

Use in PHP:
```php
// first record of loop
$template->set_loop('users', array(
	'ID' 	=> 1,
	'NAME' 	=> 'Bill'
));

// second record of loop. Usually used in foreach loop to add multiple records.
$template->set_loop('users', array(
	'ID'	=> 2,
	'NAME' 	=> 'Ben'
));
```

Use in Template:
```
{LOOP: users}
	ID: {users.ID}
	Name: {users.NAME}
{/LOOP: users}
```

Loops abide by the following the rules:
- Loop vars can be used in conditions: `{IF: users.ID == 2}`
- Loops CAN NOT be namespaced vars: `{LOOP: NAME:loop}`
- Namespaced vars can be used in loops: `{users.ROLE:ID}`



Loops can be nested within each other using period separator:

Use in PHP:
```php
$template->set_loop('users.titles', [
	['ID' => 1, 'NAME' => 'Admin'],
	['ID' => 2, 'NAME' => 'Mod']
]);
```

Use in Template:
```
{LOOP: users}
	...
	{LOOP: users.titles}
		Title ID: {users.title.ID}
		Title Name: {users.titles.NAME}
	{/LOOP: users.titles}
	...
{/LOOP: users}
```


## Template Includes:
------------------
Includes can be used to include another template which will also get parsed.
Do not include the file extension if specified in the Template class.

`{INCLUDE: header.html}` or `{INCLUDE: header}`


## Ignore Block:
-----------------
Ignore blocks are ignored by the parser, and nothing in it is parsed.

```
{IGNORE}
	{THIS_VAR_WILL_NOT_BE_PARSED}
{/IGNORE}
```

## Constant Output Var:
--------------------
You can output PHP constants in the templates or use them within conditions:
`PHP version: {C:PHP_VERSION}`

# Thats all folks! Easy peasy!
