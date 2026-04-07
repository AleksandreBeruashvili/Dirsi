<?
ob_start();
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CJSCore::Init(array("jquery"));

$APPLICATION->SetTitle("Sold Report");

// ------------------------------FUNCTIONS---------------------------------
function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getDealsByFilter($arFilter = array(), $arrSelect=array()) {
    $res = CCrmDeal::GetListEx(array("ID" => "ASC"), $arFilter, $arrSelect);

    $resArr = array();
    while($arDeal = $res->Fetch()){
        $resArr[$arDeal["ID"]] = $arDeal;
    }
    return $resArr;
}

function getStageChangesForMultipleDeals($dealIds) {
    if (empty($dealIds) || !is_array($dealIds)) return false;

    $cache = Bitrix\Main\Data\Cache::createInstance();
    $cacheKey = 'deal_stage_changes_bulk_' . md5(implode(',', $dealIds));
    $cacheTtl = 900;

    if ($cache->initCache($cacheTtl, $cacheKey)) {
        return $cache->getVars();
    }

    $arFilter = [
            'ENTITY_TYPE' => \CCrmOwnerType::DealName,
            'ENTITY_ID' => $dealIds,
            'ENTITY_FIELD' => 'STAGE_ID',
    ];
    $arSelect = ['ID', 'EVENT_NAME', 'DATE_CREATE', 'USER_ID', 'ENTITY_ID', 'ENTITY_FIELDS', 'EVENT_TEXT_1', 'EVENT_TEXT_2'];
    $res = CCrmEvent::GetList(['DATE_CREATE' => 'ASC'], $arFilter, false, false, $arSelect);

    $arStageChanges = [];
    while ($arEvent = $res->Fetch()) {
        $dealId = $arEvent['ENTITY_ID'];
        $arStageChanges[$dealId][] = $arEvent;
    }

    if ($cache->startDataCache()) {
        $cache->endDataCache($arStageChanges);
    }

    return !empty($arStageChanges) ? $arStageChanges : false;
}

function getCIBlockElementsByFilter($arFilter = array())
{
    $arElements = array();
    $arSelect = array();
    $res = CIBlockElement::GetList(array(), $arFilter, false, array("nPageSize" => 999), $arSelect);
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach ($arFilds as $key => $arFild)
            $arPushs[$key] = $arFild;
        foreach ($arProps as $key => $arProp)
            $arPushs[$key] = $arProp["VALUE"];
        array_push($arElements, $arPushs);
    }
    return $arElements;
}

function getCrmSources() {
    $sources = [];
    $res = CCrmStatus::GetList([], ['ENTITY_ID' => 'SOURCE']);
    while ($item = $res->Fetch()) {
        $sources[$item['STATUS_ID']] = $item['NAME'];
    }
    return $sources;
}

// Helper: parse Bitrix DATE_CREATE
function parseBitrixDate($str) {
    if (empty($str)) return null;
    $d = DateTime::createFromFormat('d.m.Y H:i:s', $str);
    if (!$d) $d = DateTime::createFromFormat('d.m.Y', $str);
    if (!$d) $d = DateTime::createFromFormat('d/m/Y H:i:s', $str);
    if (!$d) $d = DateTime::createFromFormat('d/m/Y', $str);
    return $d ?: null;
}

$sources = getCrmSources();

// ------------------------------MAIN CODE---------------------------------

$displayDateFrom   = isset($_GET['date_from'])   ? trim($_GET['date_from'])   : '';
$displayDateTo     = isset($_GET['date_to'])     ? trim($_GET['date_to'])     : '';
$filterResponsible = isset($_GET['responsible']) ? trim($_GET['responsible']) : '';
$filterSource      = isset($_GET['source'])      ? trim($_GET['source'])      : '';

$filterDateFrom = '';
$filterDateTo   = '';

if ($displayDateFrom !== '') {
    $dateObj = DateTime::createFromFormat('Y-m-d', $displayDateFrom);
    if ($dateObj) $filterDateFrom = $dateObj->format('d/m/Y');
}
if ($displayDateTo !== '') {
    $dateObj = DateTime::createFromFormat('Y-m-d', $displayDateTo);
    if ($dateObj) $filterDateTo = $dateObj->format('d/m/Y');
}

$sourcesMap = [
        "Meta"     => ["UC_GWOB5R", "5|FACEBOOK", "REPEAT_SALE", "RECOMMENDATION", "WEB", "EMAIL"],
        "Other"    => ["UC_MF3UL1", "UC_I4GPI2", "UC_33R68T", "UC_BXC6ET", "6", "RC_GENERATOR", "PARTNER", "CALL", "UC_8EMZX2", "WZ690d6450-95bd-4422-90db-a5abd8f885d0"],
        "Bank"     => ["UC_MTQVO0", "UC_6XZU1C"],
        "Old Base" => ["UC_NGXD08", "UC_L7ERUF"],
        "Broker"   => ["UC_VXLB3C", "UC_8AFO20", "UC_M4NTDI", "UC_3KD0VI"],
        "Ads"      => ["UC_K8MG32", "UC_AIDAQM", "UC_P2OOT2", "UC_TNUYXL", "UC_CD0QF5", "UC_W5U32M", "UC_A261E5", "BOOKING", "STORE", "CALLBACK", "WEBFORM", "TRADE_SHOW"]
];

