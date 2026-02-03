<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CModule::IncludeModule('crm');
CModule::IncludeModule('main');

session_write_close();
$APPLICATION->SetTitle("Deals Funnel Report");

/* ===================== HELPERS ===================== */

function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

function getUsersByGroupId($groupId) {
    $res = [];
    $rsUsers = CUser::GetList(
        ($by="NAME"),
        ($order="asc"),
        [
            "GROUPS_ID" => [$groupId],
            "ACTIVE" => "Y"
        ],
        ["FIELDS" => ["ID", "NAME", "LAST_NAME"]]
    );

    while ($u = $rsUsers->Fetch()) {
        $res[$u['ID']] = $u['NAME'] . ' ' . $u['LAST_NAME'];
    }
    return $res;
}

/* ===================== DATE FILTER ===================== */

// ---- SAFE DATE FILTER FOR BITRIX ----

$startDate = null;
$endDate   = null;

if (
    isset($_GET['startDate']) &&
    $_GET['startDate'] !== '' &&
    strtotime($_GET['startDate']) !== false
) {
    $startDate = date('Y-m-d 00:00:00', strtotime($_GET['startDate']));
}

if (
    isset($_GET['endDate']) &&
    $_GET['endDate'] !== '' &&
    strtotime($_GET['endDate']) !== false
) {
    $endDate = date('Y-m-d 23:59:59', strtotime($_GET['endDate']));
}

// Defaults (თუ ცარიელია)
if ($startDate === null) {
    $startDate = date('Y-m-01 00:00:00');
}

if ($endDate === null) {
    $endDate = date('Y-m-d 23:59:59');
}



/* ===================== MANAGERS (GROUP 17 ONLY) ===================== */

$managers = getUsersByGroupId(17);
$managerIds = array_keys($managers);

if (empty($managerIds)) {
    echo "No managers in group 17";
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
    exit;
}

/* ===================== DEALS QUERY ===================== */

$arFilter = [
    "CATEGORY_ID" => 0,
    "ASSIGNED_BY_ID" => $managerIds,
];

// ❗ მხოლოდ ეს ვერსია
if ($startDate) {
    $arFilter["DATE_CREATE_FROM"] = $startDate;
}

if ($endDate) {
    $arFilter["DATE_CREATE_TO"] = $endDate;
}





$arSelect = [
    "ID",
    "STAGE_ID",
    "OPPORTUNITY",
    "DATE_CREATE",
    "ASSIGNED_BY_ID",
    "SOURCE_ID" // ✅ ეს აკლდა
];


$deals = [];

$res = CCrmDeal::GetListEx(
    ["ID" => "DESC"],
    $arFilter,
    false,
    false,
    $arSelect
);

while ($deal = $res->Fetch()) {
    $deals[] = $deal;
}



/* ===================== STATISTICS ===================== */

$stats = [
    'ALL' => 0,
    'DISTRIBUTED' => 0,
    'MEETING_AGREED' => 0,
    'MEETING_FINISHED' => 0,
    'WON' => 0,
    'JUNK' => 0
];

$managerStats = [];

foreach ($deals as $deal) {

    $stats['ALL']++;

    if (!empty($deal['ASSIGNED_BY_ID'])) {
        $stats['DISTRIBUTED']++;
    }

    // ✅ Meeting Agreed
    if ($deal['STAGE_ID'] === 'UC_BAUB5P') {
        $stats['MEETING_AGREED']++;
    }

    // Meeting Finished
    if ($deal['STAGE_ID'] === 'UC_F3FOBF') {
        $stats['MEETING_FINISHED']++;
    }
    // Won
    if ($deal['STAGE_ID'] === 'WON') {
        $stats['WON']++;
    }

    // ✅ Junk
    if ($deal['STAGE_ID'] === 'LOSE') {
        $stats['JUNK']++;
    }

    // Manager stats (რჩება უცვლელი)
    $mid = $deal['ASSIGNED_BY_ID'];

    if (!isset($managerStats[$mid])) {
        $managerStats[$mid] = [
            'name' => $managers[$mid],
            'leads' => 0,
            'won' => 0,
            'junk' => 0
        ];
    }

    $managerStats[$mid]['leads']++;

    if ($deal['STAGE_ID'] === 'WON') {
        $managerStats[$mid]['won']++;
    }

    if ($deal['STAGE_ID'] === 'LOSE') {
        $managerStats[$mid]['junk']++;
    }
}


$conversion = $stats['ALL'] > 0
    ? round($stats['WON'] / $stats['ALL'] * 100, 2)
    : 0;

$statusChartData = [
    'Meeting Agreed'   => $stats['MEETING_AGREED'],
    'Meeting Finished' => $stats['MEETING_FINISHED'],
    'Won'              => $stats['WON'],
    'Junk'             => $stats['JUNK'],
    'In Work'          => $stats['ALL']
        - $stats['MEETING_AGREED']
        - $stats['MEETING_FINISHED']
        - $stats['WON']
        - $stats['JUNK']
];



