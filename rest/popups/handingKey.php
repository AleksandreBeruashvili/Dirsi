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
    <title>გასაღების გადაცემა</title>
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

        .popup-body {
            background: var(--surface);
            border-radius: 0 0 var(--radius) var(--radius);
            padding: 32px;
            box-shadow: var(--shadow-md);
        }

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
        }

        /* FILE UPLOAD */
        .file-upload-area {
            border: 2px dashed var(--border);
            border-radius: var(--radius-sm);
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            position: relative;
        }

        .file-upload-area:hover {
            border-color: var(--border-focus);
            background: var(--accent-light);
        }

        .file-upload-area.has-file {
            border-color: var(--success);
            background: #f0fdf4;
        }

        .file-upload-area input[type="file"] {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            border: none;
            padding: 0;
        }

        .file-upload-icon svg {
            width: 32px;
            height: 32px;
            fill: var(--text-secondary);
            margin-bottom: 8px;
        }

        .file-upload-area.has-file .file-upload-icon svg {
            fill: var(--success);
        }

        .file-upload-text {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .file-upload-text span {
            color: var(--accent);
            font-weight: 600;
        }

        .file-name {
            font-size: 12px;
            color: var(--success);
            margin-top: 6px;
            font-weight: 600;
            display: none;
        }

        .file-upload-area.has-file .file-name { display: block; }
        .file-upload-area.has-file .file-upload-default { display: none; }

        .form-group.has-error .file-upload-area {
            border-color: var(--error);
            background: var(--error-light);
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

        .date-input-wrapper {
            position: relative;
        }

        .date-input-wrapper .date-icon {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
        }

        .date-input-wrapper .date-icon svg {
            width: 16px;
            height: 16px;
            fill: var(--text-secondary);
        }

        input[type="date"] {
            padding-right: 40px;
        }

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

        .form-divider {
            height: 1px;
            background: var(--border);
            margin: 24px 0;
        }

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

    <div class="popup-header">
        <div class="header-icon">
            <svg viewBox="0 0 24 24"><path d="M12.65 10C11.83 7.67 9.61 6 7 6c-3.31 0-6 2.69-6 6s2.69 6 6 6c2.61 0 4.83-1.67 5.65-4H17v4h4v-4h2v-4H12.65zM7 14c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg>
        </div>
        <h1>გასაღების გადაცემა</h1>
        <p>შეავსეთ ყველა სავალდებულო ველი</p>
        <?php if($dealId): ?>
            <div class="deal-badge">
                <svg viewBox="0 0 24 24" style="width:12px;height:12px;fill:white;flex-shrink:0"><path d="M20 6h-2.18c.07-.44.18-.88.18-1.36C18 2.05 15.96 0 13.36 0c-1.28 0-2.43.5-3.28 1.33L9 2.5 7.92 1.33C7.07.5 5.92 0 4.64 0 2.04 0 0 2.05 0 4.64c0 .48.11.92.18 1.36H0v2h20V6z"/></svg>
                Deal #<?php echo $dealId; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="popup-body">
        <form id="handingKeyForm" novalidate>
            <input type="hidden" name="DEAL_ID" value="<?php echo $dealId; ?>">

            <div class="form-section-title">გასაღების ინფორმაცია</div>

            <!-- გასაღები გადაცემულია -->
            <div class="form-group" id="group_key_handed">
                <label class="form-label" for="key_handed">
                    გასაღები გადაცემულია
                    <span class="required-star">*</span>
                </label>
                <div class="custom-select-wrapper">
                    <select id="key_handed" name="key_handed">
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

            <!-- გასაღების გადაცემის თარიღი -->
            <div class="form-group" id="group_key_date">
                <label class="form-label" for="key_date">
                    გასაღების გადაცემის თარიღი
                    <span class="required-star">*</span>
                </label>
                <div class="date-input-wrapper">
                    <input type="date" id="key_date" name="key_date">
                    <span class="date-icon">
                        <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg>
                    </span>
                </div>
                <div class="error-msg">
                    <svg viewBox="0 0 24 24" style="width:13px;height:13px;fill:currentColor;flex-shrink:0"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    გთხოვთ მიუთითოთ თარიღი
                </div>
            </div>

            <div class="form-section-title">მიღება-ჩაბარების აქტი</div>

            <!-- მიღება-ჩაბარების აქტი — file upload -->
            <div class="form-group" id="group_act_file">
                <label class="form-label">
                    მიღება-ჩაბარების აქტი
                    <span class="required-star">*</span>
                </label>
                <div class="file-upload-area" id="actFileArea">
                    <input type="file" id="act_file" name="act_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    <div class="file-upload-default">
                        <div class="file-upload-icon">
                            <svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11zM8 15h8v2H8zm0-4h8v2H8zm0-4h5v2H8z"/></svg>
                        </div>
                        <div class="file-upload-text">
                            ფაილის ასატვირთად <span>დააჭირეთ აქ</span>
                        </div>
                        <div style="font-size:11px;color:var(--text-secondary);margin-top:4px;">PDF, DOC, JPG, PNG</div>
                    </div>
                    <div class="file-upload-icon" style="display:none">
                        <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                    </div>
                    <div class="file-name" id="actFileName"></div>
                </div>
                <div class="error-msg">
                    <svg viewBox="0 0 24 24" style="width:13px;height:13px;fill:currentColor;flex-shrink:0"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    გთხოვთ ატვირთოთ ფაილი
                </div>
            </div>

            <!-- მიღება-ჩაბარების აქტის გაფორმების თარიღი -->
            <div class="form-group" id="group_act_date">
                <label class="form-label" for="act_date">
                    მიღება-ჩაბარების აქტის გაფორმების თარიღი
                    <span class="required-star">*</span>
                </label>
                <div class="date-input-wrapper">
                    <input type="date" id="act_date" name="act_date">
                    <span class="date-icon">
                        <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg>
                    </span>
                </div>
                <div class="error-msg">
                    <svg viewBox="0 0 24 24" style="width:13px;height:13px;fill:currentColor;flex-shrink:0"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    გთხოვთ მიუთითოთ თარიღი
                </div>
            </div>

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
    const deal = <?php echo json_encode($deal, JSON_UNESCAPED_UNICODE); ?>;

    // ველების ჩატვირთვა Deal-იდან (UF_CRM_ კოდები შემდეგ შეცვლის)
    // document.getElementById("key_handed").value  = deal["UF_CRM_KEY_HANDED"]  || "";
    // document.getElementById("key_date").value    = deal["UF_CRM_KEY_DATE"]    ? deal["UF_CRM_KEY_DATE"].substring(0, 10)    : "";
    // document.getElementById("act_date").value    = deal["UF_CRM_ACT_DATE"]    ? deal["UF_CRM_ACT_DATE"].substring(0, 10)    : "";

    (function() {
        'use strict';

        const form = document.getElementById('handingKeyForm');

        // file upload UI
        const actFile = document.getElementById('act_file');
        const actFileArea = document.getElementById('actFileArea');
        const actFileName = document.getElementById('actFileName');

        actFile.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                actFileArea.classList.add('has-file');
                actFileName.textContent = this.files[0].name;
                actFileArea.querySelector('.file-upload-icon:last-of-type').style.display = 'block';
                document.getElementById('group_act_file').classList.remove('has-error');
            }
        });

        const fields = [
            { id: 'key_handed', groupId: 'group_key_handed', type: 'select' },
            { id: 'key_date',   groupId: 'group_key_date',   type: 'date'   },
            { id: 'act_date',   groupId: 'group_act_date',   type: 'date'   },
        ];

        function validateField(field) {
            const el    = document.getElementById(field.id);
            const group = document.getElementById(field.groupId);
            const isValid = el.value.trim() !== '';
            group.classList.toggle('has-error', !isValid);
            return isValid;
        }

        function validateFile() {
            const group = document.getElementById('group_act_file');
            const isValid = actFile.files && actFile.files.length > 0;
            group.classList.toggle('has-error', !isValid);
            return isValid;
        }

        function validateAll() {
            let allValid = true;
            fields.forEach(f => { if (!validateField(f)) allValid = false; });
            if (!validateFile()) allValid = false;
            return allValid;
        }

        fields.forEach(f => {
            const el = document.getElementById(f.id);
            el.addEventListener('change', () => validateField(f));
            el.addEventListener('input',  () => validateField(f));
        });

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!validateAll()) return;

            const key_handed_string = document.getElementById('key_handed').value == 1 ? "დიახ" : "არა";

            const submitBtn = document.querySelector('.btn-submit');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span>შენახვა...</span>';

            const data = new FormData();
            data.append('DEAL_ID',           form.DEAL_ID.value);
            data.append('key_handed',        document.getElementById('key_handed').value);
            data.append('key_handed_string', key_handed_string);
            data.append('key_date',          document.getElementById('key_date').value);
            data.append('act_date',          document.getElementById('act_date').value);
            data.append('act_file',          actFile.files[0]);
            data.append('full_price', deal["OPPORTUNITY"]);

            fetch('/rest/popupsservices/handingKey.php', {
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