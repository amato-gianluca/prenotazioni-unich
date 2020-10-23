<?php
require_once 'config.php';
ob_start();
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
    ob_clean();
    page_header('Lista prenotazioni');
    ?>
    <div class="alert alert-danger" role="alert">
    Si è verificato un errore non previsto. Si prega di contattare il responsabile del sito,
    prof. <a href="mailto:gianluca.amato@unich.it">Gianluca Amato</a>.
    </div>
    <?php
    page_footer();
}

if (array_search($uid, SUPER_USERS) === FALSE)
{
   set_error_handler ('error_handler');
}

function fix_date ($dateStr) {
    $date = new DateTimeImmutable($dateStr);
    $fixdate = $date < new DateTimeImmutable('2020-10-25') ?  $date -> sub(new DateInterval("PT1H"))  : $date;
    return $fixdate -> setTimezone(new DateTimeZone('Europe/Rome'));
}

function unfix_date($date) {
    $unfixdate = $date < new DateTimeImmutable('2020-10-25') ?  $date -> add(new DateInterval("PT1H")) : $date;
    return $unfixdate -> setTimezone(new DateTimeZone('UCT')) -> format(DateTimeInterface::ATOM);
}

function datetime_displayname($dateStr) {
    $date = fix_date($dateStr);
    return strftime("%a", $date->getTimestamp()) . " " . fix_date($dateStr)->format('d/m/Y H:i');
}

function time_displayname($dateStr) {
    return fix_date($dateStr)->format('H:i');
}

function course_displayName($course) {
    return $course['name'] . ' — ' . $course['degreeName'] .  ($course['studyProgramName'] ? '(' . $course['studyProgramName'] . ')' : '') .
           ' — ' . academic_year_to_year_of_study($course['academicYear']). '° anno';
 }

 function livesWith_displayname($livesWith) {
     switch ($livesWith) {
         case 'alone': return 'da solo';
         case 'with_parents': return 'con i genitori';
         case 'with_other_students': return 'con altri studenti';
         case 'undefined': return 'non specificato';
         default: return $livesWith;
     }
 }

function academic_year_to_year_of_study($academicYear) {
    $comps = explode('/', $academicYear);
    return 2021 - intval($comps[0]);
}

function count_reservations($reservations) {
    $result = 0;
    foreach ($reservations as $reservation) {
        $result += $reservation['handicap'] ? $reservation['companions'] : 1;
    }
    return $result;
}

function get_real_student_data($persId) {
    global $zeus_user_stmt;
    $zeus_user_stmt -> execute ([$persId]);
    return $zeus_user_stmt -> fetch();
}

function get_events_for_matricola($matricola, $time=1) {
    global $dbh_prenotazione;
    if (array_key_exists($matricola, ADMINISTRATIVE_USERS)) {
        $cdss = ADMINISTRATIVE_USERS[$matricola];
        $qMarks = str_repeat('?,', count($cdss) - 1) . '?';
        $query = '
        SELECT DISTINCT t."udLogId", t.start, t.end
        FROM "TimeTable" t, "Lesson" l, "Degree" d
        WHERE t."lessonId" = l.id AND l."degreeId" = d.id
            AND d.code IN (' . $qMarks .')
        ORDER BY t."udLogId", t.start
        ';
        $stmt = $dbh_prenotazione -> prepare($query);
        $stmt -> execute($cdss);
    } else {
        $pastFuture = ($time == -1) ? ' and date(t.start) < date(now()) ' : ' and date(t.start) >= date(now()) ';
        $query = '
        SELECT DISTINCT t."udLogId", t.start, t.end
        FROM "TimeTable" t, "Lesson" l, "LessonTeacher" lt, "Teacher" tc
        WHERE t."lessonId" = l.id AND lt."lessonId" = l.id AND lt."teacherId" = tc.id
            AND tc."identificationNumber" = ?' . $pastFuture .
        ' ORDER BY t."udLogId", t.start
        ';
        $stmt = $dbh_prenotazione -> prepare($query);
        $stmt -> execute([ $matricola ]);
    }
    $results = $stmt -> fetchAll();
    return $results;
}

