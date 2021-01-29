<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();?><?


include_once(GetLangFileName(dirname(__FILE__) . "/", "/payment.php"));
include_once(dirname(__FILE__) . "/common.php");

try {
    $id = $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["ID"];

    $sector = CSalePaySystemAction::GetParamValue("Sector");
    $password = CSalePaySystemAction::GetParamValue("Password");
    $test_mode = (strlen(CSalePaySystemAction::GetParamValue("TestMode")) > 0) ?
        intval(CSalePaySystemAction::GetParamValue("TestMode")) :
        1;
    $redirect_url = (strlen(CSalePaySystemAction::GetParamValue("RedirectURL")) > 0) ?
        CSalePaySystemAction::GetParamValue("RedirectURL") :
        "http://" . getServerHost() . "/b2p-redirect.php";

    $desc =  GetMessage("Order_DESCR") . ' ' . $id;
    $price=$GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["SHOULD_PAY"];
    $currency_str = $currency = $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["CURRENCY"];

    switch ($currency) {
        case 'RUR':
            $currency = '643';
            break;
        case 'RUB':
            $currency = '643';
            break;
        case 'EUR':
            $currency = '978';
            break;
        case 'USD':
            $currency = '840';
            break;
        default:
            throw new Exception('Unknown currency (only RUR, RUB, EUR, USD are allowed)');
            break;
    }

    $signature  = base64_encode(md5($sector . intval($price * 100) . $currency . $password));

    $best2pay_url = "https://pay.best2pay.net";
    if ($test_mode == 1)
        $best2pay_url = "https://test.best2pay.net";


    $ORDER_ID =$id;
    $ORDER = CSaleOrder::GetByID($ORDER_ID);

    $KKT = (strlen(CSalePaySystemAction::GetParamValue("KKT")) > 0) ?
        intval(CSalePaySystemAction::GetParamValue("KKT")) : 0;
    $fiscalPositions='';
    if ($KKT==1){
        $TAX = (strlen(CSalePaySystemAction::GetParamValue("TAX")) > 0) ?
            intval(CSalePaySystemAction::GetParamValue("TAX")) : 7;
        if ($TAX > 0 && $TAX < 7){
            $basket = \Bitrix\Sale\Order::load($ORDER_ID)->getBasket();
            $basketItems = $basket->getBasketItems();
            foreach ($basket as $basketItem) {
                $fiscalPositions.=$basketItem->getQuantity().';';
                $elementPrice = $basketItem->getPrice();
                $elementPrice = $elementPrice * 100;
                $fiscalPositions.=$elementPrice.';';
                $fiscalPositions.=$TAX.';';
                $fiscalPositions.=$basketItem->getField('NAME').'|';
            }
            $fiscalPositions = substr($fiscalPositions, 0, -1);
        }
    }

    $context  = stream_context_create(array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query(array(
                'sector' => $sector,
                'reference' => $id,
                'fiscal_positions' => $fiscalPositions,
                'amount' => intval($price * 100),
                'description' => $desc,
                'email' => htmlspecialchars($USER->GetEmail(), ENT_QUOTES),
                'currency' => $currency,
                'mode' => 1,
                'url' => $redirect_url,
                'signature' => $signature
            )),
        )
    ));

    if ($ORDER['PAY_VOUCHER_NUM']){
        $b2p_order_id = $ORDER['PAY_VOUCHER_NUM'];
    } else{
        $b2p_order_id = file_get_contents($best2pay_url . '/webapi/Register', false, $context);
        CSaleOrder::Update($ORDER_ID, Array('PAY_VOUCHER_NUM' => $b2p_order_id));
    }

    if (intval($b2p_order_id) == 0)
        throw new Exception($b2p_order_id);

    $signature = base64_encode(md5($sector . $b2p_order_id . $password));
    ?>

    <form action="<?=$best2pay_url?>/webapi/Epayment" method="post">
        <font class="tablebodytext">
            <?=GetMessage("PYM_TITLE")?><br>
            <?=GetMessage("PYM_ORDER")?> <?=$id?><br>
            <?=GetMessage("PYM_TO_PAY")?> <b><?=SaleFormatCurrency($price, $currency_str)?></b>
            <p>
                <input type="hidden" name="sector" value="<?=$sector?>">
                <input type="hidden" name="id" value="<?=$b2p_order_id?>">
                <input type="hidden" name="signature" value="<?=$signature?>">
                <input type="submit" value="<?=GetMessage('PYM_BUTTON')?>">
            </p>
        </font>
    </form>
    <?php

} catch (Exception $ex) {
    error_log($ex->getMessage());
    echo 'Оплата не удалась';
}

?>


