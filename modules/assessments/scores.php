<?php

session_start();

require_once '../../config/database.php';


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}


$role = $_SESSION['role'] ?? '';


/*
|--------------------------------------------------------------------------
| Instructor Only
|--------------------------------------------------------------------------
*/

if ($role !== 'instructor') {
    http_response_code(403);
    die('Access denied.');
}


$userId = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| Get Instructor
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
    FROM instructors
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

$instructor = $stmt->fetch();


if (!$instructor) {
    die('Instructor profile not found.');
}


$instructorId = (int) $instructor['id'];


/*
|--------------------------------------------------------------------------
| Get Assessment ID
|--------------------------------------------------------------------------
*/

$assessmentId = isset($_GET['assessment_id'])
    ? (int) $_GET['assessment_id']
    : 0;


if ($assessmentId <= 0) {
    die('Invalid assessment.');
}


/*
|--------------------------------------------------------------------------
| Get Assessment
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.batch_id,
        a.title,
        a.type,
        a.max_score,
        a.due_date,
        b.batch_code,
        c.course_name
    FROM assessments a

    INNER JOIN batches b
        ON b.id = a.batch_id

    INNER JOIN courses c
        ON c.id = b.course_id

    INNER JOIN batch_instructors bi
        ON bi.batch_id = b.id

    WHERE a.id = ?
    AND bi.instructor_id = ?

    LIMIT 1
");

$stmt->execute([
    $assessmentId,
    $instructorId
]);

$assessment = $stmt->fetch();


if (!$assessment) {
    http_response_code(403);
    die('Assessment not found or access denied.');
}


$batchId = (int) $assessment['batch_id'];


/*
|--------------------------------------------------------------------------
| Get Enrolled Students
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        e.id AS enrollment_id,
        s.student_code,
        s.full_name,
        s.gender,
        ass.id AS score_id,
        ass.score,
        ass.notes
    FROM enrollments e

    INNER JOIN students s
        ON s.id = e.student_id

    LEFT JOIN assessment_scores ass
        ON ass.enrollment_id = e.id
        AND ass.assessment_id = ?

    WHERE e.batch_id = ?

    AND e.status != 'cancelled'

    ORDER BY s.full_name ASC
");

$stmt->execute([
    $assessmentId,
    $batchId
]);

$students = $stmt->fetchAll();

?>


<?php require_once '../../includes/sidebar.php'; ?>


<div class="scores-page">


    <!-- =========================================================
         HEADER
    ========================================================== -->

    <div class="page-header">

        <div>

            <h1>
                <?= htmlspecialchars(
                    $assessment['title']
                ) ?>
            </h1>

            <p>

                <?= htmlspecialchars(
                    $assessment['batch_code']
                ) ?>

                -

                <?= htmlspecialchars(
                    $assessment['course_name']
                ) ?>

            </p>

        </div>


        <a
            href="index.php?batch_id=<?= $batchId ?>"
            class="btn-back"
        >
            ← Back to Assessments
        </a>

    </div>


    <!-- =========================================================
         ASSESSMENT INFORMATION
    ========================================================== -->

    <div class="info-card">

        <div class="info-item">

            <span class="info-label">
                Type
            </span>

            <strong>

                <?= ucfirst(
                    htmlspecialchars(
                        $assessment['type']
                    )
                ) ?>

            </strong>

        </div>


        <div class="info-item">

            <span class="info-label">
                Maximum Score
            </span>

            <strong>

                <?= number_format(
                    (float) $assessment['max_score'],
                    2
                ) ?>

            </strong>

        </div>


        <div class="info-item">

            <span class="info-label">
                Date
            </span>

            <strong>

                <?= $assessment['due_date']
                    ? htmlspecialchars(
                        $assessment['due_date']
                    )
                    : 'Not set'
                ?>

            </strong>

        </div>

    </div>


    <!-- =========================================================
         STUDENT SCORES
    ========================================================== -->

    <div class="card">

        <div class="card-header">

            <div>

                <h2>
                    Student Scores
                </h2>

                <p>
                    Enter the score for each enrolled student.
                </p>

            </div>

        </div>


        <?php if (empty($students)): ?>

            <div class="empty-state">

                <div class="empty-icon">
                    👨‍🎓
                </div>

                <h3>
                    No Students Found
                </h3>

                <p>
                    There are no enrolled students in this batch.
                </p>

            </div>

        <?php else: ?>


            <form
                method="POST"
                action="save_scores.php"
            >

                <input
                    type="hidden"
                    name="assessment_id"
                    value="<?= $assessmentId ?>"
                >


                <div class="table-wrapper">

                    <table class="scores-table">

                        <thead>

                            <tr>

                                <th width="60">
                                    #
                                </th>

                                <th>
                                    Student ID
                                </th>

                                <th>
                                    Student Name
                                </th>

                                <th>
                                    Gender
                                </th>

                                <th width="180">
                                    Score
                                </th>

                                <th>
                                    Notes
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach (
                                $students
                                as $index => $student
                            ): ?>

                                <tr>

                                    <td>
                                        <?= $index + 1 ?>
                                    </td>


                                    <td>

                                        <span class="student-code">

                                            <?= htmlspecialchars(
                                                $student['student_code']
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $student['full_name']
                                            ) ?>

                                        </strong>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $student['gender']
                                        ) ?>

                                    </td>


                                    <td>

                                        <div class="score-box">

                                            <input
                                                type="number"
                                                name="scores[<?= (int) $student['enrollment_id'] ?>]"
                                                value="<?= $student['score'] !== null
                                                    ? htmlspecialchars($student['score'])
                                                    : '' ?>"
                                                min="0"
                                                max="<?= htmlspecialchars($assessment['max_score']) ?>"
                                                step="0.01"
                                                placeholder="Enter score"
                                            >

                                            <span>
                                                /
                                                <?= number_format(
                                                    (float) $assessment['max_score'],
                                                    2
                                                ) ?>
                                            </span>

                                        </div>

                                    </td>


                                    <td>

                                        <input
                                            type="text"
                                            name="notes[<?= (int) $student['enrollment_id'] ?>]"
                                            value="<?= htmlspecialchars(
                                                $student['notes'] ?? ''
                                            ) ?>"
                                            class="notes-input"
                                            placeholder="Optional"
                                        >

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn-save"
                    >

                        Save All Scores

                    </button>

                </div>

            </form>

        <?php endif; ?>

    </div>

</div>


<style>

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

.scores-page {

    margin: 0 !important;

    padding: 20px;

    width: 100% !important;

    max-width: none !important;

    min-height: 100vh;

    box-sizing: border-box;

}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.page-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 20px;

}

.page-header h1 {

    margin: 0;

    font-size: 27px;

    color: #111827;

}

.page-header p {

    margin: 6px 0 0;

    color: #6b7280;

    font-size: 14px;

}


/*
|--------------------------------------------------------------------------
| BACK BUTTON
|--------------------------------------------------------------------------
*/