$monthlyStats = [];
$sourceStats = [];
$sources = CCrmStatus::GetStatusList('SOURCE');
$junkReasons = [];

foreach ($deals as $deal) {
    $month = date('Y-m', strtotime($deal['DATE_CREATE']));

    if (!isset($monthlyStats[$month])) {
        $monthlyStats[$month] = [
            'ALL' => 0,
            'WON' => 0,
            'JUNK' => 0
        ];
    }

    $monthlyStats[$month]['ALL']++;

    if ($deal['STAGE_ID'] === 'WON') {
        $monthlyStats[$month]['WON']++;
    }

    if ($deal['STAGE_ID'] === 'LOSE') {
        $monthlyStats[$month]['JUNK']++;
    }

    $src = $deal['SOURCE_ID'] ?: 'UNKNOWN';

    if (!isset($sourceStats[$src])) {
        $sourceStats[$src] = ['leads'=>0,'won'=>0];
    }

    $sourceStats[$src]['leads']++;

    if ($deal['STAGE_ID'] === 'WON') {
        $sourceStats[$src]['won']++;
    }

    if ($deal['STAGE_ID'] === 'LOSE') {
        $reason = $deal['LOSE_REASON'] ?: 'Unknown';
        $junkReasons[$reason] = ($junkReasons[$reason] ?? 0) + 1;
    }
}

ksort($monthlyStats);


