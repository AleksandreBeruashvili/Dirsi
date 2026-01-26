<?php
ob_start();
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CJSCore::Init(array("jquery"));

$APPLICATION->SetTitle("");

// ------------------------------FUNCTIONS---------------------------------
function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getDealsByFilter($arFilter = array(), $arSelect = array(), $arSort = array("ID"=>"DESC")) {
    $arDeals = array();
    $res = CCrmDeal::GetListEx($arSort, $arFilter, false, false, array('*', 'UF_*'));
    while($arDeal = $res->Fetch()) array_push($arDeals, $arDeal);
    return (count($arDeals) > 0) ? $arDeals : false;
}

function getListFieldValues($fieldName) {
    $arResult = array();
    
    $obEnum = new CUserFieldEnum;
    $rsEnum = $obEnum->GetList(array(), array("USER_FIELD_NAME" => $fieldName));
    
    while($arEnum = $rsEnum->Fetch()) {
        $arResult[$arEnum["ID"]] = $arEnum["VALUE"];
    }
    
    return $arResult;
}

function getSourcesList() {
    $arResult = array();
    $arStatuses = CCrmStatus::GetStatusList('SOURCE');
    
    foreach($arStatuses as $id => $name) {
        $arResult[$id] = $name;
    }
    
    return $arResult;
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

function getDataForInProgressStatusHistory($dateFrom = null, $dateTo = null, $filterSources = null, $filterQualities = null)
{
    $arFilter = array();

    if (!empty($dateFrom)) {
        $arFilter['>=DATE_CREATE'] = $dateFrom . ' 00:00:00';
    }

    if (!empty($dateTo)) {
        $arFilter['<=DATE_CREATE'] = $dateTo . ' 23:59:59';
    }

    if (!empty($filterSources)) {
        $arFilter['SOURCE_ID'] = $filterSources;
    }

    if (!empty($filterQualities)) {
        $arFilter['UF_CRM_1761574466063'] = $filterQualities;
    }

    $stages = CCrmStatus::GetStatusList('DEAL_STAGE');
    
    $dealRes = CCrmDeal::GetListEx(
        [], 
        $arFilter, 
        false, 
        false, 
        array()
    );
    
    $allDealNum = 0;
    while ($deal = $dealRes->Fetch()) {
        $allDealNum++;
        $dealIDs[] = $deal["ID"];
    }
    
    $resArray = [];
    if (!empty($dealIDs)) {
        $stageChanges = getStageChangesForMultipleDeals($dealIDs);
        $dealsHistories = [];
        
        if (!empty($stageChanges)) {
            foreach ($stageChanges as $dealId => $changes) {
                $count = 0;
                foreach($changes as $event){
                    if ($count === 0) {
                        $dealsHistories[$dealId][] = $event["EVENT_TEXT_1"];
                    }
                    $dealsHistories[$dealId][] = $event["EVENT_TEXT_2"];
                    $count++;
                }
            }
        }

        
        foreach($dealsHistories as $dealStages) {
            foreach ($dealStages as $stage) {
                if (!isset($resArray[$stage])) {
                    $resArray[$stage] = 0;
                }
                $resArray[$stage]++;
            }
        }
    }
    
    $orderedResult = [];
    foreach ($stages as $stageCode => $stageName) {
        $orderedResult[$stageName] = $resArray[$stageName] ?? 0;
    }
    $resArray = $orderedResult;
    return $resArray;
}

function getDataForSourcesByStage($dateFrom = null, $dateTo = null, $filterSources = null, $filterQualities = null) {
    $arFilter = array();

    if (!empty($dateFrom)) {
        $arFilter['>=DATE_CREATE'] = $dateFrom . ' 00:00:00';
    }

    if (!empty($dateTo)) {
        $arFilter['<=DATE_CREATE'] = $dateTo . ' 23:59:59';
    }

    if (!empty($filterSources)) {
        $arFilter['SOURCE_ID'] = $filterSources;
    }

    if (!empty($filterQualities)) {
        $arFilter['UF_CRM_1761574466063'] = $filterQualities;
    }

    $stages = CCrmStatus::GetStatusList('DEAL_STAGE');
    $sourcesList = getSourcesList();
    
    $dealRes = CCrmDeal::GetListEx(
        [], 
        $arFilter, 
        false, 
        false, 
        array('ID', 'SOURCE_ID')
    );
    
    $dealIDs = [];
    $dealSources = [];
    while ($deal = $dealRes->Fetch()) {
        $dealIDs[] = $deal["ID"];
        $sourceId = trim((string)($deal["SOURCE_ID"] ?? ''));
        $dealSources[$deal["ID"]] = isset($sourcesList[$sourceId]) ? $sourcesList[$sourceId] : 'No Source';
    }
    
    $resArray = [];
    
    // Initialize structure
    foreach ($stages as $stageCode => $stageName) {
        $resArray[$stageName] = [];
        foreach ($sourcesList as $sourceName) {
            $resArray[$stageName][$sourceName] = 0;
        }
        $resArray[$stageName]['No Source'] = 0;
    }
    
    if (!empty($dealIDs)) {
        $stageChanges = getStageChangesForMultipleDeals($dealIDs);
        $dealsHistories = [];
        
        if (!empty($stageChanges)) {
            foreach ($stageChanges as $dealId => $changes) {
                $count = 0;
                foreach($changes as $event){
                    if ($count === 0) {
                        $dealsHistories[$dealId][] = $event["EVENT_TEXT_1"];
                    }
                    $dealsHistories[$dealId][] = $event["EVENT_TEXT_2"];
                    $count++;
                }
            }
        }
        
        foreach($dealsHistories as $dealId => $dealStages) {
            $source = $dealSources[$dealId] ?? 'No Source';
            foreach ($dealStages as $stage) {
                if (isset($resArray[$stage])) {
                    $resArray[$stage][$source]++;
                }
            }
        }
    }
    
    return $resArray;
}

// ------------------------------ MAIN CODE ---------------------------------

// Get filter parameters
$filterDateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$filterDateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$filterSources = isset($_GET['source']) && is_array($_GET['source']) ? $_GET['source'] : [];
$filterQualities = isset($_GET['quality']) && is_array($_GET['quality']) ? $_GET['quality'] : [];

// Build deal filter
$dealFilter = array();

if ($filterDateFrom !== '' && $filterDateTo !== '') {
    $dealFilter['>=DATE_CREATE'] = $filterDateFrom . ' 00:00:00';
    $dealFilter['<=DATE_CREATE'] = $filterDateTo . ' 23:59:59';
} elseif ($filterDateFrom !== '') {
    $dealFilter['>=DATE_CREATE'] = $filterDateFrom . ' 00:00:00';
} elseif ($filterDateTo !== '') {
    $dealFilter['<=DATE_CREATE'] = $filterDateTo . ' 23:59:59';
}

if (!empty($filterSources)) {
    $dealFilter['SOURCE_ID'] = $filterSources;
}

if (!empty($filterQualities)) {
    $dealFilter['UF_CRM_1761574466063'] = $filterQualities;
}

// Fetch deals
$deals = getDealsByFilter($dealFilter);
$qualitiesList = getListFieldValues("UF_CRM_1761574466063");
$sourcesList = getSourcesList();

$qualitiesList[] = "No Quality";
$sourcesList[] = "No Source";

$resArray = [];

// Initialize result array based on filters
if (!empty($filterSources)) {
    // Only show selected sources
    $sourcesToShow = [];
    foreach ($filterSources as $sourceId) {
        if (isset($sourcesList[$sourceId])) {
            $sourcesToShow[$sourceId] = $sourcesList[$sourceId];
        }
    }
} else {
    // Show all sources
    $sourcesToShow = $sourcesList;
}

if (!empty($filterQualities)) {
    // Only show selected qualities
    $qualitiesToShow = [];
    foreach ($filterQualities as $qualityId) {
        if (isset($qualitiesList[$qualityId])) {
            $qualitiesToShow[$qualityId] = $qualitiesList[$qualityId];
        }
    }
} else {
    // Show all qualities
    $qualitiesToShow = $qualitiesList;
}

foreach ($sourcesToShow as $sourceKey => $source) {
    foreach ($qualitiesToShow as $qualityKey => $quality) {
        $resArray[$source][$quality] = 0;
    }
}

// Process deals
$dealsBySource = [];
if ($deals) {
    foreach ($deals as $deal) {
        
        // ---- QUALITY ----
        $qualityId = $deal["UF_CRM_1761574466063"] ?? '';
        if ($qualityId !== '' && isset($qualitiesList[$qualityId])) {
            $quality = $qualitiesList[$qualityId];
        } else {
            $quality = 'No Quality';
        }

        // ---- SOURCE ----
        $sourceId = trim((string)($deal["SOURCE_ID"] ?? ''));
        if ($sourceId !== '' && isset($sourcesList[$sourceId])) {
            $source = $sourcesList[$sourceId];
        } else {
            $source = 'No Source';
        }

        $userId = $deal["ASSIGNED_BY_ID"] ?? '';
        $userName = '';
        if ($userId) {
            $rsUser = CUser::GetByID($userId);
            if ($arUser = $rsUser->Fetch()) {
                $userName = trim($arUser["NAME"] . " " . $arUser["LAST_NAME"]);
            }
        }

        // Get contact
        $contactName = '';
        if (!empty($deal["CONTACT_ID"])) {
            $contactRes = CCrmContact::GetListEx(
                [],
                ['ID' => $deal["CONTACT_ID"]],
                false,
                false,
                ['ID', 'NAME', 'LAST_NAME']
            );
            if ($arContact = $contactRes->Fetch()) {
                $contactName = trim($arContact["NAME"] . " " . $arContact["LAST_NAME"]);
            }
        }
        
        // Only count if this source/quality should be shown
        if (isset($resArray[$source][$quality])) {
            $resArray[$source][$quality]++;
        }

        // Store deal info
        if (!isset($dealsBySource[$source])) {
            $dealsBySource[$source] = [];
        }
        
        $dealsBySource[$source][] = [
            'ID' => $deal['ID'],
            'TITLE' => $deal['TITLE'] ?? 'Deal #' . $deal['ID'],
            'RESPONSIBLE' => $userName ?: 'Not assigned',
            'CONTACT' => $contactName ?: 'No contact',
            'QUALITY' => $quality
        ];
    }
}

$dealsBySourceJson = json_encode($dealsBySource, JSON_UNESCAPED_UNICODE);

$numOfDealsOnStagesHistory = getDataForInProgressStatusHistory($filterDateFrom, $filterDateTo, $filterSources, $filterQualities);
$labelsJson = json_encode(array_keys($numOfDealsOnStagesHistory), JSON_UNESCAPED_UNICODE);
$valuesJson = json_encode(array_values($numOfDealsOnStagesHistory));

$sourcesByStage = getDataForSourcesByStage($filterDateFrom, $filterDateTo, $filterSources, $filterQualities);
$funnelStages = array_keys($sourcesByStage);
$funnelSourcesData = [];

// Get all unique sources
$allSources = [];
foreach ($sourcesByStage as $stage => $sources) {
    foreach ($sources as $sourceName => $count) {
        if ($count > 0 && !in_array($sourceName, $allSources)) {
            $allSources[] = $sourceName;
        }
    }
}

// Prepare data for each source
foreach ($allSources as $sourceName) {
    $sourceData = [];
    foreach ($funnelStages as $stage) {
        $sourceData[] = $sourcesByStage[$stage][$sourceName] ?? 0;
    }
    $funnelSourcesData[$sourceName] = $sourceData;
}

$funnelStagesJson = json_encode($funnelStages, JSON_UNESCAPED_UNICODE);
$funnelSourcesJson = json_encode($funnelSourcesData, JSON_UNESCAPED_UNICODE);

ob_end_clean();
?>

<style>
    .report-container {
        max-width: 1400px;
        margin: 40px auto;
        padding: 0 20px;
    }

    .report-header {
        text-align: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 3px solid #e2e8f0;
    }

    .report-title {
        font-size: 32px;
        font-weight: 700;
        color: #1a202c;
        margin: 0 0 10px 0;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
    }

    .report-subtitle {
        font-size: 14px;
        color: #718096;
        margin: 0;
    }

    .filter-section {
        background: #fff;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }

    .filter-title {
        font-size: 18px;
        font-weight: 600;
        color: #2d3748;
        margin: 0 0 20px 0;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }

    .filter-field {
        display: flex;
        flex-direction: column;
    }

    .filter-label {
        font-size: 13px;
        font-weight: 600;
        color: #4a5568;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .filter-input,
    .filter-select {
        padding: 0 12px;
        border: 1px solid #cbd5e0;
        border-radius: 6px;
        font-size: 14px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
        transition: border-color 0.2s;
        min-height: 42px;
    }

    .filter-input:focus,
    .filter-select:focus {
        outline: none;
        border-color: #4299e1;
        box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
    }

    .multi-select-dropdown {
        position: relative;
    }

    .multi-select-button {
        width: 100%;
        padding: 12px;
        border: 1px solid #cbd5e0;
        border-radius: 6px;
        font-size: 14px;
        background: white;
        text-align: left;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: border-color 0.2s;
    }

    .multi-select-button:hover {
        border-color: #a0aec0;
    }

    .multi-select-button:focus {
        outline: none;
        border-color: #4299e1;
        box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
    }

    .multi-select-button .placeholder {
        color: #a0aec0;
    }

    .multi-select-button .arrow {
        transition: transform 0.2s;
    }

    .multi-select-button .arrow.open {
        transform: rotate(180deg);
    }

    .multi-select-options {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #cbd5e0;
        border-radius: 6px;
        margin-top: 4px;
        max-height: 250px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        display: none;
    }

    .multi-select-options.open {
        display: block;
    }

    .multi-select-option {
        padding: 10px 12px;
        cursor: pointer;
        transition: background-color 0.15s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .multi-select-option:hover {
        background-color: #f7fafc;
    }

    .multi-select-option input[type="checkbox"] {
        cursor: pointer;
        width: 16px;
        height: 16px;
    }

    .multi-select-option label {
        cursor: pointer;
        flex: 1;
        user-select: none;
    }

    .selected-count {
        background: #4299e1;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }

    .filter-buttons {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
    }

    .btn {
        padding: 10px 24px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
    }

    .btn-primary {
        background: #4299e1;
        color: white;
    }

    .btn-primary:hover {
        background: #3182ce;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }

    .btn-secondary {
        background: #e2e8f0;
        color: #2d3748;
    }

    .btn-secondary:hover {
        background: #cbd5e0;
    }

    .crm-report-table {
        width: 100%;
        border-collapse: collapse;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
        font-size: 14px;
        background: #fff;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        border-radius: 12px;
        overflow: hidden;
    }

    .crm-report-table thead th {
        background: #f4f6f8;
        color: #333;
        font-weight: 600;
        padding: 14px 12px;
        border-bottom: 2px solid #e2e8f0;
        text-align: center;
        position: sticky;
        top: 0;
        z-index: 2;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        font-size: 12px;
    }

    .crm-report-table tbody td {
        padding: 12px;
        border-bottom: 1px solid #e2e8f0;
        text-align: center;
    }

    .crm-report-table tbody tr:nth-child(even) {
        background: #f7fafc;
    }

    .crm-report-table td:first-child,
    .crm-report-table th:first-child {
        text-align: left;
        font-weight: 600;
        white-space: nowrap;
        padding-left: 20px;
    }

    .crm-report-table .total-cell {
        font-weight: 700;
        background: #f0f3f7;
        font-size: 15px;
    }

    .crm-report-table .zero {
        color: #cbd5e0;
    }

    .crm-report-table tfoot th {
        background: #e9edf3;
        font-weight: 700;
        border-top: 2px solid #d5dbe3;
        padding: 14px 12px;
        font-size: 13px;
    }

    .no-data-message {
        text-align: center;
        padding: 60px 20px;
        color: #a0aec0;
        font-size: 16px;
        background: #f7fafc;
        border-radius: 12px;
        border: 2px dashed #cbd5e0;
    }

    .chart-container {
        width: 96%;
        max-width: 1200px;
        height: 420px;   /* 👈 this is the key */
        margin: 30px auto;
        padding: 20px;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    /* Popup/Modal Styles */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        z-index: 9998;
        animation: fadeIn 0.2s;
    }

    .modal-overlay.show {
        display: block;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .modal-popup {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        z-index: 9999;
        max-width: 900px;
        width: 90%;
        max-height: 80vh;
        overflow: hidden;
        animation: slideIn 0.3s;
    }

    .modal-popup.show {
        display: flex;
        flex-direction: column;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translate(-50%, -48%);
        }
        to {
            opacity: 1;
            transform: translate(-50%, -50%);
        }
    }

    .modal-header {
        padding: 20px 24px;
        border-bottom: 2px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f7fafc;
    }

    .modal-title {
        font-size: 20px;
        font-weight: 700;
        color: #1a202c;
        margin: 0;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 28px;
        color: #718096;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        transition: all 0.2s;
    }

    .modal-close:hover {
        background: #e2e8f0;
        color: #2d3748;
    }

    .modal-body {
        padding: 20px 24px;
        overflow-y: auto;
        flex: 1;
    }

    .deals-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }

    .deals-table thead th {
        background: #f4f6f8;
        color: #2d3748;
        font-weight: 600;
        padding: 12px;
        text-align: left;
        border-bottom: 2px solid #e2e8f0;
        position: sticky;
        top: 0;
        z-index: 1;
    }

    .deals-table tbody td {
        padding: 12px;
        border-bottom: 1px solid #e2e8f0;
    }

    .deals-table tbody tr:hover {
        background: #f7fafc;
    }

    .deal-link {
        color: #4299e1;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.2s;
    }

    .deal-link:hover {
        color: #3182ce;
        text-decoration: underline;
    }

    .clickable-row {
        cursor: pointer;
        transition: background-color 0.15s;
    }

    .clickable-row:hover {
        background-color: #f0f4f8 !important;
    }

    .no-deals-message {
        text-align: center;
        padding: 40px;
        color: #a0aec0;
        font-size: 15px;
    }
</style>

<div class="report-container">
    <div class="report-header">
        <h1 class="report-title">არხების მიხედვით ლიდების რაოდენობა/ხარისხი</h1>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <h2 class="filter-title">🔍 ფილტრები</h2>
        <form method="GET" id="filterForm">
            <div class="filter-grid">
                <div class="filter-field">
                    <label class="filter-label">თარიღიდან</label>
                    <input type="date" name="date_from" class="filter-input" value="<?= htmlspecialchars($filterDateFrom) ?>">
                </div>

                <div class="filter-field">
                    <label class="filter-label">თარიღამდე</label>
                    <input type="date" name="date_to" class="filter-input" value="<?= htmlspecialchars($filterDateTo) ?>">
                </div>

                <div class="filter-field">
                    <label class="filter-label">წყარო</label>
                    <div class="multi-select-dropdown">
                        <button type="button" class="multi-select-button" onclick="toggleDropdown('sourceDropdown')">
                            <span id="sourceButtonText" class="placeholder">აირჩიეთ წყარო</span>
                            <span class="arrow">▼</span>
                        </button>
                        <div id="sourceDropdown" class="multi-select-options">
                            <?php foreach ($sourcesList as $sourceId => $sourceName): ?>
                                <div class="multi-select-option">
                                    <input 
                                        type="checkbox" 
                                        name="source[]" 
                                        value="<?= htmlspecialchars($sourceId) ?>" 
                                        id="source_<?= htmlspecialchars($sourceId) ?>"
                                        <?= in_array($sourceId, $filterSources) ? 'checked' : '' ?>
                                        onchange="updateButtonText('source')"
                                    >
                                    <label for="source_<?= htmlspecialchars($sourceId) ?>">
                                        <?= htmlspecialchars($sourceName) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="filter-field">
                    <label class="filter-label">ხარისხი</label>
                    <div class="multi-select-dropdown">
                        <button type="button" class="multi-select-button" onclick="toggleDropdown('qualityDropdown')">
                            <span id="qualityButtonText" class="placeholder">აირჩიეთ ხარისხი</span>
                            <span class="arrow">▼</span>
                        </button>
                        <div id="qualityDropdown" class="multi-select-options">
                            <?php foreach ($qualitiesList as $qualityId => $qualityName): ?>
                                <div class="multi-select-option">
                                    <input 
                                        type="checkbox" 
                                        name="quality[]" 
                                        value="<?= htmlspecialchars($qualityId) ?>" 
                                        id="quality_<?= htmlspecialchars($qualityId) ?>"
                                        <?= in_array($qualityId, $filterQualities) ? 'checked' : '' ?>
                                        onchange="updateButtonText('quality')"
                                    >
                                    <label for="quality_<?= htmlspecialchars($qualityId) ?>">
                                        <?= htmlspecialchars($qualityName) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="filter-buttons">
                <button type="button" class="btn btn-secondary" onclick="clearFilters()">გასუფთავება</button>
                <button type="submit" class="btn btn-primary">ფილტრი</button>
            </div>
        </form>

    </div>

    <?php if (!empty($resArray)): ?>

    <?php
    // --------- CALCULATE COLUMN TOTALS ----------
    $columnTotals = array_fill_keys(array_keys($qualitiesToShow), 0);
    $grandTotal = 0;

    foreach ($resArray as $qualities) {
        foreach ($qualitiesToShow as $quality) {
            $count = $qualities[$quality] ?? 0;
            $columnTotals[$quality] += $count;
            $grandTotal += $count;
        }
    }
    ?>

    <table class="crm-report-table">
        <thead>
            <tr>
                <th>Source</th>

                <?php foreach ($qualitiesToShow as $quality): ?>
                    <th><?= htmlspecialchars($quality) ?></th>
                <?php endforeach; ?>

                <th>Total</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($resArray as $source => $qualities): ?>
                <tr>
                    <td><?= htmlspecialchars($source) ?></td>

                    <?php
                    $rowTotal = 0;
                    foreach ($qualitiesToShow as $quality):
                        $count = $qualities[$quality] ?? 0;
                        $rowTotal += $count;
                    ?>
                        <td class="<?= $count == 0 ? 'zero' : '' ?>">
                            <?= $count ?>
                        </td>
                    <?php endforeach; ?>

                    <td class="total-cell"><?= $rowTotal ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>

        <tfoot>
            <tr>
                <th>Total</th>

                <?php foreach ($qualitiesToShow as $quality): ?>
                    <th><?= $columnTotals[$quality] ?></th>
                <?php endforeach; ?>

                <th><?= $grandTotal ?></th>
            </tr>
        </tfoot>
    </table>

    <?php else: ?>
        <div class="no-data-message">
            <p>📊 No data available to display</p>
        </div>
    <?php endif; ?>

    <div class="chart-container">
        <canvas id="stageChart"></canvas>
    </div>

    <div class="chart-container" style="height: 500px;">
        <canvas id="funnelChart"></canvas>
    </div>

    <!-- Modal Overlay -->
    <div id="modalOverlay" class="modal-overlay" onclick="closeModal()"></div>

    <!-- Modal Popup -->
    <div id="modalPopup" class="modal-popup">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle">Deals</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <table class="deals-table">
                <thead>
                    <tr>
                        <th>Deal</th>
                        <th>Responsible</th>
                        <th>Client (Contact)</th>
                        <th>Quality</th>
                    </tr>
                </thead>
                <tbody id="modalTableBody">
                    <!-- Deals will be inserted here -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
function toggleDropdown(dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    const button = dropdown.previousElementSibling;
    const arrow = button.querySelector('.arrow');
    
    // Close all other dropdowns
    document.querySelectorAll('.multi-select-options').forEach(d => {
        if (d.id !== dropdownId) {
            d.classList.remove('open');
        }
    });
    document.querySelectorAll('.arrow').forEach(a => {
        if (a !== arrow) {
            a.classList.remove('open');
        }
    });
    
    // Toggle current dropdown
    dropdown.classList.toggle('open');
    arrow.classList.toggle('open');
}

function updateButtonText(type) {
    const checkboxes = document.querySelectorAll(`input[name="${type}[]"]:checked`);
    const buttonText = document.getElementById(`${type}ButtonText`);
    const count = checkboxes.length;
    
    if (count === 0) {
        buttonText.innerHTML = type === 'source' ? 'აირჩიეთ წყარო' : 'აირჩიეთ ხარისხი';
        buttonText.className = 'placeholder';
    } else if (count === 1) {
        const label = document.querySelector(`label[for="${checkboxes[0].id}"]`).textContent.trim();
        buttonText.innerHTML = label;
        buttonText.className = '';
    } else {
        buttonText.innerHTML = `${count} არჩეული <span class="selected-count">${count}</span>`;
        buttonText.className = '';
    }
}

function clearFilters() {
    window.location.href = window.location.pathname;
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.multi-select-dropdown')) {
        document.querySelectorAll('.multi-select-options').forEach(d => {
            d.classList.remove('open');
        });
        document.querySelectorAll('.arrow').forEach(a => {
            a.classList.remove('open');
        });
    }
});

// Initialize button text on page load
window.addEventListener('DOMContentLoaded', function() {
    updateButtonText('source');
    updateButtonText('quality');
});

const values = <?= $valuesJson ?>;
const maxValue = Math.max(...values);

// Round max up to nearest 50 or 100
const roundedMax = Math.ceil(maxValue / 50) * 50;

const ctx = document.getElementById('stageChart').getContext('2d');
const stageChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= $labelsJson ?>,
        datasets: [{
            label: 'Number of Deals',
            data: values,
            backgroundColor: 'rgba(54, 163, 235, 1)'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                suggestedMax: roundedMax,
                ticks: {
                    stepSize: roundedMax / 5,
                    font: { size: 12 }
                },
                title: {
                    display: true,
                    text: 'Number of Deals',
                    font: { size: 14, weight: 'bold' }
                }
            },
            x: {
                ticks: {
                    maxRotation: 45,
                    minRotation: 45,
                    font: { size: 11 }
                }
            }
        },
        plugins: {
            legend: { position: 'top' },
            tooltip: {
                mode: 'index',       
                intersect: false,    
                callbacks: {
                    label: ctx => `Deals: ${ctx.parsed.y}`
                }
            }
        },
        interaction: {
            mode: 'index',        
            intersect: false     
        }
    }
});

