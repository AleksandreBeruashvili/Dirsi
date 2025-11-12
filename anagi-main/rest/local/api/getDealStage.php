<?php
ob_start();
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("createProd");

function printArr($arr)
{
    echo "<pre>";
    print_r($arr);
    echo "</pre>";
}

function getDeal($id)
{
    $arSelect = array(
        "ID",
        "STAGE_ID",
        "UF_CRM_1749208140043",
        "UF_CRM_1749645541654",
        "UF_CRM_1749645602184",
        "UF_CRM_1749645626420",
        "UF_CRM_1749645638534",
        "UF_CRM_1749735905048",
        "UF_CRM_1749645932978",
        "UF_CRM_1749645959700",
        "UF_CRM_1749645978952"
    );

    $res = CCrmDeal::GetList([], array("ID" => $id), $arSelect);

    if ($arDeal = $res->Fetch()) {
        return $arDeal;
    }

    return [];
}

function checkRequiredFields($dealData)
{
    $requiredFields = array(
        "UF_CRM_1749645541654",
        "UF_CRM_1749645602184",
        "UF_CRM_1749645626420",
        "UF_CRM_1749645638534",
        "UF_CRM_1749735905048",
        "UF_CRM_1749645932978",
        "UF_CRM_1749645959700",
        "UF_CRM_1749645978952"
    );

    foreach ($requiredFields as $field) {
        // Check if field is empty, null, or doesn't exist
        if (empty($dealData[$field]) && $dealData[$field] !== '0') {
            return false;
        }
    }

    return true;
}

$deal_id = $_GET["id"];
$dealData = getDeal($deal_id);

if (empty($dealData)) {
    $response = array(
        "error" => "Deal not found"
    );
} else {
    $allFieldsFilled = checkRequiredFields($dealData);

    if ($allFieldsFilled) {
        // All fields are filled - return success response
        $response = array(
            "status" => "success",
            "message" => "All required fields are filled",
            "all_fields_complete" => true,
            "deal_data" => array(
                "STAGE_ID" => $dealData["STAGE_ID"],
                "UF_CRM_1749208140043" => $dealData["UF_CRM_1749208140043"]
            )
        );
    } else {
        // One or more fields are not filled - return opposite response
        $response = array(
            "status" => "incomplete",
            "message" => "Some required fields are missing",
            "all_fields_complete" => false,
            "deal_data" => array(
                "STAGE_ID" => $dealData["STAGE_ID"],
                "UF_CRM_1749208140043" => $dealData["UF_CRM_1749208140043"]
            )
        );
    }
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>