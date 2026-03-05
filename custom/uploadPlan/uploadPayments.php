<?php

ob_start();
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("გამარტივებული ატვირთვა");

use Shuchkin\SimpleXLSX;

ini_set('error_reporting', E_ALL);
ini_set('display_errors', true);
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

require_once $_SERVER["DOCUMENT_ROOT"] . '/custom/simplexlsx/src/SimpleXLSX.php';

global $USER;

if ($USER->GetID()) {
    $NotAuthorized = false;
    $user_id = $USER->GetID();
    $USER->Authorize(1);
} else {
    $NotAuthorized = true;
    $USER->Authorize(1);
}

// ===========================================================
// ეროვნული ბანკის USD კურსი კონკრეტულ თარიღში
// ===========================================================
function getNbgKurs($date) {
    if (!$date) return null;
    $dateObj = DateTime::createFromFormat('d/m/Y', $date);
    if (!$dateObj) return null;
    $dateFormatted = $dateObj->format('Y-m-d');
    $url = "https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies?Currencies=USD&date={$dateFormatted}";
    $resp = @file_get_contents($url);
    if (!$resp) return null;
    $json = json_decode($resp);
    return $json[0]->currencies[0]->rate ?? null;
}

// ===========================================================
// AJAX: batch დამუშავება
// ===========================================================
if ($_SERVER["REQUEST_METHOD"] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'process_batch') {

    $batchData = json_decode($_POST['batch_data'], true);
    $iblockId  = 21;

    $results = [
            'success'        => 0,
            'errors'         => [],
            'processed_rows' => 0,
    ];

    $kursCache = [];

    foreach ($batchData as $rowData) {
        $i   = $rowData['index'];
        $row = $rowData['data'];

        // A, C, D, E სავალდებულოა
        if (empty($row[0]) || empty($row[2]) || empty($row[3]) || empty($row[4])) {
            continue;
        }

        $contractNumber = trim($row[0]);
        $clientName     = isset($row[1]) ? trim($row[1]) : '';
        $dateValue      = trim($row[2]);
        $amountUSD      = trim($row[3]);
        $amountGEL      = trim($row[4]);

        // Deal-ის ძიება
        $dealBitrixId = null;
        $dbDeals = CCrmDeal::GetListEx(
                [],
                ['UF_CRM_1766563053146' => $contractNumber, 'CHECK_PERMISSIONS' => 'N'],
                false,
                ['nTopCount' => 1],
                ['ID']
        );
        if ($deal = $dbDeals->Fetch()) {
            $dealBitrixId = intval($deal['ID']);
        } else {
            $results['errors'][] = "სტრიქონი $i: Deal ვერ მოიძებნა ხელშეკრ. ნომრით '$contractNumber'";
            $results['processed_rows']++;
            continue;
        }

        // თარიღი
        $date = convertDateFormat($dateValue);
        if (!$date) {
            $results['errors'][] = "სტრიქონი $i: ვერ დამუშავდა თარიღი '$dateValue'";
            $results['processed_rows']++;
            continue;
        }

        // თანხები
        $usdAmount = parseAmount($amountUSD);
        $gelAmount = parseAmount($amountGEL);

        if ($usdAmount <= 0) {
            $results['errors'][] = "სტრიქონი $i: არასწორი USD თანხა '$amountUSD'";
            $results['processed_rows']++;
            continue;
        }

        // NBG კურსი — ჯერ Excel-ის F სვეტიდან, თუ ცარიელია — API-დან
        $excelRate = isset($row[5]) ? parseAmount(trim($row[5])) : 0;

        if ($excelRate > 0) {
            $nbgRate = $excelRate;
        } else {
            if (!isset($kursCache[$date])) {
                $kursCache[$date] = getNbgKurs($date);
            }
            $nbgRate = $kursCache[$date];
        }

        // ელემენტის შექმნა
        $arProps = [
                'date'      => $date,
                'DEAL'      => $dealBitrixId,
                'DEAL_ID'   => $dealBitrixId,
                'TANXA'     => $usdAmount . '|USD',
                'FULL_NAME' => $clientName,
        ];

        if ($gelAmount > 0) {
            $arProps['tanxa_gel'] = $gelAmount;
        }

        if ($nbgRate) {
            $arProps['NBG'] = $nbgRate;
        }

        $arForAdd = [
                'IBLOCK_ID'       => $iblockId,
                'NAME'            => 'NAME',
                'ACTIVE'          => 'Y',
                'PROPERTY_VALUES' => $arProps,
        ];

        $el = new CIBlockElement;
        if ($el->Add($arForAdd)) {
            $results['success']++;
        } else {
            $results['errors'][] = "სტრიქონი $i ($contractNumber): " . $el->LAST_ERROR;
        }

        $results['processed_rows']++;
    }

    if ($NotAuthorized) {
        $USER->Logout();
    } else {
        $USER->Authorize($user_id);
    }

    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}

