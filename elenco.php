<?php require_once 'init.php'; ?>
<?php page_header('Lista prenotazioni'); ?>
<?php
$event = isset($_GET['tt']) ? get_event_from_timetableid($_GET['tt']) : get_event($_GET['udLogId'], $_GET['start']);
if (! $debug && array_search($uid, SUPER_USERS) === FALSE && ! check_event_for_teacher($event['udLogId'], $uid))
    redirect_browser('');
?>
<h4>Dati lezione</h4>
<hr>
<p>
<strong>
<?= explode(';',$_SERVER['cn'])[0] ?><br>
<?php $courses = get_courses_for_udlogid($event['udLogId']); ?>
<?php
foreach ($courses as $course) {
    echo htmlspecialchars(course_displayname($course)), "<br>\n";
}
?>
</strong>
<?= datetime_displayname ($event['start']) ?> - <?= time_displayname($event['end']) ?>  <br>
aula <?= $event['classroomName'] ?><br>
numero di posti in aula <?= $event['seats'] ?> <br>
</p>
<?php if ($debug_visual) { ?>
    <?php $reservations = get_reservations($event['udLogId'], $event['start']); ?>
    <?php if (count($reservations) == 0) { ?>
        <h4>Non ci sono studenti prenotati</h4>
    <?php } else {  ?>
        <h4>Studenti prenotati</h4>
        <hr>
        <ol>
        <?php foreach ($reservations as $reservation) { ?>
            <?php $real_user = get_real_student_data($reservation['personalId']); ?>
            <li>
                <?php if ($reservation['status'] == 'canceled') echo "<del>" ?>
                <?= $real_user['COGNOME']?> <?= $real_user['NOME']?> (id: <?= $reservation['id']?>, matricola:  <?= $reservation['username']?>):
                <?php reservation_handicap($reservation); ?>
                created at <?= $reservation['createdAt'] ?> <strong><?=  $reservation['status'] ?></strong>
                <?php if ($reservation['status'] == 'canceled') echo "</del>" ?>
            </li>
            <?php } ?>
        </ol>
    <?php } ?>
<?php } else { ?>
    <?php $all_reservations = get_reservations_hidebugs($event['udLogId'], $event['start']); ?>
    <?php $reservations = array_filter($all_reservations, function ($r) { return $r['status'] == 'accepted' || $r['status'] == 'checkedIn'; }); ?>
    <?php if (count($reservations) == 0) { ?>
        <h4>Non ci sono studenti prenotati</h4>
    <?php } else {  ?>
        <h4>Studenti con prenotazione accettata (<?= count_reservations($reservations)?>)</h4>
        <hr>
        <ol>
        <?php foreach ($reservations as $reservation) { ?>
            <?php $real_user = get_real_student_data($reservation['personalId']); ?>
            <li>
                <?= $real_user['COGNOME']?> <?= $real_user['NOME']?>
                <?php reservation_handicap($reservation); ?>
                <?php if ($reservation['status'] == 'checkedIn') echo "<strong>(presente)</strong>"; ?>
            </li>
            <?php } ?>
        </ol>
    <?php } ?>
    <?php $reservations = array_filter($all_reservations, function ($r) { return $r['status'] == 'received'; }); ?>
    <?php if (count($reservations) == 0) { ?>
        <h4>Non ci sono studenti in lista di attesa</h4>
    <?php } else {  ?>
        <h4>Studenti in lista d'attesa (<?= count_reservations($reservations)?>)</h4>
        <p>Le liste d'attesa vengono elaborate circa ogni 30 minuti.</p>
        <hr>
        <ol>
        <?php foreach ($reservations as $reservation) { ?>
            <?php $real_user = get_real_student_data($reservation['personalId']); ?>
            <li>
                <?= $real_user['COGNOME']?> <?= $real_user['NOME']?>
                <?php reservation_handicap($reservation); ?>
            </li>
            <?php } ?>
        </ol>
    <?php } ?>
<?php } ?>

<div class="alert alert-dark">
    L'indicazione <strong>(+1)</strong> accanto al nome di uno studente indica che si tratta di uno studente con disabilità che ha richiesto di partecipare
    alla lezione con un accompagnatore. L'indicazione <strong>(-1)</strong> indica invece uno studente con disabilità ammesso in sovrannumero che non occupa
    posto in aula.
</div>

<?php page_footer(); ?>