?>

    <div class="dashboard">

        <!-- HEADER -->
        <div class="dashboard-header">
            <h1>DEALS REPORT</h1>

            <form class="filters">
                <label>From:
                    <input type="date" name="startDate"
                           value="<?= htmlspecialchars($_GET['startDate'] ?? '') ?>">
                </label>
                <label>To:
                    <input type="date" name="endDate"
                           value="<?= htmlspecialchars($_GET['endDate'] ?? '') ?>">
                </label>
                <button class="btn primary">Refresh</button>
                <button class="btn success">Go to Lifecycle Report</button>
            </form>
        </div>

        <!-- ✅ FUNNEL STAT CARDS -->
        <div class="stats-grid">

            <div class="stat-card">
                <div class="stat-number"><?= $stats['ALL'] ?></div>
                <div class="stat-label">All Leads</div>
            </div>

            <div class="stat-card">
                <div class="stat-number"><?= $stats['DISTRIBUTED'] ?></div>
                <div class="stat-label">Distributed</div>
            </div>

            <div class="stat-card">
                <div class="stat-number"><?= $stats['MEETING_AGREED'] ?></div>
                <div class="stat-label">Meeting Agreed</div>
            </div>

            <div class="stat-card">
                <div class="stat-number"><?= $stats['MEETING_FINISHED'] ?></div>
                <div class="stat-label">Meeting Finished</div>
            </div>

            <div class="stat-card">
                <div class="stat-number"><?= $stats['WON'] ?></div>
                <div class="stat-label">Won</div>
            </div>

            <div class="stat-card">
                <div class="stat-number"><?= $stats['JUNK'] ?></div>
                <div class="stat-label">Junk</div>
            </div>

            <div class="stat-card highlight">
                <div class="stat-number"><?= $conversion ?>%</div>
                <div class="stat-label">Conversion</div>
            </div>

        </div>

        <!-- CHARTS -->
        <div class="charts-grid">
            <div class="card large">
                <h3>DEALS DYNAMICS BY MONTH.</h3>
                <canvas id="leadsChart"></canvas>
            </div>

            <div class="card">
                <h3>DEALS BY STATUS TYPE, %</h3>
                <div class="pie-wrapper">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>


            <div class="card">
                <h3>DEALS AND CONVERSION BY MANAGERS</h3>

                <table class="managers-table">
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
                    <tbody>
                    <?php foreach ($managerStats as $m):
                        $inWork = $m['leads'] - $m['won'] - $m['junk'];
                        $conv = $m['leads'] > 0 ? round($m['won'] / $m['leads'] * 100, 2) : 0;
                        ?>
                        <tr>
                            <td class="manager-name">
                                <a href="#"><?= htmlspecialchars($m['name']) ?></a>
                            </td>
                            <td><?= $m['leads'] ?></td>
                            <td><?= $inWork ?></td>
                            <td><?= $m['won'] ?></td>
                            <td><?= $m['junk'] ?></td>
                            <td class="conversion-cell">
                                <div class="conversion-bar">
                                    <span style="width: <?= $conv ?>%"></span>
                                </div>
                                <div class="conversion-value"><?= $conv ?>%</div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="table-footer">
                    <button class="btn-less">Less</button>
                </div>
            </div>


        </div>



        <!-- TABLES -->
        <div class="tables-grid">

            <div class="card">
                <h3>DEALS AND CONVERSION BY SOURCES</h3>

                <table class="sources-table">
                    <thead>
                    <tr>
                        <th>Source</th>
                        <th>Leads.</th>
                        <th>In Work</th>
                        <th>Won.</th>
                        <th>Conversion</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sourceStats as $srcId => $data):
                        $leads = $data['leads'];
                        $won   = $data['won'];
                        $inWork = $leads - $won;
                        $conv  = $leads > 0 ? round($won / $leads * 100, 2) : 0;
                        $srcName = $sources[$srcId] ?? 'Unknown';
                        ?>
                        <tr>
                            <td class="source-name"><?= htmlspecialchars($srcName) ?></td>
                            <td><?= $leads ?></td>
                            <td><?= $inWork ?></td>
                            <td><?= $won ?></td>
                            <td class="conversion-cell">
                                <div class="conversion-bar">
                                    <span style="width: <?= $conv ?>%"></span>
                                </div>
                                <div class="conversion-value"><?= $conv ?>%</div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h3>DEALS AND CONVERSION BY JUNK REASONS</h3>

                <table class="junk-table">
                    <thead>
                    <tr>
                        <th>Junk Reason</th>
                        <th>Leads.</th>
                        <th>Conversion</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $totalJunk = array_sum($junkReasons);
                    foreach ($junkReasons as $reason => $count):
                        $conv = $totalJunk > 0 ? round($count / $totalJunk * 100, 2) : 0;
                        ?>
                        <tr>
                            <td class="junk-reason"><?= htmlspecialchars($reason) ?></td>
                            <td><?= $count ?></td>
                            <td class="conversion-cell">
                                <div class="conversion-bar">
                                    <span style="width: <?= $conv ?>%"></span>
                                </div>
                                <div class="conversion-value"><?= $conv ?>%</div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>



    <script>
        new Chart(document.getElementById('statusChart'), {
            type: 'pie',
            data: {
                labels: <?= json_encode(array_keys($statusChartData)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($statusChartData)) ?>,
                    backgroundColor: [
                        '#0dcaf0', // Meeting Agreed
                        '#6610f2', // Meeting Finished
                        '#28a745', // Won
                        '#dc3545', // Junk
                        '#ffc107'  // In Work
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    </script>




    <style>
        body {
            background: #f5f6f8;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .dashboard {
            max-width: 1800px;
            margin: auto;
            padding: 20px;
        }

        .dashboard-header {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .filters {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn {
            padding: 8px 14px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 600;
        }

        .btn.primary {
            background: #0d6efd;
            color: #fff;
        }

        .btn.success {
            background: #28a745;
            color: #fff;
        }

        /* STAT CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
        }

        .stat-label {
            font-size: 13px;
            color: #6c757d;
        }

        .stat-card.highlight .stat-number {
            color: #0d6efd;
        }

        /* CHARTS */
        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;

        }

        /* TABLES */
        .tables-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .card {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .card.large {
            height: 350px;
        }

        .card h3 {
            font-size: 15px;
            margin-bottom: 15px;
            color: #333;
        }

        /* TABLE */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            font-size: 13px;
            color: #6c757d;
            padding-bottom: 8px;
        }

        td {
            padding: 8px 0;
            font-size: 14px;
            border-top: 1px solid #eee;
        }

        .pie-wrapper {
            max-width: 320px;
            height: 320px;
            margin: 0 auto;
        }

        .managers-table {
            width: 100%;
            border-collapse: collapse;
        }

        .managers-table thead th {
            font-size: 14px;
            color: #6c757d;
            font-weight: 600;
            text-align: left;
            padding: 12px 8px;
            background: #fafafa;
        }

        .managers-table tbody td {
            padding: 14px 8px;
            font-size: 15px;
            border-top: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        .manager-name a {
            color: #0d6efd;
            font-weight: 600;
            text-decoration: none;
        }

        .manager-name a:hover {
            text-decoration: underline;
        }

        .conversion-cell {
            min-width: 140px;
        }

        .conversion-bar {
            width: 100%;
            height: 6px;
            background: #e9f0ff;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 6px;
        }

        .conversion-bar span {
            display: block;
            height: 100%;
            background: #0d6efd;
            border-radius: 4px;
        }

        .conversion-value {
            font-size: 14px;
            color: #333;
        }

        .table-footer {
            text-align: center;
            margin-top: 16px;
        }

        .btn-less {
            background: #e0e0e0;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
        }

        .sources-table {
            width: 100%;
            border-collapse: collapse;
        }

        .sources-table thead th {
            font-size: 14px;
            color: #6c757d;
            font-weight: 600;
            text-align: left;
            padding: 12px 8px;
            background: #fafafa;
        }

        .sources-table tbody td {
            padding: 14px 8px;
            font-size: 15px;
            border-top: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        .source-name {
            font-weight: 600;
            color: #333;
        }
        .junk-table {
            width: 100%;
            border-collapse: collapse;
        }

        .junk-table thead th {
            font-size: 14px;
            color: #6c757d;
            font-weight: 600;
            text-align: left;
            padding: 12px 8px;
            background: #fafafa;
        }

        .junk-table tbody td {
            padding: 14px 8px;
            font-size: 15px;
            border-top: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        .junk-reason {
            font-weight: 600;
            color: #333;
        }


    </style>


<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
