<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("პროდუქტების მოდული");

function getCIBlockElementsByFilter($arFilter = array(), $sort = array()) {
    $arElements = array();
    $arSelect = array("ID", "IBLOCK_ID", "NAME", "DATE_ACTIVE_FROM", "PROPERTY_*");
    $res = CIBlockElement::GetList(array("PROPERTY_TARIGI" => "ASC"), $arFilter, false, Array("nPageSize" => 9999999), $arSelect);
    while ($ob = $res->GetNextElement()) {
        $arFilds = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = array();
        foreach ($arFilds as $key => $arFild) $arPushs[$key] = $arFild;
        foreach ($arProps as $key => $arProp) $arPushs[$key] = $arProp["VALUE"];
        $arPushs["PRICE"] = CPrice::GetBasePrice($arPushs["ID"])["PRICE"];
        array_push($arElements, $arPushs);
    }
    return $arElements;
}
function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}

$arFilter = array("IBLOCK_ID" => 14);
$products = getCIBlockElementsByFilter($arFilter);

function getUniqueValues($products, $field) {
    $values = array();
    foreach ($products as $p) {
        $val = trim($p[$field]);
        if ($val !== "" && $val !== null && !in_array($val, $values)) {
            $values[] = $val;
        }
    }
    sort($values);
    return $values;
}

$uniqueProjects  = getUniqueValues($products, "PROJECT");
$uniqueTypes     = getUniqueValues($products, "PRODUCT_TYPE");
$uniqueBlocks    = getUniqueValues($products, "KORPUSIS_NOMERI_XE3NX2");
$uniqueBuildings = getUniqueValues($products, "BUILDING");
$uniqueStatuses  = getUniqueValues($products, "STATUS");

