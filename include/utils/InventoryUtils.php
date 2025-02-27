<?php
/*********************************************************************************
** The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ********************************************************************************/
require_once 'modules/Emails/mail.php';

/**
 * This function returns the Product detail block values in array format.
 * @param string $module - module name,
 * @param object $focus - module object,
 * @param integer $num_of_products - no.of products associated with it
 * @param array $associated_prod = associated product details
 * @return array Product detail block values
 */
function getProductDetailsBlockInfo($mode, $module, $focus = '', $num_of_products = '', $associated_prod = '') {
	global $log;
	$log->debug('> getProductDetailsBlockInfo '.$mode.','.$module.','.$num_of_products.','.$associated_prod);

	$productDetails = array();
	$productBlock = array();
	if ($num_of_products=='') {
		$num_of_products = getNoOfAssocProducts($module, $focus);
	}
	$productDetails['no_products'] = $num_of_products;
	if ($associated_prod=='') {
		$productDetails['product_details'] = getAssociatedProducts($module, $focus);
	} else {
		$productDetails['product_details'] = $associated_prod;
	}
	if ($focus != '') {
		$productBlock[] = array('mode'=>$focus->mode);
		$productBlock[] = $productDetails['product_details'];
		$productBlock[] = array('taxvalue' => $focus->column_fields['txtTax']);
		$productBlock[] = array('taxAdjustment' => $focus->column_fields['txtAdjustment']);
		$productBlock[] = array('hdnSubTotal' => $focus->column_fields['hdnSubTotal']);
		$productBlock[] = array('hdnGrandTotal' => $focus->column_fields['hdnGrandTotal']);
	} else {
		$productBlock[] = array(array());
	}
	$log->debug('< getProductDetailsBlockInfo');
	return $productBlock;
}

/**This function is used to get the quantity in stock of a given product
*Param $product_id - product id
*Returns type numeric
*/
function getPrdQtyInStck($product_id) {
	global $log, $adb;
	$log->debug('> getPrdQtyInStck '.$product_id);
	$result=$adb->pquery('SELECT qtyinstock FROM vtiger_products WHERE productid=?', array($product_id));
	if ($result && $adb->num_rows($result)>0) {
		$qtyinstck=$adb->query_result($result, 0, 'qtyinstock');
		if ($qtyinstck=='') {
			$qtyinstck=0;
		}
	} else {
		$qtyinstck=0;
	}
	$log->debug('< getPrdQtyInStck');
	return $qtyinstck;
}

/**This function is used to get the reorder level of a product
*Param $product_id - product id
*Returns type numeric
*/
function getPrdReOrderLevel($product_id) {
	global $log, $adb;
	$log->debug('> getPrdReOrderLevel '.$product_id);
	$result=$adb->pquery('SELECT reorderlevel FROM vtiger_products WHERE productid = ?', array($product_id));
	$reorderlevel= $adb->query_result($result, 0, 'reorderlevel');
	$log->debug('< getPrdReOrderLevel');
	return $reorderlevel;
}

/**	function to get the taxid
 *	@param string $type - tax type (VAT or Sales or Service)
 *	return int   $taxid - taxid corresponding to the Tax type from vtiger_inventorytaxinfo vtiger_table
 */
function getTaxId($type) {
	global $adb, $log;
	$log->debug('> getTaxId '.$type);

	list($void,$taxid) = cbEventHandler::do_filter('corebos.filter.TaxCalculation.getTaxId', array($type, ''));
	if ($taxid=='') {
		$res = $adb->pquery('SELECT taxid FROM vtiger_inventorytaxinfo WHERE taxname=?', array($type));
		if ($res && $adb->num_rows($res)>0) {
			$taxid = $adb->query_result($res, 0, 'taxid');
		}
	}
	$log->debug('< getTaxId '.$taxid);
	return $taxid;
}

/**	function to get the taxpercentage
 *	@param string $type       - tax type (VAT or Sales or Service)
 *	return int $taxpercentage - taxpercentage corresponding to the Tax type from vtiger_inventorytaxinfo vtiger_table
 */
function getTaxPercentage($type) {
	global $adb, $log;
	$log->debug('> into getTaxPercentage '.$type);

	list($void,$taxpercentage) = cbEventHandler::do_filter('corebos.filter.TaxCalculation.getTaxPercentage', array($type, ''));
	if ($taxpercentage=='') {
		$res = $adb->pquery('SELECT percentage FROM vtiger_inventorytaxinfo WHERE taxname = ?', array($type));
		if ($res && $adb->num_rows($res)>0) {
			$taxpercentage = $adb->query_result($res, 0, 'percentage');
		}
	}
	$log->debug('< getTaxPercentage '.$taxpercentage);
	return $taxpercentage;
}

/**	function to get the product's taxpercentage
 *	@param string $type       - tax type (VAT or Sales or Service)
 *	@param id  $productid     - productid to which we want the tax percentage
 *	@param id  $default       - if 'default' then first look for product's tax percentage and product's tax is empty then it will return the default configured
 *								tax percentage, else it will return the product's tax (not look for default value)
 *	return int $taxpercentage - taxpercentage corresponding to the Tax type from vtiger_inventorytaxinfo vtiger_table
 */
function getProductTaxPercentage($type, $productid, $default = '') {
	global $adb, $log;
	$log->debug('> into getProductTaxPercentage '.$type.','.$productid);

	list($void1,$void2,$taxpercentage) = cbEventHandler::do_filter('corebos.filter.TaxCalculation.getProductTaxPercentage', array($type, $productid, ''));
	if ($taxpercentage=='') {
		$res = $adb->pquery(
			'SELECT taxpercentage
				FROM vtiger_inventorytaxinfo
				INNER JOIN vtiger_producttaxrel ON vtiger_inventorytaxinfo.taxid = vtiger_producttaxrel.taxid
				WHERE vtiger_producttaxrel.productid = ? AND vtiger_inventorytaxinfo.taxname = ?',
			array($productid, $type)
		);
		if ($res && $adb->num_rows($res)>0) {
			$taxpercentage = $adb->query_result($res, 0, 'taxpercentage');
		}
	}
	//This is to retrieve the default configured value if the taxpercentage related to product is empty
	if ($taxpercentage == '' && $default == 'default') {
		$taxpercentage = getTaxPercentage($type);
	}

	$log->debug('< getProductTaxPercentage '.$taxpercentage);
	return $taxpercentage;
}

/**	Function used to add the history entry in the relevant tables for PO, SO, Quotes and Invoice modules
 *	@param string 	$module		- current module name
 *	@param int 	$id		- entity id
 *	@param string 	$relatedname	- parent name of the entity ie, required field venor name for PO and account name for SO, Quotes and Invoice
 *	@param float 	$total		- grand total value of the product details included tax
 *	@param string 	$history_fldval	- history field value ie., quotestage for Quotes and status for PO, SO and Invoice
 */
function addInventoryHistory($module, $id, $relatedname, $total, $history_fldval) {
	global $log, $adb;
	$log->debug("> addInventoryHistory $module, $id, $relatedname, $total, $history_fldval");

	$history_table_array = array(
		'PurchaseOrder'=>'vtiger_postatushistory',
		'SalesOrder'=>'vtiger_sostatushistory',
		'Quotes'=>'vtiger_quotestagehistory',
		'Invoice'=>'vtiger_invoicestatushistory'
	);

	$histid = $adb->getUniqueID($history_table_array[$module]);
	$modifiedtime = $adb->formatDate(date('Y-m-d H:i:s'), true);
	$query = "insert into $history_table_array[$module] values(?,?,?,?,?,?)";
	$qparams = array($histid,$id,$relatedname,$total,$history_fldval,$modifiedtime);
	$adb->pquery($query, $qparams);
	$log->debug('< addInventoryHistory');
}

