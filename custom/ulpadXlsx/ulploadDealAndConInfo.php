<?php

ob_start();
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("ხელშეკრულება → კონტაქტი (Excel)");

ini_set('error_reporting', E_ALL);
ini_set('display_errors', true);
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

require_once $_SERVER["DOCUMENT_ROOT"] . '/custom/simplexlsx/src/SimpleXLSX.php';

use Shuchkin\SimpleXLSX;

CModule::IncludeModule("crm");

global $USER;

if ($USER->GetID()) {
    $NotAuthorized = false;
    $user_id = $USER->GetID();
    $USER->Authorize(1);
} else {
    $NotAuthorized = true;
    $USER->Authorize(1);
}

$contactUpdateOptions = [
    "CHECK_PERMISSIONS" => false,
    "DISABLE_USER_FIELD_CHECK" => true,
];

$dealUpdateOptions = [
    "CHECK_PERMISSIONS" => false,
];

function normalizeSourceLookupKey($raw)
{
    $s = trim(preg_replace('/\s+/u', ' ', (string) $raw));

    return mb_strtolower($s, "UTF-8");
}

/**
 * Deal.SOURCE_ID — b_crm_status ENTITY_ID = SOURCE.
 * ექსელის C: b_crm_status.ID (რიცხვი), STATUS_ID, ან NAME (როგორც CRM-შია, სივრცეების ნორმალიზაციით).
 *
 * @return array{
 *   by_status_id: array<string,string>,
 *   by_name: array<string,string>,
 *   by_name_norm: array<string,string>,
 *   by_row_id: array<int,string>,
 *   items: list<array{id:int,status_id:string,name:string}>
 * }
 */
function buildDealSourceIndex()
{
    $byStatusId = [];
    $byName = [];
    $byNameNorm = [];
    $byRowId = [];
    $items = [];

    $dbRes = CCrmStatus::GetList(["SORT" => "ASC"], ["ENTITY_ID" => "SOURCE"]);
    while ($ar = $dbRes->Fetch()) {
        $sid = (string) $ar["STATUS_ID"];
        $name = trim((string) $ar["NAME"]);
        $rowId = isset($ar["ID"]) ? (int) $ar["ID"] : 0;

        if ($rowId > 0) {
            $byRowId[$rowId] = $sid;
            $items[] = [
                "id" => $rowId,
                "status_id" => $sid,
                "name" => $name,
            ];
        }

        $byStatusId[mb_strtolower($sid, "UTF-8")] = $sid;

        if ($name !== "") {
            $byName[mb_strtolower($name, "UTF-8")] = $sid;
            $byNameNorm[normalizeSourceLookupKey($name)] = $sid;
        }
    }

    return [
        "by_status_id" => $byStatusId,
        "by_name" => $byName,
        "by_name_norm" => $byNameNorm,
        "by_row_id" => $byRowId,
        "items" => $items,
    ];
}

/**
 * @param array $index buildDealSourceIndex()-ის შედეგი
 * @return array{0:?string,1:string} [STATUS_ID Deal-ისთვის, ან შეცდომის ტექსტი]
 */
function resolveDealSourceId($raw, array $index)
{
    $t = trim((string) $raw);
    if ($t === "") {
        return [null, ""];
    }

    if (ctype_digit($t)) {
        $id = (int) $t;
        if ($id > 0 && !empty($index["by_row_id"][$id])) {
            return [$index["by_row_id"][$id], ""];
        }
        return [null, $t];
    }

    $tl = mb_strtolower($t, "UTF-8");
    if (!empty($index["by_status_id"][$tl])) {
        return [$index["by_status_id"][$tl], ""];
    }
    if (!empty($index["by_name"][$tl])) {
        return [$index["by_name"][$tl], ""];
    }

    $normKey = normalizeSourceLookupKey($t);
    if ($normKey !== "" && !empty($index["by_name_norm"][$normKey])) {
        return [$index["by_name_norm"][$normKey], ""];
    }

    return [null, $t];
}

/**
 * A: ხელშეკრულების # → Deal.UF_CRM_1766563053146
 */
