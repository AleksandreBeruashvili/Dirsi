<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

if (!CModule::IncludeModule('crm')) {
    die('CRM module not installed');
}

function getDealInfoByIDToolbar($dealId) {
    $res = CCrmDeal::GetList(["ID" => "ASC"], ["ID" => $dealId], []);
    return $res->Fetch();
}

$dealId = isset($_REQUEST['DEAL_ID']) ? intval($_REQUEST['DEAL_ID']) : 0;
$deal = getDealInfoByIDToolbar($dealId);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>ხელშეკრულების შემოწმება</title>
    <style>
        * { box-sizing: border-box; }

        .agreement-form {
            padding: 14px 20px 20px;
            max-width: 720px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            margin-bottom: 4px;
            font-weight: 500;
            color: #333;
            font-size: 13px;
        }

        input, textarea, select {
            width: 100%;
            padding: 7px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            background: #fff;
        }

        select {
            height: 34px;
            cursor: pointer;
            width: 200px;
        }

        input:focus, textarea:focus, select:focus {
            border-color: #0286ce;
            outline: none;
        }

        .file-group {
            margin-bottom: 16px;
        }

        .file-group span {
            display: block;
            margin-bottom: 4px;
            font-size: 13px;
            color: #80868e;
            font-weight: 500;
        }

        .file-group input[type="file"] {
            font-size: 12px;
            padding: 5px;
            border: 1px dashed #ccc;
            border-radius: 4px;
            background: #fafafa;
            cursor: pointer;
        }

        .file-group input[type="file"]:hover {
            border-color: #0286ce;
            background: #f0f8ff;
        }

        .button-group {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 16px;
        }

        .btn {
            padding: 9px 22px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
        }

        .btn-primary { background: #0286ce; color: #fff; }
        .btn-primary:hover { background: #026ba5; }
        .btn-secondary { background: #f5f5f5; color: #333; }
        .btn-secondary:hover { background: #e8e8e8; }

        .error { color: red; font-size: 12px; display: none; margin-top: 2px; }
        .error-input { border-color: red !important; }

        textarea {
            resize: vertical;
            min-height: 80px;
        }
    </style>
</head>
<body>
<div class="agreement-form">
    <form id="agreementForm" enctype="multipart/form-data">
        <input type="hidden" id="dealId" name="dealId" value="<?= $dealId ?>">


        <!-- Agreement File -->
        <div class="file-group">
            <span>ხელშეკრულება:</span>
            <input id="agreementFile" type="file" onchange="fileUpload('agreementFile')" />
            <input id="agreementFileText" type="hidden" />
        </div>


        <!-- Comment -->
        <div class="form-group">
            <label for="comment">კომენტარი</label>
            <textarea id="comment" name="comment" rows="4"></textarea>
        </div>

        <div class="button-group">
            <button type="button" class="btn btn-secondary" onclick="closePopup()">გაუქმება</button>
            <button type="submit" class="btn btn-primary">გაგზავნა</button>
        </div>
    </form>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
    let deal = <?php echo json_encode($deal, JSON_UNESCAPED_UNICODE); ?>;

    function closePopup() {
        if (window.BX && BX.SidePanel) {
            BX.SidePanel.Instance.close();
        } else {
            window.close();
        }
    }

    function fileUpload(fieldID) {
        let input = document.getElementById(fieldID);
        let fileIdInput = document.getElementById(`${fieldID}Text`);
        fileIdInput.value = "";

        if (input && input.files.length > 0) {
            let deal_id = <?php echo json_encode($dealId, JSON_UNESCAPED_UNICODE); ?>;
            let data = new FormData();
            data.append('file', input.files[0]);
            data.append('dealId', deal_id);

            fetch(`${location.origin}/rest/local/AXdocUploadFile.php`, {
                method: 'POST',
                body: data
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 200 && data.uploaded) {
                        fileIdInput.value = data.uploaded;
                        console.log("File uploaded:", data.uploaded);
                    } else {
                        alert("ფაილის ატვირთვა ვერ მოხერხდა!");
                    }
                })
                .catch(err => {
                    console.error("Upload error:", err);
                    alert("ფაილის ატვირთვა ვერ მოხერხდა!");
                });
        }
    }

    $(document).ready(function() {
        function showError(id, msg) {
            $("#" + id).addClass("error-input");
            $("#" + id + "-error").text(msg).show();
        }

        function clearError(id) {
            $("#" + id).removeClass("error-input");
            $("#" + id + "-error").hide();
        }

        function validateForm() {
            let valid = true;

           
            // if (!$("#agreementFile").val()) {
            //     showError("agreementFile", "გთხოვთ დაამატეთ ხელშეკრულება");
            //     valid = false;
            // } else {
            //     clearError("agreementFile");
            // }
            
           
            if (!$("#comment").val()) {
                showError("comment", "გთხოვთ შეიყვანეთ კომენტარი");
                valid = false;
            } else {
                clearError("comment");
            }

            return valid;
        }

        $("#agreementForm").on("submit", function(e) {
            e.preventDefault();
            if (!validateForm()) return;

            let agreementFileId = document.getElementById('agreementFileText').value;

            let formData = new FormData();
            formData.append("dealId", $("#dealId").val());
            formData.append("agreementFile", agreementFileId);
            formData.append("comment", $("#comment").val());

            $.ajax({
                url: "/rest/popupsservices/nonStandardAgreement.php",
                type: "POST",
                data: formData,
                dataType: "json",
                contentType: false,
                processData: false,
                success: function (response) {
                    console.log(response);
                    if (response.status === "success") {
                        alert("მოთხოვნა წარმატებით გაიგზავნა");
                        setTimeout(() => {
                            closePopup();
                            window.top.location.reload();
                        }, 500);
                    } else {
                        alert("შეცდომა: " + (response.message || "უცნობი შეცდომა"));
                    }
                },
                error: function (xhr, status, error) {
                    alert("Server error: " + error);
                }
            });
        });
    });
</script>
</body>
</html>
