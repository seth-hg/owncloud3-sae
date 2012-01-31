<?php

$RUNTIME_NOAPPS = true;

require_once('lib/base.php');

// create database tables
OC_DB::createDbFromStructure('db_structure.xml');

//create user and group
$username = OC_Config::getValue("username");
$password = OC_Config::getValue("password");

OC_User::createUser($username, $password);
OC_Group::createGroup('admin');
OC_Group::addToGroup($username, 'admin');
OC_User::login($username, $password);

//guess what this does
OC_Installer::installShippedApps();

echo "done."

?>
