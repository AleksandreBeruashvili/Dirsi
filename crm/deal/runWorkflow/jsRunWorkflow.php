<?php
ob_start();

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle(" ");
CJSCore::Init(array("jquery"));




function getDealsByFilter($arFilter, $arSelect = array(), $arSort = array("ID"=>"DESC")) {
    $arSelect=array("ID");
    $arDeals = array();
    $res = CCrmDeal::GetList($arSort, $arFilter, $arSelect);
    while($arDeal = $res->Fetch()) array_push($arDeals, $arDeal);
    return (count($arDeals) > 0) ? $arDeals : array();
}


if($_GET["pbId"]){
    $pbId = $_GET["pbId"]; 
}else{
    $pbId = 115;
}



$arFilter= [
    "STAGE_ID" => "WON",
];

$deals = getDealsByFilter($arFilter);

$dealCount = count($deals);


ob_end_clean();
?>


<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Workflow Progress</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                margin: 0;
                padding: 40px 20px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .progress-container {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                padding: 40px;
                border-radius: 20px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                width: 100%;
                max-width: 600px;
                text-align: center;
            }

            .title {
                font-size: 28px;
                font-weight: 700;
                color: #2d3748;
                margin-bottom: 30px;
            }


            #timer {
                font-size: 32px;
                font-weight: 800;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                text-align: center;
                margin: 20px 0;
                padding: 15px;
                border-radius: 15px;
                background-color: rgba(102, 126, 234, 0.1);
                border: 2px solid rgba(102, 126, 234, 0.2);
                backdrop-filter: blur(5px);
                min-height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Courier New', monospace;
                letter-spacing: 2px;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
                transition: all 0.3s ease;
            }

            #timer.running {
                animation: pulse 2s infinite;
                border-color: rgba(79, 172, 254, 0.4);
                box-shadow: 0 4px 20px rgba(79, 172, 254, 0.3);
            }

            @keyframes pulse {
                0%, 100% { 
                    transform: scale(1);
                    opacity: 1;
                }
                50% { 
                    transform: scale(1.02);
                    opacity: 0.9;
                }
            }

            .progress-wrapper {
                position: relative;
                background: #e2e8f0;
                border-radius: 25px;
                height: 20px;
                margin: 20px 0;
                overflow: hidden;
                box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .progress-bar {
                height: 100%;
                background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
                width: 0%;
                border-radius: 25px;
                transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                position: relative;
                box-shadow: 0 2px 8px rgba(79, 172, 254, 0.3);
            }

            .progress-bar::after {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.3) 50%, transparent 100%);
                animation: shimmer 2s infinite;
            }

            @keyframes shimmer {
                0% { transform: translateX(-100%); }
                100% { transform: translateX(100%); }
            }

            .progress-text {
                display: flex;
                justify-content: space-between;
                margin-top: 15px;
                font-size: 18px;
                font-weight: 600;
            }

            .current-value {
                color: #4facfe;
            }

            .max-value {
                color: #718096;
            }

            .status {
                margin-top: 25px;
                padding: 15px;
                border-radius: 12px;
                font-weight: 500;
                font-size: 16px;
                transition: all 0.3s ease;
            }

            .status.running {
                background: linear-gradient(135deg, #e6fffa 0%, #b2f5ea 100%);
                color: #234e52;
                border: 2px solid #4fd1c7;
            }

            .status.completed {
                background: linear-gradient(135deg, #f0fff4 0%, #c6f6d5 100%);
                color: #22543d;
                border: 2px solid #48bb78;
            }

            .controls {
                margin-top: 30px;
                display: flex;
                gap: 15px;
                justify-content: center;
            }

            .btn {
                padding: 12px 30px;
                border: none;
                border-radius: 12px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .btn-primary {
                background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
                color: white;
                box-shadow: 0 4px 15px rgba(79, 172, 254, 0.4);
            }

            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(79, 172, 254, 0.6);
            }

            .btn-secondary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                box-shadow: 0 4px 15px rgba(118, 75, 162, 0.4);
            }

            .btn-secondary:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(118, 75, 162, 0.6);
            }

            .percentage {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                font-weight: 700;
                font-size: 14px;
                color: #2d3748;
                text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8);
            }
        </style>
    </head>
    <body>
        <div class="progress-container">
            <div class="title">Workflow ID: <span id="bpIdText"></span></div>
            <div id="timer"></div>
            <div class="progress-wrapper">
                <div class="progress-bar" id="progressBar"></div>
                <div class="percentage" id="percentage">0%</div>
            </div>
            
            <div class="progress-text">
                <span class="current-value" id="currentValue">0</span>
                <span class="max-value" id="maxValue">0</span>
            </div>
            
            <div class="status running" id="status">Ready to start...</div>
            
            <div class="controls">
                <button class="btn btn-primary" onclick="runWorkflowWithAPI()">Start Workflow</button>
            </div>
        </div>

    </body>
