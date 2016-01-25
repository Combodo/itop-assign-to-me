<?php

class AssignToMeMenuExtension implements iPopupMenuExtension
{
	/**
	 * Checks the ticket is in a state where status can be applied
	 */
	public static function IsActionAllowed($oTicket, $iAgentid, $sStimulus)
	{
		$sTicketClass = get_class($oTicket);
		
		// Check transition is valid
		$aTransitions = $oTicket->EnumTransitions();
		$aStimuli = MetaModel::EnumStimuli($sTicketClass);
		if (!isset($aTransitions[$sStimulus]))
		{
			return false;
		}
		
		// Check ticket is not already assigned to me and user has rights to assign ticket
		$iTicketAgentId = $oTicket->Get('agent_id');
		if (($iAgentid == $iTicketAgentId) || (!UserRights::IsStimulusAllowed($sTicketClass, $sStimulus)))
		{
			return false;
		}
		
		// Check agent is in current team	
		$iTicketTeamId = $oTicket->Get('team_id');
		$bIsInTeam = false;
		if (!is_null($iTicketTeamId))
		{
			$sOQL = "SELECT Team AS t JOIN lnkPersonToTeam AS l ON l.team_id = t.id JOIN Person AS p ON l.person_id = p.id WHERE p.id = :p_id";
			$oTeamSet = new DBObjectSet(DBObjectSearch::FromOQL($sOQL), array(), array('p_id' => $iAgentid));
			while($oTeam = $oTeamSet->Fetch())
			{
				$iTeam = $oTeam->GetKey();
				if ($iTicketTeamId == $iTeam)
				{
					$bIsInTeam = true;	
				}
			}
		}
		if (!$bIsInTeam)
		{
			return false;
		}

		return true;
	}
	
	public static function EnumItems($iMenuId, $param)
	{
		switch($iMenuId)
		{
			case iPopupMenuExtension::MENU_OBJLIST_ACTIONS:
				// $param is a DBObjectSet
				$aResult = array();
				break;
					
			case iPopupMenuExtension::MENU_OBJLIST_TOOLKIT:
				// $param is a DBObjectSet
				$aResult = array();
				break;
					
			case iPopupMenuExtension::MENU_OBJDETAILS_ACTIONS:
				// $param is a DBObject
				$aResult = array();
				$oObj = $param;

				// "Assign to me" applies to Tickets only
				if ($oObj instanceof Ticket)
				{
					$sTicketClass = get_class($oObj);
					$aAllowedClasses = MetaModel::GetModuleSetting('itop-assign-to-me', 'allowed_classes', array());
				
					foreach ($aAllowedClasses as $sAllowedClass => $aClass)
					{
						// Check $sAllowedClass is a valid class
						if (($sAllowedClass != '') && !MetaModel::IsValidClass($sAllowedClass))
						{
							IssueLog::Error('Module itop-assign-to-me - invalid class #'.$sAllowedClass.' - name "'.$sAllowedClass.'" is not a valid class');
							break;
						}

						if ($sAllowedClass == $sTicketClass)
						{
							// Check ticket has status where event can be applied
							$sTicketStatus = $oObj->Get('status');
							$bIsInRightStatus = false;
							foreach ($aClass as $sStatus => $sEvent)
							{
								if ($sTicketStatus == $sStatus)
								{
									$bIsInRightStatus = true;
									$sEventToApply = $sEvent;
								}
							}

							if ($bIsInRightStatus)
							{
								$iAgentid = UserRights::GetContactId();
								
								// Check that transition is allowed and that agent can apply it
								if (self::IsActionAllowed($oObj, $iAgentid, $sEventToApply))
								{
									// Set Assign to me additional menu
									$oAppContext = new ApplicationContext();
									$aParams = $oAppContext->GetAsHash();
									
									$aParams['class'] = $sTicketClass;
									$aParams['id'] = $oObj->GetKey();
									$aParams['operation'] = 'apply_stimulus';
									$aParams['stimulus'] = $sEventToApply;
									$aParams['agent_id'] = $iAgentid;
									$sMenu = 'Menu:AssignToMe';
									$aResult = array(
											new SeparatorPopupMenuItem(),
											new URLPopupMenuItem(
													$sMenu.' from '.$sTicketClass,
													Dict::S($sMenu),
													utils::GetAbsoluteUrlModulePage('itop-assign-to-me', 'assign-to-me.php', $aParams)
											),
									);
								}		
							}
							break;
						}							
					}
				}
				break;
					
			case iPopupMenuExtension::MENU_DASHBOARD_ACTIONS:
				// $param is a Dashboard
				$aResult = array();
				break;
					
			default:
				// Unknown type of menu, do nothing
				$aResult = array();
				break;
		}
		return $aResult;
	}
}
