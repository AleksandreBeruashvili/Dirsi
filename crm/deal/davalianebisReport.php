<?php
ob_start();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Cashflow Report");


// ======================== FUNCTIONS ========================

function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getDaricxvebi($deals_IDs) {
    if (empty($deals_IDs)) return array();
    
    $daricxvebi = array();

    $arSelect = Array("ID", "IBLOCK_SECTION_ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*");
    $arFilter = array(
        "IBLOCK_ID"     => 20,
        "PROPERTY_DEAL" => $deals_IDs,
    );

    $res = CIBlockElement::GetList(Array("PROPERTY_TARIGI" => "ASC"), $arFilter, false, Array("nPageSize" => 99999), $arSelect);
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();

        $amount = (float) str_replace("|USD","",$arProps["TANXA"]["VALUE"]);

        $daricxvebi[] = array(
            "DEAL_ID" => $arProps["DEAL"]["VALUE"],
            "daricxva_date" => $arProps["TARIGI"]["VALUE"],
            "daricxva_amount" => $amount
        );
    }

    return $daricxvebi;
}

function getGadaxdebi($deals_IDs){
    if (empty($deals_IDs)) return array();

    $gadaxdebi = array();

    $arSelect = Array("ID", "IBLOCK_SECTION_ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*");
    $arFilter = array(
        "IBLOCK_ID"             => 21,
        "PROPERTY_DEAL"         => $deals_IDs
    );

    $res = CIBlockElement::GetList(Array("date" => "ASC"), $arFilter, false, Array("nPageSize" => 99999), $arSelect);
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();

        $dealID = trim($arProps["DEAL"]["VALUE"]);

        if ($dealID === "") {
            continue;
        }

        $amount = (float) str_replace("|USD","",$arProps["TANXA"]["VALUE"]);

        $gadaxdebi[] = array(
            "DEAL_ID" => $dealID,
            "gadaxda_date" => $arProps["date"]["VALUE"],
            "gadaxda_amount" => $amount
        );
    }
    
    return $gadaxdebi;
}

function getNBG_inventory($date){
    $url="https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies?Currencies=USD&date={$date}";
    $seb = file_get_contents($url);
    $seb = json_decode($seb);
    $seb_currency=$seb[0]->currencies[0]->rate;
    return $seb_currency;
}

