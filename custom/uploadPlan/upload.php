<?php

ob_start();
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("upload plan");

use Bitrix24\SDK\Services\CRM\Deal\Service\Deal;
use Shuchkin\SimpleXLSX;

ini_set('error_reporting', E_ALL);
ini_set('display_errors', true);
ini_set('max_execution_time', 300);
ini_set('memory_limit', '1024M');

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
function printArr($arr) {
    echo "<pre>"; print_r($arr); echo "</pre>";
}


// DEAL-ის მოძებნის ფუნქცია პარამეტრებით
function findDealByParams($type, $number, $floor) {
    CModule::IncludeModule("crm");
    
    $filter = array(
        'UF_CRM_1766652554644' => $type,      // ფართის ტიპი
        'UF_CRM_1766560564150' => $number,    // ნომერი
        'UF_CRM_1766560580335' => $floor      // სართული
    );
    
    $arSelect = array('ID', 'TITLE', 'CONTACT_ID');
    
    $deals = CCrmDeal::GetListEx(
        array(),
        $filter,
        false,
        false,
        $arSelect
    );
    
    if ($deal = $deals->Fetch()) {
        return array(
            'ID' => $deal['ID'],
            'TITLE' => $deal['TITLE'],
            'CONTACT_ID' => $deal['CONTACT_ID']
        );
    }
    
    return null;
}


// $deal=findDealByParams("Flat", 26, 8);

// printArr($deal);

// კონტაქტის სახელი-გვარის მიღება
function getContactFullName($contactId) {
    if (!$contactId) return '';
    
    CModule::IncludeModule("crm");
    
    $contact = CCrmContact::GetByID($contactId);
    if ($contact) {
        $name = trim($contact['NAME']);
        $lastName = trim($contact['LAST_NAME']);
        return trim($name . ' ' . $lastName);
    }
    
    return '';
}

