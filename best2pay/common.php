<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();?><?

function getServerHost() {
	if (defined("SITE_SERVER_NAME"))
		return SITE_SERVER_NAME;
	return COption::GetOptionString("main", "server_name", "");
}

function orderAsPayed($response) {

	// looking for an order
	$order_id = intval($response->reference);
	if ($order_id == 0)
		throw new Exception("Invalid order id: {$order_id}");

	$arOrder = CSaleOrder::GetByID($order_id);
	if (!$arOrder)
		throw new Exception("No such order id: {$order_id}");
		
	
	CSalePaySystemAction::InitParamArrays($arOrder, $arOrder["ID"]);

	// check server signature
	$tmp_response = (array)$response;
	unset($tmp_response["signature"]);
	$signature = base64_encode(md5(implode('', $tmp_response) . CSalePaySystemAction::GetParamValue("Password")));
	if ($signature !== $response->signature)
		throw new Exception("Invalid signature");

	// check order state
	if (($response->type != 'PURCHASE' && $response->type != 'EPAYMENT' && $response->type != 'AUTHORIZE') || $response->state != 'APPROVED')
		return false;

	// extract payed order properties
	$amount = $response->amount / 100.0;
	switch (intval($response->currency)) {
		case 643:
			$currency = "RUB";
			break;
		case 978:
			$currency = "EUR";
			break;
		case 840:
			$currency = "USD";
			break;
		default:
			throw new Exception("Unknown currency (only RUB, EUR, USD are allowed)");
			break;
	}

	if ($amount <= 0 || doubleval($arOrder["PRICE"]) > $amount + 0.01)
		throw new Exception("The payed price ({$amount}) is lower than a order price ({$arOrder['PRICE']})");

	if ($currency !== $arOrder["CURRENCY"])
		throw new Exception("The order currency ({$arOrder['CURRENCY']}) is not equal the payed one ({$currency})");

	$arFields = array(
		"PS_STATUS" => "Y",
		"PS_STATUS_CODE" => $response->state,
		"PS_STATUS_DESCRIPTION" => $response->message,
		"PS_STATUS_MESSAGE" => "Best2Pay transaction id {$response->id}",
		"PS_SUM" => $amount,
		"PS_CURRENCY" => $currency,
		"PS_RESPONSE_DATE" => Date(CDatabase::DateFormatToPHP(CLang::GetDateFormat("FULL", LANG))),
		"USER_ID" => $arOrder["USER_ID"],
		"PAYED" => "Y",
		"DATE_PAYED" => Date(CDatabase::DateFormatToPHP(CLang::GetDateFormat("FULL", LANG))),
		"EMP_PAYED_ID" => false
	);

	if (!CSaleOrder::Update($arOrder["ID"], $arFields))
		throw new Exception("Error occured when updating order {$arOrder['ID']} status");
	
	return true;

}

?>
