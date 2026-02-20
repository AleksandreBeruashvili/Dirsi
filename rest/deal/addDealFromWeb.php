<?php
ob_start();
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
ob_end_clean();
$APPLICATION->SetTitle("Title");
CModule::IncludeModule('bizproc');
CModule::IncludeModule('workflow');
CModule::IncludeModule("iblock");
CModule::IncludeModule("crm");
CModule::IncludeModule('search');

// ─── კონტაქტის ძებნა ტელეფონით ────────────────────────────────────────────
function checkContactByPhone($phone) {
    if (empty($phone)) return false;

    $phone = substr(preg_replace('/\D/', '', $phone), -9);
    if (strlen($phone) < 9) return false;

    $db = \CCrmFieldMulti::GetList(
        array(),
        array(
            'ENTITY_ID'  => 'CONTACT',
            'TYPE_ID'    => 'PHONE',
            '%VALUE'     => $phone
        )
    );

    while ($row = $db->Fetch()) {
        $found = substr(preg_replace('/\D/', '', $row["VALUE"]), -9);
        if ($phone === $found && !empty($row["ELEMENT_ID"])) {
            return (int)$row["ELEMENT_ID"];
        }
    }
    return false;
}

// ─── კონტაქტის ძებნა მეილით ────────────────────────────────────────────────
function checkContactByEmail($email) {
    if (empty($email)) return false;

    $db = \CCrmFieldMulti::GetList(
        array(),
        array(
            'ENTITY_ID' => 'CONTACT',
            'TYPE_ID'   => 'EMAIL',
            '=VALUE'    => trim($email)
        )
    );

    if ($row = $db->Fetch()) {
        if (!empty($row["ELEMENT_ID"])) {
            return (int)$row["ELEMENT_ID"];
        }
    }
    return false;
}

// ─── ახალი კონტაქტის შექმნა ────────────────────────────────────────────────
function createContact($name, $phone, $email) {
    $contactFields = array(
        "NAME"           => $name,
        "OPENED"         => "Y",
        "ASSIGNED_BY_ID" => 1,
        "FM"             => array(
            "EMAIL" => array(
                "n0" => array("VALUE" => $email, "VALUE_TYPE" => "WORK")
            )
        )
    );

    if (!empty($phone)) {
        $contactFields["FM"]["PHONE"] = array(
            "n0" => array("VALUE" => $phone, "VALUE_TYPE" => "WORK")
        );
    }

    $CCrmContact = new CCrmContact(false);
    $contactId = $CCrmContact->Add(
        $contactFields,
        true,
        array("CURRENT_USER" => 1, 'DISABLE_USER_FIELD_CHECK' => true)
    );

    if (!$contactId) {
        return array("error" => $CCrmContact->LAST_ERROR);
    }
    return (int)$contactId;
}

// ─── კონტაქტზე დაკარგული ველის მიმატება (ტელ. ან მეილი) ──────────────────
function addMissingFieldToContact($contactId, $typeId, $value) {
    if (empty($value)) return;

    // შევამოწმოთ უკვე ხომ არ აქვს ეს მნიშვნელობა
    $db = \CCrmFieldMulti::GetList(
        array(),
        array('ENTITY_ID' => 'CONTACT', 'ELEMENT_ID' => $contactId, 'TYPE_ID' => $typeId)
    );
    while ($row = $db->Fetch()) {
        if ($typeId === 'PHONE') {
            $existing = substr(preg_replace('/\D/', '', $row["VALUE"]), -9);
            $new      = substr(preg_replace('/\D/', '', $value), -9);
            if ($existing === $new) return; // უკვე აქვს
        } else {
            if (strtolower(trim($row["VALUE"])) === strtolower(trim($value))) return; // უკვე აქვს
        }
    }

    $fieldMulti = new CCrmFieldMulti();
    $fieldMulti->Add(array(
        'ENTITY_ID'  => 'CONTACT',
        'ELEMENT_ID' => $contactId,
        'TYPE_ID'    => $typeId,
        'VALUE_TYPE' => 'WORK',
        'VALUE'      => $value,
    ));
}

