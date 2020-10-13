<?php
require_once 'config.php';
session_name(SESSION_NAME);
session_start();
setlocale(LC_ALL, 'it_IT.utf8');

$debug = isset($_GET[DEBUG_KEY]);
if ($debug) {
    $debug_param = DEBUG_KEY . '=' . urlencode($_GET[DEBUG_KEY]);
    $uid = $_GET[DEBUG_KEY];
} else {
    $debug_param = '';
    $uid = $_SERVER['uid'];
}
$debug_visual = $debug && isset($_GET[DEBUG_VISUAL_KEY]);

$dbh_zeus = new PDO(DB_DSN_ZEUS, DB_USER_ZEUS, DB_PASSWORD_ZEUS);
$dbh_prenotazione = new  PDO(DB_DSN_PRENOTAZIONI, DB_USER_PRENOTAZIONI, DB_PASSWORD_PRENOTAZIONI, [  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]);

$zeus_user_stmt = $dbh_zeus -> prepare("SELECT * FROM studenti_2020 WHERE PERS_ID=?");

function error_handler ($errno , $errstr, $errfile, $errline, array $errcontext) {
    header('Location: '.BASE.'/error.php');
}

if (! $debug) { 
   set_error_handler ('error_handler');
}

function datetime_displayname($dateStr) {
    $date = new DateTime($dateStr);
    $date->add(new DateInterval("PT1H"));
    return strftime("%a %e/%m/%Y %H:%M", $date->getTimestamp());
}

function time_displayname($dateStr) {
    $date = new DateTime($dateStr);
    $date->add(new DateInterval("PT1H"));
    return strftime("%H:%M", $date->getTimestamp());
}

function course_displayName($course) {
    return $course['name'] . ' — ' . $course['degreeName'] .  ($course['studyProgramName'] ? '(' . $course['studyProgramName'] . ')' : '') . 
           ' — ' . academic_year_to_year_of_study($course['academicYear']). '° anno';
 
 }

function academic_year_to_year_of_study($academicYear) {
    $comps = explode('/', $academicYear);
    return 2021 - intval($comps[0]);
}

function get_real_user_data($persId) {
    global $zeus_user_stmt;
    $zeus_user_stmt -> execute ([$persId]);
    return $zeus_user_stmt -> fetch();
}

function get_events_for_matricola($matricola) {
    global $dbh_prenotazione;
    $query = '
    SELECT DISTINCT t."udLogId", t.start, t.end
    FROM "TimeTable" t, "Lesson" l, "LessonTeacher" lt, "Teacher" tc
    WHERE t."lessonId" = l.id AND lt."lessonId" = l.id AND lt."teacherId" = tc.id
        AND tc."identificationNumber" = ?
    ORDER BY t."udLogId", t.start
    ';
    $stmt = $dbh_prenotazione -> prepare($query);
    $stmt -> execute([ $matricola ]);
    $results = $stmt -> fetchAll();
    return $results;
}

function check_event_for_teacher($udLogId, $identificationNumber) {
    global $dbh_prenotazione;
    $query = '
    SELECT 1
    FROM "Lesson" l, "LessonTeacher" lt, "Teacher" tc
    WHERE  lt."lessonId" = l.id AND lt."teacherId" = tc.id
          AND l."upId" = ? AND tc."identificationNumber" = ?
    ';
    $stmt = $dbh_prenotazione -> prepare($query);
    $stmt -> execute([ $udLogId, $identificationNumber ]);
    $result = $stmt -> fetch();
    return $result;
}

function get_event($udLogId, $start) {
    global $dbh_prenotazione;
    $query = '
    SELECT t."udLogId", t.start, t.end, c.seats, c.name as "classroomName"
    FROM "TimeTable" t, "Lesson" l, "Classroom" c
    WHERE t."lessonId" = l.id AND t."classroomId" = c.id 
          AND t."udLogId"= ? and t.start = ?
    ';
    $stmt = $dbh_prenotazione -> prepare($query);
    $stmt -> execute([ $udLogId, $start ]);
    $result = $stmt -> fetch();
    return $result;
}