// Funnel Chart with Stacked Bars by Source
const funnelStages = <?= $funnelStagesJson ?>;
const funnelSourcesData = <?= $funnelSourcesJson ?>;

const colors = [
    'rgba(255, 99, 132, 0.8)',
    'rgba(54, 162, 235, 0.8)',
    'rgba(255, 206, 86, 0.8)',
    'rgba(75, 192, 192, 0.8)',
    'rgba(153, 102, 255, 0.8)',
    'rgba(255, 159, 64, 0.8)',
    'rgba(199, 199, 199, 0.8)',
    'rgba(83, 102, 255, 0.8)',
    'rgba(255, 99, 255, 0.8)',
    'rgba(99, 255, 132, 0.8)'
];

const funnelDatasets = [];
let colorIndex = 0;

for (const [sourceName, data] of Object.entries(funnelSourcesData)) {
    funnelDatasets.push({
        label: sourceName,
        data: data,
        backgroundColor: colors[colorIndex % colors.length],
        borderColor: colors[colorIndex % colors.length].replace('0.8', '1'),
        borderWidth: 1
    });
    colorIndex++;
}

const funnelCtx = document.getElementById('funnelChart').getContext('2d');
const funnelChart = new Chart(funnelCtx, {
    type: 'bar',
    data: {
        labels: funnelStages,
        datasets: funnelDatasets
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: {
                stacked: true,
                ticks: {
                    maxRotation: 45,
                    minRotation: 45,
                    font: { size: 11 }
                },
                title: {
                    display: true,
                    text: 'Deal Stages',
                    font: { size: 14, weight: 'bold' }
                }
            },
            y: {
                stacked: true,
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Number of Deals',
                    font: { size: 14, weight: 'bold' }
                }
            }
        },
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    boxWidth: 15,
                    font: { size: 11 }
                }
            },
            title: {
                display: true,
                text: 'Deal Sources Distribution by Stage',
                font: { size: 16, weight: 'bold' }
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.parsed.y;
                    },
                    footer: function(tooltipItems) {
                        let total = 0;
                        tooltipItems.forEach(item => {
                            total += item.parsed.y;
                        });
                        return 'Total: ' + total;
                    }
                }
            }
        },
        interaction: {
            mode: 'index',
            intersect: false
        }
    }
});

