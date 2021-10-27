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
    $authorize_mode = (strlen(CSalePaySystemAction::GetParamValue("AuthorizeMode")) > 0) ?
        intval(CSalePaySystemAction::GetParamValue("AuthorizeMode")) :
        0;
    $redirect_url = (strlen(CSalePaySystemAction::GetParamValue("RedirectURL")) > 0) ?
        CSalePaySystemAction::GetParamValue("RedirectURL") :
        "http://" . getServerHost() . "/b2p-redirect.php";

    $desc =  'Payment ' . $id;
    //$desc =  GetMessage("Order_DESCR") . ' ' . $id;
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

    $userEmail = '';
    
    if($ORDER) {
        try {
            /** @var \Bitrix\Sale\PropertyValueCollection $propertyCollection */
            if($propertyCollection = $ORDER->getPropertyCollection()) {
                if($propUserEmail = $propertyCollection->getUserEmail()) {
                    $userEmail = $propUserEmail->getValue();
                } else {
                    /** @var \Bitrix\Sale\PropertyValue $orderProperty */
                    foreach($propertyCollection as $orderProperty) {
                        if($orderProperty->getField('CODE') == 'EMAIL') {
                            $userEmail = $orderProperty->getValue();
                            break;
                        }
                    }
                }
            }
        } catch (Exception $ex) {
            if (!$userEmail) {
                $userEmail = $USER->GetEmail();
            }
        }
    } else {
        throw new Exception('Order is not exist');
    }

    $KKT = (strlen(CSalePaySystemAction::GetParamValue("KKT")) > 0) ?
        intval(CSalePaySystemAction::GetParamValue("KKT")) : 0;
    $fiscalPositions='';
    $fiscalAmount = 0;
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
                $fiscalAmount += $elementPrice * $basketItem->getQuantity();
                $fiscalPositions.=$elementPrice.';';
                $fiscalPositions.=$TAX.';';
                $fiscalPositions.=$basketItem->getField('NAME').'|';
            }
            $shipAmount = DoubleVal($GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["PRICE_DELIVERY"]);
            if ($shipAmount>0) {
                $fiscalPositions.='1;';
                $fiscalPositions.=($shipAmount*100).';';
                $fiscalAmount += $shipAmount*100;
                $fiscalPositions.=$TAX.';';
                $fiscalPositions.='Доставка'.'|';
            }
            $fiscalDiff = abs($price * 100 - $fiscalAmount);
            if ($fiscalDiff)
                $fiscalPositions .= '1;' . $fiscalDiff . ';6;Скидка;14|';          
            $fiscalPositions = substr($fiscalPositions, 0, -1);
        }
    }

    if ($ORDER['PAY_VOUCHER_NUM']){
        $b2p_order_id = $ORDER['PAY_VOUCHER_NUM'];
    } else{
        if( $curl = curl_init() ) {
            curl_setopt($curl, CURLOPT_URL, $best2pay_url . '/webapi/Register');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, '&sector=' . $sector . '&reference=' . $id . '&fiscal_positions=' . urlencode($fiscalPositions) . '&amount=' .
                intval($price * 100) . '&description=' . urlencode($desc) . '&email=' . htmlspecialchars($userEmail, ENT_QUOTES) . '&currency=' . $currency . '&mode=' . '1' . '&signature=' . $signature . '&url=' . $redirect_url);
            $b2p_order_id = curl_exec($curl);
            curl_close($curl);
        }
        CSaleOrder::Update($ORDER_ID, Array('PAY_VOUCHER_NUM' => $b2p_order_id));
    }

    if (intval($b2p_order_id) == 0)
        throw new Exception($b2p_order_id);

    $signature = base64_encode(md5($sector . $b2p_order_id . $password));
    $urlOperation = 'Purchase';
    if ($authorize_mode){
        $urlOperation = 'Authorize';
    }
?>

<form action="<?=$best2pay_url?>/webapi/<?=$urlOperation?>" method="post">
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

