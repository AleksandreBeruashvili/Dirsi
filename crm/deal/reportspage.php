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

        .flag-button {
            width: 40px;
            height: 25px;
            border: 2px solid transparent;
            background-size: cover;
            background-position: center;
            cursor: pointer;
            margin: 3px;
            border-radius: 5px;
            transition: border-color 0.3s, transform 0.2s, box-shadow 0.3s;
        }

        .flag-button.selected {
            border-color: #007bff;
            transform: scale(1.2);
            box-shadow: 0 0 10px rgba(0, 123, 255, 0.5);
        }

        .georgia-flag {
            background-image: url('https://upload.wikimedia.org/wikipedia/commons/thumb/0/0f/Flag_of_Georgia.svg/1200px-Flag_of_Georgia.svg.png');
        }

        .uk-flag {
            background-image: url('https://upload.wikimedia.org/wikipedia/en/a/ae/Flag_of_the_United_Kingdom.svg');
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
        <option value="https://crmasgroup.ge/crm/deal/udzraviQonebisReport.php" id="product">Product Report</option>
        <option value="https://crmasgroup.ge/crm/deal/soldReport.php" id="sold">Sales Reports</option>
        <option value="https://crmasgroup.ge/crm/deal/davalianebisReport.php" id="debitors">Debt Report</option>
        <option value="https://crmasgroup.ge/crm/deal/leadsReport.php" id="leads">Leads Report</option>
        <option value="https://crmasgroup.ge/crm/deal/qualitiesBySourcesReport.php" id="sources">Sources Report</option>
        <option value="https://crmasgroup.ge/crm/deal/marketingReport.php" id="marketing">Marketing Report</option>
        <option value="https://crmasgroup.ge/crm/deal/cashFlow.php" id="cashflow">Cashflow</option>

        <!-- <option value="https://crm.homer.ge/crm/deal/statistic-inprogress.php" id="callcenter">Call center report</option> -->


    </select>

    <button id="georgiaBtn" class="flag-button georgia-flag " onclick="selectLanguage('ge', this)"></button>
    <button id="ukBtn" class="flag-button uk-flag selected" onclick="selectLanguage('eng', this)"></button>

    <!-- Sandbox attribute disables redirects -->
    <iframe 
        id="reportIframe" 
        title="Report Viewer" 
        sandbox="allow-scripts allow-same-origin allow-forms allow-downloads allow-popups">
    </iframe>

</body>
</html>

<script>
    function selectLanguage(language, button) {
        document.querySelectorAll('.flag-button').forEach(btn => btn.classList.remove('selected'));
        button.classList.add('selected');

        console.log(language);
        if(language =="ge"){
            document.getElementById("report").innerText="რეპორტის ტიპი:";
            document.getElementById("choose").innerText="არჩევა";
            document.getElementById("product").innerText="უძრავი ქონების რეპორტი";
            document.getElementById("sold").innerText="გაყიდვების რეპორტი";
            document.getElementById("debitors").innerText="დავალიანების რეპორტი";
            document.getElementById("leads").innerText="ლიდების რეპორტი";
            document.getElementById("sources").innerText="წყაროების რეპორტი";
            document.getElementById("marketing").innerText="მარკეტინგის რეპორტი";
            document.getElementById("cashflow").innerText="ქეშფლოუ რეპორტი";
            
        }else  if(language =="eng"){
            document.getElementById("report").innerText="Report type:";
            document.getElementById("choose").innerText="Choose";
            document.getElementById("product").innerText="Product Report";
            document.getElementById("sold").innerText="Sales Report";
            document.getElementById("debitors").innerText="Debt Report";
            document.getElementById("leads").innerText="Lead Report";
            document.getElementById("sources").innerText="Sources Report";
            document.getElementById("marketing").innerText="Marketing Report";
            document.getElementById("cashflow").innerText="Cashflow";

        }
        
    }    
</script>