// AJAX მოთხოვნის დამუშავება
if ($_SERVER["REQUEST_METHOD"] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'process_batch') {
    
    $batchData = json_decode($_POST['batch_data'], true);
    $iblockId = intval($_POST['iblock_id']);
    
    $results = array(
        'success' => 0,
        'errors' => array(),
        'debug' => array(),
        'deal_not_found' => 0
    );
    
    foreach($batchData as $rowData) {
        $i = $rowData['index'];
        $row = $rowData['data'];
        
        // წაიკითხე პირველი 3 სვეტი
        $type = isset($row[0]) ? trim($row[0]) : '';      // A: ფართის ტიპი
        $number= isset($row[1]) ? trim($row[1]) : '';     // B: სართული
        $floor = isset($row[2]) ? trim($row[2]) : '';    // C: ბინის ნომერი
        
        $results['debug'][] = "სტრიქონი $i: ტიპი='$type', სართული='$floor', ნომერი='$number'";
        
        if(empty($type) || empty($number) || empty($floor)) {
            $results['debug'][] = "სტრიქონი $i გამოტოვებულია - ცარიელი პარამეტრები";
            continue;
        }
        
        // მოძებნე DEAL
        $deal = findDealByParams($type, $number, $floor);
        
        if(!$deal) {
            $results['errors'][] = "სტრიქონი $i: DEAL ვერ მოიძებნა (ტიპი: $type, ნომერი: $number, სართული: $floor)";
            $results['deal_not_found']++;
            continue;
        }
        
        $dealId = $deal['ID'];
        $results['debug'][] = "სტრიქონი $i: ნაპოვნია DEAL #$dealId";
        
        // მიიღე კონტაქტის სახელი
        $clientFullName = getContactFullName($deal['CONTACT_ID']);
        
        if(empty($clientFullName)) {
            $clientFullName = $deal['TITLE']; // თუ კონტაქტი არ არის, გამოიყენე DEAL-ის სახელი
        }
        
        $results['debug'][] = "სტრიქონი $i: კლიენტი='$clientFullName'";
        
        // დავამუშაოთ თანხა-თარიღის წყვილები (იწყება 3-ე სვეტიდან, ინდექსი 3)
        $pairsProcessed = 0;
        for($j = 3; $j < count($row); $j += 2) {
            if(!isset($row[$j]) || !isset($row[$j+1])) {
                continue;
            }
            
            $amountValue = $row[$j];      // პირველი არის თანხა
            $dateValue = $row[$j+1];      // მეორე არის თარიღი
            
            $results['debug'][] = "სტრიქონი $i, სვეტები " . getColumnLetter($j) . "-" . getColumnLetter($j+1) . ": თანხა='$amountValue', თარიღი='$dateValue'";
            
            // თანხა
            $amount = 0;
            if(is_numeric($amountValue)) {
                $amount = floatval($amountValue);
            } else {
                $cleanAmount = trim(str_replace([',', ' ', "\n", "\r", "\t"], '', $amountValue));
                $amount = floatval($cleanAmount);
            }
            
            if(!$amount || $amount == 0) {
                continue;
            }
            
            // გავასუფთაოთ თარიღი
            $dateValue = trim(str_replace([' ', "\n", "\r", "\t"], '', $dateValue));
            
            if(empty($dateValue)) {
                continue;
            }
            
            // თარიღის დამუშავება
            $date = convertDateFormat($dateValue);
            
            if(!$date) {
                $results['errors'][] = "სტრიქონი $i, სვეტი " . getColumnLetter($j) . ": ვერ დამუშავდა თარიღი '$dateValue'";
                continue;
            }
            
            $pairsProcessed++;
            
            $arForAdd = array(
                'IBLOCK_ID' => $iblockId,
                'NAME' => $dealId . " - " . $clientFullName,
                'ACTIVE' => 'Y',
            );
            
            $arProps = array();
            $arProps["TARIGI"] = $date;
            $arProps["TANXA"] = $amount . "|USD";
            $arProps["DEAL"] = $dealId;
            $arProps["FULL_NAME"] = $clientFullName;
            $arProps["BINIS_NOMERI"] = $number;
            $arProps["floor"] = $floor;
            $arProps["ZETIPI"] = $type;

            $el = new CIBlockElement;
            $arForAdd["PROPERTY_VALUES"] = $arProps;
            if ($PRODUCT_ID = $el->Add($arForAdd)) {
                $results['success']++;
                $results['debug'][] = "სტრიქონი $i: წარმატებით დაემატა DEAL #$dealId (თარიღი: $date, თანხა: $amount)";
            } else {
                $results['errors'][] = "სტრიქონი $i, DEAL #$dealId, თარიღი $date: Error: " . $el->LAST_ERROR;
            }
        }
        
        if($pairsProcessed == 0) {
            $results['debug'][] = "სტრიქონი $i: არც ერთი თარიღ-თანხის წყვილი არ დამუშავდა";
        }
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

function convertDateFormat($dateValue) {
    if(empty($dateValue)) return false;
    
    // თუ უკვე DD/MM/YYYY ფორმატშია
    if(preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateValue)) {
        return $dateValue;
    }
    
    // თუ DD.MM.YYYY ფორმატშია
    if(preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateValue, $matches)) {
        return $matches[1] . '/' . $matches[2] . '/' . $matches[3];
    }
    
    // თუ YYYY-MM-DD ფორმატშია
    if(preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $dateValue, $matches)) {
        return $matches[3] . '/' . $matches[2] . '/' . $matches[1];
    }
    
    // თუ Excel date serial number-ია
    if(is_numeric($dateValue) && $dateValue > 0) {
        $unixTimestamp = ($dateValue - 25569) * 86400;
        if($unixTimestamp > 0) {
            return date('d/m/Y', $unixTimestamp);
        }
    }
    
    // სცადოთ strtotime-ით
    $timestamp = strtotime($dateValue);
    if($timestamp !== false && $timestamp > 0) {
        return date('d/m/Y', $timestamp);
    }
    
    return false;
}

function getColumnLetter($columnNumber) {
    $letter = '';
    while ($columnNumber >= 0) {
        $letter = chr($columnNumber % 26 + 65) . $letter;
        $columnNumber = floor($columnNumber / 26) - 1;
    }
    return $letter;
}

// ფაილის ატვირთვა და მონაცემების წაკითხვა
$xlsxData = null;
$uploadMessage = '';

