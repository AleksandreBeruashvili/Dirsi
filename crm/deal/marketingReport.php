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

// ------------------------------MAIN CODE---------------------------------

// Get filter values from GET parameters
$displayDateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$displayDateTo   = isset($_GET['date_to'])   ? trim($_GET['date_to'])   : '';
$filterResponsible = isset($_GET['responsible']) ? trim($_GET['responsible']) : '';
$filterSource = isset($_GET['source']) ? trim($_GET['source']) : '';

$filterDateFrom = '';
$filterDateTo = '';

if ($displayDateFrom !== '') {
    $dateObj = DateTime::createFromFormat('Y-m-d', $displayDateFrom);
    if ($dateObj) {
        $filterDateFrom = $dateObj->format('d/m/Y');
    }
}
if ($displayDateTo !== '') {
    $dateObj = DateTime::createFromFormat('Y-m-d', $displayDateTo);
    if ($dateObj) {
        $filterDateTo = $dateObj->format('d/m/Y');
    }
}

// Define sources map BEFORE using it
$sourcesMap = ["Meta" => ["WEB", "REPEAT_SALE", "5|FACEBOOK"],
               "Other" => ["CALL", "EMAIL", "6"],
               "Bank" => ["UC_MTQVO0"],
               "Old Base" => ["UC_NGXD08"]
                ];

// Populate sourcesList for dropdown (use keys from sourcesMap)
$sourcesList = array_combine(array_keys($sourcesMap), array_keys($sourcesMap));

$blockFilter = ["IBLOCK_ID" => 36];
$allMarketingCosts = getCIBlockElementsByFilter($blockFilter);

$marketingCosts = array();
$sourcesFilterList = array();

