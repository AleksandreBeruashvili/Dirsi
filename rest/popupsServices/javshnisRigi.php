<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

header('Content-Type: application/json; charset=utf-8');

if (!CModule::IncludeModule("crm")) {
    echo json_encode(["status" => "error", "message" => "CRM module not loaded"]);
    exit;
}


global $USER;
$currentUserId = $USER->GetID();


$dealId = intval($_POST["dealId"]);
if (!$dealId) {
    echo json_encode(["status" => "error", "message" => "Deal ID not provided"]);
    exit;
}

if($dealId){
    $arErrorsTmp = array();
    $wfId = CBPDocument::StartWorkflow(  
        20,                                                           //პროცესის ID
        array("crm", "CCrmDocumentDeal", "DEAL_$dealId"),        // deal || contact || lead || company
        array("TargetUser" => "user_".$currentUserId),
        $arErrorsTmp
    );
}


echo json_encode(["status" => "success", "message" => "Contact saved successfully"]);
exit;