function getUserName ($id) {
    $res = CUser::GetByID($id)->Fetch();
    return $res["NAME"]." ".$res["LAST_NAME"];
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

function getCountry($enumId) {
    if (empty($enumId)) return '';
    
    $res = CUserFieldEnum::GetList([], ["ID" => $enumId]);
    if ($row = $res->Fetch()) {
        return $row["VALUE"];
    }
    return '';
}

function getDealsByFilter($arFilter, $project = '', $arSelect = array(), $arSort = array("ID"=>"DESC")) {
    $result["deals_data"] = array();
    $result["deals_IDs"] = array();

    $res_deals = CCrmDeal::GetList($arSort, $arFilter, array("ID", "DATE_CREATE", "CONTACT_ID","COMPANY_ID", "TITLE","CONTACT_FULL_NAME","OPPORTUNITY","COMPANY_TITLE","UF_CRM_1761658532158","ASSIGNED_BY_ID","UF_CRM_1766560177934", "UF_CRM_1764317005", "UF_CRM_1761658516561", "UF_CRM_1766736693236", "UF_CRM_1761658577987", "UF_CRM_1770888201367", "UF_CRM_1770640981002", "UF_CRM_1767011536", "UF_CRM_1761658608306", "UF_CRM_1761658503260", "UF_CRM_1761658559005", "UF_CRM_1762416342444", 'UF_CRM_1766563053146'));
    while($arDeal = $res_deals->Fetch()) {
        $arDeal["payment"] = 0;
        $arDeal["responsible"] = getUserName($arDeal["ASSIGNED_BY_ID"]);

        $contact = getContactInfo($arDeal["CONTACT_ID"]);
        $arDeal["OWNER_CONTACT_PN"] = $contact["UF_CRM_1761651998145"];
        $arDeal["OWNER_CONTACT_CITIZENSHIP"] = getCountry($contact["UF_CRM_1769506891465"]); 
        $arDeal["OWNER_CONTACT_PHONE"] = $contact["PHONE"];

        $result["deals_data"][$arDeal["ID"]] = $arDeal;
        $result["deals_IDs"][] = $arDeal["ID"];
    }
    return (count($result["deals_IDs"]) > 0) ? $result : false;
}

function getProducts($dealIds) {
    if (empty($dealIds)) return array();
    
    // Ensure $dealIds is an array for the filter
    if (!is_array($dealIds)) $dealIds = array($dealIds);

    $arFilter = array(
        "IBLOCK_ID" => 14,
        "PROPERTY_DEAL" => $dealIds,
        "ACTIVE" => "Y" // Good practice to only get active items
    );

    // Note: Properties usually need the PROPERTY_ prefix in the select array
    $arSelect = ["ID", "IBLOCK_ID", "PROPERTY_BEDROOMS", "PROPERTY_OWNER_DEAL"];
    
    $res = CIBlockElement::GetList(array(), $arFilter, false, array("nTopCount" => 99999), $arSelect);
    
    $arElements = array();
    $nbg = getNBG_inventory(date("Y-m-d"));

    while ($ob = $res->GetNext()) {
        $arPushs = [];
        $dealKey = $ob["PROPERTY_OWNER_DEAL_VALUE"];
        $arPushs["OWNER_DEAL"] = $ob["PROPERTY_OWNER_DEAL_VALUE_ID"];
        $arPushs["Bedrooms"] = $ob["PROPERTY_BEDROOMS_VALUE"];
        $arPushs["ID"] = $ob["ID"];
        $arElements[$dealKey] = $arPushs;
    }

    return $arElements;
}

// ======================================================================= MAIN CODE =======================================================================

// Get filter values from request
$filterProject     = isset($_GET['project'])      ? trim($_GET['project'])      : '';
$filterBlock       = isset($_GET['block'])        ? trim($_GET['block'])        : '';
$filterBuilding    = isset($_GET['building'])     ? trim($_GET['building'])     : '';
$filterFloor       = isset($_GET['floor'])        ? trim($_GET['floor'])        : '';
$filterProductType = isset($_GET['prodType'])     ? trim($_GET['prodType'])     : '';
$filterResponsible = isset($_GET['responsible'])  ? trim($_GET['responsible'])  : '';
$filterDateFrom    = isset($_GET['date_from'])    ? trim($_GET['date_from'])    : '';
$filterDateTo      = isset($_GET['date_to'])      ? trim($_GET['date_to'])      : date('Y-m-d');
$filterCustomField = isset($_GET['custom_field']) ? trim($_GET['custom_field']) : '';

$lang = isset($_GET['lang']) ? $_GET['lang'] : 'eng';
$labels = [
    'eng' => [
        'project'        => 'Project',
        'block'          => 'Block',
        'building'       => 'Building',
        'floor'          => 'Floor',
        'prodType'       => 'Product Type',
        'responsible'    => 'Responsible',
        'contract_no'    => 'Contract №',
        'all_projects'   => 'All Projects',
        'all_blocks'     => 'All Blocks',
        'all_buildings'  => 'All Buildings',
        'all_floors'     => 'All Floors',
        'all_types'      => 'All Product Types',
        'all_resp'       => 'All Responsible',
        'apply'          => 'Apply Filters',
        'clear'          => 'Clear',
        'export'         => '📥 Export to Excel',
        'title_dollars'  => 'By $',
        'title_units'    => 'By Unit',
        'col_prod_type'  => 'Product Type',
        'col_sold_amt'   => 'Amount of Sold ($)',
        'col_paid_amt'   => 'Paid Amount ($)',
        'col_debet'      => 'Debet ($)',
        'col_by_sched'   => 'Payments by Schedule ($)',
        'col_debt'       => 'Debt ($)',
        'col_sold_count' => 'Sold flat count',
        'col_paid_full'  => 'Paid fully count',
        'col_debet_cnt'  => 'Debet Count',
        'col_sched_cnt'  => 'Payments by Schedule Count',
        'col_debt_cnt'   => 'Debt count',
        'total'          => 'Total',
        'no_data'        => 'No data available',
        'xls_deal'       => 'Deal#',
        'xls_client'     => 'Client',
        'xls_resp'       => 'Responsible',
        'xls_contract'   => 'Contract Signing Date',
        'xls_sched_start'=> 'Schedule Start Date',
        'xls_sched_end'  => 'Schedule End Date',
        'xls_buyer_type' => 'Buyer Type',
        'xls_id_num'     => 'ID Number',
        'xls_citizen'    => 'Citizenship',
        'xls_phone'      => 'Mobile Number',
        'xls_re_type'    => 'Real Estate Type',
        'xls_area'       => 'Area (sqm)',
        'xls_building'   => 'Building',
        'xls_block'      => 'Block',
        'xls_floor'      => 'Floor',
        'xls_apt'        => 'Apartment #',
        'xls_price_sqm'  => 'Price per sqm',
        'xls_total_price'=> 'Total Price',
        'xls_paid'       => 'Amount Paid',
        'xls_paid_pct'   => 'Amount Paid (%)',
        'xls_remaining'  => 'Remaining Amount',
        'xls_debt'       => 'Debt',
        'xls_overdue'    => 'Overdue Days',
        'xls_status'     => 'Status',
        'xls_contract_old' => 'Contract # (Old Base)',
        'xls_contract_new' => 'Contract #',
    ],
    'ge' => [
        'project'        => 'პროექტი',
        'block'          => 'ბლოკი',
        'building'       => 'კორპუსი',
        'floor'          => 'სართული',
        'prodType'       => 'პროდუქტის ტიპი',
        'responsible'    => 'პასუხისმგებელი',
        'contract_no'    => 'კონტრაქტი №',
        'all_projects'   => 'ყველა პროექტი',
        'all_blocks'     => 'ყველა ბლოკი',
        'all_buildings'  => 'ყველა კორპუსი',
        'all_floors'     => 'ყველა სართული',
        'all_types'      => 'ყველა ტიპი',
        'all_resp'       => 'ყველა პასუხისმგებელი',
        'apply'          => 'ფილტრის გამოყენება',
        'clear'          => 'გასუფთავება',
        'export'         => '📥 Excel-ში ექსპორტი',
        'title_dollars'  => '$ - ით',
        'title_units'    => 'ერთეულით',
        'col_prod_type'  => 'პროდუქტის ტიპი',
        'col_sold_amt'   => 'გაყიდვების თანხა ($)',
        'col_paid_amt'   => 'გადახდილი თანხა ($)',
        'col_debet'      => 'დებეტი ($)',
        'col_by_sched'   => 'გადახდა გრაფიკით ($)',
        'col_debt'       => 'დავალიანება ($)',
        'col_sold_count' => 'გაყიდული ბინების რაოდენობა',
        'col_paid_full'  => 'სრულად გადახდილი',
        'col_debet_cnt'  => 'დებეტის რაოდენობა',
        'col_sched_cnt'  => 'გრაფიკით გადამხდელები',
        'col_debt_cnt'   => 'მოვალეების რაოდენობა',
        'total'          => 'სულ',
        'no_data'        => 'მონაცემი არ მოიძებნა',
        'xls_deal'       => 'გარიგება#',
        'xls_client'     => 'კლიენტი',
        'xls_resp'       => 'პასუხისმგებელი',
        'xls_contract'   => 'ხელშეკრულების თარიღი',
        'xls_sched_start'=> 'გრაფიკის დაწყება',
        'xls_sched_end'  => 'გრაფიკის დასრულება',
        'xls_buyer_type' => 'მყიდველის ტიპი',
        'xls_id_num'     => 'პირადი ნომერი',
        'xls_citizen'    => 'მოქალაქეობა',
        'xls_phone'      => 'მობილური',
        'xls_re_type'    => 'უძრავი ქონების ტიპი',
        'xls_area'       => 'ფართობი (კვ.მ)',
        'xls_building'   => 'კორპუსი',
        'xls_block'      => 'ბლოკი',
        'xls_floor'      => 'სართული',
        'xls_apt'        => 'ბინის №',
        'xls_price_sqm'  => 'ფასი კვ.მ-ზე',
        'xls_total_price'=> 'სრული ფასი',
        'xls_paid'       => 'გადახდილი თანხა',
        'xls_paid_pct'   => 'გადახდილი (%)',
        'xls_remaining'  => 'დარჩენილი თანხა',
        'xls_debt'       => 'დავალიანება',
        'xls_overdue'    => 'ვადაგადაცილების დღეები',
        'xls_status'     => 'სტატუსი',
        'xls_contract_old' => 'ხელშეკრულების N (ძველი ბაზა)',
        'xls_contract_new' => 'ხელშეკრულების N',
    ],
];

$t = $labels[$lang] ?? $labels['eng'];

// Build the filter array with applied filters
$arFilter = ["STAGE_ID" => "WON"];

if (!empty($filterProject)) {
    $arFilter["UF_CRM_1761658516561"] = $filterProject;
}
if (!empty($filterBlock)) {
    $arFilter["UF_CRM_1766560177934"] = $filterBlock; 
}
if (!empty($filterBuilding)) {
    $arFilter["UF_CRM_1766736693236"] = $filterBuilding; 
}
if (!empty($filterFloor)) {
    $arFilter["UF_CRM_1761658577987"] = $filterFloor; 
}
if (!empty($filterProductType)) {
    if (str_contains($filterProductType, "Flat")) {
        preg_match('/\d+/', $filterProductType, $matches);
        $bedroom = $matches[0];
        $arFilter["UF_CRM_1770888201367"] = $bedroom; 
    } else {
        $arFilter["UF_CRM_1761658532158"] = $filterProductType; 
    }
}
if (!empty($filterResponsible)) {
    $arFilter["ASSIGNED_BY_ID"] = $filterResponsible;
}
if (!empty($filterCustomField)) {
    $arFilter["%UF_CRM_1770640981002"] = $filterCustomField;
}

// Get deals with applied filters
$result = getDealsByFilter($arFilter);

if ($result === false) {
    $deals = array();
    $deals_IDs = array();
} else {
    $deals = $result["deals_data"];
    $deals_IDs = $result["deals_IDs"];
}

$daricxvebi = getDaricxvebi($deals_IDs);
$gadaxdebi  = getGadaxdebi($deals_IDs);

$daricxvebiByDeals = [];
foreach ($daricxvebi as $d) {
    $dealID = trim($d["DEAL_ID"]);
    $daricxvebiByDeals[$dealID][] = $d;
}

foreach($daricxvebiByDeals as $dealId => $dilisDaricxvebi) {
    $daricxvebiByDeals[$dealId] = $daricxvebiByDeals[$dealId][count($dilisDaricxvebi) - 1]["daricxva_date"]; //dilis bolo daricxvis tarigi ever
}

// Filter by payment date range if provided
if (!empty($filterDateFrom) || !empty($filterDateTo)) {
    $gadaxdebi = array_filter($gadaxdebi, function($g) use ($filterDateFrom, $filterDateTo) {
        $raw = trim($g["gadaxda_date"]);
        if (empty($raw)) return false;

        $dateStr = null;
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})/', $raw, $m)) {
            $dateStr = $m[3] . '-' . $m[2] . '-' . $m[1];
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
            $dateStr = $m[1] . '-' . $m[2] . '-' . $m[3];
        } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $raw, $m)) {
            $dateStr = $m[3] . '-' . $m[2] . '-' . $m[1];
        } else {
            $ts = strtotime($raw);
            if ($ts === false) return true;
            $dateStr = date('Y-m-d', $ts);
        }

        if (!empty($filterDateFrom) && $dateStr < $filterDateFrom) return false;
        if (!empty($filterDateTo)   && $dateStr > $filterDateTo)   return false;
        return true;
    });

    $daricxvebi = array_filter($daricxvebi, function($d) use ($filterDateFrom, $filterDateTo) {
        $raw = trim($d["daricxva_date"]);
        if (empty($raw)) return false;

        $dateStr = null;
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})/', $raw, $m)) {
            $dateStr = $m[3] . '-' . $m[2] . '-' . $m[1];
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
            $dateStr = $m[1] . '-' . $m[2] . '-' . $m[3];
        } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $raw, $m)) {
            $dateStr = $m[3] . '-' . $m[2] . '-' . $m[1];
        } else {
            $ts = strtotime($raw);
            if ($ts === false) return true;
            $dateStr = date('Y-m-d', $ts);
        }

        if (!empty($filterDateFrom) && $dateStr < $filterDateFrom) return false;
        if (!empty($filterDateTo)   && $dateStr > $filterDateTo)   return false;
        return true;
    });
}