$budgetsPerSource = array();
$totalBudget = 0;
foreach ($allMarketingCosts as $cost) {
    if (empty($cost['FROM_DATE']) || empty($cost['TO_DATE'])) {
        continue;
    }
    
    $costFromDate = DateTime::createFromFormat('d/m/Y', $cost['FROM_DATE']);
    $costToDate = DateTime::createFromFormat('d/m/Y', $cost['TO_DATE']);
    
    if (!$costFromDate || !$costToDate) {
        continue;
    }
    
    $includeElement = true;
    
    if (!empty($filterDateFrom)) {
        $filterFromDateTime = DateTime::createFromFormat('d/m/Y', $filterDateFrom);
        if ($costToDate < $filterFromDateTime) {
            $includeElement = false;
        }
    }
    
    if (!empty($filterDateTo) && $includeElement) {
        $filterToDateTime = DateTime::createFromFormat('d/m/Y', $filterDateTo);
        if ($costFromDate > $filterToDateTime) {
            $includeElement = false;
        }
    }
    
    // Apply source filter to marketing costs
    if (!empty($filterSource) && $includeElement) {
        if ($cost["SOURCE"] !== $filterSource) {
            $includeElement = false;
        }
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

$kms = $sourcesFilterList;

// Determine which source IDs to query based on filter
$sourceIdsToQuery = [];
if (!empty($filterSource)) {
    // If source filter is set, only get IDs for that source
    $sourceIdsToQuery = $sourcesMap[$filterSource] ?? [];
} else {
    // Otherwise, get all source IDs from the filtered marketing costs sources
    $sourceIdsToQuery = array_unique(array_reduce($sourcesFilterList, function($carry, $source) use ($sourcesMap) {
        return array_merge($carry, $sourcesMap[$source] ?? []);
    }, []));
}

// Build deal filter
$dealFilter = [];
if (!empty($filterDateFrom)) {
    $dealFilter[">=DATE_CREATE"] = $filterDateFrom;
}
if (!empty($filterDateTo)) {
    $dealFilter["<=DATE_CREATE"] = $filterDateTo;
}
if (!empty($sourceIdsToQuery)) {
    $dealFilter["SOURCE_ID"] = $sourceIdsToQuery;
}
if (!empty($filterResponsible)) {
    $dealFilter["ASSIGNED_BY_ID"] = $filterResponsible;
}

$deals = getDealsByFilter($dealFilter, [
        "ID",
        "SOURCE_ID",
        "STAGE_ID",
        "ASSIGNED_BY_ID",
        "UF_CRM_1761575156657" //lost reason
    ]
);

// Collect unique responsible users for the dropdown
$responsibles = array();
foreach ($deals as $deal) {
    if (!empty($deal["ASSIGNED_BY_ID"]) && !isset($responsibles[$deal["ASSIGNED_BY_ID"]])) {
        // Get user name
        $user = CUser::GetByID($deal["ASSIGNED_BY_ID"])->Fetch();
        if ($user) {
            $userName = trim($user["NAME"] . " " . $user["LAST_NAME"]);
            $responsibles[$deal["ASSIGNED_BY_ID"]] = !empty($userName) ? $userName : "User #" . $deal["ASSIGNED_BY_ID"];
        }
    }
}

$dealIds = array_keys($deals);

$dealsHistories = getStageChangesForMultipleDeals($dealIds);

$lostDeals = [];
$meetingFinishedDeals = [];
$qualifiedDeals = [];
$nonQualifiedDeals = [];
$wonDeals = [];

$qualifiedLeadStagesNotCounted = ["New Lead", "Processed", "Sell", "Lost Negotiation"];
$allowedLostReasons = ["83", "203", "42", "205", "43", "206", "207", "208", "209", "210", "211", "212"];
$nonQualifiedLeadReasons = ["42", "40", "44", "41"];

foreach ($dealsHistories as $dealId => $events) {
    $qualified = false;
    $lost = false;
    $meetingFinished = false;
    $nonQualified = false;
    $won = false;

    foreach ($events as $event) {

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

        if (!in_array($event["EVENT_TEXT_2"], $qualifiedLeadStagesNotCounted) && !$qualified) {
            $qualified = true;
            $qualifiedDeals[] = $dealId;
        }

        if ($event["EVENT_TEXT_2"] === "Meeting finished" && !$meetingFinished) {
            $meetingFinished = true;
            $meetingFinishedDeals[] = $dealId;
        }

        if ($event["EVENT_TEXT_2"] === "Sell" && !$won) {
            $won = true;
            $wonDeals[] = $dealId;
        }
    }

}

$numOfTotalDeals = count($deals);
$numOfMeetingsFinishedDeals = count($meetingFinishedDeals);
$numOfQualifiedDeals = count($qualifiedDeals);
$numOfNonQualifiedDeals = count($nonQualifiedDeals);
$numOfWonDeals = count($wonDeals);
$numOfLostDeals = count($lostDeals);

$leadToQL = 0;
$leadToWon = 0;
$QLToMeeting = 0;
$meetingToWon = 0;

if ($numOfTotalDeals !== 0) {
    $leadToQL = $numOfQualifiedDeals / $numOfTotalDeals * 100;
    $leadToWon = $numOfWonDeals / $numOfTotalDeals * 100;
}

if ($numOfQualifiedDeals !== 0) {
    $QLToMeeting = $numOfMeetingsFinishedDeals / $numOfQualifiedDeals * 100;
}

if ($numOfMeetingsFinishedDeals !== 0) {
    $meetingToWon = $numOfWonDeals / $numOfMeetingsFinishedDeals * 100;
}

$conversionArray = [
    "leadToQL" => $leadToQL,
    "QLToMeeting" => $QLToMeeting,
    "meetingToWon" => $meetingToWon,
    "leadToWon" => $leadToWon
];

$sourceGroupCounts = array_count_values(array_map(function($deal) use ($sourcesMap) {
    foreach ($sourcesMap as $group => $sources) {
        if (in_array($deal["SOURCE_ID"], $sources)) return $group;
    }
    return "Unknown";
}, $deals));

$qualifiedBySource = array_count_values(array_map(function($id) use ($deals, $sourcesMap) {
    foreach ($sourcesMap as $group => $sources) {
        if (in_array($deals[$id]["SOURCE_ID"], $sources)) return $group;
    }
    return "Unknown";
}, $qualifiedDeals));

$wonBySource = array_count_values(array_map(function($id) use ($deals, $sourcesMap) {
    foreach ($sourcesMap as $group => $sources) {
        if (in_array($deals[$id]["SOURCE_ID"], $sources)) return $group;
    }
    return "Unknown";
}, $wonDeals));

$meetingFinishedBySource = array_count_values(array_map(function($id) use ($deals, $sourcesMap) {
    foreach ($sourcesMap as $group => $sources) {
        if (in_array($deals[$id]["SOURCE_ID"], $sources)) return $group;
    }
    return "Unknown";
}, $meetingFinishedDeals));

// result
$statCards = [
    "totalBudget" => $totalBudget,
    "totalLeads" => $numOfTotalDeals,
    "qualifiedLeads" => $numOfQualifiedDeals,
    "nonQualifiedLeads" => $numOfNonQualifiedDeals,
    "meetingFinishedLeads" => $numOfMeetingsFinishedDeals,
    "wonDeals" => $numOfWonDeals,
    "lostDeals" => $numOfLostDeals
];

$resArrayForTable = [];
foreach ($kms as $source) {
    if (!isset($resArrayForTable[$source])) {
        $totalCost = $budgetsPerSource[$source] ?? 0;
        $newLeads = $sourceGroupCounts[$source] ?? 0;
        $qualifiedLeads = $qualifiedBySource[$source] ?? 0;
        $wonLeads = $wonBySource[$source] ?? 0;
        $meetingFinishedLeads = $meetingFinishedBySource[$source] ?? 0;
        
        $resArrayForTable[$source] = [
            "totalCost" => $totalCost,
            "newLeads" => $newLeads,
            "qualifiedLeads" => $qualifiedLeads,
            "wonLeads" => $wonLeads,
            "meetingFinishedLeads" => $meetingFinishedLeads,
            "CPL" => $newLeads > 0 ? $totalCost / $newLeads : 0,
            "CPQL" => $qualifiedLeads > 0 ? $totalCost / $qualifiedLeads : 0,
            "CPW" => $wonLeads > 0 ? $totalCost / $wonLeads : 0
        ];
    }
}

ob_end_clean();
?>

<style>
    * {
        box-sizing: border-box;
    }

    body {
        background: #f5f7fa;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }

    .report-section {
        margin: 30px auto;
        max-width: 1400px;
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

    .filter-section {
        background: #f8f9fa;
        padding: 25px 30px;
        border-bottom: 1px solid #dee2e6;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
    }

    .filter-label {
        font-size: 13px;
        font-weight: 600;
        color: #495057;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .filter-input,
    .filter-select {
        padding: 10px 14px;
        border: 2px solid #dee2e6;
        border-radius: 6px;
        font-size: 14px;
        color: #495057;
        background: white;
        transition: all 0.2s ease;
    }

    .filter-input:focus,
    .filter-select:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .filter-buttons {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }

    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .btn-secondary:hover {
        background: #5a6268;
    }

    /* Stats Cards Section */
    .stats-section {
        padding: 30px;
        background: #ffffff;
        border-bottom: 1px solid #e9ecef;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }

    .stat-card {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 24px 20px;
        text-align: center;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        border-color: #667eea;
    }

    .stat-value {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 8px;
        line-height: 1.2;
    }

    .stat-label {
        font-size: 13px;
        color: #6c757d;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Color variations for stat cards */
    .stat-card:nth-child(1) .stat-value {
        color: #667eea;
    }

    .stat-card:nth-child(2) .stat-value {
        color: #4a5568;
    }

    .stat-card:nth-child(3) .stat-value {
        color: #48bb78;
    }

    .stat-card:nth-child(4) .stat-value {
        color: #ed8936;
    }

    .stat-card:nth-child(5) .stat-value {
        color: #4299e1;
    }

    .stat-card:nth-child(6) .stat-value {
        color: #38b2ac;
    }

    .stat-card:nth-child(7) .stat-value {
        color: #f56565;
    }

    .table-wrapper {
        overflow-x: auto;
        padding: 0;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }

    .data-table thead {
        background: #f8f9fa;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .data-table th {
        padding: 16px 12px;
        text-align: left;
        font-weight: 600;
        color: #495057;
        border-bottom: 2px solid #dee2e6;
        white-space: nowrap;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.5px;
    }

    .data-table tbody tr {
        transition: all 0.2s ease;
    }

    .data-table tbody tr:hover {
        background-color: #f8f9fa;
    }

    .data-table td {
        padding: 14px 12px;
        border-bottom: 1px solid #e9ecef;
        color: #212529;
    }



    .positive-metric {
        color: #28a745;
        font-weight: 600;
    }

    .negative-metric {
        color: #dc3545;
        font-weight: 600;
    }

    .metric-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }

    .badge-success {
        background: #d4edda;
        color: #155724;
    }

    .badge-warning {
        background: #fff3cd;
        color: #856404;
    }

    .badge-danger {
        background: #f8d7da;
        color: #721c24;
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .filter-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="report-section">
    <div class="section-title">📊 Marketing Performance Dashboard</div>

    <!-- Filters Section -->
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
                        <?php if (!empty($responsibles)): ?>
                            <?php foreach ($responsibles as $userId => $userName): ?>
                                <option value="<?= htmlspecialchars($userId) ?>" <?= $filterResponsible == $userId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($userName) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">📍 Source</label>
                    <select name="source" class="filter-select">
                        <option value="">All Sources</option>
                        <?php if (!empty($sourcesList)): ?>
                            <?php foreach($sourcesList as $sourceId => $sourceName): ?>
                                <option value="<?= htmlspecialchars($sourceId) ?>" <?= $filterSource == $sourceId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sourceName) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <div class="filter-buttons">
                <button type="submit" class="btn btn-primary">🔍 Apply Filters</button>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='<?= $_SERVER['PHP_SELF'] ?>'">🔄 Reset</button>
            </div>
        </form>
    </div>

    <!-- Stats Cards Section -->
    <div class="stats-section">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">$<?= number_format($statCards['totalBudget'], 2) ?></div>
                <div class="stat-label">Total Budget</div>
            </div>

            <div class="stat-card">
                <div class="stat-value"><?= $statCards['totalLeads'] ?></div>
                <div class="stat-label">Total Leads</div>
            </div>

            <div class="stat-card">
                <div class="stat-value"><?= $statCards['qualifiedLeads'] ?></div>
                <div class="stat-label">Qualified Leads</div>
            </div>

            <div class="stat-card">
                <div class="stat-value"><?= $statCards['nonQualifiedLeads'] ?></div>
                <div class="stat-label">Never Qualified</div>
            </div>

            <div class="stat-card">
                <div class="stat-value"><?= $statCards['meetingFinishedLeads'] ?></div>
                <div class="stat-label">Meetings Completed</div>
            </div>

            <div class="stat-card">
                <div class="stat-value"><?= $statCards['wonDeals'] ?></div>
                <div class="stat-label">WON Deals</div>
            </div>

            <div class="stat-card">
                <div class="stat-value"><?= $statCards['lostDeals'] ?></div>
                <div class="stat-label">Junk Deals</div>
            </div>
        </div>
    </div>

    <!-- Table Section -->
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Source</th>
                    <th>Total Cost</th>
                    <th>New Leads</th>
                    <th>Qualified Leads</th>
                    <th>Meeting Finished Leads</th>
                    <th>Won Leads</th>
                    <th>CPL</th>
                    <th>CPQL</th>
                    <th>CPW</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if (!empty($resArrayForTable)):
                    foreach ($resArrayForTable as $source => $row): 
                ?>
                    <tr>
                        <td><?= htmlspecialchars($source) ?></td>
                        <td>$<?= number_format($row['totalCost'], 2) ?></td>
                        <td><span class="metric-badge badge-warning"><?= $row['newLeads'] ?></span></td>
                        <td><span class="metric-badge badge-success"><?= $row['qualifiedLeads'] ?></span></td>
                        <td><?= $row['meetingFinishedLeads'] ?></td>
                        <td><span class="metric-badge badge-success"><?= $row['wonLeads'] ?></span></td>
                        <td>$<?= number_format($row['CPL'], 2) ?></td>
                        <td>$<?= number_format($row['CPQL'], 2) ?></td>
                        <td>$<?= number_format($row['CPW'], 2) ?></td>
                    </tr>
                <?php 
                    endforeach;
                else:
                ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px; color: #6c757d;">
                            No data available for the selected filters
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Additional scripts can be added here if needed
</script>