$sourcesList = array_combine(array_keys($sourcesMap), array_keys($sourcesMap));

$blockFilter       = ["IBLOCK_ID" => 36];
$allMarketingCosts = getCIBlockElementsByFilter($blockFilter);

$marketingCosts    = [];
$sourcesFilterList = [];
$budgetsPerSource  = [];
$totalBudget       = 0;

foreach ($allMarketingCosts as $cost) {
    if (empty($cost['FROM_DATE']) || empty($cost['TO_DATE'])) continue;

    $costFromDate = DateTime::createFromFormat('d/m/Y', $cost['FROM_DATE']);
    $costToDate   = DateTime::createFromFormat('d/m/Y', $cost['TO_DATE']);
    if (!$costFromDate || !$costToDate) continue;

    $includeElement = true;

    if (!empty($filterDateFrom)) {
        $filterFromDateTime = DateTime::createFromFormat('d/m/Y', $filterDateFrom);
        if ($costToDate < $filterFromDateTime) $includeElement = false;
    }
    if (!empty($filterDateTo) && $includeElement) {
        $filterToDateTime = DateTime::createFromFormat('d/m/Y', $filterDateTo);
        if ($costFromDate > $filterToDateTime) $includeElement = false;
    }
    if (!empty($filterSource) && $includeElement) {
        if ($cost["SOURCE"] !== $filterSource) $includeElement = false;
    }

    if ($includeElement) {
        $marketingCosts[] = $cost;
        if (!in_array($cost["SOURCE"], $sourcesFilterList)) {
            $sourcesFilterList[] = $cost["SOURCE"];
        }
        if (!isset($budgetsPerSource[$cost["SOURCE"]])) {
            $budgetsPerSource[$cost["SOURCE"]] = 0;
        }
        $totalBudget += $cost["BUDGET"];
        $budgetsPerSource[$cost["SOURCE"]] += $cost["BUDGET"];
    }
}

// Deal date range = min/max of all marketing cost periods
$allCostFromDates = [];
$allCostToDates   = [];
foreach ($marketingCosts as $cost) {
    $f = DateTime::createFromFormat('d/m/Y', $cost['FROM_DATE']);
    $t = DateTime::createFromFormat('d/m/Y', $cost['TO_DATE']);
    if ($f) $allCostFromDates[] = $f;
    if ($t) $allCostToDates[]   = $t;
}
$dealDateFrom = !empty($allCostFromDates) ? min($allCostFromDates)->format('d/m/Y') : '';
$dealDateTo   = !empty($allCostToDates)   ? max($allCostToDates)->format('d/m/Y')   : '';

// Source IDs to query
$sourceIdsToQuery = [];
if (!empty($filterSource)) {
    $sourceIdsToQuery = $sourcesMap[$filterSource] ?? [];
} 
else {
    $sourceIdsToQuery = array_unique(array_merge(...array_values($sourcesMap)));
}

// Build deal filter
$dealFilter = [];
if (!empty($filterDateFrom))      $dealFilter[">=DATE_CREATE"]  = $filterDateFrom;
if (!empty($filterDateTo))        $dealFilter["<=DATE_CREATE"]  = $filterDateTo;
if (!empty($sourceIdsToQuery))  $dealFilter["SOURCE_ID"]      = $sourceIdsToQuery;
if (!empty($filterResponsible)) $dealFilter["ASSIGNED_BY_ID"] = $filterResponsible;

$deals = getDealsByFilter($dealFilter, [
        "ID",
        "SOURCE_ID",
        "STAGE_ID",
        "ASSIGNED_BY_ID",
        "DATE_CREATE",
        "UF_CRM_1761575156657"
]);

// Responsible users dropdown
$responsibles = [];
foreach ($deals as $deal) {
    if (!empty($deal["ASSIGNED_BY_ID"]) && !isset($responsibles[$deal["ASSIGNED_BY_ID"]])) {
        $user = CUser::GetByID($deal["ASSIGNED_BY_ID"])->Fetch();
        if ($user) {
            $userName = trim($user["NAME"] . " " . $user["LAST_NAME"]);
            $responsibles[$deal["ASSIGNED_BY_ID"]] = !empty($userName) ? $userName : "User #" . $deal["ASSIGNED_BY_ID"];
        }
    }
}

