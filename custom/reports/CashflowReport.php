<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Cashflow Report");

function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

// UF_CRM_1762416342444 - xelshekrebis gaformis tarigi
function getDealsByFilter($arFilter, $project, $arSelect = array(), $arSort = array("ID"=>"DESC")) {
    $result["deals_data"] = array();
    $result["deals_IDs"] = array();

    if (!empty($project)) {
        $arFilter["UF_CRM_1761658516561"] = $project;
    }

    $res_deals = CCrmDeal::GetList($arSort, $arFilter, array("ID", "DATE_CREATE", "CONTACT_ID","COMPANY_ID", "TITLE","CONTACT_FULL_NAME","OPPORTUNITY","COMPANY_TITLE","UF_CRM_1761658516561","ASSIGNED_BY_ID","UF_CRM_1762416342444"));
    while($arDeal = $res_deals->Fetch()) {
        $arDeal["payment"] = 0;
        $result["deals_data"][$arDeal["ID"]] = $arDeal;
        $result["deals_IDs"][] = $arDeal["ID"];
    }
    return (count($result["deals_IDs"]) > 0) ? $result : false;
}

function getDaricxvebiDaGadaxdebi($fromDate, $toDate, $deals_IDs, $deals_data){
    $daricxvebi = array();
    $gadaxdebi = array();

    // daricxvebi
    $arSelect = Array("ID", "IBLOCK_SECTION_ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*");
    $arFilter = array(
        "IBLOCK_ID"             => 20,
        "PROPERTY_DEAL"         => $deals_IDs
    );

    if (!empty($fromDate)) {
        $arFilter[">=PROPERTY_TARIGI"] = $fromDate;
    }
    if (!empty($toDate)) {
        $arFilter["<=PROPERTY_TARIGI"] = $toDate;
    }

    $res = CIBlockElement::GetList(Array("PROPERTY_TARIGI" => "ASC"), $arFilter, false, Array("nPageSize" => 99999), $arSelect);
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();

        $amount = (float) str_replace("|USD","",$arProps["TANXA"]["VALUE"]);

        $daricxvebi[] = array(
            "DEAL_ID" => $arProps["DEAL"]["VALUE"],
            "DATE" => $arProps["TARIGI"]["VALUE"],
            "AMOUNT" => $amount
        );
    }

    // gadaxdebi
    $arSelect = Array("ID", "IBLOCK_SECTION_ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*");
    $arFilter = array(
        "IBLOCK_ID"             => 21,
        "PROPERTY_DEAL"         => $deals_IDs
    );

    $res = CIBlockElement::GetList(Array("date" => "ASC"), $arFilter, false, Array("nPageSize" => 99999), $arSelect);
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();

        $amount = (float) str_replace("|USD","",$arProps["TANXA"]["VALUE"]);
        $deals_data[$arProps["DEAL"]["VALUE"]]["payment"] += $amount;

        $gadaxdebi[] = array(
            "DEAL_ID" => $arProps["DEAL"]["VALUE"],
            "DATE" => $arProps["date"]["VALUE"],
            "AMOUNT" => $amount
        );
    }
    return [$daricxvebi, $gadaxdebi, $deals_data];
}

function getDealsForExcel($daricxvebi, $gadaxdebi, $deals_data){
    $dealsIDs = [];
    $deals = [];

    foreach ($daricxvebi as $item) {
        $dealID = $item["DEAL_ID"];
        if (!in_array($dealID, $dealsIDs)) {
            $dealsIDs[] = $dealID;
        }
    }

    foreach ($gadaxdebi as $item) {
        $dealID = $item["DEAL_ID"];
        if (!in_array($dealID, $dealsIDs)) {
            $dealsIDs[] = $dealID;
        }
    }

    foreach ($dealsIDs as $id) {
        if (isset($deals_data[$id])) {
            $deals[$id] = $deals_data[$id];
        }
    }

    return $deals;
}