$products = getProducts($deals_IDs);

// Get unique values for filter dropdowns
$projects = array();
$blocks = array();
$buildings = array();
$floors = array();
$prodTypes = ["Flat (1 Bed.)", "Flat (2 Bed.)", "Flat (3 Bed.)"];
$responsibles = array();

$allDealsResult = getDealsByFilter(["STAGE_ID" => "WON"]);
if ($allDealsResult !== false) {
    $allDeals = $allDealsResult["deals_data"];
    foreach ($allDeals as $deal) {
        if (!empty($deal["UF_CRM_1761658516561"]) && !in_array($deal["UF_CRM_1761658516561"], $projects)) {
            $projects[] = $deal["UF_CRM_1761658516561"];
        }
        if (!empty($deal["ASSIGNED_BY_ID"]) && !in_array($deal["ASSIGNED_BY_ID"], $responsibles)) {
            $responsibles[$deal["ASSIGNED_BY_ID"]] = getUserName($deal["ASSIGNED_BY_ID"]);
        }
        if (!empty($deal["UF_CRM_1766560177934"]) && !in_array($deal["UF_CRM_1766560177934"], $blocks) && $deal["UF_CRM_1766560177934"] !== 'P') {
            $blocks[] = $deal["UF_CRM_1766560177934"];
        }
        if (!empty($deal["UF_CRM_1766736693236"]) && !in_array($deal["UF_CRM_1766736693236"], $buildings)) {
            $buildings[] = $deal["UF_CRM_1766736693236"];
        }
        if (!empty($deal["UF_CRM_1761658577987"]) && !in_array($deal["UF_CRM_1761658577987"], $floors)) {
            $floors[] = $deal["UF_CRM_1761658577987"];
        }
        if (!empty($deal["UF_CRM_1761658532158"]) && !in_array($deal["UF_CRM_1761658532158"], $prodTypes) && $deal["UF_CRM_1761658532158"] !== "Flat") {
            $prodTypes[] = $deal["UF_CRM_1761658532158"];
        }
    }
}