/**	Function used to get the list of Tax types as a array
 *	@param string $available - available or empty where as default is all,
 *		if available then the taxes which are available now will be returned otherwise all taxes will be returned
 *	@param string $sh - sh or empty, if sh passed then the shipping and handling related taxes will be returned
 *	@param string $mode - edit or empty, if mode is edit, then it will return taxes including disabled.
 *	@param string $id - crmid or empty, getting crmid to get tax values..
 *	return array $taxtypes - return all the tax types as a array
 */
function getAllTaxes($available = 'all', $sh = '', $mode = '', $id = '') {
	global $adb, $log, $default_charset;
	$log->debug("> getAllTaxes $available,$sh,$mode,$id");
	$taxtypes = array();
	list($void1,$void2,$void3,$void4,$taxtypes) = cbEventHandler::do_filter('corebos.filter.TaxCalculation.getAllTaxes', array($available,$sh,$mode,$id, array()));
	if (empty($taxtypes)) {
		if ($sh != '' && $sh == 'sh') {
			$tablename = 'vtiger_shippingtaxinfo';
			$value_table='vtiger_inventoryshippingrel';
		} else {
			$tablename = 'vtiger_inventorytaxinfo';
			$value_table='vtiger_inventoryproductrel';
		}

		if ($mode == 'edit' && $id != '') {
			//Getting total no of taxes

			$result_ids=array();
			$result=$adb->pquery("select taxname,taxid from $tablename", array());
			$noofrows=$adb->num_rows($result);

			$inventory_tax_val_result=$adb->pquery("select * from $value_table where id=?", array($id));

			//Finding which taxes are associated with this (SO,PO,Invoice,Quotes) and getting its taxid.
			for ($i = 0; $i < $noofrows; $i++) {
				$taxname=$adb->query_result($result, $i, 'taxname');
				$taxid=$adb->query_result($result, $i, 'taxid');

				$tax_val=$adb->query_result($inventory_tax_val_result, 0, $taxname);
				if ($tax_val != '') {
					$result_ids[] = $taxid;
				}
			}
			//We are selecting taxes using that taxids. So It will get the tax even if the tax is disabled.
			$where_ids='';
			if (!empty($result_ids)) {
				$insert_str = str_repeat('?,', count($result_ids)-1);
				$insert_str .= '?';
				$where_ids="taxid in ($insert_str) or";
			}

			$res = $adb->pquery("select * from $tablename where $where_ids deleted=0 order by taxid", $result_ids);
		} else {
			//This where condition is added to get all products or only availble products
			$where = '';
			if ($available != 'all' && $available == 'available') {
				$where = " where $tablename.deleted=0";
			}

			$res = $adb->pquery("select * from $tablename $where order by deleted", array());
		}

		$noofrows = $adb->num_rows($res);
		for ($i = 0; $i < $noofrows; $i++) {
			$taxtypes[$i]['taxid'] = $adb->query_result($res, $i, 'taxid');
			$taxtypes[$i]['taxname'] = $adb->query_result($res, $i, 'taxname');
			$taxtypes[$i]['taxlabel'] = html_entity_decode($adb->query_result($res, $i, 'taxlabel'), ENT_QUOTES, $default_charset);
			$taxtypes[$i]['percentage'] = $adb->query_result($res, $i, 'percentage');
			$taxtypes[$i]['deleted'] = $adb->query_result($res, $i, 'deleted');
			if ($sh == '') {
				$taxtypes[$i]['retention'] = $adb->query_result($res, $i, 'retention');
				$taxtypes[$i]['default'] = $adb->query_result($res, $i, 'tdefault');
				$taxtypes[$i]['qcreate'] = $adb->query_result($res, $i, 'qcreate');
			}
		}
	} // corebos tax calculation
	$log->debug('< getAllTaxes');
	return $taxtypes;
}

/**	Function used to get all the tax details which are associated to the given product
 *	@param int $productid - product id to which we want to get all the associated taxes
 *	@param string $available - available or empty or available_associated where as default is all,
 *		if available then the taxes which are available now will be returned,
 *		if all then all taxes will be returned
 *		otherwise if the value is available_associated then all the associated taxes even they are not available and all the available taxes will be returned
 *	@param int crmid of account,contact or vendor to restrict tax value
 *	@return array $tax_details - tax details as a array with productid, taxid, taxname, percentage and deleted
 */
function getTaxDetailsForProduct($productid, $available = 'all', $acvid = 0) {
	global $log, $adb, $default_charset;
	$log->debug('> getTaxDetailsForProduct '.$productid);
	$tax_details = array();
	if ($productid != '') {
		list($void1,$void2,$void,$tax_details) = cbEventHandler::do_filter(
			'corebos.filter.TaxCalculation.getTaxDetailsForProduct',
			array($productid, $available, $acvid, array())
		);
		if (empty($tax_details)) {
		//where condition added to avoid to retrieve the non available taxes
			$where = '';
			if ($available != 'all' && $available == 'available') {
				$where = ' and vtiger_inventorytaxinfo.deleted=0';
			}
			if ($available != 'all' && $available == 'available_associated') {
				$query = 'SELECT COALESCE(`taxpercentage`,`percentage`) as taxpercentage, vtiger_inventorytaxinfo.*
					FROM vtiger_inventorytaxinfo
					LEFT JOIN vtiger_producttaxrel ON vtiger_inventorytaxinfo.taxid = vtiger_producttaxrel.taxid and vtiger_producttaxrel.productid=?
					WHERE vtiger_inventorytaxinfo.deleted=0';
			} else {
				$query = 'SELECT vtiger_producttaxrel.*, vtiger_inventorytaxinfo.*
					FROM vtiger_inventorytaxinfo
					INNER JOIN vtiger_producttaxrel ON vtiger_inventorytaxinfo.taxid = vtiger_producttaxrel.taxid
					WHERE vtiger_producttaxrel.productid = ? '.$where;
			}
			$params = array($productid);

			$res = $adb->pquery($query, $params);
			for ($i=0; $i<$adb->num_rows($res); $i++) {
				$tax_details[$i]['productid'] = $productid;
				$tax_details[$i]['taxid'] = $adb->query_result($res, $i, 'taxid');
				$tax_details[$i]['taxname'] = $adb->query_result($res, $i, 'taxname');
				$tax_details[$i]['taxlabel'] = html_entity_decode($adb->query_result($res, $i, 'taxlabel'), ENT_QUOTES, $default_charset);
				$tax_details[$i]['percentage'] = $adb->query_result($res, $i, 'taxpercentage');
				$tax_details[$i]['deleted'] = $adb->query_result($res, $i, 'deleted');
			}
		}
	} else {
		$log->debug('Product id is empty. we cannot retrieve the associated taxes.');
	}

	$log->debug('< getTaxDetailsForProduct');
	return $tax_details;
}

/**	Function used to delete the Inventory product details for the passed entity
 *	@param int $objectid - entity id to which we want to delete the product details values where as the entity will be Purchase Order, Sales Order, Quotes or Invoice
 */
function deleteInventoryProductDetails($focus) {
	global $log, $adb,$updateInventoryProductRel_update_product_array;
	$log->debug('> deleteInventoryProductDetails '.$focus->id);
	$product_info = $adb->pquery('SELECT productid, quantity, sequence_no, incrementondel from vtiger_inventoryproductrel WHERE id=?', array($focus->id));
	$numrows = $adb->num_rows($product_info);
	for ($index = 0; $index <$numrows; $index++) {
		$productid = $adb->query_result($product_info, $index, 'productid');
		$sequence_no = $adb->query_result($product_info, $index, 'sequence_no');
		$qty = $adb->query_result($product_info, $index, 'quantity');
		$incrementondel = $adb->query_result($product_info, $index, 'incrementondel');

		if ($incrementondel) {
			$focus->update_product_array[$focus->id][$sequence_no][$productid]= $qty;
			$sub_prod_query = $adb->pquery('SELECT productid from vtiger_inventorysubproductrel WHERE id=? AND sequence_no=?', array($focus->id,$sequence_no));
			if ($adb->num_rows($sub_prod_query)>0) {
				for ($j=0; $j<$adb->num_rows($sub_prod_query); $j++) {
					$sub_prod_id = $adb->query_result($sub_prod_query, $j, 'productid');
					$focus->update_product_array[$focus->id][$sequence_no][$sub_prod_id]= $qty;
				}
			}
		}
	}
	$updateInventoryProductRel_update_product_array = (isset($focus->update_product_array) ? $focus->update_product_array : array());
	$adb->pquery('delete from vtiger_inventoryproductrel where id=?', array($focus->id));
	$adb->pquery('delete from vtiger_inventorysubproductrel where id=?', array($focus->id));
	$adb->pquery('delete from vtiger_inventoryshippingrel where id=?', array($focus->id));
	$log->debug('< deleteInventoryProductDetails');
}