function filterGadaxdebiByDates($fromDate, $toDate, $gadaxdebi) {
    $fromObj = DateTime::createFromFormat('Y-m-d', $fromDate);
    $toObj = DateTime::createFromFormat('Y-m-d', $toDate);

    if (!$fromObj || !$toObj) return $gadaxdebi;

    $filtered_gadaxdebi = array_filter($gadaxdebi, function($item) use ($fromObj, $toObj) {
        $itemDateStr = $item["DATE"];
        if (!$itemDateStr) return false;

        $formats = ['d/m/Y', 'Y-m-d', 'Y-m-d H:i:s'];
        $itemDate = false;
        foreach ($formats as $fmt) {
            $d = DateTime::createFromFormat($fmt, $itemDateStr);
            if ($d) {
                $itemDate = $d;
                break;
            }
        }

        if (!$itemDate) return false;

        return $itemDate >= $fromObj && $itemDate <= $toObj;
    });

    return $filtered_gadaxdebi;
}

$fromDate = !empty($_GET["from_date"]) ? $_GET["from_date"] : null;
$toDate   = !empty($_GET["to_date"]) ? $_GET["to_date"] : null;
$project = $_GET["project"];
$period = $_GET["period"];

if (!empty($fromDate) && !empty($toDate)) {
    $from = DateTime::createFromFormat('Y-m-d', $fromDate);
    $to = DateTime::createFromFormat('Y-m-d', $toDate);

    if ($from && $to) {
        switch ($period) {
            case "month":
                $from->modify('first day of this month')->setTime(0, 0, 0);
                $to->modify('last day of this month')->setTime(23, 59, 59);
                break;

            case "year":
                $from->setDate($from->format('Y'), 1, 1)->setTime(0, 0, 0);
                $to->setDate($to->format('Y'), 12, 31)->setTime(23, 59, 59);
                break;

            default:
                break;
        }

        $fromDate = $from->format('Y-m-d');
        $toDate = $to->format('Y-m-d');
    }
}

$arFiler = array("STAGE_ID" => ["UC_TDFF5K", "UC_41XZPE", "WON"]);
$Deals_data_and_IDs = getDealsByFilter($arFiler, $project);
$deals_data = $Deals_data_and_IDs["deals_data"];
$deals_IDs = $Deals_data_and_IDs["deals_IDs"];

list($daricxvebi, $gadaxdebi, $deals_data) = getDaricxvebiDaGadaxdebi($fromDate, $toDate, $deals_IDs, $deals_data);

$gadaxdebi = filterGadaxdebiByDates($fromDate, $toDate, $gadaxdebi);
$dealsForExcel = getDealsForExcel($daricxvebi, $gadaxdebi, $deals_data);
$grouped_daricxvebi = [];
$grouped_gadaxdebi = [];

// switch for daricxvebi
switch ($period) {
    case "day":
        foreach ($daricxvebi as $item) {
            $date = $item["DATE"];
            $amount = $item["AMOUNT"];

            if (!isset($grouped_daricxvebi[$date])) {
                $grouped_daricxvebi[$date] = 0;
            }

            $grouped_daricxvebi[$date] += $amount;
            if (!isset($dealsForExcel[$item["DEAL_ID"]]["gadaxdebi_and_daricxvebi_by_dates"][$date]["daricxva"])) {
                $dealsForExcel[$item["DEAL_ID"]]["gadaxdebi_and_daricxvebi_by_dates"][$date]["daricxva"] = 0;
            }
            $dealsForExcel[$item["DEAL_ID"]]["gadaxdebi_and_daricxvebi_by_dates"][$date]["daricxva"] += $amount;
        }

        // FIXED SORTING - convert d/m/Y format to timestamps
        uksort($grouped_daricxvebi, function($a, $b) {
            $dateA = DateTime::createFromFormat('d/m/Y', $a);
            $dateB = DateTime::createFromFormat('d/m/Y', $b);
            
            if (!$dateA) $dateA = DateTime::createFromFormat('Y-m-d', $a);
            if (!$dateB) $dateB = DateTime::createFromFormat('Y-m-d', $b);
            
            return $dateA <=> $dateB;
        });
        break;

    case "month":
        foreach ($daricxvebi as $item) {
            if (empty($item["DATE"])) continue;

            $dateObj = DateTime::createFromFormat('d/m/Y', $item["DATE"]);
            if (!$dateObj) continue;

            $monthKey = $dateObj->format('Y-m');
            $amount = (float)$item["AMOUNT"];

            if (!isset($grouped_daricxvebi[$monthKey])) {
                $grouped_daricxvebi[$monthKey] = 0;
            }
            
            $grouped_daricxvebi[$monthKey] += $amount;
            if (!isset($dealsForExcel[$item["DEAL_ID"]]["gadaxdebi_and_daricxvebi_by_dates"][$monthKey]["daricxva"])) {
                $dealsForExcel[$item["DEAL_ID"]]["gadaxdebi_and_daricxvebi_by_dates"][$monthKey]["daricxva"] = 0;
            }
            $dealsForExcel[$item["DEAL_ID"]]["gadaxdebi_and_daricxvebi_by_dates"][$monthKey]["daricxva"] += $amount;
        }

        uksort($grouped_daricxvebi, function($a, $b) {
            return strtotime($a) - strtotime($b);
        });
        break;

    case "year":
        foreach ($daricxvebi as $item) {
            if (empty($item["DATE"])) continue;

            $dateObj = DateTime::createFromFormat('d/m/Y', $item["DATE"]);
            $yearKey = $dateObj ? $dateObj->format('Y') : '';
            $amount = (float)$item["AMOUNT"];

            if (!isset($grouped_daricxvebi[$yearKey])) {
                $grouped_daricxvebi[$yearKey] = 0;
            }

            $grouped_daricxvebi[$yearKey] += $amount;
            if (!isset($dealsForExcel[$item["DEAL_ID"]]["gadaxdebi_and_daricxvebi_by_dates"][$yearKey]["daricxva"])) {
                $dealsForExcel[$item["DEAL_ID"]]["gadaxdebi_and_daricxvebi_by_dates"][$yearKey]["daricxva"] = 0;
            }
            $dealsForExcel[$item["DEAL_ID"]]["gadaxdebi_and_daricxvebi_by_dates"][$yearKey]["daricxva"] += $amount;
        }

        uksort($grouped_daricxvebi, function($a, $b) {
            return $a - $b;
        });
        break;

    default:
        echo "Invalid period selected.";
        break;
}