function findDealByContractNumber($contractNumber)
{
    $contractNumber = trim((string) $contractNumber);
    if ($contractNumber === "") {
        return null;
    }

    $filter = [
        "UF_CRM_1766563053146" => $contractNumber,
    ];
    $select = ["ID", "TITLE", "CONTACT_ID"];

    $res = CCrmDeal::GetListEx([], $filter, false, ["nTopCount" => 2], $select);
    $first = $res ? $res->Fetch() : null;
    if (!$first) {
        return null;
    }
    $second = $res->Fetch();
    return [
        "deal" => $first,
        "duplicate" => $second ? true : false,
    ];
}

/**
 * B: ნაციონალობა → Contact.UF_CRM_1769506891465 (enum)
 * აზერბაიჯანი → 157, საქართველო → 156
 */
function mapNationalityEnum($raw)
{
    $s = mb_strtolower(trim((string) $raw), "UTF-8");
    if ($s === "") {
        return [null, ""];
    }

    if (mb_strpos($s, "აზერბაიჯან") !== false) {
        return [157, ""];
    }
    if (mb_strpos($s, "საქართველ") !== false) {
        return [156, ""];
    }
    if (mb_strpos($s, "azerbaijan") !== false) {
        return [157, ""];
    }
    if (mb_strpos($s, "georgia") !== false || $s === "geo") {
        return [156, ""];
    }

    return [null, $raw];
}

function splitGeorgianName($raw)
{
    $raw = trim((string) $raw);
    if ($raw === "") {
        return ["", ""];
    }
    if (preg_match('/^(\S+)\s+(.+)$/u', $raw, $m)) {
        return [trim($m[1]), trim($m[2])];
    }
    return [$raw, ""];
}

