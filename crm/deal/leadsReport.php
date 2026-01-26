<?php
ob_start();
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CJSCore::Init(array("jquery"));

$APPLICATION->SetTitle("Leads Report");

// ------------------------------FUNCTIONS---------------------------------
function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getUserNames($ids) {
    $names = [];

    foreach($ids as $id) {
        $res = CUser::GetByID($id)->Fetch();
        if($res) {
            $names[$id] = $res["NAME"] . " " . $res["LAST_NAME"];
        }
    }

    return $names;
}

function getAllDealResponsibles() {
    $userIds = [];
    $users = [];

    // 1. Collect unique ASSIGNED_BY_IDs
    $res = CCrmDeal::GetList(
        [],
        [],
        ['ASSIGNED_BY_ID'],
        false
    );

    while ($deal = $res->Fetch()) {
        if (!empty($deal['ASSIGNED_BY_ID'])) {
            $userIds[$deal['ASSIGNED_BY_ID']] = true;
        }
    }

    if (empty($userIds)) {
        return [];
    }

    // 2. Fetch users in ONE query
    $userRes = CUser::GetList(
        $by = "NAME",
        $order = "ASC",
        [
            "ACTIVE" => "Y",
            "ID" => implode('|', array_keys($userIds))
        ]
    );

    while ($user = $userRes->Fetch()) {
        $users[$user["ID"]] = trim($user["NAME"] . " " . $user["LAST_NAME"]);
    }

    return $users;
}


