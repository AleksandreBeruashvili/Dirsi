<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

if (!CModule::IncludeModule('crm')) {
    die('CRM module not installed');
}

function getDealInfoByIDToolbar($dealId) {
    $res = CCrmDeal::GetList(["ID" => "ASC"], ["ID" => $dealId], []);
    return $res->Fetch();
}

$dealId = isset($_GET['DEAL_ID']) ? intval($_GET['DEAL_ID']) : 0;
$deal = getDealInfoByIDToolbar($dealId);
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>რეესტრში რეგისტრაცია</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Georgian:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --bg: #f4f6fb;
            --surface: #ffffff;
            --border: #e2e8f0;
            --border-focus: #6366f1;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-label: #374151;
            --accent: #6366f1;
            --accent-hover: #4f46e5;
            --accent-light: #eef2ff;
            --error: #ef4444;
            --error-light: #fef2f2;
            --success: #10b981;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 16px rgba(99,102,241,0.10);
            --radius: 12px;
            --radius-sm: 8px;
        }

        body {
            font-family: 'Noto Sans Georgian', sans-serif;
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 24px 16px 40px;
            color: var(--text-primary);
        }

        .popup-wrapper {
            width: 100%;
            max-width: 540px;
            animation: fadeSlideIn 0.35s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes fadeSlideIn {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ===== HEADER ===== */
        .popup-header {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            border-radius: var(--radius) var(--radius) 0 0;
            padding: 28px 32px 24px;
            position: relative;
            overflow: hidden;
        }

        .popup-header::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 160px; height: 160px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }

        .popup-header::after {
            content: '';
            position: absolute;
            bottom: -30px; left: 20px;
            width: 100px; height: 100px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }

        .header-icon {
            width: 44px;
            height: 44px;
            background: rgba(255,255,255,0.18);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
        }

        .header-icon svg {
            width: 22px;
            height: 22px;
            fill: white;
        }

        .popup-header h1 {
            font-size: 20px;
            font-weight: 700;
            color: white;
            letter-spacing: -0.3px;
            position: relative;
            z-index: 1;
        }

        .popup-header p {
            font-size: 13px;
            color: rgba(255,255,255,0.75);
            margin-top: 4px;
            position: relative;
            z-index: 1;
        }

        .deal-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 20px;
            padding: 3px 10px;
            font-size: 12px;
            color: white;
            margin-top: 10px;
            position: relative;
            z-index: 1;
        }

        /* ===== BODY ===== */
        .popup-body {
            background: var(--surface);
            border-radius: 0 0 var(--radius) var(--radius);
            padding: 32px;
            box-shadow: var(--shadow-md);
        }

        /* ===== FORM ===== */
        .form-section-title {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-secondary);
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-label);
            margin-bottom: 7px;
        }

        .required-star {
            color: var(--error);
            font-size: 14px;
            line-height: 1;
        }

        /* SELECT */
        .custom-select-wrapper {
            position: relative;
        }

        .custom-select-wrapper::after {
            content: '';
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 0;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-top: 5px solid var(--text-secondary);
            pointer-events: none;
            transition: transform 0.2s;
        }

        select, input[type="date"] {
            width: 100%;
            padding: 11px 16px;
            font-family: 'Noto Sans Georgian', sans-serif;
            font-size: 14px;
            color: var(--text-primary);
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
        }

        select:focus, input[type="date"]:focus {
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
            background: var(--accent-light);
        }

        select:hover:not(:focus), input[type="date"]:hover:not(:focus) {
            border-color: #a5b4fc;
        }

        select option[value=""] {
            color: var(--text-secondary);
        }

        /* DATE INPUT */
        .date-input-wrapper {
            position: relative;
        }

        .date-input-wrapper .date-icon {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: var(--text-secondary);
        }

        .date-input-wrapper .date-icon svg {
            width: 16px;
            height: 16px;
            fill: var(--text-secondary);
        }

        input[type="date"] {
            padding-right: 40px;
        }

        /* VALIDATION STATES */
        .form-group.has-error select,
        .form-group.has-error input {
            border-color: var(--error);
            background: var(--error-light);
        }

        .form-group.has-error select:focus,
        .form-group.has-error input:focus {
            box-shadow: 0 0 0 3px rgba(239,68,68,0.12);
        }

        .error-msg {
            display: none;
            font-size: 12px;
            color: var(--error);
            margin-top: 5px;
            align-items: center;
            gap: 4px;
        }

        .form-group.has-error .error-msg {
            display: flex;
        }

        /* DIVIDER */
        .form-divider {
            height: 1px;
            background: var(--border);
            margin: 24px 0;
        }

        /* ===== FOOTER ===== */
        .popup-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 22px;
            border-radius: 8px;
            font-family: 'Noto Sans Georgian', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            outline: none;
        }

        .btn-cancel {
            background: transparent;
            color: var(--text-secondary);
            border: 1.5px solid var(--border);
        }

        .btn-cancel:hover {
            background: var(--bg);
            border-color: #cbd5e1;
            color: var(--text-primary);
        }

        .btn-submit {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(99,102,241,0.35);
            position: relative;
            overflow: hidden;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(99,102,241,0.45);
        }

        .btn-submit:hover::before {
            left: 100%;
        }

        .btn-submit:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(99,102,241,0.3);
        }

        .btn svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
            flex-shrink: 0;
        }

        /* ===== HINT ===== */
        .field-hint {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 5px;
            line-height: 1.5;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 480px) {
            body { padding: 0; }
            .popup-wrapper { max-width: 100%; }
            .popup-header { border-radius: 0; padding: 20px; }
            .popup-body { border-radius: 0; padding: 20px; }
            .popup-footer { flex-direction: column-reverse; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<div class="popup-wrapper">

    <!-- HEADER -->
    <div class="popup-header">
        <div class="header-icon">
            <svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 7V3.5L18.5 9H13zm-3 8H7v-2h3v2zm7 0h-5v-2h5v2zm0-4H7v-2h10v2z"/></svg>
        </div>
        <h1>რეესტრში რეგისტრაცია</h1>
        <p>შეავსეთ ყველა სავალდებულო ველი</p>
        <?php if($dealId): ?>
            <div class="deal-badge">
                <svg viewBox="0 0 24 24" style="width:12px;height:12px;fill:white;flex-shrink:0"><path d="M20 6h-2.18c.07-.44.18-.88.18-1.36C18 2.05 15.96 0 13.36 0c-1.28 0-2.43.5-3.28 1.33L9 2.5 7.92 1.33C7.07.5 5.92 0 4.64 0 2.04 0 0 2.05 0 4.64c0 .48.11.92.18 1.36H0v2h20V6z"/></svg>
                Deal #<?php echo $dealId; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- BODY -->
    <div class="popup-body">
        <form id="registryForm" novalidate>
            <input type="hidden" name="DEAL_ID" value="<?php echo $dealId; ?>">

            <div class="form-section-title">ხელშეკრულების ინფორმაცია</div>

            <!-- ხელშეკრულების ტიპი -->
            <div class="form-group" id="group_contract_type">
                <label class="form-label" for="contract_type">
                    ხელშეკრულების ტიპი
                    <span class="required-star">*</span>
                </label>
                <div class="custom-select-wrapper">
                    <select id="contract_type" name="contract_type">
                        <option value="">— აირჩიეთ ტიპი —</option>
                        <option value="174">სტანდარტული წინარე ნასყიდობა</option>
                        <option value="175">არასტანდარტული წინარე ნასყიდობა</option>
                        <option value="221">ძირითადი ნასყიდობა</option>
                    </select>
                </div>
                <div class="error-msg">
                    <svg viewBox="0 0 24 24" style="width:13px;height:13px;fill:currentColor;flex-shrink:0"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    გთხოვთ აირჩიოთ ხელშეკრულების ტიპი
                </div>
            </div>

            <div class="form-divider"></div>

            <div class="form-section-title">რეგისტრაციის ინფორმაცია</div>

            <!-- რეესტრში რეგისტრაცია -->
            <div class="form-group" id="group_registry_status">
                <label class="form-label" for="registry_status">
                    რეესტრში რეგისტრაცია
                    <span class="required-star">*</span>
                </label>
                <div class="custom-select-wrapper">
                    <select id="registry_status" name="registry_status">
                        <option value="">— აირჩიეთ —</option>
                        <option value="1">დიახ</option>
                        <option value="0">არა</option>
                    </select>
                </div>
                <div class="error-msg">
                    <svg viewBox="0 0 24 24" style="width:13px;height:13px;fill:currentColor;flex-shrink:0"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    გთხოვთ მიუთითოთ სტატუსი
                </div>
            </div>

            <!-- რეესტრში რეგისტრაციის თარიღი -->
            <div class="form-group" id="group_registry_date">
                <label class="form-label" for="registry_date">
                    რეესტრში რეგისტრაციის თარიღი
                    <span class="required-star">*</span>
                </label>
                <div class="date-input-wrapper">
                    <input type="date" id="registry_date" name="registry_date">
                    <span class="date-icon">
                        <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg>
                    </span>
                </div>
                <div class="error-msg">
                    <svg viewBox="0 0 24 24" style="width:13px;height:13px;fill:currentColor;flex-shrink:0"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    გთხოვთ მიუთითოთ თარიღი
                </div>
            </div>

            <!-- FOOTER -->
            <div class="popup-footer">
                <button type="button" class="btn btn-cancel" onclick="closePopup()">
                    <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                    გაუქმება
                </button>
                <button type="submit" class="btn btn-submit">
                    <svg viewBox="0 0 24 24"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                    შენახვა
                </button>
            </div>

        </form>
    </div>
</div>

<script>
    deal=<?php echo json_encode($deal, JSON_UNESCAPED_UNICODE); ?>;

    if (deal["UF_CRM_1770204855111"]=="174"){
        document.getElementById("contract_type").value="174";
    } else if (deal["UF_CRM_1770204855111"]=="175"){
        document.getElementById("contract_type").value="175";
    } else if (deal["UF_CRM_1770204855111"]=="221") {
        document.getElementById("contract_type").value="221";
    } else {
        document.getElementById("contract_type").value="";
    }

    if (deal["UF_CRM_1771499394"] == "1"){
        document.getElementById("registry_status").value="1";
    } else if (deal["UF_CRM_1771499394"] == "0") {
        document.getElementById("registry_status").value="0";
    } else {
        document.getElementById("registry_status").value="";
    }

    if (deal["UF_CRM_1771937321"]) {
        document.getElementById("registry_date").value = deal["UF_CRM_1771937321"].substring(0, 10);
    } else {
        document.getElementById("registry_date").value = "";
    }

    (function() {
        'use strict';

        const form = document.getElementById('registryForm');

        // ===== ვალიდაციის კონფიგი =====
        const fields = [
            { id: 'contract_type',   groupId: 'group_contract_type',   type: 'select' },
            { id: 'registry_status', groupId: 'group_registry_status', type: 'select' },
            { id: 'registry_date',   groupId: 'group_registry_date',   type: 'date'   },
        ];

        function validateField(field) {
            const el = document.getElementById(field.id);
            const group = document.getElementById(field.groupId);
            const val = el.value.trim();
            const isValid = val !== '' && val !== null;

            group.classList.toggle('has-error', !isValid);
            return isValid;
        }

        function validateAll() {
            let allValid = true;
            fields.forEach(f => {
                if (!validateField(f)) allValid = false;
            });
            return allValid;
        }

        // live validation — შეცდომა გაქრეს მაშინვე
        fields.forEach(f => {
            const el = document.getElementById(f.id);
            el.addEventListener('change', () => validateField(f));
            el.addEventListener('input',  () => validateField(f));
        });

        // ===== SUBMIT =====
        // ===== SUBMIT =====
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            if (!validateAll()) return;
            let registry_status_string;
            if (document.getElementById('registry_status').value == 1) {
                registry_status_string = "დიახ"
            } else {
                registry_status_string = "არა"
            }

            const submitBtn = document.querySelector('.btn-submit');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span>შენახვა...</span>';

            const data = new FormData();
            data.append('DEAL_ID',         form.DEAL_ID.value);
            data.append('contract_type',   document.getElementById('contract_type').value);
            data.append('registry_status', document.getElementById('registry_status').value);
            data.append('registry_date',   document.getElementById('registry_date').value);
            data.append('registry_status_string', registry_status_string);
            data.append('full_price', deal["OPPORTUNITY"]);

            fetch('/rest/popupsservices/registryRegistration.php', {
                method: 'POST',
                body: data
            })
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        if (window.parent && window.parent.location) {
                            window.parent.location.reload();
                        }
                        closePopup();
                    } else {
                        alert('შეცდომა: ' + res.message);
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<span>შენახვა</span>';
                    }
                })
                .catch(() => {
                    alert('დაფიქსირდა შეცდომა! სცადეთ თავიდან.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<span>შენახვა</span>';
                });
        });

        // ===== დახურვა =====
        window.closePopup = function() {
            if (window.parent && window.parent.BX && window.parent.BX.SidePanel) {
                window.parent.BX.SidePanel.Instance.close();
            } else {
                window.close();
            }
        };

    })();
</script>

</body>
</html>