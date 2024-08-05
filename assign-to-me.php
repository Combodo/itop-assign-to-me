<?php
/// Copyright (C) 2010-2021 Combodo SARL
//
//   This file is part of iTop.
//
//   iTop is free software; you can redistribute it and/or modify
//   it under the terms of the GNU Affero General Public License as published by
//   the Free Software Foundation, either version 3 of the License, or
//   (at your option) any later version.
//
//   iTop is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU Affero General Public License for more details.
//
//   You should have received a copy of the GNU Affero General Public License
//   along with iTop. If not, see <http://www.gnu.org/licenses/>

/***********************************************************************************
 *
 * Main user interface page, starts here
 *
 * ***********************************************************************************/
require_once('../approot.inc.php');
require_once(APPROOT.'/application/application.inc.php');
require_once(APPROOT.'/application/wizardhelper.class.inc.php');

require_once(APPROOT.'/application/startup.inc.php');

use \Combodo\iTop\Application\WebPage\iTopWebPage;

try {
	$operation = utils::ReadParam('operation', '');

	$oKPI = new ExecutionKPI();
	$oKPI->ComputeAndReport('Data model loaded');

	$oKPI = new ExecutionKPI();

	require_once(APPROOT.'/application/loginwebpage.class.inc.php');
	$sLoginMessage = LoginWebPage::DoLogin(); // Check user rights and prompt if needed
	$oAppContext = new ApplicationContext();

	$oKPI->ComputeAndReport('User login');

	$oP = new iTopWebPage(Dict::S('UI:WelcomeToITop'));
	$oP->SetMessage($sLoginMessage);

	switch($operation) {
		case 'stimulus':
			$sClass = utils::ReadParam('class', '');
			if (empty($sClass)) {
				throw new ApplicationException(Dict::Format('UI:Error:1ParametersMissing', 'class'));
			}

			$id =  utils::ReadParam('id', '');
			$sStimulus = utils::ReadParam('stimulus');
			$iAgentId = utils::ReadParam('agent_id');

			// Make sure that ticket exists
			$oTicket = MetaModel::GetObject($sClass, $id, false /* MustBeFound */);
			if (!($oTicket instanceof Ticket)) {
				throw new ApplicationException(Dict::Format('UI:Error:WrongActionForClass', $operation, $sClass));
			} else {
				// Double check action is allowed
				if (AssignToMeMenuExtension::IsActionAllowed($oTicket, $iAgentId, $sStimulus)) {
					// Set agent
					$oTicket->Set('agent_id', $iAgentId);

					// Check if some attributes need to be set for the transition
					$aExpectedAttributes = $oTicket->GetTransitionAttributes($sStimulus /*, current state*/);
					$bAttributesToBeSetForTransition = false;
					foreach($aExpectedAttributes as $sAttCode => $iFlag) {
						if (($sAttCode != 'team_id') && ($sAttCode != 'agent_id')) {
							$oAttDef = MetaModel::GetAttributeDef(get_class($oTicket), $sAttCode);
							// Attributes team_id and agent_id should not be part of that check
							if (($iFlag & (OPT_ATT_MUSTCHANGE | OPT_ATT_MUSTPROMPT)) || (($iFlag & OPT_ATT_MANDATORY) && !$oAttDef->HasAValue($oTicket->Get($sAttCode)))) {
								$bAttributesToBeSetForTransition = true;
								break;
							}
						}
					}
					if (!$bAttributesToBeSetForTransition) {
						// No attribute needs to be set for the transition -> apply the stimulus
						$oTicket->ApplyStimulus($sStimulus);
						$oTicket->DBUpdate();

						// ReloadAndDisplay
						$oAppContext = new ApplicationContext();
						$oP->add_header('Location: '.utils::GetAbsoluteUrlAppRoot().'pages/UI.php?operation=details&class='.$sClass.'&id='.$id.'&'.$oAppContext->GetForLink());
					} else {
						// Other attributes need to be set -> join the standard process
						$oP->LinkScriptFromAppRoot("js/json.js");
						$oP->LinkScriptFromAppRoot("js/forms-json-utils.js");
						$oP->LinkScriptFromAppRoot("js/wizardhelper.js");
						$oP->LinkScriptFromAppRoot("js/wizard.utils.js");
						$oP->LinkScriptFromAppRoot("js/links/links_widget.js");
						$oP->LinkScriptFromAppRoot("js/links/links_direct_widget.js");
						$oP->LinkScriptFromAppRoot("js/extkeywidget.js");
						$oP->LinkScriptFromAppRoot("js/jquery.blockUI.js");

						$aPrefillFormParam = array('user' => $_SESSION["auth_user"], 'context' => $oAppContext->GetAsHash(), 'stimulus' => $sStimulus, 'origin' => 'console');
						try {
							$oTicket->DisplayStimulusForm($oP, $sStimulus, $aPrefillFormParam);
						} catch (ApplicationException $e) {
							$sMessage = $e->getMessage();
							$sSeverity = 'info';
							ReloadAndDisplay($oP, $oTicket, 'stimulus', $sMessage, $sSeverity);
						}
					}
				}
			}
			break;

		case 'apply_stimulus':
			include_once (APPROOT.'pages/UI.php');
			die();
			break;

		///////////////////////////////////////////////////////////////////////////////////////////

		default: // Menu node rendering (templates)
			$oP->p('Invalid operation: '.$operation);

		///////////////////////////////////////////////////////////////////////////////////////////
	}
	$oP->output();
}
catch(CoreException $e) {
	require_once(APPROOT.'/setup/setuppage.class.inc.php');
	$oP = new SetupPage(Dict::S('UI:PageTitle:FatalError'));
	if ($e instanceof SecurityException) {
		$oP->add("<h1>".Dict::S('UI:SystemIntrusion')."</h1>\n");
	} else {
		$oP->add("<h1>".Dict::S('UI:FatalErrorMessage')."</h1>\n");
	}
	$oP->error(Dict::Format('UI:Error_Details', $e->getHtmlDesc()));
	$oP->output();

	if (MetaModel::IsLogEnabledIssue()) {
		if (MetaModel::IsValidClass('EventIssue')) {
			try {
				$oLog = new EventIssue();

				$oLog->Set('message', $e->getMessage());
				$oLog->Set('userinfo', '');
				$oLog->Set('issue', $e->GetIssue());
				$oLog->Set('impact', 'Page could not be displayed');
				$oLog->Set('callstack', $e->getTrace());
				$oLog->Set('data', $e->getContextData());
				$oLog->DBInsertNoReload();
			} catch(Exception $e) {
				IssueLog::Error("Failed to log issue into the DB");
			}
		}

		IssueLog::Error($e->getMessage());
	}

	// For debugging only
	//throw $e;
}
catch(Exception $e) {
	require_once(APPROOT.'/setup/setuppage.class.inc.php');
	$oP = new SetupPage(Dict::S('UI:PageTitle:FatalError'));
	$oP->add("<h1>".Dict::S('UI:FatalErrorMessage')."</h1>\n");
	$oP->error(Dict::Format('UI:Error_Details', $e->getMessage()));
	$oP->output();

	if (MetaModel::IsLogEnabledIssue()) {
		if (MetaModel::IsValidClass('EventIssue')) {
			try {
				$oLog = new EventIssue();

				$oLog->Set('message', $e->getMessage());
				$oLog->Set('userinfo', '');
				$oLog->Set('issue', 'PHP Exception');
				$oLog->Set('impact', 'Page could not be displayed');
				$oLog->Set('callstack', $e->getTrace());
				$oLog->Set('data', array());
				$oLog->DBInsertNoReload();
			} catch(Exception $e) {
				IssueLog::Error("Failed to log issue into the DB");
			}
		}

		IssueLog::Error($e->getMessage());
	}
}
