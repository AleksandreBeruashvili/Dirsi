<?php
ob_start();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Title");

function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getPipelineById($categoryId) {
    if($categoryId == "0") return "SALES";
    else if($categoryId == "4") return "AFTER SALE";
    else if($categoryId == "5") return "Contracts";

    return "";
}

function getDealsByFilter($arFilter) {
    $arDeals = array();
    $res = CCrmDeal::GetListEx(array("ID"=>"DESC"), $arFilter, false, Array("nPageSize"=>10), array("ID", "TITLE", "CATEGORY_ID", "ASSIGNED_BY_ID", "ASSIGNED_BY_NAME", "ASSIGNED_BY_LAST_NAME"));
    while($arDeal = $res->Fetch()) {
        $arDeal["CATEGORY_NAME"] = getPipelineById($arDeal["CATEGORY_ID"]);
        $arDeal["RESPONSIBLE_NAME"] = $arDeal["ASSIGNED_BY_NAME"] . " " . $arDeal["ASSIGNED_BY_LAST_NAME"];

        array_push($arDeals, $arDeal);
    }
    return $arDeals;
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
$dealId = $_GET["dealId"];
$rawPhone = $_GET["phone"] ?? '';
$cleanedPhone = preg_replace('/\D/', '', $rawPhone); // ყველა არასაციფრო სიმბოლოს მოცილება

$resArray = [];

if (strlen($cleanedPhone) >= 9) {
    $searchPhone = substr($cleanedPhone, -10); // ბოლო 10 ციფრი – რუსულზე მუშაობს

    $resDeals = ckeckDeals($searchPhone, $dealId);

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