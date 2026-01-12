<?php
ob_start();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Title");

function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}


function getContactInfo($contactId) {
    $arContact = array();
    $res = CCrmContact::GetList(array("ID" => "ASC"), array("ID" => $contactId), array());
    if($arContact = $res->Fetch()){
        $PHONE=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'CONTACT','TYPE_ID' => 'PHONE', 'VALUE_TYPE' => 'MOBILE|WORK', "ELEMENT_ID" => $arContact["ID"]))->Fetch();
        $MAIL=\CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'CONTACT','TYPE_ID' => 'EMAIL', 'VALUE_TYPE' => 'HOME|WORK', "ELEMENT_ID" => $arContact["ID"]))->Fetch();
        $arContact["PHONE"] = $PHONE["VALUE"];
        $arContact["EMAIL"] = $MAIL["VALUE"];
        return $arContact;
    }
    return $arContact;
}

// function getPipelineById($categoryId) {
//     if($categoryId == "0") return "SALES";
//     else if($categoryId == "4") return "AFTER SALE";
//     else if($categoryId == "5") return "Contracts";

//     return "";
// }

// function getDealsByFilter($arFilter) {
//     $arDeals = array();
//     $res = CCrmDeal::GetListEx(array("ID"=>"DESC"), $arFilter, false, Array("nPageSize"=>1), array("ID", "TITLE", "CATEGORY_ID", "ASSIGNED_BY_ID", "ASSIGNED_BY_NAME", "ASSIGNED_BY_LAST_NAME"));
//     while($arDeal = $res->Fetch()) {
//         $arDeal["CATEGORY_NAME"] = getPipelineById($arDeal["CATEGORY_ID"]);
//         $arDeal["RESPONSIBLE_NAME"] = $arDeal["ASSIGNED_BY_NAME"] . " " . $arDeal["ASSIGNED_BY_LAST_NAME"];

//         array_push($arDeals, $arDeal);
//     }
//     return $arDeals;
// }


function getDealsByFilter($arFilter, $arSelect = array(), $arSort = array("ID"=>"DESC")) {

    $arDeals = array();
    $arSelect=array("ID","SOURCE_ID", "CONTACT_ID");
    $res = CCrmDeal::GetListEx($arSort, $arFilter, false, Array("nPageSize"=>1), $arSelect);

    while($arDeal = $res->Fetch()) {
        $arDeal["CONTACT_INFO"] = getContactInfo($arDeal["CONTACT_ID"]);
        array_push($arDeals, $arDeal);
    }
    return (count($arDeals) > 0) ? $arDeals : false;
}



function ckeckDeals($mobileNumber, $dealId) {
    $mobileNumber = substr($mobileNumber, -9);

    $dbFieldMulti = \CCrmFieldMulti::GetList(array(), array('ENTITY_ID' => 'CONTACT','TYPE_ID' => 'PHONE', "%VALUE" => $mobileNumber));

    $dealsId = array($dealId);
    $dealsArr = array();
    while($info = $dbFieldMulti->Fetch()){
        if(!empty($info["ELEMENT_ID"])) {
            $arFilter = array(
                "!ID" => $dealsId,
                "CONTACT_ID" => $info["ELEMENT_ID"],
                "CHECK_PERMISSIONS" => "N",
            );

            $resDeals = getDealsByFilter($arFilter);

            foreach ($resDeals as $resDeal) {
                array_push($dealsId, $resDeal["ID"]);

                $thisArr = array(
                    "ID" => $resDeal["ID"],
                    "TITLE" => $resDeal["TITLE"],
                    "CATEGORY_NAME" => $resDeal["CATEGORY_NAME"],
                    "RESPONSIBLE_NAME" => $resDeal["RESPONSIBLE_NAME"],
                    "PHONE" => $info["VALUE"],
                );
    
                array_push($dealsArr, $thisArr);
            }
        }
    }

    return $dealsArr;
}

$root   = $this->GetRootActivity();
$dealID = $root->GetVariable("dealID");
$arFilter = array(
    "ID" => $dealID,
);
$deal = getDealsByFilter($arFilter);
$rawPhone = $deal[0]["CONTACT_INFO"]["PHONE"];

$cleanedPhone = trim($rawPhone);
$cleanedPhone = preg_replace('/\D/', '', $cleanedPhone);
$cleanedPhone = substr($cleanedPhone, -10);



$resArray = [];

if (strlen($cleanedPhone) >= 9) {

    $resDeals = ckeckDeals($cleanedPhone, $dealID);

    $resArray["status"] = 200;
    $resArray["message"] = "OK";
    $resArray["res"] = $resDeals;
} else {
    $resArray["status"] = 500;
    $resArray["message"] = "Bad Request! Number too short.";
}

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($resArray, JSON_UNESCAPED_UNICODE);