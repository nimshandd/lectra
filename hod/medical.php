<?php
require_once __DIR__.'/../includes/auth.php';requireHOD();$p=db();$d=$_SESSION['user']['department_id'];$f=['student'=>(int)($_GET['student']??0),'batch'=>(int)($_GET['batch']??0),'semester'=>(int)($_GET['semester']??0),'subject'=>(int)($_GET['subject']??0),'lecturer'=>(int)($_GET['lecturer']??0),'type'=>$_GET['type']??'','from'=>$_GET['from']??'','to'=>$_GET['to']??''];
if($_SERVER['REQUEST_METHOD']==='POST'){verifyCsrf();$record=(int)$_POST['record_id'];$action=$_POST['action']??'';$note=trim($_POST['note']??'');$expected=$action==='undo'?'medical':'absent';$q=$p->prepare('SELECT ar.id FROM attendance_records ar JOIN attendance_sessions s ON s.id=ar.attendance_session_id JOIN batch_subjects bs ON bs.id=s.batch_subject_id JOIN batches b ON b.id=bs.batch_id WHERE ar.id=? AND ar.status=? AND b.department_id=?');$q->execute([$record,$expected,$d]);if(!$q->fetch())flash('This record is no longer available.','error');else try{$p->beginTransaction();if($action==='undo'){$p->prepare("UPDATE attendance_records SET status='absent' WHERE id=?")->execute([$record]);$p->prepare("INSERT INTO medical_adjustments(attendance_record_id,previous_status,action,note,adjusted_by) VALUES(?,'medical','reverted',?,?)")->execute([$record,$note?:null,$_SESSION['user']['id']]);flash('Medical adjustment undone.');}else{$p->prepare("UPDATE attendance_records SET status='medical' WHERE id=?")->execute([$record]);$p->prepare("INSERT INTO medical_adjustments(attendance_record_id,previous_status,action,note,adjusted_by) VALUES(?,'absent','converted',?,?)")->execute([$record,$note?:null,$_SESSION['user']['id']]);flash('Absence converted to Medical.');}$p->commit();}catch(Throwable $e){if($p->inTransaction())$p->rollBack();flash('Could not save the adjustment.','error');}redirect('/hod/medical.php');}
$q=$p->prepare('SELECT st.id,st.registration_number,st.student_name FROM students st JOIN batches b ON b.id=st.batch_id WHERE b.department_id=? ORDER BY st.registration_number');$q->execute([$d]);$students=$q->fetchAll();$q=$p->prepare('SELECT * FROM batches WHERE department_id=? ORDER BY batch_name');$q->execute([$d]);$batches=$q->fetchAll();$semesters=$p->query('SELECT * FROM semesters ORDER BY semester_number')->fetchAll();$q=$p->prepare('SELECT * FROM subjects WHERE department_id=? ORDER BY subject_code');$q->execute([$d]);$subjects=$q->fetchAll();$q=$p->prepare("SELECT * FROM users WHERE department_id=? AND role='lecturer' ORDER BY name");$q->execute([$d]);$lecturers=$q->fetchAll();
$base=' FROM attendance_records ar JOIN students st ON st.id=ar.student_id JOIN attendance_sessions s ON s.id=ar.attendance_session_id JOIN batch_subjects bs ON bs.id=s.batch_subject_id JOIN batches b ON b.id=bs.batch_id JOIN subjects su ON su.id=bs.subject_id JOIN semesters se ON se.id=bs.semester_id JOIN users l ON l.id=bs.lecturer_id ';$where=' WHERE b.department_id=?';$args=[$d];foreach(['student'=>'st.id','batch'=>'b.id','semester'=>'se.id','subject'=>'su.id','lecturer'=>'l.id'] as $key=>$column)if($f[$key]){$where.=" AND $column=?";$args[]=$f[$key];}if(in_array($f['type'],['lecture','practical'],true)){$where.=' AND s.session_type=?';$args[]=$f['type'];}if($f['from']){$where.=' AND s.attendance_date>=?';$args[]=$f['from'];}if($f['to']){$where.=' AND s.attendance_date<=?';$args[]=$f['to'];}
$q=$p->prepare('SELECT ar.id,st.registration_number,st.student_name,b.batch_code,se.semester_name,s.attendance_date,su.subject_code,su.subject_name,l.name lecturer,s.lecture_number '.$base.$where.' AND ar.status="absent" ORDER BY s.attendance_date DESC,st.registration_number');$q->execute($args);$absences=$q->fetchAll();$q=$p->prepare('SELECT ar.id,st.registration_number,st.student_name,b.batch_code,se.semester_name,s.attendance_date,su.subject_code,su.subject_name,l.name lecturer,s.lecture_number,ma.note,ma.adjusted_at,u.name adjusted_by '.$base.' JOIN medical_adjustments ma ON ma.attendance_record_id=ar.id AND ma.action="converted" JOIN users u ON u.id=ma.adjusted_by '.$where.' AND ar.status="medical" ORDER BY ma.adjusted_at DESC');$q->execute($args);$medical=$q->fetchAll();$title='Medical Adjustments';require __DIR__.'/../includes/header.php';?>
<section class="panel"><h2>Medical attendance adjustments</h2><p>Medical counts as Present. Every conversion and undo remains in the audit history.</p><form method="get" class="inline-form"><label>Student<select name="student"><option value="">All</option><?php foreach($students as $x):?><option value="<?=$x['id']?>" <?=$f['student']==$x['id']?'selected':''?>><?=e($x['registration_number'].' — '.($x['student_name']?:''))?></option><?php endforeach?></select></label><label>Batch<select name="batch"><option value="">All</option><?php foreach($batches as $x):?><option value="<?=$x['id']?>" <?=$f['batch']==$x['id']?'selected':''?>><?=e($x['batch_name'])?></option><?php endforeach?></select></label><label>Semester<select name="semester"><option value="">All</option><?php foreach($semesters as $x):?><option value="<?=$x['id']?>" <?=$f['semester']==$x['id']?'selected':''?>><?=e($x['semester_name'])?></option><?php endforeach?></select></label><label>Subject<select name="subject"><option value="">All</option><?php foreach($subjects as $x):?><option value="<?=$x['id']?>" <?=$f['subject']==$x['id']?'selected':''?>><?=e($x['subject_code'])?></option><?php endforeach?></select></label><label>Lecturer<select name="lecturer"><option value="">All</option><?php foreach($lecturers as $x):?><option value="<?=$x['id']?>" <?=$f['lecturer']==$x['id']?'selected':''?>><?=e($x['name'])?></option><?php endforeach?></select></label><label>Type<select name="type"><option value="">All</option><option value="lecture" <?=$f['type']==='lecture'?'selected':''?>>Lecture</option><option value="practical" <?=$f['type']==='practical'?'selected':''?>>Practical</option></select></label><label>From<input type="date" name="from" value="<?=e($f['from'])?>"></label><label>To<input type="date" name="to" value="<?=e($f['to'])?>"></label><button>Filter</button></form></section>
<section class="panel medical-section" aria-labelledby="available-absences-title">
  <h2 id="available-absences-title">Available absences</h2>
  <div class="table-wrap medical-desktop-only">
    <table class="medical-data-table">
      <thead><tr><th scope="col">Student</th><th scope="col">Batch</th><th scope="col">Date</th><th scope="col">Subject</th><th scope="col">Lecturer</th><th scope="col">Lecture</th><th scope="col">Reference</th><th scope="col">Action</th></tr></thead>
      <tbody><?php foreach($absences as $a): $convertFormId='convert-absence-'.$a['id']; ?>
        <tr>
          <td><?=e($a['registration_number'].' — '.($a['student_name']?:'Name not entered'))?></td>
          <td><?=e($a['batch_code'])?></td>
          <td><?=e($a['attendance_date'])?></td>
          <td><?=e($a['subject_code'].' — '.$a['subject_name'])?></td>
          <td><?=e($a['lecturer'])?></td>
          <td><?=str_pad((string)$a['lecture_number'],2,'0',STR_PAD_LEFT)?></td>
          <td><input form="<?=e($convertFormId)?>" name="note" maxlength="255" placeholder="Certificate verified" aria-label="Medical reference for <?=e($a['registration_number'])?>"></td>
          <td><form id="<?=e($convertFormId)?>" method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="record_id" value="<?=$a['id']?>"><input type="hidden" name="action" value="convert"><button type="submit">Mark Medical</button></form></td>
        </tr>
      <?php endforeach?></tbody>
    </table>
  </div>
  <div class="medical-mobile-list" role="list">
    <?php foreach($absences as $a): ?>
      <article class="medical-card" role="listitem">
        <header class="medical-card-header">
          <div><h3><?=e($a['student_name']?:'Name not entered')?></h3><p class="medical-registration"><?=e($a['registration_number'])?></p></div>
          <time datetime="<?=e($a['attendance_date'])?>"><?=e(date('d M Y',strtotime($a['attendance_date'])))?></time>
        </header>
        <dl class="medical-summary">
          <div><dt>Batch</dt><dd><?=e($a['batch_code'])?></dd></div>
          <div><dt>Subject</dt><dd><?=e($a['subject_code'])?><span><?=e($a['subject_name'])?></span></dd></div>
        </dl>
        <details class="medical-more">
          <summary>More details</summary>
          <dl><div><dt>Lecturer</dt><dd><?=e($a['lecturer'])?></dd></div><div><dt>Lecture</dt><dd><?=str_pad((string)$a['lecture_number'],2,'0',STR_PAD_LEFT)?></dd></div></dl>
        </details>
        <form method="post" class="medical-card-action">
          <input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="record_id" value="<?=$a['id']?>"><input type="hidden" name="action" value="convert">
          <label>Reference <span>(optional)</span><input name="note" maxlength="255" placeholder="Certificate verified"></label>
          <button type="submit">Mark Medical</button>
        </form>
      </article>
    <?php endforeach?>
  </div>
  <?php if(!$absences):?><p class="medical-empty">No absent records match these filters.</p><?php endif?>