// ===========================================================
// თანხის parsing  →  1,600.00  →  1600.00
// ===========================================================
function parseAmount($value) {
    if (empty($value) && $value !== '0') return 0;
    $clean = preg_replace('/[^0-9.,]/', '', $value);
    if (preg_match('/^\d{1,3}(,\d{3})*(\.\d+)?$/', $clean)) {
        $clean = str_replace(',', '', $clean);
    } else {
        $clean = str_replace(',', '.', $clean);
    }
    return floatval($clean);
}

// ===========================================================
// თარიღის კონვერტაცია  →  DD/MM/YYYY
// ===========================================================
function convertDateFormat($dateValue) {
    if (empty($dateValue)) return false;
    $dateValue = trim($dateValue);

    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateValue, $m)) {
        $first = intval($m[1]); $second = intval($m[2]); $year = $m[3];
        if ($first > 12)
            return str_pad($first,  2,'0',STR_PAD_LEFT).'/'.str_pad($second,2,'0',STR_PAD_LEFT).'/'.$year;
        if ($second > 12)
            return str_pad($second, 2,'0',STR_PAD_LEFT).'/'.str_pad($first, 2,'0',STR_PAD_LEFT).'/'.$year;
        return str_pad($second, 2,'0',STR_PAD_LEFT).'/'.str_pad($first, 2,'0',STR_PAD_LEFT).'/'.$year;
    }
    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $dateValue, $m))
        return str_pad($m[1],2,'0',STR_PAD_LEFT).'/'.str_pad($m[2],2,'0',STR_PAD_LEFT).'/'.$m[3];
    if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $dateValue, $m))
        return str_pad($m[1],2,'0',STR_PAD_LEFT).'/'.str_pad($m[2],2,'0',STR_PAD_LEFT).'/'.$m[3];
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateValue, $m))
        return $m[3].'/'.$m[2].'/'.$m[1];
    if (is_numeric($dateValue) && $dateValue > 1000) {
        $unix = ($dateValue - 25569) * 86400;
        if ($unix > 0) return date('d/m/Y', $unix);
    }
    $ts = strtotime($dateValue);
    if ($ts !== false && $ts > 0) return date('d/m/Y', $ts);
    return false;
}

// ===========================================================
// ფაილის ატვირთვა
// ===========================================================
$xlsxData      = null;
$uploadMessage = '';

if ($_SERVER["REQUEST_METHOD"] == 'POST' && isset($_FILES["excelFile"])) {
    $file = $_FILES["excelFile"];
    if (!is_dir('xlsxFiles')) mkdir('xlsxFiles', 0755, true);

    if ($file && strlen($file["tmp_name"])) {
        $filePath = 'xlsxFiles/' . date("YmdHis") . '_' . basename($file["name"]);
        move_uploaded_file($file['tmp_name'], $filePath);

        if ($xlsx = SimpleXLSX::parse($filePath)) {
            $xlsxData      = $xlsx->rows();
            $uploadMessage = 'success';
        } else {
            $uploadMessage = 'error: ' . SimpleXLSX::parseError();
        }
    }
}