// Store deals data
const dealsBySource = <?= $dealsBySourceJson ?>;

// Add click handlers to table rows
function initTableClickHandlers() {
    const tableRows = document.querySelectorAll('.crm-report-table tbody tr');
    tableRows.forEach(row => {
        const sourceCell = row.querySelector('td:first-child');
        if (sourceCell) {
            row.classList.add('clickable-row');
            row.addEventListener('click', function() {
                const sourceName = sourceCell.textContent.trim();
                showDealsPopup(sourceName);
            });
        }
    });
}

// Show popup with deals
function showDealsPopup(sourceName) {
    const deals = dealsBySource[sourceName] || [];
    
    // Update modal title
    document.getElementById('modalTitle').textContent = `Deals from: ${sourceName}`;
    
    // Clear and populate table body
    const tbody = document.getElementById('modalTableBody');
    tbody.innerHTML = '';
    
    if (deals.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="no-deals-message">No deals found for this source</td></tr>';
    } else {
        deals.forEach(deal => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><a href="/crm/deal/details/${deal.ID}/" class="deal-link" target="_blank">${escapeHtml(deal.TITLE)}</a></td>
                <td>${escapeHtml(deal.RESPONSIBLE)}</td>
                <td>${escapeHtml(deal.CONTACT)}</td>
                <td>${escapeHtml(deal.QUALITY)}</td>
            `;
            tbody.appendChild(row);
        });
    }
    
    // Show modal
    document.getElementById('modalOverlay').classList.add('show');
    document.getElementById('modalPopup').classList.add('show');
    document.body.style.overflow = 'hidden';
}

// Close popup
function closeModal() {
    document.getElementById('modalOverlay').classList.remove('show');
    document.getElementById('modalPopup').classList.remove('show');
    document.body.style.overflow = '';
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});

// Initialize click handlers when page loads
window.addEventListener('DOMContentLoaded', function() {
    initTableClickHandlers();
});
</script>