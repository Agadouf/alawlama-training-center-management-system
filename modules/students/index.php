<?php

session_start();

require_once '../../config/database.php';

// =========================
// AUTHENTICATION
// =========================

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$allowedRoles = ['admin', 'staff'];

if (!in_array($_SESSION['role'], $allowedRoles, true)) {
    http_response_code(403);
    exit('Access denied.');
}

$message = '';
$error = '';


// =========================
// ADD STUDENT
// =========================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {

    $fullName = trim($_POST['full_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $dateOfBirth = $_POST['date_of_birth'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $guardianName = trim($_POST['guardian_name'] ?? '');
    $guardianPhone = trim($_POST['guardian_phone'] ?? '');
    $registrationDate = $_POST['registration_date'] ?? date('Y-m-d');
    $photoPath = null;


    // =========================
    // PHOTO UPLOAD
    // =========================

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Failed to upload student photo.';
        } elseif ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
            $error = 'Student photo must not exceed 5 MB.';
        } else {
            $imageInfo = @getimagesize($_FILES['photo']['tmp_name']);
            $allowedTypes = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
            if (!$imageInfo || !isset($allowedTypes[$imageInfo[2]])) {
                $error = 'Invalid photo. Please upload a JPG, PNG, or WEBP image.';
            } else {
                $uploadDir = __DIR__ . '/../../uploads/students/';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                    $error = 'Unable to create the student photo upload directory.';
                } else {
                    $filename = 'student_' . bin2hex(random_bytes(16)) . '.' . $allowedTypes[$imageInfo[2]];
                    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
                        $error = 'Failed to save the student photo.';
                    } else {
                        $photoPath = 'uploads/students/' . $filename;
                    }
                }
            }
        }
    }


    // =========================
    // VALIDATION
    // =========================

    if ($error !== '') {

        // Stop processing because photo validation failed.

    } elseif (
        $fullName === '' ||
        $gender === '' ||
        $registrationDate === ''
    ) {

        $error = 'Full name, gender, and registration date are required.';

    } elseif (!in_array($gender, ['Male', 'Female'], true)) {

        $error = 'Invalid gender selected.';

    } else {

        try {

            // =========================
            // GENERATE STUDENT ID
            // =========================

            $year = date('Y', strtotime($registrationDate));

            $stmt = $pdo->prepare("
                SELECT student_code
                FROM students
                WHERE student_code LIKE ?
                ORDER BY id DESC
                LIMIT 1
            ");

            $stmt->execute([
                'ALW-' . $year . '-%'
            ]);

            $lastStudent = $stmt->fetch();


            if ($lastStudent) {

                $parts = explode(
                    '-',
                    $lastStudent['student_code']
                );

                $lastNumber = (int) end($parts);

                $newNumber = $lastNumber + 1;

            } else {

                $newNumber = 1;

            }


            $studentCode =
                'ALW-' .
                $year .
                '-' .
                str_pad(
                    $newNumber,
                    4,
                    '0',
                    STR_PAD_LEFT
                );


            // =========================
            // INSERT STUDENT
            // =========================

            $stmt = $pdo->prepare("
                INSERT INTO students
                (
                    student_code,
                    full_name,
                    gender,
                    date_of_birth,
                    phone,
                    email,
                    address,
                    guardian_name,
                    guardian_phone,
                    registration_date,
                    photo,
                    status
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active'
                )
            ");

            $stmt->execute([
                $studentCode,
                $fullName,
                $gender,
                $dateOfBirth !== '' ? $dateOfBirth : null,
                $phone !== '' ? $phone : null,
                $email !== '' ? $email : null,
                $address !== '' ? $address : null,
                $guardianName !== '' ? $guardianName : null,
                $guardianPhone !== '' ? $guardianPhone : null,
                $registrationDate,
                $photoPath
            ]);


            $message =
                "Student created successfully. Student ID: "
                . $studentCode;

        } catch (PDOException $e) {

            if ($photoPath) {
                $uploadedFile = __DIR__ . '/../../' . $photoPath;
                if (is_file($uploadedFile)) {
                    @unlink($uploadedFile);
                }
            }

            $error =
                'Database error: ' . $e->getMessage();
        }
    }
}


// =========================
// SEARCH & FILTER
// =========================

$search = trim($_GET['search'] ?? '');

$filterGender = $_GET['gender'] ?? '';

$filterStatus = $_GET['status'] ?? '';

$filterDateFrom = $_GET['date_from'] ?? '';

$filterDateTo = $_GET['date_to'] ?? '';


// =========================
// GET STUDENTS
// =========================

$sql = "
    SELECT
        id,
        student_code,
        full_name,
        gender,
        date_of_birth,
        phone,
        email,
        registration_date,
        status
    FROM students
";

$where = [];

$params = [];


// =========================
// SEARCH
// =========================

if ($search !== '') {

    $where[] = "
        (
            student_code LIKE ?
            OR full_name LIKE ?
            OR phone LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


// =========================
// GENDER FILTER
// =========================

if (
    in_array(
        $filterGender,
        ['Male', 'Female'],
        true
    )
) {

    $where[] = "gender = ?";

    $params[] = $filterGender;
}


// =========================
// STATUS FILTER
// =========================

$validStatuses = [
    'active',
    'suspended',
    'inactive',
    'graduated'
];

if (
    in_array(
        $filterStatus,
        $validStatuses,
        true
    )
) {

    $where[] = "status = ?";

    $params[] = $filterStatus;
}


// =========================
// REGISTRATION DATE FILTER
// =========================

if ($filterDateFrom !== '') {

    $where[] = "registration_date >= ?";

    $params[] = $filterDateFrom;
}

if ($filterDateTo !== '') {

    $where[] = "registration_date <= ?";

    $params[] = $filterDateTo;
}


// =========================
// WHERE CLAUSE
// =========================

if (!empty($where)) {

    $sql .=
        " WHERE " .
        implode(" AND ", $where);
}


// =========================
// ORDER
// =========================

$sql .= " ORDER BY id DESC";


// =========================
// EXECUTE QUERY
// =========================

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$students = $stmt->fetchAll();


// =========================
// RESULT COUNT
// =========================

$resultCount = count($students);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Students - ALAW LAMA Training Center
    </title>


    <style>

        /* =========================
           RESET
        ========================== */

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        /* =========================
           BODY
        ========================== */

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            color: #1f2937;
        }


        /* =========================
           HEADER
        ========================== */

        .header {
            background: #1f2937;
            color: white;

            padding: 18px 30px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            min-height: 70px;
        }


        .header h1 {
            font-size: 22px;
        }


        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }


        .user-info {
            color: #d1d5db;
            font-size: 14px;
        }


        .logout-button {
            background: #dc2626;
            color: white;

            text-decoration: none;

            padding: 9px 15px;

            border-radius: 6px;

            font-size: 14px;
        }


        .logout-button:hover {
            background: #b91c1c;
        }


        /* =========================
           PAGE CONTAINER
        ========================== */

        .container {
            width: 100%;
            padding: 30px;
        }


        /* =========================
           PAGE TITLE
        ========================== */

        .page-title {
            margin-bottom: 25px;
        }


        .page-title h2 {
            font-size: 28px;
            margin-bottom: 7px;
        }


        .page-title p {
            color: #6b7280;
            font-size: 16px;
        }


        /* =========================
           BACK BUTTON
        ========================== */

        .top-actions {
            margin-top: 12px;
        }


        .btn-back {
            display: inline-block;

            padding: 9px 14px;

            background: #6b7280;
            color: white;

            text-decoration: none;

            border-radius: 6px;

            font-size: 13px;
            font-weight: bold;
        }


        .btn-back:hover {
            background: #4b5563;
        }


        /* =========================
           MAIN GRID
        ========================== */

        .grid {
            display: grid;

            grid-template-columns:
                minmax(300px, 360px)
                minmax(0, 1fr);

            gap: 25px;

            align-items: start;
        }


        /* =========================
           CARD
        ========================== */

        .card {
            background: white;

            padding: 25px;

            border-radius: 10px;

            box-shadow:
                0 2px 8px rgba(0, 0, 0, 0.05);

            min-width: 0;

            overflow: hidden;
        }


        .card h3 {
            font-size: 20px;
            margin-bottom: 20px;
        }


        /* =========================
           FORM
        ========================== */

        .form-group {
            margin-bottom: 15px;
        }


        label {
            display: block;

            margin-bottom: 6px;

            font-weight: bold;

            font-size: 14px;
        }


        .required {
            color: #dc2626;
        }


        input,
        select,
        textarea {

            width: 100%;

            padding: 11px;

            border:
                1px solid #d1d5db;

            border-radius: 6px;

            font-size: 14px;

            background: white;
        }


        input[type="file"] {
            padding: 9px;
            cursor: pointer;
        }


        .photo-help {
            margin-top: 6px;
            color: #6b7280;
            font-size: 12px;
        }


        input:focus,
        select:focus,
        textarea:focus {

            outline: none;

            border-color: #2563eb;
        }


        textarea {

            min-height: 80px;

            resize: vertical;
        }


        /* =========================
           PRIMARY BUTTON
        ========================== */

        .btn {

            border: none;

            padding: 11px 18px;

            border-radius: 6px;

            cursor: pointer;

            font-weight: bold;

            font-size: 14px;
        }


        .btn-primary {

            width: 100%;

            background: #2563eb;

            color: white;
        }


        .btn-primary:hover {

            background: #1d4ed8;
        }


        /* =========================
           ALERTS
        ========================== */

        .alert {

            padding: 14px 18px;

            border-radius: 6px;

            margin-bottom: 20px;
        }


        .success {

            background: #dcfce7;

            color: #166534;
        }


        .error {

            background: #fee2e2;

            color: #991b1b;
        }


        /* =========================
           INFO
        ========================== */

        .info {

            margin-top: 15px;

            padding: 12px;

            background: #f3f4f6;

            border-radius: 6px;

            font-size: 13px;

            color: #4b5563;
        }


        /* =========================
           FILTER BOX
        ========================== */

        .filter-box {

            background: #f9fafb;

            border: 1px solid #e5e7eb;

            border-radius: 8px;

            padding: 14px;

            margin-bottom: 20px;

            width: 100%;

            overflow: hidden;
        }


        /* =========================
           FILTER FORM
        ========================== */

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

            font-weight: bold;
        }


        .filter-group input,
        .filter-group select {

            width: 100%;

            height: 42px;

            min-width: 0;

            padding: 10px;

            border:
                1px solid #d1d5db;

            border-radius: 6px;

            font-size: 14px;

            background: white;
        }


        .filter-group input:focus,
        .filter-group select:focus {

            outline: none;

            border-color: #2563eb;
        }


        /* =========================
           FILTER BUTTONS
        ========================== */

        .filter-actions {

            grid-column: 1 / -1;

            display: flex;

            justify-content: flex-end;

            align-items: center;

            gap: 8px;

            margin-top: 2px;
        }


        .btn-filter {

            height: 40px;

            padding: 0 18px;

            border: none;

            border-radius: 6px;

            background: #2563eb;

            color: white;

            font-size: 14px;

            font-weight: bold;

            cursor: pointer;
        }


        .btn-filter:hover {

            background: #1d4ed8;
        }


        .btn-clear {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            height: 40px;

            padding: 0 18px;

            background: #6b7280;

            color: white;

            text-decoration: none;

            border-radius: 6px;

            font-size: 14px;

            font-weight: bold;

            white-space: nowrap;
        }


        .btn-clear:hover {

            background: #4b5563;
        }


        .result-count {

            margin-top: 12px;

            color: #6b7280;

            font-size: 14px;
        }


        /* =========================
           TABLE
        ========================== */

        .table-wrapper {

            width: 100%;

            overflow-x: auto;
        }


        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 750px;
        }


        th,
        td {

            padding: 12px 10px;

            border-bottom:
                1px solid #e5e7eb;

            text-align: left;

            white-space: nowrap;
        }


        th {

            background: #f9fafb;

            font-size: 13px;
        }


        td {

            font-size: 14px;
        }


        .student-code {

            font-weight: bold;

            color: #2563eb;
        }


        /* =========================
           STATUS BADGES
        ========================== */

        .badge {

            display: inline-block;

            padding: 5px 9px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;
        }


        .badge-active {

            background: #dcfce7;

            color: #166534;
        }


        .badge-suspended {

            background: #fef3c7;

            color: #92400e;
        }


        .badge-inactive {

            background: #fee2e2;

            color: #991b1b;
        }


        .badge-graduated {

            background: #dbeafe;

            color: #1d4ed8;
        }


        /* =========================
           EDIT BUTTON
        ========================== */

        .btn-edit {

            display: inline-block;

            padding: 7px 12px;

            background: #2563eb;

            color: white;

            text-decoration: none;

            border-radius: 5px;

            font-size: 13px;

            font-weight: bold;
        }


        .btn-edit:hover {

            background: #1d4ed8;
        }


        /* =========================
           EMPTY STATE
        ========================== */

        .empty {

            text-align: center;

            color: #6b7280;

            padding: 25px;
        }


        /* =========================
           RESPONSIVE
        ========================== */

        @media (max-width: 1100px) {

            .grid {

                grid-template-columns: 1fr;
            }


            .filter-form {

                grid-template-columns:
                    minmax(0, 1fr)
                    minmax(0, 1fr);
            }


            .filter-group:first-child {

                grid-column: 1 / -1;
            }


            .filter-actions {

                grid-column: 1 / -1;
            }

        }


        @media (max-width: 768px) {

            .header {

                padding: 15px 20px;
            }


            .header h1 {

                font-size: 18px;
            }


            .container {

                padding: 20px;
            }


            .user-info {

                display: none;
            }

        }


        @media (max-width: 600px) {

            .filter-form {

                grid-template-columns: 1fr;
            }


            .filter-group:first-child {

                grid-column: auto;
            }


            .filter-actions {

                grid-column: auto;

                display: grid;

                grid-template-columns: 1fr 1fr;
            }


            .btn-filter,
            .btn-clear {

                width: 100%;
            }

        }

    </style>

