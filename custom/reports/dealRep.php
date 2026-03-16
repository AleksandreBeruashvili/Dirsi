<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Marketing Statistics");
CJSCore::Init(array("jquery"));
CJSCore::Init(array("jquery", "calendar"));

use Bitrix\Crm\Binding\EntityBinding;
use Bitrix\Crm\Item;
use Bitrix\Crm\ProductRow;
use Bitrix\Crm\ProductRows;
use Bitrix\Crm\Service;

CModule::IncludeModule('crm');
CModule::IncludeModule('catalog');
ob_end_clean();

function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}
global $USER;
$user_id = $USER->GetID();

$authorizedUser = false;
if (empty($user_id)) {
    $USER->Authorize(1);
    $authorizedUser = true;
}


function getCIBlockElementsByFilter($arFilter = array()) {
    $arElements = array();
    $arSelect = Array("ID","IBLOCK_ID","NAME","DATE_ACTIVE_FROM","PROPERTY_*","DATE_CREATE","_xelsh","_mimdinare","TO_DATE","FROM_THE_DATE" );
    $res = CIBlockElement::GetList(Array(), $arFilter,  $arSelect);
    while($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];
        array_push($arElements, $arPushs);
    }
    return $arElements;
}

function divideIntoMonths($start_date, $end_date) {
    $months = [];
    $start = DateTime::createFromFormat('d/m/Y', $start_date);
    $end = DateTime::createFromFormat('d/m/Y', $end_date);
    $start->modify('first day of this month');
    while ($start <= $end) {
        $month_start = clone $start;
        $month_end = clone $start;
        $month_end->modify('last day of this month')->setTime(23, 59, 59);
        if ($month_end > $end) {
            $month_end = clone $end;
        }
        $months[] = [
                'start' => $month_start->format('d/m/Y'),
                'end' => $month_end->format('d/m/Y')
        ];
        $start->modify('first day of next month');
    }
    return $months;
}


function getDealsByFilter($arFilter, $arSelect = array(), $arSort = array("ID"=>"DESC")) {
    $arDeals = array();
    $arSelect = array("ID","OPPORTUNITY", "STAGE_ID", "DATE_CREATE", "SOURCE_ID", "ASSIGNED_BY_ID", "UF_CRM_1761575156657");

    if (isset($arFilter[">=DATE_CREATE"]) && empty($arFilter[">=DATE_CREATE"])) {
        error_log("ERROR: Empty start date in filter");
        return false;
    }
    if (isset($arFilter["<=DATE_CREATE"]) && empty($arFilter["<=DATE_CREATE"])) {
        error_log("ERROR: Empty end date in filter");
        return false;
    }

    try {
        $res = CCrmDeal::GetListEx($arSort, $arFilter, false, false, $arSelect);
        while ($arDeal = $res->Fetch()) {
            array_push($arDeals, $arDeal);
        }
    } catch (Exception $e) {
        error_log("Database query error: " . $e->getMessage());
        error_log("Filter was: " . print_r($arFilter, true));
        return false;
    }

    return (count($arDeals) > 0) ? $arDeals : false;
}

function getUserName($id)
{
    $res = CUser::GetByID($id)->Fetch();
    return $res["NAME"] . " " . $res["LAST_NAME"];
}

$currentDate = new DateTime();
$firstDayOfMonth = new DateTime('first day of this month');

// ✅ FIX: date() მეთოდი d/m/Y ფორმატით (Bitrix-ის სტანდარტი)
if (!empty($_GET['endDate'])) {
    $dateineed = date('d/m/Y', strtotime($_GET['endDate']));
} else {
    $dateineed = $currentDate->format('d/m/Y');
}

if (!empty($_GET['startDate'])) {
    $startdateineed = date('d/m/Y', strtotime($_GET['startDate']));
} else {
    $startdateineed = $firstDayOfMonth->format('d/m/Y');
}

error_log("Start date for query: " . $startdateineed);
error_log("End date for query: " . $dateineed);

$arfilter = array(
        ">=DATE_CREATE" => $startdateineed,
        "<=DATE_CREATE" => $dateineed . " 23:59:59",
        "CATEGORY_ID" => 0,
);

error_log("Filter being used: " . print_r($arfilter, true));

$deals = getDealsByFilter($arfilter);

error_log("Deals returned: " . (is_array($deals) ? count($deals) : 'false/null'));

