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
            margin-top: 50px;
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
        <option value="https://crmasgroup.ge/crm/deal/reservation.php" >Reservation report</option>
        <option value="https://crmasgroup.ge/crm/deal/soldDeals.php" >Sold report</option>
        <option value="https://crmasgroup.ge/crm/deal/paymentReport.php" >Payment report</option>
        <option value="https://crmasgroup.ge/custom/reports/cashflowReport.php" >Cashflow report</option>
        <!-- <option value="https://crm.homer.ge/crm/deal/sale-plan.php" id="plan">Plan-fact report</option> -->
        <!-- <option value="https://crm.homer.ge/custom/reports/futurecashflow.php" >Cashflow report</option> -->
        <!-- <option value="https://crm.homer.ge/crm/deal/statistic-inprogress.php" id="callcenter">Call center report</option> -->


    </select>
   

    <!-- Sandbox attribute disables redirects -->
    <iframe 
    id="reportIframe" 
    title="Report Viewer" 
    sandbox="allow-scripts allow-same-origin allow-downloads allow-popups allow-forms">
</iframe>

</body>
</html>

<script>



// function selectLanguage(language, button) {
//         document.querySelectorAll('.flag-button').forEach(btn => btn.classList.remove('selected'));
//         button.classList.add('selected');

//         console.log(language);
//         if(language =="ge"){
//             document.getElementById("report").innerText="რეპორტის ტიპი:";
//             document.getElementById("choose").innerText="არჩევა";
//             document.getElementById("reservation").innerText="რეზერვაციების რეპორტი";
//             document.getElementById("sales").innerText="გაყიდვების რეპორტი";
//             document.getElementById("prod").innerText="პროდუქტების რეპორტი";
//             document.getElementById("daily").innerText="ყოველდღიური რეპორტი";
//             document.getElementById("payment").innerText="დავალიანების რეპორტი";
//             // document.getElementById("plan").innerText="გეგმა-ფაქტის რეპორტი";
//             document.getElementById("cashflow").innerText="ფულადი სახსრების რეპორტი";
//             // document.getElementById("callcenter").innerText="ქოლ-ცენტრის რეპორტი";

          


            
            
            
//         }else  if(language =="eng"){
//             document.getElementById("report").innerText=" Report type:";
//             document.getElementById("choose").innerText="Choose";
//             document.getElementById("reservation").innerText="Reservation report";
//             document.getElementById("sales").innerText="Sales report";
//             document.getElementById("prod").innerText="Product report";
//             document.getElementById("daily").innerText="Daily report";
//             document.getElementById("payment").innerText="Debt report";
//             // document.getElementById("plan").innerText="Plan-fact report";
//             document.getElementById("cashflow").innerText="Cashflow report";

//             // document.getElementById("callcenter").innerText="Call center report";


        

//         }
        
//     }
    </script>