sort($projects);
sort($blocks);
sort($buildings);
usort($floors, function($a, $b) {
    return (int)$a - (int)$b;
});
asort($responsibles);
// end filter population

// Process deals data
foreach ($deals as &$deal) {
    $deal["jamuriDaricxvaUpToToday"] = 0;
    $deal["jamuriGadaxdaUpToToday"]  = 0;
}
unset($deal);

$today = date('Y-m-d');

// count dilis daricxvebi dgevandlamde
foreach ($daricxvebi as $d) {
    $dealID = trim($d["DEAL_ID"]);
    if (!isset($deals[$dealID])) continue;

    // Normalize date for comparison
    $raw = trim($d["daricxva_date"]);
    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})/', $raw, $m)) {
        $normalized = $m[3] . '-' . $m[2] . '-' . $m[1];
    } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
        $normalized = $m[1] . '-' . $m[2] . '-' . $m[3];
    } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $raw, $m)) {
        $normalized = $m[3] . '-' . $m[2] . '-' . $m[1];
    } else {
        $ts = strtotime($raw);
        $normalized = $ts !== false ? date('Y-m-d', $ts) : '';
    }

    // Only accumulate schedule amounts up to today
    if (!empty($normalized) && $normalized <= $today) {
        $deals[$dealID]["jamuriDaricxvaUpToToday"] += (float) $d["daricxva_amount"];
        $deals[$dealID]["boloDaricxvaDateByFilter"] = $d["daricxva_date"];
    }

    // boloDaricxvaDate always gets the last entry (no date restriction)
    $deals[$dealID]["boloDaricxvaDate"] = $daricxvebiByDeals[$dealID];
}