function processRowsFromXlsx(array $rows, array $contactUpdateOptions, array $dealUpdateOptions, array $sourceIndex)
{
    $out = [
        "ok" => 0,
        "errors" => [],
        "warnings" => [],
    ];

    if (count($rows) < 2) {
        $out["errors"][] = "ფაილში მონაცემები არ არის (ან მხოლოდ სათაურია).";
        return $out;
    }

    $dataRows = array_slice($rows, 1);
    $rowNumBase = 2;

    foreach ($dataRows as $idx => $row) {
        $excelRow = $rowNumBase + (int) $idx;

        $contract = isset($row[0]) ? trim((string) $row[0]) : "";
        if ($contract === "") {
            continue;
        }

        $found = findDealByContractNumber($contract);
        if (!$found) {
            $out["errors"][] = "სტრიქონი {$excelRow}: დილი ვერ მოიძებნა ხელშეკრულების # „{$contract}“.";
            continue;
        }
        if (!empty($found["duplicate"])) {
            $out["warnings"][] = "სტრიქონი {$excelRow}: რამდენიმე დილი ემთხვევა „{$contract}“ — განახლებულია პირველი (ID {$found["deal"]["ID"]}).";
        }

        $deal = $found["deal"];
        $dealId = (int) $deal["ID"];
        $contactId = (int) $deal["CONTACT_ID"];

        $nationalityRaw = isset($row[1]) ? $row[1] : "";
        list($nationalityEnum, $nationalityUnknown) = mapNationalityEnum($nationalityRaw);

        $sourceRaw = isset($row[2]) ? $row[2] : "";
        list($sourceId, $sourceUnknown) = resolveDealSourceId($sourceRaw, $sourceIndex);

        $nameRu = isset($row[3]) ? trim((string) $row[3]) : "";
        $nameEn = isset($row[4]) ? trim((string) $row[4]) : "";
        $nameGe = isset($row[5]) ? trim((string) $row[5]) : "";
        $actualRu = isset($row[6]) ? trim((string) $row[6]) : "";
        $legalRu = isset($row[7]) ? trim((string) $row[7]) : "";

        $nationalityCellFilled = trim((string) $nationalityRaw) !== "";
        if ($nationalityCellFilled && $nationalityEnum === null) {
            $out["errors"][] = "სტრიქონი {$excelRow}: ნაციონალობა „{$nationalityUnknown}“ — უცნობი მნიშვნელობა (მოსალოდნელია: აზერბაიჯანი ან საქართველო), ან დატოვე B ცარიელი.";
            continue;
        }

        if ($sourceUnknown !== "") {
            $out["errors"][] = "სტრიქონი {$excelRow}: წყარო „{$sourceUnknown}“ — ვერ მოიძებნა (C სვეტში ჩაწერე b_crm_status ID, STATUS_ID ან NAME — იხილე გვერდზე სია „წყაროები CRM-დან“).";
            continue;
        }

        list($name, $lastName) = splitGeorgianName($nameGe);

        $fields = [];

        if ($nationalityEnum !== null) {
            $fields["UF_CRM_1769506891465"] = $nationalityEnum;
        }
        if ($nameRu !== "") {
            $fields["UF_CRM_1766144180428"] = $nameRu;
        }
        if ($nameEn !== "") {
            $fields["UF_CRM_1767604263120"] = $nameEn;
        }
        if ($name !== "" || $lastName !== "") {
            if ($name !== "") {
                $fields["NAME"] = $name;
            }
            if ($lastName !== "") {
                $fields["LAST_NAME"] = $lastName;
            }
        }
        if ($actualRu !== "") {
            $fields["UF_CRM_1766144198587"] = $actualRu;
        }
        if ($legalRu !== "") {
            $fields["UF_CRM_1766144293570"] = $legalRu;
        }

        $dealFields = [];
        if ($sourceId !== null && $sourceId !== "") {
            $dealFields["SOURCE_ID"] = $sourceId;
        }

        if (empty($fields) && empty($dealFields)) {
            $out["warnings"][] = "სტრიქონი {$excelRow}: დილი #{$dealId} — გასაახლებელი ველები ცარიელია.";
            continue;
        }

        if (!empty($fields) && $contactId <= 0) {
            $out["errors"][] = "სტრიქონი {$excelRow}: დილს #{$dealId} არ აქვს კონტაქტი, ხოლო ექსელში კონტაქტის ველებია შევსებული.";
            continue;
        }

        $rowOk = false;

        if (!empty($fields)) {
            $contact = new CCrmContact(false);
            $ok = $contact->Update($contactId, $fields, true, true, $contactUpdateOptions);
            if (!$ok) {
                $out["errors"][] = "სტრიქონი {$excelRow}: კონტაქტი #{$contactId} — Update შეცდომა: " . $contact->LAST_ERROR;
            } else {
                $rowOk = true;
            }
        }

        if (!empty($dealFields)) {
            $dealObj = new CCrmDeal(false);
            $okDeal = $dealObj->Update($dealId, $dealFields, true, true, $dealUpdateOptions);
            if (!$okDeal) {
                $out["errors"][] = "სტრიქონი {$excelRow}: დილი #{$dealId} SOURCE_ID — Update შეცდომა: " . $dealObj->LAST_ERROR;
            } else {
                $rowOk = true;
            }
        }

        if ($rowOk) {
            $out["ok"]++;
        }
    }

    return $out;
}

$report = null;
$uploadMessage = "";

