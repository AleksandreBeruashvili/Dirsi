<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
// ob_end_clean();
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

    // Move the start date to the first day of the month
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
    $arSelect = array("ID","OPPORTUNITY", "STAGE_ID", "DATE_CREATE", "SOURCE_ID", "ASSIGNED_BY_ID", "UF_CRM_1734504938483");
    
    // Validate filter dates before querying
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

// Get dates from URL parameters or use defaults
$endDateParam = isset($_GET['endDate']) ? trim($_GET['endDate']) : '';
$startDateParam = isset($_GET['startDate']) ? trim($_GET['startDate']) : '';

// Process end date
if (!empty($endDateParam)) {
    try {
        $endDate = new DateTime($endDateParam);
        $dateineed = $endDate->format('d.m.Y');
    } catch (Exception $e) {
        $dateineed = $currentDate->format('d.m.Y');
    }
} else {
    $dateineed = $currentDate->format('d.m.Y');
}

// Process start date
if (!empty($startDateParam)) {
    try {
        $startDate = new DateTime($startDateParam);
        $startdateineed = $startDate->format('d.m.Y');
    } catch (Exception $e) {
        $startdateineed = $firstDayOfMonth->format('d.m.Y');
    }
} else {
    $startdateineed = $firstDayOfMonth->format('d.m.Y');
}

// CRITICAL: Verify dates are not empty
if (empty($startdateineed) || empty($dateineed)) {
    die("Fatal Error: Date initialization failed. Start: '$startdateineed', End: '$dateineed'");
}

// For display in JavaScript
$formattedCurrentDate1 = $currentDate->format('d/m/Y');
$formattedFirstMonday1 = $firstDayOfMonth->format('d/m/Y');

// Create filter with verified dates
$arfilter = array(
    ">=DATE_CREATE" => $startdateineed . " 00:00:00",
    "<=DATE_CREATE" => $dateineed . " 23:59:59",
    "CATEGORY_ID" => 0,
);

// Debug output - check your PHP error log
error_log("=== Date Filter Debug ===");
error_log("Start Date Input: " . $startDateParam);
error_log("End Date Input: " . $endDateParam);
error_log("Formatted Start: " . $startdateineed);
error_log("Formatted End: " . $dateineed);
error_log("Filter Array: " . print_r($arfilter, true));

$deals = getDealsByFilter($arfilter);

if ($deals === false) {
    error_log("No deals found for filter: " . print_r($arfilter, true));
}
    // printArr(count($deals));


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
$sources = [];



$reasonMap = [];

$rsEnum = \CUserFieldEnum::GetList([], ['USER_FIELD_NAME' => 'UF_CRM_1734504938483']);
while ($arEnum = $rsEnum->GetNext()) {
    $reasonMap[$arEnum['ID']] = $arEnum['VALUE'];
}