$dealIds        = array_keys($deals);
$dealsHistories = getStageChangesForMultipleDeals($dealIds);

// ----------------------------- STAGE DEFINITIONS -----------------------
$qualifiedLeadStagesNotCounted = ["New Lead", "Processed", "Sell", "Lost Negotiation"];
$allowedLostReasons            = ["83", "203", "42", "205", "43", "206", "207", "208", "209", "210", "211", "212"];
$nonQualifiedLeadReasons       = ["42", "40", "44", "41"];
$wonStageIds                   = ["WON"];

// ----------------------------- DEAL BUCKETS ----------------------------
$lostDeals            = [];
$meetingFinishedDeals = [];
$meetingAgreedDeals   = [];
$qualifiedDeals       = [];
$nonQualifiedDeals    = [];
$wonDeals = array_keys(array_filter($deals, fn($n) => $n["STAGE_ID"] === 'WON'));

foreach ($dealsHistories as $dealId => $events) {
    if (!isset($deals[$dealId])) continue;

    $qualified       = false;
    $lost            = false;
    $meetingFinished = false;
    $meetingAgreed   = false;
    $nonQualified    = false;
    
    foreach ($events as $event) {

        // Lost Negotiation
        if ($event["EVENT_TEXT_2"] === "Lost Negotiation" && !$lost) {
            if (in_array($deals[$dealId]["UF_CRM_1761575156657"], $allowedLostReasons)) {
                $lost = true;
                $lostDeals[] = $dealId;
            }
            if (in_array($deals[$dealId]["UF_CRM_1761575156657"], $nonQualifiedLeadReasons) && !$nonQualified) {
                $nonQualified = true;
                $nonQualifiedDeals[] = $dealId;
            }
        }

        // Qualified
        if (!in_array($event["EVENT_TEXT_2"], $qualifiedLeadStagesNotCounted) && !$qualified) {
            $qualified = true;
            $qualifiedDeals[] = $dealId;
        }

        // Meeting Agreed
        if ($event["EVENT_TEXT_2"] === "Meeting agreed" && !$meetingAgreed) {
            $meetingAgreed = true;
            $meetingAgreedDeals[] = $dealId;
        }

        // Meeting Finished
        if ($event["EVENT_TEXT_2"] === "Meeting finished" && !$meetingFinished) {
            $meetingFinished = true;
            $meetingFinishedDeals[] = $dealId;
        }
    }
}

// ----------------------------- GLOBAL COUNTS ---------------------------
$numOfTotalDeals            = count($deals);
$numOfQualifiedDeals        = count($qualifiedDeals);
$numOfNonQualifiedDeals     = count($nonQualifiedDeals);
$numOfMeetingAgreedDeals    = count($meetingAgreedDeals);
$numOfMeetingsFinishedDeals = count($meetingFinishedDeals);
$numOfWonDeals              = count($wonDeals);
$numOfLostDeals             = count($lostDeals);

// ----------------------------- CONVERSIONS -----------------------------
$leadToQL                       = 0;
$leadToWon                      = 0;
$leadToMeetingAgreed            = 0;
$meetingAgreedToMeetingFinished = 0;
$meetingFinishedToWon           = 0;

if ($numOfTotalDeals !== 0) {
    $leadToQL            = $numOfQualifiedDeals     / $numOfTotalDeals * 100;
    $leadToWon           = $numOfWonDeals           / $numOfTotalDeals * 100;
    $leadToMeetingAgreed = $numOfMeetingAgreedDeals / $numOfTotalDeals * 100;
}
if ($numOfMeetingAgreedDeals !== 0) {
    $meetingAgreedToMeetingFinished = $numOfMeetingsFinishedDeals / $numOfMeetingAgreedDeals * 100;
}
if ($numOfMeetingsFinishedDeals !== 0) {
    $meetingFinishedToWon = $numOfWonDeals / $numOfMeetingsFinishedDeals * 100;
}

// ----------------------------- STAT CARDS ------------------------------
$statCards = [
        "totalBudget"          => $totalBudget,
        "totalLeads"           => $numOfTotalDeals,
        "qualifiedLeads"       => $numOfQualifiedDeals,
        "nonQualifiedLeads"    => $numOfNonQualifiedDeals,
        "meetingAgreedLeads"   => $numOfMeetingAgreedDeals,
        "meetingFinishedLeads" => $numOfMeetingsFinishedDeals,
        "wonDeals"             => $numOfWonDeals,
        "lostDeals"            => $numOfLostDeals,
];

$conversionCards = [
        "leadToQL"                       => $leadToQL,
        "leadToMeetingAgreed"            => $leadToMeetingAgreed,
        "meetingAgreedToMeetingFinished" => $meetingAgreedToMeetingFinished,
        "meetingFinishedToWon"           => $meetingFinishedToWon,
        "leadToWon"                      => $leadToWon,
];