// count dilis gadaxdebi dgevandlamde
foreach ($gadaxdebi as $g) {
    $dealID = trim($g["DEAL_ID"]);
    if (!isset($deals[$dealID])) continue;
    $deals[$dealID]["jamuriGadaxdaUpToToday"] += (float) $g["gadaxda_amount"];
}

// Compute fullyPaid / paidBySchedule flags per deal
foreach ($deals as $dealID => $deal) {
    $deals[$dealID]["paidBySchedule"] = (
        $deal["jamuriGadaxdaUpToToday"] >= $deal["jamuriDaricxvaUpToToday"]
    ) ? 1 : 0;

    if (floatval($deal["jamuriGadaxdaUpToToday"]) >= floatval($deal["OPPORTUNITY"])) {
        $deals[$dealID]["paidPercentage"] = '100%';
    } else {
        $pct = $deal["OPPORTUNITY"] > 0 ? $deal["jamuriGadaxdaUpToToday"] / $deal["OPPORTUNITY"] * 100 : 0;
        $pct = min($pct, 99.99);
        $deals[$dealID]["paidPercentage"] = round($pct, 2) . "%";
    }
    $deals[$dealID]["remainedAmount"] = floatval($deal["OPPORTUNITY"]) - floatval($deal["jamuriGadaxdaUpToToday"]);
    if ($deals[$dealID]["remainedAmount"] < 0) {
        $deals[$dealID]["remainedAmount"] = 0;
    }
    $deals[$dealID]["fullyPaid"]      = $deals[$dealID]["remainedAmount"] <= 0 ? 1 : 0;
    
    $debt = floatval($deal["jamuriDaricxvaUpToToday"]) - floatval($deal["jamuriGadaxdaUpToToday"]);
    $deals[$dealID]["debt"] = $debt < 0 ? 0 : $debt;

    if (!empty($deal["boloDaricxvaDateByFilter"]) && $deals[$dealID]["debt"] > 0) {
        $raw = trim($deal["boloDaricxvaDateByFilter"]);
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})/', $raw, $m)) {
            $normalized = $m[3] . '-' . $m[2] . '-' . $m[1];
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
            $normalized = $m[1] . '-' . $m[2] . '-' . $m[3];
        } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $raw, $m)) {
            $normalized = $m[3] . '-' . $m[2] . '-' . $m[1];
        } else {
            $normalized = $raw;
        }
        $targetTs = strtotime($normalized);
        $diff = time() - $targetTs;
        $deals[$dealID]["overdueDays"] = ($targetTs !== false && $diff > 0) ? floor($diff / 86400) : 0;
    } else {
        $deals[$dealID]["overdueDays"] = 0;
    }
}

$resArray = [];

$emptyBucket = [
    "amountOfSoldFlatsDollars" => 0,
    "paidAmount"               => 0,
    "debet"                    => 0,
    "paymentScheduleDollar"    => 0,
    "debt"                     => 0,
    "count"                    => 0,
    "fullyPaidCount"           => 0,
    "notFullyPaidCount"        => 0,
    "paidByScheduleCount"      => 0,
    "debtCount"                => 0,
];

foreach ($deals as $deal) {
    $product  = $products[$deal["ID"]] ?? [];
    $prodType = $deal["UF_CRM_1761658532158"];

    if (!$prodType) continue;

    if ($prodType === "Flat") {
        $bedrooms = $product["Bedrooms"] ?? '';
        if ($bedrooms === "1") {
            $key = "Flat (1 Bed.)";
        } elseif ($bedrooms === "2") {
            $key = "Flat (2 Bed.)";
        } elseif ($bedrooms === "3") {
            $key = "Flat (3 Bed.)";
        } else {
            continue;
        }
    } else {
        $key = $prodType;
    }
    $deals[$deal["ID"]]["prodTypeNew"] = $key;

    if (!isset($resArray[$key])) {
        $resArray[$key] = $emptyBucket;
    }

    $resArray[$key]["count"]++;
    // if ($deal["fullyPaid"] === 1)                                   $resArray[$key]["fullyPaidCount"]++;
    $resArray[$key]["fullyPaidCount"] += $deal["fullyPaid"];
    // if ($deal["paidBySchedule"] === 1)  $resArray[$key]["paidByScheduleCount"]++;
    $resArray[$key]["paidByScheduleCount"] += $deal["paidBySchedule"];
    if ($deal["debt"] > 0) $resArray[$key]["debtCount"]++;

    $resArray[$key]["amountOfSoldFlatsDollars"] += (float) ($deal["OPPORTUNITY"] ?? 0);
    $resArray[$key]["paidAmount"]               += (float) ($deal["jamuriGadaxdaUpToToday"] ?? 0);
    $resArray[$key]["debet"]                     = $resArray[$key]["amountOfSoldFlatsDollars"] - $resArray[$key]["paidAmount"];
    $resArray[$key]["paymentScheduleDollar"]    += (float) ($deal["jamuriDaricxvaUpToToday"] ?? 0);
    $resArray[$key]["debt"]                     += (float) ($deal["debt"] ?? 0);
}

foreach ($resArray as $key => &$bucket) {
    $bucket["notFullyPaidCount"] = $bucket["count"] - $bucket["fullyPaidCount"];
}
unset($bucket);

