<?php require_once 'init.php'; ?>
<?php page_header('Tracking studenti (procedura ASL)','Lista prenotazioni'); ?>
<?php
if (array_search($uid, TRACKING_USERS) === FALSE) {
    redirect_browser('');
}

if (! isset($_GET['username'])) {
?>
    <div class="alert alert-primary">
    Utilizzare con la sintassi
    <code>asl.php?username=&lt;username&gt;[&amp;date=&lt;timestamp&gt;&amp;days=&lt;days&gt;&amp;scope=&lt;scope&gt;]</code>
    dove <ul>
    <li><em>username</em>: username della persona di cui tracciare i contatti. Può essere sia un docente che uno studente. Normalmente è
    la matricola.
    <li><em>timestamp / days</em>: il sistema considera tutte le lezioni a cui lo studente o il docente è stato presente, e che iniziano
    nell'intervallo di tempo che termina in <code>timestamp</code> e inizia <code>24*days</code> ore prima. Il formato di <code>timestamp</code>
    deve essere riconosciuto dalla classe <code>DateTimeImmutable</code> di PHP. Ad esempio, <code>2020-10-16</code> indica
    il 16 ottobre 2020 alle ore 00:00. (default timestamp: istante attuale, default days: 2)
    <li><em>scope</em> è <code>aula</code> o <code>lezione</code>. Se si specifica <code>lezione</code>, il sistema individua tutti gli studenti
    o docenti che hanno partecipato alla stessa lezione dove si trovava anche la persona da tracciare. Se si specifica <code>aula</code> vengono
    individuati tutti coloro che, in dato giorno, si sono trovati nella stessa aula dove si è trovato precedentemente la persona da
    tracciare, anche se i due non hanno mai partecipato ad una lezione in comune. (default: <code>lezione</code>);
    </ul>
    </div>
<?php
    die();
}

$dateend = new DateTimeImmutable(isset($_GET['date']) ? $_GET['date'] : "now", new DateTimeZone('Europe/Rome'));
$days = isset($_GET['days']) ? intval($_GET['days']) : 2;
$datestart = $dateend->sub(new DateInterval("P${days}D"));
$scope = isset($_GET['scope']) ? $_GET['scope'] : 'lezione';

$contact_students = get_tracked_students_from_student($_GET['username'], $datestart, $dateend, $scope);
if (count($contact_students) == 0) $contact_students = get_tracked_students_from_teacher($_GET['username'], $datestart, $dateend, $scope);

$contact_teachers = get_tracked_teachers_from_student($_GET['username'], $datestart, $dateend, $scope);
if (count($contact_teachers) == 0) $contact_teachers = get_tracked_teachers_from_teacher($_GET['username'], $datestart, $dateend, $scope);

if (isset($_GET['format']) && $_GET['format'] == 'csv') {
    ob_clean();
    header('Content-Type: application/csv');
    header('Content-Disposition: attachment; filename="tracking.csv";');
    $f = fopen('php://output', 'w');
    $line = ['USERNAM', 'COGNONE', 'NOME', 'COMUNE_RESIDENZA', 'PROVINCIA_RESIDENZA', 'COMUNE_DOMICILIO', 'PROVINCIA_DOMICILIO',
             'CELLULARE', 'EMAIL_ATE', 'EMAIL', 'VIVE_CON', 'ACCOMPAGNATORE', 'AULA', 'DATAORA'];
    fputcsv($f, $line);
    foreach ($contact_students as $contact) {
        $real_user = get_real_student_data($contact['personalId']);
        $line = [ $contact['username'], $real_user['COGNOME'], $real_user['NOME'], $real_user['COMUNE_RESIDENZA'],
                  $real_user['PROVINCIA_RESIDENZA'],  $real_user['COMUNE_DOMICILIO'],
                  $real_user['PROVINCIA_DOMICILIO'], $real_user['CELLULARE'], $real_user['EMAIL_ATE'],
                  $real_user['EMAIL'],
                  livesWith_displayname($contact['livesWith']),
                  ($contact['handicap'] && $contact['companions'] == 2 ? 'sì' : 'no'),
                  $contact['classroomName'],
                  fix_date($contact['start'])-> format(DateTimeInterface::ATOM) ];
        fputcsv($f, $line);
    }

    foreach ($contact_teachers as $contact) {
        $line = [ $contact['identificationNumber'], $contact['surname'], $contact['name'], '',
                  '', '', '' , '', $contact['email'], '', '', 'no',
                    $contact['classroomName'],
                    fix_date($contact['start'])-> format(DateTimeInterface::ATOM) ];
        fputcsv($f, $line);
    }
    fclose($f);
    die();
}
?>
<h4>Tracciamento studente</h4>
<hr>
<p>
<a href="<?= $_SERVER['REQUEST_URI'] ?>&amp;format=csv">(Download CSV)</a>
</p>
<h4>Altri studenti</h4>
<ol>
<?php foreach ($contact_students as $contact) { ?>
    <?php $real_user = get_real_student_data($contact['personalId']); ?>
    <li>
        <?= $real_user['COGNOME']?> <?= $real_user['NOME']?> <?php reservation_handicap($contact); ?>
        (username:  <?= $contact['username']?>)
        <?= datetime_displayname($contact['start']) ?> aula <?= $contact['classroomName'] ?>
    </li>
<?php } ?>
</ol>
<h4>Docenti</h4>
<ol>
<?php foreach ($contact_teachers as $contact) { ?>
    <li>
        <?= $contact['surname']?> <?= $contact['name'] ?> (username:  <?= $contact['identificationNumber']?>)
        <?= datetime_displayname($contact['start']) ?> aula <?= $contact['classroomName'] ?>
    </li>
<?php } ?>
</ol>

<?php page_footer(); ?>
