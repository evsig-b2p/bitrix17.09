<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
	die();

include(GetLangFileName(dirname(__FILE__)."/", "/payment.php"));

$psTitle = GetMessage("SPCP_DTITLE");
$psDescription = GetMessage("SPCP_DDESCR");

$arPSCorrespondence = array(
	"TestMode" => array(
		"NAME" => GetMessage("TestMode"),
		"DESCR" => GetMessage("TestMode_DESCR"),
		"VALUE" => "",
		"TYPE" => ""
	),
    "KKT" => array(
        "NAME" => GetMessage("KKT"),
        "DESCR" => GetMessage("KKT_DESCR"),
        "VALUE" => "",
        "TYPE" => ""
    ),
    "TAX" => array(
        "NAME" => GetMessage("TAX"),
        "DESCR" => GetMessage("TAX_DESCR"),
        "VALUE" => "",
        "TYPE" => ""
    ),
    "AuthorizeMode" => array(
        "NAME" => GetMessage("AuthorizeMode"),
        "DESCR" => GetMessage("AuthorizeMode_DESCR"),
        "VALUE" => "",
        "TYPE" => ""
    ),
	"Sector" => array(
		"NAME" => GetMessage("Sector"),
		"DESCR" => GetMessage("Sector_DESCR"),
		"VALUE" => "",
		"TYPE" => ""
	),
	"Password" => array(
		"NAME" => GetMessage("Password"),
		"DESCR" => GetMessage("Password_DESCR"),
		"VALUE" => "",
		"TYPE" => ""
	),
	"SuccessURL" => array(
		"NAME" => GetMessage("SuccessURL"),
		"DESCR" => GetMessage("SuccessURL_DESCR"),
		"VALUE" => "",
		"TYPE" => ""
	),
	"FailURL" => array(
		"NAME" => GetMessage("FailURL"),
		"DESCR" => GetMessage("FailURL_DESCR"),
		"VALUE" => "",
		"TYPE" => ""
	),
	"RedirectURL" => array(
		"NAME" => GetMessage("RedirectURL"),
		"DESCR" => GetMessage("RedirectURL_DESCR"),
		"VALUE" => "",
		"TYPE" => ""
	)
);