</section>

<section class="panel medical-section" aria-labelledby="current-adjustments-title">
  <h2 id="current-adjustments-title">Current medical adjustments</h2>
  <div class="table-wrap medical-desktop-only">
    <table class="medical-data-table">
      <thead><tr><th scope="col">Student</th><th scope="col">Batch</th><th scope="col">Date</th><th scope="col">Subject</th><th scope="col">Converted by</th><th scope="col">Converted on</th><th scope="col">Reference</th><th scope="col">Action</th></tr></thead>
      <tbody><?php foreach($medical as $m):?>
        <tr>
          <td><?=e($m['registration_number'].' — '.($m['student_name']?:'Name not entered'))?></td>
          <td><?=e($m['batch_code'])?></td>
          <td><?=e($m['attendance_date'])?></td>
          <td><?=e($m['subject_code'].' — '.$m['subject_name'])?></td>
          <td><?=e($m['adjusted_by'])?></td>
          <td><?=e($m['adjusted_at'])?></td>
          <td><?=e($m['note']?:'—')?></td>
          <td><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="record_id" value="<?=$m['id']?>"><input type="hidden" name="action" value="undo"><button class="button danger" type="submit">Undo Medical</button></form></td>
        </tr>
      <?php endforeach?></tbody>
    </table>
  </div>
  <div class="medical-mobile-list" role="list">
    <?php foreach($medical as $m): ?>
      <article class="medical-card" role="listitem">
        <header class="medical-card-header">
          <div><h3><?=e($m['student_name']?:'Name not entered')?></h3><p class="medical-registration"><?=e($m['registration_number'])?></p></div>
          <span class="medical-status">Medical</span>
        </header>
        <dl class="medical-summary">
          <div><dt>Batch</dt><dd><?=e($m['batch_code'])?></dd></div>
          <div><dt>Date</dt><dd><?=e(date('d M Y',strtotime($m['attendance_date'])))?></dd></div>
          <div class="medical-summary-subject"><dt>Subject</dt><dd><?=e($m['subject_code'])?><span><?=e($m['subject_name'])?></span></dd></div>
        </dl>
        <details class="medical-more">
          <summary>More details</summary>
          <dl><div><dt>Lecturer</dt><dd><?=e($m['lecturer'])?></dd></div><div><dt>Lecture</dt><dd><?=str_pad((string)$m['lecture_number'],2,'0',STR_PAD_LEFT)?></dd></div><div><dt>Converted by</dt><dd><?=e($m['adjusted_by'])?></dd></div><div><dt>Converted on</dt><dd><?=e($m['adjusted_at'])?></dd></div><div><dt>Reference</dt><dd><?=e($m['note']?:'No reference added')?></dd></div></dl>
        </details>
        <form method="post" class="medical-card-action">
          <input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="record_id" value="<?=$m['id']?>"><input type="hidden" name="action" value="undo">
          <button class="button danger" type="submit">Undo Medical</button>
        </form>
      </article>
    <?php endforeach?>
  </div>
  <?php if(!$medical):?><p class="medical-empty">No active medical adjustments match these filters.</p><?php endif?>
</section><?php require __DIR__.'/../includes/footer.php';?>
