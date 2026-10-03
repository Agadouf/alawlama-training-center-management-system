<?php
session_start();

require_once '../../config/database.php';
require_once '../../includes/language.php';

// ======================================================
// AUTHENTICATION
// ======================================================

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SESSION['role'] !== 'instructor') {
    http_response_code(403);
    die(__('access_denied'));
}

// ======================================================
// GET INSTRUCTOR
// ======================================================

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT id, full_name
    FROM instructors
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

$instructor = $stmt->fetch();

if (!$instructor) {
    die(__('instructor_profile_not_found'));
}

$instructorId = (int) $instructor['id'];

// ======================================================
// GET ASSIGNED BATCHES
// ======================================================

$stmt = $pdo->prepare("
    SELECT
        b.id,
        b.batch_code,
        b.start_date,
        b.end_date,
        c.course_name
    FROM batches b
    INNER JOIN courses c
        ON c.id = b.course_id
    INNER JOIN batch_instructors bi
        ON bi.batch_id = b.id
    WHERE bi.instructor_id = ?
    ORDER BY b.start_date DESC, b.batch_code ASC
");

$stmt->execute([$instructorId]);

$batches = $stmt->fetchAll();

// ======================================================
// SELECTED BATCH
// ======================================================

$selectedBatchId = isset($_GET['batch_id'])
    ? (int) $_GET['batch_id']
    : 0;

if ($selectedBatchId === 0 && !empty($batches)) {
    $selectedBatchId = (int) $batches[0]['id'];
}

// ======================================================
// VERIFY SELECTED BATCH
// ======================================================

$selectedBatch = null;

foreach ($batches as $batch) {

    if ((int) $batch['id'] === $selectedBatchId) {
        $selectedBatch = $batch;
        break;
    }
}

if ($selectedBatchId > 0 && !$selectedBatch) {
    http_response_code(403);
    die(__('batch_not_assigned'));
}

// ======================================================
// GET ASSESSMENTS
// ======================================================

$assignments = [];
$midterm = null;
$final = null;

if ($selectedBatchId > 0) {

    $stmt = $pdo->prepare("
        SELECT
            id,
            batch_id,
            title,
            type,
            max_score,
            due_date,
            description,
            status,
            created_by,
            created_at,
            updated_at
        FROM assessments
        WHERE batch_id = ?
        ORDER BY
            CASE
                WHEN type = 'assignment' THEN 1
                WHEN type = 'midterm' THEN 2
                WHEN type = 'final' THEN 3
            END,
            due_date ASC,
            id ASC
    ");

    $stmt->execute([$selectedBatchId]);

    $assessmentRows = $stmt->fetchAll();

    foreach ($assessmentRows as $assessment) {

        if ($assessment['type'] === 'assignment') {
            $assignments[] = $assessment;
        }

        if ($assessment['type'] === 'midterm') {
            $midterm = $assessment;
        }

        if ($assessment['type'] === 'final') {
            $final = $assessment;
        }
    }
}

// ======================================================
// MESSAGE
// ======================================================

$message = isset($_GET['message'])
    ? trim($_GET['message'])
    : '';

$messageType = isset($_GET['type'])
    ? trim($_GET['type'])
    : 'success';

?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($dir) ?>">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars(__('assessments')) ?> - ALAWALAMA TRAINING CENTER
    </title>

    <style>

        /* ==================================================
           GENERAL
        ================================================== */

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }

        html[dir="rtl"],
        html[dir="rtl"] body {
            direction: rtl;
        }

        html[dir="rtl"] .page-header,
        html[dir="rtl"] .card,
        html[dir="rtl"] .batch-info,
        html[dir="rtl"] .no-batch {
            text-align: right;
        }

        html[dir="rtl"] .section-header,
        html[dir="rtl"] .section-buttons,
        html[dir="rtl"] .form-actions {
            direction: rtl;
        }

        .main-page {
            margin: 0 !important;
            width: 100% !important;
            min-height: 100vh;
            padding: 30px;
        }

        /* ==================================================
           PAGE HEADER
        ================================================== */

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin: 0 0 8px;
            font-size: 28px;
            color: #111827;
        }

        .page-header p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }

        /* ==================================================
           ALERT
        ================================================== */

        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* ==================================================
           CARD
        ================================================== */

        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 22px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .card-title {
            margin: 0 0 18px;
            font-size: 18px;
            font-weight: 700;
            color: #111827;
        }

        /* ==================================================
           BATCH SELECTOR
        ================================================== */

        .batch-form {
            display: flex;
            align-items: flex-end;
            gap: 15px;
        }

        .form-group {
            flex: 1;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
        }

        select,
        input[type="text"],
        input[type="number"],
        input[type="date"],
        textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            background: #ffffff;
        }

        select:focus,
        input:focus,
        textarea:focus {
            outline: none;
            border-color: #2563eb;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        /* ==================================================
           GENERAL BUTTON
        ================================================== */

        .btn {
            display: inline-block;
            border: none;
            cursor: pointer;
            text-decoration: none;
            border-radius: 6px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 600;
        }

        .btn-primary {
            background: #2563eb;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        /* ==================================================
           BATCH INFORMATION
        ================================================== */

        .batch-info {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .info-box {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
        }

        .info-label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 6px;
        }

        .info-value {
            font-size: 15px;
            font-weight: 700;
            color: #111827;
        }

        /* ==================================================
           SECTION HEADER
        ================================================== */

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 18px;
        }

        .section-header h2 {
            margin: 0;
            font-size: 20px;
            color: #111827;
        }

        .section-header p {
            margin: 5px 0 0;
            font-size: 13px;
            color: #6b7280;
        }

        .section-buttons {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* ==================================================
           ADD ASSIGNMENT
        ================================================== */

        .btn-add {
            display: inline-block;
            border: none;
            background: #6b7280;
            color: #ffffff;
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-add:hover {
            background: #4b5563;
        }

        /* ==================================================
           FINAL RESULTS BUTTON
        ================================================== */

        .btn-results {
            display: inline-block;
            background: #7c3aed;
            color: #ffffff;
            text-decoration: none;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
        }

        .btn-results:hover {
            background: #6d28d9;
        }

        /* ==================================================
           ASSIGNMENT LIST
        ================================================== */

        .assignment-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .assignment-row {
            display: grid;

            grid-template-columns:
                minmax(220px, 2fr)
                120px
                150px
                minmax(200px, 2fr)
                130px;

            gap: 12px;
            align-items: end;

            padding: 18px;

            background: #f9fafb;

            border: 1px solid #e5e7eb;

            border-radius: 8px;
        }

        .assignment-field label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #374151;
        }

        /* ==================================================
           ASSIGNMENT ACTIONS
        ================================================== */

        .assignment-actions {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        /* ==================================================
           SAVE BUTTON
        ================================================== */

        .btn-save {
            display: inline-block;
            border: none;
            background: #16a34a;
            color: #ffffff;
            text-decoration: none;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            white-space: nowrap;
        }

        .btn-save:hover {
            background: #15803d;
        }

        /* ==================================================
           ENTER SCORES
        ================================================== */

        .btn-scores {
            display: inline-block;
            background: #2563eb;
            color: #ffffff;
            text-decoration: none;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
        }

        .btn-scores:hover {
            background: #1d4ed8;
        }

        /* ==================================================
           REMOVE
        ================================================== */

        .btn-remove {
            display: inline-block;
            border: none;
            background: #dc2626;
            color: #ffffff;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
        }

        .btn-remove:hover {
            background: #b91c1c;
        }

        /* ==================================================
           FORM ACTIONS
        ================================================== */

        .form-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
        }

        /* ==================================================
           EXAM SECTION
        ================================================== */

        .exam-section {
            margin-top: 25px;
        }

        .exam-form {
            display: grid;
            grid-template-columns: 2fr 150px 180px;
            gap: 15px;
            align-items: end;
        }

        .exam-description {
            margin-top: 15px;
        }

        /* ==================================================
           EMPTY STATE
        ================================================== */

        .empty-state {
            padding: 30px;
            text-align: center;
            color: #6b7280;
            background: #f9fafb;
            border: 1px dashed #d1d5db;
            border-radius: 8px;
            font-size: 14px;
        }

        /* ==================================================
           NO BATCH
        ================================================== */

        .no-batch {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 50px 30px;
            text-align: center;
        }

        .no-batch h2 {
            margin: 0 0 10px;
            color: #111827;
        }

        .no-batch p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }

        /* ==================================================
           RESPONSIVE
        ================================================== */

        @media (max-width: 1100px) {

            .assignment-row {
                grid-template-columns: 1fr 1fr;
            }

            .assignment-actions {
                flex-direction: row;
            }

            .exam-form {
                grid-template-columns: 1fr 1fr;
            }

        }

        @media (max-width: 900px) {

            .batch-info {
                grid-template-columns: 1fr 1fr;
            }

        }

        @media (max-width: 700px) {

            .main-page {
                padding: 20px;
            }

            .batch-form {
                flex-direction: column;
                align-items: stretch;
            }

            .batch-info {
                grid-template-columns: 1fr;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .section-buttons {
                width: 100%;
            }

            .assignment-row {
                grid-template-columns: 1fr;
            }

            .assignment-actions {
                flex-direction: row;
            }

            .exam-form {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<?php require_once '../../includes/sidebar.php'; ?>

<div class="main-page">

    <!-- ==================================================
         PAGE HEADER
    ================================================== -->

    <div class="page-header">

        <h1>
            <?= __('assessments') ?>
        </h1>

        <p>
            <?= __('assessments_page_description') ?>
        </p>

    </div>


    <!-- ==================================================
         MESSAGE
    ================================================== -->

    <?php if ($message !== ''): ?>

        <div class="alert
            <?= $messageType === 'error'
                ? 'alert-error'
                : 'alert-success'
            ?>">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <!-- ==================================================
         BATCH SELECTOR
    ================================================== -->

    <div class="card">

        <h2 class="card-title">
            <?= __('select_batch') ?>
        </h2>

        <?php if (!empty($batches)): ?>

            <form
                method="GET"
                class="batch-form"
            >

                <div class="form-group">

                    <label for="batch_id">
                        <?= __('batch') ?>
                    </label>

                    <select
                        name="batch_id"
                        id="batch_id"
                        onchange="this.form.submit()"
                    >

                        <?php foreach ($batches as $batch): ?>

                            <option
                                value="<?= (int) $batch['id'] ?>"
                                <?= (int) $batch['id'] === $selectedBatchId
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= htmlspecialchars(
                                    $batch['batch_code']
                                ) ?>

                                -

                                <?= htmlspecialchars(
                                    $batch['course_name']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    <?= __('load_batch') ?>
                </button>

            </form>

        <?php else: ?>

            <div class="empty-state">

                <?= __('no_batches') ?>

            </div>

        <?php endif; ?>

    </div>


    <?php if ($selectedBatch): ?>

        <!-- ==================================================
             BATCH INFORMATION
        ================================================== -->

        <div class="batch-info">

            <div class="info-box">

                <div class="info-label">
                    Batch
                </div>

                <div class="info-value">
                    <?= htmlspecialchars(
                        $selectedBatch['batch_code']
                    ) ?>
                </div>

            </div>


            <div class="info-box">

                <div class="info-label">
                    <?= __('course') ?>
                </div>

                <div class="info-value">
                    <?= htmlspecialchars(
                        $selectedBatch['course_name']
                    ) ?>
                </div>

            </div>


            <div class="info-box">

                <div class="info-label">
                    <?= __('start_date') ?>
                </div>

                <div class="info-value">
                    <?= htmlspecialchars(
                        $selectedBatch['start_date']
                    ) ?>
                </div>

            </div>


            <div class="info-box">

                <div class="info-label">
                    <?= __('end_date') ?>
                </div>

                <div class="info-value">
                    <?= htmlspecialchars(
                        $selectedBatch['end_date']
                    ) ?>
                </div>

            </div>

        </div>


        <!-- ==================================================
             ASSIGNMENTS
        ================================================== -->

        <div class="card">

            <div class="section-header">

                <div>

                    <h2>
                        <?= __('assignments') ?>
                    </h2>

                    <p>
                        <?= __('assignments_batch_description') ?>
                    </p>

                </div>


                <div class="section-buttons">

                    <!-- FINAL RESULTS -->

                    <a
                        href="results.php?batch_id=<?= $selectedBatchId ?>"
                        class="btn-results"
                    >
                        📊 <?= __('final_results') ?>
                    </a>


                    <!-- ADD ASSIGNMENT -->

                    <button
                        type="button"
                        class="btn-add"
                        onclick="addAssignment()"
                    >
                        + <?= __('add_assignment') ?>
                    </button>

                </div>

            </div>


            <form
                method="POST"
                action="save.php"
                id="assignmentsForm"
            >

                <input
                    type="hidden"
                    name="action"
                    value="save"
                >

                <input
                    type="hidden"
                    name="batch_id"
                    value="<?= $selectedBatchId ?>"
                >


                <div
                    id="assignmentList"
                    class="assignment-list"
                >

                    <?php if (!empty($assignments)): ?>

                        <?php foreach ($assignments as $index => $assignment): ?>

                            <div class="assignment-row">

                                <!-- TITLE -->

                                <div class="assignment-field">

                                    <label>
                                        <?= __('assignment_title') ?>
                                    </label>

                                    <input
                                        type="text"
                                        name="assignments[<?= $index ?>][title]"
                                        value="<?= htmlspecialchars(
                                            $assignment['title']
                                        ) ?>"
                                        placeholder="<?= htmlspecialchars(__('assignment_title')) ?> 1"
                                        required
                                    >

                                    <input
                                        type="hidden"
                                        name="assignments[<?= $index ?>][id]"
                                        value="<?= (int) $assignment['id'] ?>"
                                    >

                                </div>


                                <!-- MAX SCORE -->

                                <div class="assignment-field">

                                    <label>
                                        <?= __('max_score_short') ?>
                                    </label>

                                    <input
                                        type="number"
                                        name="assignments[<?= $index ?>][max_score]"
                                        value="<?= htmlspecialchars(
                                            $assignment['max_score']
                                        ) ?>"
                                        min="0.01"
                                        step="0.01"
                                        required
                                    >

                                </div>


                                <!-- DUE DATE -->

                                <div class="assignment-field">

                                    <label>
                                        <?= __('due_date') ?>
                                    </label>

                                    <input
                                        type="date"
                                        name="assignments[<?= $index ?>][due_date]"
                                        value="<?= htmlspecialchars(
                                            $assignment['due_date'] ?? ''
                                        ) ?>"
                                    >

                                </div>


                                <!-- DESCRIPTION -->

                                <div class="assignment-field">

                                    <label>
                                        <?= __('description') ?>
                                    </label>

                                    <input
                                        type="text"
                                        name="assignments[<?= $index ?>][description]"
                                        value="<?= htmlspecialchars(
                                            $assignment['description'] ?? ''
                                        ) ?>"
                                        placeholder="<?= htmlspecialchars(__('optional_description')) ?>"
                                    >

                                </div>


                                <!-- ACTIONS -->

                                <div class="assignment-actions">

                                    <a
                                        href="scores.php?assessment_id=<?= (int) $assignment['id'] ?>"
                                        class="btn-scores"
                                    >
                                        <?= __('enter_scores') ?>
                                    </a>

                                    <button
                                        type="submit"
                                        name="assessment_id"
                                        value="<?= (int) $assignment['id'] ?>"
                                        formaction="delete.php"
                                        formmethod="POST"
                                        class="btn-remove"
                                        onclick="return confirm(
                                            <?= json_encode(__('remove_assignment_confirm') . "\n\n" . __('delete_assignment_warning'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
                                        );"
                                    >
                                        <?= __('remove') ?>
                                    </button>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div
                            class="empty-state"
                            id="emptyAssignments"
                        >

                            <?= __('no_assignments') ?>

                            <br><br>

                            Click
                            <strong>+ <?= __('add_assignment') ?></strong>
                            to create one.

                        </div>

                    <?php endif; ?>

                </div>


                <!-- SAVE ASSIGNMENTS -->

                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn-save"
                    >
                        <?= __('save_all_assignments') ?>
                    </button>

                </div>

            </form>

        </div>


        <!-- ==================================================
             MIDTERM
        ================================================== -->

        <div class="card exam-section">

            <div class="section-header">

                <div>

                    <h2>
                        <?= __('midterm_exam') ?>
                    </h2>

                    <p>
                        <?= __('midterm_batch_description') ?>
                    </p>

                </div>

            </div>


            <form
                method="POST"
                action="save.php"
            >

                <input
                    type="hidden"
                    name="action"
                    value="save_single"
                >

                <input
                    type="hidden"
                    name="batch_id"
                    value="<?= $selectedBatchId ?>"
                >

                <input
                    type="hidden"
                    name="type"
                    value="midterm"
                >

                <?php if ($midterm): ?>

                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int) $midterm['id'] ?>"
                    >

                <?php endif; ?>


                <div class="exam-form">

                    <div class="assignment-field">

                        <label>
                            <?= __('title') ?>
                        </label>

                        <input
                            type="text"
                            name="title"
                            value="<?= htmlspecialchars(
                                $midterm['title']
                                ?? __('midterm_exam')
                            ) ?>"
                            required
                        >

                    </div>


                    <div class="assignment-field">

                        <label>
                            <?= __('max_score_short') ?>
                        </label>

                        <input
                            type="number"
                            name="max_score"
                            value="<?= htmlspecialchars(
                                $midterm['max_score']
                                ?? '100'
                            ) ?>"
                            min="0.01"
                            step="0.01"
                            required
                        >

                    </div>


                    <div class="assignment-field">

                        <label>
                            <?= __('exam_date') ?>
                        </label>

                        <input
                            type="date"
                            name="due_date"
                            value="<?= htmlspecialchars(
                                $midterm['due_date']
                                ?? ''
                            ) ?>"
                        >

                    </div>

                </div>


                <div class="assignment-field exam-description">

                    <label>
                        Description
                    </label>

                    <textarea
                        name="description"
                        placeholder="Optional description"
                    ><?= htmlspecialchars(
                        $midterm['description'] ?? ''
                    ) ?></textarea>

                </div>


                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn-save"
                    >
                        <?= __('save_midterm') ?>
                    </button>


                    <?php if ($midterm): ?>

                        <a
                            href="scores.php?assessment_id=<?= (int) $midterm['id'] ?>"
                            class="btn-scores"
                        >
                            Enter Scores
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </div>


        <!-- ==================================================
             FINAL EXAM
        ================================================== -->

        <div class="card exam-section">

            <div class="section-header">

                <div>

                    <h2>
                        <?= __('final_exam') ?>
                    </h2>

                    <p>
                        <?= __('final_batch_description') ?>
                    </p>

                </div>

            </div>


            <form
                method="POST"
                action="save.php"
            >

                <input
                    type="hidden"
                    name="action"
                    value="save_single"
                >

                <input
                    type="hidden"
                    name="batch_id"
                    value="<?= $selectedBatchId ?>"
                >

                <input
                    type="hidden"
                    name="type"
                    value="final"
                >

                <?php if ($final): ?>

                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int) $final['id'] ?>"
                    >

                <?php endif; ?>


                <div class="exam-form">

                    <div class="assignment-field">

                        <label>
                            <?= __('title') ?>
                        </label>

                        <input
                            type="text"
                            name="title"
                            value="<?= htmlspecialchars(
                                $final['title']
                                ?? __('final_exam')
                            ) ?>"
                            required
                        >

                    </div>


                    <div class="assignment-field">

                        <label>
                            <?= __('max_score_short') ?>
                        </label>

                        <input
                            type="number"
                            name="max_score"
                            value="<?= htmlspecialchars(
                                $final['max_score']
                                ?? '100'
                            ) ?>"
                            min="0.01"
                            step="0.01"
                            required
                        >

                    </div>


                    <div class="assignment-field">

                        <label>
                            <?= __('exam_date') ?>
                        </label>

                        <input
                            type="date"
                            name="due_date"
                            value="<?= htmlspecialchars(
                                $final['due_date']
                                ?? ''
                            ) ?>"
                        >

                    </div>

                </div>


                <div class="assignment-field exam-description">

                    <label>
                        Description
                    </label>

                    <textarea
                        name="description"
                        placeholder="Optional description"
                    ><?= htmlspecialchars(
                        $final['description'] ?? ''
                    ) ?></textarea>

                </div>


                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn-save"
                    >
                        <?= __('save_final') ?>
                    </button>


                    <?php if ($final): ?>

                        <a
                            href="scores.php?assessment_id=<?= (int) $final['id'] ?>"
                            class="btn-scores"
                        >
                            Enter Scores
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </div>

    <?php elseif (empty($batches)): ?>

        <!-- ==================================================
             NO BATCH
        ================================================== -->

        <div class="no-batch">

            <h2>
                <?= __('no_assigned_batches_title') ?>
            </h2>

            <p>
                <?= __('no_assigned_batches_description') ?>
            </p>

        </div>

    <?php endif; ?>

