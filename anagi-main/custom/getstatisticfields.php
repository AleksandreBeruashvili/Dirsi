<?php
ob_start();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Title");
CModule::IncludeModule('webservice');
ob_end_clean();

/**
 * Utility debug
 */
function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

/**
 * Ensure globals are defined safely
 */
global $USER;
$currentUserId = $USER ? $USER->GetID() : null;

/**
 * Default: assume not authorized until proven otherwise
 */
$NotAuthorized = true;
$user_id = null;

if ($USER && $USER->GetID()) {
    $NotAuthorized = false;
    $user_id = $USER->GetID();
    // Avoid re-authorizing with a fixed id in production - but keep your old behavior:
    $USER->Authorize(1);
}

/**
 * Safe wrappers for CRM fetches
 */
function getContactInfo($contactId) {
    $arContact = array();
    if (!is_numeric($contactId) || $contactId <= 0) return $arContact;
    $res = CCrmContact::GetList(array("ID" => "ASC"), array("ID" => $contactId), array());
    if ($res && $arContact = $res->Fetch()) {
        return $arContact;
    }
    return $arContact;
}

function getCompanyInfo($companyId) {
    $arCompany = array();
    if (!is_numeric($companyId) || $companyId <= 0) return $arCompany;
    $res = CCrmCompany::GetList(array("ID" => "ASC"), array("ID" => $companyId), false, false, array());
    if ($res && $arCompany = $res->Fetch()) {
        return $arCompany;
    }
    return $arCompany;
}

function getUserFullName($userid) {
    if (!$userid) return 'unknown';
    $arSelect = array('SELECT' => array("ID","NAME","LAST_NAME","WORK_POSITION","UF_*"));
    $rsUsers = CUser::GetList(($by="NAME"), ($order="desc"), array("ID" => $userid), $arSelect);
    if ($arUser = $rsUsers->Fetch()) return trim("{$arUser['NAME']} {$arUser['LAST_NAME']}");
    return 'unknown';
}

/**
 * Get product by ID with defensive checks
 */
function getProdByID($ID, $NBG = 1) {
    $arElements = array();

    if (!is_numeric($ID) || $ID <= 0) return $arElements;

    $arSelect = array("ID", "IBLOCK_ID", "IBLOCK_SECTION_ID", "DETAIL_PICTURE", "PRICE", "NAME", "PROPERTY_*");
    $res = CIBlockElement::GetList(array("ID" => "ASC"), array("ID" => $ID), false, Array("nPageSize" => 1), $arSelect);

    if ($res && $ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();

        $arPushs = array();

        // Copy fields safely
        foreach ($arFilds as $key => $arFild) {
            $arPushs[$key] = $arFild;
        }

        // Copy properties safely
        foreach ($arProps as $key => $arProp) {
            // store raw value or empty string
            $arPushs[$key] = isset($arProp["VALUE"]) ? $arProp["VALUE"] : "";
        }

        // Price - safe
        $price = CPrice::GetBasePrice($ID);
        $arPushs["PRICE"] = isset($price["PRICE"]) ? round($price["PRICE"], 2) : 0;

        // Ensure CONTACT key exists
        $arPushs["CONTACT"] = "";

        // Company/contact owners - check existence and numeric
        if (isset($arPushs["OWNER_COMPANY"]) && $arPushs["OWNER_COMPANY"] !== '' && $arPushs["OWNER_COMPANY"] !== '0') {
            $contact = getCompanyInfo($arPushs["OWNER_COMPANY"]);
            if (!empty($contact)) $arPushs["CONTACT"] = $contact;
        }

        if (isset($arPushs["OWNER_CONTACT"]) && $arPushs["OWNER_CONTACT"] !== '' && $arPushs["OWNER_CONTACT"] !== '0') {
            $contact = getContactInfo($arPushs["OWNER_CONTACT"]);
            if (!empty($contact)) $arPushs["CONTACT"] = $contact;
        }

        // OWNER_DEAL -> reserved until
        if (!empty($arPushs["OWNER_DEAL"])) {
            // echo "you're in owner deal if";
            $DealId = $arPushs["OWNER_DEAL"];
            $Deal = getDealInfo($DealId);
            if ($Deal && isset($Deal["UF_CRM_1706258652967"]) && $Deal["UF_CRM_1706258652967"] !== '0') {
                $arPushs["RESERVED_UNTIL"] = $Deal["UF_CRM_1706258652967"];
            }
        }

        // QUEUE handling: be defensive and always set keys (so frontend can rely on them)
        $arPushs["QUEUE"] = isset($arPushs["QUEUE"]) ? $arPushs["QUEUE"] : "";
        $arPushs["QUEUE_CONTACT_ID"] = "";
        $arPushs["QUEUE_CONTACT_NAME"] = "";
        $arPushs["QUEUE_RESPONSIBLE_ID"] = "";
        $arPushs["QUEUE_RESPONSIBLE_NAME"] = "";

        if (!empty($arPushs["QUEUE"]) && $arPushs["QUEUE"] !== '0' && is_string($arPushs["QUEUE"])) {
            // explode safely and check count
            $DealIds = explode('|', $arPushs["QUEUE"]);
            // choose first non-empty numeric id from array: (some systems store like |123|456)
            $firstDealId = null;
            foreach ($DealIds as $part) {
                $part = trim($part);
                if (is_numeric($part) && $part > 0) {
                    $firstDealId = (int)$part;
                    break;
                }
            }

            if ($firstDealId) {
                $Deal = getDealInfo($firstDealId);
                if ($Deal) {
                    $arPushs["QUEUE_CONTACT_ID"] = isset($Deal["CONTACT_ID"]) ? $Deal["CONTACT_ID"] : "";
                    $arPushs["QUEUE_CONTACT_NAME"] = isset($Deal["CONTACT_FULL_NAME"]) ? $Deal["CONTACT_FULL_NAME"] : "";
                    $arPushs["QUEUE_RESPONSIBLE_ID"] = isset($Deal["ASSIGNED_BY_ID"]) ? $Deal["ASSIGNED_BY_ID"] : "";
                    $arPushs["QUEUE_RESPONSIBLE_NAME"] = $arPushs["QUEUE_RESPONSIBLE_ID"] ? getUserFullName($arPushs["QUEUE_RESPONSIBLE_ID"]) : "";
                }
            }
        }

        // DEAL_RESPONSIBLE
        $arPushs["RESP_NAME"] = "";
        if (!empty($arPushs["DEAL_RESPONSIBLE"])) {
            $arPushs["RESP_NAME"] = getUserFullName($arPushs["DEAL_RESPONSIBLE"]);
        }

        // price conversions - ensure numeric to avoid warnings
        $arPushs['KVM_PRICE_GEL'] = "";
        if (isset($arPushs["KVM_PRICE"]) && is_numeric($arPushs["KVM_PRICE"])) {
            $arPushs['KVM_PRICE_GEL'] = round($arPushs["KVM_PRICE"] * $NBG, 2);
        }
        $arPushs['PRICE_GEL'] = isset($arPushs["PRICE"]) && is_numeric($arPushs["PRICE"]) ? round($arPushs["PRICE"] * $NBG, 2) : 0;

        $image = CFile::GetPath($arPushs['binis_naxazi']);
        if ($image) {
            $image = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $image;
        } else {
            $image = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . "/catalog/projects/resources/noimage.jpg";
        }
        $arPushs['image'] = $image;

        $image2 = CFile::GetPath($arPushs['binis_gegmareba']);
        if ($image2) {
            $image2 = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $image2;
        } else {
            $image2 = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . "/catalog/projects/resources/noimage.jpg";
        }
        $arPushs['image2'] = $image2;

        $image3 = CFile::GetPath($arPushs['render_3D']);
        if ($image3) {
            $image3 = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $image3;
        } else {
            $image3 = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . "/catalog/projects/resources/noimage.jpg";
        }
        $arPushs['image3'] = $image3;


        // Return assembled product
        return $arPushs;
    }

    return $arElements;
}

