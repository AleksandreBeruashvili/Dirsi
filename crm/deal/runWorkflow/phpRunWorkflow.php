<?php
ob_start();

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle(" ");
CJSCore::Init(array("jquery"));




$postJson = array();

try {
    $postJson = \Bitrix\Main\Web\Json::decode(\Bitrix\Main\HttpRequest::getInput());
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}



$pbId=$postJson["pbId"];
$dealId = $postJson["deal"]["ID"];



$resToReturn = [];

if($pbId && $dealId){
    $resToReturn["status"] = 200;
    $resToReturn["pbId"] = $pbId;
    $resToReturn["dealId"] = $dealId;

    $arErrorsTmp = array();
    $wfId = CBPDocument::StartWorkflow(
        $pbId,                                                               //პროცესის ID
        array("crm", "CCrmDocumentDeal", "DEAL_$dealId"),        // deal || contact || lead || company
        $arWorkflowParams,
        $arErrorsTmp
    );
    
}else{
    $resToReturn["status"] = 400;
    $resToReturn["ERROR"] = "wrong paraks: dealId=$dealId  pbId=$pbId";
}



ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($resToReturn, JSON_UNESCAPED_UNICODE);

?>