function updateInventoryProductRel($entity) {
	global $log, $adb,$updateInventoryProductRel_update_product_array,$updateInventoryProductRel_deduct_stock;
	$entity_id = vtws_getIdComponents($entity->getId());
	$entity_id = $entity_id[1];
	$update_product_array = $updateInventoryProductRel_update_product_array;
	$log->debug('> updateInventoryProductRel '.$entity_id);

	if (!empty($update_product_array)) {
		foreach ($update_product_array as $seq) {
			foreach ($seq as $product_info) {
				foreach ($product_info as $key => $index) {
					$updqtyinstk= getPrdQtyInStck($key);
					$upd_qty = $updqtyinstk+$index;
					updateProductQty($key, $upd_qty);
				}
			}
		}
	}

	$moduleName = $entity->getModuleName();
	if ($moduleName === 'Invoice' || $moduleName === 'Issuecards' || $moduleName === 'Receiptcards') {
		$statusFieldName = 'invoicestatus';
		$statusFieldValue = 'Cancel';
	} elseif ($moduleName === 'SalesOrder') {
		$statusFieldName = 'sostatus';
		$statusFieldValue = 'Cancelled';
	} elseif ($moduleName === 'PurchaseOrder') {
		$statusFieldName = 'postatus';
		$statusFieldValue = 'Received Shipment';
	}
	$statusChanged = false;
	$vtEntityDelta = new VTEntityDelta();
	$oldEntity = $vtEntityDelta->getOldValue($moduleName, $entity_id, $statusFieldName);
	$recordDetails = $entity->getData();
	$statusChanged = $vtEntityDelta->hasChanged($moduleName, $entity_id, $statusFieldName);
	if ($statusChanged) {
		if ($recordDetails[$statusFieldName] == $statusFieldValue) {
			$adb->pquery('UPDATE vtiger_inventoryproductrel SET incrementondel=0 WHERE id=?', array($entity_id));
			$updateInventoryProductRel_deduct_stock = false;
			if (empty($update_product_array)) {
				addProductsToStock($entity_id);
			}
		} elseif ($oldEntity == $statusFieldValue) {
			$updateInventoryProductRel_deduct_stock = false;
			deductProductsFromStock($entity_id);
		}
	} elseif (empty($recordDetails[$statusFieldName]) || $recordDetails[$statusFieldName] == $statusFieldValue) {
		$updateInventoryProductRel_deduct_stock = false;
	}

	if ($updateInventoryProductRel_deduct_stock) {
		$adb->pquery('UPDATE vtiger_inventoryproductrel SET incrementondel=1 WHERE id=?', array($entity_id));

		$product_info = $adb->pquery('SELECT productid,sequence_no, quantity from vtiger_inventoryproductrel WHERE id=?', array($entity_id));
		$numrows = $adb->num_rows($product_info);
		for ($index = 0; $index <$numrows; $index++) {
			$productid = $adb->query_result($product_info, $index, 'productid');
			$qty = $adb->query_result($product_info, $index, 'quantity');
			$sequence_no = $adb->query_result($product_info, $index, 'sequence_no');
			$qtyinstk= getPrdQtyInStck($productid);
			$upd_qty = $qtyinstk-$qty;
			updateProductQty($productid, $upd_qty);
			$sub_prod_query = $adb->pquery('SELECT productid from vtiger_inventorysubproductrel WHERE id=? AND sequence_no=?', array($entity_id,$sequence_no));
			if ($adb->num_rows($sub_prod_query)>0) {
				for ($j=0; $j<$adb->num_rows($sub_prod_query); $j++) {
					$sub_prod_id = $adb->query_result($sub_prod_query, $j, 'productid');
					$sqtyinstk= getPrdQtyInStck($sub_prod_id);
					$supd_qty = $sqtyinstk-$qty;
					updateProductQty($sub_prod_id, $supd_qty);
				}
			}
		}
	}
	$log->debug('< updateInventoryProductRel');
}

/**	Function used to save the Inventory product details for the passed entity
 *	@param object reference $focus - object reference to which we want to save the product details from REQUEST values where as the entity will be an Inventory module
 *	@param string $module - module name
 *	@param $update_prod_stock - true or false (default), if true we have to update the stock for PO only
 *	@return void
 */
