<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
CModule::IncludeModule("iblock");

function printArr($arr)
{
    echo "<pre>";
    print_r($arr);
    echo "</pre>";
}

function getciblockelementsbyfilter($arFilter = array(), $limit = 9999999)
{
    $arElements = array();
    $arSelect = array("ID", "NAME", "IBLOCK_ID", "DETAIL_PICTURE", "PREVIEW_PICTURE");
    $res = CIBlockElement::GetList(array("ID" => "ASC"), $arFilter, false, array("nPageSize" => $limit), $arSelect);
    while ($ob = $res->GetNextElement()) {
        $arFields = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $arPushs = $arFields;
        foreach ($arProps as $key => $arProp) {
            $arPushs['PROPERTY_' . $key] = $arProp["VALUE"];
        }
        array_push($arElements, $arPushs);
    }
    return $arElements;
}

if (!empty($_POST)) {
    $fileType = $_POST["file_type"];
    $flatNumbers = $_POST["flat_numbers"];
    $ptoNumber = $_POST["pto_number"];
    $building = $_POST["building"];
    $floor = $_POST["floor"];
    $project = $_POST["project"];
    $block = $_POST["block"];
    
    // Note: Ensure these variable names match your form names
    $flat_type = $_POST["flat_type"]; 

    $file = $_FILES["file"];
    $flatNumbersArray = array_map('trim', explode(',', $flatNumbers));

    $baseFilter = array(
        "IBLOCK_ID" => 14,
        "SECTION_ID" => $project, // Changed from IBLOCK_SECTION_ID for better GetList compatibility
        "PROPERTY_60" => $flat_type,
        "PROPERTY_73" => $block,
        "PROPERTY_214" => $ptoNumber,
        "PROPERTY_213" => $building,
        "PROPERTY_61" => $floor
    );

    $successCount = 0;
    $errors = array();

    $filePath = $_SERVER["DOCUMENT_ROOT"] . "/upload/" . time() . "_" . $file["name"];

    if (move_uploaded_file($file["tmp_name"], $filePath)) {
        foreach ($flatNumbersArray as $flatNumber) {
            $filter = $baseFilter;
            $filter["PROPERTY_65"] = $flatNumber;

            $elements = getciblockelementsbyfilter($filter);

            if (!empty($elements)) {
                $fileArray = CFile::MakeFileArray($filePath);

                if ($fileArray && !isset($fileArray["error"])) {
                    foreach ($elements as $element) {
                        // Determine which property to update based on file_type
                        if ($fileType == "3d_render") {
                            // Update Property 104 (3D Render)
                            CIBlockElement::SetPropertyValuesEx(
                                $element["ID"], 
                                14, 
                                array("104" => $fileArray)
                            );
                            $successCount++;
                        } elseif ($fileType == "floor_plan") {
                            // Update Property 102 (Floor Planning)
                            CIBlockElement::SetPropertyValuesEx(
                                $element["ID"], 
                                14, 
                                array("102" => $fileArray)
                            );
                            $successCount++;
                        } elseif ($fileType == "flat_plan") {
                            // Update Property 101 (flat Planning)
                            CIBlockElement::SetPropertyValuesEx(
                                $element["ID"], 
                                14, 
                                array("101" => $fileArray)
                            );
                            $successCount++;
                        }else {
                            // Fallback: If neither is selected, update the standard PREVIEW_PICTURE
                            $el = new CIBlockElement;
                            $updateResult = $el->Update($element["ID"], array("PREVIEW_PICTURE" => $fileArray));
                            
                            if ($updateResult) {
                                $successCount++;
                            } else {
                                $errors[] = "Error updating ID " . $element["ID"] . ": " . $el->LAST_ERROR;
                            }
                        }
                    }
                }
            } else {
                $errors[] = "No elements found for flat: " . $flatNumber;
            }
        }
        @unlink($filePath);
    } else {
        $errors[] = "Failed to move uploaded file.";
    }

    // Output results
    if ($successCount > 0) {
        echo "<div class='alert alert-success'>Files uploaded successfully to $successCount items!</div>";
    }
    if (!empty($errors)) {
        echo "<div class='alert alert-danger'><strong>Errors:</strong><br>" . implode("<br>", $errors) . "</div>";
    }
}
?>

<!-- HTML form with Building and PTO fields -->

<html>

<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <h1 class="mb-4">File Upload</h1>

        <form method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="file_type" class="form-label">File Type</label>
                <select name="file_type" id="file_type" class="form-select" required>
                    <option value="">Select file type</option>
                    <option value="3d_render">3D Render</option>
                    <option value="floor_plan">Floor Planning</option>
                    <option value="flat_plan">Flat Planning</option>

                </select>
            </div>

            <div class="mb-3">
                <label for="project" class="form-label">Project</label>
                <select name="project" id="project" class="form-select" required>
                    <option value="">Select Project</option>
                    <option value="33">Park Boulevard</option>
                  
                </select>
            </div>

            <div class="mb-3">
                <label for="block" class="form-label">Block</label>
                <select name="block" id="block" class="form-select">
                    <option value="">Select block</option>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                    <option value="D">D</option>
                </select>
            </div>


            <div class="mb-3">
                <label for="flat_type" class="form-label">Property type</label>
                <select name="flat_type" id="flat_type" class="form-select">
                    <option value="">Select type</option>
                    <option value="Commercial">Commercial</option>
                    <option value="Flat">Flat</option>
        
                </select>
            </div>

            

            <div class="mb-3">
                <label for="flat_numbers" class="form-label">Flat Numbers (comma separated)</label>
                <input type="text" class="form-control" id="flat_numbers" name="flat_numbers">
                <div class="form-text">Enter multiple flat numbers separated by commas (e.g., 101, 102, 103)</div>
            </div>

            <div class="mb-3">
                <label for="building" class="form-label">Building</label>
                <input type="text" class="form-control" id="building" name="building" required>
                <div class="form-text">Enter the building identifier</div>
            </div>

            <div class="mb-3">
                <label for="pto_number" class="form-label">PTO</label>
                <input type="text" class="form-control" id="pto_number" name="pto_number">
                <div class="form-text">Enter a single PTO number</div>
            </div>

            <div class="mb-3">
                <label for="floor" class="form-label">Floor</label>
                <input type="text" class="form-control" id="floor" name="floor">
                <div class="form-text">Enter the floor number (e.g., 1, 2, 3)</div>
            </div>

            <div class="mb-3">
                <label for="file" class="form-label">File</label>
                <input type="file" class="form-control" id="file" name="file" required>
            </div>

            <button type="submit" class="btn btn-primary">Upload</button>
        </form>
    </div>
</body>

</html>