<?php
//
// iTop module definition file
//

SetupWebPage::AddModule(
	__FILE__, // Path to the current file, all other file names are relative to the directory containing this file
	'itop-assign-to-me/1.0.0-dev',
	array(
		// Identification
		//
		'label' => 'iTop Assign to Me',
		'category' => 'business',

		// Setup
		//
		'dependencies' => array(
			'itop-tickets/3.2.0',
			'itop-auto-assignment/0.1.0||itop-auto-dispatch-ticket/0.1.0||combodo-autodispatch-ticket/1.0.2',
	),
		'mandatory' => false,
		'visible' => true,
		
		// Components
		//
		'datamodel' => array(
			'model.itop-assign-to-me.php',
		),
		'webservice' => array(
			
		),
		'data.struct' => array(
			// add your 'structure' definition XML files here,
		),
		'data.sample' => array(
			// add your sample data XML files here,
		),
		
		// Documentation
		//
		'doc.manual_setup' => '', // hyperlink to manual setup documentation, if any
		'doc.more_information' => '', // hyperlink to more information, if any 

		// Default settings
		//
	)
);