function saveInventoryProductDetails(&$focus, $module, $update_prod_stock = 'false', $updateDemand = '') {
	global $log, $adb;
	$id=$focus->id;
	$log->debug('> saveInventoryProductDetails '.$module);
	//Added to get the convertid
	if (isset($_REQUEST['convert_from']) && $_REQUEST['convert_from'] !='') {
		$id=vtlib_purify($_REQUEST['return_id']);
	} elseif (isset($_REQUEST['duplicate_from']) && $_REQUEST['duplicate_from'] !='') {
		$id=vtlib_purify($_REQUEST['duplicate_from']);
	}
	$ipr_cols = $adb->getColumnNames('vtiger_inventoryproductrel');
	if ($focus->mode == 'edit') {
		if ($_REQUEST['taxtype'] == 'group') {
			$all_available_taxes = getAllTaxes('available', '', 'edit', $id);
		}
		deleteInventoryProductDetails($focus);
	} else {
		if ($_REQUEST['taxtype'] == 'group') {
			$all_available_taxes = getAllTaxes('available', '', 'edit', $id);
		}
	}
	$tot_no_prod = $_REQUEST['totalProductCount'];
	if ($module != 'PurchaseOrder' && $module != 'Receiptcards') {
		if (GlobalVariable::getVariable('Application_B2B', '1')=='1') {
			$acvid = isset($focus->column_fields['account_id']) ?
				$focus->column_fields['account_id'] :
				(isset($focus->column_fields['accid']) ? $focus->column_fields['accid'] : 0);
		} else {
			$acvid = isset($focus->column_fields['contact_id']) ?
			$focus->column_fields['contact_id'] :
			(isset($focus->column_fields['ctoid']) ? $focus->column_fields['ctoid'] : 0);
		}
	} else {
		$acvid = isset($focus->column_fields['vendor_id']) ?
			$focus->column_fields['vendor_id'] :
			(isset($focus->column_fields['vendorid']) ? $focus->column_fields['vendorid'] : 0);
	}
	//If the taxtype is group then retrieve all available taxes, else retrive associated taxes for each product inside loop
	$prod_seq=1;
	for ($i=1; $i<=$tot_no_prod; $i++) {
		//if the product is deleted then we should avoid saving the deleted products
		if ($_REQUEST['deleted'.$i] == 1) {
			continue;
		}

		$prod_id = $_REQUEST['hdnProductId'.$i];
		if (isset($_REQUEST['productDescription'.$i])) {
			$description = $_REQUEST['productDescription'.$i];
		} else {
			$description = '';
		}
		$qty = $_REQUEST['qty'.$i];
		$listprice = $_REQUEST['listPrice'.$i];
		$comment = $_REQUEST['comment'.$i];

		//we have to update the Product stock for PurchaseOrder if $update_prod_stock is true
		if ($module == 'PurchaseOrder' && $update_prod_stock == 'true') {
			addToProductStock($prod_id, $qty);
		}
		if ($module == 'SalesOrder') {
			if ($updateDemand == '-') {
				deductFromProductDemand($prod_id, $qty);
			} elseif ($updateDemand == '+') {
				addToProductDemand($prod_id, $qty);
			}
		}

		if (!empty($_REQUEST['lineitem_id'.$i]) && $focus->mode == 'edit') {
			$lineitem_id = vtlib_purify($_REQUEST['lineitem_id'.$i]);
			$query ='insert into vtiger_inventoryproductrel(id, productid, sequence_no, quantity, listprice, comment, description, lineitem_id) values(?,?,?,?,?,?,?,?)';
			$qparams = array($focus->id,$prod_id,$prod_seq,$qty,$listprice,$comment,$description,$lineitem_id);
			$adb->pquery($query, $qparams);
		} else {
			$query ='insert into vtiger_inventoryproductrel(id, productid, sequence_no, quantity, listprice, comment, description) values(?,?,?,?,?,?,?)';
			$qparams = array($focus->id,$prod_id,$prod_seq,$qty,$listprice,$comment,$description);
			$adb->pquery($query, $qparams);
			$lineitem_id = $adb->getLastInsertID();
		}

		$sub_prod_str = (isset($_REQUEST['subproduct_ids'.$i]) ? $_REQUEST['subproduct_ids'.$i] : '');
		if (!empty($sub_prod_str)) {
			$query ='insert into vtiger_inventorysubproductrel(id, sequence_no, productid) values(?,?,?)';
			foreach (explode(':', $sub_prod_str) as $item) {
				$adb->pquery($query, array($focus->id,$prod_seq,$item));
			}
		}
		$prod_seq++;

		//we should update discount and tax details
		$updatequery = 'update vtiger_inventoryproductrel set ';
		$updateparams = array();

		//set the discount percentage or discount amount in update query, then set the tax values
		if (isset($_REQUEST['discount_type'.$i]) && $_REQUEST['discount_type'.$i] == 'percentage') {
			$updatequery .= ' discount_percent=?,';
			$updateparams[] = (float)$_REQUEST['discount_percentage'.$i];
		} elseif (isset($_REQUEST['discount_type'.$i]) && $_REQUEST['discount_type'.$i] == 'amount') {
			$updatequery .= ' discount_amount=?,';
			$discount_amount = $_REQUEST['discount_amount'.$i];
			$updateparams[] = (float)$discount_amount;
		}
		if ($_REQUEST['taxtype'] == 'group') {
			foreach ($all_available_taxes as $available_tax) {
				$tax_name = $available_tax['taxname'];
				if (!in_array($tax_name, $ipr_cols)) {
					continue;
				}
				$tax_val = $available_tax['percentage'];
				$request_tax_name = $tax_name.'_group_percentage';
				if (isset($_REQUEST[$request_tax_name])) {
					$tax_val =vtlib_purify($_REQUEST[$request_tax_name]);
				}
				$updatequery .= " $tax_name = ?,";
				$updateparams[] = (float)$tax_val;
			}
			$updatequery = trim($updatequery, ',').' where id=? and productid=? and lineitem_id = ?';
			array_push($updateparams, $focus->id, $prod_id, $lineitem_id);
		} else {
			foreach (getTaxDetailsForProduct($prod_id, 'all', $acvid) as $productTax) {
				$tax_name = $productTax['taxname'];
				if (!in_array($tax_name, $ipr_cols)) {
					continue;
				}
				$request_tax_name = $tax_name.'_percentage'.$i;
				$updatequery .= " $tax_name = ?,";
				$updateparams[] = (float)vtlib_purify($_REQUEST[$request_tax_name]);
			}
			$updatequery = trim($updatequery, ',').' where id=? and productid=? and lineitem_id = ?';
			array_push($updateparams, $focus->id, $prod_id, $lineitem_id);
		}
		// jens 2006/08/19 - protect against empy update queries
		if (!preg_match('/set\s+where/i', $updatequery)) {
			$adb->pquery($updatequery, $updateparams);
		}
	}

	//we should update the netprice (subtotal), taxtype, group discount, S&H charge, S&H taxes, adjustment and total
	//netprice, group discount, taxtype, S&H amount, adjustment and total to entity table

	$updatequery  = " update $focus->table_name set ";
	$updateparams = array();
	$subtotal = vtlib_purify($_REQUEST['subtotal']);
	$updatequery .= ' subtotal=?,';
	$updateparams[] = (float)$subtotal;

	$updatequery .= ' taxtype=?,';
	$updateparams[] = vtlib_purify($_REQUEST['taxtype']);

	//for discount percentage or discount amount
	if (isset($_REQUEST['discount_type_final']) && $_REQUEST['discount_type_final'] == 'percentage') {
		$updatequery .= ' discount_percent=?,';
		$updateparams[] = (float)vtlib_purify($_REQUEST['discount_percentage_final']);
	} elseif (isset($_REQUEST['discount_type_final']) && $_REQUEST['discount_type_final'] == 'amount') {
		$discount_amount_final = vtlib_purify($_REQUEST['discount_amount_final']);
		$updatequery .= ' discount_amount=?,';
		$updateparams[] = (float)$discount_amount_final;
	}

	$shipping_handling_charge = (isset($_REQUEST['shipping_handling_charge']) ? vtlib_purify($_REQUEST['shipping_handling_charge']) : 0);
	$updatequery .= ' s_h_amount=?,';
	$updateparams[] = (float)$shipping_handling_charge;

	//if the user gave - sign in adjustment then add with the value
	$adjustmentType = '';
	if (isset($_REQUEST['adjustmentType']) && $_REQUEST['adjustmentType'] == '-') {
		$adjustmentType = vtlib_purify($_REQUEST['adjustmentType']);
	}

	$adjustment = (isset($_REQUEST['adjustment']) ? vtlib_purify($_REQUEST['adjustment']) : 0);
	$updatequery .= ' adjustment=?,';
	$updateparams[] = (float)($adjustmentType.$adjustment);

	$total = vtlib_purify($_REQUEST['total']);
	$updatequery .= ' total=?';
	$updateparams[] = (float)$total;

	//$id_array = Array('PurchaseOrder'=>'purchaseorderid','SalesOrder'=>'salesorderid','Quotes'=>'quoteid','Invoice'=>'invoiceid');
	//Added where condition to which entity we want to update these values
	$updatequery .= ' where '.$focus->table_index.'=?';
	$updateparams[] = $focus->id;

	$adb->pquery($updatequery, $updateparams);

	//to save the S&H tax details in vtiger_inventoryshippingrel table
	$isr_cols = $adb->getColumnNames('vtiger_inventoryshippingrel');
	$sh_query_fields = 'id,';
	$sh_query_values = '?,';
	$sh_query_params = array($focus->id);
	foreach (getAllTaxes('all', 'sh') as $taxDetail) {
		$tax_name = $taxDetail['taxname'].'_sh_percent';
		if (isset($_REQUEST[$tax_name]) && $_REQUEST[$tax_name] != '' && in_array($taxDetail['taxname'], $isr_cols)) {
			$sh_query_fields .= $taxDetail['taxname'].',';
			$sh_query_values .= '?,';
			$sh_query_params[] = (float)vtlib_purify($_REQUEST[$tax_name]);
		}
	}
	$sh_query_fields = trim($sh_query_fields, ',');
	$sh_query_values = trim($sh_query_values, ',');

	if ($sh_query_fields!='id') {
		$sh_query = "insert into vtiger_inventoryshippingrel($sh_query_fields) values($sh_query_values)";
		$adb->pquery($sh_query, $sh_query_params);
	}
	$log->debug('< saveInventoryProductDetails');
}

/**	function used to get the tax type for the Inventory module entity
 *	@param string $module - module name
 *	@param integer $id - id of the Inventory module
 *	@return string $taxtype - taxtype for the given entity which will be individual or group
 */
function getInventoryTaxType($module, $id) {
	global $log, $adb;
	$log->debug("> getInventoryTaxType $module, $id");
	$modent = CRMEntity::getInstance($module);
	$table = $modent->table_name;
	$field = $modent->table_index;
	$res = $adb->pquery("select taxtype from $table where $field=?", array($id));
	$log->debug('< getInventoryTaxType');
	return (empty($res->fields['taxtype']) ? '' : $res->fields['taxtype']);
}