function get_courses_for_udlogid($udlogid) {
    global $dbh_prenotazione;
    $query = '
    SELECT l."afId", l.name as name, l."academicYear", d.name as "degreeName", s.name as "studyProgramName"
    FROM "Lesson" l 
         JOIN "Degree" d ON l."degreeId" = d.id 
         LEFT JOIN "StudyProgram" s ON l."studyProgramId" = s.id 
    WHERE "upId"= ?
    ';
    $stmt = $dbh_prenotazione -> prepare($query);
    $stmt -> execute([ $udlogid ]);
    $results = $stmt -> fetchAll();
    return $results;
}

function get_reservations($udLogId, $start) {
    global $dbh_prenotazione;
    $query = '
    SELECT u.id, u."personalId", u.handicap, u.companions, r."createdAt", r.status
    FROM "TimeTable" t, "Reservation" r, "User" u
    WHERE r."timeTableId" = t.id AND r."userId" = u.id
          AND t."udLogId" = ? AND t.start = ?
    ORDER BY  r."createdAt"
    ';
    $stmt = $dbh_prenotazione -> prepare($query);
    $stmt -> execute([ $udLogId, $start ]);
    $results = $stmt -> fetchAll();
    return $results;
}

function get_reservations_hidebugs($udLogId, $start) {
    $reservations = get_reservations($udLogId, $start);
    $deleted = [];
    for ($i = 0; $i < count($reservations); $i++) {
        if (array_search($i, $deleted) !== FALSE) continue;
        $ri = $reservations[$i];
        if ($ri['status'] == 'canceled') continue;
        for ($j = $i; $j < count($reservations); $j++) {
            $rj = $reservations[$j];
            if ($rj['id'] == $ri['id'] && $rj['status'] == 'checkedIn') break;
        }
        if ($j >= count($reservations)) {
            for ($j = $i; $j < count($reservations); $j++) {
                $rj = $reservations[$j];
                if ($rj['id'] == $ri['id'] && $rj['status'] == 'accepted') break;
            }
        }
        if ($j >= count($reservations)) {
            for ($j = $i; $j < count($reservations); $j++) {
                $rj = $reservations[$j];
                if ($rj['id'] == $ri['id'] && $rj['status'] == 'received') break;
            }
        }
        for ($l = $i; $l < count($reservations); $l++) {
            $rl = $reservations[$l];
            if ($l <> $j && $rl['id'] == $ri['id'] && $rl['status'] <> 'canceled') $deleted[] = $l;
        }
    }
    foreach (array_reverse($deleted) as $i)
        array_splice($reservations, $i, 1);
    return $reservations;
}

function page_header($subtitle = '', $title = 'Didattica a distanza UdA - 2020/2021') {
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css"
        integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
    <link rel="stylesheet" href="css/teledidattica.css">
    <title><?= $title ?> - <?= $subtitle ?></title>
    <style> 
    .titoletto {font-weight: bold; font-variant: small-caps;}
    </style>
</head>
<body>
    <div class="mb-4 container">
        <div class="pb-2 mt-4 mb-4 border-bottom">
            <div class="row">
                <div class="col-md-2 my-auto">
                    <img class="icon" src="<?= BASE ?>/img/logo.png" alt="UdA logo">
                </div>
                <div class="col-md-10 my-auto">
                    <h1><?= $title ?></h1>
                    <?php if ($subtitle != '') { ?>
                    <h3><?= $subtitle ?></h3>
                    <?php } ?>
                </div>
            </div>
        </div>
<?php
}

function page_footer($onloadfunc = '') {
?>
    </div>

    <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"
        integrity="sha256-CSXorXvZcTkaix6Yvo6HppcZGetbYMGWSFlBw8HfCJo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
        integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo"
        crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js"
        integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6"
        crossorigin="anonymous"></script>
    <?php if ($onloadfunc != '') { ?>
        <script>$(document).ready(<?= $onloadfunc ?>);</script>
    <?php } ?>
 </body>

</html>
<?php
}

function redirect_browser($url) {
    header('Location: '.dirname($_SERVER['PHP_SELF']).'/'.$url);
}
?>