/**
 * Get deal info safely
 */
function getDealInfo($dealID) {
    if (!is_numeric($dealID) || $dealID <= 0) return false;
    $selectFields = array("ID","CONTACT_ID","ASSIGNED_BY_ID","UF_CRM_1706258652967","CONTACT_FULL_NAME");
    // CCrmDeal::GetList signature: GetList($arOrder, $arFilter, $arNavParams=false, $arSelect=array());
    $res = CCrmDeal::GetList(
        array("ID" => "ASC"),
        array("ID" => $dealID),
        $selectFields,   // SELECT fields
        array()          // options
    );

    if ($res && $arDeal = $res->Fetch()) {
        // Optionally load product rows if needed
        // $prods = CCrmDeal::LoadProductRows($dealID);
        return $arDeal;
    }
    return false;
}

/**
 * NBG currency fetch (defensive)
 */
function getNbg()
{
    $date = date("Y-m-d");
    $url = "https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies?Currencies=USD&date={$date}";

    $seb = @file_get_contents($url);
    if ($seb === false) {
        // Fallback to 1 – avoid division by zero or null
        return 1;
    }

    $decoded = @json_decode($seb);
    if (!is_array($decoded) || !isset($decoded[0]->currencies[0]->rate)) {
        return 1;
    }

    $seb_currency = $decoded[0]->currencies[0]->rate;
    if (!is_numeric($seb_currency)) return 1;
    return $seb_currency;
}

/**
 * Main logic
 */
$elementsID = isset($_GET["prodID"]) ? $_GET["prodID"] : null;
$prodData = array();

if (is_numeric($elementsID) && $elementsID > 0) {
    $nbg = getNbg();
    $prodData = getProdByID((int)$elementsID, $nbg);
} else {
    // Always return at least an empty object/array so frontend won't break
    $prodData = array();
}
/**
 * Revert authorization state
 */
if ($NotAuthorized && $USER) {
    $USER->Logout();
} elseif (!$NotAuthorized && $USER && $user_id) {
    // re-authorize original user id
    $USER->Authorize($user_id);
}

/**
 * Output JSON
 */
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($prodData, JSON_UNESCAPED_UNICODE);