/**	function used to get the price type for the entity (PO, SO, Quotes or Invoice)
 *	@param string $module - module name
 *	@param integer $id - id of the PO or SO or Quotes or Invoice
 *	@return string $pricetype - pricetype for the given entity which will be unitprice or secondprice
 */
function getInventoryCurrencyInfo($module, $id) {
	global $log;
	$log->debug(">< getInventoryCurrencyInfo $module, $id");
	return CurrencyField::getMultiCurrencyInfoFrom($module, $id);
}

/**	function used to get the taxvalue which is associated with a product for PO/SO/Quotes or Invoice
 *	@param integer $id - id of PO/SO/Quotes or Invoice
 *	@param integer $productid - product id
 *	@param string $taxname - taxname to which we want the value
 *	@return float $taxvalue - tax value
 */
function getInventoryProductTaxValue($id, $productid, $taxname) {
	global $log, $adb;
	$log->debug("> getInventoryProductTaxValue $id, $productid, $taxname");
	list($void1,$void2,$void3,$taxvalue) = cbEventHandler::do_filter('corebos.filter.TaxCalculation.getInventoryProductTaxValue', array($id, $productid, $taxname, ''));
	if ($taxvalue == '') {
		$res = $adb->pquery("select $taxname from vtiger_inventoryproductrel where id = ? and productid = ?", array($id, $productid));
		$taxvalue = $adb->query_result($res, 0, $taxname);
	}
	if ($taxvalue == '') {
		$taxvalue = '0.00';
	}

	$log->debug('< getInventoryProductTaxValue');
	return $taxvalue;
}

/**	function used to get the shipping & handling tax percentage for the given inventory id and taxname
 *	@param integer $id - entity id which will be PO/SO/Quotes or Invoice id
 *	@param string $taxname - shipping and handling taxname
 *	@return float $taxpercentage - shipping and handling taxpercentage which is associated with the given entity
 */
function getInventorySHTaxPercent($id, $taxname) {
	global $log, $adb;
	$log->debug("> getInventorySHTaxPercent $id, $taxname");
	list($void1,$void2,$taxpercentage) = cbEventHandler::do_filter('corebos.filter.TaxCalculation.getInventorySHTaxPercent', array($id, $taxname, ''));
	if ($taxpercentage == '') {
		$res = $adb->pquery("select $taxname from vtiger_inventoryshippingrel where id= ?", array($id));
		$taxpercentage = $adb->query_result($res, 0, $taxname);
	}
	if ($taxpercentage == '') {
		$taxpercentage = '0.00';
	}

	$log->debug('< getInventorySHTaxPercent');
	return $taxpercentage;
}

/**	Function used to get the list of all Currencies as a array
 *  @param string available - if 'all' returns all the currencies, default value 'available' returns only the currencies which are available for use.
 *	return array $currency_details - return details of all the currencies as a array
 */
function getAllCurrencies($available = 'available') {
	global $adb, $log;
	$log->debug('> getAllCurrencies '.$available);

	$sql = 'select * from vtiger_currency_info';
	if ($available != 'all') {
		$sql .= " where currency_status='Active' and deleted=0";
	}
	$res=$adb->pquery($sql, array());
	$noofrows = $adb->num_rows($res);

	for ($i=0; $i<$noofrows; $i++) {
		$currency_details[$i]['currencylabel'] = $adb->query_result($res, $i, 'currency_name');
		$currency_details[$i]['currencycode'] = $adb->query_result($res, $i, 'currency_code');
		$currency_details[$i]['currencysymbol'] = $adb->query_result($res, $i, 'currency_symbol');
		$currency_details[$i]['curid'] = $adb->query_result($res, $i, 'id');
		/* alias key added to be consistent with result of InventoryUtils::getInventoryCurrencyInfo */
		$currency_details[$i]['currency_id'] = $adb->query_result($res, $i, 'id');
		$currency_details[$i]['conversionrate'] = $adb->query_result($res, $i, 'conversion_rate');
		$currency_details[$i]['curname'] = 'curname' . $adb->query_result($res, $i, 'id');
	}

	$log->debug('< getAllCurrencies');
	return $currency_details;
}

/**	Function used to get all the price details for different currencies which are associated to the given product
 *	@param integer $productid - product id to which we want to get all the associated prices
 *  @param float $unit_price - Unit price of the product
 *  @param string $available - available or available_associated where as default is available,
 *  	if available then the prices in the currencies which are available now will be returned,
 *  	otherwise if the value is available_associated then prices of all the associated currencies will be returned
 *	@return array $price_details - price details as a array with productid, curid, curname
 */
function getPriceDetailsForProduct($productid, $unit_price, $available = 'available', $itemtype = 'Products') {
	global $log, $adb;
	$log->debug('> getPriceDetailsForProduct '.$productid);
	$price_details = array();
	if ($productid != '') {
		$product_currency_id = getProductBaseCurrency($productid, $itemtype);
		$product_base_conv_rate = getBaseConversionRateForProduct($productid, 'edit', $itemtype);
		// Detail View
		if ($available == 'available_associated') {
			$query = "select vtiger_currency_info.*, vtiger_productcurrencyrel.actual_price
					from vtiger_currency_info
					inner join vtiger_productcurrencyrel on vtiger_currency_info.id = vtiger_productcurrencyrel.currencyid
					where vtiger_currency_info.currency_status = 'Active' and vtiger_currency_info.deleted=0
					and vtiger_productcurrencyrel.productid = ? and vtiger_currency_info.id != ?";
			$params = array($productid, $product_currency_id);
		} else { // Edit View
			$query = "select vtiger_currency_info.*, vtiger_productcurrencyrel.actual_price
					from vtiger_currency_info
					left join vtiger_productcurrencyrel
					on vtiger_currency_info.id = vtiger_productcurrencyrel.currencyid and vtiger_productcurrencyrel.productid = ?
					where vtiger_currency_info.currency_status = 'Active' and vtiger_currency_info.deleted=0";
			$params = array($productid);
		}

		$res = $adb->pquery($query, $params);
		for ($i=0; $i<$adb->num_rows($res); $i++) {
			$price_details[$i]['productid'] = $productid;
			$price_details[$i]['currencylabel'] = $adb->query_result($res, $i, 'currency_name');
			$price_details[$i]['currencycode'] = $adb->query_result($res, $i, 'currency_code');
			$price_details[$i]['currencysymbol'] = $adb->query_result($res, $i, 'currency_symbol');
			$currency_id = $adb->query_result($res, $i, 'id');
			$price_details[$i]['curid'] = $currency_id;
			$price_details[$i]['curname'] = 'curname' . $adb->query_result($res, $i, 'id');
			$cur_value = $adb->query_result($res, $i, 'actual_price');

			// Get the conversion rate for the given currency, get the conversion rate of the product currency to base currency.
			// Both together will be the actual conversion rate for the given currency.
			$conversion_rate = $adb->query_result($res, $i, 'conversion_rate');
			$actual_conversion_rate = $product_base_conv_rate * $conversion_rate;

			$is_basecurrency = false;
			if ($currency_id == $product_currency_id) {
				$is_basecurrency = true;
			}
			$price_details[$i]['check_value'] = true;
			if ($cur_value == null || $cur_value == '') {
				$price_details[$i]['check_value'] = false;
				if ($unit_price != null) {
					$cur_value = CurrencyField::convertFromMasterCurrency($unit_price, $actual_conversion_rate);
				} else {
					$cur_value = '0';
				}
			} elseif ($is_basecurrency) {
				$price_details[$i]['check_value'] = true;
			}
			$price_details[$i]['curvalue'] = CurrencyField::convertToUserFormat($cur_value, null, true);
			$price_details[$i]['conversionrate'] = $actual_conversion_rate;
			$price_details[$i]['is_basecurrency'] = $is_basecurrency;
		}
	} else {
		if ($available == 'available') { // Create View
			global $current_user;

			$user_currency_id = fetchCurrency($current_user->id);

			$query = "select vtiger_currency_info.* from vtiger_currency_info
					where vtiger_currency_info.currency_status = 'Active' and vtiger_currency_info.deleted=0";
			$params = array();

			$res = $adb->pquery($query, $params);
			for ($i=0; $i<$adb->num_rows($res); $i++) {
				$price_details[$i]['currencylabel'] = $adb->query_result($res, $i, 'currency_name');
				$price_details[$i]['currencycode'] = $adb->query_result($res, $i, 'currency_code');
				$price_details[$i]['currencysymbol'] = $adb->query_result($res, $i, 'currency_symbol');
				$currency_id = $adb->query_result($res, $i, 'id');
				$price_details[$i]['curid'] = $currency_id;
				$price_details[$i]['curname'] = 'curname' . $adb->query_result($res, $i, 'id');

				// Get the conversion rate for the given currency, get the conversion rate of the product currency(logged in user's currency) to base currency.
				// Both together will be the actual conversion rate for the given currency.
				$conversion_rate = $adb->query_result($res, $i, 'conversion_rate');
				$user_cursym_convrate = getCurrencySymbolandCRate($user_currency_id);
				$product_base_conv_rate = 1 / $user_cursym_convrate['rate'];
				$actual_conversion_rate = $product_base_conv_rate * $conversion_rate;

				$price_details[$i]['check_value'] = false;
				$price_details[$i]['curvalue'] = '0';
				$price_details[$i]['conversionrate'] = $actual_conversion_rate;

				$is_basecurrency = false;
				if ($currency_id == $user_currency_id) {
					$is_basecurrency = true;
				}
				$price_details[$i]['is_basecurrency'] = $is_basecurrency;
			}
		} else {
			$log->debug('Product id is empty. we cannot retrieve the associated prices.');
		}
	}

	$log->debug('< getPriceDetailsForProduct');
	return $price_details;
}