$filtered   = $products;
$isFiltered = false;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["filter_submit"])) {
    $isFiltered = true;

    $fProject   = trim($_POST["f_project"]    ?? "");
    $fType      = trim($_POST["f_type"]       ?? "");
    $fBlock     = trim($_POST["f_block"]      ?? "");
    $fBuilding  = trim($_POST["f_building"]   ?? "");
    $fFloorFrom = trim($_POST["f_floor_from"] ?? "");
    $fFloorTo   = trim($_POST["f_floor_to"]   ?? "");
    $fStatus    = trim($_POST["f_status"]     ?? "");

    $filtered = array_filter($products, function ($p) use ($fProject, $fType, $fBlock, $fBuilding, $fFloorFrom, $fFloorTo, $fStatus) {
        if ($fProject  !== "" && $p["PROJECT"]                !== $fProject)  return false;
        if ($fType     !== "" && $p["PRODUCT_TYPE"]           !== $fType)     return false;
        if ($fBlock    !== "" && $p["KORPUSIS_NOMERI_XE3NX2"] !== $fBlock)    return false;
        if ($fBuilding !== "" && $p["BUILDING"]               !== $fBuilding) return false;
        if ($fStatus   !== "" && $p["STATUS"]                 !== $fStatus)   return false;
        if ($fFloorFrom !== "" && (int)$p["FLOOR"] < (int)$fFloorFrom)       return false;
        if ($fFloorTo   !== "" && (int)$p["FLOOR"] > (int)$fFloorTo)         return false;
        return true;
    });
    $filtered = array_values($filtered);
}
?>
<!DOCTYPE html>
<html lang="ka">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Arial, sans-serif; font-size: 13px; }

    /* ფილტრი */
    .filter-form { background: #f4f4f4; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
    .filter-row { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
    .filter-group { display: flex; flex-direction: column; gap: 4px; }
    .filter-group label { font-weight: bold; }
    .filter-group select,
    .filter-group input[type="number"] { padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; min-width: 160px; }
    .floor-range { display: flex; gap: 5px; align-items: center; }
    .floor-range input { min-width: 70px !important; }
    .btn-filter { background: #e67e22; color: #fff; border: none; padding: 9px 24px; font-size: 14px; border-radius: 4px; cursor: pointer; }
    .btn-filter:hover { background: #ca6f1e; }
    .required-star { color: red; margin-left: 3px; }

    /* ცხრილი */
    .count-line { font-weight: bold; margin: 20px 0 10px; font-size: 14px; }
    table { border-collapse: collapse; width: 100%; font-size: 12px; }
    th { background: #2c6fad; color: #fff; padding: 7px 5px; text-align: center; border: 1px solid #ccc; white-space: nowrap; }
    td { padding: 6px 5px; border: 1px solid #ddd; text-align: center; }
    tr:nth-child(even) td { background: #f0f5ff; }

    /* ჩანართები */
    .tabs-container { margin-top: 20px; margin-bottom: 10px; }
    .tab-buttons { display: flex; gap: 0; }
    .tab-btn {
        padding: 10px 28px; cursor: pointer; border: 1px solid #ccc;
        background: #e8e8e8; font-size: 13px; border-bottom: none;
        border-radius: 4px 4px 0 0; font-family: Arial, sans-serif;
        transition: background .15s;
    }
    .tab-btn.active { background: #2c6fad; color: #fff; border-color: #2c6fad; }
    .tab-content { border: 1px solid #ccc; padding: 20px; border-radius: 0 4px 4px 4px; background: #fafafa; display: none; }
    .tab-content.active { display: block; }
    .form-row { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
    .form-group { display: flex; flex-direction: column; gap: 4px; }
    .form-group label { font-weight: bold; font-size: 13px; }
    .form-group select,
    .form-group input[type="number"] { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; min-width: 180px; }

    /* ღილაკები */
    .btn-action { color: #fff; border: none; padding: 9px 22px; font-size: 14px; border-radius: 4px; cursor: pointer; }
    .btn-minus  { background: #c0392b; }
    .btn-minus:hover  { background: #96281b; }
    .btn-plus   { background: #27ae60; }
    .btn-plus:hover   { background: #1e8449; }
    .btn-update { background: #2c6fad; }
    .btn-update:hover { background: #1a4f85; }
    .btn-group  { display: flex; gap: 8px; align-items: flex-end; }

    .dynamic-field { display: none; }
    .dynamic-field.visible { display: flex; flex-direction: column; gap: 4px; }

    .loading-msg { display: none; margin-top: 12px; padding: 10px 16px; background: #fff8e1; border: 1px solid #f0c040; border-radius: 4px; font-size: 13px; color: #7a5800; }
    .success-msg { display: none; margin-top: 12px; padding: 10px 16px; background: #e8f5e9; border: 1px solid #66bb6a; border-radius: 4px; font-size: 13px; color: #2e7d32; }
    .error-msg   { display: none; margin-top: 12px; padding: 10px 16px; background: #ffebee; border: 1px solid #ef9a9a; border-radius: 4px; font-size: 13px; color: #b71c1c; }

    /* Loading სპინერი */
    .spinner {
        display: inline-block; width: 16px; height: 16px;
        border: 2px solid #f0c040; border-top: 2px solid #7a5800;
        border-radius: 50%; animation: spin 0.8s linear infinite;
        margin-right: 8px; vertical-align: middle;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

</style>
</head>
<body>

<!-- ფილტრის ფორმა -->
<form method="POST" class="filter-form">
    <input type="hidden" name="filter_submit" value="1">
    <div class="filter-row">

        <div class="filter-group">
            <label>პროექტი <span class="required-star">*</span></label>
            <select name="f_project">
                <option value="">-- ყველა --</option>
                <?php foreach ($uniqueProjects as $v): ?>
                    <option value="<?= htmlspecialchars($v) ?>" <?= (($_POST["f_project"] ?? "") === $v ? "selected" : "") ?>><?= htmlspecialchars($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label>უძრავი ქონების ტიპი <span class="required-star">*</span></label>
            <select name="f_type">
                <option value="">-- ყველა --</option>
                <?php foreach ($uniqueTypes as $v): ?>
                    <option value="<?= htmlspecialchars($v) ?>" <?= (($_POST["f_type"] ?? "") === $v ? "selected" : "") ?>><?= htmlspecialchars($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label>ბლოკი</label>
            <select name="f_block">
                <option value="">-- ყველა --</option>
                <?php foreach ($uniqueBlocks as $v): ?>
                    <option value="<?= htmlspecialchars($v) ?>" <?= (($_POST["f_block"] ?? "") === $v ? "selected" : "") ?>><?= htmlspecialchars($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label>კორპუსი</label>
            <select name="f_building">
                <option value="">-- ყველა --</option>
                <?php foreach ($uniqueBuildings as $v): ?>
                    <option value="<?= htmlspecialchars($v) ?>" <?= (($_POST["f_building"] ?? "") === $v ? "selected" : "") ?>><?= htmlspecialchars($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label>სართული (დან – მდე)</label>
            <div class="floor-range">
                <input type="number" name="f_floor_from" placeholder="დან" value="<?= htmlspecialchars($_POST["f_floor_from"] ?? "") ?>" min="0">
                <span>–</span>
                <input type="number" name="f_floor_to"   placeholder="მდე" value="<?= htmlspecialchars($_POST["f_floor_to"]   ?? "") ?>" min="0">
            </div>
        </div>

        <div class="filter-group">
            <label>სტატუსი <span class="required-star">*</span></label>
            <select name="f_status">
                <option value="">-- ყველა --</option>
                <?php foreach ($uniqueStatuses as $v): ?>
                    <option value="<?= htmlspecialchars($v) ?>" <?= (($_POST["f_status"] ?? "") === $v ? "selected" : "") ?>><?= htmlspecialchars($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group" style="justify-content: flex-end;">
            <button type="submit" class="btn-filter">ფილტრაცია</button>
        </div>

    </div>
</form>

<?php if ($isFiltered): ?>

    <!-- ჩანართები: ფილტრსა და ცხრილს შორის -->
    <div class="tabs-container">
        <div class="tab-buttons">
            <button class="tab-btn active" onclick="switchTab(this, 'price')">ფასის ცვლილება</button>
            <button class="tab-btn"        onclick="switchTab(this, 'status')">სტატუსის ცვლილება</button>
        </div>

        <!-- ფასის ცვლილება -->
        <div id="tab-price" class="tab-content active">
            <div class="form-row">

                <div class="form-group">
                    <label>1 კვ.მ. ფასის ცვლილების სახე</label>
                    <select id="price-area">
                        <option value="inner">შიდა ფართი</option>
                        <option value="balcony">აივანი</option>
                        <option value="terrace">ტერასა</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>ცვლილების ტიპი</label>
                    <select id="price-change-type" onchange="onPriceTypeChange()">
                        <option value="">-- აირჩიეთ --</option>
                        <option value="fixed">განსაზღვრული ფასი</option>
                        <option value="percent">პროცენტი</option>
                    </select>
                </div>

                <div class="form-group dynamic-field" id="field-fixed">
                    <label>ახალი 1 კვ.მ. ფასი ($)</label>
                    <input type="number" id="price-fixed-value" placeholder="მაგ: 1200" min="0" step="0.01">
                </div>

                <div class="form-group dynamic-field" id="field-percent">
                    <label>პროცენტი (%)</label>
                    <input type="number" id="price-percent-value" placeholder="მაგ: 5" min="0" step="0.01">
                </div>

                <div class="form-group" style="justify-content: flex-end;">
                    <div class="btn-group">
                        <button class="btn-action btn-plus"  onclick="submitPriceChange('increase')">მომატება</button>
                        <button class="btn-action btn-minus" onclick="submitPriceChange('decrease')">დაკლება</button>
                    </div>
                </div>

            </div>

            <div class="loading-msg" id="price-loading">
                <span class="spinner"></span> მიმდინარეობს მონაცემების დამუშავება...
            </div>
            <div class="loading-msg" id="status-loading">
                <span class="spinner"></span> მიმდინარეობს მონაცემების დამუშავება...
            </div>

            <div class="success-msg" id="price-success">✅ დასრულებულია მონაცემების დამუშავება</div>
            <div class="error-msg"   id="price-error">❌ შეცდომა მონაცემების დამუშავებისას</div>
        </div>

        <!-- სტატუსის ცვლილება -->
        <div id="tab-status" class="tab-content">
            <div class="form-row">

                <div class="form-group">
                    <label>სტატუსი</label>
                    <select id="status-value">
                        <option value="">-- აირჩიეთ --</option>
                        <option value="free">თავისუფალი</option>
                        <option value="sold">გაყიდული</option>
                        <option value="reserved">დაჯავშნილი</option>
                        <option value="nfs">NFS</option>
                    </select>
                </div>

                <div class="form-group" style="justify-content: flex-end;">
                    <button class="btn-action btn-update" onclick="submitStatusChange()">განახლება</button>
                </div>

            </div>
            <div class="loading-msg" id="status-loading">⏳ მიმდინარეობს მონაცემების დამუშავება...</div>
            <div class="success-msg" id="status-success">✅ დასრულებულია მონაცემების დამუშავება</div>
            <div class="error-msg"   id="status-error">❌ შეცდომა მონაცემების დამუშავებისას</div>
        </div>
    </div>

    <!-- ცხრილი -->
    <div class="count-line">რაოდენობა: <?= count($filtered) ?></div>

    <?php if (count($filtered) > 0): ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>ბლოკი</th>
                <th>კორპუსი</th>
                <th>სართ.</th>
                <th>უძ.ქ. №</th>
                <th>სტატუსი</th>
                <th>კვ.მ. ფასი<br>შიდა ფართი</th>
                <th>ჯამური ფასი<br>შიდა ფართი</th>
                <th>კვ.მ. ფასი<br>აივანი</th>
                <th>ჯამური ფასი<br>აივანი</th>
                <th>კვ.მ. ფასი<br>ტერასა</th>
                <th>ჯამური ფასი<br>ტერასა</th>
                <th>სრული<br>ფართი</th>
                <th>პროდუქტის<br>ჯამური ფასი</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filtered as $p):?>
            <tr>
                <td><?= htmlspecialchars($p["ID"]) ?></td>
                <td><?= htmlspecialchars($p["KORPUSIS_NOMERI_XE3NX2"]) ?></td>
                <td><?= htmlspecialchars($p["BUILDING"]) ?></td>
                <td><?= htmlspecialchars($p["FLOOR"]) ?></td>
                <td><?= htmlspecialchars($p["Number"]) ?></td>
                <td><?= htmlspecialchars($p["STATUS"]) ?></td>
                <td><?= htmlspecialchars($p["KVM_PRICE"]) ?></td>
                <td><?= htmlspecialchars($p["PRICE_TOTAL_INNER"]) ?></td>
                <td><?= htmlspecialchars($p["KVM_PRICE_BALCONY"]) ?></td>
                <td><?= htmlspecialchars($p["PRICE_TOTAL_BALCONY"]) ?></td>
                <td><?= htmlspecialchars($p["KVM_PRICE_TERRACE"]) ?></td>
                <td><?= htmlspecialchars($p["PRICE_TOTAL_TERRACE"]) ?></td>
                <td><?= htmlspecialchars($p["TOTAL_AREA"] ?? "") ?></td>
                <td><?= htmlspecialchars($p["PRICE"]) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p>ფილტრის შედეგად პროდუქტი ვერ მოიძებნა.</p>
    <?php endif; ?>

    <script>
    const filteredIds = <?= json_encode(array_column($filtered, "ID")) ?>;

    function switchTab(btn, tab) {
        document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove("active"));
        document.querySelectorAll(".tab-content").forEach(c => c.classList.remove("active"));
        btn.classList.add("active");
        document.getElementById("tab-" + tab).classList.add("active");
    }

    function onPriceTypeChange() {
        const type = document.getElementById("price-change-type").value;
        document.getElementById("field-fixed").classList.toggle("visible", type === "fixed");
        document.getElementById("field-percent").classList.toggle("visible", type === "percent");
    }

    function setMsg(prefix, state) {
        ["loading", "success", "error"].forEach(s =>
            document.getElementById(prefix + "-" + s).style.display = (s === state ? "block" : "none")
        );
    }

    async function post_fetch(url, data = {}) {
        const response = await fetch(url, {
            method: "POST", mode: "cors", cache: "no-cache",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json" },
            redirect: "follow", referrerPolicy: "no-referrer",
            body: JSON.stringify(data)
        });
        return response;
    }

    const filterInfo = <?= json_encode(
        implode(" | ", array_filter([
            !empty($_POST["f_project"])  ? "პროექტი: "  . $_POST["f_project"]  : "",
            !empty($_POST["f_type"])     ? "ტიპი: "     . $_POST["f_type"]     : "",
            !empty($_POST["f_block"])    ? "ბლოკი: "    . $_POST["f_block"]    : "",
            !empty($_POST["f_building"]) ? "კორპუსი: "  . $_POST["f_building"] : "",
            !empty($_POST["f_status"])   ? "სტატუსი: "  . $_POST["f_status"]   : "",
            !empty($_POST["f_floor_from"]) || !empty($_POST["f_floor_to"])
                ? "სართული: " . ($_POST["f_floor_from"] ?? "") . "-" . ($_POST["f_floor_to"] ?? "")
                : "",
        ]))
    ) ?>;

    async function submitPriceChange(direction) {
        const changeType = document.getElementById("price-change-type").value;
        if (!changeType) { alert("გთხოვთ აირჩიოთ ცვლილების ტიპი"); return; }

        const rawValue = changeType === "fixed"
            ? parseFloat(document.getElementById("price-fixed-value").value)
            : parseFloat(document.getElementById("price-percent-value").value);

        if (isNaN(rawValue) || rawValue <= 0) { alert("გთხოვთ შეიყვანოთ დადებითი მნიშვნელობა"); return; }

        setMsg("price", "loading");

        try {
            const res  = await post_fetch("/rest/local/api/product/price-change.php", {
                ids:         filteredIds,
                change_type: changeType,
                area:        document.getElementById("price-area").value,
                direction:   direction,
                value:       rawValue,
                filter_info: filterInfo
            });
            const data = await res.json();

            if (res.ok) {
                document.getElementById("price-success").innerText =
                    "✅ დასრულებულია — განახლდა " + data.updated + " პროდუქტი";
                setMsg("price", "success");
            } else {
                document.getElementById("price-error").innerText =
                    "❌ შეცდომა" + (data.failed_ids
                        ? ": " + data.failed_ids.map(f => "ID:" + f.id + " (" + f.error + ")").join(", ")
                        : "");
                setMsg("price", "error");
            }
        } catch (e) {
            document.getElementById("price-error").innerText = "❌ შეცდომა: " + e.message;
            setMsg("price", "error");
        }
    }

    async function submitStatusChange() {
        const status = document.getElementById("status-value").value;
        if (!status) { alert("გთხოვთ აირჩიოთ სტატუსი"); return; }

        setMsg("status", "loading");

        try {
            const res  = await post_fetch("/rest/local/api/product/status-change.php", {
                ids:         filteredIds,
                status:      status,
                filter_info: filterInfo
            });
            const data = await res.json();

            if (res.ok) {
                document.getElementById("status-success").innerText =
                    "✅ დასრულებულია — განახლდა " + data.updated + " პროდუქტი";
                setMsg("status", "success");
            } else {
                document.getElementById("status-error").innerText =
                    "❌ შეცდომა" + (data.error ? ": " + data.error : "");
                setMsg("status", "error");
            }
        } catch (e) {
            document.getElementById("status-error").innerText = "❌ შეცდომა: " + e.message;
            setMsg("status", "error");
        }
    }


    </script>

<?php endif; ?>

</body>
</html>

<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php"); ?>