// switch for gadaxdebi
switch ($period) {
    case "day":
        foreach ($gadaxdebi as $item) {
            $date = $item["DATE"];
            $amount = $item["AMOUNT"];

            if (!isset($grouped_gadaxdebi[$date])) {
                $grouped_gadaxdebi[$date] = 0;
            }

            $grouped_gadaxdebi[$date] += $amount;
            if (!isset($dealsForExcel[$item["DEAL_ID"]]["gadaxdebi_and_daricxvebi_by_dates"][$date]["gadaxda"])) {
                $dealsForExcel[$item["DEAL_ID"]]["gadaxdebi_and_daricxvebi_by_dates"][$date]["gadaxda"] = 0;
            }
            $dealsForExcel[$item["DEAL_ID"]]["gadaxdebi_and_daricxvebi_by_dates"][$date]["gadaxda"] += $amount;
        }

        // FIXED SORTING - convert d/m/Y format to timestamps
        uksort($grouped_gadaxdebi, function($a, $b) {
            $dateA = DateTime::createFromFormat('d/m/Y', $a);
            $dateB = DateTime::createFromFormat('d/m/Y', $b);
            
            if (!$dateA) $dateA = DateTime::createFromFormat('Y-m-d', $a);
            if (!$dateB) $dateB = DateTime::createFromFormat('Y-m-d', $b);
            
            return $dateA <=> $dateB;
        });
        break;

    case "month":
        foreach ($gadaxdebi as $item) {
            if (empty($item["DATE"])) continue;

            $dateObj = DateTime::createFromFormat('d/m/Y', $item["DATE"]);
            if (!$dateObj) continue;

            $monthKey = $dateObj->format('Y-m');
            $amount = (float)$item["AMOUNT"];

            if (!isset($grouped_gadaxdebi[$monthKey])) {
                $grouped_gadaxdebi[$monthKey] = 0;
            }

            $grouped_gadaxdebi[$monthKey] += $amount;
            if (!isset($dealsForExcel[$item["DEAL_ID"]]["gadaxdebi_and_daricxvebi_by_dates"][$monthKey]["gadaxda"])) {
                $dealsForExcel[$item["DEAL_ID"]]["gadaxdebi_and_daricxvebi_by_dates"][$monthKey]["gadaxda"] = 0;
            }
            $dealsForExcel[$item["DEAL_ID"]]["gadaxdebi_and_daricxvebi_by_dates"][$monthKey]["gadaxda"] += $amount;
        }

        uksort($grouped_gadaxdebi, function($a, $b) {
            return strtotime($a) - strtotime($b);
        });
        break;

    case "year":
        foreach ($gadaxdebi as $item) {
            if (empty($item["DATE"])) continue;

            $dateObj = DateTime::createFromFormat('d/m/Y', $item["DATE"]);
            $yearKey = $dateObj ? $dateObj->format('Y') : '';
            $amount = (float)$item["AMOUNT"];

            if (!isset($grouped_gadaxdebi[$yearKey])) {
                $grouped_gadaxdebi[$yearKey] = 0;
            }

            $grouped_gadaxdebi[$yearKey] += $amount;
            if (!isset($dealsForExcel[$item["DEAL_ID"]]["gadaxdebi_and_daricxvebi_by_dates"][$yearKey]["gadaxda"])) {
                $dealsForExcel[$item["DEAL_ID"]]["gadaxdebi_and_daricxvebi_by_dates"][$yearKey]["gadaxda"] = 0;
            }
            $dealsForExcel[$item["DEAL_ID"]]["gadaxdebi_and_daricxvebi_by_dates"][$yearKey]["gadaxda"] += $amount;
        }

        uksort($grouped_gadaxdebi, function($a, $b) {
            return $a - $b;
        });
        break;

    default:
        echo "Invalid period selected.";
        break;
}