// Sort resArray by bedroom number for Flat types, then other types after
uksort($resArray, function($a, $b) {
    $order = ['Flat (1 Bed.)' => 1, 'Flat (2 Bed.)' => 2, 'Flat (3 Bed.)' => 3];
    $aOrder = $order[$a] ?? 99;
    $bOrder = $order[$b] ?? 99;
    if ($aOrder !== $bOrder) return $aOrder - $bOrder;
    return strcmp($a, $b);
});

// General totals
$generalTotals = [
    "amountOfSoldFlatsDollars" => 0,
    "paidAmount"               => 0,
    "debet"                    => 0,
    "paymentScheduleDollar"    => 0,
    "debt"                     => 0,
    "count"                    => 0,
    "fullyPaidCount"           => 0,
    "notFullyPaidCount"        => 0,
    "paidByScheduleCount"      => 0,
    "debtCount"                => 0,
];

foreach ($resArray as $data) {
    $generalTotals["amountOfSoldFlatsDollars"] += $data["amountOfSoldFlatsDollars"];
    $generalTotals["paidAmount"]               += $data["paidAmount"];
    $generalTotals["paymentScheduleDollar"]    += $data["paymentScheduleDollar"];
    $generalTotals["count"]                    += $data["count"];
    $generalTotals["fullyPaidCount"]           += $data["fullyPaidCount"];
    $generalTotals["paidByScheduleCount"]      += $data["paidByScheduleCount"];
    $generalTotals["debtCount"]         += $data["debtCount"];
    $generalTotals["notFullyPaidCount"]  = $generalTotals["count"] - $generalTotals["fullyPaidCount"];
    $generalTotals["debt"]                     += $data["debt"];
}
$generalTotals["debet"] = $generalTotals["amountOfSoldFlatsDollars"] - $generalTotals["paidAmount"];
// $generalTotals["debt"]  = $generalTotals["debet"]    - $generalTotals["paymentScheduleDollar"];
// printArr($deals[1357]);
ob_end_clean();

?>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/js/select2.min.js"></script>
<script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>

<style>
    .filter-container {
        background-color: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 30px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
    }
    .filter-group {
        display: flex;
        flex-direction: column;
        min-width: 180px;
    }
    .filter-group label {
        font-weight: 600;
        margin-bottom: 5px;
        color: #333;
    }
    .filter-group select,
    .filter-group input[type="date"],
    .filter-group input[type="text"] {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        height: 38px;
        box-sizing: border-box;
    }
    .filter-buttons {
        display: flex;
        gap: 10px;
        align-items: flex-end;
    }
    .btn {
        padding: 8px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: background-color 0.3s;
    }
    .btn-primary {
        background-color: #007bff;
        color: white;
    }
    .btn-primary:hover {
        background-color: #0056b3;
    }
    .btn-secondary {
        background-color: #6c757d;
        color: white;
    }
    .btn-secondary:hover {
        background-color: #545b62;
    }
    .cashflow-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 30px;
        font-family: Arial, sans-serif;
    }
    .cashflow-table th {
        background-color: #2c3e50;
        color: white;
        padding: 12px;
        text-align: left;
        border: 1px solid #ddd;
    }
    .cashflow-table td {
        padding: 10px;
        border: 1px solid #ddd;
    }
    .cashflow-table tr:nth-child(even) {
        background-color: #f2f2f2;
    }
    .cashflow-table tr:hover {
        background-color: #e8e8e8;
    }
    .total-row {
        background-color: #d4edda !important;
        font-weight: bold;
    }
    .table-title {
        font-size: 20px;
        font-weight: bold;
        margin: 20px 0 10px 0;
        color: #333;
    }
    .amount {
        text-align: right;
    }
    .count-col {
        text-align: center;
    }
</style>