// ─── მთავარი ფუნქცია ───────────────────────────────────────────────────────
function registerDealFromWebsite($name, $phone, $email, $flat_type, $message) {
    global $APPLICATION, $USER;

    if (!is_object($USER)) $USER = new CUser;
    $USER->Authorize(1);

    // 1. კონტაქტის პოვნა / შექმნა
    $contactId   = false;
    $foundByPhone = false;
    $foundByEmail = false;

    if (!empty($phone)) {
        $contactId = checkContactByPhone($phone);
        if ($contactId) $foundByPhone = true;
    }

    if (!$contactId && !empty($email)) {
        $contactId = checkContactByEmail($email);
        if ($contactId) $foundByEmail = true;
    }

    if (!$contactId) {
        $result = createContact($name, $phone, $email);
        if (is_array($result)) {
            return array("status" => "error", "message" => "Contact creation failed: " . $result["error"]);
        }
        $contactId = $result;
    } else {
        // ნაპოვნი კონტაქტია – დავამატოთ დაკარგული ველი
        if ($foundByPhone && !empty($email)) {
            addMissingFieldToContact($contactId, 'EMAIL', $email);
        }
        if ($foundByEmail && !empty($phone)) {
            addMissingFieldToContact($contactId, 'PHONE', $phone);
        }
    }

    // 2. დილის შექმნა (სათაური დროებითი)
    $arFields = array(
        "CATEGORY_ID"          => 0,
        "STAGE_ID"             => "NEW",
        "TITLE"                => "WebSite Deal #",
        "CONTACT_ID"           => $contactId,
        "SOURCE_ID"            => "STORE",
        "UF_CRM_1771574778"    => $flat_type,
        "UF_CRM_1771574810"    => $message,
        "UF_CRM_1763356180625" => 220,
        "ASSIGNED_BY_ID"       => 17,
    );

    $CCrmDeal = new CCrmDeal();
    $dealId = $CCrmDeal->Add(
        $arFields,
        true,
        array("CURRENT_USER" => 1, 'DISABLE_USER_FIELD_CHECK' => true)
    );

    if (!$dealId) {
        return array("status" => "error", "message" => $CCrmDeal->LAST_ERROR);
    }

    // 3. სათაურის განახლება – WebSite Deal # {ID}
    $updateFields = array("TITLE" => "WebSite Deal # " . $dealId);
    $updateOptions = array("CURRENT_USER" => 1, 'DISABLE_USER_FIELD_CHECK' => true);
    $CCrmDeal->Update($dealId, $updateFields, true, $updateOptions);

    return (int)$dealId;
}

// ─── REQUEST ───────────────────────────────────────────────────────────────
try {
    $json = \Bitrix\Main\Web\Json::decode(\Bitrix\Main\HttpRequest::getInput());
} catch (\Bitrix\Main\SystemException $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array("status" => "error", "message" => "Invalid JSON: " . $e->getMessage()));
    exit;
}

$name        = trim($json["name"]         ?? "ContactFromWebsite");
$phone       = trim($json["phone_number"] ?? "");
$email       = trim($json["email"]        ?? "");
$flat_type   = $json["flat_type"]         ?? null;
$message     = $json["message"]           ?? null;

if (empty($email)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array("status" => "error", "message" => "email is required"));
    exit;
}

// ─── შედეგი ────────────────────────────────────────────────────────────────
$result = registerDealFromWebsite($name, $phone, $email, $flat_type, $message);

header('Content-Type: application/json; charset=utf-8');

if (is_numeric($result)) {
    $res = CCrmDeal::GetList(array(), array("ID" => $result), array())->Fetch();
    echo json_encode(array(
        "status"  => 200,
        "message" => "OK",
        "deal"    => array(
            "ID"         => $res["ID"],
            // "TITLE"      => $res["TITLE"],
            "CONTACT_ID" => $res["CONTACT_ID"],
            // "SOURCE_ID"  => $res["SOURCE_ID"],
        )
    ), JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}
?>
