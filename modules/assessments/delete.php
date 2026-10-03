<?php

session_start();

require_once '../../config/database.php';

// ======================================================
// AUTHENTICATION
// ======================================================

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SESSION['role'] !== 'instructor') {
    http_response_code(403);
    die('Access denied.');
}

// ======================================================
// ONLY POST REQUEST
// ======================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed.');
}

// ======================================================
// GET USER
// ======================================================

$userId = (int) $_SESSION['user_id'];

// ======================================================
// GET INSTRUCTOR
// ======================================================

$stmt = $pdo->prepare("
    SELECT id
    FROM instructors
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

$instructor = $stmt->fetch();

if (!$instructor) {
    http_response_code(403);
    die('Instructor profile not found.');
}

$instructorId = (int) $instructor['id'];

// ======================================================
// GET ASSESSMENT ID
// ======================================================

$assessmentId = isset($_POST['assessment_id'])
    ? (int) $_POST['assessment_id']
    : 0;

$batchId = isset($_POST['batch_id'])
    ? (int) $_POST['batch_id']
    : 0;

if ($assessmentId <= 0) {
    die('Invalid assessment ID.');
}

// ======================================================
// FIND ASSESSMENT
// ======================================================

$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.batch_id,
        a.title,
        a.type
    FROM assessments a
    INNER JOIN batch_instructors bi
        ON bi.batch_id = a.batch_id
    WHERE
        a.id = ?
        AND bi.instructor_id = ?
    LIMIT 1
");

$stmt->execute([
    $assessmentId,
    $instructorId
]);

$assessment = $stmt->fetch();

if (!$assessment) {
    die(
        'Assessment not found or you are not assigned to this batch.'
    );
}

// ======================================================
// ONLY ASSIGNMENT
// ======================================================

if ($assessment['type'] !== 'assignment') {
    die('Only assignments can be removed.');
}

// Use the actual batch ID from the database.
$realBatchId = (int) $assessment['batch_id'];

// ======================================================
// DELETE
// ======================================================

try {

    $pdo->beginTransaction();

    // --------------------------------------------------
    // Delete student scores first
    // --------------------------------------------------

    $stmt = $pdo->prepare("
        DELETE FROM assessment_scores
        WHERE assessment_id = ?
    ");

    $stmt->execute([
        $assessmentId
    ]);

    // --------------------------------------------------
    // Delete assessment
    // --------------------------------------------------

    $stmt = $pdo->prepare("
        DELETE FROM assessments
        WHERE id = ?
    ");

    $stmt->execute([
        $assessmentId
    ]);

    // --------------------------------------------------
    // Verify DELETE
    // --------------------------------------------------

    if ($stmt->rowCount() !== 1) {

        $pdo->rollBack();

        die(
            'ERROR: Assignment was not deleted from the database.'
        );
    }

    // --------------------------------------------------
    // Commit
    // --------------------------------------------------

    $pdo->commit();

    // ==================================================
    // SUCCESS
    // ==================================================

    header(
        'Location: index.php?batch_id=' .
        $realBatchId .
        '&message=Assignment deleted successfully&type=success'
    );

    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Show the real error while testing.
    die(
        'DATABASE ERROR: ' .
        htmlspecialchars($e->getMessage())
    );
}