if ($_SERVER["REQUEST_METHOD"] == 'POST' && isset($_FILES["image"])) {
    $file = $_FILES["image"];
    
    if (!is_dir('xlsxFiles')) {
        mkdir('xlsxFiles');
    }
    
    if ($file && strlen($file["tmp_name"])) {
        $timestamp = date("Ymdhisa");
        $filePath = 'xlsxFiles/' . $timestamp . '/' . $file["name"];
        mkdir(dirname($filePath), 0777, true);
        move_uploaded_file($file['tmp_name'], $filePath);
        
        if ($xlsx = SimpleXLSX::parse($filePath)) {
            $xlsxData = $xlsx->rows();
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
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>განვადების ატვირთვა (ახალი)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-0evHe/X+R7YkIZDRvuzKMRqM+OrBnVFBL6DOitfPri4tjfHxaWutUpFmBp4vmVor" crossorigin="anonymous">
    <style>
        .container {
            max-width: 900px;
            margin-top: 50px;
        }
        .info-box {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .info-box h5 {
            color: #495057;
        }
        .info-box ul {
            margin-bottom: 0;
        }
        .list-selection-box {
            background-color: #e7f3ff;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #b3d9ff;
        }
        .list-selection-box h5 {
            color: #0066cc;
            margin-bottom: 15px;
        }
        .progress-container {
            display: none;
            margin-top: 20px;
        }
        .progress {
            height: 30px;
        }
        .progress-bar {
            font-size: 14px;
            line-height: 30px;
        }
        #results {
            margin-top: 20px;
        }
        .form-select {
            font-size: 16px;
            padding: 10px;
        }
        .debug-info {
            max-height: 300px;
            overflow-y: auto;
            font-size: 12px;
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .warning-box {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>ექსელის ატვირთვა</h1>
        </div>

        <div class="warning-box">
            <p class="mb-0">DEAL-ები იძებნება პარამეტრებით:</p>
            <ul class="mb-0">
                <li><strong>ფართის ტიპი</strong> (A სვეტი)</li>
                <li><strong>ბინის ნომერი</strong> (B სვეტი)</li>
                <li><strong>სართული</strong> (C სვეტი)</li>
            </ul>
        </div>

        <div class="list-selection-box">
            <h5>აირჩიეთ რომელ ლისტში გსურთ ატვირთვა</h5>
            <select class="form-select" id="iblockSelect" required>
                <option value="">-- აირჩიეთ სია --</option>
                <option value="20">განვადება (list id 20)</option>
            </select>
        </div>

        <div class="info-box">
            <h5>Excel ფაილის სტრუქტურა:</h5>
            <ul>
                <li><strong>A სვეტი:</strong> ფართის ტიპი (მაგ: Flat)</li>
                <li><strong>B სვეტი:</strong> სართული (მაგ: 26)</li>
                <li><strong>C სვეტი:</strong> ბინის ნომერი (მაგ: 8)</li>
                <li><strong>D სვეტიდან:</strong> თანხა-თარიღის წყვილები</li>
                <ul>
                    <li>D:  თანხა, E:  თარიღი</li>
                   
                </ul>
            </ul>
        </div>

        <form method="post" enctype="multipart/form-data" id="uploadForm">
            <div class="mb-3">
                <label class="form-label">აირჩიეთ Excel ფაილი (.xlsx)</label>
                <input type="file" name="image" class="form-control" accept=".xlsx" required>
            </div>
            <button type="submit" class="btn btn-primary" id="uploadBtn">
                <i class="bi bi-upload"></i> ატვირთვა და დამუშავება
            </button>
        </form>

        <div class="progress-container" id="progressContainer">
            <div class="alert alert-info">
                <strong>მიმდინარეობს დამუშავება...</strong>
                <p class="mb-0">გთხოვთ დაელოდოთ და არ დახუროთ ეს გვერდი.</p>
            </div>
            <div class="progress">
                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" 
                     style="width: 0%" id="progressBar">0%</div>
            </div>
            <div class="mt-2 text-center" id="progressText">
                <small class="text-muted">
                    დამუშავებულია: <span id="processedCount">0</span> / <span id="totalCount">0</span><br>
                    ვერ მოიძებნა: <span id="notFoundCount">0</span>
                </small>
            </div>
        </div>

        <div id="results"></div>
    </div>

    <script>
        const xlsxData = <?php echo $xlsxData ? json_encode($xlsxData) : 'null'; ?>;
        const uploadMessage = '<?php echo $uploadMessage; ?>';

        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const iblockSelect = document.getElementById('iblockSelect');
            if (!iblockSelect.value) {
                showError('გთხოვთ აირჩიოთ სია, სადაც გსურთ მონაცემების ატვირთვა!');
                iblockSelect.focus();
                return;
            }
            
            const formData = new FormData(this);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const scriptContent = doc.querySelector('script').textContent;
                const dataMatch = scriptContent.match(/const xlsxData = (.+?);/);
                
                if (dataMatch && dataMatch[1] !== 'null') {
                    const data = JSON.parse(dataMatch[1]);
                    console.log('Excel მონაცემები:', data);
                    startBatchProcessing(data);
                } else {
                    showError('ფაილის წაკითხვა ვერ მოხერხდა');
                }
            })
            .catch(error => {
                showError('შეცდომა: ' + error.message);
            });
        });

        if (xlsxData && uploadMessage === 'success') {
            console.log('Excel მონაცემები ავტომატურად:', xlsxData);
            const iblockSelect = document.getElementById('iblockSelect');
            if (iblockSelect.value) {
                startBatchProcessing(xlsxData);
            } else {
                showError('გთხოვთ აირჩიოთ სია, სადაც გსურთ მონაცემების ატვირთვა!');
            }
        } else if (uploadMessage && uploadMessage.startsWith('error:')) {
            showError(uploadMessage);
        }

        async function startBatchProcessing(data) {
            if (!data || data.length < 2) {
                showError('ფაილი ცარიელია ან არასწორი ფორმატისაა');
                return;
            }

            const iblockId = document.getElementById('iblockSelect').value;
            if (!iblockId) {
                showError('გთხოვთ აირჩიოთ სია!');
                return;
            }

            document.getElementById('uploadBtn').disabled = true;
            document.getElementById('progressContainer').style.display = 'block';
            
            const rows = data.slice(1); // გამოვტოვოთ header
            const BATCH_SIZE = 10; // გაზრდილი batch size რადგან DEAL search-ი ხდება
            const totalBatches = Math.ceil(rows.length / BATCH_SIZE);
            
            document.getElementById('totalCount').textContent = rows.length;
            
            let totalSuccess = 0;
            let allErrors = [];
            let allDebug = [];
            let processedRows = 0;
            let totalNotFound = 0;

            const selectedListName = document.getElementById('iblockSelect').options[document.getElementById('iblockSelect').selectedIndex].text;
            console.log('ატვირთვა ხდება სიაში: ' + selectedListName);

            for (let i = 0; i < totalBatches; i++) {
                const start = i * BATCH_SIZE;
                const end = Math.min(start + BATCH_SIZE, rows.length);
                const batch = rows.slice(start, end);
                
                const batchData = batch.map((row, idx) => ({
                    index: start + idx + 2, // +2 რადგან header-ია და Excel 1-დან იწყება
                    data: row
                }));
                
                try {
                    const result = await processBatch(batchData, iblockId);
                    totalSuccess += result.success;
                    allErrors = allErrors.concat(result.errors);
                    if(result.debug) {
                        allDebug = allDebug.concat(result.debug);
                    }
                    if(result.deal_not_found) {
                        totalNotFound += result.deal_not_found;
                    }
                    processedRows += batch.length;
                    
                    const percent = Math.round((processedRows / rows.length) * 100);
                    document.getElementById('progressBar').style.width = percent + '%';
                    document.getElementById('progressBar').textContent = percent + '%';
                    document.getElementById('processedCount').textContent = processedRows;
                    document.getElementById('notFoundCount').textContent = totalNotFound;
                    
                } catch (error) {
                    allErrors.push('Batch ' + (i + 1) + ' შეცდომა: ' + error.message);
                }
                
                await new Promise(resolve => setTimeout(resolve, 500)); // გაზრდილი delay
            }
            
            showResults(totalSuccess, allErrors, allDebug, selectedListName, totalNotFound);
            document.getElementById('uploadBtn').disabled = false;
        }

        function processBatch(batchData, iblockId) {
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('action', 'process_batch');
                formData.append('batch_data', JSON.stringify(batchData));
                formData.append('iblock_id', iblockId);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Batch პასუხი:', data);
                    resolve(data);
                })
                .catch(error => reject(error));
            });
        }

        function showResults(successCount, errors, debugInfo, listName = '', notFoundCount = 0) {
            let html = '';
            
            if (successCount > 0) {
                html += `<div class="alert alert-success">
                    <strong>✓ წარმატება!</strong><br>
                    წარმატებით დაემატა ${successCount} ჩანაწერი${listName ? ' სიაში: <strong>' + listName + '</strong>' : ''}
                </div>`;
            }
            
            if (notFoundCount > 0) {
                html += `<div class="alert alert-warning">
                    <strong>⚠ ყურადღება!</strong><br>
                    ${notFoundCount} DEAL ვერ მოიძებნა მითითებული პარამეტრებით
                </div>`;
            }
            
            if (successCount === 0 && notFoundCount === 0) {
                html += `<div class="alert alert-warning">
                    <strong>⚠ ყურადღება!</strong><br>
                    არც ერთი ჩანაწერი არ დაემატა. გთხოვთ ნახოთ შეცდომები ქვემოთ.
                </div>`;
            }
            
            if (errors.length > 0) {
                html += `<div class="alert alert-danger">
                    <strong>⚠ შეცდომები:</strong><br>`;
                errors.forEach(error => {
                    html += `<div>• ${error}</div>`;
                });
                html += '</div>';
            }
            
            document.getElementById('results').innerHTML = html;
        }

        function showError(message) {
            document.getElementById('results').innerHTML = 
                `<div class="alert alert-danger">${message}</div>`;
        }
    </script>
</body>
</html>