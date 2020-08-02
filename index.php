<?php 
//Demonstrating use of TemplateParser and Template.

//include/require or use autoloader
require 'TemplateParser.php';
require 'Template.php';

//Constant defined and can be outputted directly in the template. 
define('OUR_CONSTANT', 'Constant defined for later use.');

//Initiate the parser early incase you want to end your script early with a template.
$Parser = new TemplateParser();

//load and initiate a template. 
$template = new Template('example.html');

//Set the template up based on script usage
$template->set('page_title', 'Template Test');

$users = array(
	array('name' => 'User1', 'id' => 1, 'age' => '27'),
	array('name' => 'User2', 'id' => 2, 'age' => '24'),
	array('name' => 'User3', 'id' => 3, 'age' => '23')
);

foreach($users as $user)
{
	//Use set_loop for iterables. You can then iterate over these in the template. 
	$template->set_loop('user', array(
		'name'	=> $user['name'],
		'id'	=> $user['id'],
		'age'	=> $user['age']
	));
}

$template->set('show_users', true);

//Use namespaced variables within the template to seperate from global. 
$template->set('current_user', $users[0]);
$template->set('current_user:permissions', 'Admin');

//nest even further. 
$template->set('current_user:session:id', '0123456789');
$template->set('current_user:session:start', '0987654321');



//When ready to use a template, parse it and compile it with the TemplateParser
$Parser->parse($template)->compile($template);

//output the compiled results in preferred way. 
die( $template->get_compiled() );

?>