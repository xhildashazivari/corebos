<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
require_once 'Smarty_setup.php';
require 'modules/Vtiger/default_module_view.php';
if (isset($override_singlepane_view)) {
	$singlepane_view = $override_singlepane_view;
}
global $mod_strings, $app_strings, $currentModule, $current_user, $theme, $log;

$action = vtlib_purify($_REQUEST['action']);
$record = vtlib_purify($_REQUEST['record']);
$isduplicate = isset($_REQUEST['isDuplicate']) ? vtlib_purify($_REQUEST['isDuplicate']) : false;

if ($singlepane_view == 'true' && $action == 'CallRelatedList') {
	echo "<script>document.location='index.php?action=DetailView&module=".urlencode($currentModule).'&record='.urlencode($record)."';</script>";
	die();
} else {
	$tool_buttons = Button_Check($currentModule);

	$focus = CRMEntity::getInstance($currentModule);
	if ($record != '') {
		$focus->retrieve_entity_info($record, $currentModule);
		$focus->id = $record;
	}

	$smarty = new vtigerCRM_Smarty;

	if ($isduplicate == 'true') {
		$focus->id = '';
	}
	if (isset($_REQUEST['mode']) && $_REQUEST['mode'] != ' ') {
		$smarty->assign('OP_MODE', vtlib_purify($_REQUEST['mode']));
	}
	if (empty($_SESSION['rlvs'][$currentModule])) {
		coreBOS_Session::delete('rlvs');
	}

	// Identify this module as custom module.
	$smarty->assign('CUSTOM_MODULE', $focus->IsCustomModule);
	$errormessageclass = isset($_REQUEST['error_msgclass']) ? vtlib_purify($_REQUEST['error_msgclass']) : '';
	$errormessage = isset($_REQUEST['error_msg']) ? vtlib_purify($_REQUEST['error_msg']) : '';
	$smarty->assign('ERROR_MESSAGE_CLASS', $errormessageclass);
	$smarty->assign('ERROR_MESSAGE', $errormessage);

	$smarty->assign('APP', $app_strings);
	$smarty->assign('MOD', $mod_strings);
	$smarty->assign('MODULE', $currentModule);
	$smarty->assign('SINGLE_MOD', getTranslatedString('SINGLE_'.$currentModule, $currentModule));
	$smarty->assign('IMAGE_PATH', "themes/$theme/images/");
	$smarty->assign('THEME', $theme);
	$smarty->assign('ID', $focus->id);
	$smarty->assign('MODE', $focus->mode);
	$smarty->assign('CHECK', $tool_buttons);

	$smarty->assign('NAME', empty($focus->column_fields[$focus->def_detailview_recname]) ? '' : $focus->column_fields[$focus->def_detailview_recname]);
	$smarty->assign('UPDATEINFO', updateInfo($focus->id));

	// Module Sequence Numbering
	$mod_seq_field = getModuleSequenceField($currentModule);
	if ($mod_seq_field != null) {
		$mod_seq_id = $focus->column_fields[$mod_seq_field['name']];
	} else {
		$mod_seq_id = $focus->id;
	}
	$smarty->assign('MOD_SEQ_ID', $mod_seq_id);
	$bmapname = $currentModule.'RelatedPanes';
	$cbMapid = GlobalVariable::getVariable('BusinessMapping_'.$bmapname, cbMap::getMapIdByName($bmapname));
	if ($cbMapid) {
		if (empty($_REQUEST['RelatedPane'])) {
			$_RelatedPane=vtlib_purify($_SESSION['RelatedPane']);
		} else {
			$_RelatedPane=vtlib_purify($_REQUEST['RelatedPane']);
			coreBOS_Session::set('RelatedPane', $_RelatedPane);
		}
		$smarty->assign('RETURN_RELATEDPANE', $_RelatedPane);
		$cbMap = cbMap::getMapByID($cbMapid);
		$rltabs = $cbMap->RelatedPanes($focus->id);
		$smarty->assign('RLTabs', $rltabs['panes']);
		$restrictedRelations = (isset($rltabs['panes'][$_RelatedPane]['restrictedRelations']) ? $rltabs['panes'][$_RelatedPane]['restrictedRelations'] : null);
		$related_array = array();
		$rel_array = getRelatedLists($currentModule, $focus, $restrictedRelations);
		foreach ($rltabs['panes'][$_RelatedPane]['blocks'] as $blk) {
			if ($blk['type']=='RelatedList') {
				if (empty($rel_array[$blk['loadfrom']])) {
					if (empty($rel_array[$blk['label']])) {
						$i18n = getTranslatedString($blk['label'], $blk['label']);
						if (empty($rel_array[$i18n])) {
							if (!empty($blk['relatedid'])) {
								foreach ($rel_array as $RLLabel => $RLDetails) {
									if ($RLDetails['relationId']==$blk['relatedid']) {
										$related_array[$RLLabel] = $RLDetails;
										break;
									}
								}
							}
						} else {
							$related_array[$blk['loadfrom']] = $rel_array[$i18n];
						}
					} else {
						$related_array[$blk['loadfrom']] = $rel_array[$blk['label']];
					}
				} else {
					$related_array[$blk['loadfrom']] = $rel_array[$blk['loadfrom']];
				}
			} else {
				if (!empty($blk['loadphp'])) {
					try {
						include $blk['loadphp'];
					} catch (Exception $e) {
						$log->fatal('Related Pane LoadPHP error ('.$blk['loadphp'].'): '.$e->getMessage());
					}
				}
				$related_array[$blk['sequence']] = $blk;
			}
		}
		$smarty->assign('HASRELATEDPANES', 'true');
		if (file_exists("modules/$currentModule/RelatedPaneActions.php")) {
			include "modules/$currentModule/RelatedPaneActions.php";
			$smarty->assign('HASRELATEDPANESACTIONS', 'true');
		} else {
			$smarty->assign('HASRELATEDPANESACTIONS', 'false');
		}
	} else {
		$smarty->assign('HASRELATEDPANES', 'false');
		$restrictedRelations = null;
		$related_array = getRelatedLists($currentModule, $focus, $restrictedRelations);
	}
	$smarty->assign('RELATEDLISTS', $related_array);

	require_once 'include/ListView/RelatedListViewSession.php';
	if (!empty($_REQUEST['selected_header']) && !empty($_REQUEST['relation_id'])) {
		$relationId = vtlib_purify($_REQUEST['relation_id']);
		RelatedListViewSession::addRelatedModuleToSession($relationId, vtlib_purify($_REQUEST['selected_header']));
	}
	$open_related_modules = RelatedListViewSession::getRelatedModulesFromSession();
	$smarty->assign('SELECTEDHEADERS', $open_related_modules);
	// Gather the custom link information to display
	include_once 'vtlib/Vtiger/Link.php';
	$customlink_params = array('MODULE'=>$currentModule, 'RECORD'=>$focus->id, 'ACTION'=>vtlib_purify($_REQUEST['action']));
	$smarty->assign(
		'CUSTOM_LINKS',
		Vtiger_Link::getAllByType(getTabid($currentModule), array('DETAILVIEWBUTTON','DETAILVIEWBUTTONMENU'), $customlink_params, null, $focus->id)
	);

	$smarty->display('RelatedLists.tpl');
}
?>