<div class="filter-container">
    <form method="GET" action="">
        <input type="hidden" name="lang" value="<?= htmlspecialchars($lang) ?>">
        <div class="filter-row">
            <div class="filter-group">
                <label for="project"><?= $t['project'] ?>:</label>
                <select name="project" id="project">
                    <option value=""><?= $t['all_projects'] ?></option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?= htmlspecialchars($project) ?>" <?= $filterProject == $project ? 'selected' : '' ?>>
                            <?= htmlspecialchars($project) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="block"><?= $t['block'] ?>:</label>
                <select name="block" id="block">
                    <option value=""><?= $t['all_blocks'] ?></option>
                    <?php foreach ($blocks as $block): ?>
                        <option value="<?= htmlspecialchars($block) ?>" <?= $filterBlock == $block ? 'selected' : '' ?>>
                            <?= htmlspecialchars($block) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="building"><?= $t['building'] ?>:</label>
                <select name="building" id="building">
                    <option value=""><?= $t['all_buildings'] ?></option>
                    <?php foreach ($buildings as $building): ?>
                        <option value="<?= htmlspecialchars($building) ?>" <?= $filterBuilding == $building ? 'selected' : '' ?>>
                            <?= htmlspecialchars($building) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="floor"><?= $t['floor'] ?>:</label>
                <select name="floor" id="floor">
                    <option value=""><?= $t['all_floors'] ?></option>
                    <?php foreach ($floors as $floor): ?>
                        <option value="<?= htmlspecialchars($floor) ?>" <?= $filterFloor == $floor ? 'selected' : '' ?>>
                            <?= htmlspecialchars($floor) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="prodType"><?= $t['prodType'] ?>:</label>
                <select name="prodType" id="prodType">
                    <option value=""><?= $t['all_types'] ?></option>
                    <?php foreach ($prodTypes as $prodType): ?>
                        <option value="<?= htmlspecialchars($prodType) ?>" <?= $filterProductType == $prodType ? 'selected' : '' ?>>
                            <?= htmlspecialchars($prodType) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="responsible"><?= $t['responsible'] ?>:</label>
                <select name="responsible" id="responsible">
                    <option value=""><?= $t['all_resp'] ?></option>
                    <?php foreach ($responsibles as $id => $name): ?>
                        <option value="<?= htmlspecialchars($id) ?>" <?= $filterResponsible == $id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- <div class="filter-group">
                <label for="date_from">Payment Date From:</label>
                <input type="date" name="date_from" id="date_from"
                       value="<?= htmlspecialchars($filterDateFrom) ?>">
            </div>

            <div class="filter-group">
                <label for="date_to">Payment Date To:</label>
                <input type="date" name="date_to" id="date_to"
                       value="<?= htmlspecialchars($filterDateTo) ?>">
            </div> -->

            <div class="filter-group">
                <label for="custom_field"><?= $t['contract_no'] ?>:</label>
                <input type="text" name="custom_field" id="custom_field"
                       value="<?= htmlspecialchars($filterCustomField) ?>"
                       placeholder="Search...">
            </div>
            
            <div class="filter-buttons">
                <button type="submit" class="btn btn-primary"><?= $t['apply'] ?></button>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='<?= $_SERVER['PHP_SELF'] ?>'"><?= $t['clear'] ?></button>
            </div>
        </div>
    </form>

    <div style="margin-top: 15px;">
        <button class="btn btn-primary" onclick="exportToExcel()"><?= $t['export'] ?></button>
    </div>
</div>