ob_end_clean();
?>

<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashflow Report</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .title {
            font-size: 36px;
            text-align: center;
            font-weight: 700;
            color: white;
            margin-bottom: 30px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
            letter-spacing: -0.5px;
        }

        .form-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            margin-bottom: 30px;
            align-items: end;
        }

        .form-group {
            flex: 1;
            min-width: 180px;
        }

        label {
            font-weight: 600;
            display: block;
            margin-bottom: 8px;
            color: #374151;
            font-size: 14px;
        }

        input[type="date"], select {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            transition: all 0.2s ease;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            background: white;
        }

        input[type="date"]:hover, select:hover {
            border-color: #667eea;
        }

        input[type="date"]:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .button-group {
            display: flex;
            gap: 12px;
        }

        input[type="submit"], button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        input[type="submit"]:hover, button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        button.export-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        }

        button.export-btn:hover {
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.6);
        }

        .table-wrapper {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }

        #excelTable {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Inter', sans-serif;
        }

        #excelTable thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        #excelTable th {
            padding: 16px 12px;
            text-align: center;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid rgba(255,255,255,0.2);
        }

        #excelTable td {
            padding: 14px 12px;
            text-align: center;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
            color: #374151;
        }

        #excelTable tbody tr {
            transition: all 0.2s ease;
        }

        #excelTable tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        #excelTable tbody tr:hover {
            background: #ede9fe;
            transform: scale(1.01);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
        }

        /* Make important columns stand out */
        #excelTable td:nth-child(4),
        #excelTable td:nth-child(5),
        #excelTable td:nth-child(6) {
            font-weight: 600;
            color: #1f2937;
        }

        /* Color coding for amounts */
        .positive-amount {
            color: #10b981;
            font-weight: 600;
        }

        .negative-amount {
            color: #ef4444;
            font-weight: 600;
        }

        /* Loading state */
        .loading {
            text-align: center;
            padding: 60px;
            color: white;
            font-size: 18px;
        }

        @media (max-width: 768px) {
            .title {
                font-size: 24px;
            }

            .form-container {
                padding: 20px;
            }

            .form-group {
                min-width: 100%;
            }

            .button-group {
                width: 100%;
                flex-direction: column;
            }

            input[type="submit"], button {
                width: 100%;
            }

            #excelTable {
                font-size: 12px;
            }

            #excelTable th,
            #excelTable td {
                padding: 10px 6px;
            }
        }

        /* Scrollbar styling */
        .table-wrapper::-webkit-scrollbar {
            height: 10px;
        }

        .table-wrapper::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .table-wrapper::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 5px;
        }

        .table-wrapper::-webkit-scrollbar-thumb:hover {
            background: #764ba2;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        #excelTable a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="title">💰 Cashflow Report</div>

        <form method="get" id="newCalendarForm">
            <div class="form-container">
                <div class="form-group">
                    <label>პერიოდი</label>
                    <select name="period" id="period">
                        <option value="day">დღე</option>
                        <option value="month">თვე</option>
                        <option value="year">წელი</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>დაწყების თარიღი</label>
                    <input type="date" name="from_date" id="from_date">
                </div>

                <div class="form-group">
                    <label>დამთავრების თარიღი</label>
                    <input type="date" name="to_date" id="to_date">
                </div>

                <div class="form-group">
                    <label>პროექტი</label>
                    <select name="project" id="project">
                        <option value="Park Boulevard">Park Boulevard</option>
                    </select>
                </div>

                <div class="button-group">
                    <input type="submit" value="🔍 ძებნა">
                    <button type="button" class="export-btn" onclick="exportTableToExcel()">📊 Export to Excel</button>
                </div>
            </div>
        </form>

        <div class="table-wrapper" id="excelTableContainer">
            <div class="loading">Loading data...</div>
        </div>
    </div>

<script>
    let statistika = <?php echo json_encode($dealsForExcel, JSON_UNESCAPED_UNICODE); ?>;
    let project = <?php echo json_encode($project, JSON_UNESCAPED_UNICODE); ?>;
    
    document.getElementById("project").value = project;

    let fromDate = <?php echo json_encode($fromDate); ?>;
    let toDate   = <?php echo json_encode($toDate); ?>;

    if (fromDate) document.getElementById("from_date").value = fromDate;
    if (toDate) document.getElementById("to_date").value = toDate;

    // Helper function to parse dates in multiple formats
    function parseDate(dateStr) {
        if (!dateStr) return null;
        
        // Try different date formats
        const formats = [
            // d/m/Y format (most common for days)
            { regex: /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/, parse: (m) => new Date(m[3], m[2] - 1, m[1]) },
            // Y-m-d format
            { regex: /^(\d{4})-(\d{2})-(\d{2})$/, parse: (m) => new Date(m[1], m[2] - 1, m[3]) },
            // Y-m format (months)
            { regex: /^(\d{4})-(\d{2})$/, parse: (m) => new Date(m[1], m[2] - 1, 1) },
            // Y format (years)
            { regex: /^(\d{4})$/, parse: (m) => new Date(m[1], 0, 1) }
        ];

        for (let format of formats) {
            const match = dateStr.match(format.regex);
            if (match) {
                const date = format.parse(match);
                // Validate the date is valid
                if (!isNaN(date.getTime())) {
                    return date;
                }
            }
        }
        
        // Fallback
        const fallbackDate = new Date(dateStr);
        return isNaN(fallbackDate.getTime()) ? null : fallbackDate;
    }


    function exportTableToExcel() {
        const allDatesSet = new Set();

        Object.values(statistika).forEach(deal => {
            const dates = deal.gadaxdebi_and_daricxvebi_by_dates || {};
            Object.keys(dates).forEach(date => allDatesSet.add(date));
        });

        const allDates = Array.from(allDatesSet).sort((a, b) => {
            const dateA = parseDate(a);
            const dateB = parseDate(b);
            
            // Handle invalid dates
            if (!dateA && !dateB) return 0;
            if (!dateA) return 1;
            if (!dateB) return -1;
            
            return dateA - dateB;
        });

        const data = [];

        Object.values(statistika).forEach(deal => {
            const row = {
                "კლიენტი": deal.CONTACT_FULL_NAME || "",
                "ხელშეკრულება": deal.TITLE || "",
                "გაფორმების თარიღი": deal.UF_CRM_1762416342444 || "",
                "კონტრ. ღირებულება": deal.OPPORTUNITY || 0,
                "გადაიხადა": deal.payment || 0,
                // "მიმდინარე დარიცხვა": deal.payment || 0,
                "დარჩენილი": (deal.OPPORTUNITY || 0) - (deal.payment || 0),
            };

            allDates.forEach(date => {
                const daricxva = deal.gadaxdebi_and_daricxvebi_by_dates?.[date]?.daricxva || 0;
                const gadaxda = deal.gadaxdebi_and_daricxvebi_by_dates?.[date]?.gadaxda || 0;

                row[`დარიცხვა ${date}`] = daricxva;
                row[`გადახდა ${date}`] = gadaxda;
            });

            const totalDaricxva = Object.values(deal.gadaxdebi_and_daricxvebi_by_dates || {}).reduce((sum, v) => sum + (v.daricxva || 0), 0);
            const totalGadaxda = Object.values(deal.gadaxdebi_and_daricxvebi_by_dates || {}).reduce((sum, v) => sum + (v.gadaxda || 0), 0);

            row["ჯამური დარიცხვა"] = totalDaricxva;
            row["ჯამური გადახდა"] = totalGadaxda;

            data.push(row);
        });

        const ws = XLSX.utils.json_to_sheet(data);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Sheet1");
        XLSX.writeFile(wb, 'Report.xlsx');
    }

    function renderExcelTable() {
        const container = document.getElementById("excelTableContainer");
        container.innerHTML = ""; // reset

        const allDatesSet = new Set();

        // collect all unique dates
        Object.values(statistika).forEach(deal => {
            const dates = deal.gadaxdebi_and_daricxvebi_by_dates || {};
            Object.keys(dates).forEach(date => allDatesSet.add(date));
        });

        const allDates = Array.from(allDatesSet).sort((a, b) => {
            const dateA = parseDate(a);
            const dateB = parseDate(b);
            
            // Handle invalid dates
            if (!dateA && !dateB) return 0;
            if (!dateA) return 1;
            if (!dateB) return -1;
            
            return dateA - dateB;
        });

        // Create table
        const table = document.createElement("table");
        table.id = "excelTable";

        // Build header row 1
        let header1 = "<tr>";
        header1 += `
            <th rowspan="2">კლიენტი</th>
            <th rowspan="2">ხელშეკრულება</th>
            <th rowspan="2">გაფორმების თარიღი</th>
            <th rowspan="2">კონტრ. ღირებულება</th>
            <th rowspan="2">გადაიხადა</th>
            <th rowspan="2">დარჩენილი</th>
        `;
        allDates.forEach(date => {
            header1 += `<th colspan="2">${date}</th>`;
        });
        header1 += `
            <th rowspan="2">ჯამური დარიცხვა</th>
            <th rowspan="2">ჯამური გადახდა</th>
        `;
        header1 += "</tr>";

        // Build header row 2
        let header2 = "<tr>";
        allDates.forEach(() => {
            header2 += `<th>დარიცხვა ($)</th><th>გადახდა ($)</th>`;
        });
        header2 += "</tr>";

        table.innerHTML += `<thead>${header1}${header2}</thead>`;

        // Build table body
        let tbody = "<tbody>";
        Object.values(statistika).forEach(deal => {
            tbody += "<tr>";

            const remaining = (deal.OPPORTUNITY || 0) - (deal.payment || 0);

            tbody += `
                <td><a href="/crm/contact/details/${deal.CONTACT_ID}/" target="_blank" style="color: #667eea; text-decoration: none; font-weight: 500;">${deal.CONTACT_FULL_NAME || ""}</a></td>
                <td><a href="/crm/deal/details/${deal.ID}/" target="_blank" style="color: #667eea; text-decoration: none; font-weight: 500;">${deal.TITLE || ""}</a></td>
                <td>${deal.UF_CRM_1762416342444 || ""}</td>
                <td>${deal.OPPORTUNITY || 0}</td>
                <td>${deal.payment || 0}</td>
                <td>${remaining}</td>
            `;

            allDates.forEach(date => {
                const daricxva = deal.gadaxdebi_and_daricxvebi_by_dates?.[date]?.daricxva || 0;
                const gadaxda = deal.gadaxdebi_and_daricxvebi_by_dates?.[date]?.gadaxda || 0;
                tbody += `<td>${daricxva}</td><td>${gadaxda}</td>`;
            });

            const totalDaricxva = Object.values(deal.gadaxdebi_and_daricxvebi_by_dates || {}).reduce((sum, v) => sum + (v.daricxva || 0), 0);
            const totalGadaxda = Object.values(deal.gadaxdebi_and_daricxvebi_by_dates || {}).reduce((sum, v) => sum + (v.gadaxda || 0), 0);

            tbody += `<td>${totalDaricxva}</td><td>${totalGadaxda}</td>`;
            tbody += "</tr>";
        });
        tbody += "</tbody>";

        table.innerHTML += tbody;
        container.appendChild(table);
    }

    // Render table when page loads
    document.addEventListener("DOMContentLoaded", renderExcelTable);


</script>

</body>
</html>