if ($deals === false || empty($deals)) {
    error_log("WARNING: No deals found!");
    $testFilter = array("CATEGORY_ID" => 0);
    $testDeals = getDealsByFilter($testFilter);
    error_log("Test query (all deals in category 0): " . (is_array($testDeals) ? count($testDeals) : 'none'));
}

// ✅ FIX: ცვლადების ინიციალიზაცია if($deals)-ის გარეთ
$dealsdata = [];
$managers = [];
$sources = [];
$managerNames = [];
$sourceNames = [];
$managerNamesList = [];
$sourceNamesList = [];

$fbData = array(
        "Leads" => 0,
        "QL" => 0,
        "NonQualified" => 0,
        "Meetings Scheduled" => 0,
        "Meetings Completed" => 0,
        "Unsuccessful" => 0,
        "WON" => 0,
        "start" => $startdateineed,
        "end" => $dateineed,
        "total_revenue" => 0
);

$reasonMap = [];
$rsEnum = \CUserFieldEnum::GetList([], ['USER_FIELD_NAME' => 'UF_CRM_1761575156657']);
while ($arEnum = $rsEnum->GetNext()) {
    $reasonMap[$arEnum['ID']] = $arEnum['VALUE'];
}

if ($deals) {
    foreach ($deals as $deal) {

        $fbData["Leads"]++;

        $reasonId = $deal['UF_CRM_1761575156657'];

        if (is_array($reasonId)) {
            $reasonNames = array_map(function($id) use ($reasonMap) {
                return $reasonMap[$id] ?? 'UR';
            }, $reasonId);
            $reasonName = implode(', ', $reasonNames);
        } else {
            $reasonName = $reasonMap[$reasonId] ?? 'UR';
        }

        $stageId = $deal["STAGE_ID"];
        $stageName = \Bitrix\Crm\StatusTable::getList([
                'filter' => ['ENTITY_ID' => 'DEAL_STAGE', 'STATUS_ID' => $stageId],
                'select' => ['NAME']
        ])->fetch()['NAME'];

        $dealInfo = [
                "dateCreate" => $deal["DATE_CREATE"],
                "id" => $deal["ID"],
                "manager" => getUserName($deal['ASSIGNED_BY_ID']),
                "source" => \Bitrix\Crm\StatusTable::getList([
                        'filter' => ['ENTITY_ID' => 'SOURCE', 'STATUS_ID' => $deal['SOURCE_ID']],
                        'select' => ['NAME']
                ])->fetch()['NAME'],
                "stage" => $stageName,
                "stageId" => $stageId,
                "opportunity" => floatval($deal["OPPORTUNITY"]),
                "reason" => $reasonName
        ];

        $dealsdata[] = $dealInfo;
        $managers[] = $deal['ASSIGNED_BY_ID'];
        $sources[] = $deal['SOURCE_ID'];

        if (in_array($deal["STAGE_ID"], array("UC_2OKWI1", "UC_NOUE6K", "UC_2IVED4", "UC_XG2GSV", "UC_ZODBLJ", "UC_7XY1I6", "6", "8", "WON"))) {
            $fbData["QL"]++;
        }
        if (in_array($deal["STAGE_ID"], array("UC_NOUE6K", "UC_2IVED4", "UC_XG2GSV", "UC_ZODBLJ", "UC_7XY1I6", "6", "8", "WON"))) {
            $fbData["Meetings Scheduled"]++;
        }
        if (in_array($deal["STAGE_ID"], array("UC_NOUE6K", "UC_2IVED4", "UC_XG2GSV", "UC_ZODBLJ", "UC_7XY1I6", "6", "8", "WON"))) {
            $fbData["Meetings Completed"]++;
        }
        if (in_array($deal["STAGE_ID"], array("LOSE"))) {
            $fbData["Unsuccessful"]++;
        }
        if (in_array($deal["STAGE_ID"], array("WON"))) {
            $fbData["WON"]++;
            $fbData["total_revenue"] += floatval($deal["OPPORTUNITY"]);
        }
    }

    $managers = array_unique($managers);
    foreach ($managers as $managerId) {
        $managerNames[$managerId] = getUserName($managerId);
    }

    $sources = array_unique($sources);
    foreach ($sources as $sourceId) {
        $sourceNames[$sourceId] = \Bitrix\Crm\StatusTable::getList([
                'filter' => ['ENTITY_ID' => 'SOURCE', 'STATUS_ID' => $sourceId],
                'select' => ['NAME']
        ])->fetch()['NAME'];
    }
}