function getDealsByFilter($arFilter=array(), $arSelect = array(), $arSort = array("ID"=>"DESC")) {
    $arDeals = array();
    $res = CCrmDeal::GetList($arSort, $arFilter, $arSelect);
    while($arDeal = $res->Fetch()) {
        array_push($arDeals, $arDeal);
    } 
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

// ------------------------------MAIN CODE---------------------------------

// Get filter parameters
$filterUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$filterDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filterDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build filter array
$dealFilter = array();

if ($filterUserId > 0) {
    $dealFilter['ASSIGNED_BY_ID'] = $filterUserId;
}

if($filterDateFrom) {
    $dateFromConverted = ConvertTimeStamp(strtotime($filterDateFrom . ' 00:00:00'), "FULL");
    $dealFilter['>=DATE_CREATE'] = $dateFromConverted;
}

if($filterDateTo) {
    $dateToConverted = ConvertTimeStamp(strtotime($filterDateTo . ' 23:59:59'), "FULL");
    $dealFilter['<=DATE_CREATE'] = $dateToConverted;
}

// Get all deals with necessary fields
$arSelect = array(
    "ID",
    "ASSIGNED_BY_ID",
    "STAGE_ID",
    "DATE_CREATE",
    "UF_CRM_1764316558373",
    "UF_CRM_1764316587053", 
    "UF_CRM_1761574466063",
    "UF_CRM_1761575156657"
);

$deals = getDealsByFilter($dealFilter, $arSelect);

$reasonsList = getListFieldValues("UF_CRM_1761575156657");
$statusList = getListFieldValues("UF_CRM_1761574466063");
$allUsers = getAllDealResponsibles();

// Initialize counters
$soldDeals = 0;
$yesPropertyCount = 0;
$statusCounts = array();
$lostByReason = array();

// NEW COUNTERS
$qualifiedDeals = 0;
$nonQualifiedDeals = 0;
$meetingCompletedCount = 0;
$wonDealsCount = 0;
$lostDealsCount = 0;

// Initialize status counts with deal arrays
foreach($statusList as $statusId => $statusName) {
    $statusCounts[$statusName] = array(
        'count' => 0,
        'deals' => array()
    );
}

// Initialize lost reasons with deal arrays
foreach($reasonsList as $reasonId => $reasonName) {
    $lostByReason[$reasonName] = array(
        'count' => 0,
        'deals' => array()
    );
}

// Collect user IDs
$assignedIds = [];
$damushavebuliDeals = [];
$shexvedrebiDeals = [];
$userNamesMap = [];

if($deals) {
    foreach($deals as $deal) {
        // Get user name for this deal
        if(!isset($userNamesMap[$deal['ASSIGNED_BY_ID']])) {
            $userRes = CUser::GetByID($deal['ASSIGNED_BY_ID'])->Fetch();
            if($userRes) {
                $userNamesMap[$deal['ASSIGNED_BY_ID']] = $userRes["NAME"] . " " . $userRes["LAST_NAME"];
            }
        }
        
        // Count WON deals
        if(strpos($deal['STAGE_ID'], 'WON') !== false) {
            $wonDealsCount++;
        }
        
        // Count LOST deals
        if(strpos($deal['STAGE_ID'], 'LOSE') !== false) {
            $lostDealsCount++;
        }
        
        // Count sold deals (assuming WON stage)
        if(strpos($deal['STAGE_ID'], 'WON') !== false || strpos($deal['STAGE_ID'], 'LOSE') !== false) {
            $soldDeals++;
            $damushavebuliDeals[] = array(
                'ID' => $deal['ID'],
                'ASSIGNED_BY_ID' => $deal['ASSIGNED_BY_ID'],
                'ASSIGNED_BY_NAME' => isset($userNamesMap[$deal['ASSIGNED_BY_ID']]) ? $userNamesMap[$deal['ASSIGNED_BY_ID']] : 'Unknown',
                'DATE_CREATE' => $deal['DATE_CREATE'],
                'STAGE_ID' => $deal['STAGE_ID'],
                'LINK' => '/crm/deal/details/' . $deal['ID'] . '/'
            );
        }
        
        // Count meeting completed (UF_CRM_1764316558373 = "468")
        if(isset($deal['UF_CRM_1764316558373']) && 
           ($deal['UF_CRM_1764316558373'] == '468')) {
            
            $meetingCompletedCount++;
            $shexvedrebiDeals[] = array(
                'ID' => $deal['ID'],
                'ASSIGNED_BY_ID' => $deal['ASSIGNED_BY_ID'],
                'ASSIGNED_BY_NAME' => isset($userNamesMap[$deal['ASSIGNED_BY_ID']]) ? $userNamesMap[$deal['ASSIGNED_BY_ID']] : 'Unknown',
                'CUSTOM_FIELD' => isset($deal['UF_CRM_1764316587053']) ? $deal['UF_CRM_1764316587053'] : '',
                'STAGE_ID' => $deal['STAGE_ID'],
                'LINK' => '/crm/deal/details/' . $deal['ID'] . '/'
            );
            $yesPropertyCount++;
        }
        
        // Count UF_CRM_1761574466063 values by name
        if(isset($deal['UF_CRM_1761574466063'])) {
            $statusId = $deal['UF_CRM_1761574466063'];
            if(isset($statusList[$statusId])) {
                $statusName = $statusList[$statusId];
                $statusCounts[$statusName]['count']++;
                $statusCounts[$statusName]['deals'][] = array(
                    'ID' => $deal['ID'],
                    'ASSIGNED_BY_ID' => $deal['ASSIGNED_BY_ID'],
                    'ASSIGNED_BY_NAME' => isset($userNamesMap[$deal['ASSIGNED_BY_ID']]) ? $userNamesMap[$deal['ASSIGNED_BY_ID']] : 'Unknown',
                    'DATE_CREATE' => $deal['DATE_CREATE'],
                    'STAGE_ID' => $deal['STAGE_ID'],
                    'LINK' => '/crm/deal/details/' . $deal['ID'] . '/'
                );
                
                // Count qualified vs non-qualified
                if(stripos($statusName, 'კვალიფიცირებული') !== false || 
                   stripos($statusName, 'qualified') !== false) {
                    $qualifiedDeals++;
                } else if(stripos($statusName, 'არაკვალიფიცირებული') !== false || 
                          stripos($statusName, 'non-qualified') !== false ||
                          stripos($statusName, 'არ არის') !== false) {
                    $nonQualifiedDeals++;
                }
            }
        }
        
        // Count LOST deals by UF_CRM_1761575156657
        if(strpos($deal['STAGE_ID'], 'LOSE') !== false) {
            $reasonId = isset($deal['UF_CRM_1761575156657']) ? $deal['UF_CRM_1761575156657'] : 0;
            
            if(isset($reasonsList[$reasonId])) {
                $reasonName = $reasonsList[$reasonId];
                $lostByReason[$reasonName]['count']++;
                $lostByReason[$reasonName]['deals'][] = array(
                    'ID' => $deal['ID'],
                    'ASSIGNED_BY_ID' => $deal['ASSIGNED_BY_ID'],
                    'ASSIGNED_BY_NAME' => isset($userNamesMap[$deal['ASSIGNED_BY_ID']]) ? $userNamesMap[$deal['ASSIGNED_BY_ID']] : 'Unknown',
                    'DATE_CREATE' => $deal['DATE_CREATE'],
                    'STAGE_ID' => $deal['STAGE_ID'],
                    'LINK' => '/crm/deal/details/' . $deal['ID'] . '/'
                );
            }
        }
        
        // Collect assigned user IDs
        if(isset($deal['ASSIGNED_BY_ID'])) {
            if (!in_array($deal['ASSIGNED_BY_ID'], $assignedIds)) {
                $assignedIds[] = $deal['ASSIGNED_BY_ID'];
            }
        }
    }
}

$users = getUserNames($assignedIds);

// Count sold deals per user
$soldDealsByUser = array();
foreach($damushavebuliDeals as $deal) {
    $userId = $deal['ASSIGNED_BY_ID'];
    $userName = $deal['ASSIGNED_BY_NAME'];
    
    if(!isset($soldDealsByUser[$userId])) {
        $soldDealsByUser[$userId] = array(
            'name' => $userName,
            'count' => 0,
            'deals' => array()
        );
    }
    
    $soldDealsByUser[$userId]['count']++;
    $soldDealsByUser[$userId]['deals'][] = $deal;
}

// Sort by count descending
uasort($soldDealsByUser, function($a, $b) {
    return $b['count'] - $a['count'];
});

$dealsByMonth = array();

if (!empty($deals)) {
    foreach ($deals as $deal) {
        if (empty($deal['DATE_CREATE'])) continue;

        $dateObj = DateTime::createFromFormat('d/m/Y h:i:s a', trim($deal['DATE_CREATE']));

        if (!$dateObj) continue; // invalid date

        $timestamp = $dateObj->getTimestamp();

        $monthKey   = $dateObj->format('Y-m');
        $monthLabel = $dateObj->format('M Y');

        if (!isset($dealsByMonth[$monthKey])) {
            $dealsByMonth[$monthKey] = [
                'label' => $monthLabel,
                'count' => 0
            ];
        }

        $dealsByMonth[$monthKey]['count']++;
    }
}

ksort($dealsByMonth);

ob_end_clean();
?>

<style>
    .report-container {
        align-items: center;
        flex-direction: column;
        display: flex;
        max-width: 100%;
        padding: 20px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    
    .filter-section {
        width: 100%;
        margin-bottom: 25px;
        background: #ffffff;
        padding: 10px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    
    .filter-title {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 20px;
        color: #4a5568;
    }
    
    .filter-form {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr auto;
        gap: 15px;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
    }
    
    .filter-label {
        font-size: 13px;
        font-weight: 500;
        color: #64748b;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .filter-select,
    .filter-input {
        padding: 10px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 14px;
        color: #374151;
        background: white;
        transition: border-color 0.2s ease;
    }
    
    .filter-select:focus,
    .filter-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .filter-button {
        height: 42px;
        padding: 10px 24px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .filter-button:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
    }
    
    .filter-button:active {
        transform: translateY(0);
    }
    
    .reset-button {
        padding: 10px 20px;
        background: #f1f5f9;
        color: #64748b;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: background-color 0.2s ease;
        text-decoration: none;
        display: inline-block;
    }
    
    .reset-button:hover {
        background: #e2e8f0;
    }
    
    .report-section {
        width: 100%;
        margin-bottom: 30px;
        background: #fefefe;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    
    .section-title {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 15px;
        color: #4a5568;
        height: 50px;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .stat-card {
        background: linear-gradient(135deg, #e0f2fe 0%, #dbeafe 100%);
        padding: 20px;
        border-radius: 6px;
        text-align: center;
        transition: transform 0.2s ease;
        cursor: pointer;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
    }
    
    .stat-card.green {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    }
    
    .stat-card.green .stat-value {
        color: #065f46;
    }
    
    .stat-card.red {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    }
    
    .stat-card.red .stat-value {
        color: #991b1b;
    }
    
    .stat-card.purple {
        background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%);
    }
    
    .stat-card.purple .stat-value {
        color: #6b21a8;
    }
    
    .stat-card.orange {
        background: linear-gradient(135deg, #ffedd5 0%, #fed7aa 100%);
    }
    
    .stat-card.orange .stat-value {
        color: #9a3412;
    }
    
    .stat-value {
        font-size: 32px;
        font-weight: 700;
        color: #1e40af;
        margin-bottom: 5px;
    }
    
    .stat-label {
        font-size: 13px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .table-wrapper {
        position: relative;
        overflow: hidden;
        width: 100%;
    }
    
    .table-wrapper.collapsed {
        max-height: 220px;
        width: 100%;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
    }
    
    .data-table thead {
        background: linear-gradient(135deg, #fce7f3 0%, #fbcfe8 100%);
    }
    
    .data-table th {
        padding: 14px 16px;
        text-align: left;
        font-weight: 600;
        color: #831843;
        font-size: 14px;
        border-bottom: 2px solid #f9a8d4;
    }
    
    .data-table tbody tr {
        transition: background-color 0.15s ease;
        cursor: pointer;
    }
    
    .data-table tbody tr:nth-child(even) {
        background: #fdf4ff;
    }
    
    .data-table tbody tr:hover {
        background: #fae8ff;
    }
    
    .data-table td {
        padding: 12px 16px;
        border-bottom: 1px solid #f3e8ff;
        color: #374151;
    }
    
    .count-badge {
        display: inline-block;
        background: #a78bfa;
        color: white;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.2s ease;
    }
    
    .count-badge:hover {
        background: #8b5cf6;
    }
    
    .total-row {
        background: #f3e8ff !important;
        font-weight: 600;
    }
    
    .total-badge {
        background: #7c3aed;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px;
        color: #9ca3af;
        font-style: italic;
    }
    
    .toggle-button {
        padding: 10px 20px;
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    
    .toggle-button:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
    }
    
    .toggle-button:active {
        transform: translateY(0);
    }
    
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }
    
    .modal-content {
        background: white;
        width: 90%;
        max-width: 1000px;
        max-height: 80vh;
        overflow: auto;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    }
    
    .modal-header {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .modal-title {
        font-size: 20px;
        font-weight: 600;
        color: #1f2937;
        margin: 0;
    }
    
    .modal-close {
        margin-top: 15px;
        padding: 10px 20px;
        background: #f1f5f9;
        color: #64748b;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: background-color 0.2s ease;
    }
    
    .modal-close:hover {
        background: #e2e8f0;
    }
    
    @media (max-width: 968px) {
        .filter-form {
            grid-template-columns: 1fr;
        }
        
        .modal-content {
            width: 95%;
            max-height: 90vh;
        }
    }

    .reportebi {
        display: flex;
        flex-direction: row;
        width: 103%;
        gap: 10px;
    }

    .chart-container {
        width: 70%;
        height: 250px;
        position: relative;
    }
</style>

<div class="report-container">
    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label class="filter-label">Responsible</label>
                <select name="user_id" class="filter-select">
                    <option value="0">All Responsibles</option>
                    <?php foreach ($allUsers as $userId => $userName): ?>
                        <?php if($userId != 3): ?>
                            <option value="<?= $userId ?>" <?= ($filterUserId == $userId ? 'selected' : '') ?>>
                                <?= htmlspecialchars($userName) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">From</label>
                <input type="date" name="date_from" class="filter-input" value="<?= htmlspecialchars($filterDateFrom) ?>">
            </div>
            
            <div class="filter-group">
                <label class="filter-label">To</label>
                <input type="date" name="date_to" class="filter-input" value="<?= htmlspecialchars($filterDateTo) ?>">
            </div>
            
            <div class="filter-group" style="display: flex; gap: 10px; flex-direction: row; align-items: end;">
                <button type="submit" class="filter-button">Apply</button>
                <a href="?" class="reset-button" style="height: 20.4px;">Clear</a>
            </div>
        </form>
    </div>

    <!-- Summary Stats -->
    <div class="report-section" style="align-items: normal;">
        <div class="stats-grid">
            <div class="stat-card" id="damushavebuliCard">
                <div class="stat-value"><?= $soldDeals ?></div>
                <div class="stat-label">დამუშავებული ლიდები (WON/LOSE)</div>
            </div>
            <div class="stat-card green" id="gayidulebiCard">
                <div class="stat-value"><?= $wonDealsCount ?></div>
                <div class="stat-label">გაყიდული გარიგებები (WON)</div>
            </div>
            <div class="stat-card red" id="dalostilebiCard">
                <div class="stat-value"><?= $lostDealsCount ?></div>
                <div class="stat-label">დალოსთილი გარიგებები (LOST)</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-value"><?= $qualifiedDeals ?></div>
                <div class="stat-label">კვალიფიცირებული ლიდები</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-value"><?= $nonQualifiedDeals ?></div>
                <div class="stat-label">არაკვალიფიცირებული ლიდები</div>
            </div>
            <div class="stat-card" id="shexvedrebiCard">
                <div class="stat-value"><?= $meetingCompletedCount ?></div>
                <div class="stat-label">ჩატარებული შეხვედრები</div>
            </div>
        </div>
    </div>

    <!-- Monthly Deals Graph -->
    <div class="report-section">
        <div class="section-title">DEALS DYNAMICS BY MONTH</div>
        <div class="chart-container">
            <canvas id="monthlyDealsChart"></canvas>
        </div>
    </div>

    <div class="reportebi">
        <!-- Sold Deals by User -->
        <div class="report-section" id="soldByUser">
            <div class="section-title">დამუშავებული ლიდები მენეჯერების მიხედვით</div>
            <div class="table-wrapper collapsed" id="soldByUserWrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>მენეჯერი</th>
                            <th style="text-align: center;">დამუშავებული ლიდების რაოდენობა</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($soldDealsByUser)): ?>
                            <tr>
                                <td colspan="2" class="empty-state">არ მოიძებნა დამუშავებული ლიდები</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($soldDealsByUser as $userId => $userData): ?>
                                <tr data-user-id="<?= $userId ?>">
                                    <td><?= htmlspecialchars($userData['name']) ?></td>
                                    <td style="text-align: center;">
                                        <span class="count-badge">
                                            <?= $userData['count'] ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td><strong>Total</strong></td>
                            <td style="text-align: center;">
                                <span class="count-badge total-badge">
                                    <?= $soldDeals ?>
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php if(count($soldDealsByUser) > 5): ?>
                <button class="toggle-button" onclick="toggleTable('soldByUserWrapper', this)">
                    მეტის ნახვა
                </button>
            <?php endif; ?>
        </div>
        
        <!-- Status Breakdown -->
        <div class="report-section" id="qualities">
            <div class="section-title">ლიდების ანალიტიკა ხარისხების მიხედვით</div>
            <div class="table-wrapper collapsed" id="qualitiesWrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ხარისხი</th>
                            <th style="text-align: center;">Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($statusCounts as $statusName => $statusData): ?>
                            <tr data-status-name="<?= htmlspecialchars($statusName, ENT_QUOTES) ?>">
                                <td><?= htmlspecialchars($statusName) ?></td>
                                <td style="text-align: center;">
                                    <span class="count-badge">
                                        <?= $statusData['count'] ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td><strong>Total</strong></td>
                            <td style="text-align: center;">
                                <span class="count-badge total-badge">
                                    <?= array_sum(array_column($statusCounts, 'count')) ?>
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php if(count($statusCounts) > 5): ?>
                <button class="toggle-button" onclick="toggleTable('qualitiesWrapper', this)">
                    მეტის ნახვა
                </button>
            <?php endif; ?>
        </div>

        <!-- Lost Deals by Reason -->
        <div class="report-section" id="lostReason">
            <div class="section-title">ლოსთების რაოდენობა და მიზეზები</div>
            <div class="table-wrapper collapsed" id="lostReasonWrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>მიზეზი</th>
                            <th style="text-align: center;">Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($lostByReason as $reasonName => $reasonData): ?>
                            <tr data-reason-name="<?= htmlspecialchars($reasonName, ENT_QUOTES) ?>">
                                <td><?= htmlspecialchars($reasonName) ?></td>
                                <td style="text-align: center;">
                                    <span class="count-badge">
                                        <?= $reasonData['count'] ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td><strong>Total</strong></td>
                            <td style="text-align: center;">
                                <span class="count-badge total-badge">
                                    <?= array_sum(array_column($lostByReason, 'count')) ?>
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php if(count($lostByReason) > 5): ?>
                <button class="toggle-button" onclick="toggleTable('lostReasonWrapper', this)">
                    მეტის ნახვა
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Shexvedrebi Modal -->
    <div id="shexvedrebiModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">ჩატარებული შეხვედრები</h3>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>გარიგება</th>
                        <th>მენეჯერი</th>
                        <th>შეხვედრის დრო</th>
                        <th>ეტაპი</th>
                    </tr>
                </thead>
                <tbody id="shexvedrebiBody"></tbody>
            </table>

            <button onclick="closeModal('shexvedrebiModal')" class="modal-close">
                დახურვა
            </button>
        </div>
    </div>

    <!-- Damushavebuli Modal -->
    <div id="damushavebuliModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">დამუშავებული ლიდები (WON/LOSE)</h3>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>გარიგება</th>
                        <th>მენეჯერი</th>
                        <th>თარიღი</th>
                        <th>ეტაპი</th>
                    </tr>
                </thead>
                <tbody id="damushavebuliBody"></tbody>
            </table>

            <button onclick="closeModal('damushavebuliModal')" class="modal-close">
                დახურვა
            </button>
        </div>
    </div>

    <!-- Quality Modal -->
    <div id="qualityModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="qualityModalTitle">ლიდები</h3>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>გარიგება</th>
                        <th>მენეჯერი</th>
                        <th>თარიღი</th>
                        <th>ეტაპი</th>
                    </tr>
                </thead>
                <tbody id="qualityBody"></tbody>
            </table>

            <button onclick="closeModal('qualityModal')" class="modal-close">
                დახურვა
            </button>
        </div>
    </div>

    <!-- Lost Reason Modal -->
    <div id="lostReasonModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="lostReasonModalTitle">დაკარგული გარიგებები</h3>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>გარიგება</th>
                        <th>მენეჯერი</th>
                        <th>თარიღი</th>
                        <th>ეტაპი</th>
                    </tr>
                </thead>
                <tbody id="lostReasonBody"></tbody>
            </table>

            <button onclick="closeModal('lostReasonModal')" class="modal-close">
                დახურვა
            </button>
        </div>
    </div>

    <!-- User Sold Deals Modal -->
    <div id="userSoldModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="userSoldModalTitle">დამუშავებული ლიდები</h3>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>გარიგება</th>
                        <th>თარიღი</th>
                        <th>ეტაპი</th>
                    </tr>
                </thead>
                <tbody id="userSoldBody"></tbody>
            </table>

            <button onclick="closeModal('userSoldModal')" class="modal-close">
                დახურვა
            </button>
        </div>
    </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
    const shexvedrebiDeals = <?= json_encode($shexvedrebiDeals, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const damushavebuliDeals = <?= json_encode($damushavebuliDeals, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const statusCounts = <?= json_encode($statusCounts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const lostByReason = <?= json_encode($lostByReason, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const dealsByMonth = <?= json_encode(array_values($dealsByMonth), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    const wonDeals  = damushavebuliDeals.filter(d => d.STAGE_ID.includes('WON'));
    const lostDeals = damushavebuliDeals.filter(d => d.STAGE_ID.includes('LOSE'));
    
    function toggleTable(wrapperId, button) {
        const wrapper = document.getElementById(wrapperId);
        if (!wrapper) return;
        
        if (wrapper.classList.contains('collapsed')) {
            wrapper.classList.remove('collapsed');
            button.innerHTML = 'ნაკლების ნახვა';
        } else {
            wrapper.classList.add('collapsed');
            button.innerHTML = 'მეტის ნახვა';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Shexvedrebi card
        const shexvedrebiCard = document.getElementById('shexvedrebiCard');
        if (shexvedrebiCard) {
            shexvedrebiCard.addEventListener('click', openShexvedrebiModal);
        }

        // Damushavebuli card
        const damushavebuliCard = document.getElementById('damushavebuliCard');
        if (damushavebuliCard) {
            damushavebuliCard.addEventListener('click', openDamushavebuliModal);
        }

        const gayidulebiCard = document.getElementById('gayidulebiCard');
        if (gayidulebiCard) {
            gayidulebiCard.addEventListener('click', openGayiduliModal);
        }

        const dalostilebiCard = document.getElementById('dalostilebiCard');
        if (dalostilebiCard) {
            dalostilebiCard.addEventListener('click', openDalostiliModal);
        }


        // Quality table rows
        const qualityRows = document.querySelectorAll('#qualities tbody tr[data-status-name]');
        qualityRows.forEach(row => {
            row.addEventListener('click', function() {
                const statusName = this.getAttribute('data-status-name');
                if (statusName) {
                    openQualityModal(statusName);
                }
            });
        });

        // Lost reason table rows
        const lostReasonRows = document.querySelectorAll('#lostReason tbody tr[data-reason-name]');
        lostReasonRows.forEach(row => {
            row.addEventListener('click', function() {
                const reasonName = this.getAttribute('data-reason-name');
                if (reasonName) {
                    openLostReasonModal(reasonName);
                }
            });
        });

        // User sold deals table rows
        const userSoldRows = document.querySelectorAll('#soldByUser tbody tr[data-user-id]');
        userSoldRows.forEach(row => {
            row.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                if (userId) {
                    openUserSoldModal(userId);
                }
            });
        });
    });

    function openShexvedrebiModal() {
        const tbody = document.getElementById('shexvedrebiBody');
        const modal = document.getElementById('shexvedrebiModal');
        
        if (!tbody || !modal) return;
        
        tbody.innerHTML = '';

        if (!shexvedrebiDeals || shexvedrebiDeals.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 20px;">არ მოიძებნა შეხვედრები</td></tr>';
        } else {
            shexvedrebiDeals.forEach(deal => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <a href="${deal.LINK}" target="_blank" style="color: #3b82f6; text-decoration: none;">
                            გარიგება #${deal.ID}
                        </a>
                    </td>
                    <td>${deal.ASSIGNED_BY_NAME}</td>
                    <td>${deal.CUSTOM_FIELD || 'N/A'}</td>
                    <td>${deal.STAGE_ID}</td>
                `;
                tbody.appendChild(row);
            });
        }

        modal.style.display = 'flex';
    }

    function renderDealsModal(deals, titleText) {
        const tbody = document.getElementById('damushavebuliBody');
        const modal = document.getElementById('damushavebuliModal');
        const title = modal.querySelector('.modal-title');

        if (!tbody || !modal) return;

        if (titleText) {
            title.textContent = titleText;
        }

        tbody.innerHTML = '';

        if (!deals || deals.length === 0) {
            tbody.innerHTML =
                '<tr><td colspan="4" style="text-align:center; padding:20px;">არ მოიძებნა გარიგებები</td></tr>';
        } else {
            deals.forEach(deal => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <a href="${deal.LINK}" target="_blank" style="color:#3b82f6;text-decoration:none;">
                            გარიგება #${deal.ID}
                        </a>
                    </td>
                    <td>${deal.ASSIGNED_BY_NAME}</td>
                    <td>${deal.DATE_CREATE || '-'}</td>
                    <td>${deal.STAGE_ID}</td>
                `;
                tbody.appendChild(row);
            });
        }

        modal.style.display = 'flex';
    }

    function openDamushavebuliModal() {
        const tbody = document.getElementById('damushavebuliBody');
        const modal = document.getElementById('damushavebuliModal');
        
        if (!tbody || !modal) return;
        
        tbody.innerHTML = '';

        if (!damushavebuliDeals || damushavebuliDeals.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 20px;">არ მოიძებნა დამუშავებული ლიდები</td></tr>';
        } else {
            damushavebuliDeals.forEach(deal => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <a href="${deal.LINK}" target="_blank" style="color: #3b82f6; text-decoration: none;">
                            გარიგება #${deal.ID}
                        </a>
                    </td>
                    <td>${deal.ASSIGNED_BY_NAME}</td>
                    <td>${deal.DATE_CREATE}</td>
                    <td>${deal.STAGE_ID}</td>
                `;
                tbody.appendChild(row);
            });
        }

        modal.style.display = 'flex';
    }

    function openGayiduliModal() {
        renderDealsModal(wonDeals, 'გაყიდული გარიგებები (WON)');
    }

    function openDalostiliModal() {
        renderDealsModal(lostDeals, 'დალოსთილი გარიგებები (LOST)');
    }

    function openQualityModal(statusName) {
        const tbody = document.getElementById('qualityBody');
        const modal = document.getElementById('qualityModal');
        const title = document.getElementById('qualityModalTitle');
        
        if (!tbody || !modal || !title) return;
        
        title.textContent = `ლიდები: ${statusName}`;
        tbody.innerHTML = '';

        const deals = statusCounts[statusName] ? statusCounts[statusName].deals : [];

        if (!deals || deals.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 20px;">არ მოიძებნა ლიდები</td></tr>';
        } else {
            deals.forEach(deal => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <a href="${deal.LINK}" target="_blank" style="color: #3b82f6; text-decoration: none;">
                            გარიგება #${deal.ID}
                        </a>
                    </td>
                    <td>${deal.ASSIGNED_BY_NAME}</td>
                    <td>${deal.DATE_CREATE}</td>
                    <td>${deal.STAGE_ID}</td>
                `;
                tbody.appendChild(row);
            });
        }

        modal.style.display = 'flex';
    }

    function openLostReasonModal(reasonName) {
        const tbody = document.getElementById('lostReasonBody');
        const modal = document.getElementById('lostReasonModal');
        const title = document.getElementById('lostReasonModalTitle');
        
        if (!tbody || !modal || !title) return;
        
        title.textContent = `დაკარგული გარიგებები: ${reasonName}`;
        tbody.innerHTML = '';

        const deals = lostByReason[reasonName] ? lostByReason[reasonName].deals : [];

        if (!deals || deals.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 20px;">არ მოიძებნა გარიგებები</td></tr>';
        } else {
            deals.forEach(deal => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <a href="${deal.LINK}" target="_blank" style="color: #3b82f6; text-decoration: none;">
                            გარიგება #${deal.ID}
                        </a>
                    </td>
                    <td>${deal.ASSIGNED_BY_NAME}</td>
                    <td>${deal.DATE_CREATE}</td>
                    <td>${deal.STAGE_ID}</td>
                `;
                tbody.appendChild(row);
            });
        }

        modal.style.display = 'flex';
    }

    function openUserSoldModal(userId) {
        const tbody = document.getElementById('userSoldBody');
        const modal = document.getElementById('userSoldModal');
        const title = document.getElementById('userSoldModalTitle');
        
        if (!tbody || !modal || !title) return;
        
        // Find deals for this user
        const userDeals = damushavebuliDeals.filter(deal => deal.ASSIGNED_BY_ID == userId);
        
        if (userDeals.length > 0) {
            title.textContent = `დამუშავებული ლიდები: ${userDeals[0].ASSIGNED_BY_NAME}`;
        } else {
            title.textContent = 'დამუშავებული ლიდები';
        }
        
        tbody.innerHTML = '';

        if (!userDeals || userDeals.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 20px;">არ მოიძებნა ლიდები</td></tr>';
        } else {
            userDeals.forEach(deal => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <a href="${deal.LINK}" target="_blank" style="color: #3b82f6; text-decoration: none;">
                            გარიგება #${deal.ID}
                        </a>
                    </td>
                    <td>${deal.DATE_CREATE}</td>
                    <td>${deal.STAGE_ID}</td>
                `;
                tbody.appendChild(row);
            });
        }

        modal.style.display = 'flex';
    }

    // Initialize Chart.js
    const ctx = document.getElementById('monthlyDealsChart');
    if (ctx && dealsByMonth.length > 0) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: dealsByMonth.map(item => item.label),
                datasets: [{
                    label: 'Deals',
                    data: dealsByMonth.map(item => item.count),
                    backgroundColor: 'rgb(59, 131, 246)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
        }
    }

    // Close modal when clicking outside
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay').forEach(modal => {
                modal.style.display = 'none';
            });
        }
    });
</script>
