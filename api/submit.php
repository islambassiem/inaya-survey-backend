<?php

declare(strict_types=1);

require_once __DIR__ . "/bootstrap.php";


// =====================================================
// 3. VALIDATE ORIGIN
// =====================================================

if (!in_array($origin, $allowedOrigins, true)) {
    respond(403, [
        "success" => false,
        "code" => "origin_not_allowed"
    ]);
}

// =====================================================
// 4. HANDLE PREFLIGHT REQUEST
// =====================================================

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}


// =====================================================
// 5. VALIDATE REQUEST METHOD
// =====================================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, [
        "success" => false,
        "code" => "method_not_allowed"
    ]);
}

// =====================================================
// 6. VALIDATE CONTENT TYPE
// =====================================================

$contentType = $_SERVER["CONTENT_TYPE"] ?? "";

if (stripos($contentType, "application/json") !== 0) {
    respond(415, [
        "success" => false,
        "code" => "invalid_content_type"
    ]);
}

// =====================================================
// 7. VALIDATE REQUEST SIZE
// =====================================================

$maxRequestSize = 50000;

$contentLength = (int)($_SERVER["CONTENT_LENGTH"] ?? 0);

if ($contentLength > $maxRequestSize) {
    respond(413, [
        "success" => false,
        "code" => "request_too_large"
    ]);
}

$rawInput = file_get_contents("php://input");

if (
    $rawInput === false ||
    strlen($rawInput) > $maxRequestSize
) {
    respond(413, [
        "success" => false,
        "code" => "request_too_large"
    ]);
}

// =====================================================
// 8. PARSE JSON REQUEST
// =====================================================

$rawInput = file_get_contents("php://input");

try {
    $input = json_decode(
        $rawInput,
        true,
        512,
        JSON_THROW_ON_ERROR
    );
} catch (\JsonException $e) {
    respond(400, [
        "success" => false,
        "code" => "invalid_json"
    ]);
}

if (!is_array($input)) {
    respond(400, [
        "success" => false,
        "code" => "invalid_request"
    ]);
}

// =====================================================
// 9. VALIDATE LANGUAGE
// =====================================================

$language = $input["language"] ?? "";

if (!in_array($language, ["en", "ar"], true)) {
    respond(422, [
        "success" => false,
        "code" => "invalid_language"
    ]);
}

// =====================================================
// 10. VALIDATE ANSWERS STRUCTURE
// =====================================================

$answers = $input["answers"] ?? null;

if (!is_array($answers) || !array_is_list($answers)) {
    respond(422, [
        "success" => false,
        "code" => "invalid_answers"
    ]);
}

if (count($answers) === 0) {
    respond(422, [
        "success" => false,
        "code" => "incomplete_answers"
    ]);
}

// =====================================================
// 11. RETRIEVE ACTIVE QUESTIONS
// =====================================================

require_once __DIR__ . "/../config/database.php";

$catalogStmt = $pdo->query(
    "SELECT
        id,
        question_type,
        question_en,
        question_ar
     FROM survey_questions
     WHERE is_active = 1"
);

$catalogRows = $catalogStmt->fetchAll(PDO::FETCH_ASSOC);

$expectedQuestions = [];

foreach ($catalogRows as $question) {

    $questionId = (int) $question["id"];

    $expectedQuestions[$questionId] = $question;
}

if (count($answers) !== count($expectedQuestions)) {
    respond(422, [
        "success" => false,
        "code" => "incomplete_answers"
    ]);
}

$allowedValues = [0, 1, 2, 3, 4, 5];

// =====================================================
// 12. VALIDATE ALL ANSWERS
// =====================================================

$validatedAnswers = [];

