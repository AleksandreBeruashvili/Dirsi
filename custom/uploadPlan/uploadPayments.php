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

    // კურსების cache — ერთი თარიღისთვის NBG-ს მხოლოდ ერთხელ
    $kursCache = [];

    foreach ($batchData as $rowData) {
        $i   = $rowData['index'];
        $row = $rowData['data'];

        // A, C, D, E სავალდებულოა
        if (empty($row[0]) || empty($row[2]) || empty($row[3]) || empty($row[4])) {
            continue;
        }

        $contractNumber = trim($row[0]);                        // A — ხელშეკრულების N
        $clientName     = isset($row[1]) ? trim($row[1]) : ''; // B — კლიენტი
        $dateValue      = trim($row[2]);                        // C — თარიღი (MM/DD/YYYY)
        $amountUSD      = trim($row[3]);                        // D — თანხა USD
        $amountGEL      = trim($row[4]);                        // E — თანხა GEL (Excel-დან)
        // F — კურსი: ავტომატურად NBG-დან

        // ----- Deal-ის ძიება -----
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

        // ----- თარიღი -----
        $date = convertDateFormat($dateValue);
        if (!$date) {
            $results['errors'][] = "სტრიქონი $i: ვერ დამუშავდა თარიღი '$dateValue'";
            $results['processed_rows']++;
            continue;
        }

        // ----- თანხები Excel-დან -----
        $usdAmount = parseAmount($amountUSD);
        $gelAmount = parseAmount($amountGEL);

        if ($usdAmount <= 0) {
            $results['errors'][] = "სტრიქონი $i: არასწორი USD თანხა '$amountUSD'";
            $results['processed_rows']++;
            continue;
        }

        // ----- NBG კურსი (cache-ით, მხოლოდ NBG ველისთვის) -----
        if (!isset($kursCache[$date])) {
            $kursCache[$date] = getNbgKurs($date);
        }
        $nbgRate = $kursCache[$date]; // null-ი დასაშვებია — უბრალოდ NBG ველი ცარიელი დარჩება

        // ----- ელემენტის შექმნა -----
        $arProps = [
                'date'      => $date,
                'DEAL'      => ['D_' . $dealBitrixId],
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

    // MM/DD/YYYY (ამერიკული — Excel)
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateValue, $m)) {
        $first = intval($m[1]); $second = intval($m[2]); $year = $m[3];
        if ($first > 12)
            return str_pad($first,  2,'0',STR_PAD_LEFT).'/'.str_pad($second,2,'0',STR_PAD_LEFT).'/'.$year;
        if ($second > 12)
            return str_pad($second, 2,'0',STR_PAD_LEFT).'/'.str_pad($first, 2,'0',STR_PAD_LEFT).'/'.$year;
        // ორივე ≤ 12 → MM/DD (ამერიკული)
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6fb; }
        .main-card {
            max-width: 860px; margin: 40px auto;
            background: #fff; border-radius: 16px;
            box-shadow: 0 4px 24px rgba(80,80,160,.10);
            padding: 36px 40px 40px;
        }
        h1 { font-size: 1.7rem; font-weight: 700; color: #3730a3; }
        .info-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; padding: 18px 22px;
            border-radius: 10px; margin-bottom: 28px;
        }
        .info-box h5 { font-weight: 700; margin-bottom: 6px; }
        .info-box p  { margin: 0; font-size: .93rem; opacity: .93; }

        .schema-box { display: flex; gap: 6px; margin-bottom: 28px; }
        .schema-col {
            flex: 1; border-radius: 8px; padding: 11px 8px 9px;
            text-align: center; font-size: .78rem; line-height: 1.4;
        }
        .schema-col .col-letter { font-size: 1.2rem; font-weight: 800; display: block; margin-bottom: 3px; }
        .schema-col.ca { background: #ede9fe; color: #5b21b6; }
        .schema-col.cb { background: #f1f5f9; color: #64748b; }
        .schema-col.cc { background: #ecfdf5; color: #065f46; }
        .schema-col.cd { background: #fef9c3; color: #854d0e; }
        .schema-col.ce { background: #dcfce7; color: #166534; }
        .schema-col.cf { background: #f0fdf4; color: #166534; border: 2px dashed #86efac; }
        .auto-label {
            display: inline-block; background: #22c55e; color: white;
            border-radius: 4px; font-size: .65rem; padding: 1px 5px; font-weight: 700; margin-top: 3px;
        }
        .btn-upload {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none; padding: 12px 36px;
            font-size: 1rem; border-radius: 8px; font-weight: 600; transition: all .2s;
        }
        .btn-upload:hover    { transform: translateY(-2px); color: white; box-shadow: 0 4px 12px rgba(102,126,234,.4); }
        .btn-upload:disabled { opacity: .6; transform: none; }
        .progress           { height: 32px; border-radius: 16px; }
        .progress-bar       { font-size: .9rem; line-height: 32px; background: linear-gradient(90deg, #667eea, #764ba2); }
        .progress-container { display: none; margin-top: 24px; }
        .stats-box          { display: flex; gap: 14px; margin-top: 16px; }
        .stat-card          { flex: 1; padding: 14px; border-radius: 10px; text-align: center; }
        .stat-card.info     { background: #dbeafe; color: #1e40af; }
        .stat-card.success  { background: #d1fae5; color: #065f46; }
        .stat-card.error    { background: #fee2e2; color: #991b1b; }
        .stat-number        { font-size: 2rem; font-weight: 700; }
    </style>
</head>
<body>
<div class="main-card">

    <h1 class="mb-1">📤 გადახდების ატვირთვა</h1>
    <p class="text-muted mb-4" style="font-size:.9rem;">iBlock 21 — გადახდების სია</p>

    <div class="info-box">
        <h5>ინსტრუქცია</h5>
        <p>USD და GEL თანხები Excel-დან მოდის, <strong>NBG კურსი</strong> კი ავტომატურად
            წამოიღება ეროვნული ბანკიდან გადახდის თარიღისთვის.</p>
    </div>

    <div class="schema-box">
        <div class="schema-col ca">
            <span class="col-letter">A</span>ხელშეკრ. N<br>
        </div>
        <div class="schema-col cb">
            <span class="col-letter">B</span>კლიენტი<br>
        </div>
        <div class="schema-col cc">
            <span class="col-letter">C</span>თარიღი<br>
        </div>
        <div class="schema-col cd">
            <span class="col-letter">D</span>თანხა USD<br>
        </div>
        <div class="schema-col ce">
            <span class="col-letter">E</span>თანხა GEL<br>
        </div>
        <div class="schema-col cf">
            <span class="col-letter">F</span>კურსი<br>
            <span class="auto-label">NBG AUTO</span>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data" id="uploadForm">
        <div class="mb-4">
            <label class="form-label fw-bold">Excel ფაილი (.xlsx)</label>
            <input type="file" name="excelFile" class="form-control form-control-lg" accept=".xlsx" required>
        </div>
        <button type="submit" class="btn btn-upload" id="uploadBtn">⬆️&nbsp; ატვირთვა</button>
    </form>

    <div class="progress-container" id="progressContainer">
        <div class="alert alert-info mt-3">
            <strong>⏳ მიმდინარეობს დამუშავება...</strong> გთხოვთ დაელოდოთ.
        </div>
        <div class="progress">
            <div class="progress-bar progress-bar-striped progress-bar-animated"
                 role="progressbar" style="width:0%" id="progressBar">0%</div>
        </div>
        <div class="stats-box" id="statsBox" style="display:none">
            <div class="stat-card info">
                <div class="stat-number" id="totalCount">0</div><div>სულ სტრიქონი</div>
            </div>
            <div class="stat-card success">
                <div class="stat-number" id="successCount">0</div><div>წარმატებული</div>
            </div>
            <div class="stat-card error">
                <div class="stat-number" id="errorCount">0</div><div>შეცდომა</div>
            </div>
        </div>
    </div>

    <div id="results" class="mt-4"></div>
</div>

<script>
    const IBLOCK_ID  = 21;
    const BATCH_SIZE = 50;

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
                    if (match && match[1] !== 'null') {
                        try { data = JSON.parse(match[1]); break; } catch(ex) {}
                    }
                }
                data ? startBatchProcessing(data)
                    : showError('❌ ფაილის წაკითხვა ვერ მოხერხდა. შეამოწმეთ ფორმატი (.xlsx).');
            })
            .catch(err => showError('❌ ქსელის შეცდომა: ' + err.message));
    });

    async function startBatchProcessing(data) {
        if (!data || data.length < 2) { showError('❌ ფაილი ცარიელია'); return; }

        document.getElementById('uploadBtn').disabled = true;
        document.getElementById('progressContainer').style.display = 'block';

        const rows = data.slice(1);
        const totalBatches = Math.ceil(rows.length / BATCH_SIZE);
        document.getElementById('totalCount').textContent = rows.length;
        document.getElementById('statsBox').style.display = 'flex';

        let totalSuccess = 0, totalErrors = 0, allErrors = [], processed = 0;

        for (let i = 0; i < totalBatches; i++) {
            const start     = i * BATCH_SIZE;
            const batch     = rows.slice(start, start + BATCH_SIZE);
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

            const pct = Math.round((processed / rows.length) * 100);
            document.getElementById('progressBar').style.width  = pct + '%';
            document.getElementById('progressBar').textContent  = pct + '%';
            document.getElementById('successCount').textContent = totalSuccess;
            document.getElementById('errorCount').textContent   = totalErrors;

            if (i < totalBatches - 1) await new Promise(r => setTimeout(r, 100));
        }

        showResults(totalSuccess, allErrors, rows.length);
        document.getElementById('uploadBtn').disabled = false;
    }

    function processBatch(batchData) {
        const fd = new FormData();
        fd.append('action',     'process_batch');
        fd.append('batch_data', JSON.stringify(batchData));
        fd.append('iblock_id',  IBLOCK_ID);
        return fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); });
    }

    function showResults(successCount, errors, totalRows) {
        const rate = totalRows > 0 ? Math.round((successCount / totalRows) * 100) : 0;
        let html = '';
        if (successCount > 0) {
            html += `<div class="alert alert-success">
            <h5 class="mb-1">✅ დასრულდა!</h5>
            <p class="mb-0">წარმატებით დაემატა <strong>${successCount}</strong> გადახდა
            <strong>${totalRows}</strong>-დან (${rate}%)</p>
        </div>`;
        }
        if (errors.length > 0) {
            html += `<div class="alert alert-danger">
            <h6 class="mb-2">⚠️ შეცდომები (${errors.length}):</h6>
            <div style="max-height:220px;overflow-y:auto;font-size:.875rem;">`;
            errors.forEach(e => { html += `<div class="mb-1">• ${e}</div>`; });
            html += `</div></div>`;
        }
        if (!successCount && !errors.length) {
            html += `<div class="alert alert-warning">
            <h6>ℹ️ ჩანაწერები ვერ დამუშავდა</h6>
            <p class="mb-0">Excel სტრუქტურა: A=ხელშეკრ.N | B=კლიენტი | C=თარიღი | D=USD | E=GEL</p>
        </div>`;
        }
        document.getElementById('results').innerHTML = html;
        document.getElementById('results').scrollIntoView({ behavior: 'smooth' });
    }

    function showError(msg) {
        document.getElementById('results').innerHTML =
            `<div class="alert alert-danger"><strong>${msg}</strong></div>`;
    }

    const xlsxData  = <?php echo $xlsxData ? json_encode($xlsxData) : 'null'; ?>;
    const uploadMsg = <?php echo json_encode($uploadMessage); ?>;
</script>
</body>
</html>