// ----------------------------- TABLE: one row per marketing cost --------
$resArrayForTable = [];

foreach ($marketingCosts as $cost) {
    $groupName    = $cost["SOURCE"];
    $campaignName = $cost["NAME"];
    $budget       = (float)$cost["BUDGET"];
    $fromDate     = $cost["FROM_DATE"];
    $toDate       = $cost["TO_DATE"];
    $periodLabel  = $fromDate . ' - ' . $toDate;

    $costFrom = DateTime::createFromFormat('d/m/Y', $fromDate);
    $costTo   = DateTime::createFromFormat('d/m/Y', $toDate);

    $groupSourceIds = $sourcesMap[$groupName] ?? [];

    $rowNewLeads             = 0;
    $rowQualifiedLeads       = 0;
    $rowMeetingAgreedLeads   = 0;
    $rowMeetingFinishedLeads = 0;
    $rowWonLeads             = 0;

    foreach ($deals as $dealId => $deal) {
        if (!in_array($deal["SOURCE_ID"], $groupSourceIds)) continue;
        $dealDate = parseBitrixDate($deal["DATE_CREATE"]);
        if ($dealDate && $costFrom && $costTo && ($dealDate < $costFrom || $dealDate > $costTo)) continue;
        $rowNewLeads++;
    }
    foreach ($qualifiedDeals as $id) {
        if (!in_array($deals[$id]["SOURCE_ID"], $groupSourceIds)) continue;
        $dealDate = parseBitrixDate($deals[$id]["DATE_CREATE"]);
        if ($dealDate && $costFrom && $costTo && ($dealDate < $costFrom || $dealDate > $costTo)) continue;
        $rowQualifiedLeads++;
    }
    foreach ($meetingAgreedDeals as $id) {
        if (!in_array($deals[$id]["SOURCE_ID"], $groupSourceIds)) continue;
        $dealDate = parseBitrixDate($deals[$id]["DATE_CREATE"]);
        if ($dealDate && $costFrom && $costTo && ($dealDate < $costFrom || $dealDate > $costTo)) continue;
        $rowMeetingAgreedLeads++;
    }
    foreach ($meetingFinishedDeals as $id) {
        if (!in_array($deals[$id]["SOURCE_ID"], $groupSourceIds)) continue;
        $dealDate = parseBitrixDate($deals[$id]["DATE_CREATE"]);
        if ($dealDate && $costFrom && $costTo && ($dealDate < $costFrom || $dealDate > $costTo)) continue;
        $rowMeetingFinishedLeads++;
    }
    foreach ($wonDeals as $id) {
        if (!in_array($deals[$id]["SOURCE_ID"], $groupSourceIds)) continue;
        $dealDate = parseBitrixDate($deals[$id]["DATE_CREATE"]);
        if ($dealDate && $costFrom && $costTo && ($dealDate < $costFrom || $dealDate > $costTo)) continue;
        $rowWonLeads++;
    }

    $resArrayForTable[] = [
            "periodLabel"         => $periodLabel,
            "source"              => $groupName,
            "utmS"                => $campaignName,
            "totalCost"           => $budget,
            "newLeads"            => $rowNewLeads,
            "qualifiedLeads"      => $rowQualifiedLeads,
            "meetingAgreedLeads"  => $rowMeetingAgreedLeads,
            "meetingFinishedLeads"=> $rowMeetingFinishedLeads,
            "wonLeads"            => $rowWonLeads,
            "CPL"  => $rowNewLeads             > 0 ? $budget / $rowNewLeads             : 0,
            "CPQL" => $rowQualifiedLeads       > 0 ? $budget / $rowQualifiedLeads       : 0,
            "CPMA" => $rowMeetingAgreedLeads   > 0 ? $budget / $rowMeetingAgreedLeads   : 0,
            "CPMF" => $rowMeetingFinishedLeads > 0 ? $budget / $rowMeetingFinishedLeads : 0,
            "CPW"  => $rowWonLeads             > 0 ? $budget / $rowWonLeads             : 0,
    ];
}

ob_end_clean();
?>

