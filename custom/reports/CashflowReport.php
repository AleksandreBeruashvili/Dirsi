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
        // $deals_data[$arProps["DEAL"]["VALUE"]]["daricxva"] += $amount;

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

    if (!$fromObj || !$toObj) return $gadaxdebi; // fallback if dates invalid

    $filtered_gadaxdebi = array_filter($gadaxdebi, function($item) use ($fromObj, $toObj) {
        $itemDateStr = $item["DATE"];
        if (!$itemDateStr) return false;

        // Try multiple formats
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
                // include full months (from start of first month to end of last month)
                $from->modify('first day of this month')->setTime(0, 0, 0);
                $to->modify('last day of this month')->setTime(23, 59, 59);
                break;

            case "year":
                // include full years (from start of first year to end of last year)
                $from->setDate($from->format('Y'), 1, 1)->setTime(0, 0, 0);
                $to->setDate($to->format('Y'), 12, 31)->setTime(23, 59, 59);
                break;

            default: // day
                // leave unchanged
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
// $daricxvebi = filterGadaxdebiByDates($fromDate, $toDate, $daricxvebi);
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

            uksort($grouped_daricxvebi, function($a, $b) {
                return strtotime($a) - strtotime($b);
            });

            // printArr($grouped_daricxvebi);
            break;

        case "month":
            foreach ($daricxvebi as $item) {
                if (empty($item["DATE"])) continue; // safety check

                $dateObj = DateTime::createFromFormat('d/m/Y', $item["DATE"]);
                if (!$dateObj) continue;

                $monthKey = $dateObj->format('Y-m'); // e.g. 2025-09
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

            // Sort months chronologically
            uksort($grouped_daricxvebi, function($a, $b) {
                return strtotime($a) - strtotime($b);
            });

            // printArr($grouped_daricxvebi);
            break;

        case "year":
            // printArr($daricxvebi);
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

            // Sort by year ascending
            uksort($grouped_daricxvebi, function($a, $b) {
                return $a - $b;
            });

            // printArr($grouped_daricxvebi);

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

            uksort($grouped_gadaxdebi, function($a, $b) {
                return strtotime($a) - strtotime($b);
            });

            // printArr($grouped_daricxvebi);
            break;

        case "month":
            foreach ($gadaxdebi as $item) {
                if (empty($item["DATE"])) continue; // safety check

                $dateObj = DateTime::createFromFormat('d/m/Y', $item["DATE"]);
                if (!$dateObj) continue;

                $monthKey = $dateObj->format('Y-m'); // e.g. 2025-09
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

            // Sort months chronologically
            uksort($grouped_gadaxdebi, function($a, $b) {
                return strtotime($a) - strtotime($b);
            });

            // printArr($grouped_daricxvebi);
            break;

        case "year":
            // printArr($daricxvebi);
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

            // Sort by year ascending
            uksort($grouped_gadaxdebi, function($a, $b) {
                return $a - $b;
            });

            // printArr($grouped_daricxvebi);

            break;

        default:
            echo "Invalid period selected.";
            break;
}

// printArr($dealsForExcel);
ob_end_clean();

?>


<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashflow Report</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f7fa;
            color: #333;
            margin: 0;
            /* padding: 40px; */
        }

        h2 {
            color: #1e3a8a;
            font-weight: 600;
            text-align: center;
            margin-top: 60px;
            margin-bottom: 20px;
        }

        .form-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            background: #fff;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            margin-bottom: 40px;
        }

        label {
            font-weight: 500;
            display: block;
            margin-bottom: 6px;
        }

        input[type="date"], select {
            width: 200px;
            padding: 8px 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            transition: all 0.2s ease;
            font-size: 14px;
        }

        input[type="date"]:hover, select:hover {
            border-color: #2563eb;
        }

        input[type="submit"], button {
            background-color: #2563eb;
            border: none;
            color: white;
            padding: 9px 18px;
            font-size: 14px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.15s ease;
        }

        input[type="submit"]:hover, button:hover {
            background-color: #1d4ed8;
            transform: translateY(-1px);
        }

        button.export-btn {
            background-color: #16a34a;
        }

        button.export-btn:hover {
            background-color: #15803d;
        }

        #table {
            width: 100%;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        th, td {
            padding: 12px 16px;
            text-align: center;
        }

        th {
            background: #2563eb;
            color: white;
            font-weight: 500;
            border-bottom: 2px solid #1e40af;
        }

        tr:nth-child(even) td {
            background: #f9fafb;
        }

        tr:hover td {
            background: #e0f2fe;
        }

        td {
            border-bottom: 1px solid #e5e7eb;
            transition: background 0.2s ease;
        }

        @media (max-width: 768px) {
            .form-container {
                flex-direction: column;
            }
            input[type="date"], select {
                width: 100%;
            }
            table {
                font-size: 12px;
            }
        }

        .title {
            font-size: 26px;
            text-align: center;
            font-weight: 600;
            color: #1e3a8a;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>

    <div class="title">💰 Cashflow Report</div>

    <form method="get" id="newCalendarForm">
        <div class="form-container">
            <div>
                <label>პერიოდი</label>
                <select name="period" id="period" class="dropdown">
                    <option value="day">დღე</option>
                    <option value="month">თვე</option>
                    <option value="year">წელი</option>
                </select>
            </div>

            <div>
                <label>დაწყების თარიღი</label>
                <input type="date" name="from_date" id="from_date">
            </div>

            <div>
                <label>დამთავრების თარიღი</label>
                <input type="date" name="to_date" id="to_date">
            </div>

            <div>
                <label>პროექტი</label>
                <select name="project" id="project" class="dropdown">
                    <option value="Park Boulevard">Park Boulevard</option>
                </select>
            </div>

            <div style="display:flex; align-items:center; gap:10px; margin-top:25px;">
                <input type="submit" value="ძებნა">
                <button type="button" class="export-btn" onclick="exportTableToExcel()">Export to Excel</button>
            </div>
        </div>
    </form>

    <?php if (!empty($grouped_daricxvebi) || !empty($grouped_gadaxdebi)): ?>
        <?php
            $allDates = array_unique(array_merge(array_keys($grouped_daricxvebi), array_keys($grouped_gadaxdebi)));
            usort($allDates, function($a, $b) use ($period) {
                if ($period === "year") return (int)$a <=> (int)$b;
                elseif ($period === "month") return strtotime($a . "-01") <=> strtotime($b . "-01");
                else return strtotime($a) <=> strtotime($b);
            });
        ?>
        <h2>ფინანსური მოძრაობა</h2>
        <table id="table">
            <thead>
                <tr>
                    <?php
                    // Georgian month names
                    $geoMonths = [
                        '01' => 'იანვარი', '02' => 'თებერვალი', '03' => 'მარტი',
                        '04' => 'აპრილი', '05' => 'მაისი', '06' => 'ივნისი',
                        '07' => 'ივლისი', '08' => 'აგვისტო', '09' => 'სექტემბერი',
                        '10' => 'ოქტომბერი', '11' => 'ნოემბერი', '12' => 'დეკემბერი'
                    ];

                    foreach ($allDates as $date):
                        $displayDate = $date; // fallback
                        if ($period === "year") {
                            $displayDate = $date;
                        } elseif ($period === "month") {
                            // format: YYYY-MM → ოქტომბერი 2025
                            [$y, $m] = explode("-", $date);
                            $displayDate = $geoMonths[$m] . " " . $y;
                        } elseif ($period === "day") {
                            // format: dd/mm/YYYY or YYYY-MM-DD
                            $d = $m = $y = null;
                            if (strpos($date, "/") !== false) {
                                [$d, $m, $y] = explode("/", $date);
                            } elseif (strpos($date, "-") !== false) {
                                [$y, $m, $d] = explode("-", $date);
                            }
                            if ($d && $m && $y) {
                                $displayDate = ltrim($d, "0") . " " . $geoMonths[$m] . " " . $y;
                            }
                        }
                    ?>
                        <th colspan="2"><?= htmlspecialchars($displayDate) ?></th>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <?php foreach ($allDates as $date): ?>
                        <th>დარიცხვა ($)</th>
                        <th>გადახდა ($)</th>
                    <?php endforeach; ?>
                </tr>
            </thead>

            <tbody>
                <tr>
                    <?php foreach ($allDates as $date): ?>
                        <td><?= number_format($grouped_daricxvebi[$date] ?? 0, 2, '.', ',') ?></td>
                        <td><?= number_format($grouped_gadaxdebi[$date] ?? 0, 2, '.', ',') ?></td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
    <?php endif; ?>



<script>
    let statistika = <?php echo json_encode($dealsForExcel, JSON_UNESCAPED_UNICODE); ?>;

    let project = <?php echo json_encode($project, JSON_UNESCAPED_UNICODE); ?>;

    document.getElementById("project").value = project;

    let fromDate = <?php echo json_encode($fromDate); ?>;
    let toDate   = <?php echo json_encode($toDate); ?>;

    if (fromDate) document.getElementById("from_date").value = fromDate;
    if (toDate) document.getElementById("to_date").value = toDate;



    function exportTableToExcel() {
        const allDatesSet = new Set();

        Object.values(statistika).forEach(deal => {
            const dates = deal.gadaxdebi_and_daricxvebi_by_dates || {};
            Object.keys(dates).forEach(date => allDatesSet.add(date));
        });

        const allDates = Array.from(allDatesSet).sort((a,b) => new Date(a) - new Date(b));

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


</script>

</body>
</html>