/**	Function used to get the base currency used for the given Product
 *	@param integer $productid - product id for which we want to get the id of the base currency
 *  @return integer $currencyid - id of the base currency for the given product
 */
function getProductBaseCurrency($productid, $module = 'Products') {
	global $adb;
	if ($module == 'Services') {
		$sql = 'select currency_id from vtiger_service where serviceid=?';
	} else {
		$sql = 'select currency_id from vtiger_products where productid=?';
	}
	$res = $adb->pquery($sql, array($productid));
	return $adb->query_result($res, 0, 'currency_id');
}

/**	Function used to get the conversion rate for the product base currency with respect to the CRM base currency
 *	@param integer $productid - product id for which we want to get the conversion rate of the base currency
 *  @param string $mode - Mode in which the function is called
 *  @return float $conversion_rate - conversion rate of the base currency for the given product based on the CRM base currency
 */
function getBaseConversionRateForProduct($productid, $mode = 'edit', $module = 'Products') {
	global $adb, $current_user;

	if ($mode == 'edit') {
		if ($module == 'Services') {
			$sql = 'select conversion_rate
				from vtiger_service
				inner join vtiger_currency_info on vtiger_service.currency_id = vtiger_currency_info.id
				where vtiger_service.serviceid=?';
		} else {
			$sql = 'select conversion_rate
				from vtiger_products
				inner join vtiger_currency_info on vtiger_products.currency_id = vtiger_currency_info.id
				where vtiger_products.productid=?';
		}
		$params = array($productid);
	} else {
		$sql = 'select conversion_rate from vtiger_currency_info where id=?';
		$params = array(fetchCurrency($current_user->id));
	}

	$res = $adb->pquery($sql, $params);
	$conv_rate = $adb->query_result($res, 0, 'conversion_rate');

	return 1 / (empty($conv_rate) ? 1 : $conv_rate);
}

/**	Function used to get the prices for the given list of products based in the specified currency
 *	@param integer $currencyid - currency id based on which the prices have to be provided
 *	@param array $product_ids - List of product id's for which we want to get the price based on given currency
 *  @return array $prices_list - List of prices for the given list of products based on the given currency in the form of 'product id' mapped to 'price value'
 */
function getPricesForProducts($currencyid, $product_ids, $module = 'Products', $unitprice = true) {
	global $adb, $current_user;
	$price_list = array();
	$product_ids = (array)$product_ids;
	if (count($product_ids) > 0) {
		if ($module == 'Services') {
			$query = 'SELECT vtiger_currency_info.id, vtiger_currency_info.conversion_rate, vtiger_service.serviceid AS productid, vtiger_service.unit_price,
					vtiger_productcurrencyrel.actual_price, vtiger_service.cost_price
				FROM (vtiger_currency_info, vtiger_service)
				LEFT JOIN vtiger_productcurrencyrel ON vtiger_service.serviceid=vtiger_productcurrencyrel.productid and vtiger_currency_info.id=vtiger_productcurrencyrel.currencyid
				WHERE vtiger_service.serviceid in ('. generateQuestionMarks($product_ids) .') and vtiger_currency_info.id=?';
		} else {
			$query = 'SELECT vtiger_currency_info.id, vtiger_currency_info.conversion_rate, vtiger_products.productid, vtiger_products.unit_price,
					vtiger_productcurrencyrel.actual_price, vtiger_products.cost_price
				FROM (vtiger_currency_info, vtiger_products)
				LEFT JOIN vtiger_productcurrencyrel ON vtiger_products.productid=vtiger_productcurrencyrel.productid and vtiger_currency_info.id=vtiger_productcurrencyrel.currencyid
				WHERE vtiger_products.productid in ('. generateQuestionMarks($product_ids) .') and vtiger_currency_info.id=?';
		}
		$params = array($product_ids, $currencyid);
		$result = $adb->pquery($query, $params);
		$uppermission = getFieldVisibilityPermission($module, $current_user->id, 'unit_price');
		$cppermission = getFieldVisibilityPermission($module, $current_user->id, 'cost_price');
		for ($i=0; $i<$adb->num_rows($result); $i++) {
			$product_id = $adb->query_result($result, $i, 'productid');
			if (($unitprice && $uppermission == '0') || (!$unitprice && $cppermission == '0')) {
				$actual_price = (float)$adb->query_result($result, $i, 'actual_price');

				if ($actual_price == null || $actual_price == '') {
					$unit_price = $adb->query_result($result, $i, ($unitprice ? 'unit_price' : 'cost_price'));
					$product_conv_rate = $adb->query_result($result, $i, 'conversion_rate');
					$product_base_conv_rate = getBaseConversionRateForProduct($product_id, 'edit', $module);
					$conversion_rate = $product_conv_rate * $product_base_conv_rate;

					$actual_price = (float)$unit_price * (float)$conversion_rate;
				}
				$price_list[$product_id] = $actual_price;
			} else {
				$price_list[$product_id] = '';
			}
		}
	}
	return $price_list;
}

/**	Function used to get the currency used for the given Price book
 *	@param integer $pricebook_id - pricebook id for which we want to get the id of the currency used
 *  @return integer $currencyid - id of the currency used for the given pricebook
 */
function getPriceBookCurrency($pricebook_id) {
	global $adb;
	$result = $adb->pquery('select currency_id from vtiger_pricebook where pricebookid=?', array($pricebook_id));
	return $adb->query_result($result, 0, 'currency_id');
}

// deduct products from stock - if status will be changed from cancel to other status.
function deductProductsFromStock($recordId) {
	global $adb;
	$adb->pquery('UPDATE vtiger_inventoryproductrel SET incrementondel=1 WHERE id=?', array($recordId));
	updateProductStockFromDatabase($recordId, false);
}

// Add Products to stock - status changed to cancel or delete the invoice
function addProductsToStock($recordId) {
	updateProductStockFromDatabase($recordId, true);
}

