<?php
//Demonstrating use of TemplateParser and Template.
require 'TemplateParser.php';
require 'Template.php';
$TemplateParser = new \Template\TemplateParser();


/*
*  Example of constant usage. Output in template using {C:MY_SITE_NAME}
*/
define('MY_SITE_NAME', 'My Awesome Site!');



//load and initiate a template.
//'.html' added within template as defined.
$template = new Template\Template('example');

//or: $template = $TemplateParser::new('example');



//set basic template variable. Output in template using {PAGE_TITLE}
$template->set('PAGE_TITLE', 'Example Template');

//set multiple variables using array.
$template->set([
	'SHOW_USERS'	=> true,
	'SHOW_TIME'		=> true,
	'TIME'			=> (new DateTime())->format('H:i')
]);


//Example set of users to manage.
$users = array(
	['id' => 1, 'forename' => 'Ross', 'surname' => 'Geller', 'age' => '27'],
	['id' => 2, 'forename' => 'Chandler', 'surname' => 'Bing', 'age' => '27'],
	['id' => 3, 'forename' => 'Joey', 'surname' => 'Tribiani', 'age' => '25'],
	['id' => 4, 'forename' => 'Monica', 'surname' => 'Geller', 'age' => '27'],
	['id' => 5, 'forename' => 'Rachel', 'surname' => 'Green', 'age' => '27'],
	['id' => 6, 'forename' => 'Phoebe', 'surname' => 'Buffet', 'age' => '26']
);

/*
*  Set multiple records to the template using set_loop
*  You can then iterate over these in the template.
*/
foreach($users as $user) {

	//loop names use lowercase so the templater can distinguish.
	//output loop variables like: {users.NAME}
	$template->set_loop('users', [
		'NAME'	=> $user['forename'] . ' ' . $user['surname'],
		'ID'	=> $user['id'],
		'AGE'	=> $user['age'],
		'TEST'	=> ['INNER' => 'test', 'INNER2' => 3]
	]);

}

/*
*  Store the current user in a template var.
*  Array keys will be converted to uppercase to match template system.
*  Output in the template using: {CURRENT_USER:ID} {CURRENT_USER:FORENAME}  etc...
*/
$template->set('CURRENT_USER', $users[0]);

//Set a new value within our CURRENT_USER namespace
$template->set('CURRENT_USER:JOB', 'Paleontologist');

//nest even further.
$template->set('CURRENT_USER:SESSION:ID', '0123456789');
$template->set('CURRENT_USER:SESSION:START', (new DateTime())->format('H:i'));



/*
*  When ready to use the template there are many options;
*  You need to parse then compile
*  Or just use the compile method which will also parse if needed.
*  Then do what you wish with the compiled content.
*/

$TemplateParser->compile($template);

//output the compiled results in preferred way.
die( $template->get_compiled() );
