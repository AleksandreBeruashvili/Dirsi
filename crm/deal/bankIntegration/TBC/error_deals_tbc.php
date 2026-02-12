<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

function getCIBlockElementsByFilter($arFilter,$arSelect=Array("ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*"),$arSort=array("ID"=>"DESC"))
{
    $arElements = array();
    $res = CIBlockElement::GetList($arSort, $arFilter, false, Array("nPageSize" => 99999), $arSelect);
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach ($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach ($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];
        array_push($arElements, $arPushs);
    }
    return $arElements;
}

function value_to_name($val){

    if($val=="75"){
        return "GEL";
    }elseif ($val=="74"){
        return "USD";
    }else{
        return " ";
    }

}

function addCIBlockElement($arForAdd, $arProps = array()) {
    $el = new CIBlockElement;
    $arForAdd["PROPERTY_VALUES"] = $arProps;
    if ($PRODUCT_ID = $el->Add($arForAdd)) return $PRODUCT_ID;
    else return 'Error: ' . $el->LAST_ERROR;
}

function getDealsByFilter($arFilter, $arSelect = array(), $arSort = array("ID"=>"ASC")) {
    $arDeals = array();
    $res = CCrmDeal::GetList($arSort, $arFilter, $arSelect);
    while($arDeal = $res->Fetch()) array_push($arDeals, $arDeal);
    return (count($arDeals) > 0) ? $arDeals : false;
}

function getContactsByFilter($arFilter, $arSelect = array(), $arSort = array("ID"=>"ASC")) {
    $arDeals = array();
    $res = CCrmContact::GetList($arSort, $arFilter, $arSelect);
    while($arDeal = $res->Fetch()) array_push($arDeals, $arDeal);
    return (count($arDeals) > 0) ? $arDeals : false;
}

$data=$_POST;

if(!empty($_POST)) {

    for ($x=0;$x<count($_POST);$x++) {

        if(!empty($_POST["DEAL_".$x])) {

            $deal = getDealsByFilter(array("ID" => $_POST["DEAL_" . $x]));

            if (!empty($deal)) {

                $dealid = $_POST["DEAL_" . $x];

                $list_element = getCIBlockElementsByFilter(array("IBLOCK_ID" => 65, "ID" => $_POST["PAYMENT_" . $x]));

                    $arForAdd = array(
                        'IBLOCK_ID' => 25,
                        'NAME' => $list_element[0]["partnerName"] . " " . $list_element[0]["valueDate"],
                        'ACTIVE' => 'Y',
                    );

                    $gvari = ' ';

                    $true_date = explode("T", $list_element[0]["valueDate"])[0];

                    $formdatearr = explode("-", $true_date);

                    $form_tarigi = $formdatearr[2] . "/" . $formdatearr[1] . "/" . $formdatearr[0];

                    $nbg_rate = floatval($list_element[0]["NBG_RATE"]);

                    $tanxa_larshi = number_format(floatval(floatval($_POST["AMOUNT_" . $x])*$nbg_rate), 2, ".", "");

                    $arPropsOld["DEAL"] = $dealid;
                    $arPropsOld["date"] = $form_tarigi;
                    $arPropsOld["comment"] = $list_element[0]["description"];
                    $arPropsOld["TANXA"] = $_POST["AMOUNT_" . $x];
                    $arPropsOld["BANK_PAYMENT_ID"] = $_POST["PAYMENT_" . $x];
                    $arPropsOld["tanxa_gel"] = $tanxa_larshi;
                    $arPropsOld["PROJECT"] = $deal[0]["UF_CRM_1693385948133"];
                    $arPropsOld["KORPUSI"] = $deal[0]["UF_CRM_1702018321416"];
                    $arPropsOld["BINIS_NOMERI"] = $deal[0]["UF_CRM_1693385964548"];
                    $arPropsOld["ZETIPI"] = $deal[0]["UF_CRM_1693385992603"];
                    $arPropsOld["ANGARISHIS_TIPI"] = $deal[0]["UF_CRM_1705413820965"];
                    $arPropsOld["KONTRAKT_DATE"] = $deal[0]["UF_CRM_1693398443196"];
                    $arPropsOld["TRANZ_TYPE"] = $deal[0]["UF_CRM_1702038078127"];
                    $arPropsOld["CURRENCY"] = value_to_name($deal[0]["UF_CRM_1705395356366"]);
                    $arPropsOld["NBG"] = $list_element[0]["NBG_RATE"];
                    $arPropsOld["FULL_NAME"] = $deal[0]["CONTACT_FULL_NAME"];
                    $arPropsOld["xelshNum"] = $deal[0]["UF_CRM_1715329001809"];

                $res = addCIBlockElement($arForAdd, $arPropsOld);



                ///////////// გადახდების დაჯამება /////////

                $arFilter = array("ID"=>$dealid);
                $deals=getDealsByFilter($arFilter);

                $arFilter = array("PROPERTY_DEAL"=>$dealid,
                    "IBLOCK_ID"=>66);
                $payments=getCIBlockElementsByFilter($arFilter);

                $moneyToPay=$deals[0]['OPPORTUNITY'];
                $payedMoney=0;

                foreach($payments as $singlePayment){
                    $payedMoney=$payedMoney+$singlePayment['TANXA'];
                }

                $moneyLeft=$moneyToPay-$payedMoney;

                $CCrmDeal = new CCrmDeal();
                $upd = array(
                    "UF_CRM_1720447878" => $moneyLeft,
                    "UF_CRM_1711115555932" => $payedMoney,

                );
                $CCrmDeal->Update($dealid, $upd);

                $sales_dealid="D_".$dealid;


                $arFilter = array("UF_CRM_1693398065" => $sales_dealid);
                $otherDeals=getDealsByFilter($arFilter);

                foreach($otherDeals as $deal){

                    $otherDeal_ID=$deal["ID"];

                    $CCrmDeal = new CCrmDeal();
                    $upd = array(
                        "UF_CRM_1720447878" => $moneyLeft,
                        "UF_CRM_1711115555932" => $payedMoney,

                    );
                    $CCrmDeal->Update($otherDeal_ID, $upd);
                }

                ///////////// გადახდების დაჯამება /////////

                header("Location:https://crmasgroup.ge/crm/deal/bankIntegration/TBC/merge_deals_tbc.php");

            }
        }

    }


}else{
    header("Location:https://crmasgroup.ge/crm/deal/bankIntegration/TBC/merge_deals_tbc.php");
}

