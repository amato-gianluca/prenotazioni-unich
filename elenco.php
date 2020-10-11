<?php require_once 'init.php'; ?>
<?php page_header('Lista prenotazioni'); ?>
<?php 
$event = get_event($_GET['udLogId'], $_GET['start']);
if (! $debug &&  ! check_event_for_teacher($event['udLogId'], $uid))
    redirect_browser('');

function reservation_handicap($reservation) {
    if ($reservation['handicap']=='t') {
        if ($reservation['companions'] === 0) echo '<strong>(-1)</strong>';
        elseif ($reservation['companions'] === 2)  echo '<strong>(+1)</strong>';
    }
}
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
            <?php $real_user = get_real_user_data($reservation['personalId']); ?>
            <li>
                <?php if ($reservation['status'] == 'canceled') echo "<del>" ?>
                <?= $real_user['COGNOME']?> <?= $real_user['NOME']?> (id: <?= $reservation['id']?>): 
                <?php reservation_handicap($reservation); ?>
                created at <?= $reservation['createdAt'] ?> <strong><?=  $reservation['status'] ?></strong>
                <?php if ($reservation['status'] == 'canceled') echo "</del>" ?>
            </li>
            <?php } ?>
        </ol>
    <?php } ?>
<?php } else { ?>
    <?php $reservations = get_reservations_status($event['udLogId'], $event['start'], 'accepted'); ?>
    <?php if (count($reservations) == 0) { ?>
        <h4>Non ci sono studenti prenotati</h4>
    <?php } else {  ?>
        <h4>Studenti con prenotazione accettata (<?= count($reservations)?>)</h4>
        <hr>
        <ol>
        <?php foreach ($reservations as $reservation) { ?>
            <?php $real_user = get_real_user_data($reservation['personalId']); ?>
            <li>
                <?= $real_user['COGNOME']?> <?= $real_user['NOME']?>
                <?php reservation_handicap($reservation); ?>
            </li>
            <?php } ?>
        </ol>
    <?php } ?>
    <?php $reservations = get_reservations_status($event['udLogId'], $event['start'], 'received'); ?>
    <?php if (count($reservations) == 0) { ?>
        <h4>Non ci sono studenti in lista di attesa</h4>
    <?php } else {  ?>
        <h4>Studenti in lista d'attesa (<?= count($reservations)?>)</h4>
        <p>Le liste d'attesa vengono elaborate circa ogni 30 minuti.</p>
        <hr>
        <ol>
        <?php foreach ($reservations as $reservation) { ?>
            <?php $real_user = get_real_user_data($reservation['personalId']); ?>
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
