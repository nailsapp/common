<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
| -------------------------------------------------------------------
| SSL routes
| -------------------------------------------------------------------
| This file contains LinkedIn app configurations
|
*/

$config['routes_ssl'] = array(

	//	All of admin secure please
	'admin:any)',
	'admin/:any',
	
	//	All of auth secure
	'auth',
	'auth/:any',

);