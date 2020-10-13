<?php require_once 'init.php'; ?>
<?php page_header('Lista prenotazioni'); ?>
<h2>Docente: <?= explode(';',$_SERVER['cn'])[0] ?></h2>
<?php $events = get_events_for_matricola($uid); ?>
<?php if (count($events) == 0) {?>
  <h4>Non ci sono lezioni programmate in presenza.</h4>
<?php } else { ?>
<h4>Elenco lezioni</h4>
<p>Trovate qui sotto l'elenco di tutte le lezioni programmate dall'inizio dei corsi fino alla settimana corrente e alle quali
è assegnata un'aula. Non sono visualizzate le lezioni per le quali si prevede l'erogazione unicamente a distanza,
che non appaiono neanche agli studenti nella app.</p>
<ul>
    <?php foreach ($events as $event) { ?>
        <?php $courses = get_courses_for_udlogid($event['udLogId']); ?>
        <li class="event-item"><a href="elenco.php?udLogId=<?= urlencode($event['udLogId']) ?>&start=<?= urlencode($event['start']) ?>&<?= $debug_param ?>">
        <?php
        foreach ($courses as $course) { 
            echo htmlspecialchars(course_displayName($course)), "<br>\n";    
        }
        ?> 
        <?= datetime_displayname($event['start']) ?> - <?= time_displayname($event['end']) ?>
        </a></li>
    <?php } ?>
</ul>
<?php } ?>
<?php page_footer(); ?>
