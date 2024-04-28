<?php

  require $_SERVER['DOCUMENT_ROOT']."inc/init.php";

  if (!$staff) {
    redirect('/');
  }

  // all actions from the 'manage schedules' table actions column
  if ($_POST['schedule_id']) {
    $change_sched = $db->row("SELECT * FROM schedules WHERE site_id = ".$site->ID." AND id = ".(int)$_POST['schedule_id']);
    if ($change_sched) {
      if ($_POST['set_active']) {
        $db->query("UPDATE schedules SET active = 0 WHERE site_id = ".$site->ID);
        $db->query("UPDATE schedules SET active = 1 WHERE site_id = ".$site->ID." AND id = $change_sched[id]");
        $_SESSION['success'] = "<b>".html($change_sched['name'])."</b> is now the active schedule";
      }
      else if ($_POST['delete']) {
        if ($change_sched['active']) {
          $_SESSION['error'] = "Can't delete the active schedule.";
        }
        else {
          $db->query("DELETE FROM read_dates WHERE schedule_date_id IN(
            SELECT id FROM schedule_dates WHERE schedule_id = $change_sched[id]
          )");
          $db->query("DELETE FROM schedule_dates WHERE schedule_id = $change_sched[id]");
          $db->query("DELETE FROM schedules WHERE site_id = ".$site->ID." AND id  = $change_sched[id]");
          $_SESSION['success'] = 'Schedule deleted';
          redirect('?');
        }
      }
      else if ($_POST['duplicate']) {
        $new_id = $db->insert("schedules", [
          'site_id' => $site->ID,
          'name' => "Copy of ".$change_sched['name'],
          'start_date' => $change_sched['start_date'],
          'end_date' => $change_sched['end_date'],
          'active' => 0
        ]);
        $db->query("
          INSERT INTO schedule_dates (schedule_id, date, passage)
            SELECT $new_id, date, passage
            FROM schedule_dates WHERE schedule_id = $change_sched[id]");
        $_SESSION['success'] = "Schedule duplicated.&nbsp;<a href='/admin/schedules?calendar_id=$new_id'>Edit new schedule's calendar &gt;&gt;</a>";
        redirect();
      }
      else {
        $start_date = strtotime($change_sched['start_date']);
        if ($new_date = strtotime($_POST['start_date']))
          $start_date = $new_date;
        $end_date = strtotime($change_sched['end_date']);
        if ($new_date = strtotime($_POST['end_date']))
          $end_date = $new_date;

        $start_date_obj = new Datetime('@'.$start_date);
        $end_date_obj = new Datetime('@'.$end_date);
        $interval = $start_date_obj->diff($end_date_obj);
        if ($interval->y > 3) {
          $_SESSION['error'] = "Schedule must be shorter 4 years";
        }
        else if ($start_date >= $end_date) {
          $_SESSION['error'] = "Start date must be before end date.";
        }
        else if (!$_POST['name']) {
          $_SESSION['error'] = "Schedule must have a name.";
        }
        else if (
          ($change_sched['start_date'] != date('Y-m-d', $start_date) || $change_sched['end_date'] != date('Y-m-d', $end_date))
           && $db->col("
                SELECT COUNT(*)
                FROM read_dates rd
                JOIN schedule_dates sd ON sd.id = rd.schedule_date_id
                WHERE sd.schedule_id = $change_sched[id]")) {
          $_SESSION['error'] = "This schedule has already been started by some readers. You can no longer change the start and end dates.";
        }
        else {
          $db->update("schedules", [
            'name' => $_POST['name'],
            'start_date' => date('Y-m-d', $start_date),
            'end_date' => date('Y-m-d', $end_date)
          ], "id = $change_sched[id]");
          $_SESSION['success'] = "Saved schedule";
        }   
      }
    }
    redirect();
  }
  else if ($_POST['new_schedule']) {
    $start_date = strtotime($_POST['start_date']);
    $end_date = strtotime($_POST['end_date']);
    if (!$start_date || !$end_date || $start_date >= $end_date) {
      $_SESSION['error'] = "Start date must be before end date.";
    }
    else if (!$_POST['name']) {
      $_SESSION['error'] = "Schedule must have a name.";
    }
    else {
      $new_id = $db->insert('schedules', [
        'site_id' => $site->ID,
        'name' => $_POST['name'],
        'start_date' => date('Y-m-d', $start_date),
        'end_date' => date('Y-m-d', $end_date),
        'active' => 0
      ]);
      $_SESSION['success'] = "Created schedule.&nbsp;<a href='/admin/schedules?calendar_id=$new_id'>Edit new schedule's calendar &gt;&gt;</a>";
      redirect("?edit=$new_id");
    }
  }
  else if ($_REQUEST['calendar_id']) {
    require $_SERVER['DOCUMENT_ROOT']."inc/calendar.php";
  }
  else {
    $page_title = "Manage Schedules";
    $hide_title = true;
    $add_to_head .= "
    <link rel='stylesheet' href='/css/admin.css' media='screen'>";
    require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";

    echo admin_navigation();

    if ($_GET['new_schedule']) {
      // create new schedule
      echo "<p><a href='/admin/schedules'>&lt;&lt; Back to schedules</a></p>";
      
      echo "<h5>Create New Schedule</h5>";
      echo "<form method='post'>
        <input type='hidden' name='new_schedule' value='1'>

        <label>Start date: <input type='date' name='start_date'></label>
        <label>End date: <input type='date' name='end_date'></label>
        <label>Name: <input type='text' name='name' minlength='1'></label>
        <button type='submit'>Save</button>
      </form>";
    }
    else if ($edit_sched = $db->row("SELECT * FROM schedules WHERE id = ".(int)$_GET['edit'])) {
      // viewing single schedule
      echo "<p><a href='/admin/schedules'>&lt;&lt; Back to schedules</a></p>";

      $start_date = new Datetime($edit_sched['start_date']);
      $end_date = new Datetime($edit_sched['end_date']);
      echo "<h5>Editing '".html($edit_sched['name'])."' schedule</h5>";
      echo "<form method='post'>
        <input type='hidden' name='schedule_id' value='$edit_sched[id]'>

        <label>Start date: <input type='date' name='start_date' value='".$start_date->format('Y-m-d')."'></label>
        <label>End date: <input type='date' name='end_date' value='".$end_date->format('Y-m-d')."'></label>
        <label>Name: <input type='text' name='name' minlength='1' value='".html($edit_sched['name'])."'></label>
        <button type='submit'>Save</button>
        <button type='submit' ".($edit_sched['active'] ? 'disabled' : '')." name='delete' value='1' onclick='return confirm(`Are you sure you want to delete $edit_sched[name]? This can NEVER be recovered. All existing reading progress, INCLUDING BADGES, will be permanently lost.`)'>Delete Schedule</button>
        <button type='button' onclick='window.location = \"/admin/schedules?calendar_id=$edit_sched[id]\"'>Edit Calendar</button>
      </form>";
    }
    else {
      // all schedules summary
      $schedules = $db->$db->select("SELECT * FROM schedules WHERE site_id = ".$site->ID." ORDER BY active DESC, start_date DESC");
      echo "<p><button type='button' onclick='window.location = `?new_schedule=1`'>+ Create Schedule</button></p>
      <p>Click a Schedule's name to edit its start and end dates</p>";
      echo "<table>
        <thead>
          <tr>
            <th data-sort='name'>
              Name
            </th>
            <th data-sort='start'>
              Start
            </th>
            <th data-sort='end'>
              End
            </th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>";
      foreach($schedules as $schedule) {
        echo "
          <tr class='".($schedule['active'] ? 'active' : '')."'>
            <td data-name><a href='?edit=$schedule[id]'><small>".html($schedule['name'])."</small></a></td>
            <td data-start='$schedule[start_date]'><small>".date('F j, Y', strtotime($schedule['start_date']))."</small></td>
            <td data-end='$schedule[end_date]'><small>".date('F j, Y', strtotime($schedule['end_date']))."</small></td>
            <td>
              <form method='post'>
                <small>
                  <input type='hidden' name='schedule_id' value='$schedule[id]'>
                  <button type='submit' name='set_active' value='1' ".($schedule['active'] ? 'disabled' : '').">Set active</button>
                  <button type='submit' name='duplicate' value='1'>Duplicate</button>
                  <button type='button' onclick='window.location = `/admin/schedules?calendar_id=$schedule[id]`'>Edit Calendar</button>
                </small>
              </form>
            </td>
          </tr>";
      }
    
      echo "
        </tbody>
      </table>";

      echo "
      <script src='/js/tableSort.js'></script>
      <script src='/js/schedules.js'></script>";
    }
  }

  require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";