<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
session_write_close();

CJSCore::Init(["jquery"]);
$APPLICATION->SetTitle("Deals by Responsible & Source");

/**
 * Selected filters
 */
$selectedResponsibleId = !empty($_GET['responsible_id'])
    ? (int)$_GET['responsible_id']
    : null;

$selectedSourceId = !empty($_GET['source_id'])
    ? $_GET['source_id']
    : null;

$selectedStageId = !empty($_GET['stage_id'])
    ? $_GET['stage_id']
    : null;

/**
 * CRM filter
 */
$arFilter = [];

if ($selectedStageId) {
    $arFilter['STAGE_ID'] = $selectedStageId;
}


if ($selectedResponsibleId) {
    $arFilter["ASSIGNED_BY_ID"] = $selectedResponsibleId;
}

if ($selectedSourceId) {
    $arFilter["SOURCE_ID"] = $selectedSourceId;
}

/**
 * Load deals (ONCE)
 */
$res = CCrmDeal::GetListEx(
    ['ID' => 'DESC'],
    $arFilter,
    false,
    ['nPageSize' => 200],
    ['ID', 'ASSIGNED_BY_ID', 'SOURCE_ID']
);

$deals = [];
$userIds = [];
$sourceIds = [];

while ($row = $res->Fetch()) {
    $deals[] = $row;

    if (!empty($row['ASSIGNED_BY_ID'])) {
        $userIds[$row['ASSIGNED_BY_ID']] = true;
    }

    if (!empty($row['SOURCE_ID'])) {
        $sourceIds[$row['SOURCE_ID']] = true;
    }
}

/**
 * Load users
 */
$users = [];

if (!empty($userIds)) {
    $rsUsers = CUser::GetList(
        ($by = "id"),
        ($order = "asc"),
        ['ID' => implode('|', array_keys($userIds))],
        ['FIELDS' => ['ID', 'NAME', 'LAST_NAME']]
    );

    while ($u = $rsUsers->Fetch()) {
        $users[$u['ID']] = trim($u['NAME'].' '.$u['LAST_NAME']);
    }
}

/**
 * Load sources
 */
$allSources = \CCrmStatus::GetStatusList('SOURCE');
$sources = [];

foreach ($sourceIds as $code => $_) {
    if (isset($allSources[$code])) {
        $sources[$code] = $allSources[$code];
    }
}

// Load deal stages (for default category = 0)
$allStages = \CCrmStatus::GetStatusList('DEAL_STAGE');

?>

    <!-- FILTERS -->
    <form method="get" style="margin-bottom:15px; display:flex; gap:20px; align-items:center;">

        <!-- RESPONSIBLE -->
        <label>
            Responsible:
            <select name="responsible_id" onchange="this.form.submit()">
                <option value="">All</option>
                <?php foreach ($users as $id => $name): ?>
                    <option value="<?= $id ?>"
                        <?= ($selectedResponsibleId === $id) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <!-- SOURCE -->
        <label>
            Source:
            <select name="source_id" onchange="this.form.submit()">
                <option value="">All</option>
                <?php foreach ($sources as $code => $name): ?>
                    <option value="<?= htmlspecialchars($code) ?>"
                        <?= ($selectedSourceId === $code) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <!-- STAGE -->
        <label>
            Stage:
            <select name="stage_id" onchange="this.form.submit()">
                <option value="">All</option>
                <?php foreach ($allStages as $stageId => $stageName): ?>
                    <option value="<?= htmlspecialchars($stageId) ?>"
                        <?= ($selectedStageId === $stageId) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($stageName) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

    </form>


    <!-- DEALS TABLE -->
    <table class="deal-table">
        <thead>
        <tr>
            <th>Deal ID</th>
            <th>Responsible</th>
            <th>Source</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($deals)): ?>
            <tr>
                <td colspan="3">No deals found</td>
            </tr>
        <?php else: ?>
            <?php foreach ($deals as $deal): ?>
                <tr>
                    <td>
                        <a target="_blank"
                           href="https://crmasgroup.ge/crm/deal/details/<?= (int)$deal['ID']; ?>/">
                            <?= (int)$deal['ID']; ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($users[$deal['ASSIGNED_BY_ID']] ?? '—') ?></td>
                    <td><?= htmlspecialchars($allSources[$deal['SOURCE_ID']] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <style>
        .deal-table {
            width: 100%;
            border-collapse: collapse;
        }
        .deal-table th,
        .deal-table td {
            border: 1px solid #ccc;
            padding: 8px 12px;
        }
        .deal-table th {
            background: #f5f5f5;
        }
        .deal-table a {
            color: #2067b0;
            text-decoration: none;
            font-weight: 600;
        }
        .deal-table a:hover {
            text-decoration: underline;
        }
    </style>

<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