</head>


<body>


<?php

// =========================
// SIDEBAR
// =========================

require_once '../../includes/sidebar.php';

?>


<!-- =========================
     HEADER
========================== -->

<header class="header">

    <h1>
        ALAW LAMA TRAINING CENTER
    </h1>


    <div class="header-right">

        <span class="user-info">

            <?= htmlspecialchars($_SESSION['username']) ?>

            |

            <?= htmlspecialchars(
                ucfirst($_SESSION['role'])
            ) ?>

        </span>


        <a
            href="../../logout.php"
            class="logout-button"
        >
            Logout
        </a>

    </div>

</header>


<!-- =========================
     PAGE CONTENT
========================== -->

<main class="container">


    <!-- =========================
         PAGE TITLE
    ========================== -->

    <div class="page-title">

        <h2>
            Students
        </h2>


        <p>
            Manage students and their personal information.
        </p>


        <div class="top-actions">

            <a
                href="../../modules/dashboard/index.php"
                class="btn-back"
            >
                ← Back to Dashboard
            </a>

        </div>

    </div>


    <!-- =========================
         ALERTS
    ========================== -->

    <?php if ($message): ?>

        <div class="alert success">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- =========================
         MAIN GRID
    ========================== -->

    <div class="grid">


        <!-- =========================
             ADD STUDENT
        ========================== -->

        <div class="card">

            <h3>
                Add Student
            </h3>


            <form method="POST" enctype="multipart/form-data">


                <!-- FULL NAME -->

                <div class="form-group">

                    <label>

                        Full Name

                        <span class="required">
                            *
                        </span>

                    </label>


                    <input
                        type="text"
                        name="full_name"
                        maxlength="150"
                        required
                    >

                </div>


                <!-- PROFILE PHOTO -->

                <div class="form-group">

                    <label>
                        Profile Photo
                    </label>

                    <input
                        type="file"
                        name="photo"
                        accept="image/jpeg,image/png,image/webp"
                    >

                    <div class="photo-help">
                        JPG, PNG or WEBP. Maximum size: 5 MB.
                    </div>

                </div>


                <!-- GENDER -->

                <div class="form-group">

                    <label>

                        Gender

                        <span class="required">
                            *
                        </span>

                    </label>


                    <select
                        name="gender"
                        required
                    >

                        <option value="">
                            Select Gender
                        </option>

                        <option value="Male">
                            Male
                        </option>

                        <option value="Female">
                            Female
                        </option>

                    </select>

                </div>


                <!-- DATE OF BIRTH -->

                <div class="form-group">

                    <label>
                        Date of Birth
                    </label>


                    <input
                        type="date"
                        name="date_of_birth"
                    >

                </div>


                <!-- PHONE -->

                <div class="form-group">

                    <label>
                        Phone
                    </label>


                    <input
                        type="text"
                        name="phone"
                        maxlength="30"
                    >

                </div>


                <!-- EMAIL -->

                <div class="form-group">

                    <label>
                        Email
                    </label>


                    <input
                        type="email"
                        name="email"
                        maxlength="150"
                    >

                </div>


                <!-- ADDRESS -->

                <div class="form-group">

                    <label>
                        Address
                    </label>


                    <textarea
                        name="address"
                    ></textarea>

                </div>


                <!-- GUARDIAN NAME -->

                <div class="form-group">

                    <label>
                        Guardian Name
                    </label>


                    <input
                        type="text"
                        name="guardian_name"
                        maxlength="150"
                    >

                </div>


                <!-- GUARDIAN PHONE -->

                <div class="form-group">

                    <label>
                        Guardian Phone
                    </label>


                    <input
                        type="text"
                        name="guardian_phone"
                        maxlength="30"
                    >

                </div>


                <!-- REGISTRATION DATE -->

                <div class="form-group">

                    <label>

                        Registration Date

                        <span class="required">
                            *
                        </span>

                    </label>


                    <input
                        type="date"
                        name="registration_date"
                        value="<?= date('Y-m-d') ?>"
                        required
                    >

                </div>


                <!-- ADD BUTTON -->

                <button
                    type="submit"
                    name="add_student"
                    class="btn btn-primary"
                >
                    Add Student
                </button>


            </form>


            <!-- INFO -->

            <div class="info">

                Student ID will be generated automatically.

                Example:

                <strong>
                    ALW-2026-0001
                </strong>

            </div>

        </div>


        <!-- =========================
             STUDENT LIST
        ========================== -->

        <div class="card">

            <h3>
                Student List
            </h3>


            <!-- =========================
                 SEARCH & FILTER
            ========================== -->

            <div class="filter-box">

                <form
                    method="GET"
                    class="filter-form"
                >


                    <!-- SEARCH -->

                    <div class="filter-group">

                        <label>
                            Search
                        </label>


                        <input
                            type="text"
                            name="search"
                            placeholder="Student ID, name or phone..."
                            value="<?= htmlspecialchars($search) ?>"
                        >

                    </div>


                    <!-- GENDER -->

                    <div class="filter-group">

                        <label>
                            Gender
                        </label>


                        <select name="gender">

                            <option value="">
                                All Genders
                            </option>


                            <option
                                value="Male"
                                <?= $filterGender === 'Male'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Male
                            </option>


                            <option
                                value="Female"
                                <?= $filterGender === 'Female'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Female
                            </option>

                        </select>

                    </div>


                    <!-- STATUS -->

                    <div class="filter-group">

                        <label>
                            Status
                        </label>


                        <select name="status">

                            <option value="">
                                All Statuses
                            </option>


                            <option
                                value="active"
                                <?= $filterStatus === 'active'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Active
                            </option>


                            <option
                                value="suspended"
                                <?= $filterStatus === 'suspended'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Suspended
                            </option>


                            <option
                                value="inactive"
                                <?= $filterStatus === 'inactive'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Inactive
                            </option>


                            <option
                                value="graduated"
                                <?= $filterStatus === 'graduated'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Graduated
                            </option>

                        </select>

                    </div>


                    <!-- REGISTRATION DATE FROM -->

                    <div class="filter-group">

                        <label>
                            Registration From
                        </label>

                        <input
                            type="date"
                            name="date_from"
                            value="<?= htmlspecialchars($filterDateFrom) ?>"
                        >

                    </div>


                    <!-- REGISTRATION DATE TO -->

                    <div class="filter-group">

                        <label>
                            Registration To
                        </label>

                        <input
                            type="date"
                            name="date_to"
                            value="<?= htmlspecialchars($filterDateTo) ?>"
                        >

                    </div>


                    <!-- BUTTONS -->

                    <div class="filter-actions">

                        <button
                            type="submit"
                            class="btn-filter"
                        >
                            Search
                        </button>


                        <a
                            href="index.php"
                            class="btn-clear"
                        >
                            Clear
                        </a>

                    </div>


                </form>


                <!-- RESULT COUNT -->

                <div class="result-count">

                    Showing

                    <strong>
                        <?= $resultCount ?>
                    </strong>

                    student(s)

                </div>

            </div>


            <!-- =========================
                 STUDENT TABLE
            ========================== -->

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Student ID
                            </th>

                            <th>
                                Full Name
                            </th>

                            <th>
                                Gender
                            </th>

                            <th>
                                Phone
                            </th>

                            <th>
                                Registration Date
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (empty($students)): ?>

                        <tr>

                            <td
                                colspan="7"
                                class="empty"
                            >

                                <?php if (
                                    $search !== '' ||
                                    $filterGender !== '' ||
                                    $filterStatus !== '' ||
                                    $filterDateFrom !== '' ||
                                    $filterDateTo !== ''
                                ): ?>

                                    No students match your
                                    search or filters.

                                <?php else: ?>

                                    No students found.

                                <?php endif; ?>

                            </td>

                        </tr>


                    <?php else: ?>


                        <?php foreach ($students as $student): ?>

                            <tr>


                                <!-- STUDENT ID -->

                                <td>

                                    <span class="student-code">

                                        <?= htmlspecialchars(
                                            $student['student_code']
                                        ) ?>

                                    </span>

                                </td>


                                <!-- FULL NAME -->

                                <td>

                                    <?= htmlspecialchars(
                                        $student['full_name']
                                    ) ?>

                                </td>


                                <!-- GENDER -->

                                <td>

                                    <?= htmlspecialchars(
                                        $student['gender']
                                    ) ?>

                                </td>


                                <!-- PHONE -->

                                <td>

                                    <?= htmlspecialchars(
                                        $student['phone'] ?? '-'
                                    ) ?>

                                </td>


                                <!-- REGISTRATION DATE -->

                                <td>

                                    <?= htmlspecialchars(
                                        $student['registration_date']
                                    ) ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?php

                                    $status =
                                        $student['status'];

                                    $badgeClass =
                                        'badge-' . $status;

                                    ?>


                                    <span
                                        class="badge <?= htmlspecialchars(
                                            $badgeClass
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            ucfirst($status)
                                        ) ?>

                                    </span>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <a
                                        href="edit.php?id=<?= (int) $student['id'] ?>"
                                        class="btn-edit"
                                    >
                                        Edit
                                    </a>

                                </td>


                            </tr>

                        <?php endforeach; ?>


                    <?php endif; ?>


                    </tbody>

                </table>

            </div>

        </div>

    </div>

</main>


</body>

</html>