</div>


<!-- ======================================================
     JAVASCRIPT
====================================================== -->

<script>

const I18N = <?= json_encode([
    'assignment_title' => __('assignment_title'),
    'max_score' => __('max_score_short'),
    'due_date' => __('due_date'),
    'description' => __('description'),
    'optional_description' => __('optional_description'),
    'remove' => __('remove'),
    'no_assignments' => __('no_assignments'),
    'add_assignment' => __('add_assignment'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

let assignmentIndex = <?= count($assignments) ?>;

/**
 * Add a new assignment row.
 */
function addAssignment() {

    const list = document.getElementById('assignmentList');
    const empty = document.getElementById('emptyAssignments');

    if (empty) {
        empty.remove();
    }

    const row = document.createElement('div');
    row.className = 'assignment-row';

    row.innerHTML = `
        <div class="assignment-field">
            <label>${escapeHtml(I18N.assignment_title)}</label>
            <input
                type="text"
                name="assignments[${assignmentIndex}][title]"
                placeholder="${escapeHtml(I18N.assignment_title)} ${assignmentIndex + 1}"
                required
            >
        </div>

        <div class="assignment-field">
            <label>${escapeHtml(I18N.max_score)}</label>
            <input
                type="number"
                name="assignments[${assignmentIndex}][max_score]"
                value="100"
                min="0.01"
                step="0.01"
                required
            >
        </div>

        <div class="assignment-field">
            <label>${escapeHtml(I18N.due_date)}</label>
            <input
                type="date"
                name="assignments[${assignmentIndex}][due_date]"
            >
        </div>

        <div class="assignment-field">
            <label>${escapeHtml(I18N.description)}</label>
            <input
                type="text"
                name="assignments[${assignmentIndex}][description]"
                placeholder="${escapeHtml(I18N.optional_description)}"
            >
        </div>

        <div class="assignment-actions">
            <button
                type="button"
                class="btn-remove"
                onclick="removeAssignment(this)"
            >
                ${escapeHtml(I18N.remove)}
            </button>
        </div>
    `;

    list.appendChild(row);
    assignmentIndex++;
}

/**
 * Remove an assignment row from the current form.
 */
function removeAssignment(button) {

    const row = button.closest('.assignment-row');

    if (!row) {
        return;
    }

    row.remove();

    const list = document.getElementById('assignmentList');

    if (list.children.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'empty-state';
        empty.id = 'emptyAssignments';
        empty.innerHTML = `
            ${escapeHtml(I18N.no_assignments)}
            <br><br>
            <span>${escapeHtml(I18N.add_assignment)}</span>
        `;
        list.appendChild(empty);
    }
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

</script>

</body>

</html>