<!-- ===== TABLE 1: DOLLAR AMOUNTS ===== -->
<div class="table-title">By $</div>
<table class="cashflow-table" style="table-layout: fixed;">
    <colgroup>
        <col style="width: 20%;">
        <col style="width: 16%;">
        <col style="width: 16%;">
        <col style="width: 16%;">
        <col style="width: 16%;">
        <col style="width: 16%;">
    </colgroup>
    <thead>
        <tr>
            <th>Product Type</th>
            <th class="amount">Amount of Sold ($)</th>
            <th class="amount">Paid Amount ($)</th>
            <th class="amount">Debet ($)</th>
            <th class="amount">Payments by Schedule ($)</th>
            <th class="amount">Debt ($)</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($resArray)): ?>
        <tr>
            <td colspan="6" style="text-align: center;">No data available</td>
        </tr>
        <?php else: ?>
            <?php foreach ($resArray as $key => $data): ?>
            <tr>
                <td><?= htmlspecialchars($key) ?></td>
                <td class="amount"><?= number_format($data["amountOfSoldFlatsDollars"], 2) ?></td>
                <td class="amount"><?= number_format($data["paidAmount"], 2) ?></td>
                <td class="amount"><?= number_format($data["debet"], 2) ?></td>
                <td class="amount"><?= number_format($data["paymentScheduleDollar"], 2) ?></td>
                <td class="amount"><?= number_format($data["debt"], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td>Total</td>
                <td class="amount"><?= number_format($generalTotals["amountOfSoldFlatsDollars"], 2) ?></td>
                <td class="amount"><?= number_format($generalTotals["paidAmount"], 2) ?></td>
                <td class="amount"><?= number_format($generalTotals["debet"], 2) ?></td>
                <td class="amount"><?= number_format($generalTotals["paymentScheduleDollar"], 2) ?></td>
                <td class="amount"><?= number_format($generalTotals["debt"], 2) ?></td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- ===== TABLE 2: COUNTS ===== -->
<div class="table-title">By Unit</div>
<table class="cashflow-table" style="table-layout: fixed;">
    <colgroup>
        <col style="width: 20%;">
        <col style="width: 16%;">
        <col style="width: 16%;">
        <col style="width: 16%;">
        <col style="width: 16%;">
        <col style="width: 16%;">
    </colgroup>
    <thead>
        <tr>
            <th>Product Type</th>
            <th class="count-col">Sold flat count</th>
            <th class="count-col">Paid fully count</th>
            <th class="count-col">Debet Count</th>
            <th class="count-col">Payments by Schedule Count</th>
            <th class="count-col">Debt count</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($resArray)): ?>
        <tr>
            <td colspan="6" style="text-align: center;">No data available</td>
        </tr>
        <?php else: ?>
            <?php foreach ($resArray as $key => $data): ?>
            <tr>
                <td><?= htmlspecialchars($key) ?></td>
                <td class="count-col"><?= (int) $data["count"] ?></td>
                <td class="count-col"><?= (int) $data["fullyPaidCount"] ?></td>
                <td class="count-col"><?= (int) $data["notFullyPaidCount"] ?></td>
                <td class="count-col"><?= (int) $data["paidByScheduleCount"] ?></td>
                <td class="count-col"><?= (int) $data["debtCount"] ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td>Total</td>
                <td class="count-col"><?= (int) $generalTotals["count"] ?></td>
                <td class="count-col"><?= (int) $generalTotals["fullyPaidCount"] ?></td>
                <td class="count-col"><?= (int) $generalTotals["notFullyPaidCount"] ?></td>
                <td class="count-col"><?= (int) $generalTotals["paidByScheduleCount"] ?></td>
                <td class="count-col"><?= (int) $generalTotals["debtCount"] ?></td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<script>
    const dealsData = <?= json_encode(array_values($deals)) ?>;
    const t = <?= json_encode($t) ?>;

    function exportToExcel() {
        const wb = XLSX.utils.book_new();

        // =============================================
        // SHEET 1: Summary Tables (from rendered HTML)
        // =============================================
        const summaryRows = [];

        const titles = document.querySelectorAll('.table-title');
        titles.forEach(function(titleEl) {
            summaryRows.push([titleEl.innerText.trim()]);

            let table = titleEl.nextElementSibling;
            while (table && table.tagName !== 'TABLE') {
                table = table.nextElementSibling;
            }
            if (!table) return;

            // Header row
            const headerRow = [];
            table.querySelectorAll('thead tr th').forEach(function(th) {
                headerRow.push(th.innerText.trim());
            });
            summaryRows.push(headerRow);

            // Data rows
            table.querySelectorAll('tbody tr').forEach(function(tr) {
                const row = [];
                tr.querySelectorAll('td').forEach(function(td) {
                    let val = td.innerText.trim();
                    const num = parseFloat(val.replace(/,/g, ''));
                    if (!isNaN(num) && val !== '') val = num;
                    row.push(val);
                });
                summaryRows.push(row);
            });

            summaryRows.push([]);
        });

        const ws1 = XLSX.utils.aoa_to_sheet(summaryRows);
        ws1['!cols'] = [
            { wch: 22 }, { wch: 18 }, { wch: 18 }, { wch: 18 }, { wch: 26 }, { wch: 16 },
        ];
        XLSX.utils.book_append_sheet(wb, ws1, 'Cashflow Summary');

        // =============================================
        // SHEET 2: Deal Details (existing logic)
        // =============================================
        const fields = [
            { key: 'ID',                        label: t.xls_deal },
            { key: 'UF_CRM_1766563053146',      label: t.xls_contract_old },
            { key: 'UF_CRM_1770640981002',      label: t.xls_contract_new },
            { key: 'CONTACT_FULL_NAME',         label: t.xls_client },
            { key: 'responsible',               label: t.xls_resp },
            { key: 'UF_CRM_1762416342444',      label: t.xls_contract },
            { key: 'UF_CRM_1767011536',         label: t.xls_sched_start },
            { key: 'boloDaricxvaDate',          label: t.xls_sched_end },
            { key: '',                          label: t.xls_buyer_type },
            { key: 'OWNER_CONTACT_PN',          label: t.xls_id_num },
            { key: 'OWNER_CONTACT_CITIZENSHIP', label: t.xls_citizen },
            { key: 'OWNER_CONTACT_PHONE',       label: t.xls_phone },
            { key: 'prodTypeNew',               label: t.xls_re_type },
            { key: 'UF_CRM_1761658608306',      label: t.xls_area },
            { key: 'UF_CRM_1766736693236',      label: t.xls_building },
            { key: 'UF_CRM_1766560177934',      label: t.xls_block },
            { key: 'UF_CRM_1761658577987',      label: t.xls_floor },
            { key: 'UF_CRM_1761658559005',      label: t.xls_apt },
            { key: 'UF_CRM_1761658503260',      label: t.xls_price_sqm },
            { key: 'OPPORTUNITY',               label: t.xls_total_price },
            { key: 'jamuriGadaxdaUpToToday',    label: t.xls_paid },
            { key: 'paidPercentage',            label: t.xls_paid_pct },
            { key: 'remainedAmount',            label: t.xls_remaining },
            { key: 'debt',                      label: t.xls_debt },
            { key: 'overdueDays',               label: t.xls_overdue },
            { key: '',                          label: t.xls_status },
        ];

        const rows = dealsData.filter(function(p) { return p.prodTypeNew; }).map(function(p) {
            const row = {};
            fields.forEach(function(f) {
                if (p[f.key] === "Flat") {
                    row[f.label] = (p["Bedrooms"] || "") + " Rooms Studio";
                } else {
                    row[f.label] = p[f.key] ?? '';
                }
            });
            return row;
        });

        const ws2 = XLSX.utils.json_to_sheet(rows, { header: fields.map(function(f) { return f.label; }) });
        ws2['!cols'] = fields.map(function(f) { return { wch: Math.max(f.label.length, 14) }; });
        XLSX.utils.book_append_sheet(wb, ws2, 'Debt');

        const today = new Date().toISOString().slice(0, 10);
        XLSX.writeFile(wb, 'debt_report_' + today + '.xlsx');
    }
</script>