<style>
    * { box-sizing: border-box; }

    body {
        background: #f5f7fa;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }

    .report-section {
        margin: 30px auto;
        max-width: 1500px;
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    .section-title {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px 30px;
        font-size: 24px;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    /* ===== FILTERS ===== */
    .filter-section { background: #f8f9fa; padding: 25px 30px; border-bottom: 1px solid #dee2e6; }
    .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
    .filter-group { display: flex; flex-direction: column; }
    .filter-label { font-size: 13px; font-weight: 600; color: #495057; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
    .filter-input, .filter-select { padding: 10px 14px; border: 2px solid #dee2e6; border-radius: 6px; font-size: 14px; color: #495057; background: white; transition: all 0.2s ease; }
    .filter-input:focus, .filter-select:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
    .filter-buttons { display: flex; gap: 10px; margin-top: 20px; }
    .btn { padding: 12px 24px; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; text-transform: uppercase; letter-spacing: 0.5px; }
    .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102,126,234,0.4); }
    .btn-secondary { background: #6c757d; color: white; }
    .btn-secondary:hover { background: #5a6268; }

    /* ===== STAT CARDS ===== */
    .stats-section { padding: 30px; background: #ffffff; border-bottom: 1px solid #e9ecef; }
    .stats-section-label { font-size: 13px; font-weight: 700; color: #764ba2; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #e9ecef; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 16px; margin-bottom: 30px; }
    .stat-card { background: #f8f9fa; border-radius: 12px; padding: 22px 16px; text-align: center; transition: all 0.3s ease; border: 2px solid transparent; }
    .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 16px rgba(0,0,0,0.1); border-color: #667eea; }
    .stat-value { font-size: 28px; font-weight: 700; margin-bottom: 8px; line-height: 1.2; }
    .stat-label { font-size: 12px; color: #6c757d; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
    .color-budget  { color: #667eea; }
    .color-total   { color: #4a5568; }
    .color-ql      { color: #48bb78; }
    .color-nql     { color: #ed8936; }
    .color-agreed  { color: #4299e1; }
    .color-finished{ color: #38b2ac; }
    .color-won     { color: #38b2ac; }
    .color-lost    { color: #f56565; }

    /* Conversion cards */
    .conversion-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
    .conv-card { background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); border: 1px solid #e9ecef; border-radius: 10px; padding: 18px 16px; text-align: center; transition: all 0.3s ease; }
    .conv-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .conv-value { font-size: 26px; font-weight: 700; color: #667eea; margin-bottom: 6px; }
    .conv-arrow { font-size: 11px; color: #adb5bd; margin: 4px 0; }
    .conv-label { font-size: 11px; color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; }

    /* ===== TABLE ===== */
    .table-wrapper { overflow-x: auto; padding: 0; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .data-table thead { background: #f8f9fa; position: sticky; top: 0; z-index: 10; }
    .data-table th { padding: 16px 12px; text-align: left; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6; white-space: nowrap; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
    .data-table th.col-new    { background: #fff8e1; }
    .data-table th.col-agreed { background: #e3f2fd; }
    .data-table th.col-won    { background: #e8f5e9; }
    .data-table tbody tr { transition: all 0.2s ease; }
    .data-table tbody tr:hover { background-color: #f8f9fa; }
    .data-table td { padding: 14px 12px; border-bottom: 1px solid #e9ecef; color: #212529; }
    .metric-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
    .badge-success { background: #d4edda; color: #155724; }
    .badge-warning { background: #fff3cd; color: #856404; }
    .badge-info    { background: #cce5ff; color: #004085; }
    .utm-code { font-family: monospace; font-size: 11px; background: #e9ecef; padding: 2px 6px; border-radius: 4px; color: #495057; }

    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .conversion-grid { grid-template-columns: repeat(2, 1fr); }
        .filter-grid { grid-template-columns: 1fr; }
    }

    .btn-export { background: linear-gradient(135deg, #1D6F42 0%, #2e7d32 100%); color: white; }
    .btn-export:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(29,111,66,0.4); }

    /* ===== CLICKABLE STAT CARDS ===== */
    .stat-card.clickable { cursor: pointer; }
    .stat-card.clickable:hover { border-color: #667eea; box-shadow: 0 8px 20px rgba(102,126,234,0.2); }
    .stat-card.clickable::after { content: '🔍'; font-size: 11px; display: block; margin-top: 6px; opacity: 0.4; transition: opacity 0.2s; }
    .stat-card.clickable:hover::after { opacity: 1; }

    /* ===== MODAL ===== */
    .modal-overlay {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,0.55); backdrop-filter: blur(3px);
        z-index: 9999; align-items: center; justify-content: center; padding: 20px;
    }
    .modal-overlay.open { display: flex; }
    .modal-box {
        background: #fff; border-radius: 14px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.25);
        width: 100%; max-width: 1000px; max-height: 85vh;
        display: flex; flex-direction: column;
        animation: modalIn 0.25s cubic-bezier(0.34,1.56,0.64,1);
    }
    @keyframes modalIn {
        from { opacity: 0; transform: scale(0.92) translateY(20px); }
        to   { opacity: 1; transform: scale(1) translateY(0); }
    }
    .modal-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 20px 28px; border-bottom: 1px solid #e9ecef;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 14px 14px 0 0; color: white;
    }
    .modal-title { font-size: 18px; font-weight: 700; letter-spacing: 0.3px; }
    .modal-count { font-size: 13px; opacity: 0.85; margin-top: 2px; }
    .modal-close {
        background: rgba(255,255,255,0.2); border: none; border-radius: 8px;
        color: white; font-size: 20px; width: 36px; height: 36px;
        cursor: pointer; transition: background 0.2s;
        display: flex; align-items: center; justify-content: center;
    }
    .modal-close:hover { background: rgba(255,255,255,0.35); }
    .modal-body { overflow-y: auto; flex: 1; }
    .modal-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .modal-table thead th {
        position: sticky; top: 0; background: #f8f9fa;
        padding: 12px 16px; text-align: left; font-size: 11px;
        font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px;
        color: #495057; border-bottom: 2px solid #dee2e6; white-space: nowrap;
    }
    .modal-table tbody td { padding: 11px 16px; border-bottom: 1px solid #f1f3f5; color: #212529; }
    .modal-table tbody tr:hover { background: #f8f9fa; }
    .modal-table tbody tr:last-child td { border-bottom: none; }
    .stage-pill {
        display: inline-block; padding: 3px 10px; border-radius: 20px;
        font-size: 11px; font-weight: 600; background: #e9ecef; color: #495057;
    }
    .modal-empty { text-align: center; padding: 50px; color: #adb5bd; font-size: 15px; }
</style>

<div class="report-section">
    <div class="section-title">📊 Marketing Performance Dashboard</div>

    <!-- ===== FILTERS ===== -->
    <div class="filter-section">
        <form method="GET" action="">
            <div class="filter-grid">
                <div class="filter-group">
                    <label class="filter-label">📅 Date From</label>
                    <input type="date" name="date_from" class="filter-input" value="<?= htmlspecialchars($displayDateFrom) ?>">
                </div>
                <div class="filter-group">
                    <label class="filter-label">📅 Date To</label>
                    <input type="date" name="date_to" class="filter-input" value="<?= htmlspecialchars($displayDateTo) ?>">
                </div>
                <div class="filter-group">
                    <label class="filter-label">👤 Responsible</label>
                    <select name="responsible" class="filter-select">
                        <option value="">All Responsibles</option>
                        <?php foreach ($responsibles as $userId => $userName): ?>
                            <option value="<?= htmlspecialchars($userId) ?>" <?= $filterResponsible == $userId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($userName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">📍 Source</label>
                    <select name="source" class="filter-select">
                        <option value="">All Sources</option>
                        <?php foreach ($sourcesList as $sourceId => $sourceName): ?>
                            <option value="<?= htmlspecialchars($sourceId) ?>" <?= $filterSource == $sourceId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sourceName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="filter-buttons">
                <button type="submit" class="btn btn-primary">🔍 Apply Filters</button>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='<?= $_SERVER['PHP_SELF'] ?>'">🔄 Reset</button>
                <button type="button" class="btn btn-export" onclick="exportToExcel()">📥 Export to Excel</button>
            </div>
        </form>
    </div>

    <!-- ===== STAT CARDS ===== -->
    <div class="stats-section">
        <div class="stats-section-label">📈 Volume Metrics</div>
        <div class="stats-grid">
            <div class="stat-card clickable" onclick="openModal('totalBudget')"><div class="stat-value color-budget">$<?= number_format($statCards['totalBudget'], 2) ?></div><div class="stat-label">Total Budget</div></div>
            <div class="stat-card clickable" onclick="openModal('totalLeads')"><div class="stat-value color-total"><?= $statCards['totalLeads'] ?></div><div class="stat-label">Total Leads</div></div>
            <div class="stat-card clickable" onclick="openModal('qualifiedLeads')"><div class="stat-value color-ql"><?= $statCards['qualifiedLeads'] ?></div><div class="stat-label">QL</div></div>
            <div class="stat-card clickable" onclick="openModal('nonQualifiedLeads')"><div class="stat-value color-nql"><?= $statCards['nonQualifiedLeads'] ?></div><div class="stat-label">NQL</div></div>
            <div class="stat-card clickable" onclick="openModal('meetingAgreedLeads')"><div class="stat-value color-agreed"><?= $statCards['meetingAgreedLeads'] ?></div><div class="stat-label">Meeting Scheduled</div></div>
            <div class="stat-card clickable" onclick="openModal('meetingFinishedLeads')"><div class="stat-value color-finished"><?= $statCards['meetingFinishedLeads'] ?></div><div class="stat-label">Meeting Completed</div></div>
            <div class="stat-card clickable" onclick="openModal('wonDeals')"><div class="stat-value color-won"><?= $statCards['wonDeals'] ?></div><div class="stat-label">Won Deals</div></div>
            <div class="stat-card clickable" onclick="openModal('lostDeals')"><div class="stat-value color-lost"><?= $statCards['lostDeals'] ?></div><div class="stat-label">Junk Deals</div></div>
        </div>

        <div class="stats-section-label">🔄 Conversion Rates</div>
        <div class="conversion-grid">
            <div class="conv-card"><div class="conv-value"><?= number_format($conversionCards['leadToQL'], 1) ?>%</div><div class="conv-arrow">Total Leads → QL</div><div class="conv-label">Conversion Rate for QL</div></div>
            <div class="conv-card"><div class="conv-value"><?= number_format($conversionCards['leadToMeetingAgreed'], 1) ?>%</div><div class="conv-arrow">Total Leads → Meeting Scheduled</div><div class="conv-label">Conversion Rate for Meeting Scheduled</div></div>
            <div class="conv-card"><div class="conv-value"><?= number_format($conversionCards['meetingAgreedToMeetingFinished'], 1) ?>%</div><div class="conv-arrow">Meeting Scheduled → Meeting Completed</div><div class="conv-label">Conversion Rate for Meeting Completed</div></div>
            <div class="conv-card"><div class="conv-value"><?= number_format($conversionCards['meetingFinishedToWon'], 1) ?>%</div><div class="conv-arrow">Meeting Completed → Won</div><div class="conv-label">Conversion Rate for Won</div></div>
            <div class="conv-card"><div class="conv-value"><?= number_format($conversionCards['leadToWon'], 1) ?>%</div><div class="conv-arrow">Total Leads → Won</div><div class="conv-label">Overall Conversion Rate</div></div>
        </div>
    </div>

    <!-- ===== TABLE ===== -->
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
            <tr>
                <th>Campaign Period</th>
                <th>Source</th>
                <th>UTM_S</th>
                <th>Total Cost</th>
                <th class="col-new">New Leads</th>
                <th>CPL</th>
                <th>Qualified Leads</th>
                <th>CPQL</th>
                <th class="col-agreed">Meeting Agreed Leads</th>
                <th class="col-agreed">CP Meeting Agreed</th>
                <th>Meeting Finished Leads</th>
                <th>CP Meeting Finished</th>
                <th class="col-won">Won Leads</th>
                <th class="col-won">CPW</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($resArrayForTable)): ?>
                <?php foreach ($resArrayForTable as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['periodLabel']) ?></td>
                        <td><strong><?= htmlspecialchars($row['source']) ?></strong></td>
                        <td><code class="utm-code"><?= htmlspecialchars($row['utmS']) ?></code></td>
                        <td>$<?= number_format($row['totalCost'], 2) ?></td>
                        <td><span class="metric-badge badge-warning"><?= $row['newLeads'] ?></span></td>
                        <td>$<?= number_format($row['CPL'], 2) ?></td>
                        <td><span class="metric-badge badge-success"><?= $row['qualifiedLeads'] ?></span></td>
                        <td>$<?= number_format($row['CPQL'], 2) ?></td>
                        <td><span class="metric-badge badge-info"><?= $row['meetingAgreedLeads'] ?></span></td>
                        <td>$<?= number_format($row['CPMA'], 2) ?></td>
                        <td><?= $row['meetingFinishedLeads'] ?></td>
                        <td>$<?= number_format($row['CPMF'], 2) ?></td>
                        <td><span class="metric-badge badge-success"><?= $row['wonLeads'] ?></span></td>
                        <td>$<?= number_format($row['CPW'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="14" style="text-align:center; padding:40px; color:#6c757d;">
                        No data available for the selected filters
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===== MODAL ===== -->
<div class="modal-overlay" id="dealsModal" onclick="if(event.target===this) closeModal()">
    <div class="modal-box">
        <div class="modal-header">
            <div>
                <div class="modal-title" id="modalTitle">Deals</div>
                <div class="modal-count" id="modalCount"></div>
            </div>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body">
            <table class="modal-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Deal ID</th>
                        <th>Date Created</th>
                        <th>Source</th>
                        <th>Stage</th>
                        <th>Responsible</th>
                    </tr>
                </thead>
                <tbody id="modalTableBody"></tbody>
            </table>
            <div class="modal-empty" id="modalEmpty" style="display:none">No deals found</div>
        </div>
    </div>
</div>

<?php
// Build deal detail map for modal
$dealDetailsForModal = [];
foreach ($deals as $dealId => $deal) {
    $user = CUser::GetByID($deal["ASSIGNED_BY_ID"])->Fetch();
    $userName = $user ? trim(($user["NAME"] ?? '') . ' ' . ($user["LAST_NAME"] ?? '')) : '';
    $dealDetailsForModal[$dealId] = [
        'id'          => $dealId,
        'date'        => $deal['DATE_CREATE'] ?? '',
        'source_id'   => $deal['SOURCE_ID']   ?? '',
        'source_name' => $sources[$deal['SOURCE_ID']] ?? ($deal['SOURCE_ID'] ?? ''),
        'stage'       => $deal['STAGE_ID']    ?? '',
        'responsible' => $userName,
    ];
}
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
    function exportToExcel() {
        var table = document.querySelector('.data-table');
        var wb = XLSX.utils.book_new();

        var rows = [];
        var headers = [];
        table.querySelectorAll('thead th').forEach(function(th) {
            headers.push(th.innerText.trim());
        });
        rows.push(headers);

        // Columns that should always stay as text (0-indexed)
        var textOnlyCols = [0, 1, 2]; // Campaign Period, Source, UTM_S

        table.querySelectorAll('tbody tr').forEach(function(tr) {
            var cells = tr.querySelectorAll('td');
            if (cells.length < 2) return;
            var row = [];
            cells.forEach(function(td, i) {
                var text = td.innerText.trim();
                if (textOnlyCols.indexOf(i) !== -1) {
                    row.push(text); // always string
                } else {
                    var num = parseFloat(text.replace(/[$,]/g, ''));
                    row.push(isNaN(num) || text === '' ? text : num);
                }
            });
            rows.push(row);
        });

        var ws = XLSX.utils.aoa_to_sheet(rows);

        ws['!cols'] = [
            {wch: 24}, {wch: 12}, {wch: 28}, {wch: 12},
            {wch: 12}, {wch: 10}, {wch: 16}, {wch: 10},
            {wch: 22}, {wch: 20}, {wch: 24}, {wch: 22},
            {wch: 12}, {wch: 10},
        ];

        XLSX.utils.book_append_sheet(wb, ws, 'Marketing Report');

        var today = new Date();
        var dateStr = today.getFullYear() + '-'
            + String(today.getMonth()+1).padStart(2,'0') + '-'
            + String(today.getDate()).padStart(2,'0');
        XLSX.writeFile(wb, 'marketing_report_' + dateStr + '.xlsx');
    }

    // ===== MODAL DATA =====
    const dealDetails    = <?= json_encode($dealDetailsForModal) ?>;
    const sourcesMapJs   = <?= json_encode($sources) ?>;

    const buckets = {
        totalBudget:          <?= json_encode(array_keys($deals)) ?>,
        totalLeads:           <?= json_encode(array_keys($deals)) ?>,
        qualifiedLeads:       <?= json_encode($qualifiedDeals) ?>,
        nonQualifiedLeads:    <?= json_encode($nonQualifiedDeals) ?>,
        meetingAgreedLeads:   <?= json_encode($meetingAgreedDeals) ?>,
        meetingFinishedLeads: <?= json_encode($meetingFinishedDeals) ?>,
        wonDeals:             <?= json_encode($wonDeals) ?>,
        lostDeals:            <?= json_encode($lostDeals) ?>,
    };

    const bucketLabels = {
        totalBudget:          'Total Budget — All Deals',
        totalLeads:           'Total Leads',
        qualifiedLeads:       'Qualified Leads (QL)',
        nonQualifiedLeads:    'Non-Qualified Leads (NQL)',
        meetingAgreedLeads:   'Meeting Scheduled',
        meetingFinishedLeads: 'Meeting Completed',
        wonDeals:             'Won Deals',
        lostDeals:            'Junk Deals',
    };

    function openModal(bucketKey) {
        const ids    = buckets[bucketKey] || [];
        const label  = bucketLabels[bucketKey] || bucketKey;
        const tbody  = document.getElementById('modalTableBody');
        const empty  = document.getElementById('modalEmpty');

        document.getElementById('modalTitle').textContent = label;
        document.getElementById('modalCount').textContent = ids.length + ' deal(s)';
        tbody.innerHTML = '';

        if (ids.length === 0) {
            empty.style.display = 'block';
        } else {
            empty.style.display = 'none';
            ids.forEach(function(id, idx) {
                const d   = dealDetails[id];
                if (!d) return;
                const tr  = document.createElement('tr');
                tr.innerHTML =
                    '<td style="color:#adb5bd;font-size:11px">' + (idx + 1) + '</td>' +
                    '<td><a href="/crm/deal/details/' + d.id + '/" target="_blank" style="color:#667eea;font-weight:700;text-decoration:none;" onmouseover="this.style.textDecoration=\'underline\'" onmouseout="this.style.textDecoration=\'none\'">#' + d.id + '</a></td>' +
                    '<td>' + (d.date || '—') + '</td>' +
                    '<td>' + (d.source_name || d.source_id || '—') + '</td>' +
                    '<td><span class="stage-pill">' + (d.stage || '—') + '</span></td>' +
                    '<td>' + (d.responsible || '—') + '</td>';
                tbody.appendChild(tr);
            });
        }

        document.getElementById('dealsModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        document.getElementById('dealsModal').classList.remove('open');
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });


</script>