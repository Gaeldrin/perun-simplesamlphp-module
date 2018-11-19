<?php
const CONFIG_FILE_NAME = 'module_perun_listOfSps.php';
const PROXY_IDENTIFIER = 'proxyIdentifier';
const ATTRIBUTES_DEFINITIONS = 'attributesDefinitions';
const SHOW_OIDC_SERVICES = 'showOIDCServices';

const PERUN_PROXY_IDENTIFIER_ATTR_NAME = 'perunProxyIdentifierAttr';
const PERUN_LOGIN_URL_ATTR_NAME = 'loginURLAttr';
const PERUN_TEST_SP_ATTR_NAME = 'isTestSpAttr';
const PERUN_SHOW_ON_SERVICE_LIST_ATTR_NAME = 'showOnServiceListAttr';
const PERUN_SAML2_ENTITY_ID_ATTR_NAME = 'SAML2EntityIdAttr';
const PERUN_OIDC_CLIENT_ID_ATTR_NAME = 'OIDCClientIdAttr';

$config = SimpleSAML_Configuration::getInstance();
$conf = SimpleSAML_Configuration::getConfig(CONFIG_FILE_NAME);

$proxyIdentifier = $conf->getString(PROXY_IDENTIFIER);
if (is_null($proxyIdentifier) || empty($proxyIdentifier)) {
	throw new SimpleSAML_Error_Exception("perun:listOfSps: missing mandatory config option '". PROXY_IDENTIFIER ."'.");
}

$perunProxyIdentifierAttr = $conf->getString(PERUN_PROXY_IDENTIFIER_ATTR_NAME);
if (is_null($perunProxyIdentifierAttr) || empty($perunProxyIdentifierAttr)) {
	throw new SimpleSAML_Error_Exception("perun:listOfSps: missing mandatory config option '". PERUN_PROXY_IDENTIFIER_ATTR_NAME ."'.");
}

$attributesDefinitions = $conf->getArray(ATTRIBUTES_DEFINITIONS);
if (is_null($attributesDefinitions) || empty($attributesDefinitions)) {
	throw new SimpleSAML_Error_Exception("perun:listOfSps: missing mandatory config option '". ATTRIBUTES_DEFINITIONS ."'.");
}

$showOIDCServices = $conf->getBoolean(SHOW_OIDC_SERVICES, false);
$perunSaml2EntityIdAttr =$conf->getString(PERUN_SAML2_ENTITY_ID_ATTR_NAME);
if (is_null($perunSaml2EntityIdAttr) || empty($perunSaml2EntityIdAttr)) {
	throw new SimpleSAML_Error_Exception("perun:listOfSps: missing mandatory config option '". PERUN_SAML2_ENTITY_ID_ATTR_NAME ."'.");
}

$perunOidcClientIdAttr =$conf->getString(PERUN_OIDC_CLIENT_ID_ATTR_NAME);
if ($showOIDCServices && (is_null($perunOidcClientIdAttr) || empty($perunOidcClientIdAttr))) {
	throw new SimpleSAML_Error_Exception("perun:listOfSps: missing mandatory config option '". PERUN_OIDC_CLIENT_ID_ATTR_NAME ."'.");
}

$perunLoginURLAttr = $conf->getString(PERUN_LOGIN_URL_ATTR_NAME, null);
$perunTestSpAttr = $conf->getString(PERUN_TEST_SP_ATTR_NAME, null);
$perunShowOnServiceListAttr = $conf->getString(PERUN_SHOW_ON_SERVICE_LIST_ATTR_NAME, null);

$rpcAdapter = new sspmod_perun_AdapterRpc();
$attributeDefinition = array();
$attributeDefinition[$perunProxyIdentifierAttr] = $proxyIdentifier;
$facilities = $rpcAdapter->searchFacilitiesByAttributeValue($attributeDefinition);

$attrNames = array();

array_push($attrNames, $perunSaml2EntityIdAttr);
if (!is_null($perunOidcClientIdAttr) && !empty($perunOidcClientIdAttr)) {
	array_push($attrNames, $perunOidcClientIdAttr);
}
if (!is_null($perunLoginURLAttr) && !empty($perunLoginURLAttr)) {
	array_push($attrNames, $perunLoginURLAttr);
}
if (!is_null($perunTestSpAttr) && !empty($perunTestSpAttr)) {
	array_push($attrNames, $perunTestSpAttr);
}
if (!is_null($perunShowOnServiceListAttr) && !empty($perunShowOnServiceListAttr)) {
	array_push($attrNames, $perunShowOnServiceListAttr);
}
foreach ($attributesDefinitions as $attributeDefinition) {
	array_push($attrNames, $attributeDefinition);
}

$samlServices = array();
$oidcServices = array();
$samlTestServicesCount = 0;
$oidcTestServicesCount = 0;
foreach ($facilities as $facility) {
	$attributes = $rpcAdapter->getFacilityAttributes($facility, $attrNames);

	$facilityAttributes = array();
	foreach ($attributes as $attribute) {
		$facilityAttributes[$attribute['name']] = $attribute;
	}
	if (!is_null($facilityAttributes[$perunSaml2EntityIdAttr]['value']) && !empty($facilityAttributes[$perunSaml2EntityIdAttr]['value'])) {
		$samlServices[$facility->getId()] = array(
			'facility' => $facility,
			'loginURL' => $facilityAttributes[$perunLoginURLAttr],
			'showOnServiceList' => $facilityAttributes[$perunShowOnServiceListAttr],
			'facilityAttributes' => $facilityAttributes
		);
		if ($facilityAttributes[$perunTestSpAttr]['value']) {
			$samlTestServicesCount++;
		}
	}

	if ($showOIDCServices && (!is_null($facilityAttributes[$perunOidcClientIdAttr]['value']) && !empty($facilityAttributes[$perunOidcClientIdAttr]['value']))) {
		$oidcServices[$facility->getId()] = array(
			'facility' => $facility,
			'loginURL' => $facilityAttributes[$perunLoginURLAttr],
			'showOnServiceList' => $facilityAttributes[$perunShowOnServiceListAttr],
			'facilityAttributes' => $facilityAttributes
		);
		if ($facilityAttributes[$perunTestSpAttr]['value']) {
			$oidcTestServicesCount++;
		}
	}
}

$statistics = array();
$statistics['samlServicesCount'] = sizeof($samlServices);
$statistics['samlTestServicesCount'] = $samlTestServicesCount;
$statistics['oidcServicesCount'] = sizeof($oidcServices);
$statistics['oidcTestServicesCount'] = $oidcTestServicesCount;

$attributesToShow = array();
foreach ($attrNames as $attrName) {
	if ($attrName != $perunLoginURLAttr && $attrName !=  $perunShowOnServiceListAttr && $attrName != $perunTestSpAttr &&
		$attrName != $perunOidcClientIdAttr && $attrName != $perunSaml2EntityIdAttr) {
		array_push($attributesToShow, $attrName);
	}
}

$t = new SimpleSAML_XHTML_Template($config, 'perun:listOfSps-tpl.php');
$t->data['statistics'] = $statistics;
$t->data['attributesToShow'] = $attributesToShow;
$t->data['samlServices'] = $samlServices;
$t->data['oidcServices'] = $oidcServices;
$t->show();
