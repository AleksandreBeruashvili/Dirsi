<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CJSCore::Init(array("jquery"));

$APPLICATION->SetTitle("ფინანსური სტატისტიკები");

function printArr($arr)
{
    echo "<pre>";
    print_r($arr);
    echo "</pre>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
        }
        iframe {
            width: 100%;
            height: 600px;
            border: 1px solid #ccc;
            margin-top: 20px;
            display: none; /* Initially hidden */
        }
        select {
            padding: 10px;
            font-size: 16px;
        }
 
    </style>
    <script>
        function updateIframe() {
            const iframe = document.getElementById('reportIframe');
            const selectedValue = document.getElementById('reportSelector').value;

            if (selectedValue) {
                iframe.src = selectedValue;
                iframe.style.display = 'block'; // Show the iframe
            } else {
                iframe.style.display = 'none'; // Hide the iframe if no selection is made
            }
        }
    </script>
</head>
<body>
    <label for="reportSelector" id="report">Report type:</label>
    <select id="reportSelector" onchange="updateIframe()">
  
        <option value="" id="choose">Choose</option>
        <option value="https://crmasgroup.ge/crm/deal/udzraviQonebisReport.php">Status Report</option>
        <option value="https://crmasgroup.ge/crm/deal/soldReport.php">Sold Reports</option>
        <option value="https://crmasgroup.ge/crm/deal/davalianebisReport.php">Debt Report</option>
        <option value="https://crmasgroup.ge/crm/deal/leadsReport.php">Leads Report</option>
        <option value="https://crmasgroup.ge/crm/deal/qualitiesBySourcesReport.php">Sources Report</option>
        <option value="https://crmasgroup.ge/crm/deal/cashFlow.php">Cashflow</option>

        <!-- <option value="https://crm.homer.ge/crm/deal/statistic-inprogress.php" id="callcenter">Call center report</option> -->


    </select>


    <!-- Sandbox attribute disables redirects -->
    <iframe 
        id="reportIframe" 
        title="Report Viewer" 
        sandbox="allow-scripts allow-same-origin allow-forms allow-downloads allow-popups">
    </iframe>

</body>
</html>

<script>




    
    </script>