$sourcesCatalog = buildDealSourceIndex();

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["xlsx"])) {
    $file = $_FILES["xlsx"];
    if ($file && strlen($file["tmp_name"])) {
        if (!is_dir(__DIR__ . "/xlsxFiles")) {
            mkdir(__DIR__ . "/xlsxFiles", 0777, true);
        }
        $timestamp = date("YmdHis");
        $filePath = __DIR__ . '/xlsxFiles/' . $timestamp . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file["name"]);
        if (move_uploaded_file($file["tmp_name"], $filePath)) {
            if ($xlsx = SimpleXLSX::parse($filePath)) {
                $report = processRowsFromXlsx(
                    $xlsx->rows(),
                    $contactUpdateOptions,
                    $dealUpdateOptions,
                    $sourcesCatalog
                );
                $uploadMessage = "success";
            } else {
                $uploadMessage = "error: " . SimpleXLSX::parseError();
            }
        } else {
            $uploadMessage = "error: ფაილის ატვირთვა ვერ მოხერხდა";
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
    <title>ხელშეკრულება → კონტაქტი</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-0evHe/X+R7YkIZDRvuzKMRqM+OrBnVFBL6DOitfPri4tjfHxaWutUpFmBp4vmVor" crossorigin="anonymous">
    <style>
        .container { max-width: 900px; margin-top: 40px; }
        .col-map { background: #f8f9fa; padding: 16px; border-radius: 8px; font-size: 14px; }
    </style>
</head>
<body>
<div class="container">
    <h1 class="h3 mb-3">Excel → დილი (ხელშეკრულების #) → კონტაქტი</h1>

    <div class="col-map mb-4">
        <p class="mb-2"><strong>სვეტები (პირველი სტრიქონი — სათაური, იგნორირდება):</strong></p>
        <p class="mb-2 text-muted">დილზე იცვლება მხოლოდ <code>SOURCE_ID</code> (C სვეტი). დანარჩენი სვეტები კონტაქტზეა; A სვეტით მხოლოდ ძებნაა <code>UF_CRM_1766563053146</code>-ით.</p>
        <ul class="mb-0">
            <li><strong>A</strong> — ხელშეკრულების # — დილის ძებნა <code>UF_CRM_1766563053146</code> (არ იწერება ექსელიდან)</li>
            <li><strong>B</strong> — ნაციონალობა → Contact <code>UF_CRM_1769506891465</code> (აზერბაიჯანი = 157, საქართველო = 156; ცარიელი B — ველი არ შეიცვლება)</li>
            <li><strong>C</strong> — წყარო → Deal <strong>მხოლოდ</strong> <code>SOURCE_ID</code> — ქვემოთ სიიდან აირჩიე <strong>ID</strong> (რიცხვი), ან ზუსტად <strong>STATUS_ID</strong>, ან <strong>სახელი</strong> როგორც CRM-შია</li>
            <li><strong>D</strong> — სახელი/გვარი (რუს) → <code>UF_CRM_1766144180428</code></li>
            <li><strong>E</strong> — სახელი/გვარი (ინგ) → <code>UF_CRM_1767604263120</code></li>
            <li><strong>F</strong> — სახელი/გვარი (ქართ) → პირველი სიტყვა <code>NAME</code>, დანარჩენი <code>LAST_NAME</code></li>
            <li><strong>G</strong> — ფაქტობრივი მისამართი (რუს) → <code>UF_CRM_1766144198587</code></li>
            <li><strong>H</strong> — იურიდიული მისამართი (რუს) → <code>UF_CRM_1766144293570</code></li>
        </ul>
    </div>

    <?php if (!empty($sourcesCatalog["items"])): ?>
        <div class="col-map mb-4">
            <p class="mb-2"><strong>წყაროები CRM-დან</strong> (ექსელის C სვეტი — ერთ-ერთი სვეტი „ID“, „STATUS_ID“ ან „სახელი“)</p>
            <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light sticky-top">
                    <tr>
                        <th>ID</th>
                        <th>STATUS_ID</th>
                        <th>სახელი (NAME)</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sourcesCatalog["items"] as $src): ?>
                        <tr>
                            <td><code><?= (int) $src["id"] ?></code></td>
                            <td><code><?= htmlspecialchars($src["status_id"]) ?></code></td>
                            <td><?= htmlspecialchars($src["name"]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($uploadMessage && strpos($uploadMessage, "error:") === 0): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($uploadMessage) ?></div>
    <?php endif; ?>

    <?php if ($report): ?>
        <?php if ($report["ok"] > 0): ?>
            <div class="alert alert-success">წარმატებით დამუშავდა <strong><?= (int) $report["ok"] ?></strong> სტრიქონი (კონტაქტი და/ან დილი).</div>
        <?php endif; ?>
        <?php if (!empty($report["warnings"])): ?>
            <div class="alert alert-warning">
                <?php foreach ($report["warnings"] as $w): ?>
                    <div><?= htmlspecialchars($w) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($report["errors"])): ?>
            <div class="alert alert-danger">
                <?php foreach ($report["errors"] as $e): ?>
                    <div><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="mb-5">
        <div class="mb-3">
            <label class="form-label">აირჩიეთ .xlsx</label>
            <input type="file" name="xlsx" class="form-control" accept=".xlsx" required>
        </div>
        <button type="submit" class="btn btn-primary">ატვირთვა და განახლება</button>
    </form>
</div>
</body>
</html>