foreach ($answers as $answer) {

    if (!is_array($answer)) {
        respond(422, [
            "success" => false,
            "code" => "invalid_answer_structure"
        ]);
    }

    $questionId = $answer["questionId"] ?? null;

    if (
        !is_int($questionId) ||
        !isset($expectedQuestions[$questionId])
    ) {
        respond(422, [
            "success" => false,
            "code" => "invalid_question_id"
        ]);
    }

    if (isset($validatedAnswers[$questionId])) {
        respond(422, [
            "success" => false,
            "code" => "duplicate_question"
        ]);
    }

    $expectedType = $expectedQuestions[$questionId]["question_type"];

    $answerValue = $answer["answerValue"] ?? null;
    $answerText = $answer["answerText"] ?? null;
    $comment = $answer["comment"] ?? null;

    // ---------------------------------------------
    // Validate Likert questions
    // ---------------------------------------------

    if ($expectedType === "likert") {

        if (
            !is_int($answerValue) ||
            !in_array($answerValue, $allowedValues, true)
        ) {
            respond(422, [
                "success" => false,
                "code" => "invalid_likert_answer"
            ]);
        }

        if ($answerText !== null) {
            respond(422, [
                "success" => false,
                "code" => "invalid_answer_text"
            ]);
        }

        if (
            $comment !== null &&
            !is_string($comment)
        ) {
            respond(422, [
                "success" => false,
                "code" => "invalid_comment"
            ]);
        }

        if (
            is_string($comment) &&
            mb_strlen($comment, "UTF-8") > 2000
        ) {
            respond(422, [
                "success" => false,
                "code" => "comment_too_long"
            ]);
        }

        $validatedAnswers[$questionId] = [
            "questionId" => $questionId,
            "answerValue" => $answerValue,
            "answerText" => null,
            "comment" => $comment !== null
                ? trim($comment)
                : null
        ];
    }

    // ---------------------------------------------
    // Validate open-text questions
    // ---------------------------------------------

    if ($expectedType === "text") {

        if ($answerValue !== null) {
            respond(422, [
                "success" => false,
                "code" => "invalid_text_question"
            ]);
        }

        if ($comment !== null) {
            respond(422, [
                "success" => false,
                "code" => "invalid_text_comment"
            ]);
        }

        if (
            $answerText !== null &&
            !is_string($answerText)
        ) {
            respond(422, [
                "success" => false,
                "code" => "invalid_answer_text"
            ]);
        }

        if (
            is_string($answerText) &&
            mb_strlen($answerText, "UTF-8") > 5000
        ) {
            respond(422, [
                "success" => false,
                "code" => "answer_too_long"
            ]);
        }

        $validatedAnswers[$questionId] = [
            "questionId" => $questionId,
            "answerValue" => null,
            "answerText" => $answerText !== null
                ? trim($answerText)
                : null,
            "comment" => null
        ];
    }
}

// Ensure all 27 questions are included.

if (count($validatedAnswers) !== 27) {
    respond(422, [
        "success" => false,
        "code" => "incomplete_answers"
    ]);
}

// =====================================================
// 13. DATABASE CONNECTION
// =====================================================

require_once __DIR__ . "/../config/database.php";

// =====================================================
// 14. SAVE RESPONSES IN A TRANSACTION
// =====================================================

try {

    $pdo->beginTransaction();

    // Generate a unique submission ID.
    $bytes = random_bytes(16);

    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    $hex = bin2hex($bytes);

    $submissionId = sprintf(
        "%s-%s-%s-%s-%s",
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );

    // Retrieve question snapshots from the catalog.
    $questionStmt = $pdo->prepare(
        "SELECT
            id,
            question_type,
            question_en,
            question_ar
         FROM survey_questions
         WHERE id = :id
           AND is_active = 1"
    );

    // Prepare insertion statement.
    $insertStmt = $pdo->prepare(
        "INSERT INTO survey_responses (
            submission_id,
            question_id,
            question_type,
            question_en,
            question_ar,
            answer_value,
            answer_text,
            comment,
            language
        ) VALUES (
            :submission_id,
            :question_id,
            :question_type,
            :question_en,
            :question_ar,
            :answer_value,
            :answer_text,
            :comment,
            :language
        )"
    );

    // Insert all 27 answers.
    foreach ($validatedAnswers as $answer) {

        $question = $expectedQuestions[$answer["questionId"]] ?? null;

        if (!$question) {
            throw new RuntimeException("Question catalog validation failed.");
        }

        if (!$question) {
            throw new RuntimeException(
                "Question catalog validation failed."
            );
        }

        $isLikert = $question["question_type"] === "likert";

        $insertStmt->execute([
            ":submission_id" => $submissionId,

            ":question_id" => $question["id"],

            ":question_type" => $question["question_type"],

            ":question_en" => $question["question_en"],

            ":question_ar" => $question["question_ar"],

            ":answer_value" => $isLikert
                ? $answer["answerValue"]
                : null,

            ":answer_text" => $isLikert
                ? null
                : $answer["answerText"],

            ":comment" => $isLikert
                ? $answer["comment"]
                : null,

            ":language" => $language
        ]);
    }

    // All records saved successfully.
    $pdo->commit();

    respond(201, [
        "success" => true,
        "message" => "Survey submitted successfully."
    ]);

// } catch (Throwable $e) {

//     if ($pdo->inTransaction()) {
//         $pdo->rollBack();
//     }

//     error_log(
//         "Survey submission failed: " . $e->getMessage()
//     );

//     respond(500, [
//         "success" => false,
//         "code" => "submission_failed"
//     ]);
// }
} catch (Throwable $e) {

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("==================================");
    error_log("SURVEY SUBMISSION ERROR");
    error_log("Message: " . $e->getMessage());
    error_log("File: " . $e->getFile());
    error_log("Line: " . $e->getLine());
    error_log("==================================");

    respond(500, [
        "success" => false,
        "code" => "submission_failed"
    ]);
}