// Update Product stock from database
function updateProductStockFromDatabase($recordId, $add = true) {
	global $adb;
	$pdosql = 'SELECT vtiger_inventoryproductrel.productid,sequence_no, quantity
		from vtiger_inventoryproductrel
		inner join vtiger_products on vtiger_products.productid = vtiger_inventoryproductrel.productid
		WHERE id=?';
	$subpdosql = 'SELECT vtiger_inventorysubproductrel.productid
		from vtiger_inventorysubproductrel
		inner join vtiger_products on vtiger_products.productid = vtiger_inventorysubproductrel.productid
		WHERE id=? AND sequence_no=?';
	$product_info = $adb->pquery($pdosql, array($recordId));
	$numrows = $adb->num_rows($product_info);
	for ($index = 0; $index <$numrows; $index++) {
		$productid = $adb->query_result($product_info, $index, 'productid');
		$qty = $adb->query_result($product_info, $index, 'quantity');
		$sequence_no = $adb->query_result($product_info, $index, 'sequence_no');
		$qtyinstk= getPrdQtyInStck($productid);
		if ($add) {
			$upd_qty = $qtyinstk+$qty;
		} else {
			$upd_qty = $qtyinstk-$qty;
		}
		updateProductQty($productid, $upd_qty);
		$sub_prod_query = $adb->pquery($subpdosql, array($recordId,$sequence_no));
		if ($adb->num_rows($sub_prod_query)>0) {
			for ($j=0; $j<$adb->num_rows($sub_prod_query); $j++) {
				$sub_prod_id = $adb->query_result($sub_prod_query, $j, 'productid');
				$sqtyinstk= getPrdQtyInStck($sub_prod_id);
				if ($add) {
					$supd_qty = $sqtyinstk+$qty;
				} else {
					$supd_qty = $sqtyinstk-$qty;
				}
				updateProductQty($sub_prod_id, $supd_qty);
			}
		}
	}
}

function createRecords($obj) {
	global $adb, $VTIGER_BULK_SAVE_MODE;
	$previousBulkSaveMode = isset($VTIGER_BULK_SAVE_MODE) ? $VTIGER_BULK_SAVE_MODE : false;
	$moduleName = $obj->module;

	$moduleHandler = vtws_getModuleHandlerFromName($moduleName, $obj->user);
	$moduleMeta = $moduleHandler->getMeta();
	$moduleFields = $moduleMeta->getModuleFields();
	include_once 'include/fields/InventoryLineField.php';
	$ilfields = new InventoryLineField();
	$moduleFields = array_merge($moduleFields, $ilfields->getInventoryLineFieldsByObject());
	$focus = CRMEntity::getInstance($moduleName);
	$pdowsid = vtws_getEntityId('Products').'x';
	$srvwsid = vtws_getEntityId('Services').'x';
	$usrwsid = vtws_getEntityId('Users').'x';

	$tableName = Import_Utils::getDbTableName($obj->user);
	$sql = 'SELECT subject FROM ' . $tableName . ' WHERE status = '. Import_Data_Controller::$IMPORT_RECORD_NONE .' GROUP BY subject';
	if ($obj->batchImport) {
		$importBatchLimit = GlobalVariable::getVariable('Import_Batch_Limit', 100);
		if (!is_numeric($importBatchLimit)) {
			$importBatchLimit = 100;
		}
		$sql .= ' LIMIT '. $importBatchLimit;
	}
	$result = $adb->query($sql);
	$numberOfRecords = $adb->num_rows($result);

	if ($numberOfRecords <= 0) {
		return false;
	}

	$fieldMapping = $obj->fieldMapping;

	for ($i = 0; $i < $numberOfRecords; ++$i) {
		$row = $adb->raw_query_result_rowdata($result, $i);
		$entityInfo = null;
		$fieldData = array();
		$lineItems = array();
		$subjectResult = $adb->pquery('SELECT * FROM '.$tableName.' WHERE status='.Import_Data_Controller::$IMPORT_RECORD_NONE.' AND subject=?', array($row['subject']));
		$count = $adb->num_rows($subjectResult);
		$subjectRowIDs = array();
		for ($j = 0; $j < $count; ++$j) {
			$subjectRow = $adb->raw_query_result_rowdata($subjectResult, $j);
			$subjectRowIDs[] = isset($subjectRow['id']) ? $subjectRow['id'] : 0;
			if ($subjectRow['productid'] == '' || $subjectRow['quantity'] == '' || $subjectRow['listprice'] == '') {
				continue;
			}
			$lineItemData = array();
			$lineItemData['discount'] = 0;
			foreach ($fieldMapping as $fieldName => $index) {
				if (!empty($moduleFields[$fieldName]) && $moduleFields[$fieldName]->getTableName() == 'vtiger_inventoryproductrel') {
					if ($fieldName=='productid') {
						$fieldValue = $subjectRow[$fieldName];
						if (strpos($fieldValue, '::::') > 0) {
							$fieldValueDetails = explode('::::', $fieldValue);
						} elseif (strpos($fieldValue, ':::') > 0) {
							$fieldValueDetails = explode(':::', $fieldValue);
						} else {
							$fieldValueDetails = $fieldValue;
						}
						if (count($fieldValueDetails) > 1) {
							$referenceModuleName = trim($fieldValueDetails[0]);
							$entityLabel = trim($fieldValueDetails[1]);
							if (!empty($fieldValueDetails[2])) {
								$entityId = getEntityId($referenceModuleName, $entityLabel, $fieldValueDetails[2]);
							} else {
								$entityId = getEntityId($referenceModuleName, $entityLabel);
							}
						} else {
							$referencedModules = array('Products','Services');
							$entityLabel = $fieldValue;
							foreach ($referencedModules as $referenceModule) {
								$referenceModuleName = $referenceModule;
								$referenceEntityId = getEntityId($referenceModule, $entityLabel);
								if ($referenceEntityId != 0) {
									$entityId = $referenceEntityId;
									break;
								}
							}
						}
						if (empty($entityId) && in_array($referenceModuleName, array('Products','Services'))) {
							$pdoinfo = array();
							if ($referenceModuleName == 'Services') {
								$pdoinfo['servicename'] = $entityLabel;
							} else {
								$pdoinfo['productname'] = $entityLabel;
							}
							$pdoinfo['assigned_user_id'] = $usrwsid.$obj->user->id;
							try {
								$VTIGER_BULK_SAVE_MODE = false;
								$psInfo = vtws_create($referenceModuleName, $pdoinfo, $obj->user);
								$VTIGER_BULK_SAVE_MODE = $previousBulkSaveMode;
								$entityId = $psInfo['id'];
							} catch (Exception $e) {
								continue 2; // ignore this line
							}
						} else {
							if ($referenceModuleName == 'Services') {
								$entityId = $srvwsid.$entityId;
							} else {
								$entityId = $pdowsid.$entityId;
							}
						}
						$lineItemData[$fieldName] = $entityId;
					} else {
						$lineItemData[$fieldName] = $subjectRow[$fieldName];
						if ($fieldName=='discount_amount' && $subjectRow[$fieldName]>0) {
							$lineItemData['discount'] = 1;
							$lineItemData['discount_type'] = 'amount';
						}
						if ($fieldName=='discount_percent' && $subjectRow[$fieldName]>0) {
							$lineItemData['discount'] = 1;
							$lineItemData['discount_percentage'] = $subjectRow[$fieldName];
							$lineItemData['discount_type'] = 'percentage';
						}
						if ($fieldName=='quantity') {
							$lineItemData['qty'] = $subjectRow[$fieldName];
						}
					}
				}
			}
			$lineItems[] = $lineItemData;
		}
		foreach ($fieldMapping as $fieldName => $index) {
			$fieldData[$fieldName] = $subjectRow[strtolower($fieldName)];
			if ($fieldName=='hdnTaxType') {
				$fieldData['taxtype'] = $subjectRow[strtolower($fieldName)];
			}
			if ($fieldName=='txtAdjustment' && $fieldData[$fieldName]!=0) {
				$fieldData['adjustmentType'] = 'add';
				$fieldData['adjustment'] = $fieldData[$fieldName];
			}
		}
		if (!array_key_exists('assigned_user_id', $fieldData)) {
			$fieldData['assigned_user_id'] = $obj->user->id;
		}

		if (method_exists($focus, 'importRecord')) {
			$entityInfo = $focus->importRecord($obj, $fieldData, $lineItems);
		}

		if ($entityInfo == null) {
			$entityInfo = array('id' => null, 'status' => $obj->getImportRecordStatus('failed'));
		}
		foreach ($subjectRowIDs as $id) {
			$obj->importedRecordInfo[$id] = $entityInfo;
			$obj->updateImportStatus($id, $entityInfo);
		}
	}
	unset($result);
	return true;
}