function check_event_for_teacher($udLogId, $identificationNumber) {
    global $dbh_prenotazione;
    if (array_key_exists($identificationNumber, ADMINISTRATIVE_USERS)) {
        $cdss = ADMINISTRATIVE_USERS[$identificationNumber];
        $qMarks = str_repeat('?,', count($cdss) - 1) . '?';
        $query = '
        SELECT 1
        FROM "Lesson" l, "Degree" d
        WHERE l."degreeId" = d.id
              AND l."upId" = ? AND d.code IN (' . $qMarks .');
        ';
        $stmt = $dbh_prenotazione -> prepare($query);
        $stmt -> execute(array_merge([$udLogId], $cdss));
    } else {
        $query = '
        SELECT 1
        FROM "Lesson" l, "LessonTeacher" lt, "Teacher" tc
        WHERE  lt."lessonId" = l.id AND lt."teacherId" = tc.id
              AND l."upId" = ? AND tc."identificationNumber" = ?
        ';
        $stmt = $dbh_prenotazione -> prepare($query);
        $stmt -> execute([ $udLogId, $identificationNumber ]);
    }
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

function get_event_from_timetableid($id) {
    global $dbh_prenotazione;
    $query = '
    SELECT t."udLogId", t.start, t.end, c.seats, c.name as "classroomName"
    FROM "TimeTable" t, "Lesson" l, "Classroom" c
    WHERE t."lessonId" = l.id AND t."classroomId" = c.id
          AND t.id= ?
    ';
    $stmt = $dbh_prenotazione -> prepare($query);
    $stmt -> execute([ $id ]);
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
    SELECT u.id, u."personalId", u.username, u.handicap, u.companions, r."createdAt", r.status
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

function scope_condition($scope) {
    if ($scope == 'lezione')
        return 'tt1."udLogId" = tt2."udLogId" AND tt1.start = tt2.start';
    else
        return 'DATE(tt1.start)=DATE(tt2.start) AND tt2.start >= tt1.start AND tt1."classroomId" = tt2."classroomId"';
}

function get_tracked_students_from_student($matricola, $datestart, $dateend, $scope) {
    global $dbh_prenotazione;
    $query = '
    SELECT DISTINCT u2.*, tt2.start, c.name AS "classroomName"
    FROM "User" u1, "Reservation" r1, "TimeTable" tt1, "TimeTable" tt2, "Reservation" r2, "User" u2, "Classroom" c
    WHERE u1."personalId" = r1."personalId" AND r1."timeTableId" = tt1.id
          AND '. scope_condition($scope) . '
          AND tt2.id = r2."timeTableId" AND r2."personalId" = u2."personalId" AND tt2."classroomId" = c.id
          AND r1.status=\'checkedIn\' AND u1."username" = ? AND tt1.start >= ? AND tt1.start <= ?
          AND r2.status=\'checkedIn\'
          ORDER BY u2.username
    ';
    $stmt = $dbh_prenotazione -> prepare($query);
    $stmt -> execute([ $matricola, unfix_date($datestart), unfix_date($dateend) ]);
    $results = $stmt -> fetchAll();
    return $results;
}

function get_tracked_students_from_teacher($matricola, $datestart, $dateend, $scope) {
    global $dbh_prenotazione;
    $query = '
    SELECT DISTINCT u2.*, tt2.start, c.name AS "classroomName"
    FROM "Teacher" t1, "LessonTeacher" lt1, "TimeTable" tt1, "TimeTable" tt2, "Reservation" r2, "User" u2, "Classroom" c
    WHERE t1.id = lt1."teacherId" AND lt1."lessonId" = tt1."lessonId"
          AND ' . scope_condition($scope) . '
          AND tt2.id = r2."timeTableId" AND r2."personalId" = u2."personalId" AND tt2."classroomId" = c.id
          AND t1."identificationNumber" = ? AND tt1.start >= ? AND tt1.start <= ?
          AND r2.status=\'checkedIn\'
          ORDER BY u2.username
    ';
    $stmt = $dbh_prenotazione -> prepare($query);
    $stmt -> execute([ $matricola, unfix_date($datestart), unfix_date($dateend) ]);
    $results = $stmt -> fetchAll();
    return $results;
}

function get_tracked_teachers_from_student($matricola, $datestart, $dateend, $scope) {
    global $dbh_prenotazione;
    $query = '
    SELECT DISTINCT t2.*, tt2.start, c.name AS "classroomName"
    FROM "User" u1, "Reservation" r1, "TimeTable" tt1, "TimeTable" tt2, "LessonTeacher" lt2, "Teacher" t2, "Classroom" c
    WHERE u1."personalId" = r1."personalId" AND r1."timeTableId" = tt1.id
          AND ' . scope_condition($scope) . '
          AND tt2."lessonId" = lt2."lessonId" AND lt2."teacherId" = t2.id AND tt2."classroomId" = c.id
          AND r1.status=\'checkedIn\' AND u1."username" = ? AND tt1.start >= ? AND tt1.start <= ?
          ORDER BY t2.surname, t2.name, t2."identificationNumber"
    ';
    $stmt = $dbh_prenotazione -> prepare($query);
    $stmt -> execute([ $matricola, unfix_date($datestart), unfix_date($dateend) ]);
    $results = $stmt -> fetchAll();
    return $results;
}

function get_tracked_teachers_from_teacher($matricola, $datestart, $dateend, $scope) {
    global $dbh_prenotazione;
    $query = '
    SELECT DISTINCT t2.*, tt2.start, c.name AS "classroomName"
    FROM "Teacher" t1, "LessonTeacher" lt1, "TimeTable" tt1, "TimeTable" tt2, "LessonTeacher" lt2,  "Teacher" t2, "Classroom" c
    WHERE t1.id = lt1."teacherId" AND lt1."lessonId" = tt1."lessonId"
          AND '. scope_condition($scope) . '
          AND tt2."lessonId" = lt2."lessonId" AND lt2."teacherId" = t2.id AND tt2."classroomId" = c.id
          AND t1."identificationNumber" = ? AND tt1.start >= ? AND tt1.start <= ?
          ORDER BY t2.surname, t2.name, t2."identificationNumber", tt2.start
    ';
    $stmt = $dbh_prenotazione -> prepare($query);
    $stmt -> execute([ $matricola, unfix_date($datestart), unfix_date($dateend) ]);
    $results = $stmt -> fetchAll();
    return $results;
}

function reservation_handicap($reservation) {
    if ($reservation['handicap']) {
        if ($reservation['companions'] === 0) echo '<strong>(-1)</strong>';
        elseif ($reservation['companions'] === 2)  echo '<strong>(+1)</strong>';
    }
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