if ($deals) {
    $dealsdata = [];
    foreach ($deals as $deal) {

        $fbData["Leads"]++;

        $reasonId = $deal['UF_CRM_1734504938483'];

        if (is_array($reasonId)) {
            $reasonNames = array_map(function($id) use ($reasonMap) {
                return $reasonMap[$id] ?? 'UR';
            }, $reasonId);
            $reasonName = implode(', ', $reasonNames); // ყველა მიზეზი ვადუღებთ ერთ სტრინგად
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
            "reason" => $reasonName // ✅ ეს დაემატოს
        ];

        $dealsdata[]=$dealInfo;
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

    // უნიკალური მენეჯერები
    $managers = array_unique($managers);
    $managerNames = [];
    foreach ($managers as $managerId) {
        $managerNames[$managerId] = getUserName($managerId);
    }

    // უნიკალური წყაროები
    $sources = array_unique($sources);

    // სურვილის შემთხვევაში, შეგიძლია გამოიტანო ტექსტური დასახელებები წყაროებისათვის:
    $sourceNames = [];
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



// printArr($fbData);

 
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

        .status-breakdown {
            display: flex;
            flex-direction: column;
            margin-top: 15px;
        }

        .status-item {
            display: flex;
            justify-content: space-between;
        }

        .status-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9em;
        }

        .status-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
        }

        .status-percentage {
            font-weight: 600;
            color: #2c3e50;
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
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .bottom-section {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
            }
            
            .date-filter {
                flex-wrap: wrap;
            }
        }
        .reason-chart-container {
            width: 100%;
            max-width: 600px;
            height: 400px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        #reasonChart {
            width: 100% !important;
            height: 100% !important;
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

        .redirect-btn:hover {
            background-color: #45a049;
        }
        .hidden-row {
            display: none;
        }
        .toggle-btn {
            margin-top: 8px;
            background-color: #e0e0e0;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }
        .toggle-btn:hover {
            background-color: #ccc;
        }
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
            <button type="submit" class="refresh-btn" onclick="updateReport()">Refresh</button>
            <button type="button" class="redirect-btn" onclick="redirectToLifeCycle()">Go to Lifecycle Report</button>
        </form>
    </div>
    </div>
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-number" id="totalLeads">4,218</div>
                <div class="stat-label">Leads.</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="confirmedLeads">2,719</div>
                <div class="stat-label">QL.</div>
            </div>

            <div class="stat-card">
                <div class="stat-number" id="nonQualified">2,719</div>
                <div class="stat-label">NonQL.</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="Scheduled">265</div>
                <div class="stat-label">Meetings Scheduled.</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="Completed">265</div>
                <div class="stat-label">Meetings Completed.</div>
            </div>

            <div class="stat-card">
                <div class="stat-number" id="inDealLeads">388</div>
                <div class="stat-label">Won.</div>
            </div>

            <div class="stat-card">
                <div class="stat-number" id="unsuccessfulLeads">3,565</div>
                <div class="stat-label">Junk.</div>
            </div>
            <div class="stat-card conversion-card">
                <div class="stat-number" id="conversionRate">9.20%</div>
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
                    <tbody id="managersTable">
                    </tbody>
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
                    <tbody id="sourcesTable">
                    </tbody>
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
                    <tbody id="reasonChart">
                    </tbody>
                </table>
            </div>
        </div>

        <div class="table-wrapper1">
            <div class="table-container1" id="dealTableContainer1" >
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    let dateandfilter1 = <?php echo json_encode($dateineed); ?>;
    let datestartfilter1 = <?php echo json_encode($startdateineed); ?>;
    let fbData = <?php echo json_encode($fbData); ?>;
    let managerNamesList = <?php echo json_encode($managerNamesList); ?>;
    let sourceNamesList = <?php echo json_encode($sourceNamesList); ?>;
    let dealsdata = <?php echo json_encode($dealsdata); ?>;
    let reasonMap = <?php echo json_encode($reasonMap); ?>;
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

            // Helper function to parse date from multiple formats
            function parseDateFromString(dateString) {
                try {
                    if (!dateString) return new Date();
                    
                    // Handle DD.MM.YYYY HH:MM:SS or DD/MM/YYYY HH:MM:SS
                    let cleanDate = dateString.replace(/\./g, '/');
                    
                    // Split date and time
                    const [datePart, timePart] = cleanDate.split(' ');
                    const [day, month, year] = datePart.split('/');
                    
                    // Validate parts
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


                // Source statistics
                if (deal.source && !processed.sourceStats[deal.source]) {
                    processed.sourceStats[deal.source] = { leads: 0, won: 0 };
                }
                if (deal.source) {
                    processed.sourceStats[deal.source].leads++;
                    if (deal.stageId === 'WON') {
                        processed.sourceStats[deal.source].won++;
                    }
                }


                    // Source statistics
                if (deal.reason && !processed.reasonStats[deal.reason]) {
                    processed.reasonStats[deal.reason] = { leads: 0, won: 0 };
                }
                if (deal.reason) {
                    processed.reasonStats[deal.reason].leads++;
                    if (deal.stageId === 'WON') {
                        processed.reasonStats[deal.reason].won++;
                    }
                }
                // Reason statistics
                // if (deal.reason && !processed.reasonStats[deal.reason]) {
                //     processed.reasonStats[deal.reason] = 0;
                // }
                // if (deal.reason) {
                //     processed.reasonStats[deal.reason]++;
                // }

                // Monthly data processing
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

                // Status statistics
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
        
        // Update main statistics
        if (document.getElementById('totalLeads')) {
            document.getElementById('totalLeads').textContent = data.totalDeals.toLocaleString();
        }
        if (document.getElementById('confirmedLeads')) {
            document.getElementById('confirmedLeads').textContent = data.Qualified.toLocaleString();
        }
        if (document.getElementById('nonQualified')) {
            document.getElementById('nonQualified').textContent = (data.totalDeals) - (data.Qualified);
        }
        if (document.getElementById('Scheduled')) {
            document.getElementById('Scheduled').textContent = data.Scheduled.toLocaleString();
        }
        if (document.getElementById('Completed')) {
            document.getElementById('Completed').textContent = data.Completed.toLocaleString();
        }
        if (document.getElementById('unsuccessfulLeads')) {
            document.getElementById('unsuccessfulLeads').textContent = data.failedDeals.toLocaleString();
        }
        if (document.getElementById('inDealLeads')) {
            document.getElementById('inDealLeads').textContent = data.wonDeals.toLocaleString();
        }
        
        const conversionRate = data.totalDeals > 0 ? (data.wonDeals / data.totalDeals * 100).toFixed(2) : '0.00';
        if (document.getElementById('conversionRate')) {
            document.getElementById('conversionRate').textContent = conversionRate + '%';
        }
        
        // Update total revenue
        if (document.getElementById('totalRevenue')) {
            document.getElementById('totalRevenue').textContent = data.totalRevenue.toLocaleString() + ' ₾';
        }
    }
   

function createLeadsChart() {
    const ctx = document.getElementById('leadsChart');
    if (!ctx) return;
    
    const data = dataProcessor.processedData;

    // Get date filter values
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');

    console.log(startDateInput);
    console.log(endDateInput);
    
    let startDate = null;
    let endDate = null;
    
    if (startDateInput && startDateInput.value) {
        startDate = new Date(startDateInput.value);
    }
    
    if (endDateInput && endDateInput.value) {
        endDate = new Date(endDateInput.value);
    }

    // Get all months from data (only for fallback when no date filters)
    const monthlyEntries = Object.entries(data.monthlyData).sort((a, b) => {
        return a[0].localeCompare(b[0]);
    });

    // Create comprehensive month range based on date filters or all available data
    let allMonths = [];
    
    if (startDate && endDate) {

        console.log(startDate);
        console.log(endDate);

        // If both dates are provided, create range from start to end
        let currentYear = startDate.getFullYear();
        let currentMonth = startDate.getMonth() + 1; // getMonth() returns 0-11, we need 1-12
        
        const endYear = endDate.getFullYear();
        const endMonth = endDate.getMonth() + 1;
        
        while (currentYear < endYear || (currentYear === endYear && currentMonth <= endMonth)) {
            const monthKey = currentYear + '-' + currentMonth.toString().padStart(2, '0');
            allMonths.push(monthKey);
            
            currentMonth++;
            if (currentMonth > 12) {
                currentMonth = 1;
                currentYear++;
            }
        }
    } else if (startDate && !endDate) {
        // If only start date is provided, start from start date to last available data
        const lastAvailableMonth = monthlyEntries.length > 0 ? monthlyEntries[monthlyEntries.length - 1][0] : null;
        
        if (lastAvailableMonth) {
            const [endYear, endMonth] = lastAvailableMonth.split('-').map(Number);
            
            let currentYear = startDate.getFullYear();
            let currentMonth = startDate.getMonth() + 1;
            
            while (currentYear < endYear || (currentYear === endYear && currentMonth <= endMonth)) {
                const monthKey = currentYear + '-' + currentMonth.toString().padStart(2, '0');
                allMonths.push(monthKey);
                
                currentMonth++;
                if (currentMonth > 12) {
                    currentMonth = 1;
                    currentYear++;
                }
            }
        }
    } else if (!startDate && endDate) {
        // If only end date is provided, start from first available data to end date
        const firstAvailableMonth = monthlyEntries.length > 0 ? monthlyEntries[0][0] : null;
        
        if (firstAvailableMonth) {
            const [startYear, startMonth] = firstAvailableMonth.split('-').map(Number);
            
            let currentYear = startYear;
            let currentMonth = startMonth;
            
            const endYear = endDate.getFullYear();
            const endMonth = endDate.getMonth() + 1;
            
            while (currentYear < endYear || (currentYear === endYear && currentMonth <= endMonth)) {
                const monthKey = currentYear + '-' + currentMonth.toString().padStart(2, '0');
                allMonths.push(monthKey);
                
                currentMonth++;
                if (currentMonth > 12) {
                    currentMonth = 1;
                    currentYear++;
                }
            }
        }
    } else {
        // If no date filters, use all available data range
        if (monthlyEntries.length > 0) {
            const firstMonth = monthlyEntries[0][0];
            const lastMonth = monthlyEntries[monthlyEntries.length - 1][0];
            
            const [startYear, startMonth] = firstMonth.split('-').map(Number);
            const [endYear, endMonth] = lastMonth.split('-').map(Number);
            
            let currentYear = startYear;
            let currentMonth = startMonth;
            
            while (currentYear < endYear || (currentYear === endYear && currentMonth <= endMonth)) {
                const monthKey = currentYear + '-' + currentMonth.toString().padStart(2, '0');
                allMonths.push(monthKey);
                
                currentMonth++;
                if (currentMonth > 12) {
                    currentMonth = 1;
                    currentYear++;
                }
            }
        }
    }

    console.log(allMonths);

    // Create month labels in Georgian
    const monthLabels = allMonths.map(month => {
        const [year, monthNum] = month.split('-');
        const monthNames = {
            '01': 'Jan', '02': 'Feb', '03': 'Mar', '04': 'Apr',
            '05': 'May', '06': 'Jun', '07': 'Jul', '08': 'Aug',
            '09': 'Sep', '10': 'Oct', '11': 'Nov', '12': 'Dec'
        };
        return `${monthNames[monthNum]} ${year}`;
    });

    // Create data arrays - only for confirmed and won
    const monthlyConfirmed = allMonths.map(month => {
        return data.monthlyData[month] ? data.monthlyData[month].confirmed : 0;
    });


    const monthlyWon = allMonths.map(month => {
        return data.monthlyData[month] ? data.monthlyData[month].won : 0;
    });


    const monthlyTotal = allMonths.map(month => {
        return data.monthlyData[month] ? data.monthlyData[month].total : 0;
    });

    // Calculate max value for y-axis scaling
    const maxValue = Math.max(...monthlyConfirmed, ...monthlyWon, ...monthlyTotal);

    // Destroy existing chart
    if (leadsChart) {
        leadsChart.destroy();
    }

    

    leadsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: monthLabels,
            datasets: [

                {
                    label: 'Total',
                    data: monthlyTotal,
                    backgroundColor: 'rgba(182, 176, 176, 0.8)',
                    borderColor: '#676763ff',
                    borderWidth: 2,
                    borderRadius: 5,
                    borderSkipped: false
                },
                {
                    label: 'QL',
                    data: monthlyConfirmed,
                    backgroundColor: 'rgba(70, 130, 180, 0.8)',
                    borderColor: '#4682B4',
                    borderWidth: 2,
                    borderRadius: 5,
                    borderSkipped: false
                },
                {
                    label: 'WON',
                    data: monthlyWon,
                    backgroundColor: 'rgba(52, 168, 83, 0.8)',
                    borderColor: '#34a853',
                    borderWidth: 2,
                    borderRadius: 5,
                    borderSkipped: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    align: 'start',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'rect',
                        padding: 20,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: 'white',
                    bodyColor: 'white',
                    borderColor: '#ccc',
                    borderWidth: 1,
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)',
                        lineWidth: 1
                    },
                    ticks: {
                        stepSize: Math.max(1, Math.ceil(maxValue / 8)),
                        font: {
                            size: 11
                        },
                        color: '#666'
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 11
                        },
                        color: '#666',
                        maxRotation: 45
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
}

    function createStatusChart() {
        const ctx = document.getElementById('statusChart');
        if (!ctx) return;
        
        const data = dataProcessor?.processedData || {};

        const total = data.totalDeals || 0;
        const fail = data.failedDeals || 0;
        const success = data.wonDeals || 0;
        const inWork = total-(fail+success) || 0;

        const failPercent = total > 0 ? (fail / total) * 100 : 0;
        const successPercent = total > 0 ? (success / total) * 100 : 0;
        const inWorkPercent = total > 0 ? (inWork / total) * 100 : 0;

        if (statusChart) {
            statusChart.destroy();
        }

        statusChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['JUNK', 'WON', 'IN WORK'],
                datasets: [{
                    data: [failPercent, successPercent, inWorkPercent],
                    backgroundColor: ['#ea4335', '#34a853', '#fbbc04'],
                    borderWidth: 1,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const count = Math.round((context.parsed.y / 100) * total);
                                return context.label + ': ' + context.parsed.y.toFixed(1) + '% (' + count + ')';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
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
            .map(([name, stats]) => {
                const translatedName = reasonTranslations[name] || name;
                return {
                    name: translatedName,
                    leads: stats.leads,
                    conversion: stats.leads > 0 ? (stats.won / stats.leads * 100).toFixed(2) : '0.00'
                };
            })
            .sort((a, b) => b.leads - a.leads);

        tbody.innerHTML = sources.map((source, index) => `
            <tr class="reason-row ${index >= 4 ? 'hidden-row' : ''}">
                <td class="reason-name">${source.name}</td>
                <td>${source.leads}</td>
                <td>
                    <div class="conversion-bar">
                        <div class="conversion-fill" style="width: ${Math.min(source.conversion * 2, 100)}%"></div>
                    </div>
                    ${source.conversion}%
                </td>
            </tr>
        `).join('');

        // Add toggle button
        if (sources.length > 4) {
            const buttonRow = document.createElement('tr');
            buttonRow.innerHTML = `
                <td colspan="3" style="text-align: center;">
                    <button id="toggleReasonRows" class="toggle-btn">More</button>
                </td>
            `;
            tbody.appendChild(buttonRow);

            document.getElementById("toggleReasonRows").addEventListener("click", function () {
                const hiddenRows = document.querySelectorAll(".reason-row");
                const isCollapsed = this.textContent === "More";

                hiddenRows.forEach((row, idx) => {
                    if (idx >= 4) {
                        row.style.display = isCollapsed ? "table-row" : "none";
                    }
                });

                this.textContent = isCollapsed ? "Less" : "More";
            });
        }
    }


    function populateManagersTable() {
        const tbody = document.getElementById('managersTable');
        const data = dataProcessor.processedData;

        // მხოლოდ ეს მენეჯერები უნდა გამოჩნდნენ
        const allowedManagers = [
            'Ana Arabidze',
            'Ano Gelovani',
            'Gala Tsintsadze',
            'Kristina Khimshiashvili',
            'Mari Andguladze'
        ];

        const managers = Object.entries(data.managerStats)
            .filter(([name]) => allowedManagers.includes(name)) // ფილტრავს მხოლოდ დაშვებულ მენეჯერებს
            .map(([name, stats]) => ({
                name,
                leads: stats.leads,
                inWork: stats.leads -(stats.won + stats.junk),
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
                    <div class="conversion-bar">
                        <div class="conversion-fill" style="width: ${Math.min(manager.conversion * 2, 100)}%"></div>
                    </div>
                    ${manager.conversion}%
                </td>
            </tr>
        `).join('');

        if (managers.length > 4) {
            const buttonRow = document.createElement('tr');
            buttonRow.innerHTML = `
                <td colspan="6" style="text-align: center;">
                    <button id="toggleManagerRows" class="toggle-btn">More</button>
                </td>
            `;
            tbody.appendChild(buttonRow);

            document.getElementById("toggleManagerRows").addEventListener("click", function () {
                const hiddenRows = document.querySelectorAll(".manager-row");
                const isCollapsed = this.textContent === "More";

                hiddenRows.forEach((row, idx) => {
                    if (idx >= 4) {
                        row.style.display = isCollapsed ? "table-row" : "none";
                    }
                });

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
                    <div class="conversion-bar">
                        <div class="conversion-fill" style="width: ${Math.min(source.conversion * 2, 100)}%"></div>
                    </div>
                    ${source.conversion}%
                </td>
            </tr>
        `).join('');

        if (sources.length > 4) {
            const buttonRow = document.createElement('tr');
            buttonRow.innerHTML = `
                <td colspan="4" style="text-align: center;">
                    <button id="toggleSourceRows" class="toggle-btn">More</button>
                </td>
            `;
            tbody.appendChild(buttonRow);

            document.getElementById("toggleSourceRows").addEventListener("click", function () {
                const hiddenRows = document.querySelectorAll(".source-row");
                const isCollapsed = this.textContent === "More";

                hiddenRows.forEach((row, idx) => {
                    if (idx >= 4) {
                        row.style.display = isCollapsed ? "table-row" : "none";
                    }
                });

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
        // populateStatusTable();
        // populateReasonsTable();
    }
    function redirectToLifeCycle() {
        const startInput = document.getElementById("startDate");
        const endInput = document.getElementById("endDate");

        if (!startInput.value || !endInput.value) {
            alert("Please select both start and end dates.");
            return;
        }

        const from = startInput.value; // yyyy-mm-dd
        const to = endInput.value;

        const url = `https://bitrix24.petragroup.ge/custom/reports/lifeCycle.php?from=${from}&to=${to}`;
        window.open(url, "_blank"); // ან გამოიყენე: window.location.href = url;
    }
    document.addEventListener('DOMContentLoaded', function () {
    const startDateInput = document.getElementById("startDate");
    const endDateInput = document.getElementById("endDate");

    // Helper: dd/mm/yyyy → yyyy-mm-dd with validation
    function convertToInputDateFormat(dateStr) {
        // Check if dateStr is valid
        if (!dateStr || typeof dateStr !== 'string') {
            console.warn('Invalid date string:', dateStr);
            return '';
        }
        
        const parts = dateStr.split('/');
        
        // Validate that we have exactly 3 parts
        if (parts.length !== 3) {
            console.warn('Invalid date format:', dateStr);
            return '';
        }
        
        const [day, month, year] = parts;
        
        // Validate each part exists
        if (!day || !month || !year) {
            console.warn('Missing date components:', dateStr);
            return '';
        }
        
        return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
    }

    // Set start date with fallback
    if (startDateInput) {
        const convertedStart = convertToInputDateFormat(datestartfilter1);
        if (convertedStart) {
            startDateInput.value = convertedStart;
        } else {
            // Fallback to first day of current month
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            startDateInput.value = firstDay.toISOString().split('T')[0];
        }
    }

    // Set end date with fallback
    if (endDateInput) {
        const convertedEnd = convertToInputDateFormat(dateandfilter1);
        if (convertedEnd) {
            endDateInput.value = convertedEnd;
        } else {
            // Fallback to today
            const today = new Date();
            endDateInput.value = today.toISOString().split('T')[0];
        }
    }

    // Set global date variables
    if (startDateInput.value) {
        const startParts = startDateInput.value.split('-'); // yyyy-mm-dd
        datestartfilter = new Date(startParts[0], startParts[1] - 1, startParts[2]);
    }
    
    if (endDateInput.value) {
        const endParts = endDateInput.value.split('-');
        dateandfilter = new Date(endParts[0], endParts[1] - 1, endParts[2]);
    }

    updateReport();
});


</script>
</body>
</html>