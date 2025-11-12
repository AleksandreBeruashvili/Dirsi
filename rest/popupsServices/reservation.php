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

function getDealInfoByIDToolbar($dealId)
{
    $res = CCrmDeal::GetList(array("ID" => "ASC"), array("ID" => $dealId), array());
    if ($arDeal = $res->Fetch()) {
        return $arDeal;
    }
}


function addWorkDays($startDate, $daysToAdd) {
    $date = DateTime::createFromFormat('d/m/Y', $startDate);
    $addedDays = 0;

    while ($addedDays < $daysToAdd) {
        $date->modify('+1 day');
        $dayOfWeek = $date->format('w'); 
        if ($dayOfWeek != 0) {
            $addedDays++;
        }
    }

    return $date->format('d/m/Y');
}

$deal =getDealInfoByIDToolbar($dealId);

if (!$deal) {
    echo json_encode(["status" => "error", "message" => "Deal not found"]);
    exit;
}

$contactId = intval($deal["CONTACT_ID"]);
$phone=trim($_POST["phone"]);
$reservationType=trim($_POST["reservationType"]);
$sellComment=trim($_POST["comment"]);

$ResChange=trim($_POST["ResChange"]);
$ResChangeDate =trim($_POST["ResChangeDate"]);
$vada =trim($_POST["vada"]);

$contactFields = [
    "NAME" => trim($_POST["firstName"]),
    "LAST_NAME" => trim($_POST["lastName"]),
    "UF_CRM_1761652010097" => trim($_POST["passportId"]),
    "UF_CRM_1761651998145" => trim($_POST["personalId"]),

];



$contact = new CCrmContact(false);
if ($contactId > 0) {

    $result = $contact->Update($contactId, $contactFields, true, true, array("CURRENT_USER" => 1, "DISABLE_USER_FIELD_CHECK" => true));


    if($phone){
        $fieldMulti = new \CCrmFieldMulti();

        $dbFieldMulti = \CCrmFieldMulti::GetList(
            [],
            [
                'ENTITY_ID' => 'CONTACT',
                'TYPE_ID' => 'PHONE',
                'ELEMENT_ID' => $contactId,
            ]
        );

        while ($field = $dbFieldMulti->Fetch()) {
            $fieldMulti->Delete($field['ID']);
        }

        $newPhoneData = [
            'ENTITY_ID' => 'CONTACT',
            'ELEMENT_ID' => $contactId,
            'TYPE_ID' => 'PHONE',
            'VALUE_TYPE' => 'WORK',
            'VALUE' => $phone,
        ];

        $result2 = $fieldMulti->Add($newPhoneData);
    }


    if (!$result || !$result2) {
        echo json_encode(["status" => "error", "message" => "Failed to update contact"]);
        exit;
    }

}
if($dealId){
    $today = date("d/m/Y"); 
    $threeWorkDaysLater = addWorkDays($today, 2);

    if($reservationType=="ufaso"){
        $typeId="71";
        $reservationDate=$threeWorkDaysLater;
    }else if($reservationType=="uvado"){
        $typeId="72";
        $reservationDate="";
    }else{
        $typeId=""; 
        $reservationDate="";
    }
}


if($dealId){
    $arErrorsTmp = array();
    $wfId = CBPDocument::StartWorkflow(  
        18,                                                           //პროცესის ID
        array("crm", "CCrmDocumentDeal", "DEAL_$dealId"),        // deal || contact || lead || company
        array("vada"=>$vada, "ResChangeDate"=>$ResChangeDate, "resComment"=>$sellComment, "gashvebisTarixi"=>$today,"reservationDate"=>$reservationDate, "reservationType"=>$reservationType, "TargetUser" => "user_".$currentUserId),
        $arErrorsTmp
    );
}


echo json_encode(["status" => "success", "message" => "Contact saved successfully"]);
exit;