.btn-back {

    display: inline-block;

    padding: 10px 15px;

    background: #f3f4f6;

    color: #374151;

    text-decoration: none;

    border-radius: 6px;

    font-size: 13px;

    font-weight: 600;

}

.btn-back:hover {

    background: #e5e7eb;

}


/*
|--------------------------------------------------------------------------
| INFO CARD
|--------------------------------------------------------------------------
*/

.info-card {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;

    margin-bottom: 20px;

}

.info-item {

    background: #ffffff;

    border: 1px solid #e5e7eb;

    border-radius: 8px;

    padding: 15px;

}

.info-label {

    display: block;

    margin-bottom: 6px;

    color: #6b7280;

    font-size: 12px;

}


/*
|--------------------------------------------------------------------------
| CARD
|--------------------------------------------------------------------------
*/

.card {

    background: #ffffff;

    border: 1px solid #e5e7eb;

    border-radius: 10px;

    padding: 20px;

    box-shadow:
        0 1px 3px rgba(0,0,0,.05);

}


/*
|--------------------------------------------------------------------------
| CARD HEADER
|--------------------------------------------------------------------------
*/

.card-header {

    margin-bottom: 20px;

}

.card-header h2 {

    margin: 0;

    font-size: 18px;

}

.card-header p {

    margin: 5px 0 0;

    color: #6b7280;

    font-size: 13px;

}


/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.table-wrapper {

    width: 100%;

    overflow-x: auto;

    border: 1px solid #e5e7eb;

    border-radius: 8px;

}

.scores-table {

    width: 100%;

    border-collapse: collapse;

    min-width: 850px;

}

.scores-table th {

    background: #f9fafb;

    padding: 12px;

    text-align: left;

    font-size: 12px;

    color: #374151;

    border-bottom: 1px solid #e5e7eb;

}

.scores-table td {

    padding: 12px;

    font-size: 13px;

    border-bottom: 1px solid #f0f0f0;

}

.scores-table tr:last-child td {

    border-bottom: none;

}


/*
|--------------------------------------------------------------------------
| STUDENT CODE
|--------------------------------------------------------------------------
*/

.student-code {

    font-family: monospace;

    font-size: 12px;

    background: #f3f4f6;

    padding: 5px 7px;

    border-radius: 4px;

}


/*
|--------------------------------------------------------------------------
| SCORE
|--------------------------------------------------------------------------
*/

.score-box {

    display: flex;

    align-items: center;

    gap: 7px;

}

.score-box input {

    width: 100px;

    padding: 9px;

    border: 1px solid #d1d5db;

    border-radius: 6px;

    text-align: center;

    font-size: 13px;

}

.score-box span {

    color: #6b7280;

    font-size: 12px;

}


/*
|--------------------------------------------------------------------------
| NOTES
|--------------------------------------------------------------------------
*/

.notes-input {

    width: 100%;

    min-width: 180px;

    padding: 9px;

    border: 1px solid #d1d5db;

    border-radius: 6px;

    box-sizing: border-box;

}


/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/

.form-actions {

    display: flex;

    justify-content: flex-end;

    margin-top: 20px;

}

.btn-save {

    border: none;

    background: #16a34a;

    color: #ffffff;

    padding: 11px 20px;

    border-radius: 6px;

    font-size: 13px;

    font-weight: 600;

    cursor: pointer;

}

.btn-save:hover {

    background: #15803d;

}


/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.empty-state {

    text-align: center;

    padding: 50px 20px;

}

.empty-icon {

    font-size: 40px;

    margin-bottom: 10px;

}

.empty-state h3 {

    margin: 0 0 7px;

}

.empty-state p {

    margin: 0;

    color: #6b7280;

}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 700px) {

    .scores-page {

        padding: 15px !important;

    }

    .page-header {

        flex-direction: column;

        align-items: stretch;

    }

    .btn-back {

        text-align: center;

    }

    .info-card {

        grid-template-columns: 1fr;

    }

    .form-actions {

        justify-content: stretch;

    }

    .btn-save {

        width: 100%;

    }

}

</style>