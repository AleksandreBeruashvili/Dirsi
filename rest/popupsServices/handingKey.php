<?php
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

if (!CModule::IncludeModule('crm')) {
    echo json_encode(['status' => 'error', 'message' => 'CRM module not installed']);
    die();
}

if (!CModule::IncludeModule('bizproc')) {
    echo json_encode(['status' => 'error', 'message' => 'Bizproc module not installed']);
    die();
}

global $USER;
$currentUserId = $USER->GetID();

header('Content-Type: application/json');

$dealId          = isset($_POST['DEAL_ID'])           ? intval($_POST['DEAL_ID'])             : 0;
$keyHanded       = isset($_POST['key_handed'])        ? $_POST['key_handed']                  : '';
$keyHandedString = isset($_POST['key_handed_string']) ? trim($_POST['key_handed_string'])     : '';
$keyDate         = isset($_POST['key_date'])          ? trim($_POST['key_date'])              : '';
$actDate         = isset($_POST['act_date'])          ? trim($_POST['act_date'])              : '';
$fullPrice        = isset($_POST['full_price'])         ? intval($_POST['full_price'])         : 0;

if (!$dealId || $keyHanded === '' || $keyDate === '' || $actDate === '') {
    echo json_encode(['status' => 'error', 'message' => 'მონაცემები არასრულია']);
    die();
}

// ფაილის ატვირთვა Bitrix-ში
$actFileId = null;
if (!empty($_FILES['act_file']['tmp_name'])) {
    $file = $_FILES['act_file'];

    $arFile = CFile::MakeFileArray($file['tmp_name'], $file['name'], $file['type']);
    $arFile['name']         = $file['name'];
    $arFile['MODULE_ID']    = 'crm';

    $actFileId = CFile::SaveFile($arFile, 'crm');

    if (!$actFileId) {
        echo json_encode(['status' => 'error', 'message' => 'ფაილის შენახვა ვერ მოხდა']);
        die();
    }

}

// BP დაიწყე
$arErrorsTmp = [];
CBPDocument::StartWorkflow(
    108,
    ["crm", "CCrmDocumentDeal", "DEAL_$dealId"],
    [
        "keyHanded"       => $keyHanded,
        "keyHandedString" => $keyHandedString,
        "keyDate"         => $keyDate,
        "actDate"         => $actDate,
        "actFileId"       => $actFileId,
        "TargetUser" => "user_" . $currentUserId,
        "fullPrice"  => $fullPrice,
    ],
    $arErrorsTmp
);

if (!empty($arErrorsTmp)) {
    echo json_encode(["status" => "error", "errors" => $arErrorsTmp]);
    die();
}

echo json_encode(["status" => "success"]);