</html>

<script>



/// bp ID here //

const pbId = <?php echo json_encode($pbId); ?>;

// ======= //




deals = <?php echo json_encode($deals); ?>;
dealCount = <?php echo json_encode($dealCount); ?>;


let timerInterval;
let startTime;


document.getElementById('maxValue').textContent = dealCount;
document.getElementById('bpIdText').textContent = pbId;


function updateProgress(current, max) {
    const percentage = Math.round((current / max) * 100);
    const progressBar = document.getElementById('progressBar');
    const percentageEl = document.getElementById('percentage');
    const currentValueEl = document.getElementById('currentValue');
    const maxValueEl = document.getElementById('maxValue');
    
    progressBar.style.width = `${percentage}%`;
    percentageEl.textContent = `${percentage}%`;
    currentValueEl.textContent = current;
    maxValueEl.textContent = max;
}

function setStatus(message, isCompleted = false) {
    const statusEl = document.getElementById('status');
    statusEl.textContent = message;
    statusEl.className = isCompleted ? 'status completed' : 'status running';
}

function startTimer() {
    startTime = Date.now();
    const timerEl = document.getElementById('timer');
    timerEl.classList.add('running');
    
    timerInterval = setInterval(() => {
        const elapsed = Date.now() - startTime;
        timerEl.textContent = formatTime(elapsed);
    }, 100);
}

function stopTimer() {
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
    const timerEl = document.getElementById('timer');
    timerEl.classList.remove('running');
}


function formatTime(ms) {
    const totalSeconds = Math.floor(ms / 1000);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    let timeString = '';
    
    if (hours > 0) {
        timeString += `${hours}:`;
        timeString += `${minutes.toString().padStart(2, '0')}:`;
    } else if (minutes > 0) {
        timeString += `${minutes}:`;
    }
    
    timeString += seconds.toString().padStart(hours > 0 || minutes > 0 ? 2 : 1, '0') ;
    
    return timeString;
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function post_fetch(url, data) {
    await sleep(100); 
    return {
        json: () => Promise.resolve({success: true, dealId: data.deal?.ID})
    };
}

async function runWorkflowWithAPI() {
    const totalDeals = deals.length;
    updateProgress(0, totalDeals);
    setStatus(`Processing ${totalDeals} deals...`);
    startTimer();
    
    for (let i = 0; i < deals.length; i++) {
        try {

            const jsonToSend = {
                "pbId": pbId,
                "deal": deals[i],
            };
            
            const response = await post_fetch(`${location.origin}/crm/deal/runWorkflow/phpRunWorkflow.php`, jsonToSend);
            const data = await response.json();
            console.log('i:', i);
            console.log('Response:', data);

            // Update progress after each successful request
            updateProgress(i + 1, totalDeals);
            setStatus(`Processing deal ${i + 1} of ${totalDeals}...`);

            await sleep(10);
        } catch (error) {
            console.error(`Error uploading contact ${i}:`, error);
            setStatus(`Error on deal ${i + 1}: ${error.message}`);
        }
    }
    stopTimer();
    setStatus('Workflow completed successfully!', true);
}


async function post_fetch(url, data = {}) {
    console.log(data);
    const response = await fetch(url, {
        method: 'POST',
        mode: 'cors',
        cache: 'no-cache',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
        },
        redirect: 'follow',
        referrerPolicy: 'no-referrer',
        body: JSON.stringify(data)
    });
    return response;
}


</script>