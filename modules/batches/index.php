<?php

session_start();

require_once '../../config/database.php';
require_once '../../includes/language.php';

// ========================================
// AUTHORIZATION
// ========================================

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Admin and Staff can fully manage/view batches
if (!in_array($_SESSION['role'], ['admin', 'staff'], true)) {
    die($lang === 'ar' ? 'غير مسموح بالوصول.' : 'Access denied.');
}

$canManageBatches = in_array($_SESSION['role'], ['admin', 'staff'], true);


// ========================================
// HANDLE ACTIVATE / DEACTIVATE
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {

    // Admin and Staff can change batch status
    if (!$canManageBatches) {
        die($lang === 'ar' ? 'غير مسموح بالوصول.' : 'Access denied.');
    }

    $batchId = filter_input(
        INPUT_POST,
        'batch_id',
        FILTER_VALIDATE_INT
    );

    if (!$batchId || $batchId <= 0) {
        die($lang === 'ar' ? 'معرّف الدفعة غير صالح.' : 'Invalid batch ID.');
    }

    // Get current batch
    $statusStmt = $pdo->prepare("
        SELECT
            id,
            status
        FROM batches
        WHERE id = ?
        LIMIT 1
    ");

    $statusStmt->execute([$batchId]);

    $batchData = $statusStmt->fetch();

    if (!$batchData) {
        die($lang === 'ar' ? 'الدفعة غير موجودة.' : 'Batch not found.');
    }

    // Toggle active/planned status
    if ($batchData['status'] === 'active') {
        $newStatus = 'cancelled';
    } elseif ($batchData['status'] === 'cancelled') {
        $newStatus = 'planned';
    } else {
        $newStatus = 'active';
    }

    try {

        $updateStmt = $pdo->prepare("
            UPDATE batches
            SET
                status = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $updateStmt->execute([
            $newStatus,
            $batchId
        ]);

        header('Location: index.php?status_updated=1');
        exit;

    } catch (Exception $e) {

        die($lang === 'ar' ? 'فشل تحديث حالة الدفعة.' : 'Failed to update batch status.');
    }
}


// ========================================
// SEARCH & FILTER
// ========================================

$search = trim($_GET['search'] ?? '');
$filterCourse = $_GET['course'] ?? '';
$filterStatus = $_GET['status'] ?? '';


// ========================================
// FETCH COURSES FOR FILTER
// ========================================

$courseStmt = $pdo->query("
    SELECT
        id,
        course_code,
        course_name
    FROM courses
    ORDER BY course_name ASC
");

$courses = $courseStmt->fetchAll();


// ========================================
// BUILD BATCH QUERY
// ========================================

$sql = "
    SELECT
        b.id,
        b.batch_code,
        b.course_id,
        b.start_date,
        b.end_date,
        b.room,
        b.capacity,
        b.status,
        b.created_at,
        b.updated_at,

        c.course_code,
        c.course_name,

        COUNT(DISTINCT bi.instructor_id) AS instructor_count

    FROM batches b

    INNER JOIN courses c
        ON b.course_id = c.id

    LEFT JOIN batch_instructors bi
        ON b.id = bi.batch_id
";

$where = [];
$params = [];


// ========================================
// SEARCH
// ========================================

if ($search !== '') {

    $where[] = "
        (
            b.batch_code LIKE ?
            OR b.room LIKE ?
            OR c.course_code LIKE ?
            OR c.course_name LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


// ========================================
// COURSE FILTER
// ========================================

if ($filterCourse !== '') {

    $courseId = filter_var(
        $filterCourse,
        FILTER_VALIDATE_INT
    );

    if ($courseId) {

        $where[] = "b.course_id = ?";
        $params[] = $courseId;
    }
}


// ========================================
// STATUS FILTER
// ========================================

$allowedStatuses = [
    'planned',
    'active',
    'completed',
    'cancelled'
];

if (in_array($filterStatus, $allowedStatuses, true)) {

    $where[] = "b.status = ?";
    $params[] = $filterStatus;
}


// ========================================
// APPLY WHERE
// ========================================

if (!empty($where)) {

    $sql .= "
        WHERE
        " . implode(" AND ", $where);
}


// ========================================
// GROUP BY
// ========================================

$sql .= "
    GROUP BY
        b.id,
        b.batch_code,
        b.course_id,
        b.start_date,
        b.end_date,
        b.room,
        b.capacity,
        b.status,
        b.created_at,
        b.updated_at,
        c.course_code,
        c.course_name
";


// ========================================
// ORDER
// ========================================

$sql .= "
    ORDER BY b.id DESC
";


// ========================================
// EXECUTE QUERY
// ========================================

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$batches = $stmt->fetchAll();


// ========================================
// SIDEBAR
// ========================================

require_once '../../includes/sidebar.php';

?>

<style>

/* ========================================
   PAGE HEADER
======================================== */

.page-header {
    padding: 25px 30px 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.page-header h1 {
    margin: 0;
    font-size: 26px;
    color: #111827;
}

.page-header p {
    margin: 5px 0 0;
    color: #6b7280;
    font-size: 14px;
}


/* ========================================
   TOP ACTIONS
======================================== */

.top-actions {
    margin-top: 12px;
}

.btn-back {
    display: inline-block;
    background: #6b7280;
    color: white;
    text-decoration: none;
    padding: 9px 14px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    transition: background 0.2s;
}

.btn-back:hover {
    background: #4b5563;
}


/* ========================================
   ADD BUTTON
======================================== */

.btn-add {
    display: inline-block;
    background: #2563eb;
    color: white;
    text-decoration: none;
    padding: 10px 16px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    transition: background 0.2s;
}

.btn-add:hover {
    background: #1d4ed8;
}


/* ========================================
   CONTAINER
======================================== */

.container {
    padding: 20px 30px 40px;
}


/* ========================================
   FILTER CARD
======================================== */

.filter-card {
    background: white;
    border-radius: 10px;
    box-shadow:
        0 2px 10px rgba(0, 0, 0, 0.06);
    padding: 20px;
    margin-bottom: 20px;
}

.filter-title {
    margin: 0 0 15px;
    font-size: 16px;
    font-weight: 600;
    color: #374151;
}


/* ========================================
   FILTER FORM
======================================== */

.filter-form {
    display: grid;
    grid-template-columns:
        minmax(0, 2fr)
        minmax(0, 1fr)
        minmax(0, 1fr);
    gap: 12px;
    width: 100%;
}

.filter-group {
    min-width: 0;
}

.filter-group label {
    display: block;
    margin-bottom: 6px;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}

.filter-group input,
.filter-group select {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    background: white;
    color: #111827;
    outline: none;
}

.filter-group input:focus,
.filter-group select:focus {
    border-color: #2563eb;
    box-shadow:
        0 0 0 2px rgba(37, 99, 235, 0.1);
}


/* ========================================
   FILTER ACTIONS
======================================== */

.filter-actions {
    grid-column: 1 / -1;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 2px;
}

.btn-filter {
    border: none;
    background: #2563eb;
    color: white;
    padding: 10px 18px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: background 0.2s;
}

.btn-filter:hover {
    background: #1d4ed8;
}

.btn-clear {
    background: #6b7280;
    color: white;
    padding: 10px 18px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.2s;
}

.btn-clear:hover {
    background: #4b5563;
}


/* ========================================
   RESULT INFO
======================================== */

.result-info {
    margin-bottom: 12px;
    font-size: 14px;
    color: #6b7280;
}

.result-info strong {
    color: #111827;
}


/* ========================================
   ALERT
======================================== */

.alert-success {
    margin: 20px 30px 0;
    padding: 12px 15px;
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
    border-radius: 6px;
    font-size: 14px;
}


/* ========================================
   TABLE CARD
======================================== */

.table-card {
    background: white;
    border-radius: 10px;
    box-shadow:
        0 2px 10px rgba(0, 0, 0, 0.06);
    overflow: hidden;
}


/* ========================================
   TABLE WRAPPER
======================================== */

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}


/* ========================================
   TABLE
======================================== */

table {
    width: 100%;
    min-width: 1100px;
    border-collapse: collapse;
}

th,
td {
    padding: 13px 15px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
    font-size: 14px;
    white-space: nowrap;
}

th {
    background: #f9fafb;
    color: #374151;
    font-weight: 600;
}

td {
    color: #4b5563;
}

tbody tr:hover td {
    background: #f9fafb;
}


/* ========================================
   BATCH CODE
======================================== */

.batch-code {
    font-weight: 600;
    color: #2563eb;
}


/* ========================================
   COURSE
======================================== */

.course-name {
    font-weight: 600;
    color: #111827;
}

.course-code {
    display: block;
    margin-top: 3px;
    font-size: 12px;
    color: #6b7280;
}


/* ========================================
   STATUS
======================================== */

.status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.status-planned {
    background: #fef3c7;
    color: #92400e;
}

.status-active {
    background: #dcfce7;
    color: #166534;
}

.status-completed {
    background: #dbeafe;
    color: #1e40af;
}

.status-cancelled {
    background: #fee2e2;
    color: #991b1b;
}


/* ========================================
   ACTIONS
======================================== */

.actions {
    display: flex;
    gap: 7px;
    align-items: center;
}

.actions form {
    margin: 0;
    padding: 0;
}


/* ========================================
   VIEW BUTTON
======================================== */

.btn-view {
    display: inline-block;
    padding: 6px 11px;
    background: #f3f4f6;
    color: #374151;
    text-decoration: none;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 600;
    transition: background 0.2s;
}

.btn-view:hover {
    background: #e5e7eb;
}


/* ========================================
   EDIT BUTTON
======================================== */

.btn-edit {
    display: inline-block;
    padding: 6px 11px;
    background: #eff6ff;
    color: #1d4ed8;
    text-decoration: none;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 600;
    transition: background 0.2s;
}

.btn-edit:hover {
    background: #dbeafe;
}


/* ========================================
   STATUS BUTTON
======================================== */

.btn-status {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-cancel {
    background: #fee2e2;
    color: #b91c1c;
}

.btn-cancel:hover {
    background: #fecaca;
}

.btn-reactivate {
    background: #dcfce7;
    color: #166534;
}

.btn-reactivate:hover {
    background: #bbf7d0;
}


/* ========================================
   EMPTY STATE
======================================== */

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6b7280;
}

.empty-state h3 {
    margin: 0 0 8px;
    color: #374151;
    font-size: 18px;
}

.empty-state p {
    margin: 0;
    font-size: 14px;
}


/* ========================================
   RESPONSIVE
======================================== */

@media (max-width: 1000px) {

    .filter-form {
        grid-template-columns:
            minmax(0, 1fr)
            minmax(0, 1fr);
    }

    .filter-group:first-child {
        grid-column: 1 / -1;
    }
}


@media (max-width: 768px) {

    .page-header {
        padding: 20px;
        align-items: flex-start;
        flex-direction: column;
    }

    .container {
        padding: 15px 20px 30px;
    }

    .btn-add {
        width: 100%;
        text-align: center;
    }

    .alert-success {
        margin-left: 20px;
        margin-right: 20px;
    }

    .filter-form {
        grid-template-columns: 1fr;
    }

    .filter-group:first-child {
        grid-column: auto;
    }

    .filter-actions {
        grid-column: auto;
        justify-content: stretch;
        flex-direction: column;
    }

    .btn-filter,
    .btn-clear {
        width: 100%;
        text-align: center;
        box-sizing: border-box;
    }
}

</style>


<!-- ========================================
     PAGE HEADER
======================================== -->

<div class="page-header">

    <div>

        <h1>
            <?= $lang === 'ar' ? 'الدفعات' : 'Batches' ?>
        </h1>

        <p>
            <?= $lang === 'ar' ? 'إدارة دفعات المقررات والجداول والقاعات والمدرسين.' : 'Manage course batches, schedules, rooms, and instructors.' ?>
        </p>

        <div class="top-actions">

            <a
                href="../../modules/dashboard/index.php"
                class="btn-back"
            >
                <?= $lang === 'ar' ? '← العودة إلى لوحة التحكم' : '← Back to Dashboard' ?>
            </a>

        </div>

    </div>


    <!-- ADMIN + STAFF -->

    <?php if ($canManageBatches): ?>

        <a
            href="add.php"
            class="btn-add"
        >
            <?= $lang === 'ar' ? '+ إضافة دفعة' : '+ Add Batch' ?>
        </a>

    <?php endif; ?>

</div>


<!-- ========================================
     SUCCESS MESSAGE
======================================== -->

<?php if (isset($_GET['status_updated'])): ?>

    <div class="alert-success">
        <?= $lang === 'ar' ? 'تم تحديث حالة الدفعة بنجاح.' : 'Batch status updated successfully.' ?>
    </div>

<?php endif; ?>


<?php if (isset($_GET['success'])): ?>

    <div class="alert-success">
        <?= $lang === 'ar' ? 'تمت إضافة الدفعة بنجاح.' : 'Batch added successfully.' ?>
    </div>

<?php endif; ?>


<?php if (isset($_GET['updated'])): ?>

    <div class="alert-success">
        <?= $lang === 'ar' ? 'تم تحديث معلومات الدفعة بنجاح.' : 'Batch information updated successfully.' ?>
    </div>

<?php endif; ?>


<!-- ========================================
     MAIN CONTENT
======================================== -->

<main class="container">


    <!-- ========================================
         SEARCH & FILTER
    ======================================== -->

    <div class="filter-card">

        <h2 class="filter-title">
            <?= $lang === 'ar' ? 'البحث وتصفية الدفعات' : 'Search & Filter Batches' ?>
        </h2>


        <form
            method="GET"
            action="index.php"
            class="filter-form"
        >


            <!-- SEARCH -->

            <div class="filter-group">

                <label for="search">
                    <?= $lang === 'ar' ? 'بحث' : 'Search' ?>
                </label>

                <input
                    type="text"
                    id="search"
                    name="search"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="<?= $lang === 'ar' ? 'رمز الدفعة، المقرر، القاعة...' : 'Batch code, course, room...' ?>"
                >

            </div>


            <!-- COURSE -->

            <div class="filter-group">

                <label for="course">
                    <?= $lang === 'ar' ? 'المقرر' : 'Course' ?>
                </label>

                <select
                    id="course"
                    name="course"
                >

                    <option value="">
                        <?= $lang === 'ar' ? 'جميع المقررات' : 'All Courses' ?>
                    </option>

                    <?php foreach ($courses as $course): ?>

                        <option
                            value="<?= (int) $course['id'] ?>"
                            <?= (string) $filterCourse === (string) $course['id']
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= htmlspecialchars(
                                $course['course_code']
                            ) ?>

                            -

                            <?= htmlspecialchars(
                                $course['course_name']
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- STATUS -->

            <div class="filter-group">

                <label for="status">
                    <?= $lang === 'ar' ? 'الحالة' : 'Status' ?>
                </label>

                <select
                    id="status"
                    name="status"
                >

                    <option value="">
                        <?= $lang === 'ar' ? 'جميع الحالات' : 'All Status' ?>
                    </option>

                    <option
                        value="planned"
                        <?= $filterStatus === 'planned'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        <?= $lang === 'ar' ? 'مخطط' : 'Planned' ?>
                    </option>

                    <option
                        value="active"
                        <?= $filterStatus === 'active'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        <?= $lang === 'ar' ? 'نشط' : 'Active' ?>
                    </option>

                    <option
                        value="completed"
                        <?= $filterStatus === 'completed'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        <?= $lang === 'ar' ? 'مكتمل' : 'Completed' ?>
                    </option>

                    <option
                        value="cancelled"
                        <?= $filterStatus === 'cancelled'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        <?= $lang === 'ar' ? 'ملغى' : 'Cancelled' ?>
                    </option>

                </select>

            </div>


            <!-- FILTER ACTIONS -->

            <div class="filter-actions">

                <button
                    type="submit"
                    class="btn-filter"
                >
                    <?= $lang === 'ar' ? '🔎 بحث / تصفية' : '🔎 Search / Filter' ?>
                </button>

                <a
                    href="index.php"
                    class="btn-clear"
                >
                    <?= $lang === 'ar' ? 'مسح' : 'Clear' ?>
                </a>

            </div>


        </form>

    </div>


    <!-- ========================================
         RESULT COUNT
    ======================================== -->

    <div class="result-info">

        <?= $lang === 'ar' ? 'عرض' : 'Showing' ?>

        <strong>
            <?= count($batches) ?>
        </strong>

        <?= $lang === 'ar' ? 'دفعة/دفعات' : 'batch(es)' ?>

        <?php if (
            $search !== ''
            || $filterCourse !== ''
            || $filterStatus !== ''
        ): ?>

            <?= $lang === 'ar' ? 'مطابقة لبحثك/تصفية البحث.' : 'matching your search/filter.' ?>

        <?php endif; ?>

    </div>


    <!-- ========================================
         TABLE
    ======================================== -->

    <div class="table-card">


        <?php if (count($batches) > 0): ?>


            <div class="table-wrapper">

                <table>


                    <!-- TABLE HEADER -->

                    <thead>

                        <tr>

                            <th>
                                #
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'رمز الدفعة' : 'Batch Code' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'المقرر' : 'Course' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'تاريخ البداية' : 'Start Date' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'تاريخ النهاية' : 'End Date' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'القاعة' : 'Room' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'السعة' : 'Capacity' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'المدرسون' : 'Instructors' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'الحالة' : 'Status' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'الإجراءات' : 'Actions' ?>
                            </th>

                        </tr>

                    </thead>


                    <!-- TABLE BODY -->

                    <tbody>


                        <?php foreach (
                            $batches
                            as $index => $batch
                        ): ?>


                            <tr>


                                <!-- NUMBER -->

                                <td>

                                    <?= $index + 1 ?>

                                </td>


                                <!-- BATCH CODE -->

                                <td>

                                    <span class="batch-code">

                                        <?= htmlspecialchars(
                                            $batch['batch_code']
                                        ) ?>

                                    </span>

                                </td>


                                <!-- COURSE -->

                                <td>

                                    <span class="course-name">

                                        <?= htmlspecialchars(
                                            $batch['course_name']
                                        ) ?>

                                    </span>

                                    <span class="course-code">

                                        <?= htmlspecialchars(
                                            $batch['course_code']
                                        ) ?>

                                    </span>

                                </td>


                                <!-- START DATE -->

                                <td>

                                    <?= htmlspecialchars(
                                        date(
                                            'd M Y',
                                            strtotime(
                                                $batch['start_date']
                                            )
                                        )
                                    ) ?>

                                </td>


                                <!-- END DATE -->

                                <td>

                                    <?= htmlspecialchars(
                                        date(
                                            'd M Y',
                                            strtotime(
                                                $batch['end_date']
                                            )
                                        )
                                    ) ?>

                                </td>


                                <!-- ROOM -->

                                <td>

                                    <?= htmlspecialchars(
                                        $batch['room'] ?? '-'
                                    ) ?>

                                </td>


                                <!-- CAPACITY -->

                                <td>

                                    <?= (int) $batch['capacity'] ?>

                                </td>


                                <!-- INSTRUCTORS -->

                                <td>

                                    <?= (int) $batch['instructor_count'] ?>

                                    <?= $lang === 'ar' ? 'مدرس/مدرسين' : 'instructor(s)' ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?php

                                    $statusClass =
                                        'status-' .
                                        strtolower(
                                            $batch['status']
                                        );

                                    ?>

                                    <span
                                        class="status <?= $statusClass ?>"
                                    >

                                        <?= $lang === 'ar'
                                            ? (
                                                $batch['status'] === 'planned' ? 'مخطط' :
                                                ($batch['status'] === 'active' ? 'نشط' :
                                                ($batch['status'] === 'completed' ? 'مكتمل' : 'ملغى'))
                                            )
                                            : htmlspecialchars(ucfirst($batch['status']))
                                        ?>

                                    </span>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div class="actions">


                                        <!-- VIEW -->

                                        <a
                                            href="view.php?id=<?= (int) $batch['id'] ?>"
                                            class="btn-view"
                                        >
                                            <?= $lang === 'ar' ? 'عرض' : 'View' ?>
                                        </a>


                                        <!-- ADMIN + STAFF -->

                                        <?php if ($canManageBatches): ?>


                                            <!-- EDIT -->

                                            <a
                                                href="edit.php?id=<?= (int) $batch['id'] ?>"
                                                class="btn-edit"
                                            >
                                                <?= $lang === 'ar' ? 'تعديل' : 'Edit' ?>
                                            </a>


                                            <!-- STATUS -->

                                            <?php if (
                                                $batch['status'] === 'active'
                                            ): ?>

                                                <form
                                                    method="POST"
                                                    onsubmit="return confirm(
                                                        '<?= $lang === 'ar' ? 'هل أنت متأكد أنك تريد إلغاء هذه الدفعة؟' : 'Are you sure you want to cancel this batch?' ?>'
                                                    );"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="batch_id"
                                                        value="<?= (int) $batch['id'] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        name="toggle_status"
                                                        class="btn-status btn-cancel"
                                                    >
                                                        <?= $lang === 'ar' ? 'إلغاء' : 'Cancel' ?>
                                                    </button>

                                                </form>


                                            <?php elseif (
                                                $batch['status'] === 'cancelled'
                                            ): ?>


                                                <form
                                                    method="POST"
                                                    onsubmit="return confirm(
                                                        '<?= $lang === 'ar' ? 'هل أنت متأكد أنك تريد إعادة تفعيل هذه الدفعة؟' : 'Are you sure you want to reactivate this batch?' ?>'
                                                    );"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="batch_id"
                                                        value="<?= (int) $batch['id'] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        name="toggle_status"
                                                        class="btn-status btn-reactivate"
                                                    >
                                                        <?= $lang === 'ar' ? 'إعادة تفعيل' : 'Reactivate' ?>
                                                    </button>

                                                </form>


                                            <?php endif; ?>


                                        <?php endif; ?>


                                    </div>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    </tbody>


                </table>

            </div>


        <?php else: ?>


            <!-- ========================================
                 EMPTY STATE
            ======================================== -->

            <div class="empty-state">


                <?php if (
                    $search !== ''
                    || $filterCourse !== ''
                    || $filterStatus !== ''
                ): ?>


                    <h3>
                        <?= $lang === 'ar' ? 'لا توجد دفعات' : 'No Batches Found' ?>
                    </h3>

                    <p>
                        <?= $lang === 'ar' ? 'لا توجد دفعات تطابق البحث أو التصفية الحالية.' : 'No batches match your current search or filters.' ?>
                    </p>

                    <div style="margin-top:15px;">

                        <a
                            href="index.php"
                            class="btn-clear"
                        >
                            <?= $lang === 'ar' ? 'مسح التصفية' : 'Clear Filters' ?>
                        </a>

                    </div>


                <?php else: ?>


                    <h3>
                        <?= $lang === 'ar' ? 'لا توجد دفعات' : 'No Batches Found' ?>
                    </h3>

                    <p>
                        <?= $lang === 'ar' ? 'لا توجد دفعات حالياً في النظام.' : 'There are currently no batches in the system.' ?>
                    </p>


                <?php endif; ?>


            </div>


        <?php endif; ?>


    </div>


</main>