if($managerNames){
    $managerNamesList = array_values($managerNames);
}
if($sourceNames){
    $sourceNamesList = array_values($sourceNames);
}

if ($authorizedUser) {
    $USER->Logout();
}
$user_id = $USER->GetID();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deals Report Dashboard</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: #333;
        }

        .container {
            max-width: 2000px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 1.8em;
            color: #2c3e50;
            font-weight: 600;
        }

        .date-filter {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .date-filter input, .date-filter label {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: white;
            font-size: 14px;
        }

        .date-filter label {
            border: none;
            background: none;
            font-weight: 500;
            color: #666;
            padding: 8px 5px;
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 2.2em;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.85em;
            color: #6c757d;
            font-weight: 500;
        }

        .conversion-card .stat-number {
            color: #007bff;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: 300px;
        }

        .chart-container canvas {
            max-height: 280px !important;
        }

        .chart-title {
            font-size: 1.1em;
            font-weight: 600;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .bottom-section {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }

        .table-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .performance-table {
            width: 100%;
            border-collapse: collapse;
        }

        .performance-table th {
            text-align: left;
            padding: 12px 8px;
            border-bottom: 2px solid #f1f3f4;
            font-weight: 600;
            color: #5f6368;
            font-size: 0.85em;
        }

        .performance-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #f1f3f4;
            font-size: 0.9em;
        }

        .performance-table tr:hover {
            background: #f8f9fa;
        }

        .manager-name {
            color: #1a73e8;
            font-weight: 500;
        }

        .source-name {
            color: #137333;
            font-weight: 500;
        }

        .reason-name {
            color: rgb(210 17 15);
            font-weight: 500;
        }

        .conversion-bar {
            display: inline-block;
            width: 60px;
            height: 4px;
            background: #e8f0fe;
            border-radius: 2px;
            margin-right: 8px;
            position: relative;
        }

        .conversion-fill {
            height: 100%;
            background: #1a73e8;
            border-radius: 2px;
        }

        .status-chart-container {
            position: relative;
            height: 200px;
        }

        .refresh-btn {
            padding: 8px 16px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
        }

        .refresh-btn:hover {
            background: #0056b3;
        }

        @media (max-width: 1024px) {
            .charts-grid { grid-template-columns: 1fr; }
            .bottom-section { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 15px; }
            .date-filter { flex-wrap: wrap; }
        }

        .redirect-btn {
            margin-left: 10px;
            background-color: #4caf50;
            color: white;
            border: none;
            padding: 8px 16px;
            font-size: 14px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out;
        }

        .redirect-btn:hover { background-color: #45a049; }

        .hidden-row { display: none; }

        .toggle-btn {
            margin-top: 8px;
            background-color: #e0e0e0;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }

        .toggle-btn:hover { background-color: #ccc; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>DEALS REPORT</h1>
        <div class="date-filter">
            <form method="get" class="filter-form">
                <label for="startDate">From:</label>
                <input type="date" id="startDate" name="startDate">
                <label for="endDate">To:</label>
                <input type="date" id="endDate" name="endDate">
                <button type="submit" class="refresh-btn">Refresh</button>
                <button style="display:none;" type="button" class="redirect-btn" onclick="redirectToLifeCycle()">Go to Lifecycle Report</button>
            </form>
        </div>
    </div>

    <div class="stats-overview">
        <div class="stat-card">
            <div class="stat-number" id="totalLeads">0</div>
            <div class="stat-label">Leads.</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="confirmedLeads">0</div>
            <div class="stat-label">QL.</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="nonQualified">0</div>
            <div class="stat-label">NonQL.</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="Scheduled">0</div>
            <div class="stat-label">Meetings Scheduled.</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="Completed">0</div>
            <div class="stat-label">Meetings Completed.</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="inDealLeads">0</div>
            <div class="stat-label">Won.</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="unsuccessfulLeads">0</div>
            <div class="stat-label">Junk.</div>
        </div>
        <div class="stat-card conversion-card">
            <div class="stat-number" id="conversionRate">0%</div>
            <div class="stat-label">Conversion</div>
        </div>
    </div>

    <div class="charts-grid">
        <div class="chart-container">
            <div class="chart-title">DEALS DYNAMICS BY MONTH.</div>
            <canvas id="leadsChart"></canvas>
        </div>
        <div class="chart-container">
            <div class="chart-title">DEALS BY STATUS TYPE, %</div>
            <div class="status-chart-container">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

    <div class="bottom-section">
        <div class="table-container">
            <div class="chart-title">DEALS AND CONVERSION BY MANAGERS</div>
            <table class="performance-table">
                <thead>
                <tr>
                    <th>Manager</th>
                    <th>Leads.</th>
                    <th>In Work</th>
                    <th>Won.</th>
                    <th>Junk</th>
                    <th>Conversion</th>
                </tr>
                </thead>
                <tbody id="managersTable"></tbody>
            </table>
        </div>

        <div class="table-container">
            <div class="chart-title">DEALS AND CONVERSION BY SOURCES</div>
            <table class="performance-table">
                <thead>
                <tr>
                    <th>Source</th>
                    <th>Leads.</th>
                    <th>Won.</th>
                    <th>Conversion</th>
                </tr>
                </thead>
                <tbody id="sourcesTable"></tbody>
            </table>
        </div>

        <div class="table-container">
            <div class="chart-title">DEALS AND CONVERSION BY JUNK REASONS</div>
            <table class="performance-table">
                <thead>
                <tr>
                    <th>Junk Reason</th>
                    <th>Leads.</th>
                    <th>Conversion</th>
                </tr>
                </thead>
                <tbody id="reasonChart"></tbody>
            </table>
        </div>
    </div>

    <div class="table-wrapper1">
        <div class="table-container1" id="dealTableContainer1"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    let dateandfilter1   = <?php echo json_encode($dateineed); ?>;
    let datestartfilter1 = <?php echo json_encode($startdateineed); ?>;
    let fbData           = <?php echo json_encode($fbData); ?>;
    let managerNamesList = <?php echo json_encode($managerNamesList ?? []); ?>;
    let sourceNamesList  = <?php echo json_encode($sourceNamesList ?? []); ?>;
    let dealsdata        = <?php echo json_encode($dealsdata ?? []); ?>;
    let reasonMap        = <?php echo json_encode($reasonMap); ?>;
    let dataProcessor;
    let leadsChart, statusChart, reasonChart;
    let datestartfilter = '';
    let dateandfilter = '';

    class LeadsDataProcessor {
        constructor() {
            this.rawDeals = dealsdata || [];
            this.processedData = this.processDeals();
        }

        processDeals() {
            const processed = {
                totalDeals: this.rawDeals.length,
                Qualified: 0,
                Scheduled: 0,
                Completed: 0,
                warmLeads2: 0,
                wonDeals: 0,
                failedDeals: 0,
                totalRevenue: 0,
                managerStats: {},
                sourceStats: {},
                monthlyData: {},
                statusStats: {},
                reasonStats: {}
            };

            function parseDateFromString(dateString) {
                try {
                    if (!dateString) return new Date();
                    let cleanDate = dateString.replace(/\./g, '/');
                    const [datePart, timePart] = cleanDate.split(' ');
                    const [day, month, year] = datePart.split('/');
                    if (!day || !month || !year) {
                        console.warn('Invalid date format:', dateString);
                        return new Date();
                    }
                    return new Date(parseInt(year), parseInt(month) - 1, parseInt(day));
                } catch (error) {
                    console.error('Error parsing date:', dateString, error);
                    return new Date();
                }
            }

            this.rawDeals.forEach(deal => {
                if (["UC_2OKWI1", "UC_NOUE6K", "UC_2IVED4", "UC_XG2GSV", "UC_ZODBLJ", "UC_7XY1I6", "6", "8", "WON"].includes(deal.stageId)) {
                    processed.Qualified++;
                }
                if (["UC_NOUE6K", "UC_2IVED4", "UC_XG2GSV", "UC_ZODBLJ", "UC_7XY1I6", "6", "8", "WON"].includes(deal.stageId)) {
                    processed.Scheduled++;
                }
                if (["UC_XG2GSV", "UC_ZODBLJ", "UC_7XY1I6", "6", "8", "WON"].includes(deal.stageId)) {
                    processed.Completed++;
                }
                if (deal.stageId == 'LOSE') {
                    processed.failedDeals++;
                }
                if (deal.stageId == 'WON') {
                    processed.wonDeals++;
                    processed.totalRevenue += (deal.opportunity || 0);
                }

                if (!processed.managerStats[deal.manager]) {
                    processed.managerStats[deal.manager] = { leads: 0, junk: 0, won: 0, revenue: 0 };
                }
                processed.managerStats[deal.manager].leads++;
                if (deal.stageId === 'WON') {
                    processed.managerStats[deal.manager].won++;
                    processed.managerStats[deal.manager].revenue += (deal.opportunity || 0);
                }
                if (deal.stageId == 'LOSE') {
                    processed.managerStats[deal.manager].junk++;
                }

                if (deal.source && !processed.sourceStats[deal.source]) {
                    processed.sourceStats[deal.source] = { leads: 0, won: 0 };
                }
                if (deal.source) {
                    processed.sourceStats[deal.source].leads++;
                    if (deal.stageId === 'WON') {
                        processed.sourceStats[deal.source].won++;
                    }
                }

                if (deal.reason && !processed.reasonStats[deal.reason]) {
                    processed.reasonStats[deal.reason] = { leads: 0, won: 0 };
                }
                if (deal.reason) {
                    processed.reasonStats[deal.reason].leads++;
                    if (deal.stageId === 'WON') {
                        processed.reasonStats[deal.reason].won++;
                    }
                }

                const dateObj = parseDateFromString(deal.dateCreate);
                const monthKey = dateObj.getFullYear() + '-' + (dateObj.getMonth() + 1).toString().padStart(2, '0');

                if (!processed.monthlyData[monthKey]) {
                    processed.monthlyData[monthKey] = { total: 0, confirmed: 0, won: 0, revenue: 0 };
                }

                processed.monthlyData[monthKey].total++;
                if (["UC_2OKWI1", "UC_NOUE6K", "UC_2IVED4", "UC_XG2GSV", "UC_ZODBLJ", "UC_7XY1I6", "6", "8", "WON"].includes(deal.stageId)) {
                    processed.monthlyData[monthKey].confirmed++;
                }
                if (deal.stageId === 'WON') {
                    processed.monthlyData[monthKey].won++;
                    processed.monthlyData[monthKey].revenue += (deal.opportunity || 0);
                }

                if (!processed.statusStats[deal.stageId]) {
                    processed.statusStats[deal.stageId] = 0;
                }
                processed.statusStats[deal.stageId]++;
            });

            return processed;
        }
    }

    function updateStats() {
        const data = dataProcessor.processedData;
        if (document.getElementById('totalLeads'))
            document.getElementById('totalLeads').textContent = data.totalDeals.toLocaleString();
        if (document.getElementById('confirmedLeads'))
            document.getElementById('confirmedLeads').textContent = data.Qualified.toLocaleString();
        if (document.getElementById('nonQualified'))
            document.getElementById('nonQualified').textContent = (data.totalDeals - data.Qualified).toLocaleString();
        if (document.getElementById('Scheduled'))
            document.getElementById('Scheduled').textContent = data.Scheduled.toLocaleString();
        if (document.getElementById('Completed'))
            document.getElementById('Completed').textContent = data.Completed.toLocaleString();
        if (document.getElementById('unsuccessfulLeads'))
            document.getElementById('unsuccessfulLeads').textContent = data.failedDeals.toLocaleString();
        if (document.getElementById('inDealLeads'))
            document.getElementById('inDealLeads').textContent = data.wonDeals.toLocaleString();
        const conversionRate = data.totalDeals > 0 ? (data.wonDeals / data.totalDeals * 100).toFixed(2) : '0.00';
        if (document.getElementById('conversionRate'))
            document.getElementById('conversionRate').textContent = conversionRate + '%';
        if (document.getElementById('totalRevenue'))
            document.getElementById('totalRevenue').textContent = data.totalRevenue.toLocaleString() + ' ₾';
    }

    function createLeadsChart() {
        const ctx = document.getElementById('leadsChart');
        if (!ctx) return;

        const data = dataProcessor.processedData;
        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');

        let startDate = startDateInput && startDateInput.value ? new Date(startDateInput.value) : null;
        let endDate   = endDateInput   && endDateInput.value   ? new Date(endDateInput.value)   : null;

        const monthlyEntries = Object.entries(data.monthlyData).sort((a, b) => a[0].localeCompare(b[0]));
        let allMonths = [];

        const buildRange = (sy, sm, ey, em) => {
            let cy = sy, cm = sm;
            while (cy < ey || (cy === ey && cm <= em)) {
                allMonths.push(cy + '-' + cm.toString().padStart(2, '0'));
                cm++; if (cm > 12) { cm = 1; cy++; }
            }
        };

        if (startDate && endDate) {
            buildRange(startDate.getFullYear(), startDate.getMonth()+1, endDate.getFullYear(), endDate.getMonth()+1);
        } else if (startDate && !endDate) {
            const lastAvailableMonth = monthlyEntries.length > 0 ? monthlyEntries[monthlyEntries.length-1][0] : null;
            if (lastAvailableMonth) {
                const [ey, em] = lastAvailableMonth.split('-').map(Number);
                buildRange(startDate.getFullYear(), startDate.getMonth()+1, ey, em);
            }
        } else if (!startDate && endDate) {
            const firstAvailableMonth = monthlyEntries.length > 0 ? monthlyEntries[0][0] : null;
            if (firstAvailableMonth) {
                const [sy, sm] = firstAvailableMonth.split('-').map(Number);
                buildRange(sy, sm, endDate.getFullYear(), endDate.getMonth()+1);
            }
        } else {
            if (monthlyEntries.length > 0) {
                const [sy, sm] = monthlyEntries[0][0].split('-').map(Number);
                const [ey, em] = monthlyEntries[monthlyEntries.length-1][0].split('-').map(Number);
                buildRange(sy, sm, ey, em);
            }
        }

        const monthNames = {'01':'Jan','02':'Feb','03':'Mar','04':'Apr','05':'May','06':'Jun','07':'Jul','08':'Aug','09':'Sep','10':'Oct','11':'Nov','12':'Dec'};
        const monthLabels  = allMonths.map(m => { const [y,mo] = m.split('-'); return `${monthNames[mo]} ${y}`; });
        const monthlyTotal     = allMonths.map(m => data.monthlyData[m] ? data.monthlyData[m].total     : 0);
        const monthlyConfirmed = allMonths.map(m => data.monthlyData[m] ? data.monthlyData[m].confirmed : 0);
        const monthlyWon       = allMonths.map(m => data.monthlyData[m] ? data.monthlyData[m].won       : 0);
        const maxValue = Math.max(...monthlyConfirmed, ...monthlyWon, ...monthlyTotal);

        if (leadsChart) leadsChart.destroy();

        leadsChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: monthLabels,
                datasets: [
                    { label: 'Total', data: monthlyTotal,     backgroundColor: 'rgba(182,176,176,0.8)', borderColor: '#676763ff', borderWidth: 2, borderRadius: 5, borderSkipped: false },
                    { label: 'QL',    data: monthlyConfirmed, backgroundColor: 'rgba(70,130,180,0.8)',  borderColor: '#4682B4',   borderWidth: 2, borderRadius: 5, borderSkipped: false },
                    { label: 'WON',   data: monthlyWon,       backgroundColor: 'rgba(52,168,83,0.8)',   borderColor: '#34a853',   borderWidth: 2, borderRadius: 5, borderSkipped: false }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', align: 'start', labels: { usePointStyle: true, pointStyle: 'rect', padding: 20, font: { size: 12 } } },
                    tooltip: { mode: 'index', intersect: false, backgroundColor: 'rgba(0,0,0,0.8)', titleColor: 'white', bodyColor: 'white', borderColor: '#ccc', borderWidth: 1, callbacks: { label: (c) => c.dataset.label + ': ' + c.parsed.y.toLocaleString() } }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.1)', lineWidth: 1 }, ticks: { stepSize: Math.max(1, Math.ceil(maxValue/8)), font: { size: 11 }, color: '#666' } },
                    x: { grid: { display: false }, ticks: { font: { size: 11 }, color: '#666', maxRotation: 45 } }
                },
                interaction: { intersect: false, mode: 'index' }
            }
        });
    }

    function createStatusChart() {
        const ctx = document.getElementById('statusChart');
        if (!ctx) return;

        const data = dataProcessor?.processedData || {};
        const total  = data.totalDeals  || 0;
        const fail   = data.failedDeals || 0;
        const success= data.wonDeals    || 0;
        const inWork = total - (fail + success);

        const failPercent    = total > 0 ? (fail   / total) * 100 : 0;
        const successPercent = total > 0 ? (success/ total) * 100 : 0;
        const inWorkPercent  = total > 0 ? (inWork / total) * 100 : 0;

        if (statusChart) statusChart.destroy();

        statusChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['JUNK', 'WON', 'IN WORK'],
                datasets: [{ data: [failPercent, successPercent, inWorkPercent], backgroundColor: ['#ea4335', '#34a853', '#fbbc04'], borderWidth: 1, borderRadius: 5 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => c.label + ': ' + c.parsed.y.toFixed(1) + '% (' + Math.round(c.parsed.y/100*total) + ')' } } },
                scales: { y: { beginAtZero: true, max: 100, ticks: { callback: (v) => v + '%' } } }
            }
        });
    }

    function createReasonChart() {
        const tbody = document.getElementById('reasonChart');
        const data = dataProcessor.processedData;

        const reasonTranslations = {
            'არასწორი ნომერი': 'Wrong number',
            'ვერ დავუკავშირდი': "Couldn't reach",
            'პოლიტიკური სიტუაცია': 'Political situation',
            'შეცდომით დარეკა': 'Called by mistake',
            'აგენტი': 'Agent',
            'სოც. ქსელების ინფორმაციის დაზუსტება': 'Social media information clarification',
            'ინფორმაციის დაზუსტება': 'Information clarification',
            'ებრაელები': 'Jews',
            'აგენტის ლიდი': 'Agent lead',
            'ფასი': 'Price',
            'კვადრატულობა': 'Squareness',
            'ვადები': 'Deadlines',
            'არამიზნობრივი': 'Unintended',
            'ლოდინი': 'Waiting',
            'გაუმებული': 'Missing',
            'ძველი ბაზა': 'Old database',
            'სხვა': 'Other'
        };

        const sources = Object.entries(data.reasonStats)
            .map(([name, stats]) => ({
                name: reasonTranslations[name] || name,
                leads: stats.leads,
                conversion: stats.leads > 0 ? (stats.won / stats.leads * 100).toFixed(2) : '0.00'
            }))
            .sort((a, b) => b.leads - a.leads);

        tbody.innerHTML = sources.map((source, index) => `
            <tr class="reason-row ${index >= 4 ? 'hidden-row' : ''}">
                <td class="reason-name">${source.name}</td>
                <td>${source.leads}</td>
                <td>
                    <div class="conversion-bar"><div class="conversion-fill" style="width: ${Math.min(source.conversion * 2, 100)}%"></div></div>
                    ${source.conversion}%
                </td>
            </tr>`).join('');

        if (sources.length > 4) {
            const buttonRow = document.createElement('tr');
            buttonRow.innerHTML = `<td colspan="3" style="text-align:center"><button id="toggleReasonRows" class="toggle-btn">More</button></td>`;
            tbody.appendChild(buttonRow);
            document.getElementById("toggleReasonRows").addEventListener("click", function () {
                const isCollapsed = this.textContent === "More";
                document.querySelectorAll(".reason-row").forEach((row, idx) => { if (idx >= 4) row.style.display = isCollapsed ? "table-row" : "none"; });
                this.textContent = isCollapsed ? "Less" : "More";
            });
        }
    }

    function populateManagersTable() {
        const tbody = document.getElementById('managersTable');
        const data = dataProcessor.processedData;

        const allowedManagers = ['Ana Arabidze', 'Ano Gelovani', 'Gala Tsintsadze', 'Kristina Khimshiashvili', 'Mari Andguladze'];

        const managers = Object.entries(data.managerStats)
            //.filter(([name]) => allowedManagers.includes(name))
            .map(([name, stats]) => ({
                name,
                leads: stats.leads,
                inWork: stats.leads - (stats.won + stats.junk),
                won: stats.won,
                junk: stats.junk,
                conversion: stats.leads > 0 ? (stats.won / stats.leads * 100).toFixed(2) : '0.00'
            }))
            .sort((a, b) => b.leads - a.leads);

        tbody.innerHTML = managers.map((manager, index) => `
            <tr class="manager-row ${index >= 4 ? 'hidden-row' : ''}">
                <td class="manager-name">${manager.name}</td>
                <td>${manager.leads}</td>
                <td>${manager.inWork}</td>
                <td>${manager.won}</td>
                <td>${manager.junk}</td>
                <td>
                    <div class="conversion-bar"><div class="conversion-fill" style="width: ${Math.min(manager.conversion * 2, 100)}%"></div></div>
                    ${manager.conversion}%
                </td>
            </tr>`).join('');

        if (managers.length > 4) {
            const buttonRow = document.createElement('tr');
            buttonRow.innerHTML = `<td colspan="6" style="text-align:center"><button id="toggleManagerRows" class="toggle-btn">More</button></td>`;
            tbody.appendChild(buttonRow);
            document.getElementById("toggleManagerRows").addEventListener("click", function () {
                const isCollapsed = this.textContent === "More";
                document.querySelectorAll(".manager-row").forEach((row, idx) => { if (idx >= 4) row.style.display = isCollapsed ? "table-row" : "none"; });
                this.textContent = isCollapsed ? "Less" : "More";
            });
        }
    }

    function populateSourcesTable() {
        const tbody = document.getElementById('sourcesTable');
        const data = dataProcessor.processedData;

        const sources = Object.entries(data.sourceStats)
            .map(([name, stats]) => ({
                name,
                leads: stats.leads,
                won: stats.won,
                conversion: stats.leads > 0 ? (stats.won / stats.leads * 100).toFixed(2) : '0.00'
            }))
            .sort((a, b) => b.leads - a.leads);

        tbody.innerHTML = sources.map((source, index) => `
            <tr class="source-row ${index >= 4 ? 'hidden-row' : ''}">
                <td class="source-name">${source.name}</td>
                <td>${source.leads}</td>
                <td>${source.won}</td>
                <td>
                    <div class="conversion-bar"><div class="conversion-fill" style="width: ${Math.min(source.conversion * 2, 100)}%"></div></div>
                    ${source.conversion}%
                </td>
            </tr>`).join('');

        if (sources.length > 4) {
            const buttonRow = document.createElement('tr');
            buttonRow.innerHTML = `<td colspan="4" style="text-align:center"><button id="toggleSourceRows" class="toggle-btn">More</button></td>`;
            tbody.appendChild(buttonRow);
            document.getElementById("toggleSourceRows").addEventListener("click", function () {
                const isCollapsed = this.textContent === "More";
                document.querySelectorAll(".source-row").forEach((row, idx) => { if (idx >= 4) row.style.display = isCollapsed ? "table-row" : "none"; });
                this.textContent = isCollapsed ? "Less" : "More";
            });
        }
    }

    function updateReport() {
        dataProcessor = new LeadsDataProcessor();
        updateStats();
        createLeadsChart();
        createStatusChart();
        createReasonChart();
        populateManagersTable();
        populateSourcesTable();
    }

    function redirectToLifeCycle() {
        const startInput = document.getElementById("startDate");
        const endInput = document.getElementById("endDate");
        if (!startInput.value || !endInput.value) { alert("Please select both start and end dates."); return; }
        const url = `https://bitrix24.petragroup.ge/custom/reports/lifeCycle.php?from=${startInput.value}&to=${endInput.value}`;
        window.open(url, "_blank");
    }

    document.addEventListener('DOMContentLoaded', function () {
        const startDateInput = document.getElementById("startDate");
        const endDateInput = document.getElementById("endDate");

        function convertToInputDateFormat(dateStr) {
            if (!dateStr || typeof dateStr !== 'string') return '';
            const normalizedDate = dateStr.replace(/\./g, '/');
            const parts = normalizedDate.split('/');
            if (parts.length !== 3) return '';
            const [day, month, year] = parts;
            if (!day || !month || !year) return '';
            return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
        }

        if (startDateInput) {
            const convertedStart = convertToInputDateFormat(datestartfilter1);
            if (convertedStart) {
                startDateInput.value = convertedStart;
            } else {
                const today = new Date();
                startDateInput.value = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
            }
        }

        if (endDateInput) {
            const convertedEnd = convertToInputDateFormat(dateandfilter1);
            if (convertedEnd) {
                endDateInput.value = convertedEnd;
            } else {
                endDateInput.value = new Date().toISOString().split('T')[0];
            }
        }

        if (startDateInput.value) {
            const [y, m, d] = startDateInput.value.split('-');
            datestartfilter = new Date(y, m - 1, d);
        }
        if (endDateInput.value) {
            const [y, m, d] = endDateInput.value.split('-');
            dateandfilter = new Date(y, m - 1, d);
        }

        updateReport();
    });
</script>
</body>
</html>