if ($NotAuthorized) {
    $USER->Logout();
} else {
    $USER->Authorize($user_id);
}

ob_end_clean();
?>
<!doctype html>
<html lang="ka">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>გადახდების ატვირთვა</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Georgian:wght@300;400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Noto Sans Georgian', sans-serif;
            background: #f4f6fb;
            color: #1e293b;
        }

        .main-card {
            max-width: 860px;
            margin: 40px auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(80,80,160,.09);
            padding: 36px 40px 44px;
        }

        h1 { font-size: 1.65rem; font-weight: 700; color: #1e3a5f; }
        .subtitle { font-size: .82rem; color: #94a3b8; font-family: 'JetBrains Mono', monospace; margin-top: 4px; }

        /* ── Schema ── */
        .schema { display: grid; grid-template-columns: repeat(6,1fr); gap: 8px; margin-bottom: 28px; }
        .col-cell {
            border-radius: 10px; padding: 13px 8px 11px;
            text-align: center; border: 1px solid transparent;
        }
        .col-cell .letter {
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.15rem; font-weight: 700;
            display: block; margin-bottom: 5px;
        }
        .col-cell .lbl  { font-size: .72rem; line-height: 1.4; opacity: .85; }
        .col-cell .desc { font-size: .65rem; color: #64748b; margin-top: 5px; line-height: 1.3; }
        .col-cell .auto-badge {
            display: inline-block; margin-top: 5px;
            background: #16a34a; color: #fff;
            font-size: .58rem; font-weight: 700;
            padding: 2px 7px; border-radius: 20px; letter-spacing: .4px;
        }

        .ca { background: #eef2ff; color: #4f46e5; border-color: #c7d2fe; }
        .cb { background: #f0f9ff; color: #0369a1; border-color: #bae6fd; }
        .cc { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
        .cd { background: #fefce8; color: #a16207; border-color: #fde68a; }
        .ce { background: #f0fdf4; color: #166534; border-color: #86efac; }
        .cf { background: #f0fdf4; color: #15803d; border-color: #4ade80; border-style: dashed; }

        /* ── Section title ── */
        .section-title {
            font-size: .7rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: 1.1px; color: #94a3b8;
            margin-bottom: 14px;
        }

        /* ── Info note ── */
        .info-note {
            background: #eff6ff; border: 1px solid #bfdbfe;
            border-radius: 10px; padding: 14px 18px;
            font-size: .85rem; color: #1e40af;
            margin-bottom: 24px;
            display: flex; align-items: flex-start; gap: 10px;
        }
        .info-note .icon { font-size: 1.1rem; flex-shrink: 0; margin-top: 1px; }

        /* ── File drop zone ── */
        .file-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 12px; padding: 36px 24px;
            text-align: center; position: relative;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            background: #fafbfc;
        }
        .file-zone:hover, .file-zone.drag {
            border-color: #6366f1;
            background: #f5f3ff;
        }
        .file-zone input[type=file] {
            position: absolute; inset: 0;
            opacity: 0; cursor: pointer; width: 100%; height: 100%;
        }
        .file-zone .zone-icon { font-size: 2.2rem; margin-bottom: 8px; }
        .file-zone p { color: #64748b; font-size: .88rem; margin: 0; }
        .file-zone strong { color: #6366f1; }
        .file-zone .hint { font-size: .76rem; color: #94a3b8; margin-top: 6px; }
        .file-chosen {
            display: none; margin-top: 10px;
            font-size: .82rem; font-family: 'JetBrains Mono', monospace;
            color: #16a34a; font-weight: 500;
        }

        /* ── Field hint list ── */
        .field-hints { margin-top: 20px; }
        .field-hint-row {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 9px 0; border-bottom: 1px solid #f1f5f9;
            font-size: .83rem;
        }
        .field-hint-row:last-child { border: none; }
        .fh-col {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600; font-size: .78rem;
            min-width: 22px; padding: 2px 7px;
            border-radius: 5px; text-align: center;
        }
        .fh-a { background: #eef2ff; color: #4f46e5; }
        .fh-b { background: #f0f9ff; color: #0369a1; }
        .fh-c { background: #f0fdf4; color: #15803d; }
        .fh-d { background: #fefce8; color: #a16207; }
        .fh-e { background: #f0fdf4; color: #166534; }
        .fh-f { background: #dcfce7; color: #15803d; }
        .fh-name  { font-weight: 600; color: #334155; min-width: 120px; }
        .fh-desc  { color: #64748b; font-size: .8rem; line-height: 1.5; }
        .fh-badge {
            font-size: .62rem; font-weight: 700; padding: 1px 6px;
            border-radius: 20px; background: #16a34a; color: #fff;
            margin-left: 6px; vertical-align: middle;
        }
        .fh-req {
            font-size: .62rem; font-weight: 700; padding: 1px 6px;
            border-radius: 20px; background: #dc2626; color: #fff;
            margin-left: 6px; vertical-align: middle;
        }

        /* ── Button ── */
        .btn-upload {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: #fff; border: none; border-radius: 10px;
            padding: 13px 32px; font-size: .95rem; font-weight: 700;
            font-family: 'Noto Sans Georgian', sans-serif;
            cursor: pointer; transition: all .2s;
            width: 100%; margin-top: 18px; letter-spacing: .2px;
        }
        .btn-upload:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(79,70,229,.35);
        }
        .btn-upload:disabled { opacity: .45; cursor: not-allowed; transform: none; }

        /* ── Processing notice ── */
        .processing-notice {
            display: none; align-items: center; gap: 10px;
            background: #eff6ff; border: 1px solid #bfdbfe;
            border-radius: 10px; padding: 13px 18px;
            font-size: .87rem; color: #1d4ed8; margin-bottom: 16px;
        }
        .spinner {
            width: 17px; height: 17px; flex-shrink: 0;
            border: 2px solid #bfdbfe; border-top-color: #3b82f6;
            border-radius: 50%; animation: spin .7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Progress ── */
        .progress-wrap { display: none; margin-top: 20px; }
        .progress { height: 10px; border-radius: 10px; background: #e2e8f0; overflow: hidden; }
        .progress-bar {
            height: 100%; width: 0%; border-radius: 10px;
            background: linear-gradient(90deg, #4f46e5, #7c3aed);
            transition: width .3s ease;
        }
        .prog-label {
            font-family: 'JetBrains Mono', monospace; font-size: .74rem;
            color: #94a3b8; text-align: right; margin-top: 5px;
        }

        /* ── Stats ── */
        .stats-grid { display: none; grid-template-columns: repeat(3,1fr); gap: 12px; margin-top: 16px; }
        .stat-card {
            border-radius: 10px; padding: 16px 12px; text-align: center;
            border: 1px solid transparent;
        }
        .stat-card.s-total { background: #f0f9ff; border-color: #bae6fd; }
        .stat-card.s-ok    { background: #f0fdf4; border-color: #bbf7d0; }
        .stat-card.s-err   { background: #fef2f2; border-color: #fecaca; }
        .stat-num {
            font-family: 'JetBrains Mono', monospace; font-size: 2rem; font-weight: 700;
        }
        .s-total .stat-num { color: #0369a1; }
        .s-ok    .stat-num { color: #15803d; }
        .s-err   .stat-num { color: #dc2626; }
        .stat-lbl { font-size: .74rem; color: #64748b; margin-top: 3px; }

        /* ── Result alerts ── */
        .res-alert { border-radius: 10px; padding: 15px 18px; font-size: .87rem; margin-top: 16px; }
        .res-ok   { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
        .res-fail { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .res-warn { background: #fefce8; border: 1px solid #fde68a; color: #854d0e; }
        .res-alert h6 { font-weight: 700; margin-bottom: 7px; }
        .err-list { max-height: 200px; overflow-y: auto; }
        .err-list div {
            padding: 4px 0; border-bottom: 1px solid rgba(0,0,0,.06);
            font-family: 'JetBrains Mono', monospace; font-size: .77rem;
        }
        .err-list div:last-child { border: none; }

        hr.divider { border: none; border-top: 1px solid #f1f5f9; margin: 24px 0; }

        @media (max-width: 560px) {
            .main-card { padding: 24px 18px 32px; }
            .schema { grid-template-columns: repeat(3,1fr); }
            .stats-grid { grid-template-columns: repeat(3,1fr); }
        }
    </style>
</head>
<body>
<div class="main-card">

    <!-- Header -->
    <div class="d-flex align-items-center gap-3 mb-1">
        <div style="width:46px;height:46px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border-radius:11px;display:grid;place-items:center;font-size:21px;flex-shrink:0;">📤</div>
        <div>
            <h1 class="mb-0">გადახდების ატვირთვა</h1>
            <div class="subtitle">iBlock ID: 21 &nbsp;·&nbsp; batch size: 50</div>
        </div>
    </div>

    <hr class="divider">

    <!-- Info note -->
    <div class="info-note">
        <span class="icon">💡</span>
        <span>Excel-ის <strong>F სვეტში</strong> NBG კურსი შეგიძლიათ მიუთითოთ ხელით. თუ ცარიელია, კურსი ავტომატურად წამოვა <strong>ეროვნული ბანკის API-დან</strong> გადახდის თარიღის მიხედვით.</span>
    </div>

    <!-- Schema -->
    <div class="section-title">Excel ფაილის სტრუქტურა</div>
    <div class="schema">
        <div class="col-cell ca">
            <span class="letter">A</span>
            <span class="lbl">ხელშეკრულების ნომერი</span>
        </div>
        <div class="col-cell cb">
            <span class="letter">B</span>
            <span class="lbl">კლიენტის სახელი</span>
        </div>
        <div class="col-cell cc">
            <span class="letter">C</span>
            <span class="lbl">თარიღი</span>
        </div>
        <div class="col-cell cd">
            <span class="letter">D</span>
            <span class="lbl">თანხა USD</span>
        </div>
        <div class="col-cell ce">
            <span class="letter">E</span>
            <span class="lbl">თანხა GEL</span>
        </div>
        <div class="col-cell cf">
            <span class="letter">F</span>
            <span class="lbl">NBG კურსი</span>
            <span class="auto-badge">AUTO</span>
        </div>
    </div>

    <!-- Field descriptions -->
    <div class="field-hints mb-4">
        <div class="field-hint-row">
            <span class="fh-col fh-a">A</span>
            <div>
                <div class="fh-name">ხელშეკრულების ნომერი </div>
                <div class="fh-desc">Bitrix CRM-ში Deal-ის მოსაძებნი უნიკალური ნომერი (მაგ. PB/SALES/001). სისტემა ამ ველით პოულობს შესაბამის Deal-ს.</div>
            </div>
        </div>
        <div class="field-hint-row">
            <span class="fh-col fh-b">B</span>
            <div>
                <div class="fh-name">კლიენტის სახელი</div>
                <div class="fh-desc">გადამხდელის სრული სახელი — FULL_NAME ველში ინახება.</div>
            </div>
        </div>
        <div class="field-hint-row">
            <span class="fh-col fh-c">C</span>
            <div>
                <div class="fh-name">თარიღი </div>
                <div class="fh-desc">გადახდის თარიღი. მიიღება ფორმატები: MM/DD/YYYY, DD.MM.YYYY, DD-MM-YYYY, YYYY-MM-DD ან Excel-ის სერიული რიცხვი.</div>
            </div>
        </div>
        <div class="field-hint-row">
            <span class="fh-col fh-d">D</span>
            <div>
                <div class="fh-name">თანხა USD </div>
                <div class="fh-desc">გადახდის თანხა დოლარებში. მიიღება ფორმატები: 1600.00 ან 1,600.00.</div>
            </div>
        </div>
        <div class="field-hint-row">
            <span class="fh-col fh-e">E</span>
            <div>
                <div class="fh-name">თანხა GEL </div>
                <div class="fh-desc">გადახდის თანხა ლარებში. ინახება tanxa_gel ველში. თუ 0-ია, ველი ცარიელი რჩება.</div>
            </div>
        </div>
        <div class="field-hint-row">
            <span class="fh-col fh-f">F</span>
            <div>
                <div class="fh-name">NBG კურსი </div>
                <div class="fh-desc">USD/GEL გაცვლითი კურსი. თუ Excel-ში მითითებულია — გამოიყენება ის. თუ ცარიელია — ავტომატურად წამოვა <strong>nbg.gov.ge</strong>-დან გადახდის თარიღისთვის.</div>
            </div>
        </div>
    </div>

    <hr class="divider">

    <!-- Upload form -->
    <div class="section-title mb-3">ფაილის ატვირთვა</div>

    <div class="processing-notice" id="processingNotice">
        <div class="spinner"></div>
        <span>მიმდინარეობს დამუშავება — გთხოვთ დაელოდოთ...</span>
    </div>

    <form method="post" enctype="multipart/form-data" id="uploadForm">
        <div class="file-zone" id="fileArea">
            <input type="file" name="excelFile" id="fileInput" accept=".xlsx" required>
            <div class="zone-icon">📂</div>
            <p>ჩააგდეთ ფაილი ან <strong>დააჭირეთ არჩევისთვის</strong></p>
            <p class="hint">მხოლოდ .xlsx ფორმატი</p>
            <div class="file-chosen" id="fileChosen"></div>
        </div>
        <button type="submit" class="btn-upload" id="uploadBtn">⬆️&nbsp; ატვირთვა და დამუშავება</button>
    </form>

    <!-- Progress -->
    <div class="progress-wrap" id="progressWrap">
        <div class="progress"><div class="progress-bar" id="progressFill"></div></div>
        <div class="prog-label" id="progressLabel">0%</div>
        <div class="stats-grid" id="statsBox">
            <div class="stat-card s-total">
                <div class="stat-num" id="totalCount">0</div>
                <div class="stat-lbl">სულ სტრიქონი</div>
            </div>
            <div class="stat-card s-ok">
                <div class="stat-num" id="successCount">0</div>
                <div class="stat-lbl">წარმატებული</div>
            </div>
            <div class="stat-card s-err">
                <div class="stat-num" id="errorCount">0</div>
                <div class="stat-lbl">შეცდომა</div>
            </div>
        </div>
    </div>

    <div id="results"></div>

</div>

<script>
    const BATCH_SIZE = 50;

    document.getElementById('fileInput').addEventListener('change', function() {
        const el = document.getElementById('fileChosen');
        if (this.files[0]) { el.textContent = '✓ ' + this.files[0].name; el.style.display = 'block'; }
    });

    const fa = document.getElementById('fileArea');
    fa.addEventListener('dragover', e => { e.preventDefault(); fa.classList.add('drag'); });
    fa.addEventListener('dragleave', () => fa.classList.remove('drag'));
    fa.addEventListener('drop', () => fa.classList.remove('drag'));

    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        e.preventDefault();
        document.getElementById('results').innerHTML = '';
        fetch(window.location.href, { method: 'POST', body: new FormData(this) })
            .then(r => r.text())
            .then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                let data = null;
                for (const s of doc.querySelectorAll('script')) {
                    const match = s.textContent.match(/const xlsxData\s*=\s*(null|\[[\s\S]*?\]);/);
                    if (match && match[1] !== 'null') { try { data = JSON.parse(match[1]); break; } catch(ex) {} }
                }
                data ? startBatchProcessing(data)
                    : showError('ფაილის წაკითხვა ვერ მოხერხდა — შეამოწმეთ ფორმატი (.xlsx)');
            })
            .catch(err => showError('ქსელის შეცდომა: ' + err.message));
    });

    async function startBatchProcessing(data) {
        if (!data || data.length < 2) { showError('ფაილი ცარიელია'); return; }

        document.getElementById('uploadBtn').disabled = true;
        document.getElementById('processingNotice').style.display = 'flex';
        document.getElementById('progressWrap').style.display = 'block';
        document.getElementById('statsBox').style.display = 'grid';

        const rows = data.slice(1);
        const total = rows.length;
        const totalBatches = Math.ceil(total / BATCH_SIZE);
        document.getElementById('totalCount').textContent = total;

        let totalSuccess = 0, totalErrors = 0, allErrors = [], processed = 0;

        for (let i = 0; i < totalBatches; i++) {
            const start = i * BATCH_SIZE;
            const batch = rows.slice(start, start + BATCH_SIZE);
            const batchData = batch.map((row, idx) => ({ index: start + idx + 2, data: row }));

            try {
                const result = await processBatch(batchData);
                totalSuccess += result.success;
                totalErrors  += result.errors.length;
                allErrors     = allErrors.concat(result.errors);
                processed    += batch.length;
            } catch (err) {
                allErrors.push('Batch ' + (i+1) + ' შეცდომა: ' + err.message);
                totalErrors++;
            }

            const pct = Math.round((processed / total) * 100);
            document.getElementById('progressFill').style.width  = pct + '%';
            document.getElementById('progressLabel').textContent = pct + '%';
            document.getElementById('successCount').textContent  = totalSuccess;
            document.getElementById('errorCount').textContent    = totalErrors;

            if (i < totalBatches - 1) await new Promise(r => setTimeout(r, 100));
        }

        document.getElementById('processingNotice').style.display = 'none';
        showResults(totalSuccess, allErrors, total);
        document.getElementById('uploadBtn').disabled = false;
    }

    function processBatch(batchData) {
        const fd = new FormData();
        fd.append('action',     'process_batch');
        fd.append('batch_data', JSON.stringify(batchData));
        return fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); });
    }

    function showResults(successCount, errors, totalRows) {
        const rate = totalRows > 0 ? Math.round((successCount / totalRows) * 100) : 0;
        let html = '';
        if (successCount > 0) {
            html += `<div class="res-alert res-ok">
                <h6>✅ ატვირთვა დასრულდა</h6>
                წარმატებით დაემატა <strong>${successCount}</strong> / <strong>${totalRows}</strong> გადახდა (${rate}%)
            </div>`;
        }
        if (errors.length > 0) {
            html += `<div class="res-alert res-fail">
                <h6>⚠️ შეცდომები — ${errors.length}</h6>
                <div class="err-list">${errors.map(e => `<div>· ${e}</div>`).join('')}</div>
            </div>`;
        }
        if (!successCount && !errors.length) {
            html += `<div class="res-alert res-warn">
                <h6>ℹ️ ჩანაწერები ვერ დამუშავდა</h6>
                სვეტების რიგი: A=ხელშეკრ.N | B=კლიენტი | C=თარიღი | D=USD | E=GEL | F=კურსი
            </div>`;
        }
        document.getElementById('results').innerHTML = html;
        document.getElementById('results').scrollIntoView({ behavior: 'smooth' });
    }

    function showError(msg) {
        document.getElementById('results').innerHTML =
            `<div class="res-alert res-fail"><strong>❌ ${msg}</strong></div>`;
    }

    const xlsxData  = <?php echo $xlsxData ? json_encode($xlsxData) : 'null'; ?>;
    const uploadMsg = <?php echo json_encode($uploadMessage); ?>;
</script>
</body>
</html>