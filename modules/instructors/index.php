<?php

session_start();

require_once '../../config/database.php';
require_once '../../includes/language.php';
function t_instructor($en, $ar) {
    global $lang;
    return $lang === 'ar' ? $ar : $en;
}


// ========================================
// AUTHORIZATION
// ========================================

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Admin and Staff can access Instructor Management
if (!in_array($_SESSION['role'], ['admin', 'staff'])) {
    die('Access denied.');
}

$isAdmin = $_SESSION['role'] === 'admin';


// ========================================
// HANDLE ACTIVATE / DEACTIVATE
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {

    // Only Admin can change instructor status
    if (!$isAdmin) {
        die('Access denied.');
    }

    $instructorId = filter_input(
        INPUT_POST,
        'instructor_id',
        FILTER_VALIDATE_INT
    );

    if (!$instructorId || $instructorId <= 0) {
        die('Invalid instructor ID.');
    }

    // Get current instructor information
    $statusStmt = $pdo->prepare("
        SELECT
            i.id,
            i.user_id,
            i.status
        FROM instructors i
        WHERE i.id = ?
        LIMIT 1
    ");

    $statusStmt->execute([$instructorId]);

    $instructorData = $statusStmt->fetch();

    if (!$instructorData) {
        die('Instructor not found.');
    }

    // Toggle status
    $newStatus = $instructorData['status'] === 'active'
        ? 'inactive'
        : 'active';

    try {

        $pdo->beginTransaction();

        // ========================================
        // UPDATE INSTRUCTOR STATUS
        // ========================================

        $updateInstructor = $pdo->prepare("
            UPDATE instructors
            SET
                status = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $updateInstructor->execute([
            $newStatus,
            $instructorId
        ]);

        // ========================================
        // KEEP USER ACCOUNT STATUS SYNCHRONIZED
        // ========================================

        $updateUser = $pdo->prepare("
            UPDATE users
            SET
                status = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
            AND role = 'instructor'
        ");

        $updateUser->execute([
            $newStatus,
            $instructorData['user_id']
        ]);

        $pdo->commit();

        header('Location: index.php?status_updated=1');
        exit;

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        die('Failed to update instructor status.');
    }
}


// ========================================
// SEARCH & FILTER
// ========================================

$search = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$filterSpecialization = $_GET['specialization'] ?? '';


// ========================================
// GET SPECIALIZATIONS FOR FILTER
// ========================================

$specializationStmt = $pdo->query("
    SELECT DISTINCT specialization
    FROM instructors
    WHERE specialization IS NOT NULL
      AND specialization != ''
    ORDER BY specialization ASC
");

$specializations = $specializationStmt->fetchAll();


// ========================================
// BUILD INSTRUCTOR QUERY
// ========================================

$sql = "
    SELECT
        i.id,
        i.user_id,
        i.full_name,
        i.phone,
        i.email,
        i.specialization,
        i.status,
        i.created_at,
        i.updated_at,
        u.username
    FROM instructors i
    LEFT JOIN users u ON i.user_id = u.id
";

$where = [];
$params = [];


// ========================================
// SEARCH
// ========================================

if ($search !== '') {

    $where[] = "
        (
            i.full_name LIKE ?
            OR i.phone LIKE ?
            OR i.email LIKE ?
            OR i.specialization LIKE ?
            OR u.username LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


// ========================================
// STATUS FILTER
// ========================================

if (in_array($filterStatus, ['active', 'inactive'])) {

    $where[] = "i.status = ?";
    $params[] = $filterStatus;
}


// ========================================
// SPECIALIZATION FILTER
// ========================================

if ($filterSpecialization !== '') {

    $where[] = "i.specialization = ?";
    $params[] = $filterSpecialization;
}


// ========================================
// APPLY WHERE
// ========================================

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}


// ========================================
// ORDER
// ========================================

$sql .= " ORDER BY i.id DESC";


// ========================================
// EXECUTE QUERY
// ========================================

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$instructors = $stmt->fetchAll();


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
   BACK BUTTON
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
   BUTTONS
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

    border: none;

    cursor: pointer;
}

.btn-edit:hover {
    background: #dbeafe;
}


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


.btn-deactivate {
    background: #fee2e2;

    color: #b91c1c;
}

.btn-deactivate:hover {
    background: #fecaca;
}


.btn-activate {
    background: #dcfce7;

    color: #166534;
}

.btn-activate:hover {
    background: #bbf7d0;
}


/* ========================================
   SUCCESS MESSAGE
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

    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
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
   RESULT COUNT
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

    min-width: 950px;

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
   USERNAME
======================================== */

.username {
    font-weight: 600;

    color: #2563eb;
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

.status-active {
    background: #dcfce7;

    color: #166534;
}

.status-inactive {
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
            <?= htmlspecialchars(__('instructors')) ?>
        </h1>

        <p>
            <?= htmlspecialchars(t_instructor('Manage instructors and their information.', 'إدارة المدرسين ومعلوماتهم.')) ?>
        </p>

        <div class="top-actions">

            <a
                href="../../modules/dashboard/index.php"
                class="btn-back"
            >
                <?= htmlspecialchars(t_instructor('← Back to Dashboard', '← العودة إلى لوحة التحكم')) ?>
            </a>

        </div>

    </div>


    <?php if ($isAdmin): ?>

        <a
            href="add.php"
            class="btn-add"
        >
            <?= htmlspecialchars(t_instructor('+ Add Instructor', '+ إضافة مدرس')) ?>
        </a>

    <?php endif; ?>

</div>


<!-- ========================================
     SUCCESS MESSAGES
======================================== -->

<?php if (isset($_GET['status_updated'])): ?>

    <div class="alert-success">
        <?= htmlspecialchars(t_instructor('Instructor status updated successfully.', 'تم تحديث حالة المدرس بنجاح.')) ?>
    </div>

<?php endif; ?>


<?php if (isset($_GET['updated'])): ?>

    <div class="alert-success">
        <?= htmlspecialchars(t_instructor('Instructor information updated successfully.', 'تم تحديث معلومات المدرس بنجاح.')) ?>
    </div>

<?php endif; ?>


<?php if (isset($_GET['success'])): ?>

    <div class="alert-success">
        <?= htmlspecialchars(t_instructor('Instructor added successfully.', 'تمت إضافة المدرس بنجاح.')) ?>
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
            <?= htmlspecialchars(t_instructor('Search & Filter Instructors', 'البحث وتصفية المدرسين')) ?>
        </h2>

        <form
            method="GET"
            action="index.php"
            class="filter-form"
        >

            <!-- SEARCH -->

            <div class="filter-group">

                <label for="search">
                    <?= htmlspecialchars(__('search')) ?>
                </label>

                <input
                    type="text"
                    id="search"
                    name="search"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="<?= htmlspecialchars(t_instructor('Username, name, phone, email, specialization...', 'اسم المستخدم، الاسم، الهاتف، البريد الإلكتروني، التخصص...')) ?>"
                >

            </div>


            <!-- STATUS -->

            <div class="filter-group">

                <label for="status">
                    <?= htmlspecialchars(__('status')) ?>
                </label>

                <select
                    id="status"
                    name="status"
                >

                    <option value="">
                        <?= htmlspecialchars(t_instructor('All Status', 'كل الحالات')) ?>
                    </option>

                    <option
                        value="active"
                        <?= $filterStatus === 'active' ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars(t_instructor('Active', 'نشط')) ?>
                    </option>

                    <option
                        value="inactive"
                        <?= $filterStatus === 'inactive' ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars(t_instructor('Inactive', 'غير نشط')) ?>
                    </option>

                </select>

            </div>


            <!-- SPECIALIZATION -->

            <div class="filter-group">

                <label for="specialization">
                    <?= htmlspecialchars(t_instructor('Specialization', 'التخصص')) ?>
                </label>

                <select
                    id="specialization"
                    name="specialization"
                >

                    <option value="">
                        <?= htmlspecialchars(t_instructor('All Specializations', 'كل التخصصات')) ?>
                    </option>

                    <?php foreach ($specializations as $item): ?>

                        <option
                            value="<?= htmlspecialchars($item['specialization']) ?>"
                            <?= $filterSpecialization === $item['specialization'] ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($item['specialization']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- BUTTONS -->

            <div class="filter-actions">

                <button
                    type="submit"
                    class="btn-filter"
                >
                    <?= htmlspecialchars(t_instructor('🔎 Search / Filter', '🔎 بحث / تصفية')) ?>
                </button>

                <a
                    href="index.php"
                    class="btn-clear"
                >
                    <?= htmlspecialchars(t_instructor('Clear', 'مسح')) ?>
                </a>

            </div>

        </form>

    </div>


    <!-- ========================================
         RESULT COUNT
    ======================================== -->

    <div class="result-info">

        <?= htmlspecialchars(t_instructor('Showing', 'عرض')) ?>
        <strong><?= count($instructors) ?></strong>
        <?= htmlspecialchars(t_instructor('instructor(s)', 'مدرس(ين)')) ?>

        <?php if ($search !== '' || $filterStatus !== '' || $filterSpecialization !== ''): ?>

            <?= htmlspecialchars(t_instructor('matching your search/filter.', 'مطابقة للبحث/التصفية الحالية.')) ?>

        <?php endif; ?>

    </div>


    <!-- ========================================
         TABLE
    ======================================== -->

    <div class="table-card">


        <?php if (count($instructors) > 0): ?>


            <div class="table-wrapper">

                <table>

                    <!-- ========================================
                         TABLE HEADER
                    ======================================== -->

                    <thead>

                        <tr>

                            <th>
                                #
                            </th>

                            <th>
                                <?= htmlspecialchars(t_instructor('Username', 'اسم المستخدم')) ?>
                            </th>

                            <th>
                                <?= htmlspecialchars(t_instructor('Full Name', 'الاسم الكامل')) ?>
                            </th>

                            <th>
                                <?= htmlspecialchars(__('phone')) ?>
                            </th>

                            <th>
                                <?= htmlspecialchars(__('email')) ?>
                            </th>

                            <th>
                                <?= htmlspecialchars(t_instructor('Specialization', 'التخصص')) ?>
                            </th>

                            <th>
                                <?= htmlspecialchars(__('status')) ?>
                            </th>

                            <th>
                                <?= htmlspecialchars(__('actions')) ?>
                            </th>

                        </tr>

                    </thead>


                    <!-- ========================================
                         TABLE BODY
                    ======================================== -->

                    <tbody>

                        <?php foreach ($instructors as $index => $instructor): ?>

                            <tr>

                                <!-- NUMBER -->

                                <td>
                                    <?= $index + 1 ?>
                                </td>


                                <!-- USERNAME -->

                                <td>

                                    <span class="username">

                                        <?= htmlspecialchars(
                                            $instructor['username'] ?? '-'
                                        ) ?>

                                    </span>

                                </td>


                                <!-- FULL NAME -->

                                <td>

                                    <?= htmlspecialchars(
                                        $instructor['full_name']
                                    ) ?>

                                </td>


                                <!-- PHONE -->

                                <td>

                                    <?= htmlspecialchars(
                                        $instructor['phone'] ?? '-'
                                    ) ?>

                                </td>


                                <!-- EMAIL -->

                                <td>

                                    <?= htmlspecialchars(
                                        $instructor['email'] ?? '-'
                                    ) ?>

                                </td>


                                <!-- SPECIALIZATION -->

                                <td>

                                    <?= htmlspecialchars(
                                        $instructor['specialization'] ?? '-'
                                    ) ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?php if (
                                        $instructor['status'] === 'active'
                                    ): ?>

                                        <span class="status status-active">
                                            Active
                                        </span>

                                    <?php else: ?>

                                        <span class="status status-inactive">
                                            Inactive
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div class="actions">


                                        <!-- EDIT -->

                                        <?php if ($isAdmin): ?>

                                            <a
                                                href="edit.php?id=<?= (int) $instructor['id'] ?>"
                                                class="btn-edit"
                                            >
                                                <?= htmlspecialchars(__('edit')) ?>
                                            </a>

                                        <?php endif; ?>


                                        <!-- ACTIVATE / DEACTIVATE -->

                                        <?php if ($isAdmin): ?>

                                            <form
                                                method="POST"
                                                onsubmit="return confirmStatusChange(
                                                    '<?= htmlspecialchars(
                                                        $instructor['full_name'],
                                                        ENT_QUOTES
                                                    ) ?>',
                                                    '<?= $instructor['status'] ?>'
                                                );"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="instructor_id"
                                                    value="<?= (int) $instructor['id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="toggle_status"
                                                    class="btn-status <?= $instructor['status'] === 'active'
                                                        ? 'btn-deactivate'
                                                        : 'btn-activate'
                                                    ?>"
                                                >

                                                    <?= $instructor['status'] === 'active'
                                                        ? 'Deactivate'
                                                        : 'Activate'
                                                    ?>

                                                </button>

                                            </form>

                                        <?php else: ?>

                                            <span
                                                style="
                                                    color:#9ca3af;
                                                    font-size:12px;
                                                "
                                            >
                                                View Only
                                            </span>

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
                    || $filterStatus !== ''
                    || $filterSpecialization !== ''
                ): ?>

                    <h3>
                        <?= htmlspecialchars(t_instructor('No Instructors Found', 'لم يتم العثور على مدرسين')) ?>
                    </h3>

                    <p>
                        <?= htmlspecialchars(t_instructor('No instructors match your current search or filters.', 'لا يوجد مدرسون يطابقون البحث أو عوامل التصفية الحالية.')) ?>
                    </p>

                    <div style="margin-top:15px;">

                        <a
                            href="index.php"
                            class="btn-clear"
                        >
                            <?= htmlspecialchars(t_instructor('Clear Filters', 'مسح عوامل التصفية')) ?>
                        </a>

                    </div>

                <?php else: ?>

                    <h3>
                        <?= htmlspecialchars(t_instructor('No Instructors Found', 'لم يتم العثور على مدرسين')) ?>
                    </h3>

                    <p>
                        <?= htmlspecialchars(t_instructor('There are currently no instructors in the system.', 'لا يوجد مدرسون حاليًا في النظام.')) ?>
                    </p>

                <?php endif; ?>

            </div>


        <?php endif; ?>


    </div>


</main>


<script>

function confirmStatusChange(name, currentStatus) {

    if (currentStatus === 'active') {

        return confirm(
            <?= json_encode($lang === 'ar'
                ? 'هل أنت متأكد من رغبتك في تعطيل "'
                : 'Are you sure you want to deactivate "'
            ) ?> +
            name +
            <?= json_encode($lang === 'ar'
                ? '"؟\n\nلن يتمكن المدرس من تسجيل الدخول بعد الآن.'
                : '"?\n\nThe instructor will no longer be able to log in.'
            ) ?>
        );

    }

    return confirm(
        <?= json_encode($lang === 'ar'
            ? 'هل أنت متأكد من رغبتك في تفعيل "'
            : 'Are you sure you want to activate "'
        ) ?> +
        name +
        <?= json_encode($lang === 'ar'
            ? '"؟\n\nسيتمكن المدرس من تسجيل الدخول مرة أخرى.'
            : '"?\n\nThe instructor will be able to log in again.'
        ) ?>
    );
}

</script>