function importRecord($obj, $inventoryFieldData, $lineItems) {
	global $adb;
	$moduleName = $obj->module;
	$inventoryHandler = vtws_getModuleHandlerFromName($moduleName, $obj->user);
	$inventoryMeta = $inventoryHandler->getMeta();
	$currency = '';
	if (isset($inventoryFieldData['currency_id'])) {
		$currency = $inventoryFieldData['currency_id'];
		unset($inventoryFieldData['currency_id']);
	}
	$fieldData = $obj->transformForImport($inventoryFieldData, $inventoryMeta);
	if (empty($fieldData)) {
		return null;
	}
	$fieldData['pdoInformation'] = $lineItems;
	$wsrs=$adb->pquery('select id from vtiger_ws_entity where name=?', array('Currency'));
	if ($wsrs && $adb->num_rows($wsrs)==1) {
		$wsid = $adb->query_result($wsrs, 0, 0);
	} else {
		$wsid = 0;
	}
	if ($currency == ' ' || empty($currency)) {
		$currid = 1;
	} else {
		$currid = getCurrencyId($currency);
	}
	$cur_sym_rate = getCurrencySymbolandCRate($currid);
	$fieldData['currency_id'] = $wsid.'x'.$currid;
	$fieldData['conversion_rate'] = $cur_sym_rate['rate'];
	$entityInfo = vtws_create($moduleName, $fieldData, $obj->user);
	$entityInfo['status'] = $obj->getImportRecordStatus('created');
	return $entityInfo;
}

function getImportStatusCount($obj) {
	global $adb;
	$tableName = Import_Utils::getDbTableName($obj->user);
	$result = $adb->query('SELECT status FROM '.$tableName. ' GROUP BY subject,status');

	$statusCount = array('TOTAL' => 0, 'IMPORTED' => 0, 'FAILED' => 0, 'PENDING' => 0, 'CREATED' => 0, 'SKIPPED' => 0, 'UPDATED' => 0, 'MERGED' => 0);

	if ($result) {
		$noOfRows = $adb->num_rows($result);
		$statusCount['TOTAL'] = $noOfRows;
		for ($i=0; $i<$noOfRows; ++$i) {
			$status = $adb->query_result($result, $i, 'status');
			if ($obj->getImportRecordStatus('none') == $status) {
				$statusCount['PENDING']++;
			} elseif ($obj->getImportRecordStatus('failed') == $status) {
				$statusCount['FAILED']++;
			} else {
				$statusCount['IMPORTED']++;
				switch ($status) {
					case $obj->getImportRecordStatus('created'):
						$statusCount['CREATED']++;
						break;
					case $obj->getImportRecordStatus('skipped'):
						$statusCount['SKIPPED']++;
						break;
					case $obj->getImportRecordStatus('updated'):
						$statusCount['UPDATED']++;
						break;
					case $obj->getImportRecordStatus('merged'):
						$statusCount['MERGED']++;
						break;
				}
			}
		}
	}
	return $statusCount;
}

function undoLastImport($obj, $user) {
	global $adb;
	$moduleName = $obj->get('module');
	$ownerId = $obj->get('foruser');
	$owner = new Users();
	$owner->id = $ownerId;
	$owner->retrieve_entity_info($ownerId, 'Users');
	$dbTableName = Import_Utils::getDbTableName($owner);
	$viewer = new vtigerCRM_Smarty();
	if (!is_admin($user) && $user->id != $owner->id) {
		$viewer->display(vtlib_getModuleTemplate('Vtiger', 'OperationNotPermitted.tpl'));
		exit;
	}
	$result = $adb->query(
		"SELECT recordid
			FROM $dbTableName
			WHERE status = ". Import_Data_Controller::$IMPORT_RECORD_CREATED. ' AND recordid IS NOT NULL GROUP BY subject,recordid'
	);
	$noOfRecords = $adb->num_rows($result);
	$noOfRecordsDeleted = 0;
	for ($i=0; $i<$noOfRecords; ++$i) {
		$recordId = $adb->query_result($result, $i, 'recordid');
		if (isRecordExists($recordId) && isPermitted($moduleName, 'Delete', $recordId) == 'yes') {
			$focus = CRMEntity::getInstance($moduleName);
			$focus->id = $recordId;
			$focus->trash($moduleName, $recordId);
			$noOfRecordsDeleted++;
		}
	}
	$viewer->assign('FOR_MODULE', $moduleName);
	$viewer->assign('TOTAL_RECORDS', $noOfRecords);
	$viewer->assign('DELETED_RECORDS_COUNT', $noOfRecordsDeleted);
	$viewer->view(vtlib_getModuleTemplate('Import', 'ImportUndoResult.tpl'));
}

function getInventoryFieldsForExport($tableName) {
	$sql = ','.$tableName.'.adjustment AS "Adjustment", '.$tableName.'.total AS "Total", '.$tableName.'.subtotal AS "Sub Total", ';
	$sql .= $tableName.'.taxtype AS "Tax Type", '.$tableName.'.discount_amount AS "Discount Amount", ';
	$sql .= $tableName.'.discount_percent AS "Discount Percent", '.$tableName.'.s_h_amount AS "S&H Amount", ';
	$sql .= 'vtiger_currency_info.currency_name as "Currency",';
	include_once 'include/fields/InventoryLineField.php';
	$ilfields = new InventoryLineField();
	$inventory_fields = $ilfields->getInventoryLineFieldsByLabel();
	foreach ($inventory_fields as $fdesc) {
		$sql .= $fdesc['tablename'].'.'.$fdesc['columnname'].' AS "'.$fdesc['fieldlabel'].'",';
	}
	$sql = rtrim($sql, ',').' ';
	return $sql;
}

function getCurrencyId($fieldValue) {
	global $adb;
	$sql = 'SELECT id FROM vtiger_currency_info WHERE currency_name = ? AND deleted = 0';
	$result = $adb->pquery($sql, array($fieldValue));
	$currencyId = 1;
	if ($adb->num_rows($result) > 0) {
		$currencyId = $adb->query_result($result, 0, 'id');
	}
	return $currencyId;
}

function isFrontendEditViewAction($request, $module) {
	global $log;
	$return = ((empty($request['action']) || ($request['action'] != $module.'Ajax' && $request['action'] != 'MassEditSave' && $request['action'] != 'ProcessDuplicates'))
		&& (empty($request['ajxaction']) || ($request['ajxaction'] != 'DETAILVIEW' && $request['ajxaction'] != 'Workflow')));
	$log->debug('>< isFrontendEditViewAction '.($return ? 'true':'false'));
	return $return;
}

function inventoryCanSaveProductLines($request, $module) {
	global $log;
	$return = ((empty($request['action']) || ($request['action'] != $module.'Ajax' && $request['action'] != 'MassEditSave' && $request['action'] != 'ProcessDuplicates'))
		&& (empty($request['ajxaction']) || ($request['ajxaction'] != 'DETAILVIEW' && $request['ajxaction'] != 'Workflow'))
		&& (isset($request['totalProductCount']) && (int)$request['totalProductCount'] > 0));
	$log->debug('>< inventoryCanSaveProductLines '.($return ? 'true':'false'));
	return $return;
}
?>